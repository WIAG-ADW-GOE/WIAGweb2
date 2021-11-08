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
    const AUTH_ID = [
        'GND' => 1,
        'GS' => 200,
        'VIAF' => 4,
        'Wikidata' => 2,
        'Wikipedia' => 3,
    ];

    const URL_GS = "http://personendatenbank.germania-sacra.de/index/gsn/";
    const URL_GND = "http://d-nb.info/gnd/";
    const URL_WIKIDATA = "https://www.wikidata.org/wiki/";
    const URL_VIAF = "https://viaf.org/viaf/";

    const CONTENT_TYPE = [
        'json' => 'application/json; charset=UTF-8',
        'jsonld' => 'application/ld+json; charset=UTF-8',
        'csv' => 'text/csv; charset=UTF-8',
        'rdf' => 'application/rdf+xml;charset=UTF-8',
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

    public function uriWiagId($id) {
        $uriId = $this->router->generate('id', ['id' => $id], $this->router::ABSOLUTE_URL);
        # Apache (GWDG server) does not forward https to Symfony
        $uriId = str_replace('http:', 'https:', $uriId);
        return $uriId;
    }

    public function createResponseJson($persons) {
        # see https://symfony.com/doc/current/components/serializer.html#the-jsonencoder
        $serializer = new Serializer([], array(new JSONEncoder()));

        # handle a single person
        if (is_a($persons, Person::class)) {
            $persons = array($persons);
        }

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

        # handle a single person
        if (is_a($persons, Person::class)) {
            $persons = array($persons);
        }

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
        # see https://symfony.com/doc/current/components/serializer.html#the-xmlencoder
        $serializer = new Serializer([], array(new XMLEncoder()));

        # handle a single person
        if (is_a($persons, Person::class)) {
            $persons = array($persons);
        }


        $personNodes = array();
        if (count($persons) == 1) {
            $personNodes = $this->personLinkedData($persons[0]);
        } else {
            foreach($persons as $person) {
                array_push($personNodes, ...$this->personLinkedData($person));
            }
        }
        $xmlroot = RDFService::xmlroot($personNodes);
        $data = $serializer->serialize($xmlroot, 'xml', RDFService::XML_CONTEXT);
        # dd($data);

        $response = new Response();
        $response->headers->set('Content-Type', self::CONTENT_TYPE['rdf']);

        $response->setContent($data);
        return $response;
    }

    public function createResponseJsonld($persons) {
        # see https://symfony.com/doc/current/components/serializer.html#the-jsonencoder
        $serializer = new Serializer([], array(new JSONEncoder()));

        # handle a single person
        if (is_a($persons, Person::class)) {
            $persons = array($persons);
        }

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

        $fv = $person->getPrefixName();
        if($fv) $pj['prefix'] = $fv;

        $fv = $person->getFamilynameVariants();
        $fnvs = array();
        foreach ($fv as $fvi) {
            $fnvs[] = $fvi->getName();
        }

        if($fnvs) $pj['familyNameVariant'] = implode(', ', $fnvs);

        $fv = $person->getGivennameVariants();

        $fnvs = array();
        foreach ($fv as $fvi) {
            $fnvs[] = $fvi->getName();
        }

        if($fnvs) $pj['givenNameVariant'] = implode(', ', $fnvs);

        $fv = $person->getNoteName();
        if($fv) $pj['noteName'] = $fv;

        $fv = $person->getNotePerson();
        if($fv) $pj['notePerson'] = $fv;

        $fv = $person->getDateBirth();
        if($fv) $pj['dateOfBirth'] = $fv;

        $fv = $person->getDateDeath();
        if($fv) $pj['dateOfDeath'] = $fv;

        $fv = $person->getReligiousOrder();
        if($fv) $pj['religiousOrder'] = $fv->getAbbreviation();

        $item = $person->getItem();
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
            $pj['identifier'] = $nd;
        }

        $roles = $person->getRoles();
        $nd = array();
        foreach ($roles as $role) {
             $fv = $this->roleData($role);
             if ($fv) $nd[] = $fv;
        }
        if ($nd) {
            $pj['offices'] = $nd;
        }


        // $fv = $person->getReference();
        // if($fv) {
        //     $pj['reference'] = $fv->toArray();
        //     $fiv = $person->getPagesGatz();
        //     if($fiv)
        //         $pj['reference']['pages'] = $fiv;
        // }

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
                '@rdf:resource' => RDFService::NAMESP_GND.'DifferentiatedPerson'
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


        $fv = $person->getItem()->getIdExternalByAuthorityId(self::AUTH_ID['GND']);
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

        $fv = $person->getItem()->getUriExternalByAuthorityId(self::AUTH_ID['Wikipedia']);
        if($fv) {
            $pld[$foaffx.'page'] = $fv;
        }

        $descName = [
            '@rdf:about' => $this->uriWiagId($person->getItem()->getIdPublic()),
            '#' => $pld,
        ];

        // offices
        $roles = $person->getRoles();
        $descOffices = array();
        if($roles) {
            foreach($roles as $oc) {
                $roleNodeID = uniqid('role');
                $descOffices[] = [
                    '@rdf:about' => $this->uriWiagId($personID),
                    '#' => [
                        $scafx.'hasOccupation' => [
                            '@rdf:nodeID' => $roleNodeID
                        ]
                    ]
                ];
                $descOffices[] = $this->roleNode($oc, $roleNodeID);
            }
        }

        return array_merge([$descName], $descOffices);


        // references ?!

    }

    public function roleNode($office, $roleNodeID) {
        $scafx = "schema:";
        $gfx = "gndo:";

        $ocld['rdf:type'] = [
            '@rdf:resource' => RDFService::NAMESP_SCHEMA.'Role'
        ];

        $ocld[$scafx.'roleName'] = RDFService::xmlStringData($office->getRoleName());

        $fv = $office->getDateBegin();
        if($fv) $ocld[$scafx.'startDate'] = RDFService::xmlStringData($fv);

        $fv = $office->getDateEnd();
        if($fv) $ocld[$scafx.'endDate'] = RDFService::xmlStringData($fv);

        $fv = $office->getDiocese();
        if($fv) {
            $dioceseID = $fv->getItem()->getIdPublic();
            if($dioceseID)
                $ocld[$gfx.'affiliation'] = [
                    '@rdf:resource' => $this->uriWiagId($dioceseID)
                ];
            $ocld[$scafx.'description'] = RDFService::xmlStringData($fv->getName());
        } else {
            $fv = $office->getDioceseName();
            if ($fv) $ocld[$scafx.'description'] = $fv;
        }

        # 2021-11-08 We have no data about monasteries in the tbl_bischofaemter_gatz?!
        // $id_monastery = $office->getIdMonastery();
        // if (!is_null($id_monastery) && $id_monastery != "") {
        //     $fv = $office->getMonastery();
        //     if ($fv) {
        //         $ocld[$scafx.'description'] = RDFService::xmlStringData($fv->getMonasteryName());
        //     }
        // }

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

        $fv = $person->getItem()->getIdExternalByAuthorityId(self::AUTH_ID['GND']);
        if($fv) $pld[$gfx.'gndIdentifier'] = $fv;


        $exids = array();

        foreach ($person->getItem()->getIdsExternal() as $id) {
            $exids[] = $id->getAuthority()->getUrlFormatter().$id->getValue();
        }

        if(count($exids) > 0) {
            $pld[$owlfx.'sameAs'] = count($exids) > 1 ? $exids : $exids[0];
        }

        $fv = $person->getItem()->getUriExternalByAuthorityId(self::AUTH_ID['Wikipedia']);
        if($fv) {
            $pld[$foaffx.'page'] = $fv;
        }


        /* offices */
        $roles = $person->getRoles();
        $nodesOffices = array();
        if($roles) {
            foreach ($roles as $oc) {
                $nodesOffices[] = $this->jsonRoleNode($oc);
            }
            $pld[$scafx.'hasOccupation'] = $nodesOffices;
        }

        return array_merge($personID, $pld);

        # references?

    }

    public function jsonRoleNode($office) {
        $scafx = "schema:";
        $gndfx = "gndo:";

        // $ocld['@id'] = $roleNodeID;
        $ocld['@type'] = RDFService::NAMESP_SCHEMA.'Role';

        $ocld[$scafx.'roleName'] = $office->getRoleName();

        $fv = $office->getDateBegin();
        if($fv) $ocld[$scafx.'startDate'] = $fv;

        $fv = $office->getDateEnd();
        if($fv) $ocld[$scafx.'endDate'] = $fv;

        $diocese = $office->getDiocese();
        if($diocese) {
            $ocld[$scafx.'description'] = $diocese->getDioceseStatus().' '.$diocese->getName();
            $ocld[$gndfx.'affiliation'] = $this->uriWiagId($diocese->getItem()->getIdPublic());
        } else {
            $fv = $office->getDioceseName();
            if ($fv) $ocld[$scafx.'description'] = $fv;
        }

        # TODO 2021-11-08
        # at the moment the ACCESS source contains no data about monasteries
        // $id_monastery = $office->getIdMonastery();
        // if (!is_null($id_monastery) && $id_monastery != "") {
        //     $fv = $office->getMonastery();
        //     if($fv) {
        //         $ocld[$scafx.'description'] = $fv->getMonasteryName();
        //     }
        // }
        return $ocld;
    }

    public function roleData($role) {
        $pj = array();

        $fv = $role->getRoleName();
        if ($fv) $pj['title'] = $fv;

        $fv = null;
        $diocese = $role->getDiocese();
        if ($diocese) {
            $fv = $diocese->getDioceseStatus().' '.$diocese->getName();
        } else {
            $fv = $role->getDioceseName();
        }
        if ($fv) $pj['diocese'] = $fv;

        $fv = $role->getDateBegin();
        if ($fv) $pj['dateBegin'] = $fv;

        $fv = $role->getDateEnd();
        if ($fv) $pj['dateEnd'] = $fv;

        return $pj;

    }



};
