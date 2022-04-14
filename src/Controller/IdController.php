<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Entity\Person;
use App\Entity\Diocese;
use App\Entity\CanonLookup;
use App\Entity\Authority;

use App\Service\PersonService;
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

    public function __construct(PersonService $personService, DioceseService $dioceseService) {
        $this->personService = $personService;
        $this->dioceseService = $dioceseService;
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

        $person = $personRepository->findWithAssociations($id);

        if ($format == 'html') {

            // get data from Germania Sacra
            $authorityGs = Authority::ID['Germania Sacra'];
            $gsn = $person->getIdExternal($authorityGs);
            $personGs = array();
            if (!is_null($gsn)) {
                $itemTypeBishopGs = Item::ITEM_TYPE_ID['Bischof GS'];
                $bishopGs = $personRepository->findByIdExternal($itemTypeBishopGs, $gsn, $authorityGs);
                $personGs = array_merge($personGs, $bishopGs);

                $itemTypeCanonGs = Item::ITEM_TYPE_ID['Domherr GS'];
                $canonGs = $personRepository->findByIdExternal($itemTypeCanonGs, $gsn, $authorityGs);
                $personGs = array_merge($personGs, $canonGs);
            }

            // get data from Domherrendatenbank
            $authorityWIAG = Authority::ID['WIAG-ID'];
            $wiagid = $person->getItem()->getIdPublic();
            $canon = array();
            if (!is_null($wiagid)) {
                $itemTypeCanon = Item::ITEM_TYPE_ID['Domherr'];
                $canon = $personRepository->findByIdExternal($itemTypeCanon, $wiagid, $authorityWIAG);
            }
            return $this->render('bishop/person.html.twig', [
                'person' => $person,
                'persongs' => $personGs,
                'canon' => $canon,
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

        if ($format == 'html') {
            $canonLookupRepository = $this->getDoctrine()
                                          ->getRepository(CanonLookup::class);

            $canonLookup = $canonLookupRepository->findWithPerson($id);

            return $this->render('canon/person.html.twig', [
                'canonlookup' => $canonLookup,
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
