<?php

namespace App\Service;


use App\Entity\Diocese;
use App\Repository\DioceseRepository;
use App\Service\RDFService;

use Symfony\Component\HttpFoundation\Response;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DioceseService {
    const AUTH_ID = [
        'GND' => 1,
        'GS' => 200,
        'VIAF' => 4,
        'Wikidata' => 2,
        'Wikipedia' => 3,
    ];

    const URL_GS = "";
    const URL_GND = "http://d-nb.info/gnd/";
    const URL_WIKIDATA = "https://www.wikidata.org/wiki/";
    const URL_VIAF = "https://viaf.org/viaf/";

    const CONTENT_TYPE = [
        'json' => 'application/json; charset=UTF-8',
        'jsonld' => 'application/ld+json; charset=UTF-8',
        'csv' => 'text/csv; charset=UTF-8',
        'rdf' => 'application/rdf+xml;charset=UTF-8',
    ];

    const DIOCESE_FILENAME_CSV = 'WIAGDioceses.csv';

    const JSONLDCONTEXT = [
        "@context" => [
            "@version" => 1.1,
            "xsd" => "http://www.w3.org/2001/XMLSchema#",
            "rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
            "foaf" => "http://xmlns.com/foaf/0.1/",
            "gndo" => "https://d-nb.info/standards/elementset/gnd#",
            "schema" => "https://schema.org/",
            "dcterms" => "http://purl.org/dc/terms/", # Dublin Core
            "variantNamesByLang" => [
                "@id" => "https://d-nb.info/standards/elementset/gnd#variantName",
                "@container" => "@language",
            ],
        ],
    ];

    private $router;

    public function __construct(UrlGeneratorInterface $router) {
        $this->router = $router;
    }

    public function uriWiagId($id) {
        $uriId = $this->router->generate('id', ['id' => $id], $this->router::ABSOLUTE_URL);
        # Apache (GWDG server) does not forward https to Symfony
        $uriId = str_replace('http:', 'https:', $uriId);
        return $uriId;
    }

    public function createResponse($format, $node_list) {
        $fcn = 'createResponse'.$format;
        return $this->$fcn($node_list);
    }

    public function createResponseJson($node_list) {
        # see https://symfony.com/doc/current/components/serializer.html#the-jsonencoder
        $serializer = new Serializer([], array(new JSONEncoder()));

        $data = $serializer->serialize(['dioceses' => $node_list], 'json');

        $response = new Response();
        $response->headers->set('Content-Type', self::CONTENT_TYPE['json']);

        $response->setContent($data);
        return $response;

    }

    public function createResponseCsv($node_list) {
        # see https://symfony.com/doc/current/components/serializer.html#the-csvencoder
        $csvEncoder = new CsvEncoder();
        $csvOptions = ['csv_delimiter' => "\t"];

        if(count($node_list) == 1) {
            $filename = $node_list[0]['wiagId'].'.csv';
        } else {
            $filename = "WIAG-Bistuemer.csv";
        }

        $data = $csvEncoder->encode($node_list, 'csv', $csvOptions);

        $response = new Response();
        $response->headers->set('Content-Type', self::CONTENT_TYPE['csv']);
        $response->headers->set('Content-Disposition', "filename=".$filename);

        $response->setContent($data);
        return $response;
    }

    public function createResponseRdf($dioceses) {
        # see https://symfony.com/doc/current/components/serializer.html#the-xmlencoder
        $serializer = new Serializer([], array(new XMLEncoder()));

        # handle a single diocese
        if (is_a($dioceses, Diocese::class)) {
            $dioceses = array($dioceses);
        }


        $dioceseNodes = array();
        if (count($dioceses) == 1) {
            $dioceseNodes = $this->dioceseLinkedData($dioceses[0]);
        } else {
            foreach($dioceses as $diocese) {
                array_push($dioceseNodes, $this->dioceseLinkedData($diocese));
            }
        }
        $xmlroot = RDFService::xmlroot($dioceseNodes);
        $data = $serializer->serialize($xmlroot, 'xml', RDFService::XML_CONTEXT);

        $response = new Response();
        $response->headers->set('Content-Type', self::CONTENT_TYPE['rdf']);

        $response->setContent($data);
        return $response;
    }

    public function createResponseJsonld($node_list) {
        # see https://symfony.com/doc/current/components/serializer.html#the-jsonencoder
        $serializer = new Serializer([], array(new JSONEncoder()));

        $node_list = array_merge(self::JSONLDCONTEXT, ['@set' => $node_list]);
        $data = $serializer->serialize($node_list, 'json');

        $response = new Response();
        $response->headers->set('Content-Type', self::CONTENT_TYPE['jsonld']);

        $response->setContent($data);
        return $response;

    }

    /**
     */
    public function dioceseData($format, $diocese) {
        switch ($format) {
        case 'Json':
        case 'Csv':
            return $this->dioceseDataPlain($diocese);
            break;
        case 'Jsonld':
            return $this->dioceseJSONLinkedData($diocese);
            break;
        case 'Rdf':
        default:
            return null;
        }
    }


    /**
     * dioceseDataPlain
     */
    public function dioceseDataPlain($diocese) {
        $cd = array();
        $idPublic = $diocese->getItem()->getIdPublic();
        $cd['wiagId'] = $idPublic;

        $cd['URI'] = $this->uriWiagId($idPublic);

        $fv = $diocese->getName();
        if($fv) $cd['name'] = $fv;

        $fv = $diocese->getDioceseStatus();
        if($fv) $cd['status'] = $fv;

        $fv = $diocese->getDateOfFounding();
        if($fv) $cd['dateOfFounding'] = $fv;

        $fv = $diocese->getDateOfDissolution();
        if($fv) {
            $cd['dateOfDissolution']
                = $fv == 'keine' ? 'none' : $fv;
        }

        $fv = $diocese->getAltLabels();
        if($fv) {
            $clabel = array();
            foreach($fv as $label) {
                $clabel[] = [
                    'altLabel' => $label->getLabel(),
                    'lang' => $label->getLang(),
                ];
            }
            $cd['altLabels'] = $clabel;
        }

        $fv = $diocese->getNote();
        if($fv) $cd['note'] = $fv;

        $fv = $diocese->getEcclesiasticalProvince();
        if($fv) $cd['ecclesiasticalProvince'] = $fv;

        $fv = $diocese->getBishopricSeat();
        if($fv) $cd['bishopricSeat'] = $fv->getName();

        $fv = $diocese->getNoteBishopricSeat();
        if($fv) $cd['noteBishopricSeat'] = $fv;

        // external identifiers
        $item = $diocese->getItem();
        $nd = array();

        foreach (self::AUTH_ID as $key => $auth) {
            if ($key == 'Wikipedia') {
                $fv = $item->getUriExternalByAuthorityId($auth);
            } else {
                $fv = $item->getIdExternalByAuthorityId($auth);
            }
            if ($fv) $nd[$key] = $fv;
        }

        if ($nd) {
            $cd['identifier'] = $nd;
        }

        $fv = $diocese->getNoteAuthorityFile();
        if($fv) $cd['identifiersComment'] = $fv;

        // references
        $nd = array();
        $references = $item->getReference();

        foreach ($references as $reference) {
            $volData = array();
            $volume = $reference->getReferenceVolume();
            $fv = $volume->getFullCitation();
            if ($fv) $volData['citation'] = $fv;

            $fv = $volume->getTitleShort();
            if ($fv) $volData['shortTitle'] = $fv;

            $fv = $volume->getAuthorEditor();
            if ($fv) $volData['authorOrEditor'] = $fv;

            $fv = $volume->getRiOpacId();
            if ($fv) $volData['RiOpac'] = $fv;

            $fv = $volume->getOnlineResource();
            if ($fv) $volData['online'] = $fv;

            $fv = $reference->getPagePlain();
            if ($fv) $volData['page'] = $fv;

            if ($volData) $nd[] = $volData;
        }

        if ($nd) $cd['references'] = $nd;

        return $cd;
    }

    public function dioceseLinkedData($diocese): array {
        $dld = array();

        $gfx = "gndo:";
        $owlfx = "owl:";
        $foaffx = "foaf:";
        $scafx = "schema:";

        $dld = [
            'rdf:type' => [
                '@rdf:resource' => RDFService::NAMESP_GND.'ReligiousAdministrativeUnit'
                ]
        ];

        $dioceseId = $diocese->getItem()->getIdPublic();


        $dioceseName = $diocese->getName();
        $dioceseStatus = $diocese->getDioceseStatus();

        $dld[$gfx.'preferredName'] = $dioceseStatus.' '.$dioceseName;

        $fv = $diocese->getDateOfFounding();
        if($fv) $dld[$gfx.'dateOfEstablishment'] = $fv;

        $fv = $diocese->getDateOfDissolution();
        if($fv) $dld[$gfx.'dateOfTermination'] = $fv;

        $fv = $diocese->getAltLabels();
        if($fv) {
            $clabel = array();
            foreach($fv as $label) {
                $clabel[] = RDFService::rdfLangStringData($label->getLabel(), $label->getLang());
            }
            $dld[$gfx.'variantName'] = $clabel;
        }

        $note = $diocese->getNote();
        $noteSeat = $diocese->getNoteBishopricSeat();
        if($note) {
            $noteout = $note;
            if($noteSeat) $noteout = $noteout.' '.$noteSeat;
            $dld[$scafx.'description'] = $note;
        }
        elseif($noteSeat) {
            $dld[$scafx.'description'] = $noteSeat;
        }

        // rdf types?!
        // ecclesiastical province
        // bishopric seat

        // external identifiers
        $item = $diocese->getItem();

        $fv = $item->getIdsExternal();
        if($fv) {
            $cei = array();
            foreach($fv as $extid) {

                $baseUrl = $extid->getAuthority()->getUrlFormatter();
                $extUrl = $baseUrl.$extid->getValue();

                $authId = $extid->getAuthorityId();
                if($authId == self::AUTH_ID['Wikipedia']) {
                    $dld[$foaffx.'page'] = $extUrl;
                }
                else {
                    $cei[] = $extUrl;
                }

            }
            $dld[$owlfx.'sameAs'] = $cei;
        }

        // rdf type?!
        // comment authority

        $descName = [
            '@rdf:about' => $this->uriWiagId($dioceseId),
            '#' => $dld,
        ];

        return $descName;

    }

    public function dioceseJSONLinkedData($diocese) {
        $dld = array();

        $gfx = "gndo:";
        $owlfx = "owl:";
        $foaffx = "foaf:";
        $scafx = "schema:";
        $dctermsfx = "dcterms:";

        $dioceseId = [
            "@id" => $this->uriWiagId($diocese->getItem()->getIdPublic()),
            "@type" => $gfx."ReligiousAdministrativeUnit",
        ];


        $dioceseName = $diocese->getName();
        $dioceseStatus = $diocese->getDioceseStatus();

        $dld[$gfx.'preferredName'] = $dioceseStatus.' '.$dioceseName;

        $fv = $diocese->getDateOfFounding();
        if($fv) $dld[$gfx.'dateOfEstablishment'] = $fv;

        $fv = $diocese->getDateOfDissolution();
        if($fv) $dld[$gfx.'dateOfTermination'] = $fv;

        $fv = $diocese->getAltlabels();
        if($fv) {
            $clabel = array();
            foreach($fv as $label) {
                $lang = $label->getLang();
                $clabel[$lang][] = $label->getLabel();
            }
            $dld['variantNamesByLang'] = $clabel;
        }

        $note = $diocese->getNote();
        $noteSeat = $diocese->getNoteBishopricSeat();
        if($note) {
            $noteout = $note;
            if($noteSeat) $noteout = $noteout.' '.$noteSeat;
            $dld[$scafx.'description'] = $note;
        }
        elseif($noteSeat) {
            $dld[$scafx.'description'] = $noteSeat;
        }

        // external identifiers
        $item = $diocese->getItem();

        $fv = $item->getIdExternal();
        if($fv) {
            $cei = array();
            foreach($fv as $extid) {

                $baseUrl = $extid->getAuthority()->getUrlFormatter();
                $extUrl = $baseUrl.$extid->getValue();

                $authId = $extid->getAuthorityId();
                if($authId == self::AUTH_ID['Wikipedia']) {
                    $dld[$foaffx.'page'] = $extUrl;
                }
                else {
                    $cei[] = $extUrl;
                }

            }
            $dld[$owlfx.'sameAs'] = $cei;
        }

        // references
        $nd = array();
        foreach ($item->getReference() as $ref) {
            // citation
            $vol = $ref->getReferenceVolume();
            $cce = [$vol->getFullCitation()];
            $ce = $ref->getPagePlain();
            if ($ce) {
                $cce[] = "S. ".$ce;
            }
            $ce = $ref->getIdInReference();
            if ($ce) {
                $cce[] = "ID/Nr. ".$ce;
            }
            $nd[] = implode(', ', $cce);
        }

        if ($nd) {
            $fv = count($nd) > 1 ? $nd : $nd[0];
            $dld[$dctermsfx.'bibliographicCitation'] = $fv;
        }


        return array_merge($dioceseId, $dld);
    }




};
