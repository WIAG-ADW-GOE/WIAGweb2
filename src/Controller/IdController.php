<?php
namespace App\Controller;

use App\Entity\Corpus;
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
use App\Entity\ReferenceVolume;

use App\Form\Model\PersonFormModel;

use App\Repository\PersonRepository;

use App\Service\UtilService;
use App\Service\PersonService;
use App\Service\DioceseService;
use App\Service\DownloadService;

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
     */
    #[Route(path: '/id/{id}', name: 'id')]
    public function id(string $id, Request $request) {
        $itemCorpusRepository = $this->entityManager->getRepository(ItemCorpus::class);

        $format = $request->query->get('format') ?? 'html';
        $format = ucfirst(strtolower($format));

        $ic = $itemCorpusRepository->findBy(['idPublic' => $id]);

        if (is_null($ic) or count($ic) < 1) {
            return $this->render('home\message.html.twig', [
                'message' => 'Kein Eintrag für ID '.$id.' vorhanden.'
            ]);
        }

        $id_list = Utilservice::collectionColumn($ic, 'itemId');

        return $this->renderPublic($id_list, $format);

    }

    /**
     * respond to beacon requests; find item (any corpus) by GND_ID; show details
     */
    #[Route(path: '/gnd/{id}', name: 'gnd_id')]
    public function detailsByGnd(string $id,
                                 Request $request) {

        $urlExternalRepository = $this->entityManager->getRepository(UrlExternal::class);

        $format = $request->query->get('format') ?? 'html';

        $only_online = true;
        $corpus_id_list = [];
        $list_size = 200;
        $id_list = $urlExternalRepository->findIdBySomeNormUrl(
            $id,
            $corpus_id_list,
            $list_size,
            $only_online
        );

        if (count($id_list) < 1) {
            return $this->render('home\message.html.twig', [
                'message' => 'Kein Eintrag für ID '.$id.' vorhanden.'
            ]);
        }

        return $this->renderPublic($id_list, $format);
    }

    /**
     * Returns HTML or data for ID $id
     */
    public function person($id, $format) {

        $corpusRepository = $this->entityManager->getRepository(Corpus::class);
        $itemRepository = $this->entityManager->getRepository(Item::class);
        $personRepository = $this->entityManager->getRepository(Person::class);
        $itemNameRoleRepository = $this->entityManager->getRepository(ItemNameRole::class);
        $referenceVolumeRepository = $this->entityManager->getRepository(ReferenceVolume::class);


        // collect office data in an array of Items
        $item_list = $itemRepository->findItemNameRole([$id]);
        if (is_null($item_list) or count($item_list) < 0) {
            return $this->render('home\message.html.twig', [
                'message' => 'Keine Person mit gültiger ID '.$id.' gefunden.'
            ]);
        }
        $item = array_values($item_list)[0];
        $person = $item->getPerson();
        $corpus_list = $corpusRepository->findByCorpusId($item->getCorpusId());
        $corpus = array_values($corpus_list)[0];
        $person_role_list = $item->getPersonRole();

        $format = ucfirst(strtolower($format));

        if ($format == 'Html') {
            return $this->render('person/person.html.twig', [
                'personName' => $person,
                'personRole' => $person_role_list,
                'roleSortCritList' => ['dateSortKey', 'id'],
                'corpus' => $corpus->getCorpusId(),
                'pageTitle' => $corpus->getPageTitle(),
            ]);
        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                return $this->render('home\message.html.twig', [
                    'message' => 'Unbekanntes Format: '.$format
                ]);
            }
            // compare PersonController
            $volume_list = $referenceVolumeRepository->findArray();
            $id_chunk = array($id);
            $person_chunk = $personRepository->findArray($id_chunk);
            // list of persons with role data
            $person_role_chunk = $itemNameRoleRepository->findPersonRoleArray($id_chunk);
            PersonService::setVolume($person_role_chunk, $volume_list);

            $node_list = array();
            // fill $node_list
            foreach($person_chunk as $person) {
                $inr = $person['item']['itemNameRole'];
                $item_id_role_list = array_column($inr, 'itemIdRole');
                $person_role_list = UtilService::findAllArray($person_role_chunk, 'id', $item_id_role_list);
                $node_list[] = $this->personService->personData($format, $person, $person_role_list);
            }

            $wiag_id = DownloadService::idPublic($person['item']['itemCorpus']);
            return $this->personService->createResponse($format, $node_list, $wiag_id);
        }
    }

    public function diocese($id, $format) {
        $repository = $this->entityManager->getRepository(Diocese::class);

        $result = $repository->dioceseWithBishopricSeat($id);
        if (is_null($result) or count($result) < 1 or !$result[0]->getItem()->getIsOnline()) {
            return $this->render('home\message.html.twig', [
                'message' => 'Bistum nicht gefunden.'
            ]);
        }

        $diocese = $result[0];

        $format = ucfirst(strtolower($format));

        if ($format == 'Html') {
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

    public function priestOfUtrecht($id, $format) {
        $personRepository = $this->entityManager->getRepository(Person::class);
        $referenceVolumeRepository = $this->entityManager->getRepository(referenceVolume::class);

        $person_list = $personRepository->findList([$id]);

        if (is_null($person_list)
            or count($person_list) < 1
            or !$person_list[0]->getItem()->getIsOnline()) {
            return $this->render('home\message.html.twig', [
                'message' => 'Priester nicht gefunden.'
            ]);
        }
        $person = $person_list[0];

        $format = ucfirst(strtolower($format));

        $birthplace = $person->getBirthplace();
        if ($birthplace) {
            $pieRepository = $this->entityManager->getRepository(PlaceIdExternal::class);
            foreach ($birthplace as $bp) {
                $bp->setUrlWhg($pieRepository->findUrlWhg($bp->getPlaceId()));
            }
        }

        if ($format == 'Html') {

            return $this->render('priest_ut/person.html.twig', [
                'person' => $person,
            ]);
        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }

            // build data array
            $person_list = $personRepository->findArrayWithRole([$id]);

            // set reference volumes
            $volume_list = $referenceVolumeRepository->findArray();
            PersonService::setVolume($person_list, $volume_list);

            // get all external IDs for places
            $placeIdExternalRepository = $this->entityManager->getRepository(PlaceIdExternal::class);
            $place_id_ext_list = $placeIdExternalRepository->findMappedArray();

            if (!is_null($person_list) && count($person_list) > 0) {
                $person = $person_list[0];
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
            } else {
                throw $this->createNotFoundException('Priester nicht gefunden');
            }

            $node_list = [$this->personService->personData($format, $person, [$person])];

            $wiag_id = DownloadService::idPublic($person['item']['itemCorpus']);

            return $this->personService->createResponse($format, $node_list, $wiag_id);
        }

    }

    /**
     * return list of GND numbers for all bishops
     */
    #[Route(path: '/beacon.txt', name: 'beacon')]
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

        $data = implode( "\n", $cdata)."\n";

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
     * map elements of $id_list to IDs of accessible items and return a HTTP response
     */
    private function renderPublic($id_list, $format) {
        $itemRepository = $this->entityManager->getRepository(Item::class);
        $itemCorpusRepository = $this->entityManager->getRepository(ItemCorpus::class);
        $itemNameRoleRepository = $this->entityManager->getRepository(ItemNameRole::class);

        $ic_list = $itemCorpusRepository->findByItemId($id_list);
        $id_list_person = array();
        $id_list_other = array();

        foreach ($ic_list as $ic) {
            $item_id = $ic->getItemId();
            $corpus_id = $ic->getCorpusId();
            if ($corpus_id == 'dioc') {
                return $this->diocese($item_id, $format);
            } elseif ($corpus_id == 'utp') {
                return $this->priestOfUtrecht($item_id, $format);
            } else {
                $id_list_person[] = $ic->getItemId();
            }
        }

        // map personIDs to children
        $item_list = $itemRepository->findBy(['id' => $id_list_person]);
        $id_child_list = array();

        foreach ($item_list as $item) {
            $child = $itemRepository->findCurrentChild($item);
            if ($child) {
                $id_child_list[] = $child->getId();
            }
        }

        if (count($id_child_list) < 1) {
            return $this->render('home\message.html.twig', [
                'message' => 'Keine Person mit passender gültiger ID gefunden.'
            ]);
        }

        $inr_list = $itemNameRoleRepository->findByItemIdRole($id_child_list);
        if (is_null($inr_list) or count($inr_list) < 1) {
            return $this->render('home\message.html.twig', [
                'message' => 'Keine Person mit passender gültiger ID gefunden.'
            ]);
        }

        $id = $inr_list[0]->getItemIdName();

        return $this->person($id, $format);

    }


}
