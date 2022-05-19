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
use App\Entity\PersonHeader;

use App\Service\ItemService;
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
            $offset = $request->request->get('offset') ?? 0;
            // set offset to page begin
            $offset = (int) floor($offset / self::PAGE_SIZE) * self::PAGE_SIZE;

            $countResult = $repository->countBishop($model);
            $count = $countResult["n"];

            $ids = $repository->bishopIds($model,
                                          self::PAGE_SIZE,
                                          $offset);

            # easy way to get all persons in the right order
            $cPerson = array();
            foreach($ids as $id) {
                $cPerson[] = $personRepository->findWithOffice($id["personId"]);
            }

            return $this->renderForm('bishop/query_result.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'count' => $count,
                'cperson' => $cPerson,
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
                                     ItemRepository $repository,
                                     PersonRepository $personRepository,
                                     ItemService $service) {

        $model = new BishopFormModel;

        $form = $this->createForm(BishopFormType::class, $model);
        $form->handleRequest($request);

        $offset = $request->request->get('offset');

        $model = $form->getData();

        $hassuccessor = false;
        $idx = 0;
        if($offset == 0) {
            $ids = $repository->bishopIds($model,
                                           2,
                                           $offset);
            if(count($ids) == 2) $hassuccessor = true;

        } else {
            $ids = $repository->bishopIds($model,
                                           3,
                                           $offset - 1);
            if(count($ids) == 3) $hassuccessor = true;
            $idx += 1;
        }

        $person_id = $ids[$idx]['personId'];
        $person = $personRepository->find($person_id);

        // collect office data in an array of Items
        $item = $service->getBishopOfficeData($person);

        $person_header = new PersonHeader($person);
        foreach($item as $item_loop) {
            if ($item_loop->getSource() == 'Domherr') {
                $person_header->setSecond($item_loop->getPerson());
            }
        }

        return $this->render('bishop/person.html.twig', [
            'form' => $form->createView(),
            'person' => $person,
            'personheader' => $person_header,
            'item' => $item,
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
                              ItemService $itemService,
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

        $node_list = array();
        foreach ($ids as $id) {

            $person = $personRepository->find($id['personId']);

            // collect office data in an array of Items
            $item_list = $itemService->getBishopOfficeData($person);
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
