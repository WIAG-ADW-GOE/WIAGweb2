<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemReference;
use App\Entity\ItemProperty;
use App\Entity\ItemPropertyType;
use App\Entity\UrlExternal;
use App\Entity\Person;
use App\Entity\InputError;
use App\Entity\PersonRole;
use App\Entity\PersonRoleProperty;
use App\Entity\RolePropertyType;
use App\Entity\Authority;
use App\Entity\NameLookup;
use App\Entity\CanonLookup;
use App\Entity\UserWiag;
use App\Form\EditPersonFormType;
use App\Form\Model\PersonFormModel;
use App\Entity\Role;

use App\Service\EditPersonService;
use App\Service\UtilService;
use App\Service\AutocompleteService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class EditPersonController extends AbstractController {
    /** number of suggestions in autocomplete list */
    const SUGGEST_SIZE = 8;
    const EDIT_FORM_ID = 'edit_person_edit_form';

    private $editService;
    private $entityManager;
    private $autocomplete;

    public function __construct(EditPersonService $editService,
                                AutocompleteService $autocomplete,
                                EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        $this->autocomplete = $autocomplete;
        $this->editService = $editService;
    }

    /**
     * display query form for persons;
     *
     * @Route("/edit/person/query/{itemTypeId}", name="edit_person_query")
     */
    public function query(Request $request, int $itemTypeId) {

        $model = new PersonFormModel;
        // set defaults
        $edit_status_default_list = [
            '4' => 'fertig',
            '5' => 'online'
        ];
        $model->editStatus = [$edit_status_default_list[$itemTypeId]];
        $model->isOnline = true;
        $model->listSize = 5;
        $model->itemTypeId = $itemTypeId;

        $status_choices = $this->getStatusChoices($itemTypeId);

        $form = $this->createForm(EditPersonFormType::class, $model, [
            'statusChoices' => $status_choices
        ]);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        $person_list = array();
        if ($form->isSubmitted() && $form->isValid() && $model->isValid()) {

            $personRepository = $this->entityManager->getRepository(Person::class);
            $itemRepository = $this->entityManager->getRepository(Item::class);
            $canonLookupRepository = $this->entityManager->getRepository(CanonLookup::class);

            $limit = 0; $offset = 0; $online_only = false;
            $id_all = $itemRepository->personIds($model, $limit, $offset, $online_only);
            $count = count($id_all);

            //
            $offset = $request->request->get('offset');
            $page_number = $request->request->get('pageNumber');

            // set offset to page begin
            if (!is_null($offset)) {
                $offset = intdiv($offset, $model->listSize) * $model->listSize;
            } elseif (!is_null($page_number) && $page_number > 0) {
                $page_number = min($page_number, intdiv($count, $model->listSize) + 1);
                $offset = ($page_number - 1) * $model->listSize;
                // this may happen if elements were deleted
                while ($offset >= $count) {
                    $offset -= $model->listSize;
                }
            } else {
                $offset = 0;
            }

            $id_list = array_slice($id_all, $offset, $model->listSize);

            $person_list = $personRepository->findList($id_list);

            // add empty role, reference and url external if not present
            $authorityRepository = $this->entityManager->getRepository(Authority::class);
            $auth_list = $authorityRepository->findList(Authority::coreIDs());
            foreach ($person_list as $person) {
                $person->extractSeeAlso();
                $person->addEmptyDefaultElements($auth_list);
            }

            $template_params = [
                'itemTypeId' => $itemTypeId,
                'personList' => $person_list,
                'form' => $form,
                'error_list' => $model->getInputError(),
                'count' => $count,
                'offset' => $offset,
                'pageSize' => $model->listSize,
            ];
        } else {
            $template_params = [
                'form' => $form,
                'itemTypeId' => $itemTypeId,
                'error_list' => $model->getInputError(),
            ];
        }

        $template = 'edit_person/query.html.twig';
        return $this->renderEditElements($template, $itemTypeId, $template_params);

    }

    /**
     * getStatusChoices(int $itemTypeId)
     *
     * @return choice list for status values
     */
    private function getStatusChoices(int $itemTypeId) {
        $personRepository = $this->entityManager->getRepository(Person::class);

        $suggestions = $this->autocomplete->suggestEditStatus($itemTypeId, null, 60);
        $status_list = array_column($suggestions, 'suggestion');

        $status_choices = ['- alle -' => null];
        $status_choices = array_merge($status_choices, array_combine($status_list, $status_list));
        // $status_choices = array_combine($status_list, $status_list);

        return $status_choices;
    }


    /**
     * map data to objects and save them to the database
     * @Route("/edit/person/save", name="edit_person_save")
     */
    public function save(Request $request) {

        $form_data = $request->request->get(self::EDIT_FORM_ID);
        $item_type_id = $form_data[0]['item']['itemTypeId'];

        /* map/validate form */
        // fill person_list
        $person_list = $this->editService->mapFormdata(
            $form_data,
        );

        $error_flag = false;
        foreach($person_list as $person) {
            if ($person->hasError('error')) {
                $error_flag = true;
            }
        }

        $form_display_type = $request->request->get('formType');

        /* save data */
        $entity_manager = $this->entityManager;
        if (!$error_flag) {
            $current_user_id = $this->getUser()->getId();
            $personRepository = $entity_manager->getRepository(Person::class);
            $nameLookupRepository = $entity_manager->getRepository(NameLookup::class);
            $canonLookupRepository = $entity_manager->getRepository(CanonLookup::class);
            foreach ($person_list as $key => $person) {
                if ($person->getItem()->getFormIsEdited()) {
                    $person_id = $person->getId();
                    if ($person_id == 0) { // new entry
                        // start out with a new object to avoid cascade errors
                        $person_new = $this->editService->makePerson($item_type_id, $current_user_id);
                        $this->editService->initMetaData($person_new, $item_type_id, $current_user_id);
                        $this->entityManager->persist($person_new);
                        $this->entityManager->flush();
                        $person_id = $person_new->getItem()->getId();
                    }
                    // read complete tree to perform deletions if necessary
                    $query_result = $personRepository->findList([$person_id]);
                    $target = $query_result[0];
                    // transfer data from $person to $target
                    $this->editService->update($target, $person, $current_user_id);

                    // merging process?
                    $target_item = $target->getItem();
                    if ($target_item->getMergeStatus() == 'merging') {
                        $parent_list = $this->editService->readParentList($person);
                        $target_item->setMergeStatus('child');
                        $idPublic = $parent_list[0]->getItem()->getIdPublic();
                        $target_item->setIdPublic($idPublic);
                        $target_id = $target->getId();
                        foreach ($parent_list as $parent) {
                            $this->editService->updateAsParent($parent, $target_id);
                        }
                    }

                    // update auxiliary tables
                    $nameLookupRepository->update($target);
                    if ($target->getItemTypeId() == Item::ITEM_TYPE_ID['Domherr']['id']) {
                        $canonLookupRepository->update($target);
                    }

                    // form status
                    $expanded = $person->getItem()->getFormIsExpanded();
                    $target->getItem()->setFormIsExpanded($expanded);
                    $target->getItem()->setFormIsEdited(0);

                    $person_list[$key] = $target; // show updated object
                }
            }

            $this->entityManager->flush();

            // add an empty form in case the user wants to add more items
            if ($form_display_type == "new_entry") {
                $person = $this->editService->makePerson($item_type_id, $current_user_id);
                // add empty elements for blank form sections
                $authorityRepository = $this->entityManager->getRepository(Authority::class);
                $auth_list = $authorityRepository->findList(Authority::coreIDs());
                $person->addEmptyDefaultElements($auth_list);
                $person->getItem()->setFormIsExpanded(true);
                $person_list[] = $person;
            }

        }

        // add empty elements for blank form sections
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::coreIDs());
        foreach ($person_list as $person) {
            $person->extractSeeAlso();
            $person->addEmptyDefaultElements($auth_list);
        }

        $template = "";
        if ($form_display_type == 'list') {
            $template = 'edit_person/_list.html.twig';
        } else {
            $template = 'edit_person/new_person.html.twig';
        }

        return $this->renderEditElements($template, $item_type_id, [
            'personList' => $person_list,
            'count' => count($person_list),
            'formType' => $form_display_type,
        ]);

    }

    private function renderEditElements($template, $item_type_id, $param_list) {

        $userWiagRepository = $this->entityManager->getRepository(UserWiag::class);
        $authorityRepository = $this->entityManager->getRepository(Authority::class);

        $auth_query_result = $authorityRepository->findList(array_values(Authority::ID));

        $auth_base_url_list = array();
        foreach($auth_query_result as $auth) {
            $auth_base_url_list[$auth->getId()] = $auth->getUrl();
        }

        // property types
        $itemPropertyTypeRepository = $this->entityManager->getRepository(ItemPropertyType::class);
        $item_property_type_list = $itemPropertyTypeRepository->findByItemTypeId($item_type_id);
        $rolePropertyTypeRepository = $this->entityManager->getRepository(rolePropertyType::class);
        $role_property_type_list = $rolePropertyTypeRepository->findByItemTypeId($item_type_id);

        $param_list_combined = array_merge($param_list, [
            'menuItem' => 'edit-menu',
            'editFormId' => self::EDIT_FORM_ID,
            'itemTypeId' => $item_type_id,
            'userWiagRepository' => $userWiagRepository,
            'authBaseUrlList' => $auth_base_url_list,
            'itemPropertyTypeList' => $item_property_type_list,
            'rolePropertyTypeList' => $role_property_type_list,
        ]);

        return $this->renderForm($template, $param_list_combined);

    }

    /**
     *
     * @Route("/edit/person/delete", name="edit_person_delete")
     */
    public function deleteEntry(Request $request) {


        // the button name is 'delete-id'
        $id = $request->request->get('delete-id');

        // set the delete flag
        $itemRepository = $this->entityManager->getRepository(Item::class);
        $item = $itemRepository->find($id);
        $item->setIsDeleted(1);
        $this->entityManager->flush();

        return $this->query($request);
    }

    /**
     * display edit form for new bishop
     *
     * @Route("/edit/person/new/{itemTypeId}", name="edit_person_new")
     */
    public function newPerson(Request $request, int $itemTypeId) {

        $person = $this->editService->makePerson($itemTypeId, $this->getUser()->getId());
        // add empty elements for blank form sections
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::coreIDs());
        $person->addEmptyDefaultElements($auth_list);
        $person->getItem()->setFormIsExpanded(true);

        $template = 'edit_person/new_person.html.twig';

        return $this->renderEditElements($template, $itemTypeId, [
            'personList' => array($person),
            'title' => 'Neue Einträge',
            'form' => null,
            'count' => 1,
        ]);

    }

    /**
     *
     * @Route("/edit/person/item-content/{itemTypeId}/{id}/{index}", name="edit_person_item_content")
     */
    public function _itemContent(Request $request,
                                 int $itemTypeId,
                                 int $id,
                                 int $index) {
        $person_repository = $this->entityManager->getRepository(Person::class);
        $query_result = $person_repository->findList([$id]);
        $person = $query_result[0];
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::coreIDs());
        $person->extractSeeAlso();
        $person->addEmptyDefaultElements($auth_list);

        return $this->renderEditElements("edit_person/_item_content.html.twig", $itemTypeId, [
            'person' => $person,
            'personIndex' => $index,
            'formType' => 'list',
        ]);
    }

    /**
     * @return template for new item property
     *
     * @Route("/edit/person/new-property/{itemTypeId}/{personIndex}", name="edit_person_new_property")
     */
    public function newProperty(Request $request,
                                int $itemTypeId,
                                int $personIndex) {

        $prop = new ItemProperty();

        // property types
        $itemPropertyTypeRepository = $this->entityManager->getRepository(ItemPropertyType::class);
        $item_property_type_list = $itemPropertyTypeRepository->findByItemTypeId($itemTypeId);

        // current_idx is set by JavaScript

        return $this->render('edit_person/_input_property.html.twig', [
            'editFormId' => self::EDIT_FORM_ID,
            'personIndex' => $personIndex,
            'current_idx' => $request->query->get('current_idx'),
            'prop' => $prop,
            'is_last' => true,
            'itemPropertyTypeList' => $item_property_type_list,
            'itemTypeId' => $itemTypeId,
        ]);

    }


    /**
     * @return template for new role
     *
     * @Route("/edit/person/new-role/{itemTypeId}/{personIndex}", name="edit_person_new_role")
     */
    public function newRole(Request $request,
                            int $itemTypeId,
                            int $personIndex) {

        $role = new PersonRole;
        $role->setId(0);

        $rolePropertyTypeRepository = $this->entityManager->getRepository(rolePropertyType::class);
        $role_property_type_list = $rolePropertyTypeRepository->findByItemTypeId($itemTypeId);


        return $this->render('edit_person/_input_role.html.twig', [
            'editFormId' => self::EDIT_FORM_ID,
            'itemTypeId' => $itemTypeId,
            'personIndex' => $personIndex,
            'current_idx' => $request->query->get('current_idx'),
            'role' => $role,
            'is_last' => true,
            'rolePropertyTypeList' => $role_property_type_list,
        ]);

    }

    /**
     * @return template for new role
     *
     * @Route("/edit/person/new-role-property/{itemTypeId}/{personIndex}/{roleIndex}", name="edit_person_new_role_property")
     */
    public function newRoleProperty(Request $request,
                                    int $itemTypeId,
                                    int $personIndex,
                                    int $roleIndex) {

        $prop = new PersonRoleProperty;

        $rolePropertyTypeRepository = $this->entityManager->getRepository(rolePropertyType::class);
        $role_property_type_list = $rolePropertyTypeRepository->findByItemTypeId($itemTypeId);


        return $this->render('edit_person/_input_role_property.html.twig', [
            'editFormId' => self::EDIT_FORM_ID,
            'itemTypeId' => $itemTypeId,
            'personIndex' => $personIndex,
            'roleIndex' => $roleIndex,
            'current_idx' => $request->query->get('current_idx'),
            'prop' => $prop,
            'is_last' => true,
            'rolePropertyTypeList' => $role_property_type_list,
            'itemTypeId' => $itemTypeId,
        ]);

    }


    /**
     * @return template for new reference
     *
     * @Route("/edit/person/new-reference/{itemTypeId}/{personIndex}", name="edit_person_new_reference")
     */
    public function newReference(Request $request,
                                 int $itemTypeId,
                                 int $personIndex) {

        $reference = new ItemReference(0);

        return $this->render('edit_person/_input_reference.html.twig', [
            'editFormId' => self::EDIT_FORM_ID,
            'itemTypeId' => $itemTypeId,
            'personIndex' => $personIndex,
            'current_idx' => $request->query->get('current_idx'),
            'ref' => $reference,
            'itemTypeId' => $itemTypeId,
        ]);

    }

    /**
     * @return template for new external ID
     *
     * @Route("/edit/person/new-url-external/{itemTypeId}/{personIndex}", name="edit_person_new_urlexternal")
     */
    public function newUrlExternal(Request $request,
                                 int $itemTypeId,
                                 int $personIndex) {

        $urlExternal = new urlExternal();

        return $this->render('edit_person/_input_url_external.html.twig', [
            'editFormId' => self::EDIT_FORM_ID,
            'itemTypeId' => $itemTypeId,
            'personIndex' => $personIndex,
            'current_idx' => $request->query->get('current_idx'),
            'urlext' => $urlExternal,
            'itemTypeId' => $itemTypeId,
        ]);

    }

    /**
     * display query form for bishops; handle query
     *
     * @Route("/edit/person/merge-query/{itemTypeId}", name="edit_person_merge_query")
     */
    public function mergeQuery(Request $request,
                               FormFactoryInterface $formFactory,
                               int $itemTypeId) {

        $model = new PersonFormModel;
        // set defaults
        $model->editStatus = ['fertig'];
        $model->isOnline = true;
        $model->listSize = 5;

        $status_choices = $this->getStatusChoices($itemTypeId);

        $form = $formFactory->createNamed('bishop_merge',
                                          EditPersonFormType::class,
                                          $model, [
                                              'statusChoices' => $status_choices,
                                          ]);
        // $form = $this->createForm(EditBishopFormType::class);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        $template_params = [
            'itemTypeId' => $itemTypeId,
            'menuItem' => 'edit-menu',
            'form' => $form,
        ];


        if ($form->isSubmitted() && $form->isValid()) {
            $personRepository = $this->entityManager->getRepository(Person::class);

            $itemRepository = $this->entityManager->getRepository(Item::class);

            $limit = 0;
            $offset = 0;
            $online_only = false;
            $id_all = $itemRepository->bishopIds($model, $limit, $offset, $online_only);
            $count = count($id_all);

            $offset = $request->request->get('offset');
            $page_number = $request->request->get('pageNumber');

            // set offset to page begin
            if (!is_null($offset)) {
                $offset = intdiv($offset, $model->listSize) * $model->listSize;
            } elseif (!is_null($page_number) && $page_number > 0) {
                $page_number = min($page_number, intdiv($count, $model->listSize) + 1);
                $offset = ($page_number - 1) * $model->listSize;
            } else {
                $offset = 0;
            }

            $id_list = array_slice($id_all, $offset, $model->listSize);
            $person_list = $personRepository->findList($id_list);

            $template_params = [
                'menuItem' => 'edit-menu',
                'itemTypeId' => $itemTypeId,
                'form' => $form,
                'count' => $count,
                'personList' => $person_list,
                'offset' => $offset,
                'pageSize' => $model->listSize,
                // 'mergeSelectFormId' => 'merge_select', // 2023-02-15 drop?
            ];

        }

        $debug_flag = $request->query->get('debug');
        $template = $debug_flag ? 'merge_query_debug.html.twig' : 'merge_query.html.twig';

        return $this->renderForm('edit_person/'.$template, $template_params);

    }

    /**
     * merge data; display merged data in a new window
     *
     * @Route("/edit/person/merge-item/{itemTypeId}/{first}/{second_id}", name="edit_person_merge_item")
     *
     * merge $first (id) with $second_id (id_in_source) into a new person
     * route paramters are optional, because the JS controller needs the base path.
     */
    public function mergeItem(Request $request,
                              int $itemTypeId,
                              $first = null,
                              $second_id = null) {

        $itemRepository = $this->entityManager->getRepository(Item::class);
        $personRepository = $this->entityManager->getRepository(Person::class);

        // create new person
        $current_user = $this->getUser()->getId();
        $person = $this->editService->makePerson($itemTypeId, $current_user);

        // get parent data
        $parent_list = $itemRepository->findById($first);
        $id_in_source = $parent_list[0]->getIdInSource();

        $second = $itemRepository->findMergeCandidate($second_id, $itemTypeId);
        if (is_null($second)) {
            $msg = "Zu {$second_id} (angegegeben im Feld 'identisch mit') wurde keine Person gefunden.";
            $person->getInputError()->add(new InputError("status", $msg));
        } else {
            $parent_list[] = $second;
        }

        // store parents in $person and merge data
        $parent_person_list = array();

        // we need completely inilialized Person objects
        $id_list = array_map(
            function ($v) {return $v->getId(); },
            $parent_list);
        $parent_person_list = $personRepository->findList($id_list);


        $person->merge($parent_person_list);
        $person->getItem()->setMergeStatus('merging');

        // set public id
        $person->getItem()->setIdPublic($parent_list[0]->getIdPublic());
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::coreIDs());
        $person->extractSeeAlso();
        $person->addEmptyDefaultElements($auth_list);

        $person->getItem()->setFormIsExpanded(true);
        $person->getItem()->setFormIsEdited(true);

        // child should be searchable via the IDs of its parents: use item.merged_into_id

        $template = 'edit_person/new_person.html.twig';

        return $this->renderEditElements($template, $itemTypeId, [
            'personList' => array($person),
            'title' => 'Einträge zusammenführen',
            'form' => null,
            'count' => 1,
        ]);

    }

    /**
     * edit a single entry
     *
     * @Route("/edit/person/edit-single/{someid}", name="edit_person_edit_single")
     */
    public function editSingle(Request $request,
                               $someid) {

        $itemRepository = $this->entityManager->getRepository(Item::class);
        $personRepository = $this->entityManager->getRepository(Person::class);

        $model = new PersonFormModel;
        $model->someid = $someid;

        $online_only = false;
        $limit = null;
        $offset = null;
        $id_all = $itemRepository->bishopIds($model, $limit, $offset, $online_only);

        $person_list = $personRepository->findList($id_all);

        // add empty role, reference and id external if not present
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::coreIDs());
        foreach ($person_list as $person) {
            $person->addEmptyDefaultElements($auth_list);
        }

        if (count($person_list) == 1) {
            $person_list[0]->getItem()->setFormIsExpanded(1);
        }


        $template = 'edit_person/new_person.html.twig';

        return $this->renderEditElements($template, $item_type_id, [
            'personList' => $person_list,
            'form' => null,
            'title' => null,
            'count' => count($person_list)
        ]);

    }


    /**
     * split merged item, show parents in edit forms
     *
     * @Route("/edit/person/split-item/{itemTypeId}/{id}", name="edit_person_split_item")
     *
     */
    public function splitItem(int $itemTypeId, int $id) {
        $itemRepository = $this->entityManager->getRepository(Item::class);
        $personRepository = $this->entityManager->getRepository(Person::class);
        $canonLookupRepository = $this->entityManager->getRepository(CanonLookup::class);

        $item = $itemRepository->find($id);

        // set status values for parents and child
        $person_list = array();
        if (!is_null($item)) {
            $parent_list = $itemRepository->findParents($item);
            // dd ($parent_list);

            $item->setIsDeleted(1);
            $item->setMergeStatus('orphan');
            $item->setIsOnline(0);
            $orphan_person = $personRepository->find($id);
            $canonLookupRepository->update($orphan_person);

            $id_list = array();
            foreach($parent_list as $parent_item) {
                $parent_item->updateIsOnline();

                // merge_status
                $parent_parent_list = $itemRepository->findParents($parent_item);
                $merge_status = count($parent_parent_list) > 0 ? 'child' : 'original';
                $parent_item->setMergeStatus($merge_status);

                $parent_item->setMergedIntoId(null);

                $id_list[] = $parent_item->getId();

                $parent_item->setFormIsExpanded(1);
            }
            $person_list = $personRepository->findList($id_list);
            foreach ($person_list as $parent_person) {
                $canonLookupRepository->update($parent_person);
            }

            $this->entityManager->flush();

        } else {
            throw $this->createNotFoundException('ID is nicht gültig: '.$id);
        }

        $template = 'edit_person/new_person.html.twig';

        return $this->renderEditElements($template, $itemTypeId, [
            'personList' => $person_list,
            'form' => null,
            'count' => count($person_list),
        ]);

    }

    /**
     * usually used for asynchronous JavaScript request
     *
     * @Route("/edit/person/suggest/{itemTypeId}/{field}", name="edit_person_suggest")
     */
    public function autocomplete(Request $request,
                                 $itemTypeId,
                                 $field){

        $repository = $this->entityManager->getRepository(Person::class);

        $hint = $request->query->get('q');

        $fnName = 'editSuggest'.ucfirst($field);
        $suggestions = $repository->$fnName($itemTypeId, $hint, self::SUGGEST_SIZE);

        return $this->render('bishop/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

    /**
     * 2023-03-31 Teste Service mit Datenbank-Zugriff
     * @Route("/edit/person/autocomplete", name="test_03_31")
     */
    public function autocompleteTest(AutocompleteService $service) {
        dd($service->message());
        return new Response($service->message());
    }



}