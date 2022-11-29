<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemReference;
use App\Entity\ItemProperty;
use App\Entity\ItemPropertyType;
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

use App\Service\PersonService;
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

    private $personService;
    private $itemTypeId;
    private $entityManager;

    public function __construct(PersonService $personService,
                                EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        $this->personService = $personService;
        $this->itemTypeId = Item::ITEM_TYPE_ID['Bischof']['id'];
    }

    /**
     * display query form for bishops; handle query
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

        $template_params = [
            'form' => $form,
        ];

        $person_list = array();
        if ($form->isSubmitted() && $form->isValid()) {
            $personRepository = $this->entityManager->getRepository(Person::class);

            $itemRepository = $this->entityManager->getRepository(Item::class);

            $id_all = $itemRepository->bishopIds($model);
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
                'personList' => $person_list,
                'form' => $form,
                'count' => $count,
                'offset' => $offset,
                'pageSize' => $model->listSize,
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
        $current_user_id = $this->getUser()->getId();

        $personRepository = $this->entityManager->getRepository(Person::class);
        $person_list = array();
        $error_flag = false;
        foreach($form_data as $data) {
            $person_id = $data['item']['id'];
            if ($person_id > 0) {
                // get complete Person
                $person = $personRepository->findList([$person_id])[0];
                $person_list[] = $person;
            } else {
                // new entry
                $item = Item::newItem($current_user_id, 'Bischof');
                $person = Person::newPerson($item);
                // map id and keep form open
                $item->setIdInSource($data['item']['idInSource']);
                $item->setFormIsExpanded(1);
                $person_list[] = $person;
            }

            if (isset($data['item']['formIsEdited'])) {
                $this->personService->mapPerson($person, $data, $current_user_id);

                if ($person->hasError('error')) {
                    $error_flag = true;
                }
            }

            // set form collapse state
            $expanded_param = isset($data['item']['formIsExpanded']) ? 1 : 0;
            $person->getItem()->setFormIsExpanded($expanded_param);
        }

        $new_entry = $request->query->get('newEntry');

        // save data
        if (!$error_flag) {
            $person_list = array();
            // otherwise previous data for roles show still up 2022-10-06
            $this->entityManager->clear();
            // save changes to database
            // any object that was retrieved via Doctrine is stored to the database

            $nameLookupRepository = $this->entityManager->getRepository(NameLookup::class);
            $itemRepository = $this->entityManager->getRepository(Item::class);

            // rebuild $person_list with persistent new entries
            foreach($form_data as $data) {
                $person_id = $data['item']['id'];
                $edited_flag = isset($data['item']['formIsEdited']);
                $expanded_flag = isset($data['item']['formIsExpanded']) ? 1 : 0;
                $person = null;
                if ($edited_flag) {
                    if ($person_id < 1) { // new entry
                        $person = $this->personService->makePersonPersist($current_user_id, 'Bischof');
                        $this->personService->updateMerged($data, $current_user_id);
                    } else {
                        // edited, no errors
                        $person = $personRepository->findList([$person_id])[0];
                    }
                    $this->personService->mapPerson($person, $data, $current_user_id);
                    $person_list[] = $person;
                    $person->getItem()->setFormIsExpanded($expanded_flag);

                } elseif(!$new_entry) { // in edit/change-mode restore complete list
                    $person = $personRepository->findList([$person_id])[0];
                    $person_list[] = $person;
                }
                // do nothing for new elements that were not edited
            }

            foreach ($person_list as $person) {
                if ($person->getItem()->getFormIsEdited()) {
                    // update table name_lookup
                    $nameLookupRepository->update($person);
                    // reset edit flag
                    $person->getItem()->setFormIsEdited(0);
                }
            }

            $this->entityManager->flush();

            // add an empty form in case the user wants to add more items
            if ($new_entry) {
                $item_type_id = $this->itemTypeId;
                $itemRepository = $this->entityManager->getRepository(Item::class);
                $id_in_source = $itemRepository->findMaxIdInSource($this->itemTypeId) + 1;

                $person = $this->personService->makePersonScheme($id_in_source, $this->getUser()->getId());
                $person_list[] = $person;
            }
        }

        $template = "";
        if ($request->query->get('listOnly')) {
            $template = 'edit_bishop/_list.html.twig';
        } else { // useful for debugging: dump output is accessible; see edit_bishop/_list.html.twig
            $template = 'edit_bishop/edit_result.html.twig';
        }

        return $this->renderEditElements($template, [
            'personList' => $person_list,
            'newEntry' => $new_entry,
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
     * display query form for bishops; handle query
     *
     * @Route("/edit/bischof/new", name="edit_bishop_new")
     */
    public function newBishop(Request $request) {

        $itemRepository = $this->entityManager->getRepository(Item::class);
        $id_in_source = $itemRepository->findMaxIdInSource($this->itemTypeId) + 1;

        $person = $this->personService->makePersonScheme($id_in_source, $this->getUser()->getId());

        $person_list = array($person);

        $template = 'edit_bishop/new_bishop.html.twig';

        return $this->renderEditElements($template, [
            'personList' => $person_list,
            'form' => null,
            'count' => 1,
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

        return $this->render('edit_bishop/_input_role.html.twig', [
            'base_id_role' => $request->query->get('base_id'),
            'base_input_name_role' => $request->query->get('base_input_name'),
            'intern_collapsable' => false,
            'role' => $role,
        ]);

    }

    /**
     * @return template for new reference
     *
     * @Route("/edit/bischof/new-reference", name="edit_bishop_new_reference")
     */
    public function newReference(Request $request) {

        $reference = new ItemReference(0);

        return $this->render('edit_bishop/_input_reference_core.html.twig', [
            'base_id' => $request->query->get('base_id'),
            'base_input_name' => $request->query->get('base_input_name'),
            'current_idx' => $request->query->get('current_idx'),
            'ref' => $reference,
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

        $form = $formFactory->createNamed('bishop_merge', EditBishopFormType::class, $model, [
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

            $id_all = $itemRepository->bishopIds($model);
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
                'mergeSelectFormId' => 'merge_select',
            ];

        }

        $debug_flag = $request->query->get('debug');
        $template = $debug_flag ? 'merge_query_debug.html.twig' : 'merge_query.html.twig';

        return $this->renderForm('edit_bishop/'.$template, $template_params);

    }

    /**
     * merge data in a form section
     *
     * @Route("/edit/bischof/merge-item/{first}/{index}/{second_id}", name="edit_bishop_merge_item")
     * merge $first (id) with $second_id (id_in_source) into a new person
     * route paramters are optional, because the JS controller needs the base path.
     * 2022-11-25: $index is obsolete if merged data are displayed in a new window.
     */
    public function mergeItem(Request $request,
                              $first = null,
                              $index = null,
                              $second_id = null) {

        $itemRepository = $this->entityManager->getRepository(Item::class);
        $personRepository = $this->entityManager->getRepository(Person::class);
        $itemRepository = $this->entityManager->getRepository(Item::class);

        $id_in_source = $itemRepository->findMaxIdInSource($this->itemTypeId) + 1;
        $wiag_user_id = $this->getUser()->getId();
        $person = $this->personService->makePersonScheme($id_in_source, $wiag_user_id);

        $id_list = array(intval($first, 10));

        // $second_id is item.id_in_source
        // dump($second_id);
        $second_item = $itemRepository->findOneBy([
            'idInSource' => intval($second_id, 10),
            'itemTypeId' => Item::ITEM_TYPE_ID['Bischof']['id'],
        ]);

        if ($second_item) {
            $id_list[] = $second_item->getId();
        }

        $person_merge_list = $personRepository->findList($id_list);

        $ancestor_list = $person->getMergeAncestor();
        $person->merge($person_merge_list[0]);
        $ancestor_list->add($person_merge_list[0]->getItem()->getIdInSource());

        if (count($person_merge_list) > 1) {
            $person->merge($person_merge_list[1]);
            $ancestor_list->add($person_merge_list[1]->getItem()->getIdInSource());
        } else {
            $person->getInputError()->add(new InputError("status", "Zu {$second_id} wurde keine Person gefunden"));
        }

        $person_list=array($person);

        $template = 'edit_bishop/new_bishop.html.twig';

        return $this->renderEditElements($template, [
            'personList' => $person_list,
            'form' => null,
            'count' => 1,
        ]);

        // obsolete 2022-11-25
        $template = 'edit_bishop/new_bishop.html.twig';

        $template = 'edit_bishop/_item.html.twig';

        return $this->renderEditElements($template, [
            'person' => $person_list[0],
            'index' => $index,
        ]);
    }

}
