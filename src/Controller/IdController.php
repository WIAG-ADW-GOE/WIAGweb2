<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Entity\Person;
use App\Entity\Diocese;
use App\Entity\CanonLookup;
use App\Entity\Authority;
use App\Entity\UrlExternal;
use App\Entity\PlaceIdExternal;

use App\Repository\PersonRepository;

use App\Service\PersonService;
use App\Service\ItemService;
use App\Service\DioceseService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class IdController extends AbstractController {
    private $personService;
    private $dioceseService;
    private $itemService;

    public function __construct(PersonService $personService,
                                DioceseService $dioceseService,
                                ItemService $itemService) {
        $this->personService = $personService;
        $this->dioceseService = $dioceseService;
        $this->itemService = $itemService;
    }

    /**
     * find item by public ID; show details or deliver data as JSON, CSV or XML
     *
     * decide which format should be delivered
     *
     * @Route("/id/{id}", name="id")
     */
    public function id(string $id, Request $request) {

        // $format = $request->request->get('format') ?? 'html';
        $format = $request->query->get('format') ?? 'html';

        $dcn = $this->getDoctrine();
        $itemRepository = $dcn->getRepository(Item::class);
        $itemTypeRepository = $dcn->getRepository(ItemType::class);

        $itemResult = $itemRepository->findByIdPublic($id);

        if ($itemResult) {
            $item = $itemResult[0];
            $itemTypeId = $item->getItemTypeId();
            $itemId = $item->getId();

            $itemTypeResult = $itemTypeRepository->find($itemTypeId);

            $typeName = $itemTypeResult->getNameApp();

            # e.g. bishop($itemId, $format)
            return $this->$typeName($itemId, $format);

        } else {
            throw $this->createNotFoundException('Id is nicht gültig: '.$itemId);
        }


     }

    public function bishop($id, $format) {

        $personRepository = $this->getDoctrine()->getRepository(Person::class);

        $person = $personRepository->find($id);
        // collect office data in an array of Items
        $item_list = $this->itemService->getBishopOfficeData($person);


        if ($format == 'html') {
            $person->setSibling($this->itemService->getSibling($person));

            return $this->render('bishop/person.html.twig', [
                'person' => $person,
                'item' => $item_list,
            ]);
        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }

            // build data array
            $node_list = array();
            $node_list[] = $this->personService->personData($format, $person, $item_list);

            return $this->personService->createResponse($format, $node_list);
        }
    }

    public function diocese($id, $format) {
        $repository = $this->getDoctrine()
                           ->getRepository(Diocese::class);

        $diocese = $repository->dioceseWithBishopricSeatById($id);

        if ($format == 'html') {
            return $this->render('diocese/diocese.html.twig', [
                'diocese' => $diocese,
            ]);
        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }

            $node_list = array();
            $node_list[] = $this->dioceseService->dioceseData($format, $diocese);

            return $this->dioceseService->createResponse($format, $node_list);
        }

    }

    public function canon_gs($id, $format) {
        return $this->canon($id, $format);
    }

    public function canon($id, $format) {

        $personRepository = $this->getDoctrine()->getRepository(Person::class);
        $person = $personRepository->find($id);
        // collect external URLs
        $urlExternalRepository = $this->getDoctrine()->getRepository(UrlExternal::class);
        $urlByType = $urlExternalRepository->groupByType($id);
        $person->setUrlByType($urlByType);

        // collect office data in an array of Items
        $item_list = $this->itemService->getCanonOfficeData($person);


        if ($format == 'html') {

            return $this->render('canon/person.html.twig', [
                'person' => $person,
                'item' => $item_list,
            ]);


        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }

            // build data array
            $node_list = array();
            $node_list[] = $this->personService->personData($format, $person, $item_list);

            return $this->personService->createResponse($format, $node_list);
        }
    }



    public function priest_ut($id, $format) {
        $personRepository = $this->getDoctrine()
                           ->getRepository(Person::class);

        $person = $personRepository->findWithOffice($id);
        $personRepository->addReferenceVolumes($person);
        $birthplace = $person->getBirthplace();
        if ($birthplace) {
            $pieRepository = $this->getDoctrine()
                                  ->getRepository(PlaceIdExternal::class);
            foreach ($birthplace as $bp) {
                $bp->setUrlWhg($pieRepository->findUrlWhg($bp->getPlaceId()));
            }
        }

        if ($format == 'html') {

            return $this->render('priest_ut/person.html.twig', [
                'person' => $person,
            ]);
        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }

            // build data array
            $node_list = array();
            $item_list = [$person->getItem()];
            $node_list[] = $this->personService->personData($format, $person, $item_list);

            return $this->personService->createResponse($format, $node_list);

        }

    }

}
