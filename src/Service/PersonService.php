<?php

namespace App\Service;


use App\Entity\Person;
# use App\Repository\PersonRepository;
# use App\Service\RDFService;

use Symfony\Component\HttpFoundation\Response;

# use Doctrine\ORM\EntityManagerInterface;
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

    // 2022-04-30 obsolete?
    // const URL_GS = "http://personendatenbank.germania-sacra.de/index/gsn/";
    // const URL_GND = "http://d-nb.info/gnd/";
    // const URL_WIKIDATA = "https://www.wikidata.org/wiki/";
    // const URL_VIAF = "https://viaf.org/viaf/";
    const URL_KLOSTERDATENBANK = 'https://klosterdatenbank.adw-goe.de/gsn/';

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

        $data = $serializer->serialize(['persons' => $node_list], 'json');

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
            $filename = "WIAG-Persons.csv";
        }

        $data = $csvEncoder->encode($node_list, 'csv', $csvOptions);

        $response = new Response();
        $response->headers->set('Content-Type', self::CONTENT_TYPE['csv']);
        $response->headers->set('Content-Disposition', "filename=".$filename);

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

    public function createResponseJsonld_old($persons) {
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
     * personData
     */
    public function personData_old($person) {
        $item = $person->getItem();

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

        // external identifiers
        $nd = array();

        $id_external = $item->getIdExternal();
        foreach ($id_external as $id_loop) {
            $auth = $id_loop->getAuthority();
            $nd[$auth->getUrlNameFormatter()] = $id_loop->getUrl();
        }

        if ($nd) {
            $pj['identifier'] = $nd;
        }

        // roles (offices)
        $roles = $person->getRole();
        $nd = array();
        foreach ($roles as $role) {
             $fv = $this->roleData($role);
             if ($fv) $nd[] = $fv;
        }
        if ($nd) {
            $pj['offices'] = $nd;
        }

        $item_refs = $item->getReference();

        $nd = array();

        foreach ($item_refs as $ref_loop) {
            $rd = array();
            // citation
            $vol = $ref_loop->getReferenceVolume();
            $cce = [$vol->getFullCitation()];
            $ce = $ref_loop->getPagePlain();
            if ($ce) {
                $cce[] = "S. ".$ce;
            }
            $ce = $ref_loop->getIdInReference();
            if ($ce) {
                $cce[] = "ID/Nr. ".$ce;
            }
            $rd['citation'] = implode(', ', $cce);
            // authorOrEditor, RiOpac, shortTitle
            $rd['authorOrEditor'] = $vol->getAuthorEditor();
            $fvi = $vol->getRiOpacId();
            if ($fvi) {
                $nd['RiOpac'] = $fvi;
            }
            $fvi = $vol->getTitleShort();
            if ($fvi) {
                $rd['shortTitle'] = $fvi;
            }
            $nd[] = $rd;
        }

        if ($nd) {
            if (array_key_exists('offices', $pj)) {
                $pj['offices']['references'] = $nd;
            } else {
                $pj['references'] = $nd;
            }
        }


        // extra properties for priests in Utrecht
        // ordination
        $nd = array();
        $itemProp = $item->combineItemProperty();
        if (array_key_exists('ordination_priest', $itemProp)) {
            $nd['office'] = $itemProp['ordination_priest'];
        }
        if (array_key_exists('ordination_priest_date', $itemProp)) {
            $nd['date'] = $itemProp['ordination_priest_date']->format('d.m.Y');
        }
        if ($nd) {
            $pj['ordination'] = $nd;
        }

        // birthplace
        $nd = array();
        foreach ($person->getBirthPlace() as $bp) {
            $bpd['name'] = $bp->getPlaceName();
            $urlwhg = $bp->getUrlWhg();
            if ($urlwhg) {
                $bpd['URL_WordHistoricalGazetteer'] = $urlwhg;
            }
            $nd[] = $bpd;
        }

        if ($nd) {
            $pj['birthplaces'] = $nd;
        }

        return $pj;

    }

    /**
     */
    public function personData($format, $person, $item_list) {
        switch ($format) {
        case 'Json':
        case 'Csv':
            return $this->personDataPlain($person, $item_list);
            break;
        case 'Jsonld':
            return $this->personJSONLinkedData($person, $item_list);
            break;
        case 'Rdf':
        default:
            return null;
        }
    }

    /**
     * personDataPlain
     *
     * build data array for `person` with office data in `item_list`
     */
    public function personDataPlain($person, $item_list) {

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

        // external identifiers
        $nd = array();

        $id_external = $person->getItem()->getIdExternal();
        foreach ($id_external as $id_loop) {
            $auth = $id_loop->getAuthority();
            $nd[$auth->getUrlNameFormatter()] = $id_loop->getUrl();
        }

        if ($nd) {
            $pj['identifier'] = $nd;
        }

        // roles (offices)

        $nd = array();
        foreach ($item_list as $item) {
            $role_list = $item->getPerson()->getRole();
            $ref_list = $item->getReference();
            foreach ($role_list as $role) {
                $fv = $this->roleData($role, $ref_list);
                if ($fv) $nd[] = $fv;
            }
        }
        if ($nd) {
            $pj['offices'] = $nd;
        }

        // extra properties for priests in Utrecht
        // ordination
        $nd = array();
        $itemProp = $item->combineItemProperty();
        if (array_key_exists('ordination_priest', $itemProp)) {
            $nd['office'] = $itemProp['ordination_priest'];
        }
        if (array_key_exists('ordination_priest_date', $itemProp)) {
            $nd['date'] = $itemProp['ordination_priest_date']->format('d.m.Y');
        }
        if ($nd) {
            $pj['ordination'] = $nd;
        }

        // birthplace
        $nd = array();
        foreach ($person->getBirthPlace() as $bp) {
            $bpd['name'] = $bp->getPlaceName();
            $urlwhg = $bp->getUrlWhg();
            if ($urlwhg) {
                $bpd['URL_WordHistoricalGazetteer'] = $urlwhg;
            }
            $nd[] = $bpd;
        }

        if ($nd) {
            $pj['birthplaces'] = $nd;
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
                '@rdf:resource' => RDFService::NAMESP_GND.'DifferentiatedPerson'
                ]
        ];

        $personId = $person->getItem()->getIdPublic();

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

        foreach ($person->getItem()->getIdExternal() as $id) {
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
            '@rdf:about' => $this->uriWiagId($personId),
            '#' => $pld,
        ];

        // offices
        $roles = $person->getRole();
        $descOffices = array();
        if($roles) {
            foreach($roles as $oc) {
                $roleNodeId = uniqid('role');
                $descOffices[] = [
                    '@rdf:about' => $this->uriWiagId($personID),
                    '#' => [
                        $scafx.'hasOccupation' => [
                            '@rdf:nodeID' => $roleNodeId,
                        ]
                    ]
                ];
                $descOffices[] = $this->roleNode($oc, $roleNodeId);
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
                $ocld[$scafx.'affiliation'] = [
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

    public function personJSONLinkedData_old($person) {
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

        $fv = $person->commentLine(false);
        if($fv) {
            $pld[$gfx.'biographicalOrHistoricalInformation'] = $fv;
        }

        $fv = $person->getDateBirth();
        if($fv) $pld[$scafx.'birthDate'] = $fv;

        $fv = $person->getDateDeath();
        if($fv) $pld[$scafx.'deathDate'] = $fv;

        // $fv = $person->getReligiousOrder();
        // if($fv) $pld['religiousOrder'] = $fv;

        $fv = $person->getItem()->getIdExternalByAuthorityId(self::AUTH_ID['GND']);
        if($fv) $pld[$gfx.'gndIdentifier'] = $fv;


        $exids = array();

        foreach ($person->getItem()->getIdExternal() as $id) {
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
        $roles = $person->getRole();
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

    public function personJSONLinkedData($person, $item_list) {
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

        // additional information
        $bhi = array();
        $fv = $person->commentLine(false);
        if($fv) {
            $bhi[] = $fv;
        }

        $fv = $person->getItem()->combineItemProperty();
        if ($fv) {
            $ipt = $this->itemPropertyText($fv, 'ordination_priest');
            if ($ipt) {
                $bhi[] = $ipt;
            }
        }

        if ($bhi) $pld[$gfx.'biographicalOrHistoricalInformation'] = implode('; ', $bhi);

        $fv = $person->getDateBirth();
        if($fv) $pld[$scafx.'birthDate'] = $fv;

        $fv = $person->getDateDeath();
        if($fv) $pld[$scafx.'deathDate'] = $fv;

        // $fv = $person->getReligiousOrder();
        // if($fv) $pld['religiousOrder'] = $fv;

        // birthplace
        $nd = array();
        foreach ($person->getBirthPlace() as $bp) {
            $bpd = array();
            $bpd[$scafx.'name'] = $bp->getPlaceName();
            $urlwhg = $bp->getUrlWhg();
            if ($urlwhg) {
                $bpd[$scafx.'sameAs'] = $urlwhg;
            }
            $nd[] = $bpd;
        }

        if ($nd) {
            $fv = count($nd) > 1 ? $nd : $nd[0];
            $pld[$scafx.'birthPlace'] = $fv;
        }

        $fv = $person->getItem()->getIdExternalByAuthorityId(self::AUTH_ID['GND']);
        if($fv) $pld[$gfx.'gndIdentifier'] = $fv;


        $exids = array();

        foreach ($person->getItem()->getIdExternal() as $id) {
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
        // $roles = $person->getRole();
        // $nodesOffices = array();
        // if($roles) {
        //     foreach ($roles as $oc) {
        //         $nodesOffices[] = $this->jsonRoleNode($oc);
        //     }
        //     $pld[$scafx.'hasOccupation'] = $nodesOffices;
        // }

        // roles (offices)

        $nd = array();
        foreach ($item_list as $item) {
            $role_list = $item->getPerson()->getRole();
            $ref_list = $item->getReference();
            foreach ($role_list as $role) {
                $fv = $this->jsonRoleNode($role, $ref_list);
                if ($fv) $nd[] = $fv;
            }
        }

        if ($nd) {
            $pld[$scafx.'hasOccupation'] = $nd;
        }

        // $jsondata = array_merge(CanonLinkedData::JSONLDCONTEXT, $canonNode);

        return array_merge($personID, $pld);

    }

    public function jsonRoleNode($office, $ref_list) {
        $scafx = "schema:";
        $gndfx = "gndo:";
        $dctermsfx = "dcterms:";

        // $ocld['@id'] = $roleNodeID;
        $ocld['@type'] = RDFService::NAMESP_SCHEMA.'Role';

        $ocld[$scafx.'roleName'] = $office->getRoleName();

        $fv = $office->getDateBegin();
        if($fv) $ocld[$scafx.'startDate'] = $fv;

        $fv = $office->getDateEnd();
        if($fv) $ocld[$scafx.'endDate'] = $fv;

        // monastery or diocese
        $inst_name = null;
        $inst_url = null;
        $fv = $office->getInstitution();
        if ($fv) {
            $inst_name = $fv->getName();
            $inst_url = self::URL_KLOSTERDATENBANK.$fv->getIdGsn();
        } else {
            $fv = $office->getInstitutionName();
            if ($fv) {
                $inst_name = $fv;
            } else {
                $fv = $office->getDiocese();
                if($fv) {
                    $inst_name = $fv->getDioceseStatus().' '.$fv->getName();
                    $inst_url = $this->uriWiagId($fv->getItem()->getIdPublic());
                } else {
                    $fv = $office->getDioceseName();
                    if ($fv) {
                        $inst_name = $fv;
                    }
                }
            }
        }

        $nd = array();
        if ($inst_name) {
            $nd[$scafx.'name'] = $inst_name;
            if ($inst_url) {
                $nd[$scafx.'url'] = $inst_url;
            }
        }

        if ($nd) {
            $ocld[$scafx.'affiliation'] = $nd;
        }

        // references
        $nd = array();
        foreach ($ref_list as $ref) {
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
            $ocld[$dctermsfx.'bibliographicCitation'] = $fv;
        }

        return $ocld;
    }

    public function roleData($role, $ref_list) {
        $pj = array();

        $fv = $role->getRole();
        if ($fv) {
            $pj['title'] = $fv->getName();
        } else {
            $fv = $role->getRoleName();
            if ($fv) $pj['title'] = $fv;
        }

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

        $fv = $role->getInstitution();
        if ($fv) $pj['institution'] = $fv->getName();

        $nd = array();

        foreach ($ref_list as $ref) {
            $rd = array();
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
            $rd['citation'] = implode(', ', $cce);
            // authorOrEditor, RiOpac, shortTitle
            $rd['authorOrEditor'] = $vol->getAuthorEditor();
            $fvi = $vol->getRiOpacId();
            if ($fvi) {
                $rd['RiOpac'] = $fvi;
            }
            $fvi = $vol->getTitleShort();
            if ($fvi) {
                $rd['shortTitle'] = $fvi;
            }
            $nd[] = $rd;
        }

        if ($nd) {
            $pj['references'] = $nd;
        }

        return $pj;

    }

    public function itemPropertyText($properties, string $key): ?string {
        # ordination of pries
        if (!array_key_exists($key, $properties)) {
            return null;
        }
        //dd($properties);

        if ($key == 'ordination_priest') {
            $text = 'Weihe zum '.$properties['ordination_priest'];
            $fv = $properties['ordination_priest_date'];
            if (!is_null($fv)) {
                // $date_value = date('d.m.Y', $fv);
                $text = $text.' am '.$fv->format('d.m.Y');
            }
            if (array_key_exists('ordination_priest_place', $properties)) {
                $text = $text.' in '.$properties['ordination_priest_place'];
            }
            return $text;
        }

        return null;

    }



};
