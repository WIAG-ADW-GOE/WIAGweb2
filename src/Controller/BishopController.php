<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Person;
use App\Entity\Authority;
use App\Repository\PersonRepository;
use App\Repository\ItemRepository;
use App\Form\BishopFormType;
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


class BishopController extends AbstractController {
    /** number of items per page */
    const PAGE_SIZE = 20;
    /** number of suggestions in autocomplete list */
    const HINT_SIZE = 8;

    /**
     * display query form for bishops; handle query
     *
     * @Route("/bischof", name="bishop_query")
     */
    public function query(Request $request,
                          ItemRepository $repository) {

        $personRepository = $this->getDoctrine()->getRepository(Person::class);

        // we need to pass an instance of BishopFormModel, because facets depend on it's data
        $model = new BishopFormModel;

        $flagInit = count($request->request->all()) == 0;

        $form = $this->createForm(BishopFormType::class, $model, [
            'forceFacets' => $flagInit,
        ]);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->renderForm('bishop/query.html.twig', [
                    'menuItem' => 'collections',
                    'form' => $form,
            ]);
        } else {

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

            $id_all = $repository->bishopIds($model);
            $count = count($id_all);

            $id_list = array_slice($id_all, $offset, self::PAGE_SIZE);
            $person_list = $personRepository->findList($id_list);

            # TODO 2022-07-15 query all data for ids in $ids (see canon)
            # easy way to get all persons in the right order
            // $person_list = array();
            // foreach($id_list as $id) {
            //     $person = $personRepository->findWithOffice($id["personId"]);
            //     $repository->setSibling($person);
            //     $person_list[] = $person;
            // }

            return $this->renderForm('bishop/query_result.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'count' => $count,
                'personList' => $person_list,
                'offset' => $offset,
                'pageSize' => self::PAGE_SIZE,
            ]);
        }
    }

    /**
     * display details for a bishop
     *
     * @Route("/bischof/listenelement", name="bishop_list_detail")
     */
    public function bishopListDetail(Request $request,
                                     ItemRepository $itemRepository,
                                     PersonRepository $personRepository) {

        $model = new BishopFormModel;

        $form = $this->createForm(BishopFormType::class, $model);
        $form->handleRequest($request);

        $offset = $request->request->get('offset');

        $model = $form->getData();

        $hassuccessor = false;
        $idx = 0;
        if($offset == 0) {
            $ids = $itemRepository->bishopIds($model,
                                              2,
                                              $offset);
            if(count($ids) == 2) $hassuccessor = true;

        } else {
            $ids = $itemRepository->bishopIds($model,
                                              3,
                                              $offset - 1);
            if(count($ids) == 3) $hassuccessor = true;
            $idx += 1;
        }

        $person_id = $ids[$idx];
        $person = $personRepository->find($person_id);

        // collect office data in Person[]
        $personRole = $itemRepository->getBishopOfficeData($person);

        $repository->setSibling($person);

        return $this->render('bishop/person.html.twig', [
            'form' => $form->createView(),
            'personName' => $person,
            'personRole' => $personRole,
            'offset' => $offset,
            'hassuccessor' => $hassuccessor,
        ]);


    }

    /**
     * return bishop data
     *
     * @Route("/bischof/data", name="bishop_query_data")
     */
    public function queryData(Request $request,
                              ItemRepository $repository,
                              PersonRepository $personRepository,
                              PersonService $personService) {

        if ($request->isMethod('POST')) {
            $model = new BishopFormModel();
            $form = $this->createForm(BishopFormType::class, $model);
            $form->handleRequest($request);
            $model = $form->getData();
            $format = $request->request->get('format');
        } else {
            $model = BishopFormModel::newByArray($request->query->all());
            $format = $request->query->get('format') ?? 'json';
        }

        $ids = $repository->bishopIds($model);



        $format = ucfirst(strtolower($format));
        if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
            throw $this->createNotFoundException('Unbekanntes Format: '.$format);
        }

        // 2022-07-21 collect data in one db-query
        $node_list = array();
        foreach ($ids as $id) {

            $person = $personRepository->find($id['personId']);

            // collect office data in an array of Items
            $item_list = $repository->getBishopOfficeData($person);
            $node_list[] = $personService->personData($format, $person, $item_list);
        }

        return $personService->createResponse($format, $node_list);

    }


    /**
     * AJAX
     *
     * @Route("/bischof-suggest/{field}", name="bishop_suggest")
     */
    public function autocomplete(Request $request,
                                 ItemRepository $repository,
                                 String $field) {
        $name = $request->query->get('q');
        $fnName = 'suggestBishop'.ucfirst($field);
        $suggestions = $repository->$fnName($name, self::HINT_SIZE);

        return $this->render('bishop/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

}
