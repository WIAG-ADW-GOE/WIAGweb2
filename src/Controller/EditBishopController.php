<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Person;
use App\Entity\Authority;
use App\Form\EditBishopFormType;
use App\Form\Model\EditBishopFormModel;
use App\Entity\Role;

use App\Service\ItemService;
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
    /** number of items per page */
    const PAGE_SIZE = 5;
    /** number of suggestions in autocomplete list */
    const HINT_SIZE = 8;

    /**
     * display query form for bishops; handle query
     *
     * @Route("/edit/bischof/query", name="edit_bishop_query")
     */
    public function query(Request $request,
                          EntityManagerInterface $entityManager,
                          ItemService $service) {


        // we need to pass an instance of BishopFormModel, because facets depend on it's data
        $model = new EditBishopFormModel;

        $flagInit = count($request->request->all()) == 0;

        $form = $this->createForm(EditBishopFormType::class, $model);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        if ($form->isSubmitted() && $form->isValid()) {

            $itemRepository = $entityManager->getRepository(Item::class);

            $countResult = $itemRepository->countEditBishop($model);
            $count = $countResult["n"];

            $offset = $request->request->get('offset');
            $page_number = $request->request->get('pageNumber');

            // set offset to page begin
            if (!is_null($offset)) {
                $offset = intdiv($offset, self::PAGE_SIZE) * self::PAGE_SIZE;
            } elseif (!is_null($page_number) && $page_number > 0) {
                $page_number = min($page_number, intdiv($count, self::PAGE_SIZE) + 1);
                $offset = ($page_number - 1) * self::PAGE_SIZE;
            } else {
                $offset = 0;
            }

            $ids = $itemRepository->idsEditBishop($model,
                                                  self::PAGE_SIZE,
                                                  $offset);

            $person_list = $this->personList(array_column($ids, 'personId'), $entityManager, $service);

            $edit_form_id = 'edit_bishop_edit_form';

            return $this->renderForm('edit_bishop/query_result.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'editFormId' => $edit_form_id,
                'count' => $count,
                'personlist' => $person_list,
                'offset' => $offset,
                'pageSize' => self::PAGE_SIZE,
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
                         EntityManagerInterface $entityManager,
                         ItemService $service) {

        $edit_form_id = 'edit_bishop_edit_form';
        $form_data = $request->request->get($edit_form_id);

        // save data
        $user_id = $this->getUser()->getId();

        $personRepository = $entityManager->getRepository(Person::class);
        $person_list = array();
        foreach($form_data as $person_loop) {
            // find person and update
            $id = $person_loop['item']['id'];
            $person = $personRepository->findWithOffice($id);

            $item = $person->getItem();
            // TODO $service->updateItem($item, $person_loop)
            // TODO $service->updatePerson($person, $person_loop)

            $person->setSibling($service->getSibling($person)); // ??
            // TODO save to database
            $person_list[] = $person;
        }

        if ($request->query->get('list')) {
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

    private function personList($ids,
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
