<?php

namespace App\Service;


use App\Entity\Person;
use App\Repository\PersonRepository;
# use App\Service\RDFService;

use Symfony\Component\HttpFoundation\Response;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PersonService {
    const GND_ID = 1;
    const WIKIPEDIA_ID = 3;

    const URL_GS = "http://personendatenbank.germania-sacra.de/index/gsn/";
    const URL_GND = "http://d-nb.info/gnd/";
    const URL_WIKIDATA = "https://www.wikidata.org/wiki/";
    const URL_VIAF = "https://viaf.org/viaf/";

    const NAMESP_GND = "https://d-nb.info/standards/elementset/gnd#";
    const NAMESP_SCHEMA = "https://schema.org/";

    const CONTENT_TYPE = [
        'json' => 'application/json; charset=UTF-8',
        'jsonld' => 'application/ld+json; charset=UTF-8',
        'csv' => 'text/csv; charset=UTF-8',
    ];

    const BISHOP_FILENAME_CSV = 'WIAGBishops.csv';

    const JSONLDCONTEXT = [
        "@context" => [
            "@version" => 1.1,
            "xsd" => "http://www.w3.org/2001/XMLSchema#",
            "rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
            "foaf" => "http://xmlns.com/foaf/0.1/",
            "gndo" => "https://d-nb.info/standards/elementset/gnd#",
            "schema" => "https://schema.org/",
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

    public function uriId($id) {
        $uriId = $this->router->generate('id', ['id' => $id]);
        # Apache (GWDG server) does not forward https to Symfony
        $uriId = str_replace('http:', 'https:', $uriId);
        return $uriId;
    }

    public function createResponseJson($persons) {
        # see https://symfony.com/doc/current/components/serializer.html#the-jsonencoder
        $serializer = new Serializer([], array(new JSONEncoder()));

        $data = null;
        if (count($persons) == 1) {
            $personNode = $this->personData($persons[0]);
            $data = $serializer->serialize($personNode, 'json');
        } else {
            $personNodes = array();
            foreach($persons as $person) {
                array_push($personNodes, $this->personData($person));
            }
            $data = $serializer->serialize(['persons' => $personNodes], 'json');
        }

        $response = new Response();
        $response->headers->set('Content-Type', self::CONTENT_TYPE['json']);

        $response->setContent($data);
        return $response;
    }

    public function createResponseCsv($persons) {
        # see https://symfony.com/doc/current/components/serializer.html#the-csvencoder
        $csvEncoder = new CsvEncoder();
        $csvOptions = ['csv_delimiter' => "\t"];

        $personData = null;
        if (count($persons) == 1) {
            $personData = $this->personData($persons[0]);
        } else {
            $personData = array();
            foreach($persons as $person) {
                array_push($personData, $this->personData($person));
            }
        }
        $data = $csvEncoder->encode($personData, 'csv', $csvOptions);

        $response = new Response();
        $response->headers->set('Content-Type', self::CONTENT_TYPE['csv']);
        $response->headers->set('Content-Disposition', "filename=".self::BISHOP_FILENAME_CSV);

        $response->setContent($data);
        return $response;
    }

    public function createResponseRdf($persons) {
        $data = $personLinkedData->personsToRdf($persons, $baseurl);
        $response->headers->set('Content-Type', 'application/rdf+xml;charset=UTF-8');
    }

    public function createResponseJsonld($persons) {
        # see https://symfony.com/doc/current/components/serializer.html#the-jsonencoder
        $serializer = new Serializer([], array(new JSONEncoder()));

        $personNodes = self::JSONLDCONTEXT;
        if (count($persons) == 1) {
            array_push($personNodes, $this->personJsonLinkedData($persons[0]));
        } else {
            foreach($persons as $person) {
                array_push($personNodes, $this->personJsonLinkedData($person));
            }
        }
        $data = $serializer->serialize($personNodes, 'json');

        $response = new Response();
        $response->headers->set('Content-Type', self::CONTENT_TYPE['jsonld']);

        $response->setContent($data);
        return $response;

    }


    /**
     * personData
     * TODO
     */
    public function personData($person) {
        $pj = array();
        $pj['wiagId'] = $person->getItem()->getIdPublic();

        $fv = $person->getFamilyname();
        if($fv) $pj['familyName'] = $fv;

        $pj['givenName'] = $person->getGivenname();

        return $pj;

        # TODO
        $fv = $person->getPrefixName();
        if($fv) $pj['prefix'] = $fv;

        $fv = $person->getFamilynameVariant();
        if($fv) $pj['familyNameVariant'] = $fv;

        $fv = $person->getGivennameVariant();
        if($fv) $pj['givenNameVariant'] = $fv;

        $fv = $person->getCommentName();
        if($fv) $pj['commentName'] = $fv;

        $fv = $person->getCommentPerson();
        if($fv) $pj['commentPerson'] = $fv;

        $fv = $person->getDateBirth();
        if($fv) $pj['dateOfBirth'] = $fv;

        $fv = $person->getDateDeath();
        if($fv) $pj['dateOfDeath'] = $fv;

        // $fv = $person->getReligiousOrder();
        // if($fv) $pj['religiousOrder'] = $fv;

        if($person->hasExternalIdentifier() || $person->hasOtherIdentifier()) {
            $pj['identifier'] = array();
            $nd = &$pj['identifier'];
            $fv = $person->getGsid();
            if($fv) $nd['gsId'] = $fv;

            $fv = $person->getGndid();
            if($fv) $nd['gndId'] = $fv;

            $fv = $person->getViafid();
            if($fv) $nd['viafId'] = $fv;

            $fv = $person->getWikidataid();
            if($fv) $nd['wikidataId'] = $fv;

            $fv = $person->getWikipediaurl();
            if($fv) $nd['wikipediaUrl'] = $fv;
        }

        $offices = $person->getOffices();
        if($offices && count($offices) > 0) {
            $pj['offices'] = array();
            $ocJSON = &$pj['offices'];
            foreach($offices as $oc) {
                $ocJSON[] = $oc->toArray();
            }
        }

        $fv = $person->getReference();
        if($fv) {
            $pj['reference'] = $fv->toArray();
            $fiv = $person->getPagesGatz();
            if($fiv)
                $pj['reference']['pages'] = $fiv;
        }

        return $pj;

    }

    public function personLinkedData($person) {
        $pld = array();

        $gfx = "gndo:";
        $owlfx = "owl:";
        $foaffx = "foaf:";
        $scafx = "schema:";



        $pld = [
            'rdf:type' => [
                '@rdf:resource' => self::NAMESP_GND.'DifferentiatedPerson'
                ]
        ];

        $personID = $person->getItem()->getIdPublic();

        $fn = $person->getFamilyname();
        $fndt = RDFService::xmlStringData($fn);

        $gn = $person->getGivenname();
        $gndt = RDFService::xmlStringData($gn);

        $prefixname = $person->getPrefixname();
        $prefixdt = RDFService::xmlStringData($prefixname);

        $aname = array_filter([$gn, $prefixname, $fn],
                              function($v){return $v !== null;});
        $pld[$gfx.'preferredName'] = RDFService::xmlStringData(implode(' ', $aname));

        $pfeftp[$gfx.'forename'] = $gndt;
        if($prefixname)
            $pfeftp[$gfx.'prefix'] = $prefixdt;
        if($fn)
            $pfeftp[$gfx.'surname'] = $fndt;

        $pld[$gfx.'preferredNameEntityForThePerson'] = RDFService::blankNode(array($pfeftp));

        $gnvs = $person->getGivennameVariants();

        $vneftps = array();
        /* one or more variants for the given name */
        if($gnvs) {
            foreach($gnvs as $gnvi) {
                $vneftp = [];
                $vneftp[$gfx.'forename'] = RDFService::xmlStringData(trim($gnvi->getName()));
                if($prefixname)
                    $vneftp[$gfx.'prefix'] = $prefixdt;
                if($fn)
                    $vneftp[$gfx.'surname'] = $fndt;
                $vneftps[] = $vneftp;
            }
        }

        $fnvs = $person->getFamilynameVariants();
        if($fnvs) {
            foreach($fnvs as $fnvi) {
                $vneftp = [];
                $vneftp[$gfx.'forename'] = $gndt;
                if($prefixname)
                    $vneftp[$gfx.'prefix'] = $prefixdt;
                $vneftp[$gfx.'surname'] = RDFService::xmlStringData($fnvi->getName());
                $vneftps[] = $vneftp;
            }
        }

        if($gnvs && $fnvs) {
            foreach($fnvs as $fnvi) {
                foreach($gnvs as $gnvi) {
                    $vneftp = [];
                    $vneftp[$gfx.'forename'] = RDFService::xmlStringData($gnvi->getName());
                    if($prefixname)
                        $vneftp[$gfx.'prefix'] = $prefixdt;
                    $vneftp[$gfx.'surname'] = RDFService::xmlStringData($fnvi->getName());
                    $vneftps[] = $vneftp;
                }
            }
        }

        /* Set 'variantNameEntityForThePerson' as string or array */
        if(count($vneftps) > 0)
            $pld[$gfx.'variantNameEntityForThePerson'] = RDFService::blankNode($vneftps);


        $fv = $person->getNotePerson();
        if($fv)
            $pld[$gfx.'biographicalOrHistoricalInformation'] = RDFService::xmlStringData($fv);

        $fv = $person->getDateBirth();
        if($fv) $pld[$gfx.'dateOfBirth'] = RDFService::xmlStringData($fv);

        $fv = $person->getDateDeath();
        if($fv) $pld[$gfx.'dateOfDeath'] = RDFService::xmlStringData($fv);


        $fv = $person->getItem()->getIdExternalByAuthorityId(self::GND_ID);
        if($fv) $pld[$gfx.'gndIdentifier'] = RDFService::xmlStringData($fv);


        $exids = array();

        foreach ($person->getItem()->getIdsExternal() as $id) {
            $exids[] = [
                '@rdf:resource' => $id->getAuthority()->getUrlFormatter().$id->getValue(),
            ];
        }

        if(count($exids) > 0) {
            $pld[$owlfx.'sameAs'] = count($exids) > 1 ? $exids : $exids[0];
        }

        $fv = $person->getItem()->getIdExternalByAuthorityId(self::WIKIPEDIA_ID);
        if($fv) {
            $pld[$foaffx.'page'] = $fv;
        }

        $descName = [
            '@rdf:about' => $this->uriId($person->getItem()->getIdPublic()),
            '#' => $pld,
        ];


        // TODO 2021-11-03
        // $offices = $person->getOffices();
        // $descOffices = array();
        // if($offices) {
        //     foreach($offices as $oc) {
        //         $roleNodeID = uniqid('role');
        //         $descOffices[] = [
        //             '@rdf:about' => $this->uriId().$personID,
        //             '#' => [
        //                 $scafx.'hasOccupation' => [
        //                     '@rdf:nodeID' => $roleNodeID
        //                 ]
        //             ]
        //         ];
        //         $descOffices[] = $this->roleNode($oc, $roleNodeID, $idpath);
        //     }
        // }

        // return array_merge([$descName], $descOffices);

        return array($descName);

        // references ?!

    }

    public function roleNode($office, $roleNodeID, $idpath) {
        $scafx = "schema:";
        $gfx = "gndo:";

        $ocld['rdf:type'] = [
            '@rdf:resource' => self::NAMESP_SCHEMA.'Role'
        ];

        $ocld[$scafx.'roleName'] = RDFService::xmlStringData($office->getOfficeName());

        $fv = $office->getDateStart();
        if($fv) $ocld[$scafx.'startDate'] = RDFService::xmlStringData($fv);

        $fv = $office->getDateEnd();
        if($fv) $ocld[$scafx.'endDate'] = RDFService::xmlStringData($fv);

        $fv = $office->getDiocese();
        if($fv) {
            $dioceseRepository = $this->entitymanager->getRepository(Diocese::class);
            $dioceseID = $dioceseRepository->getDioceseID($fv);
            if($dioceseID)
                $ocld[$gfx.'affiliation'] = [
                    '@rdf:resource' => $idpath.$dioceseID
                ];
            $ocld[$scafx.'description'] = RDFService::xmlStringData($fv);
        }

        $id_monastery = $office->getIdMonastery();
        if (!is_null($id_monastery) && $id_monastery != "") {
            $fv = $office->getMonastery();
            if ($fv) {
                $ocld[$scafx.'description'] = RDFService::xmlStringData($fv->getMonasteryName());
            }
        }

        $roleNode = [
            '@rdf:nodeID' => $roleNodeID,
            '#' => $ocld,
        ];

        return $roleNode;
    }

    public function personJSONLinkedData($person) {
        $pld = array();

        $gfx = "gndo:";
        $owlfx = "owl:";
        $foaffx = "foaf:";
        $scafx = "schema:";

        // $persondetails = [
        //         "http://wiag-vocab.adw-goe.de/10891" => [
        //             "gndo:preferredName" => "Ignaz Heinrich Wessenberg",
        //             "gndo:preferredNameEntityForThePerson"=> [
        //                 "gndo:forename"=>"Ignaz Heinrich",
        //                 "gndo:prefix"=>"von",
        //                 "gndo:surname"=>"Wessenberg"
        //             ]
        //         ]
        //     ];

        // return $persondetails;

        $personID = [
            "@id" => $person->getItem()->getIdPublic(),
            "@type" => $gfx."DifferentiatedPerson",
        ];

        $fn = $person->getFamilyname();

        $gn = $person->getGivenname();

        $prefixname = $person->getPrefixName();

        $aname = array_filter([$gn, $prefixname, $fn],
                              function($v){return $v !== null;});
        $pld[$gfx.'preferredName'] = implode(' ', $aname);

        $pfeftp[$gfx.'forename'] = $gn;
        if($prefixname)
            $pfeftp[$gfx.'prefix'] = $prefixname;
        if($fn)
            $pfeftp[$gfx.'surname'] = $fn;

        $pld[$gfx.'preferredNameEntityForThePerson'] = $pfeftp;


        $gnvs = $person->getGivennameVariants();

        $vneftps = array();
        /* one or more variants for the given name */
        if($gnvs) {
            foreach($gnvs as $gnvi) {
                $vneftp = [];
                $vneftp[$gfx.'forename'] = $gnvi->getName();
                if($prefixname)
                    $vneftp[$gfx.'prefix'] = $prefixname;
                if($fn)
                    $vneftp[$gfx.'surname'] = $fn;
                $vneftps[] = $vneftp;
            }
        }

        $fnvs = $person->getFamilynameVariants();
        /* one or more variants for the familyname */
        if($fnvs) {
            foreach($fnvs as $fnvi) {
                $vneftp = [];
                $vneftp[$gfx.'forename'] = $gn;
                if($prefixname)
                    $vneftp[$gfx.'prefix'] = $prefixname;
                $vneftp[$gfx.'surname'] = $fnvi->getName();
                $vneftps[] = $vneftp;
            }
        }

        if($gnvs && $fnvs) {
            foreach($fnvs as $fnvi) {
                foreach($gnvs as $gnvi) {
                    $vneftp = [];
                    $vneftp[$gfx.'forename'] = $gnvi->getName();
                    if($prefixname)
                        $vneftp[$gfx.'prefix'] = $prefixname;
                    $vneftp[$gfx.'surname'] = $fnvi->getName();
                    $vneftps[] = $vneftp;
                }
            }
        }

        /* Set 'variantNameEntityForThePerson' */
        if(count($vneftps) > 0)
            $pld[$gfx.'variantNameEntityForThePerson'] = $vneftps;

        $fv = $person->getNotePerson();
        if($fv)
            $pld[$gfx.'biographicalOrHistoricalInformation'] = $fv;

        $fv = $person->getDateBirth();
        if($fv) $pld[$gfx.'dateOfBirth'] = $fv;

        $fv = $person->getDateDeath();
        if($fv) $pld[$gfx.'dateOfDeath'] = $fv;

        // $fv = $person->getReligiousOrder();
        // if($fv) $pld['religiousOrder'] = $fv;

        $fv = $person->getItem()->getIdExternalByAuthorityId(self::GND_ID);
        if($fv) $pld[$gfx.'gndIdentifier'] = $fv;


        $exids = array();

        foreach ($person->getItem()->getIdsExternal() as $id) {
            $exids[] = $id->getAuthority()->getUrlFormatter().$id->getValue();
        }

        if(count($exids) > 0) {
            $pld[$owlfx.'sameAs'] = count($exids) > 1 ? $exids : $exids[0];
        }

        $fv = $person->getItem()->getUriExternalByAuthorityId(self::WIKIPEDIA_ID);
        if($fv) {
            $pld[$foaffx.'page'] = $fv;
        }

        /* offices */
        # TODO 2021-11-03
        // $offices = $person->getOffices();
        // $nodesOffices = array();
        // $pldOffices = array();
        // if($offices && count($offices) > 0) {
        //     foreach($offices as $oc) {
        //         $nodesOffices[] = $this->jsonRoleNode($oc, $this->uriId($person->getItem()->getIdPublic()));
        //     }
        //     $pld[$scafx.'hasOccupation'] = $nodesOffices;
        // }

        return array_merge($personID, $pld);

        # references?

    }

    public function jsonRoleNode($office, $idpath) {
        $scafx = "schema:";
        $gndfx = "gndo:";

        // $ocld['@id'] = $roleNodeID;
        $ocld['@type'] = self::NAMESP_SCHEMA.'Role';

        $ocld[$scafx.'roleName'] = $office->getOfficeName();

        $fv = $office->getDateStart();
        if($fv) $ocld[$scafx.'startDate'] = $fv;

        $fv = $office->getDateEnd();
        if($fv) $ocld[$scafx.'endDate'] = $fv;

        $diocese = $office->getDiocese();
        if($diocese) {
            $ocld[$scafx.'description'] = $diocese;
            $dioceseRepository = $this->entitymanager->getRepository(Diocese::class);
            $dioceseID = $dioceseRepository->getDioceseID($diocese);
            if($dioceseID) $ocld[$gndfx.'affiliation'] = $idpath.$dioceseID;
        }

        $id_monastery = $office->getIdMonastery();
        if (!is_null($id_monastery) && $id_monastery != "") {
            $fv = $office->getMonastery();
            if($fv) {
                $ocld[$scafx.'description'] = $fv->getMonasteryName();
            }
        }
        return $ocld;
    }



};
