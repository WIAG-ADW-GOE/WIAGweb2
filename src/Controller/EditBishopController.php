<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemReference;
use App\Entity\ItemProperty;
use App\Entity\ItemPropertyType;
use App\Entity\IdExternal;
use App\Entity\Person;
use App\Entity\InputError;
use App\Entity\PersonRole;
use App\Entity\PersonRoleProperty;
use App\Entity\RolePropertyType;
use App\Entity\Authority;
use App\Entity\NameLookup;
use App\Entity\UserWiag;
use App\Form\EditBishopFormType;
use App\Form\Model\BishopFormModel;
use App\Entity\Role;

use App\Service\EditPersonService;
use App\Service\UtilService;

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

class EditBishopController extends AbstractController {
    /** number of suggestions in autocomplete list */
    const HINT_SIZE = 8;

    private $editService;
    private $itemTypeId;
    private $entityManager;

    public function __construct(EditPersonService $editService,
                                EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        $this->editService = $editService;
        $this->itemTypeId = Item::ITEM_TYPE_ID['Bischof']['id'];
    }

    /**
     * display query form for bishops;
     *
     * @Route("/edit/bischof/query", name="edit_bishop_query")
     */
    public function query(Request $request) {

        $model = new BishopFormModel;
        // set defaults
        $model->editStatus = ['fertig'];
        $model->isOnline = true;
        $model->listSize = 5;

        $status_choices = $this->getStatusChoices();

        $form = $this->createForm(EditBishopFormType::class, $model, [
            'status_choices' => $status_choices,
        ]);
        // $form = $this->createForm(EditBishopFormType::class);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        $person_list = array();
        if ($form->isSubmitted() && $form->isValid() && $model->isValid()) {

            $personRepository = $this->entityManager->getRepository(Person::class);

            $itemRepository = $this->entityManager->getRepository(Item::class);

            $limit = 0; $offset = 0; $online_only = false;
            $id_all = $itemRepository->bishopIds($model, $limit, $offset, $online_only);
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

            // add empty role, reference and id external if not present
            foreach ($person_list as $person) {
                $person->addEmptyDefaultElements();
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


        $template = 'edit_bishop/query.html.twig';
        return $this->renderEditElements($template, $template_params);

    }

    /**
     * getStatusChoices()
     *
     * @return choice list for status values
     */
    private function getStatusChoices() {
        $personRepository = $this->entityManager->getRepository(Person::class);

        $suggestions = $personRepository->suggestEditStatus($this->itemTypeId, null, 60);
        $status_list = array_column($suggestions, 'suggestion');

        $status_choices = ['- alle -' => null];
        $status_choices = array_merge($status_choices, array_combine($status_list, $status_list));
        // $status_choices = array_combine($status_list, $status_list);

        return $status_choices;
    }


    /**
     * map data to objects and save them to the database
     * @Route("/edit/bischof/save", name="edit_bishop_save")
     */
    public function save(Request $request) {

        $edit_form_id = 'edit_bishop_edit_form';
        $form_data = $request->request->get($edit_form_id);

        /* map/validate form */
        // dump($form_data);
        // fill person_list
        $person_list = $this->editService->mapFormdata(
            $this->itemTypeId,
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
        if (!$error_flag) {
            $current_user_id = $this->getUser()->getId();
            $personRepository = $this->entityManager->getRepository(Person::class);
            $nameLookupRepository = $this->entityManager->getRepository(NameLookup::class);
            foreach ($person_list as $key => $person) {
                if ($person->getItem()->getFormIsEdited()) {
                    $person_id = $person->getId();
                    if ($person_id == 0) { // new entry
                        // start out with a new object to avoid cascade errors
                        $person_new = $this->editService->makePerson($this->itemTypeId, $current_user_id);
                        $this->editService->initMetaData($person_new, $this->itemTypeId, $current_user_id);
                        $this->entityManager->persist($person_new);
                        $this->entityManager->flush();
                        $person_id = $person_new->getItem()->getId();
                    }
                    // read complete tree to perform deletions if necessary
                    $query_result = $personRepository->findList([$person_id]);
                    $target = $query_result[0];
                    $this->editService->update($target, $person, $current_user_id);
                    $nameLookupRepository->update($target);
                    $target->getItem()->setFormIsEdited(0);

                    $person_list[$key] = $target; // show updated object
                }
            }

            $this->entityManager->flush();

            // add an empty form in case the user wants to add more items
            if ($form_display_type == "new_entry") {
                $person = $this->editService->makePerson($this->itemTypeId, $current_user_id);
                // add empty elements for blank form sections
                $person->addEmptyDefaultElements();
                $person->getItem()->setFormIsExpanded(true);
                $person_list[] = $person;
            }

        }

        // add empty elements for blank form sections
        foreach ($person_list as $person) {
            $person->addEmptyDefaultElements();
        }

        $template = "";
        if ($form_display_type == 'list') {
            $template = 'edit_bishop/_list.html.twig';
        } else {
            $template = 'edit_bishop/new_bishop.html.twig';
        }

        return $this->renderEditElements($template, [
            'personList' => $person_list,
            'count' => count($person_list),
            'formType' => $form_display_type,
        ]);

    }

    private function renderEditElements($template, $param_list = array()) {
        $edit_form_id = 'edit_bishop_edit_form';

        $userWiagRepository = $this->entityManager->getRepository(UserWiag::class);
        $authorityRepository = $this->entityManager->getRepository(Authority::class);

        $auth_base_url_list = $authorityRepository->baseUrlList(array_values(Authority::ID));

        // property types
        $itemPropertyTypeRepository = $this->entityManager->getRepository(ItemPropertyType::class);
        $item_property_type_list = $itemPropertyTypeRepository->findByItemTypeId($this->itemTypeId);
        $rolePropertyTypeRepository = $this->entityManager->getRepository(rolePropertyType::class);
        $role_property_type_list = $rolePropertyTypeRepository->findByItemTypeId($this->itemTypeId);

        $param_list_combined = array_merge($param_list, [
            'menuItem' => 'edit-menu',
            'editFormId' => $edit_form_id,
            'userWiagRepository' => $userWiagRepository,
            'authBaseUrlList' => $auth_base_url_list,
            'itemPropertyTypeList' => $item_property_type_list,
            'rolePropertyTypeList' => $role_property_type_list,
        ]);

        return $this->renderForm($template, $param_list_combined);

    }

    /**
     *
     * @Route("/edit/bischof/delete", name="edit_bishop_delete")
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
     * display query form for bishops; handle query
     *
     * @Route("/edit/bischof/new", name="edit_bishop_new")
     */
    public function newBishop(Request $request) {

        $person = $this->editService->makePerson($this->itemTypeId, $this->getUser()->getId());
        // add empty elements for blank form sections
        $person->addEmptyDefaultElements();
        $person->getItem()->setFormIsExpanded(true);

        $template = 'edit_bishop/new_bishop.html.twig';

        return $this->renderEditElements($template, [
            'personList' => array($person),
            'form' => null,
            'count' => 1,
        ]);

    }

    /**
     * @return template for new item property
     *
     * @Route("/edit/bischof/new-property", name="edit_bishop_new_property")
     */
    public function newProperty(Request $request) {

        $prop = new ItemProperty();

        // property types
        $itemPropertyTypeRepository = $this->entityManager->getRepository(ItemPropertyType::class);
        $item_property_type_list = $itemPropertyTypeRepository->findByItemTypeId($this->itemTypeId);


        return $this->render('edit_bishop/_input_property.html.twig', [
            'base_id' => $request->query->get('base_id'),
            'base_input_name' => $request->query->get('base_input_name'),
            'current_idx' => $request->query->get('current_idx'),
            'prop' => $prop,
            'is_last' => true,
            'itemPropertyTypeList' => $item_property_type_list,
            'itemTypeId' => $this->itemTypeId,
        ]);

    }


    /**
     * @return template for new role
     *
     * @Route("/edit/bischof/new-role", name="edit_bishop_new_role")
     */
    public function newRole(Request $request) {

        $role = new PersonRole;
        $role->setId(0);

        $rolePropertyTypeRepository = $this->entityManager->getRepository(rolePropertyType::class);
        $role_property_type_list = $rolePropertyTypeRepository->findByItemTypeId($this->itemTypeId);


        return $this->render('edit_bishop/_input_role.html.twig', [
            'base_id' => $request->query->get('base_id'),
            'base_input_name' => $request->query->get('base_input_name'),
            'current_idx' => $request->query->get('current_idx'),
            'role' => $role,
            'is_last' => true,
            'rolePropertyTypeList' => $role_property_type_list,
            'itemTypeId' => $this->itemTypeId,
        ]);

    }

    /**
     * @return template for new role
     *
     * @Route("/edit/bischof/new-role-property", name="edit_bishop_new_role_property")
     */
    public function newRoleProperty(Request $request) {

        $prop = new PersonRoleProperty;

        $rolePropertyTypeRepository = $this->entityManager->getRepository(rolePropertyType::class);
        $role_property_type_list = $rolePropertyTypeRepository->findByItemTypeId($this->itemTypeId);


        return $this->render('edit_bishop/_input_role_property.html.twig', [
            'base_id' => $request->query->get('base_id'),
            'base_input_name' => $request->query->get('base_input_name'),
            'current_idx' => $request->query->get('current_idx'),
            'prop' => $prop,
            'is_last' => true,
            'rolePropertyTypeList' => $role_property_type_list,
            'itemTypeId' => $this->itemTypeId,
        ]);

    }


    /**
     * @return template for new reference
     *
     * @Route("/edit/bischof/new-reference", name="edit_bishop_new_reference")
     */
    public function newReference(Request $request) {

        $reference = new ItemReference(0);

        return $this->render('edit_bishop/_input_reference.html.twig', [
            'base_id' => $request->query->get('base_id'),
            'base_input_name' => $request->query->get('base_input_name'),
            'current_idx' => $request->query->get('current_idx'),
            'ref' => $reference,
            'itemTypeId' => $this->itemTypeId,
        ]);

    }

    /**
     * @return template for new external ID
     *
     * @Route("/edit/bischof/new-idexternal", name="edit_bishop_new_idexternal")
     */
    public function newIdExternal(Request $request) {

        $idExternal = new IdExternal(0);

        return $this->render('edit_bishop/_input_id_external.html.twig', [
            'base_id' => $request->query->get('base_id'),
            'base_input_name' => $request->query->get('base_input_name'),
            'current_idx' => $request->query->get('current_idx'),
            'idext' => $idExternal,
            'itemTypeId' => $this->itemTypeId,
        ]);

    }



    /**
     * display query form for bishops; handle query
     *
     * @Route("/edit/bischof/merge-query", name="edit_bishop_merge_query")
     */
    public function mergeQuery(Request $request,
                               FormFactoryInterface $formFactory) {

        $model = new BishopFormModel;
        // set defaults
        $model->editStatus = ['fertig'];
        $model->isOnline = true;
        $model->listSize = 5;

        $status_choices = $this->getStatusChoices();

        $form = $formFactory->createNamed('bishop_merge',
                                          EditBishopFormType::class, $model, [
            'status_choices' => $status_choices,
        ]);
        // $form = $this->createForm(EditBishopFormType::class);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        $template_params = [
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
                'form' => $form,
                'count' => $count,
                'personList' => $person_list,
                'offset' => $offset,
                'pageSize' => $model->listSize,
                'mergeSelectFormId' => 'merge_select', // 2023-02-15 drop?
            ];

        }

        $debug_flag = $request->query->get('debug');
        $template = $debug_flag ? 'merge_query_debug.html.twig' : 'merge_query.html.twig';

        return $this->renderForm('edit_bishop/'.$template, $template_params);

    }

    /**
     * merge data; display merged data in a new window
     *
     * @Route("/edit/bischof/merge-item/{first}/{second_id}", name="edit_bishop_merge_item")
     *
     * merge $first (id) with $second_id (id_in_source) into a new person
     * route paramters are optional, because the JS controller needs the base path.
     */
    public function mergeItem(Request $request,
                              $first = null,
                              $second_id = null) {

        $itemRepository = $this->entityManager->getRepository(Item::class);
        $personRepository = $this->entityManager->getRepository(Person::class);

        // create new person
        $current_user = $this->getUser()->getId();
        $person = $this->editService->makePerson($this->itemTypeId, $current_user);

        // get parent data
        $parent_list = $itemRepository->findById($first);
        $id_in_source = $parent_list[0]->getIdInSource();

        $second = $itemRepository->findMergeCandidate($second_id, $this->itemTypeId);
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
        $person->addEmptyDefaultElements();

        $person->getItem()->setFormIsExpanded(true);
        $person->getItem()->setFormIsEdited(true);

        // child should be findable by its parents IDs : use item.merged_into_id

        $template = 'edit_bishop/new_bishop.html.twig';

        return $this->renderEditElements($template, [
            'personList' => array($person),
            'form' => null,
            'count' => 1,
        ]);

    }

    /**
     * split merged item, show parents in edit forms
     *
     * @Route("/edit/bischof/split-item/{id}", name="edit_bishop_split_item")
     *
     */
    public function splitItem(int $id) {
        $itemRepository = $this->entityManager->getRepository(Item::class);
        $personRepository = $this->entityManager->getRepository(Person::class);

        $item = $itemRepository->find($id);

        // set status values for parents and child
        $person_list = array();
        if (!is_null($item)) {
            $parent_list = $itemRepository->findBy(['mergedIntoId' => $item->getId()]);
            // dd ($parent_list);

            $item->setIsDeleted(1);
            $item->setMergeStatus('orphan');
            $item->setIsOnline(0);

            $online_status = Item::ITEM_TYPE[$item->getItemTypeId()]['online_status'];
            $id_list = array();
            foreach($parent_list as $parent_item) {
                if ($parent_item->getEditStatus() == $online_status) {
                    $parent_item->setIsOnline(1);
                }
                $parent_item->setFormIsExpanded(1);
                $parent_item->setMergeStatus('original');
                $id_list[] = $parent_item->getId();
            }
            $person_list = $personRepository->findList($id_list);

            $this->entityManager->flush();

        } else {
            throw $this->createNotFoundException('ID is nicht gültig: '.$id);
        }

        $template = 'edit_bishop/new_bishop.html.twig';

        return $this->renderEditElements($template, [
            'personList' => $person_list,
            'form' => null,
            'count' => count($person_list),
        ]);

    }

}
