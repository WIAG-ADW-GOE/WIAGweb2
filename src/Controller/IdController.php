<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemCorpus;
use App\Entity\ItemNameRole;
use App\Entity\ItemReference;
use App\Entity\Person;
use App\Entity\Diocese;
use App\Entity\CanonLookup;
use App\Entity\Authority;
use App\Entity\UrlExternal;
use App\Entity\PlaceIdExternal;

use App\Repository\PersonRepository;

use App\Service\UtilService;
use App\Service\PersonService;
use App\Service\DioceseService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\HeaderUtils;

use Doctrine\ORM\EntityManagerInterface;


class IdController extends AbstractController {
    private $personService;
    private $utilService;
    private $dioceseService;
    private $entityManager;

    public function __construct(PersonService $personService,
                                UtilService $utilService,
                                DioceseService $dioceseService,
                                EntityManagerInterface $entityManager) {
        $this->personService = $personService;
        $this->utilService = $utilService;
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
        $itemCorpusRepository = $this->entityManager->getRepository(ItemCorpus::class);

        $itemResult = $itemRepository->findByIdPublicOrParent($id);
        if (!is_null($itemResult) and count($itemResult) > 0) {
            $item = $itemResult[0];
            $item_id = $item->getId();
            $corpus = $itemCorpusRepository->findCorpusPrio($item_id);
            $corpus_id = $corpus->getCorpusId();

            if ($corpus_id == 'dioc') {
                return $this->diocese($item_id, $format);
            } elseif ($corpus_id == 'utp') {
                return $this->priestOfUtrecht($item_id, $format);
            } else {
                return $this->person($item_id, $corpus, $format);
            }

        } else {
            return $this->render('home\message.html.twig', [
                'message' => 'Kein Eintrag für ID '.$id.' vorhanden.'
            ]);
        }


     }

    /**
     * @return HTML or data for ID $id
     */
    public function person($id, $corpus, $format) {

        $itemRepository = $this->entityManager->getRepository(Item::class);

        // collect office data in an array of Items
        $item_list = $itemRepository->findItemNameRole([$id]);
        $item = array_values($item_list)[0];
        $person = $item->getPerson();
        $person_role_list = $item->getPersonRole();

        if ($format == 'html') {
            return $this->render('person/person.html.twig', [
                'personName' => $person,
                'personRole' => $person_role_list,
                'roleSortCritList' => ['dateSortKey', 'id'],
                'corpus' => $corpus->getCorpusId(),
                'pageTitle' => $corpus->getPageTitle(),
            ]);
        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }

            // build data array
            $node_list = [$this->personService->personData($format, $person, $person_role_list)];

            return $this->personService->createResponse($format, $node_list);
        }
    }

    public function diocese($id, $format) {
        $repository = $this->entityManager->getRepository(Diocese::class);

        $result = $repository->dioceseWithBishopricSeat($id);
        if (!is_null($result) && count($result) > 0) {
            $diocese = $result[0];
        } else {
            throw $this->createNotFoundException('Bistum nicht gefunden');
            $diocese = null;
        }

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
        $canon_lookup_list = $canonLookupRepository->findByPersonIdRole($id);
        $id_name = $canon_lookup_list[0]->getPersonIdName();
        $canon_list = $canonLookupRepository->findList([$id_name], null);

        // extract Person object to be compatible with bishops
        $canon_list = UtilService::sortByFieldList($canon_list, ['prioRole']);
        $personName = $canon_list[0]->getPersonName();
        $personRole = array_map(function($el) {
            return $el->getPerson();
        }, $canon_list);

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

    public function priestOfUtrecht($id, $format) {
        $personRepository = $this->entityManager->getRepository(Person::class);

        // old version 2022-10-07
        // $person = $personRepository->findWithOffice($id);
        // $this->entityManager->getRepository(ItemReference::class)->setReferenceVolume([$person]);

        $person_list = $personRepository->findList([$id]);
        if (!is_null($person_list) && count($person_list) > 0) {
            $person = $person_list[0];
        } else {
            throw $this->createNotFoundException('Priester nicht gefunden');
            $person = null;
        }


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

    /**
     * return list of GND numbers for all bishops
     *
     * @Route("/beacon.txt", name="beacon")
     */
    public function beacon (Request $request) {
        $response = new Response();
        $data = "line1\nline2";
        $mimeType = 'text/plain';
        $filename = "beacon.txt";
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Type', $mimeType.'; charset=UTF-8');
        // deliver beacon data directly
        // $response->headers->set('Content-Disposition', $disposition);
        // $baseurl = $request->getSchemeAndHttpHost();
        $baseurl = 'https://'.$request->getHttpHost();
        $cbeaconheader = [
            "#FORMAT: BEACON",
            "#VERSION: 0.1",
            "#PREFIX: http://d-nb.info/gnd/",
            "#TARGET: ".$baseurl."/gnd/{ID}",
            "#FEED: ".$baseurl."/beacon.txt",
            "#NAME: Wissensaggregator Mittelalter und Frühe Neuzeit",
            "#DESCRIPTION: ",
            "#INSTITUTION: Germania Sacra, Akademie der Wissenschaften zu Goettingen",
            "#CONTACT: bkroege@gwdg.de",
            "#TIMESTAMP: ".date(DATE_ATOM),
        ];

        $gnd_id = Authority::ID['GND'];

        $urlExternalRepository = $this->entityManager->getRepository(UrlExternal::class);
        $gnd_list = $urlExternalRepository->findValues($gnd_id);


        $cdata = array_merge($cbeaconheader, array_column($gnd_list, 'value'));

        $data = implode($cdata, "\n")."\n";

        $debug_flag = false;
        if ($debug_flag) {
            return $this->render('home/beacon.html.twig', [
                'data' => $cdata,
            ]);
        }

        $response->setContent($data);
        return $response;
    }

    /**
     * respond to beacon requests; find item by GND_ID; show details
     *
     * @Route("/gnd/{id}", name="gnd_id")
     */
    public function detailsByGndId(string $id, Request $request) {

        $urlExternalRepository = $this->entityManager->getRepository(urlExternal::class);
        $gnd_id = Authority::ID['GND'];

        $urlext = $urlExternalRepository->findBy(['value' => $id, 'authorityId' => $gnd_id]);

        if (is_null($urlext) || count($urlext) < 1) {
            throw $this->createNotFoundException('GND-ID wurde nicht gefunden');
        }

        $id = $urlext[0]->getItemId();

        $itemRepository = $this->entityManager->getRepository(Item::class);

        $item = $itemRepository->find($id);

        if (is_null($item)) {
            throw $this->createNotFoundException('GND-ID wurde nicht gefunden');
        }

        return $this->id($item->getIdPublic(), $request);

    }



}
