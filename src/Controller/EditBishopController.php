<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemReference;
use App\Entity\ItemProperty;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\PersonRoleProperty;
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

    public function __construct(PersonService $personService) {
        $this->personService = $personService;
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

        $suggestions = $personRepository->suggestEditStatus(Item::ITEM_TYPE_ID['Bischof']['id'], null, 60);
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

            return $this->renderForm('edit_bishop/query_result.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'editFormId' => $edit_form_id,
                'count' => $count,
                'personlist' => $person_list,
                'userWiagRepository' => $userWiagRepository,
                'offset' => $offset,
                'newEntry' => 0,
                'pageSize' => $model->listSize,
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
        $new_entry_param = $request->query->get('new_entry');

        /* save data */
        $current_user_id = $this->getUser()->getId();

        $personRepository = $entityManager->getRepository(Person::class);
        $person_list = array();
        $flag_error = false;
        $item_type_id = Item::ITEM_TYPE_ID['Bischof']['id'];
        foreach($form_data as $data) {
            $person_id = $data['item']['id'];
            if (isset($data['item']['formIsEdited'])) {

                if (trim($person_id) == "") {
                    $item = Item::newItem($current_user_id, 'Bischof');
                    $person = Person::newPerson($item);
                    $entityManager->persist($item);
                    $entityManager->persist($person);
                    $entityManager->flush();
                    // only item receives the new id ?! (miracles of Doctrine)
                    $person_id = $item->getId();
                }
                $person = $personRepository->find($person_id);

                $this->personService->mapPerson($person, $data, $current_user_id);

                if ($person->hasError('error')) {
                    $flag_error = true;
                }
                $person_list[] = $person;
            } else {
                // if new entries are edited only add entries that changed
                if (is_null($new_entry_param) || $new_entry_param < 1) {
                    // get roles and references from the database
                    $person_list = array_merge($person_list, $personRepository->findList([$person_id]));
                }
            }
        }

        if (!$flag_error) {
            // save changes to database
            // any object that was retrieved via Doctrine is stored to the database

            // update table name_lookup
            $nameLookupRepository = $entityManager->getRepository(NameLookup::class);
            $itemRepository = $entityManager->getRepository(Item::class);
            foreach ($person_list as $person) {
                if ($person->getItem()->getFormIsEdited()) {
                    $nameLookupRepository->update($person);
                }
            }

            $entityManager->flush();

            // unset edit flag
            foreach ($person_list as $person) {
                $person->getItem()->setFormIsEdited(false);
            }

            $id_list = array_map(function($el) {
                return $el->getId();
            }, $person_list);

            $person_list = $personRepository->findList($id_list);


            if (!is_null($new_entry_param) && $new_entry_param > 0) {
                $id_in_source = $itemRepository->findMaxIdInSource($item_type_id) + 1;
                $person = $this->makePersonScheme($id_in_source);
                $person_list[] = $person;
            } else {
                $new_entry_param = 0;
            }
        }

        $userWiagRepository = $entityManager->getRepository(UserWiag::class);

        if ($request->query->get('list_only')) {
            return $this->render('edit_bishop/_list.html.twig', [
                'menuItem' => 'collections',
                'personlist' => $person_list,
                'newEntry' => $new_entry_param,
                'editFormId' => $edit_form_id,
                'userWiagRepository' => $userWiagRepository,
            ]);
        }

        return $this->render('edit_bishop/edit_result.html.twig', [
                'menuItem' => 'collections',
                'personlist' => $person_list,
                'editFormId' => $edit_form_id,
                'newEntry' => $new_entry_param,
                'userWiagRepository' => $userWiagRepository,
        ]);
    }

    /**
     * display query form for bishops; handle query
     *
     * @Route("/edit/bischof/new", name="edit_bishop_new")
     */
    public function newBishop(Request $request,
                              EntityManagerInterface $entityManager) {

        $item_type_id = Item::ITEM_TYPE_ID['Bischof']['id'];
        $itemRepository = $entityManager->getRepository(Item::class);
        $id_in_source = $itemRepository->findMaxIdInSource($item_type_id) + 1;

        $person = $this->makePersonScheme($id_in_source);

        $person_list = array($person);

        $edit_form_id = 'edit_bishop_edit_form';

        $userWiagRepository = $entityManager->getRepository(UserWiag::class);

        return $this->render('edit_bishop/new_bishop.html.twig', [
            'menuItem' => 'collections',
            'form' => null,
            'editFormId' => $edit_form_id,
            'count' => 1,
            'personlist' => $person_list,
            'newEntry' => 1,
            // find user WIAG for all elements of $person_list
            'userWiagRepository' => $userWiagRepository,
        ]);

    }

    /**
     * create person object
     */
    private function makePersonScheme($id_in_source) {

        // generate an object of type person as scheme
        $userWiagId = $this->getUser()->getId();

        $item = Item::newItem($userWiagId, 'Bischof');
        $person = Person::newPerson($item);

        $item->setIdInSource($id_in_source);

        // add role
        $role = new PersonRole();
        $person->getRole()->add($role);
        $role->setPerson($person);

        // add reference
        $reference = new ItemReference();
        $item->getReference()->add($reference);
        $reference->setItem($item);

        return $person;
    }


    /**
     * @return template for new role
     *
     * @Route("/edit/bischof/new-role", name="edit_bishop_new_role")
     */
    public function new_role(Request $request) {

        $role = new PersonRole;
        $role->setId(0);

        return $this->render('edit_bishop/_input_role.html.twig', [
            'base_id_role' => $request->query->get('base_id'),
            'base_input_name_role' => $request->query->get('base_input_name'),
            'role' => $role,
        ]);

    }

    /**
     * @return template for new reference
     *
     * @Route("/edit/bischof/new-reference", name="edit_bishop_new_reference")
     */
    public function new_reference(Request $request) {

        $reference = new ItemReference(0);

        return $this->render('edit_bishop/_input_reference.html.twig', [
            'base_id_ref' => $request->query->get('base_id'),
            'base_input_name_ref' => $request->query->get('base_input_name'),
            'ref' => $reference,
            'itemTypeId' => Item::ITEM_TYPE_ID['Bischof']['id'],
        ]);

    }

    /**
     * @return template for additional item property
     *
     * @Route("/edit/bischof/new-property", name="edit_bishop_new_property")
     */
    public function new_property(Request $request) {

        $property = new ItemProperty;

        return $this->render('edit_bishop/_input_property.html.twig', [
            'base_id_prop' => $request->query->get('base_id'),
            'base_input_name_prop' => $request->query->get('base_input_name'),
            'prop' => $property,
            'itemTypeId' => Item::ITEM_TYPE_ID['Bischof']['id'],
        ]);

    }

    /**
     * @return template for additional role property
     *
     * @Route("/edit/bischof/new-role-property", name="edit_bishop_new_role_property")
     */
    public function new_role_property(Request $request) {

        $property = new PersonRoleProperty;

        return $this->render('edit_bishop/_input_property.html.twig', [
            'base_id_prop' => $request->query->get('base_id'),
            'base_input_name_prop' => $request->query->get('base_input_name'),
            'prop' => $property,
            'itemTypeId' => Item::ITEM_TYPE_ID['Bischof']['id'],
        ]);

    }



    /**
     * 2022-09-01 obsolete?
     */
    private function personList_hide($ids,
                                EntityManagerInterface $entityManager,
                                $service) {
        # easy way to get all persons in the right order
        $personRepository = $entityManager->getRepository(Person::class);
        $person_list = array();
        foreach($ids as $id) {
            $person = $personRepository->findWithOffice($id);
            $person->setSibling($service->getSibling($person));
            $person_list[] = $person;
        }
        return $person_list;
    }

}
