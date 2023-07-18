<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemReference;
use App\Entity\Person;
use App\Entity\Authority;
use App\Entity\PlaceIdExternal;
use App\Repository\PersonRepository;
use App\Repository\ItemRepository;
use App\Form\PriestUtFormType;
use App\Form\Model\PriestUtFormModel;
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

class PriestUtController extends AbstractController {
    /** number of items per page */
    const PAGE_SIZE = 20;
    /** number of suggestions in autocomplete list */
    const HINT_SIZE = 8;


    /**
     * display query form for priestUts; handle query
     *
     * @Route("/priest_utrecht", name="priest_ut_query")
     */
    public function query(Request $request,
                          EntityManagerInterface $entityManager) {

        // we need to pass an instance of PriestUtFormModel, because facets depend on it's data
        $model = new PriestUtFormModel;

        $flagInit = count($request->request->all()) == 0;

        $form = $this->createForm(PriestUtFormType::class, $model, [
            'forceFacets' => $flagInit,
        ]);


        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        $itemRepository = $entityManager->getRepository(Item::class);

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->renderForm('priest_ut/query.html.twig', [
                    'menuItem' => 'collections',
                    'form' => $form,
            ]);
        } else {
            // $count_result = $itemRepository->countPriestUt($model);

            $id_all = $itemRepository->priestUtIds($model);
            $count = count($id_all);
            // $count = $count_result["n"];

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

            $personRepository = $entityManager->getRepository(Person::class);

            $id_list = array_slice($id_all, $offset, self::PAGE_SIZE);
            $person_list = $personRepository->findList($id_list);

            return $this->renderForm('priest_ut/query_result.html.twig', [
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
     * display details for a priestUt
     *
     * @Route("/priest_utrecht/listenelement", name="priest_ut_list_detail")
     */
    public function priestUtListDetail(Request $request,
                                       EntityManagerInterface $entityManager) {

        $model = new PriestUtFormModel;

        $form = $this->createForm(PriestUtFormType::class, $model);
        $form->handleRequest($request);

        $offset = $request->request->get('offset');
        $model = $form->getData();

        $itemRepository = $entityManager->getRepository(Item::class);

        $hassuccessor = false;
        $idx = 0;
        if($offset == 0) {
            $ids = $itemRepository->priestUtIds($model,
                                                2,
                                                $offset);
            if(count($ids) == 2) $hassuccessor = true;

        } else {
            $ids = $itemRepository->priestUtIds($model,
                                                3,
                                                $offset - 1);
            if(count($ids) == 3) $hassuccessor = true;
            $idx += 1;
        }

        $personRepository = $entityManager->getRepository(Person::class);
        $person_id = $ids[$idx];

        $person_list = $personRepository->findList([$person_id]);
        if (!is_null($person_list) && count($person_list) > 0) {
            $person = $person_list[0];
        } else {
            throw $this->createNotFoundException('Priester nicht gefunden');
            $person = null;
        }

        $birthplace = $person->getBirthplace();
        if ($birthplace) {
            $pieRepository = $this->getDoctrine()
                                  ->getRepository(PlaceIdExternal::class);
            foreach ($birthplace as $bp) {
                $bp->setUrlWhg($pieRepository->findUrlWhg($bp->getPlaceId()));
            }
        }

        return $this->render('priest_ut/person.html.twig', [
            'form' => $form->createView(),
            'person' => $person,
            'offset' => $offset,
            'hassuccessor' => $hassuccessor,
        ]);


    }

    /**
     * return priestUt data
     *
     * @Route("/priest_utrecht/data", name="priest_ut_query_data")
     */
    public function queryData(Request $request,
                              EntityManagerInterface $entityManager,
                              PersonService $personService) {

        if ($request->isMethod('POST')) {
            $model = new PriestUtFormModel();
            $form = $this->createForm(PriestUtFormType::class, $model);
            $form->handleRequest($request);
            $model = $form->getData();
            $format = $request->request->get('format');
        } else {
            $model = PriestUtFormModel::newByArray($request->query->all());
            $format = $request->query->get('format') ?? 'json';
        }


        $format = ucfirst(strtolower($format));
        if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
            throw $this->createNotFoundException('Unbekanntes Format: '.$format);
        }


        $itemRepository = $entityManager->getRepository(Item::class);
        $personRepository = $entityManager->getRepository(Person::class);
        $itemReferenceRepository = $entityManager->getRepository(ItemReference::class);

        $id_all = $itemRepository->priestUtIds($model);
        $person_list = $personRepository->findList($id_all);

        $node_list = array();
        foreach($person_list as $person) {
            $birthplace = $person->getBirthplace();
            if ($birthplace) {
                $pieRepository = $entityManager->getRepository(PlaceIdExternal::class);
                foreach ($birthplace as $bp) {
                    $bp->setUrlWhg($pieRepository->findUrlWhg($bp->getPlaceId()));
                }
            }

            $node = $personService->personData($format, $person, [$person]);
            $node_list[] = $node;
        }


        $fncResponse = 'createResponse'.$format; # e.g. 'createResponseRdf'
        return $personService->$fncResponse($node_list);
    }


    /**
     * AJAX
     *
     * @Route("/priest-utrecht-suggest/{field}", name="priest_ut_suggest")
     */
    public function autocomplete(Request $request,
                                 ItemRepository $repository,
                                 String $field) {
        $name = $request->query->get('q');
        $fnName = 'suggestPriestUt'.ucfirst($field);
        $suggestions = $repository->$fnName($name, self::HINT_SIZE);

        return $this->render('priest_ut/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

}
