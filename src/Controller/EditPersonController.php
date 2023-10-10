<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemReference;
use App\Entity\ItemProperty;
use App\Entity\ItemPropertyType;
use App\Entity\ItemCorpus;
use App\Entity\UrlExternal;
use App\Entity\Person;
use App\Entity\InputError;
use App\Entity\PersonRole;
use App\Entity\PersonRoleProperty;
use App\Entity\RolePropertyType;
use App\Entity\Authority;
use App\Entity\NameLookup;
use App\Entity\ItemNameRole;
use App\Entity\UserWiag;
use App\Form\EditPersonFormType;
use App\Form\Model\PersonFormModel;
use App\Entity\Role;

use App\Service\EditPersonService;
use App\Service\AutocompleteService;
use App\Service\UtilService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
     * @Route("/edit/person/query", name="edit_person_query")
     */
    public function query(Request $request) {

        $model = new PersonFormModel;
        // set defaults
        $model->editStatus = ['- alle -' => '- alle -'];
        $model->isOnline = true;
        $model->listSize = 20;
        $model->corpus = 'epc,can';
        $model->isEdit = true;

        $status_choices = $this->statusChoices();

        $sort_by_choices = $this->sortByChoices();

        $form = $this->createForm(EditPersonFormType::class, $model, [
            'statusChoices' => $status_choices,
            'sortByChoices' => $sort_by_choices,
        ]);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        $person_list = array();
        if ($form->isSubmitted() && $form->isValid() && $model->isValid() || $model->isEmpty()) {

            $personRepository = $this->entityManager->getRepository(Person::class);

            $limit = 0; $offset = 0; $online_only = false;
            $id_all = $personRepository->findEditPersonIds($model, $limit, $offset);

            $count = count($id_all);

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
            $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
            foreach ($person_list as $person) {
                $person->extractSeeAlso();
                $person->addEmptyDefaultElements($auth_list);
            }

            $template_params = [
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
                'error_list' => $model->getInputError(),
            ];
        }

        $template = 'edit_person/query.html.twig';
        return $this->renderEditElements($template, $template_params);

    }

    /**
     * sortByChoices($itemTypeid)
     *
     * @return choice list for sorting
     */
    private function sortByChoices() {
        return [
            'Vorname, Familienname' => 'givenname',
            'Familienname, Vorname' => 'familyname',
            'Domstift/Kloster' => 'institution',
            'Bistum' => 'diocese',
            'Jahr' => 'year',
            'identisch mit' => 'commentDuplicate',
            'ID' => 'idInSource',
            'Status' => 'editStatus'
        ];
    }

    /**
     * statusChoices(int $itemTypeId)
     *
     * @return choice list for status values
     */
    private function statusChoices() {
        $personRepository = $this->entityManager->getRepository(Person::class);

        $q_param = null;
        $suggestions = $this->autocomplete->suggestEditStatus($q_param, 60);
        $status_list = array_column($suggestions, 'suggestion');

        $status_choices = ['- alle -' => '- alle -'];
        $status_choices = array_merge($status_choices, array_combine($status_list, $status_list));
        // $status_choices = array_combine($status_list, $status_list);

        return $status_choices;
    }


    /**
     * 2023-07-06 not in use any more; see saveSingle save separate forms separately
     * map data to objects and save them to the database
     * @Route("/edit/person/save", name="edit_person_save")
     */
    // public function save(Request $request) {

    //     $form_data = $request->request->get(self::EDIT_FORM_ID);
    //     $item_type_id = $form_data[0]['item']['itemTypeId'];
    //     $current_user_id = $this->getUser()->getId();

    //     /* map/validate form */
    //     // fill person_list
    //     $person_list = $this->editService->mapFormdata($form_data, $current_user_id);

    //     $error_flag = false;
    //     foreach($person_list as $person) {
    //         if ($person->getItem()->hasError('error')) {
    //             $error_flag = true;
    //         }
    //     }

    //     $form_display_type = $request->request->get('formType') ?? 'list';

    //     /* save data */
    //     $entity_manager = $this->entityManager;
    //     if (!$error_flag) {
    //         $personRepository = $entity_manager->getRepository(Person::class);
    //         $nameLookupRepository = $entity_manager->getRepository(NameLookup::class);
    //         $canonLookupRepository = $entity_manager->getRepository(CanonLookup::class);
    //         foreach ($person_list as $key => $person) {
    //             if ($person->getItem()->getFormIsEdited()) {
    //                 $person_id = $person->getId();
    //                 if ($person_id == 0) { // new entry
    //                     // start out with a new object to avoid cascade errors
    //                     $person_new = new Person($item_type_id, $current_user_id);
    //                     $this->editService->initMetaData($person_new, $item_type_id);
    //                     $this->entityManager->persist($person_new);
    //                     $this->entityManager->flush();
    //                     $person_id = $person_new->getItem()->getId();
    //                 }
    //                 // read complete tree to perform deletions if necessary
    //                 // 2023-05-25 here, we get a second object for the same
    //                 // person from the database. Which one takes precedence when
    //                 // data are written to the database?
    //                 $query_result = $personRepository->findList([$person_id]);
    //                 $target = $query_result[0];

    //                 // restore canon from Digitales Personenregister?
    //                 $this->restoreCanonGs($target, $person);
    //                 // transfer data from $person to $target
    //                 $this->editService->update($target, $person, $current_user_id);

    //                 // merging process?
    //                 $target_item = $target->getItem();
    //                 if ($target_item->getMergeStatus() == 'merging') {
    //                     $parent_list = $this->editService->readParentList($person);
    //                     $target_item->setMergeStatus('child');
    //                     $idPublic = $parent_list[0]->getItem()->getIdPublic();
    //                     $target_item->setIdPublic($idPublic);
    //                     $target_id = $target->getId();
    //                     foreach ($parent_list as $parent) {
    //                         $this->editService->updateAsParent($parent, $target_id);
    //                     }
    //                 }

    //                 // update auxiliary tables
    //                 $nameLookupRepository->update($target);
    //                 // only the canon entry is responsible for the content of canonLookupRepository
    //                 if ($target->getItemTypeId() == Item::ITEM_TYPE_ID['Domherr']['id']) {
    //                     $canonLookupRepository->update($target);
    //                 }

    //                 // form status
    //                 $expanded = $person->getItem()->getFormIsExpanded();
    //                 $target->getItem()->setFormIsExpanded($expanded);
    //                 $target->getItem()->setFormIsEdited(0);

    //                 $person_list[$key] = $target; // show updated object
    //             }
    //         }


    //         $this->entityManager->flush();

    //         // add an empty form in case the user wants to add more items
    //         if ($form_display_type == "new_entry") {
    //             $person = new Person($item_type_id, $current_user_id);
    //             // add empty elements for blank form sections
    //             $authorityRepository = $this->entityManager->getRepository(Authority::class);
    //             $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
    //             $person->addEmptyDefaultElements($auth_list);
    //             $person->getItem()->setFormIsExpanded(true);
    //             $person_list[] = $person;
    //         }

    //     }

    //     // add empty elements for blank form sections
    //     $authorityRepository = $this->entityManager->getRepository(Authority::class);
    //     $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
    //     foreach ($person_list as $person) {
    //         $person->extractSeeAlso();
    //         $person->addEmptyDefaultElements($auth_list);
    //     }

    //     $template = "";
    //     if ($form_display_type == 'list') {
    //         $template = 'edit_person/_list.html.twig';
    //     } else {
    //         $template = 'edit_person/new_person.html.twig';
    //     }

    //     return $this->renderEditElements($template, $item_type_id, [
    //         'personList' => $person_list,
    //         'count' => count($person_list),
    //         'formType' => $form_display_type,
    //     ]);

    // }

    /**
     * TEST: map data to objects and save them to the database
     * @Route("/edit/person/restore-cn-gs-in-cl")
     */
    public function restoreCnGsInClTmp (request $request) {
        $canonLookupRepository = $this->entityManager->getRepository(CanonLookup::class);

        $id_cand_list = $canonLookupRepository->addCanonGsGlob();

        return $this->render("base.html.twig");
    }



    /**
     * map data to objects and save them to the database
     * @Route("/edit/person/save-single", name="edit_person_save_single")
     */
    public function saveSingle(Request $request) {
        $current_user_id = $this->getUser()->getId();

        // use EDIT_FORM_ID as the name attribute of the form in the template
        $form_data = $request->request->get(self::EDIT_FORM_ID);
        $person_index = array_keys($form_data)[0];
        // get first element independent from indexing
        $form_data = array_values($form_data)[0];

        /* map/validate form */
        // fill person_list
        $person_list = $this->editService->mapFormdata([$form_data], $current_user_id);
        $person = $person_list[0];
        $error_flag = $person->getItem()->hasError('error');
        $person_id = $person->getId();

        /* save data */
        $em = $this->entityManager;
        if (!$error_flag) {
            $itemRepository = $em->getRepository(Item::class);
            $personRepository = $em->getRepository(Person::class);
            $nameLookupRepository = $em->getRepository(NameLookup::class);
            $itemNameRoleRepository = $em->getRepository(ItemNameRole::class);
            $urlExternalRepository = $em->getRepository(UrlExternal::class);

            // this function is only called if form data have changed
            if ($person_id == 0) { // new entry
                // start out with a new object to avoid cascade errors
                $person_new = new Person($current_user_id);
                $this->entityManager->persist($person_new);
                $this->entityManager->flush();
                $person_id = $person_new->getItem()->getId();
            }

            // read complete tree to perform deletions if necessary
            $query_result = $personRepository->findList([$person_id]);
            $target = $query_result[0];

            // collect IDs of affected persons
            $affected_id_list = [$person_id];
            // - get value of URL external via object data
            // - person
            $uext_value = $person->getItem()->getGsn();
            if (!is_null($uext_value)) {
                $item_id_list = $urlExternalRepository->findItemId($uext_value, Authority::ID['GSN']);
                $affected_id_list = array_merge($affected_id_list, $item_id_list);
            }
            // - target (ante)
            $uext_value = $target->getItem()->getGsn();
            if (!is_null($uext_value)) {
                $item_id_list = $urlExternalRepository->findItemId($uext_value, Authority::ID['GSN']);
                $affected_id_list = array_merge($affected_id_list, $item_id_list);
            }
            $affected_id_list = array_unique($affected_id_list);


            // transfer data from $person to $target
            $this->editService->update($target, $person, $current_user_id);

            // merging process?
            $target_item = $target->getItem();
            if ($target_item->getMergeStatus() == 'merging') {
                $parent_list = $this->editService->readParentList($target);

                $target_item->setMergeStatus('child');
                $target_item->clearMergeParent();
                $idPublic = $parent_list[0]->getItem()->getIdPublic();
                $target_item->setIdPublic($idPublic);
                $target_id = $target->getId();
                $ancestor_list = array();
                foreach ($parent_list as $parent) {
                    $item_loop = $parent->getItem();
                    $ancestor_list[] = $item_loop;
                    $q_ancestor_list = $itemRepository->findAncestor($item_loop);
                    $ancestor_list = array_merge($ancestor_list, $q_ancestor_list);
                    $this->editService->updateItemAsParent($item_loop, $target_id);
                }
                // TODO 2023-10-10
                foreach ($parent_list as $parent) {
                    $affected_id_list_loop = $this->getIdLinkedPersons($parent);
                    $affected_person_id_list = array_merge($affected_person_id_list, $affected_id_list_loop);
                }

                $target->getItem()->setAncestor($ancestor_list);
            }

            $nameLookupRepository->update($target);

            // form status
            $expanded = $person->getItem()->getFormIsExpanded();
            $target->getItem()->setFormIsExpanded($expanded);
            $target->getItem()->setFormIsEdited(false);

            $this->entityManager->flush();

            $itemNameRoleRepository->updateByIdList($affected_id_list);

            $person=$target;
        }

        // add empty elements for blank form sections
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
        $person->extractSeeAlso();
        $person->addEmptyDefaultElements($auth_list);
        $person->getItem()->setFormType($form_data['item']['formType']);

        $template = "edit_person/_item.html.twig";

        $debug_submit = $request->request->get('debug');
        if ($debug_submit == 'debug') {
            $template = "edit_person/item_debug.html.twig";
        }

        return $this->renderEditElements($template, [
            'person' => $person,
            'personIndex' => $person_index,
        ]);
    }

    /**
     * @return list of included persons and own id
     */
    private function getIdLinkedPersons($person) {
        $itemRepository = $this->entityManager->getRepository(Item::class);
        $urlExternalRepository = $this->entityManager->getRepository(UrlExternal::class);

        $type_id_canon_gs = Item::ITEM_TYPE_ID['Domherr GS']['id'];
        $type_id_episc_gs = Item::ITEM_TYPE_ID['Bischof GS']['id'];

        // bishop
        $id_list = array($person->getId());
        $uext = $person->getItem()->getUrlExternalObj('WIAG-ID');
        if (!is_null($uext)) {
            $item_ep_list = $itemRepository->findByIdPublic($uext->getValue());
            if (!is_null($item_ep_list) and count($item_ep_list) > 0) {
                $item_ep_id = $item_ep_list[0]->getId();
                $id_list[] = $item_ep_id;
                $item_ep = $itemRepository->find($item_ep_id);

                // canon GS
                $uext = $item_ep->getUrlExternalObj('GS');
                if (!is_null($uext)) {
                    $id_list_cn_gs = $urlExternalRepository->findItemId($uext->getValue(), $type_id_canon_gs);
                    $id_list_ep_gs = $urlExternalRepository->findItemId($uext->getValue(), $type_id_episc_gs);
                    $id_list = array_merge($id_list, $id_list_cn_gs, $id_list_ep_gs);
                }

            }
        }

        // canon GS
        $uext = $person->getItem()->getUrlExternalObj('GS');
        if (!is_null($uext)) {
            $id_list = array_merge($id_list, $urlExternalRepository->findItemId($uext->getValue(), $type_id_canon_gs));
        }

        // is bishop referred to by a canon?
        if ($person->getItem()->getItemTypeId() == Item::ITEM_TYPE_ID['Bischof']['id']) {
            $canon_id_list = $urlExternalRepository->findIdBySomeNormUrl($person->getItem()->getIdPublic());
            foreach($canon_id_list as $canon_id) {
                $id_list[] = $canon_id;
            }
        }

        return($id_list);
    }


    private function renderEditElements($template, $param_list) {

        $userWiagRepository = $this->entityManager->getRepository(UserWiag::class);
        $authorityRepository = $this->entityManager->getRepository(Authority::class);

        $essential_auth_list = $authorityRepository->findList(array_values(Authority::ID));

        $param_list_combined = array_merge($param_list, [
            'menuItem' => 'edit-menu',
            'editFormId' => self::EDIT_FORM_ID,
            'userWiagRepository' => $userWiagRepository,
            'essentialAuthorityList' => $essential_auth_list,
            'itemPropertyTypeList' => $this->getItemPropertyTypeList(),
        ]);

        return $this->renderForm($template, $param_list_combined);

    }

    /**
     *
     * @Route("/edit/person/delete-local", name="edit_person_delete_local")
     */
    public function deleteEntryLocal(Request $request) {
        $personRepository = $this->entityManager->getRepository(Person::class);
        $itemRepository = $this->entityManager->getRepository(Item::class);
        $canonLookupRepository = $this->entityManager->getRepository(CanonLookup::class);

        $form_data = $request->request->get(self::EDIT_FORM_ID);
        // get first element independent from indexing
        $form_data = array_values($form_data)[0];
        $item_id = $form_data['id'];

        $item = $itemRepository->find($item_id);
        $item_was_online = $item->getIsOnline();

        $item->setIsDeleted(1);
        $item->setIsOnline(0);
        $item->updateChangedMetaData($this->getUser());

        // clear/update canon_lookup
        if ($item_was_online) {
            $q_person = $personRepository->findList([$item_id]);
            if (!is_null($q_person) and count($q_person) > 0) {
                $affected_id_list = $this->getIdLinkedPersons($q_person[0]);
                $canonLookupRepository->clearByIdRole($affected_id_list);
                $canonLookupRepository->insertByListMayBe($affected_id_list);
            }
        }


        $this->entityManager->flush();

        return new Response("delete ID ".$item_id);
    }


    /**
     *
     * @Route("/edit/person/delete/{q_id}", name="edit_person_delete")
     */
    public function deleteEntry(Request $request, $q_id) {
        $person_repository = $this->entityManager->getRepository(Person::class);

        $form_data = $request->request->get(self::EDIT_FORM_ID);
        $item_type_id = $form_data[0]['item']['itemTypeId'];

        $id_list = array_column($form_data, 'id');

        $person_list = $person_repository->findList($id_list);

        // deletion takes priority: all other edit data are lost and sub-forms are closed
        foreach ($person_list as $person) {
            $id_loop = $person->getId();
            if ($id_loop == $q_id) {
                $person->getItem()->setIsDeleted(1);
                $person->getItem()->setIsOnline(0);
            }
        }

        $this->entityManager->flush();

        $person_list = array_filter($person_list, function ($v) {
            return !$v->getItem()->getIsDeleted();
        });

        $template = 'edit_person/_list.html.twig';

        return $this->renderEditElements($template, $item_type_id, [
            'personList' => $person_list,
            'count' => count($person_list),
            'formType' => 'list',
        ]);
    }

    /**
     * display edit form for new person
     *
     * @Route("/edit/person/new", name="edit_person_new")
     */
    public function newList() {
        $person = $this->makePerson();
        $person->getItem()->setFormIsExpanded(true);
        $person->getItem()->setFormType('insert');

        $template = 'edit_person/new_person.html.twig';

        return $this->renderEditElements($template, [
            'personList' => array($person),
            'count' => 1,
        ]);

    }

    /**
     * display edit form for new person
     *
     * @Route("/edit/person/new-entry", name="edit_person_new_entry")
     */
    public function _newEntry(Request $request) {
        $person = $this->makePerson();
        $person->getItem()->setFormIsExpanded(true);
        $person->getItem()->setFormType('insert');
        $template = 'edit_person/_item.html.twig';
        $personIndex = $request->query->get('current_idx');

        return $this->renderEditElements($template, [
            'person' => $person,
            'personIndex' => $personIndex,
        ]);

    }

    private function makePerson() {
        $current_user_id = intVal($this->getUser()->getId());
        $person = new Person($current_user_id);
        // add empty elements for blank form sections
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
        $person->addEmptyDefaultElements($auth_list);

        return $person;
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
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $userWiagRepository = $this->entityManager->getRepository(UserWiag::class);

        $query_result = $person_repository->findList([$id]);
        $person = $query_result[0];
        $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
        $person->extractSeeAlso();
        $person->addEmptyDefaultElements($auth_list);

        $item = $person->getItem();
        $user = $userWiagRepository->find($item->getChangedBy());
        $item->setChangedByUser($user);

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

        // current_idx is set by JavaScript

        return $this->render('edit_person/_input_property.html.twig', [
            'editFormId' => self::EDIT_FORM_ID,
            'personIndex' => $personIndex,
            'current_idx' => $request->query->get('current_idx'),
            'prop' => $prop,
            'is_last' => true,
            'itemPropertyTypeList' => $this->getItemPropertyTypeList(),
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

        $role = new PersonRole();
        $role->setId(0);

        return $this->render('edit_person/_input_role.html.twig', [
            'editFormId' => self::EDIT_FORM_ID,
            'itemTypeId' => $itemTypeId,
            'personIndex' => $personIndex,
            'current_idx' => $request->query->get('current_idx'),
            'role' => $role,
            'is_last' => true,
            'itemPropertyTypeList' => $this->getItemPropertyTypeList(),
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

        $prop = new PersonRoleProperty();


        return $this->render('edit_person/_input_role_property.html.twig', [
            'editFormId' => self::EDIT_FORM_ID,
            'itemTypeId' => $itemTypeId,
            'personIndex' => $personIndex,
            'roleIndex' => $roleIndex,
            'current_idx' => $request->query->get('current_idx'),
            'prop' => $prop,
            'is_last' => true,
            'itemPropertyTypeList' => $this->getItemPropertyTypeList(),
            'itemTypeId' => $itemTypeId,
        ]);

    }

    private function getItemPropertyTypeList() {
        $repository = $this->entityManager->getRepository(itemPropertyType::class);
        $type_list = $repository->findAll();
        return UtilService::sortByFieldList($type_list, ['displayOrder', 'name']);
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
        $edit_status_default_list = [
            Item::ITEM_TYPE_ID['Bischof']['id'] => null, # all status values
            Item::ITEM_TYPE_ID['Domherr']['id'] => null, # all status values
        ];
        $model->editStatus = [$edit_status_default_list[$itemTypeId]];
        $model->listSize = 10;
        $model->itemTypeId = $itemTypeId;

        $status_choices = $this->statusChoices($itemTypeId);
        $sort_by_choices = $this->sortByChoices($itemTypeId);

        $form = $formFactory->createNamed(
            'person_merge_query',
            EditPersonFormType::class,
            $model, [
                'statusChoices' => $status_choices,
                'sortByChoices' => $sort_by_choices,
            ]);

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
            $id_all = $itemRepository->personIds($model, $limit, $offset, $online_only);
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
            ];

        }

        $debug_flag = $request->query->get('debug');
        $template = $debug_flag ? 'merge_query_debug.html.twig' : 'merge_query.html.twig';

        return $this->renderForm('edit_person/'.$template, $template_params);

    }

    /**
     * @Route("/edit/person/merge-item-local", name="edit_person_merge_local")
     * merge items using form data; show form with combined data
     * see modal-form.submitForm
     */
    public function mergeItemLocal(Request $request) {
        $itemRepository = $this->entityManager->getRepository(Item::class);
        $personRepository = $this->entityManager->getRepository(Person::class);
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $itemCorpusRepository = $this->entityManager->getRepository(ItemCorpus::class);

        $current_user = $this->getUser()->getId();


        // use EDIT_FORM_ID as the name attribute of the form in the template
        $form_data = $request->request->get(self::EDIT_FORM_ID);
        $person_index = array_keys($form_data)[0];
        // get first element independent from indexing
        $form_data = array_values($form_data)[0];

        // see person_edit/_merge_list.html.twig
        $id_in_corpus_second = $request->query->get('selected');

        // find merge candidate
        $iic_parts = UtilService::splitIdInCorpus($id_in_corpus_second);
        $ic_item_id = $itemCorpusRepository->findItemIdByCorpusAndId($iic_parts['corpus'], $iic_parts['id']);
        $second = is_null($ic_item_id) ? null : $itemRepository->find($ic_item_id['itemId']);

        $id_list = array($form_data['id']);
        if (is_null($second)) {
            $msg = "Zu {$id_in_source_second} (angegegeben im Feld 'identisch mit') wurde keine Person gefunden.";
            $q_person = $personRepository->findList($id_list);
            $person = $q_person[0];
            $person->getItem()->getInputError()->add(new InputError("status", $msg));
        } elseif ($second->getId() == $form_data['id']) {
            $msg = "Eine Person kann nicht mit sich selbst zusammengeführt werden.";
            $q_person = $personRepository->findList($id_list);
            $person = $q_person[0];
            $person->getItem()->getInputError()->add(new InputError("status", $msg));
        } else {
            $id_list[] = $second->getId();

            // get parent data
            $parent_list = $personRepository->findList($id_list);

            $corpus_id_list = array();
            foreach ($parent_list as $parent) {
                $corpus_id_list = array_merge($corpus_id_list, $parent->getItem()->getCorpusIdList());
            }
            $corpus_id_list = array_unique($corpus_id_list);

            // create new person with id_in_source and id_public
            $person = new Person($current_user);
            foreach ($corpus_id_list as $corpus_id) {
                $this->editService->makeItemCorpus($person->getItem(), $corpus_id);
            }

            $person->merge($parent_list);
            $person->getItem()->setMergeStatus('merging');

            $person->getItem()->setFormIsEdited(true);
        }

        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
        $person->extractSeeAlso();
        $person->addEmptyDefaultElements($auth_list);

        $person->getItem()->setFormIsExpanded(true);
        $person->getItem()->setFormType("edit");

        $template = "edit_person/_item.html.twig";

        return $this->renderEditElements($template, [
            'person' => $person,
            'personIndex' => $person_index,
        ]);

    }

    /**
     * edit a single entry
     *
     * @Route("/edit/person/edit-single/{someid}", name="edit_person_edit_single")
     */
    public function editSingle(Request $request,
                               $someid) {

        $personRepository = $this->entityManager->getRepository(Person::class);

        $model = new PersonFormModel;
        $model->someid = $someid;

        $online_only = false;
        $limit = null;
        $offset = null;
        $id_all = $personRepository->findEditPersonIds($model, $limit, $offset, $online_only);

        $person_list = $personRepository->findList($id_all);
        if (count($person_list) > 0) {
            $item_type_id = $person_list[0]->getItemTypeId();
        } else {
            $item_type_id = Item::ITEM_TYPE_ID['Domherr']['id'];
        }

        // add empty role, reference and id external if not present
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
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
            $online_involved = $item->getIsOnline();

            $item->setIsDeleted(1);
            $item->setMergeStatus('orphan');
            $item->setIsOnline(0);
            $orphan_person = $personRepository->find($id);

            $affected_person_id_list = array();
            if ($online_involved) {
                $affected_person_id_list = $this->getIdLinkedPersons($orphan_person);
            }


            $id_list = array();
            foreach($parent_list as $parent_item) {
                $parent_item->updateIsOnline();
                $online_involved = $online_involved or $parent_item->getIsOnline();

                // merge_status
                $parent_parent_list = $itemRepository->findParents($parent_item);
                $merge_status = count($parent_parent_list) > 0 ? 'child' : 'original';
                $parent_item->setMergeStatus($merge_status);

                $parent_item->setMergedIntoId(null);

                $id_list[] = $parent_item->getId();

                $parent_item->setFormIsExpanded(1);
            }

            $with_deleted = true;
            $person_list = $personRepository->findList($id_list, $with_deleted);


            $this->entityManager->flush();

            if ($online_involved) {
                foreach ($person_list as $parent_person) {
                    $affected_id_list_loop = $this->getIdLinkedPersons($parent_person);
                    $affected_person_id_list = array_merge($affected_person_id_list, $affected_id_list_loop);
                }
                $affected_person_id_list = array_unique($affected_person_id_list);
                // 2023-10-06 TODO item_name_role
                //$canonLookupRepository->clearByIdRole($affected_person_id_list);
                //$canonLookupRepository->insertByListMayBe($affected_person_id_list);
            }


        } else {
            throw $this->createNotFoundException('ID is nicht gültig: '.$id);
        }

        // add empty elements for blank form sections (after flush)
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
        foreach ($person_list as $person) {
            $person->extractSeeAlso();
            $person->addEmptyDefaultElements($auth_list);

            $person->getItem()->setFormType('insert');
        }

        $template = 'edit_person/new_person.html.twig';

        return $this->renderEditElements($template, $itemTypeId, [
            'personList' => $person_list,
            'form' => null,
            'count' => count($person_list),
        ]);

    }

    /**
     * 2023-07-14 obsolete
     */
    private function restoreCanonGs($target, $source) {
        $urlExternalRepository = $this->entityManager->getRepository(UrlExternal:: class);
        $personRepository = $this->entityManager->getRepository(Person::class);
        $canonLookupRepository = $this->entityManager->getRepository(CanonLookup::class);
        $canon_lookup = null;

        $auth_id = Item::AUTHORITY_ID['GS'];
        $item_type_id = Item::ITEM_TYPE_ID['Domherr GS']['id'];
        $uext_gs_target = $target->getItem()->getUrlExternalByAuthorityId($auth_id);
        $uext_gs_source = $source->getItem()->getUrlExternalByAuthorityId($auth_id);

        // new state (source): reference has gone
        if (!is_null($uext_gs_target) and is_null($uext_gs_source)) {
            // is there a Domherr GS ?
            $q_uext = $urlExternalRepository->findByValueAndItemType($uext_gs_target, $item_type_id);
            if (!is_null($q_uext) and count($q_uext) > 0) {
                $uext = $q_uext[0];
                $item_id = $uext->getItemId();
                // is there a reference by a bishop already
                $current = $canonLookupRepository->findOneByPersonIdRole($item_id);
                if (is_null($current) or $current->getPersonIdName() == $source->getId()) {
                    $person = $personRepository->find($item_id);
                    $canon_lookup = new CanonLookup();
                    $canon_lookup->setPerson($person);
                    $canon_lookup->setPersonIdName($item_id);
                    $canon_lookup->setPrioRole(1);
                    $this->entityManager->persist($canon_lookup);
                }
            }
        }

        return $canon_lookup;
    }

    /**
     * display query form for doublets
     *
     * @Route("/edit/person/query-doublet/{itemTypeId}", name="edit_person_query_doublet")
     */
    public function queryDoublet(Request $request, int $itemTypeId) {
        // parameters
        $list_size = 20;

        // set defaults
        $edit_status_default_list = [
            Item::ITEM_TYPE_ID['Bischof']['id'] => null, # all status values
            Item::ITEM_TYPE_ID['Domherr']['id'] => null, # all status values
        ];

        $status_choices = $this->statusChoices($itemTypeId);

        $authority_choices = [
            'GSN' => Authority::ID['GS'],
            'GND' => Authority::ID['GND'],
            'Wikidata' => Authority::ID['Wikidata']
        ];

        $model = [
            'editStatus' => [$edit_status_default_list[$itemTypeId]],
            'authority' => Authority::ID['GS'],
            'itemTypeId' => $itemTypeId
        ];

        $form = $this->createFormBuilder($model)
                     ->setMethod('GET')
                     ->add('editStatus', ChoiceType::class, [
                         'required' => false,
                         'label' => 'Status',
                         'multiple' => true,
                         'expanded' => false,
                         'choices' => $status_choices,
                     ])
                     ->add('authority', ChoiceType::class, [
                         'label' => 'Normdaten',
                         'choices' => $authority_choices,
                     ])
                     ->getForm();


        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        $person_list = array();
        $personRepository = $this->entityManager->getRepository(Person::class);
        $itemRepository = $this->entityManager->getRepository(Item::class);

        $limit = 0; $offset = 0;
        $id_all = $itemRepository->personDoubletIds($model, $limit, $offset);
        $count = count($id_all);

            //
            $offset = $request->query->get('offset');
            $page_number = $request->query->get('pageNumber');

            // set offset to page begin
            if (!is_null($offset)) {
                $offset = intdiv($offset, $list_size) * $list_size;
            } elseif (!is_null($page_number) && $page_number > 0) {
                $page_number = min($page_number, intdiv($count, $list_size) + 1);
                $offset = ($page_number - 1) * $list_size;
                // this may happen if elements were deleted
                while ($offset >= $count) {
                    $offset -= $list_size;
                }
            } else {
                $offset = 0;
            }

            $id_list = array_slice($id_all, $offset, $list_size);

            $person_list = $personRepository->findList($id_list);

            // add empty role, reference and url external if not present
            $authorityRepository = $this->entityManager->getRepository(Authority::class);
            $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
            foreach ($person_list as $person) {
                $person->extractSeeAlso();
                $person->addEmptyDefaultElements($auth_list);
            }

            $template_params = [
                'itemTypeId' => $itemTypeId,
                'personList' => $person_list,
                'form' => $form,
                'error_list' => null,
                'count' => $count,
                'offset' => $offset,
                'pageSize' => $list_size,
            ];

        $template = 'edit_person/query_doublet.html.twig';
        return $this->renderEditElements($template, $itemTypeId, $template_params);

    }

}
