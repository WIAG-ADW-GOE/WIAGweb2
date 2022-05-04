<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Entity\Person;
use App\Entity\Diocese;
use App\Entity\CanonLookup;
use App\Entity\Authority;
use App\Entity\UrlExternal;

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

        $format = $request->request->get('format') ?? 'html';

        $itemRepository = $this->getDoctrine()
                               ->getRepository(Item::class);

        $itemTypeRepository = $this->getDoctrine()
                                   ->getRepository(ItemType::class);

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
        $personRepository = $this->getDoctrine()
                                 ->getRepository(Person::class);

        $personRepository = $this->getDoctrine()->getRepository(Person::class);
        $person = $personRepository->find($id);


        if ($format == 'html') {

            $item = $this->itemService->getBishopOfficeData($person);

            return $this->render('bishop/person.html.twig', [
                'person' => $person,
                'item' => $item,
            ]);
        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }
            $fncResponse='createResponse'.$format; # e.g. 'createResponseRdf'
            return $this->personService->$fncResponse([$person]);
        }
    }

    public function diocese($id, $format) {
        $repository = $this->getDoctrine()
                           ->getRepository(Diocese::class);

        $result = $repository->dioceseWithBishopricSeatById($id);

        if ($format == 'html') {
            return $this->render('diocese/diocese.html.twig', [
                'diocese' => $result,
            ]);
        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }
            $fncResponse='createResponse'.$format; # e.g. 'createResponseRdf'
            return $this->dioceseService->$fncResponse([$result]);
        }


        switch($format) {
        case 'html':
            return $this->render('diocese/diocese.html.twig', [
                'diocese' => $result,
            ]);
        }

    }

    public function canon_gs($id, $format) {
        return $this->canon($id, $format);
    }

    public function canon($id, $format) {
        $dcn = $this->getDoctrine();
        $personRepository = $dcn->getRepository(Person::class);

        if ($format == 'html') {

            $person = $personRepository->find($id);

            // collect external URLs
            $urlExternalRepository = $dcn->getRepository(UrlExternal::class);
            $urlByType = $urlExternalRepository->groupByType($id);
            $person->setUrlByType($urlByType);

            // collect office data in an array of Items
            $item = $this->itemService->getCanonOfficeData($person);

            return $this->render('canon/person.html.twig', [
                'person' => $person,
                'item' => $item,
            ]);

        } else {
            $personRepository = $this->getDoctrine()
                                     ->getRepository(Person::class);

            // TODO 2022-03-24 find person and roles with prio = 1
            $person = $personRepository->findWithAssociations($id);

            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }
            $fncResponse='createResponse'.$format; # e.g. 'createResponseRdf'
            return $this->personService->$fncResponse([$person]);
        }
    }

    public function priest_ut($id, $format) {
        $personRepository = $this->getDoctrine()
                           ->getRepository(Person::class);

        $person = $personRepository->findWithAssociations($id);

        if ($format == 'html') {

            return $this->render('priest_ut/person.html.twig', [
                'person' => $person,
            ]);
        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }
            $fncResponse='createResponse'.$format; # e.g. 'createResponseRdf'
            return $this->personService->$fncResponse([$person]);
        }

        ## see PriestUtController

    }




}
