<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemReference;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\Authority;
use App\Entity\NameLookup;
use App\Entity\UserWiag;
use App\Form\EditBishopFormType;
use App\Form\Model\BishopFormModel;
use App\Entity\Role;

use App\Service\PersonService;

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

    /**
     * display query form for bishops; handle query
     *
     * @Route("/edit/bischof/query", name="edit_bishop_query")
     */
    public function query(Request $request,
                          EntityManagerInterface $entityManager) {


        $model = new BishopFormModel;
        // set defaults
        // $model->editStatus = [null];
        $model->isOnline = true;
        $model->listSize = 5;

        $personRepository = $entityManager->getRepository(Person::class);

        $suggestions = $personRepository->suggestEditStatus(Item::ITEM_TYPE_ID['Bischof'], null, 60);
        $status_list = array_column($suggestions, 'suggestion');

        // $status_choices = ['- alle -' => null];
        // $status_choices = array_merge($status_choices, array_combine($status_list, $status_list));
        $status_choices = array_combine($status_list, $status_list);

        dump($status_choices);
        $form = $this->createForm(EditBishopFormType::class, $model, [
            'status_choices' => $status_choices,
        ]);
        // $form = $this->createForm(EditBishopFormType::class);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();
        dump($model->editStatus);

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

            return $this->renderForm('edit_bishop/query_result.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'editFormId' => $edit_form_id,
                'count' => $count,
                'personlist' => $person_list,
                'offset' => $offset,
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
                         PersonService $personService,
                         EntityManagerInterface $entityManager) {

        $edit_form_id = 'edit_bishop_edit_form';
        $form_data = $request->request->get($edit_form_id);

        /* save data */
        $current_user_id = $this->getUser()->getId();

        $personRepository = $entityManager->getRepository(Person::class);
        $person_list = array();
        $flag_error = false;
        foreach($form_data as $data) {
            // dump($data);
            $person_id = $data['item']['id'];
            if (isset($data['item']['formIsEdited'])) {
                $person = $personRepository->find($person_id);
                $personService->mapPerson($person, $data, $current_user_id);
                if (!$person->getInputError()->isEmpty()) {
                    $flag_error = true;
                }
                $person_list[] = $person;
            } else {
                // get roles and references from the database
                $person_list = array_merge($person_list, $personRepository->findList([$person_id]));
            }
        }

        if (!$flag_error) {
            // save changes to database
            // any object that was retrieved via Doctrine is stored to the database

            // update table name_lookup
            $nameLookupRepository = $entityManager->getRepository(NameLookup::class);
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

            // get data from the database
            $id_list = array_map(function($el) {
                return $el['item']['id'];
            }, $form_data);

            $person_list = $personRepository->findList($id_list);
        }

        if ($request->query->get('list_only')) {
            return $this->render('edit_bishop/_list.html.twig', [
                'menuItem' => 'collections',
                'personlist' => $person_list,
                'editFormId' => $edit_form_id,
            ]);
        }

        return $this->render('edit_bishop/edit_result.html.twig', [
                'menuItem' => 'collections',
                'personlist' => $person_list,
                'editFormId' => $edit_form_id,
        ]);
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
            'itemTypeId' => Item::ITEM_TYPE_ID['Bischof'],
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
