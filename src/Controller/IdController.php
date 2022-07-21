<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Entity\ItemReference;
use App\Entity\Person;
use App\Entity\Diocese;
use App\Entity\CanonLookup;
use App\Entity\Authority;
use App\Entity\UrlExternal;
use App\Entity\PlaceIdExternal;

use App\Repository\PersonRepository;

use App\Service\PersonService;
use App\Service\DioceseService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Doctrine\ORM\EntityManagerInterface;


class IdController extends AbstractController {
    private $personService;
    private $dioceseService;
    private $entityManager;

    public function __construct(PersonService $personService,
                                DioceseService $dioceseService,
                                EntityManagerInterface $entityManager) {
        $this->personService = $personService;
        $this->dioceseService = $dioceseService;
        $this->entityManager = $entityManager;
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

        $itemRepository = $this->entityManager->getRepository(Item::class);
        $itemTypeRepository = $this->entityManager->getRepository(ItemType::class);

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

        $itemRepository = $this->entityManager->getRepository(Item::class);
        $personRepository = $this->entityManager->getRepository(Person::class);

        $person = $personRepository->find($id);
        // collect office data in an array of Items
        $personRole = $itemRepository->getBishopOfficeData($person);


        if ($format == 'html') {

            $itemRepository->setSibling($person);

            return $this->render('bishop/person.html.twig', [
                'person' => $person,
                'item' => $item_list,
            ]);
        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }

            // build data array
            $node_list = [$this->personService->personData($format, $person, $personRole)];

            return $this->personService->createResponse($format, $node_list);
        }
    }

    public function diocese($id, $format) {
        $repository = $this->entityManager->getRepository(Diocese::class);

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

    /**
     *
     */
    public function canon($id, $format) {

        $canonLookupRepository = $this->entityManager->getRepository(CanonLookup::class);
        $canon_list = $canonLookupRepository->findList([$id], null);
        // dd($ids, $person_id, $canon_list);

        // extract Person object to be compatible with bishops
        $personName = $canon_list[0]->getPersonName();
        $personRole = array_map(function($el) {
            return $el->getPerson();
        }, $canon_list);

        $itemReferenceRepository = $this->entityManager->getRepository(ItemReference::class);
        $itemReferenceRepository->setReferenceVolume($personRole);

        $urlExternalRepository = $this->entityManager->getRepository(UrlExternal::class);
        $urlByType = $urlExternalRepository->groupByType($personName->getId());
        $personName->setUrlByType($urlByType);


        if ($format == 'html') {

            return $this->render('canon/person.html.twig', [
                'personName' => $personName,
                'personRole' => $personRole,
            ]);


        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }

            // build data array
            $node_list = [$this->personService->personData($format, $personName, $personRole)];

            return $this->personService->createResponse($format, $node_list);
        }
    }

    public function priest_ut($id, $format) {
        $personRepository = $this->entityManager->getRepository(Person::class);

        $person = $personRepository->findWithOffice($id);
        $this->entityManager->getRepository(ItemReference::class)->setReferenceVolume([$person]);
        $birthplace = $person->getBirthplace();
        if ($birthplace) {
            $pieRepository = $this->entityManager->getRepository(PlaceIdExternal::class);
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
            $node_list = [$this->personService->personData($format, $person, [$person])];

            return $this->personService->createResponse($format, $node_list);

        }

    }

}
