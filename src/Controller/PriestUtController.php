<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemReference;
use App\Entity\Person;
use App\Entity\Authority;
use App\Entity\PlaceIdExternal;
use App\Entity\ReferenceVolume;
use App\Form\PriestUtFormType;
use App\Form\Model\PriestUtFormModel;
use App\Entity\Role;

use App\Service\PersonService;
use App\Service\UtilService;
use App\Service\AutocompleteService;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
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

    private $autocomplete = null;

    public function __construct(AutocompleteService $service) {
        $this->autocomplete = $service;
    }

    /**
     * display query form for priestUts; handle query
     */
    #[Route(path: '/priest_utrecht', name: 'priest_ut_query')]
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

        $personRepository = $entityManager->getRepository(Person::class);

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->render('priest_ut/query.html.twig', [
                    'menuItem' => 'collections',
                    'form' => $form,
            ]);
        } else {
            $id_all = $personRepository->priestUtIds($model);
            $count = count($id_all);
            // $count = $count_result["n"];

            $offset = $request->request->get('offset');
            $page_number = $request->request->get('pageNumber');

            // set offset to page begin
            $offset = UtilService::offset($offset, $page_number, $count, self::PAGE_SIZE);

            $personRepository = $entityManager->getRepository(Person::class);

            $id_list = array_slice($id_all, $offset, self::PAGE_SIZE);
            $person_list = $personRepository->findList($id_list);

            return $this->render('priest_ut/query_result.html.twig', [
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
     */
    #[Route(path: '/priest_utrecht/listenelement', name: 'priest_ut_list_detail')]
    public function priestUtListDetail(Request $request,
                                       EntityManagerInterface $entityManager) {

        $model = new PriestUtFormModel;

        $form = $this->createForm(PriestUtFormType::class, $model);
        $form->handleRequest($request);

        $offset = $request->request->get('offset');
        $model = $form->getData();

        $personRepository = $entityManager->getRepository(Person::class);

        $hassuccessor = false;
        $idx = 0;
        if($offset == 0) {
            $ids = $personRepository->priestUtIds($model,
                                                2,
                                                $offset);
            if(count($ids) == 2) $hassuccessor = true;

        } else {
            $ids = $personRepository->priestUtIds($model,
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
        }

        $birthplace = $person->getBirthplace();
        if ($birthplace) {
            $pieRepository = $entityManager->getRepository(PlaceIdExternal::class);
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
     */
    #[Route(path: '/priest_utrecht/data', name: 'priest_ut_query_data')]
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

        $personRepository = $entityManager->getRepository(Person::class);
        $itemReferenceRepository = $entityManager->getRepository(ItemReference::class);

        $referenceVolumeRepository = $entityManager->getRepository(ReferenceVolume::class);
        $volume_list = $referenceVolumeRepository->findArray();


        $id_all = $personRepository->priestUtIds($model);
        $person_list = $personRepository->findArrayWithRole($id_all);
        PersonService::setVolume($person_list, $volume_list);

        // get all external IDs for places
        $placeIdExternalRepository = $entityManager->getRepository(PlaceIdExternal::class);
        $place_id_ext_list = $placeIdExternalRepository->findMappedArray();

        $node_list = array();
        foreach($person_list as &$person) {
            if (array_key_exists('birthplace', $person)) {
                foreach($person['birthplace'] as &$birthplace) {
                    $bp_id = $birthplace['placeId'];
                    if ($bp_id) {
                        $id_ext = $place_id_ext_list[$bp_id];
                        $birthplace['url'] = str_replace('{id}', $id_ext['value'], $id_ext['format']);
                    } else {
                        $birthplace['url'] = null;
                    }
                }
            }
            $node = $personService->personData($format, $person, [$person]);
            $node_list[] = $node;
        }


        $fncResponse = 'createResponse'.$format; # e.g. 'createResponseRdf'
        return $personService->$fncResponse($node_list, "WIAG-Priests-of-Utrecht");
    }


    /**
     * AJAX
     */
    #[Route(path: '/priest-utrecht-suggest/{field}', name: 'priest_ut_suggest')]
    public function autocomplete(Request $request,
                                 String $field) {
        $name = $request->query->get('q');
        $fnName = 'suggestPriestUt'.ucfirst($field);
        $suggestions = $this->autocomplete->$fnName($name, self::HINT_SIZE);

        return $this->render('priest_ut/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

}
