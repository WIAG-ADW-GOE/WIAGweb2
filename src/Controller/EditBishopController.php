<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemReference;
use App\Entity\ItemProperty;
use App\Entity\ItemPropertyType;
use App\Entity\Person;
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
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;


class EditBishopController extends AbstractController {
    /** number of suggestions in autocomplete list */
    const HINT_SIZE = 8;

    private $personService;
    private $itemTypeId;

    public function __construct(PersonService $personService) {
        $this->personService = $personService;
        $this->itemTypeId = Item::ITEM_TYPE_ID['Bischof']['id'];
    }

    /**
     * display query form for bishops; handle query
     *
     * @Route("/edit/bischof/query", name="edit_bishop_query")
     */
    public function query(Request $request,
                          EntityManagerInterface $entityManager) {


        $model = new BishopFormModel;
        // set defaults
        $model->editStatus = ['fertig'];
        $model->isOnline = true;
        $model->listSize = 5;

        $personRepository = $entityManager->getRepository(Person::class);

        $suggestions = $personRepository->suggestEditStatus($this->itemTypeId, null, 60);
        $status_list = array_column($suggestions, 'suggestion');

        $status_choices = ['- alle -' => null];
        $status_choices = array_merge($status_choices, array_combine($status_list, $status_list));
        // $status_choices = array_combine($status_list, $status_list);

        $form = $this->createForm(EditBishopFormType::class, $model, [
            'status_choices' => $status_choices,
        ]);
        // $form = $this->createForm(EditBishopFormType::class);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        if ($form->isSubmitted() && $form->isValid()) {

            $itemRepository = $entityManager->getRepository(Item::class);

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

            $edit_form_id = 'edit_bishop_edit_form';
            $userWiagRepository = $entityManager->getRepository(UserWiag::class);
            $authorityRepository = $entityManager->getRepository(Authority::class);
            $auth_base_url_list = $authorityRepository->baseUrlList(array_values(Authority::ID));

            // property types
            $itemPropertyTypeRepository = $entityManager->getRepository(ItemPropertyType::class);
            $item_property_type_list = $itemPropertyTypeRepository->findByItemTypeId($this->itemTypeId);
            $rolePropertyTypeRepository = $entityManager->getRepository(rolePropertyType::class);
            $role_property_type_list = $rolePropertyTypeRepository->findByItemTypeId($this->itemTypeId);

            return $this->renderForm('edit_bishop/query_result.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'editFormId' => $edit_form_id,
                'count' => $count,
                'personList' => $person_list,
                'userWiagRepository' => $userWiagRepository,
                'offset' => $offset,
                'pageSize' => $model->listSize,
                'authBaseUrlList' => $auth_base_url_list,
                'itemPropertyTypeList' => $item_property_type_list,
                'rolePropertyTypeList' => $role_property_type_list,
            ]);
        }

        return $this->renderForm('edit_bishop/query.html.twig', [
            'menuItem' => 'collections',
            'form' => $form,
        ]);

    }

    /**
     * map data to objects and save them to the database
     * @Route("/edit/bischof/save", name="edit_bishop_save")
     */
    public function save(Request $request,
                         EntityManagerInterface $entityManager) {

        $edit_form_id = 'edit_bishop_edit_form';
        $form_data = $request->request->get($edit_form_id);


        /* map/validate form */
        $current_user_id = $this->getUser()->getId();

        $personRepository = $entityManager->getRepository(Person::class);
        $person_list = array();
        $flag_error = false;
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
                    $flag_error = true;
                }
            }

            // set form collapse state
            $expanded_param = isset($data['item']['formIsExpanded']) ? 1 : 0;
            $person->getItem()->setFormIsExpanded($expanded_param);
        }

        $new_entry = $request->query->get('newEntry');

        // save data
        if (!$flag_error) {
            // save changes to database
            // any object that was retrieved via Doctrine is stored to the database

            $nameLookupRepository = $entityManager->getRepository(NameLookup::class);
            $itemRepository = $entityManager->getRepository(Item::class);

            // rebuild $person_list with persistent new entries
            $person_list = array();
            foreach($form_data as $data) {
                $person_id = $data['item']['id'];
                $edited_flag = isset($data['item']['formIsEdited']);
                $expanded_flag = isset($data['item']['formIsExpanded']) ? 1 : 0;
                $person = null;
                if ($edited_flag) {
                    if (!$person_id > 0) { // new entry
                        $item = Item::newItem($current_user_id, 'Bischof');
                        $person = Person::newPerson($item);
                        $entityManager->persist($person);
                        $entityManager->flush();
                        $person_id = $item->getId();
                        $person = $personRepository->find($person_id);
                        $person_list[] = $person;
                        $this->personService->mapPerson($person, $data, $current_user_id);
                        $person->getItem()->setFormIsExpanded($expanded_flag);
                    } elseif ($person_id > 0) { // edited, no errors: use data from first mapping
                        $person = $personRepository->findList([$person_id])[0];
                        $person_list[] = $person;
                        $person->getItem()->setFormIsExpanded($expanded_flag);
                    }
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

            $entityManager->flush();

            if ($new_entry) {
                $item_type_id = $this->itemTypeId;
                $itemRepository = $entityManager->getRepository(Item::class);
                $id_in_source = $itemRepository->findMaxIdInSource($this->itemTypeId) + 1;

                $person = $this->personService->makePersonScheme($id_in_source, $this->getUser()->getId());
                $person_list[] = $person;
            }


        }


        $userWiagRepository = $entityManager->getRepository(UserWiag::class);
        $authorityRepository = $entityManager->getRepository(Authority::class);

        $auth_base_url_list = $authorityRepository->baseUrlList(array_values(Authority::ID));

        // property types
        $itemPropertyTypeRepository = $entityManager->getRepository(ItemPropertyType::class);
        $item_property_type_list = $itemPropertyTypeRepository->findByItemTypeId($this->itemTypeId);
        $rolePropertyTypeRepository = $entityManager->getRepository(rolePropertyType::class);
        $role_property_type_list = $rolePropertyTypeRepository->findByItemTypeId($this->itemTypeId);

        $template = "";
        if ($request->query->get('listOnly')) {
            $template = 'edit_bishop/_list.html.twig';
        } else { // useful for debugging: dump output is accessible
            $template = 'edit_bishop/edit_result.html.twig';
        }

        return $this->render($template, [
            'menuItem' => 'collections',
            'personList' => $person_list,
            'newEntry' => $new_entry,
            'editFormId' => $edit_form_id,
            'userWiagRepository' => $userWiagRepository,
            'authBaseUrlList' => $auth_base_url_list,
            'itemPropertyTypeList' => $item_property_type_list,
            'rolePropertyTypeList' => $role_property_type_list,
        ]);
    }

    /**
     * display query form for bishops; handle query
     *
     * @Route("/edit/bischof/new", name="edit_bishop_new")
     */
    public function newBishop(Request $request,
                              EntityManagerInterface $entityManager) {

        $itemRepository = $entityManager->getRepository(Item::class);
        $id_in_source = $itemRepository->findMaxIdInSource($this->itemTypeId) + 1;

        $person = $this->personService->makePersonScheme($id_in_source, $this->getUser()->getId());

        $person_list = array($person);

        $edit_form_id = 'edit_bishop_edit_form';

        $userWiagRepository = $entityManager->getRepository(UserWiag::class);
        $authorityRepository = $entityManager->getRepository(Authority::class);
        $auth_base_url_list = $authorityRepository->baseUrlList(array_values(Authority::ID));

        // property types
        $itemPropertyTypeRepository = $entityManager->getRepository(ItemPropertyType::class);
        $item_property_type_list = $itemPropertyTypeRepository->findByItemTypeId($this->itemTypeId);
        $rolePropertyTypeRepository = $entityManager->getRepository(rolePropertyType::class);
        $role_property_type_list = $rolePropertyTypeRepository->findByItemTypeId($this->itemTypeId);


        return $this->render('edit_bishop/new_bishop.html.twig', [
            'menuItem' => 'collections',
            'form' => null,
            'editFormId' => $edit_form_id,
            'count' => 1,
            'personList' => $person_list,
            // find user WIAG for all elements of $person_list
            'userWiagRepository' => $userWiagRepository,
            'authBaseUrlList' => $auth_base_url_list,
            'itemPropertyTypeList' => $item_property_type_list,
            'rolePropertyTypeList' => $role_property_type_list,
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

        return $this->render('edit_bishop/_input_reference.html.twig', [
            'base_id_ref' => $request->query->get('base_id'),
            'base_input_name_ref' => $request->query->get('base_input_name'),
            'ref' => $reference,
            'itemTypeId' => $this->itemTypeId,
        ]);

    }

    /**
     * @return template for additional item property
     *
     * @Route("/edit/bischof/new-property", name="edit_bishop_new_property")
     */
    public function newProperty(Request $request,
                                EntityManagerInterface $entityManager) {

        $property = new ItemProperty;

        // property types
        $itemPropertyTypeRepository = $entityManager->getRepository(ItemPropertyType::class);
        $item_property_type_list = $itemPropertyTypeRepository->findByItemTypeId($this->itemTypeId);
        $property->setType($item_property_type_list[0]);

        return $this->render('edit_bishop/_input_property.html.twig', [
            'base_id_prop' => $request->query->get('base_id'),
            'base_input_name_prop' => $request->query->get('base_input_name'),
            'prop' => $property,
            'itemTypeId' => $this->itemTypeId,
            'itemPropertyTypeList' => $item_property_type_list,
        ]);

    }

    /**
     * @return template for additional role property
     *
     * @Route("/edit/bischof/new-role-property", name="edit_bishop_new_role_property")
     */
    public function new_role_property(Request $request,
                                      EntityManagerInterface $entityManager) {

        $property = new PersonRoleProperty;
        $rolePropertyTypeRepository = $entityManager->getRepository(rolePropertyType::class);
        $role_property_type_list = $rolePropertyTypeRepository->findByItemTypeId($this->itemTypeId);
        $property->setType($role_property_type_list[0]);


        return $this->render('edit_bishop/_input_role_property.html.twig', [
            'base_id_prop' => $request->query->get('base_id'),
            'base_input_name_prop' => $request->query->get('base_input_name'),
            'prop' => $property,
            'itemTypeId' => $this->itemTypeId,
            'rolePropertyTypeList' => $role_property_type_list,
        ]);

    }


}
