<?php

namespace App\Service;

use App\Entity\Item;
use App\Entity\ItemProperty;
use App\Entity\ItemPropertyType;
use App\Entity\IdExternal;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\PersonRoleProperty;
use App\Entity\RolePropertyType;
use App\Entity\ItemReference;
use App\Entity\ReferenceVolume;
use App\Entity\Role;
use App\Entity\Diocese;
use App\Entity\Institution;
use App\Entity\Authority;
use App\Entity\GivennameVariant;
use App\Entity\FamilynameVariant;
use App\Entity\InputError;

use App\Service\UtilService;

use Symfony\Component\HttpFoundation\Response;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PersonService {
    // see table `authority
    const AUTH_ID = [
        'GND' => 1,
        'GS' => 200,
        'VIAF' => 4,
        'Wikidata' => 2,
        'Wikipedia' => 3,
    ];

    const EDIT_STATUS_DEFAULT = [
        'Bischof' => 'angelegt',
    ];

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
    private $entityManager;
    private $utilService;

    public function __construct(UrlGeneratorInterface $router,
                                EntityManagerInterface $entityManager,
                                UtilService $utilService) {
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->utilService = $utilService;
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

    public function createResponseRdf($node_list) {
        # see https://symfony.com/doc/current/components/serializer.html#the-xmlencoder
        $serializer = new Serializer([], array(new XMLEncoder()));

        // remove top node
        $xmlroot = RDFService::xmlroot(array_merge(...$node_list));

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
     * @return node list with structured data for a person with office data in `personRole`
     */
    public function personData($format, $person, $personRole) {
        switch ($format) {
        case 'Json':
        case 'Csv':
            return $this->personDataPlain($person, $personRole);
            break;
        case 'Jsonld':
            return $this->personJSONLinkedData($person, $personRole);
            break;
        case 'Rdf':
            return $this->personLinkedData($person, $personRole);
            break;
        default:
            return null;
        }
    }

    /**
     * personDataPlain
     *
     * build data array for `person` with office data in `personRole`
     */
    public function personDataPlain($person, $personRole) {

        $pj = array();
        $pj['wiagId'] = $person->getItem()->getIdPublic();

        $fv = $person->getFamilyname();
        if ($fv) $pj['familyName'] = $fv;

        $pj['givenName'] = $person->getGivenname();

        $fv = $person->getPrefixName();
        if ($fv) $pj['prefix'] = $fv;

        $fv = $person->getFamilynameVariants();
        $fnvs = array();
        foreach ($fv as $fvi) {
            $fnvs[] = $fvi->getName();
        }

        if ($fnvs) $pj['familyNameVariant'] = implode(', ', $fnvs);

        $fv = $person->getGivennameVariants();

        $fnvs = array();
        foreach ($fv as $fvi) {
            $fnvs[] = $fvi->getName();
        }

        if ($fnvs) $pj['givenNameVariant'] = implode(', ', $fnvs);

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
        foreach ($personRole as $person_loop) {
            $role_list = $person_loop->getRole();
            $ref_list = $person_loop->getItem()->getReference();
            foreach ($role_list as $role) {
                $fv = $this->roleData($role, $ref_list);
                if ($fv) $nd[] = $fv;
            }
        }

        if ($nd) {
            # references are part of the role node
            $pj['offices'] = $nd;
        } else {
            $ref_list = $person->getItem()->getReference();
            if ($ref_list) {
                $pj['references'] = $this->referenceData($ref_list);
            }
        }

        // extra properties for priests in Utrecht
        // ordination
        $nd = array();
        $itemProp = $person->getItem()->combineItemProperty();
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

    public function personLinkedData($person, $personRole) {
        $pld = array();

        $gfx = "gndo:";
        $owlfx = "owl:";
        $foaffx = "foaf:";
        $scafx = "schema:";
        $dctermsfx = "dcterms:";


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

        // 'variantNameEntityForThePerson' as string or array
        if(count($vneftps) > 0) {
            $pld[$gfx.'variantNameEntityForThePerson'] = RDFService::blankNode($vneftps);
        }

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

        if ($bhi) {
            $bhi_string = RDFService::xmlStringData(implode('; ', $bhi));
            $pld[$gfx.'biographicalOrHistoricalInformation'] = $bhi_string;
        }

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

        $fv = $person->getItem()->getUriExtByAuthId(self::AUTH_ID['Wikipedia']);
        if($fv) {
            $pld[$foaffx.'page'] = $fv;
        }

        // birthplace
        $nd = array();
        foreach ($person->getBirthPlace() as $bp) {
            $urlwhg = $bp->getUrlWhg();
            if ($urlwhg) {
                $nd[] = ['@rdf:resource' => $urlwhg];
            } else {
                $nd[] = RDFService::xmlStringData($bp->getPlaceName());
            }
        }

        if ($nd) {
            if (count($nd) == 1) {
                $pld[$scafx.'birthPlace'] = $nd[0];
            } else {
                $pld[$scafx.'birthPlace'] = RDFService::list("rdf:Bag", $nd);
            }
        }

        $descName = [
            '@rdf:about' => $this->uriWiagId($personId),
            '#' => $pld,
        ];


        // offices
        $descOffices = array();
        foreach ($personRole as $person_loop) {
            $role_list = $person_loop->getRole();
            $ref_list = $person_loop->getItem()->getReference();

            foreach($role_list as $oc) {
                $roleNodeId = uniqid('role');
                $descOffices[] = [
                    '@rdf:about' => $this->uriWiagId($personId),
                    '#' => [
                        $scafx.'hasOccupation' => [
                            '@rdf:nodeID' => $roleNodeId,
                        ]
                    ]
                ];
                $descOffices[] = $this->roleNode($oc, $roleNodeId, $ref_list);
            }
        }

        // add reference(s) in case there are no offices
        if (count($descOffices) == 0) {
            $ref_list = $person->getItem()->getReference();
            if ($ref_list) {
                // references
                $nd = $this->referenceCitation($ref_list);

                if ($nd) {
                    $nd = array_map([RDFService::class, 'xmlStringData'], $nd);
                    if (count($nd) == 1) {
                        $descName[$dctermsfx.'bibliographicCitation'] = $nd[0];
                    } else {
                        $descName[$dctermsfx.'bibliographicCitation'] = RDFService::list("rdf:Bag", $nd);
                    }
                }
            }
        }

        return array_merge([$descName], $descOffices);

    }

    public function roleNode($office, $roleNodeID, $ref_list) {
        $scafx = "schema:";
        $gfx = "gndo:";
        $dctermsfx = "dcterms:";

        $ocld['rdf:type'] = [
            '@rdf:resource' => RDFService::NAMESP_SCHEMA.'Role'
        ];

        $ocld[$scafx.'roleName'] = RDFService::xmlStringData($office->getRoleName());

        $fv = $office->getDateBegin();
        if($fv) $ocld[$scafx.'startDate'] = RDFService::xmlStringData($fv);

        $fv = $office->getDateEnd();
        if($fv) $ocld[$scafx.'endDate'] = RDFService::xmlStringData($fv);

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

        if($inst_url) {
            $ocld[$scafx.'affiliation'] = ['@rdf:resource' => $inst_url];
        } else {
            $nd = array(
                [$scafx.'name' => RDFService::xmlStringData($inst_name)]
            );
            $ocld[$scafx.'affiliation'] = RDFService::blankNode($nd);
        }

        // references
        $nd = $this->referenceCitation($ref_list);

        if ($nd) {
            $nd = array_map([RDFService::class, 'xmlStringData'], $nd);
            if (count($nd) == 1) {
                $ocld[$dctermsfx.'bibliographicCitation'] = $nd[0];
            } else {
                $ocld[$dctermsfx.'bibliographicCitation'] = RDFService::list("rdf:Bag", $nd);
            }
        }

        $roleNode = [
            '@rdf:nodeID' => $roleNodeID,
            '#' => $ocld,
        ];

        return $roleNode;
    }

    /**
     * @return string for bibliographic citation
     */
    public function referenceCitation($ref_list) {
        $nd = array();
        foreach ($ref_list as $ref) {
            // citation
            $vol = $ref->getReferenceVolume();
            if ($vol) {
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
        }
        return $nd;
    }


    public function personJSONLinkedData($person, $personRole) {
        $pld = array();

        $gfx = "gndo:";
        $owlfx = "owl:";
        $foaffx = "foaf:";
        $scafx = "schema:";
        $dctermsfx = "dcterms:";

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
        // one or more variants for the given name
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
        // one or more variants for the familyname
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

        // set 'variantNameEntityForThePerson'
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

        $fv = $person->getItem()->getUriExtByAuthId(self::AUTH_ID['Wikipedia']);
        if($fv) {
            $pld[$foaffx.'page'] = $fv;
        }

        // roles (offices)

        $nd = array();
        foreach ($personRole as $person_loop) {
            $role_list = $person_loop->getRole();
            $ref_list = $person_loop->getItem()->getReference();
            foreach ($role_list as $role) {
                $fv = $this->jsonRoleNode($role, $ref_list);
                if ($fv) $nd[] = $fv;
            }
        }

        if ($nd) {
            $pld[$scafx.'hasOccupation'] = $nd;
        } else { # add reference(s) in case there are no offices
            $ref_list = $person->getItem()->getReference();
            if ($ref_list) {
                // references
                $nd = $this->referenceCitation($ref_list);
                if ($nd) {
                    $fv = count($nd) > 1 ? $nd : $nd[0];
                    $pld[$dctermsfx.'bibliographicCitation'] = $fv;
                }
            }
        }

        return array_merge($personID, $pld);

    }

    public function jsonRoleNode($office, $ref_list) {
        $scafx = "schema:";
        $gndfx = "gndo:";
        $dctermsfx = "dcterms:";

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
        $nd = $this->referenceCitation($ref_list);

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

        $reference_nodes = $this->referenceData($ref_list);
        if ($reference_nodes) {
            $pj['references'] = $reference_nodes;
        }

        return $pj;

    }

    /**
     * collect reference data
     * @return list of reference nodes
     */
    public function referenceData($ref_list) {
        $nd = array();

        foreach ($ref_list as $ref) {
            $rd = array();
            $cce = array();
            // citation
            $vol = $ref->getReferenceVolume();

            if ($vol) {
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
            }
            $nd[] = $rd;
        }

        return $nd;
    }

    /**
     * @return a text version for a set of elements in `properties`
     */
    public function itemPropertyText($properties, string $key): ?string {

        if (array_key_exists('ordination_priest', $properties)) {
            $text = 'Weihe zum '.$properties[$key];
            $fv = $properties[$key.'_date'];
            if (!is_null($fv)) {
                // $date_value = date('d.m.Y', $fv);
                $text = $text.' am '.$fv->format('d.m.Y');
            }
            if (array_key_exists($key.'_place', $properties)) {
                $text = $text.' in '.$properties[$key.'_place'];
            }
            return $text;
        }

        return null;

    }

    /**
     * map content of $data to $obj_list
     */
    public function mapPerson($person, $data, $user_id) {
        // item
        $item = $person->getItem();

        $edit_status = trim($data['item']['editStatus']);
        $new_flag = ($data['item']['id'] == "");
        $this->updateEditMetaData($item, $edit_status, $user_id, $new_flag);

        // item: deleted TODO 2022-09-22 turned off for the moment
        // $deleted_status = $data['item']['isDeleted'];
        // $item->setIsDeleted($deleted_status);

        // if ($deleted_status == 1) {
        //     $this->setByKeys($person, $data, ['comment']);
        //     // other elements are not accessible
        //     return $person;
        // }


        // item: checkboxes
        $key_list = ['formIsEdited'];
        foreach($key_list as $key) {
            $set_fnc = 'set'.ucfirst($key);
            $item->$set_fnc(isset($data['item'][$key]));
        }

        // idInSource
        if (array_key_exists('idInSource', $data['item'])) {
            $id_in_source = $data['item']['idInSource'];
            # TODO 2022-12-13 use parameter instead of 'Bischof'
            $id_public = $this->makeIdPublic('Bischof', $id_in_source);

            $item->setIdInSource($id_in_source);
            $item->setIdPublic($id_public);

        }

        // item: status values, editorial notes
        $key_list = ['commentDuplicate'];
        $this->utilService->setByKeys($item, $data['item'], $key_list);

        // person

        //- merge parents
        if (array_key_exists('mergeParentId', $data['item'])) {
            $itemRepository = $this->entityManager->getRepository(Item::class);
            $parent_list = $itemRepository->findBy(['id' => $data['item']['mergeParentId']]);
            $item->setMergeParent($parent_list);
            $item->setMergeStatus('child');
        }

        $key_list = ['givenname',
                     'prefixname',
                     'familyname',
                     'dateBirth',
                     'dateDeath',
                     'comment',
                     'noteName',
                     'notePerson'];
        $this->utilService->setByKeys($person, $data, $key_list);

        $this->validateSubstring($person, $key_list, Item::JOIN_DELIM);

        if (is_null($person->getGivenname())) {
            $msg = "Das Feld 'Vorname' kann nicht leer sein.";
            $person->getInputError()->add(new InputError('name', $msg));
        }

        // name variants
        $this->mapNameVariants($person, $data);

        // numerical values for dates
        $date_birth = $person->getDateBirth();
        if (!is_null($date_birth)) {
            $year = $this->utilService->parseDate($date_birth, 'lower');
            if (!is_null($year)) {
                $person->setNumDateBirth($year);
            } else {
                $msg = "Keine gültige Datumsangabe in '".$date_birth."' gefunden.";
                $person->getInputError()->add(new InputError('name', $msg));
            }
        } else {
            $person->setNumDateBirth(null);
        }

        $date_death = $person->getDateDeath();
        if (!is_null($date_death)) {
            $year = $this->utilService->parseDate($date_death, 'upper');
            if (!is_null($year)) {
                $person->setNumDateDeath($year);
            } else {
                $msg = "Keine gültige Datumsangabe in '".$date_death."' gefunden.";
                $person->getInputError()->add(new InputError('name', $msg));
            }
        } else {
            $person->setNumDateDeath(null);
        }

        // roles, reference, free properties
        $section_map = [
            'role' => 'mapRole',
            'ref'  => 'mapReference',
            'prop' => 'mapItemProperty',
        ];

        foreach($section_map as $key => $mapFunction) {
            if (array_key_exists($key, $data)) {
                foreach($data[$key] as $data_loop) {
                    $this->$mapFunction($person, $data_loop);
                }
            }
        }

        // external IDs
        $this->mapIdExternal($person, $data['idext']);

        // date min/date max
        $this->updateDateRange($person);

        // validation
        if ($item->getIsOnline() && $item->getIsDeleted()) {
            $msg = "Der Eintrag kann nicht gleichzeitig online und gelöscht sein.";
            $person->getInputError()->add(new InputError('status', $msg));
        }
        if (is_null($item->getEditStatus())) {
            $msg = "Das Feld 'Status' darf nicht leer sein.";
            $person->getInputError()->add(new InputError('status', $msg));
        }

        return $person;
    }

    private function mapNameVariants($person, $data) {
        // givenname
        $gnv_data = trim($data['givennameVariants']);
        $person->setFormGivennameVariants($gnv_data);
        $person_id = $person->getId();

        // - remove entries
        $person_gnv = $person->getGivennameVariants();
        foreach ($person_gnv as $gnv_remove) {
            $person_gnv->removeElement($gnv_remove);
            $gnv_remove->setPerson(null);
            $this->entityManager->remove($gnv_remove);
        }

        // - set new entries
        // -- ';' is an alternative separator
        $gnv_data = str_replace(';', ',', $gnv_data);
        $gnv_list = explode(',', $gnv_data);
        foreach ($gnv_list as $gnv) {
            if (trim($gnv) != "") {
                $gnv_new = new GivenNameVariant();
                $person_gnv->add($gnv_new);
                $gnv_new->setName(trim($gnv));
                $gnv_new->setLang('de');
                if ($person_id > 0) {
                    $gnv_new->setPerson($person);
                    $this->entityManager->persist($gnv_new);
                }
            }
        }

        // familyname
        $fnv_data = trim($data['familynameVariants']);
        $person->setFormFamilynameVariants($fnv_data);

        // - remove entries
        $person_fnv = $person->getFamilynameVariants();
        foreach ($person_fnv as $fnv_remove) {
            $person_fnv->removeElement($fnv_remove);
            $fnv_remove->setPerson(null);
            $this->entityManager->remove($fnv_remove);
        }

        // - set new entries
        $fnv_list = explode(',', $fnv_data);
        foreach ($fnv_list as $fnv) {
            if (trim($fnv) != "") {
                $fnv_new = new FamilyNameVariant();
                $person_fnv->add($fnv_new);
                $fnv_new->setName(trim($fnv));
                $fnv_new->setLang('de');
                if ($person_id > 0) {
                    $fnv_new->setPerson($person);
                    $this->entityManager->persist($fnv_new);
                }
            }
        }

    }

    private function mapRole($person, $data) {
        $roleRepository = $this->entityManager->getRepository(PersonRole::class);
        $roleRoleRepository = $this->entityManager->getRepository(Role::class);
        $dioceseRepository = $this->entityManager->getRepository(Diocese::class);
        $institutionRepository = $this->entityManager->getRepository(Institution::class);

        $id = $data['id'];

        // $key_list = ['role', 'institution', 'date_begin', 'date_end'];
        $key_list = ['role', 'institution'];
        $no_data = $this->utilService->no_data($data, $key_list);

        $role = null;

        // new role
        if ($data['id'] == 0) {
            if ($no_data || $data['delete'] == "delete") {
                return null;
            } else {
                $role = new PersonRole();
                $person->getRole()->add($role);
                if ($person->getId() > 0) {
                    $role->setPerson($person);
                    $this->entityManager->persist($role);
                }
            }
        } else {
            $role = $roleRepository->find($id);
        }

        // delete?
        if (!is_null($role) && $data['delete'] == "delete" || $no_data) {
            $person->getRole()->removeElement($role);
            $role->setPerson(null);
            $this->entityManager->remove($role);
            return $role;
        }

        $role_name = trim($data['role']);
        $role_role = $roleRoleRepository->findOneByName($role_name);
        if ($role_role) {
            $role->setRole($role_role);
        } else {
            $role->setRole(null);
            $msg = "Das Amt '{$role_name}' ist nicht in der Liste der Ämter eingetragen.";
            $person->getInputError()->add(new InputError('role', $msg, 'warning'));
        }
        $role->setRoleName($role_name);

        $inst_name = substr(trim($data['institution']), 0, 255);
        $inst_type_id = $data['instTypeId'];
        $institution = null;
        $diocese = null;
        if ($inst_name != "") {
            $role->setInstitutionTypeId($inst_type_id);
            if ($inst_type_id == 1) {
                $role->setInstitution(null);
                $role->setInstitutionName(null);
                $diocese = $dioceseRepository->findOneByName($inst_name);
                if (is_null($diocese)) {
                    $msg = "Das Bistum '{$inst_name}' ist nicht in der Liste der Bistümer eingetragen.";
                    $person->getInputError()->add(new InputError('role', $msg, 'warning'));
                    $role->setDiocese(null);
                    $role->setDioceseName($inst_name);
                } else {
                    $role->setDiocese($diocese);
                    $role->setDioceseName($diocese->getName());
                }
            } else {
                $role->setDiocese(null);
                $role->setDioceseName(null);
                $query_result = $institutionRepository->findBy([
                    'name' => $inst_name,
                    'itemTypeId' => $inst_type_id
                ]);
                if (count($query_result) < 1) {
                    $msg = "'{$inst_name}' ist nicht in der Liste der Klöster/Domstifte eingetragen.";
                    $person->getInputError()->add(new InputError('role', $msg, 'warning'));
                    $role->setInstitution(null);
                    $role->setInstitutionName($inst_name);
                } else {
                    $institution = $query_result[0];
                    $role->setInstitution($institution);
                    $role->setInstitutionName($institution->getName());
                }
            }
        }

        // other fields
        $role_uncertain = isset($data['uncertain']) ? 1 : 0;
        $role->setUncertain($role_uncertain);

        $data['note'] = substr(trim($data['note']), 0, 1023);
        $this->utilService->setByKeys($role, $data, ['note', 'dateBegin', 'dateEnd']);

        // numerical values for dates
        $date_begin = $role->getDateBegin();
        if (!$this->emptyDate($date_begin)) {
            $year = $this->utilService->parseDate($date_begin, 'lower');
            if (!is_null($year)) {
                $this->utilService->setByKeys($role, $data, ['dateBegin']);
                $role->setNumDateBegin($year);
            } else {
                $msg = "Keine gültige Datumsangabe in '".$date_begin."' gefunden.";
                $person->getInputError()->add(new InputError('role', $msg));
            }
        } else {
            $role->setNumDateBegin(null);
        }

        $date_end = $role->getDateEnd();

        if (!$this->emptyDate($date_end)) {
            $year = $this->utilService->parseDate($date_end, 'upper');
            if (!is_null($year)) {
                $this->utilService->setByKeys($role, $data, ['dateEnd']);
                $role->setNumDateEnd($year);
            } else {
                $msg = "Keine gültige Datumsangabe in '".$date_end."' gefunden.";
                $person->getInputError()->add(new InputError('role', $msg));
            }
        } else {
            $role->setNumDateEnd(null);
        }

        $sort_key = UtilService::SORT_KEY_MAX;
        if (!$this->emptyDate($date_begin)) {
            $sort_key = $this->utilService->sortKeyVal($date_begin);
        } elseif (!$this->emptyDate($date_end)) {
            $sort_key = $this->utilService->sortKeyVal($date_end);
        }

        # we got a parse result or both, $date_begin and $date_end are empty
        $role->setDateSortKey($sort_key);

        // free role properties
        $key = 'prop';
        if (array_key_exists($key, $data)) {
            foreach($data[$key] as $data_loop) {
                $this->mapRoleProperty($person, $role, $data_loop);
            }
        }

        return $role;

    }

    private function emptyDate($s) {
        return (is_null($s) || $s == '?' || $s == 'unbekannt');
    }

    /**
     * fill person's references with $data
     */
    private function mapReference($person, $data) {
        $referenceRepository = $this->entityManager->getRepository(ItemReference::class);
        $volumeRepository = $this->entityManager->getRepository(ReferenceVolume::class);

        $id = $data['id'];
        $item = $person->getItem();
        $item_type_id = $item->getItemTypeId();

        $key_list = ['volume', 'page', 'idInReference'];
        $no_data = $this->utilService->no_data($data, $key_list);
        $reference = null;

        // new reference
        if ($data['id'] == 0) {
            if ($no_data || $data['delete'] == "delete") {
                return null;
            } else {
                $reference = new ItemReference();
                $item->getReference()->add($reference);
                if ($item->getId() > 0) {
                    $reference->setItem($item);
                    $this->entityManager->persist($reference);
                }
            }
        } else {
            $reference = $referenceRepository->find($id);
        }

        // delete?
        if (!is_null($reference) && $data['delete'] == "delete" || $no_data) {
            $item->getReference()->removeElement($reference);
            $reference->setItem(null);
            $this->entityManager->remove($reference);
            return $reference;
        }

        // set data
        $volume_name = trim($data['volume']);
        $reference->setVolumeTitleShort($volume_name); # save data for the form

        if ($volume_name != "") {
            $volume_query_result = $volumeRepository->findByTitleShortAndType($volume_name, $item_type_id);
            if ($volume_query_result) {
                $volume = $volume_query_result[0];
                $reference->setItemTypeId($item_type_id);
                $reference->setReferenceId($volume->getReferenceId());
            } else {
                $error_msg = "Keinen Band für '".$volume_name."' gefunden.";
                $person->getInputError()->add(new InputError('reference', $error_msg));
            }
        } else {
            $error_msg = "Das Feld 'Bandtitel' darf nicht leer sein.";
            $person->getInputError()->add(new InputError('reference', $error_msg));
        }

        $key_list = ['page','idInReference'];
        $this->utilService->setByKeys($reference, $data, $key_list);

        return $reference;
    }

    /**
     * fill item properties (free properties) with $data
     */
    private function mapItemProperty($person, $data) {
        $itemPropertyRepository = $this->entityManager->getRepository(ItemProperty::class);

        $id = $data['id'];
        $item = $person->getItem();

        // the property entry is considered empty if no value is set
        $key_list = ['value'];
        $no_data = $this->utilService->no_data($data, $key_list);
        $itemProperty = null;

        // new itemProperty
        if ($data['id'] == 0) {
            if ($no_data) {
                return null;
            } else {
                $itemProperty = new ItemProperty();
                $item->getItemProperty()->add($itemProperty);
                if ($person->getId() > 0) {
                    $itemProperty->setItem($item);
                    $this->entityManager->persist($itemProperty);
                }
            }
        } else {
            $itemProperty = $itemPropertyRepository->find($id);
        }

        // delete?
        if (!is_null($itemProperty) && ($data['delete'] == 'delete' || $no_data)) {
            $item->getItemProperty()->removeElement($itemProperty);
            $itemProperty->setItem(null);
            $this->entityManager->remove($itemProperty);
            return $itemProperty;
        }

        // set data
        $property_type = $this->entityManager->getRepository(ItemPropertyType::class)
                                             ->find($data['type']);
        $itemProperty->setPropertyTypeId($property_type->getId());
        $itemProperty->setType($property_type);

        // case of completely missing data see above
        if (trim($data['value']) == "") {
            $msg = "Das Feld 'Attribut-Wert' darf nicht leer sein.";
            $person->getInputError()->add(new InputError('name', $msg));
        }
        $itemProperty->setValue($data['value']);

        return $itemProperty;
    }

    /**
     * fill id external with $data
     */
    private function mapIdExternal($person, $data) {
        $idExternalRepository = $this->entityManager->getRepository(IdExternal::class);
        $authorityRepository = $this->entityManager->getRepository(Authority::class);

        $item = $person->getItem();
        $item_id = $item->getId();

        $item_id_external_list = $item->getIdExternal();
        // replace all existing entries
        $id_external_list = $idExternalRepository->findByItemId($item_id);
        foreach($id_external_list as $id_loop) {
            $item->getIdExternal()->removeElement($id_loop);
            //$id_loop->setItem(null); not necessary
            $this->entityManager->remove($id_loop);
        }


        foreach($data as $key => $value) {
            if (!is_null($value) && trim($value) != "") {
                $authority_id = Authority::ID[$key];

                $id_external = new IdExternal();
                $item_id_external_list->add($id_external);
                $id_external->setAuthorityId($authority_id);
                $authority = $authorityRepository->find($authority_id);
                $id_external->setAuthority($authority);
                // drop base URL if present
                if ($key == 'Wikipedia') {
                    $val_list = explode('/', trim($value));
                    $value = array_slice($val_list, -1)[0];
                }
                $id_external->setValue($value);
                if ($item->getId() > 0) {
                    $id_external->setItem($item);
                    $this->entityManager->persist($id_external);
                }
            }
        }

        return null;

    }


    /**
     * fill role properties (free properties) with $data
     */
    private function mapRoleProperty($person, $role, $data) {
        $rolePropertyRepository = $this->entityManager->getRepository(PersonRoleProperty::class);
        $id = $data['id'];

        // the property entry is considered empty if no value is set
        $key_list = ['value'];
        $no_data = $this->utilService->no_data($data, $key_list);
        $roleProperty = null;

        // new roleProperty
        if ($data['id'] == 0) {
            if ($no_data) {
                return null;
            } else {
                $roleProperty = new PersonRoleProperty();
                $role->getRoleProperty()->add($roleProperty);
                if ($person->getId() > 0) {
                    $roleProperty->setPersonRole($role);
                    $roleProperty->setPersonId($person->getId());
                    $this->entityManager->persist($roleProperty);
                }
            }
        } else {
            $roleProperty = $rolePropertyRepository->find($id);
        }

        // delete?
        if (!is_null($roleProperty) && $data['delete'] == "delete" || $no_data) {
            $role->getRoleProperty()->removeElement($roleProperty);
            $roleProperty->setPersonRole(null);
            $this->entityManager->remove($roleProperty);
            return $roleProperty;
        }

        // set data
        $property_type = $this->entityManager->getRepository(RolePropertyType::class)
                                             ->find($data['type']);
        $roleProperty->setPropertyTypeId($property_type->getId());
        $roleProperty->setType($property_type);

        // case of completely missing data see above
        if (trim($data['value']) == "") {
            $msg = "Das Feld 'Attribut-Wert' darf nicht leer sein.";
            $person->getInputError()->add(new InputError('role', $msg));
        } else {
            $roleProperty->setValue($data['value']);
        }

        return $roleProperty;
    }


    /**
     * updateDateRange($person)
     *
     * update dateMin and dateMax
     */
    private function updateDateRange($person) {
        $date_min = null;
        $date_max = null;

        foreach ($person->getRole() as $role) {
            $date_begin = $role->getNumDateBegin();
            if (!is_null($date_begin) && (is_null($date_min) || $date_min > $date_begin)) {
                $date_min = $date_begin;
            }
            $date_end = $role->getNumDateEnd();
            if (!is_null($date_end) && (is_null($date_max) || $date_max < $date_end)) {
                $date_max = $date_end;
            }

        }

        // birth and death restrict date range
        $date_birth = $person->getNumDateBirth();
        if (!is_null($date_birth)
            && (is_null($date_min) || $date_min < $date_birth)) {
            $date_min = $date_birth;
        }
        $date_death = $person->getNumDateDeath();
        if (!is_null($date_death)
            && (is_null($date_max) || $date_max > $date_death)) {
            $date_max = $date_death;
        }

        // use date_min as fallback for $date_max and vice versa
        if (is_null($date_max)) {
            $date_max = $date_min;
        }
        if (is_null($date_min)) {
            $date_min = $date_max;
        }

        $person->setDateMin($date_min);
        $person->setDateMax($date_max);

        return $person;
    }


    public function makeIdPublic($item_type, $numeric_part)  {
        $width = Item::ITEM_TYPE_ID[$item_type]['numeric_field_width'];
        $numeric_field = str_pad($numeric_part, $width, "0", STR_PAD_LEFT);

        $mask = Item::ITEM_TYPE_ID[$item_type]['id_public_mask'];
        $id_public = str_replace("#", $numeric_field, $mask);
        return $id_public;
    }

    /**
     * create person object
     */
    public function makePersonScheme($id_in_source, $userWiagId) {

        $item = Item::newItem($userWiagId, 'Bischof');
        $person = Person::newPerson($item);
        $item->setEditStatus(self::EDIT_STATUS_DEFAULT['Bischof']);

        $item->setIdInSource($id_in_source);
        $item->setFormIsExpanded(1);

        return $person;
    }

    /**
     * create person object and persist
     */
    public function makePersonPersist($user_wiag_id, $item_type_name) {

        $item = Item::newItem($user_wiag_id, $item_type_name);
        $person = Person::newPerson($item);

        $item->setEditStatus(self::EDIT_STATUS_DEFAULT[$item_type_name]);
        $this->entityManager->persist($person);
        $this->entityManager->flush();
        $person_id = $item->getId();

        $personRepository = $this->entityManager->getRepository(Person::class);
        $person = $personRepository->find($person_id);

        return $person;
    }

    /**
     * updateMerged($form_data, $user_wiag_id)
     *
     * find merged entities; update meta data
     */
    public function updateMerged($person, $user_wiag_id) {
        $itemRepository = $this->entityManager->getRepository(Item::class);
        $parent_list = $person->getItem()->getMergeParent();
        $child_id = $person->getId();
        foreach ($parent_list as $parent) {
            $parent->setMergedIntoId($child_id);
            $parent->setMergeStatus('parent');
        }
    }

    /**
     * updateEditMetaData($item, $edit_status, $user_wiag_id, $child_id, $new_flag = false)
     *
     * update meta data for $item
     */
    public function updateEditMetaData($item, $edit_status, $user_wiag_id, $new_flag = false) {

        $now_date = new \DateTimeImmutable('now');
        $item->setChangedBy($user_wiag_id);
        $item->setDateChanged($now_date);

        // new item
        if ($new_flag) {
            $item->setCreatedBy($user_wiag_id);
            $item->setDateCreated($now_date);
        }

        $item->setEditStatus($edit_status);

        return($item);

    }

    /**
     * validateSubstring($person, $key_list, $substring)
     *
     * Set error if fields contain $substring
     */
    public function validateSubstring($person, $key_list, $substring) {
        foreach($key_list as $key) {
            $get_fnc = 'get'.ucfirst($key);
            if (str_contains($person->$get_fnc(), $substring)) {
                $field = Person::EDIT_FIELD_LIST[$key];
                $msg = "Das Feld '".$field."' enthält '".$substring."'.";
                $person->getInputError()->add(new InputError('name', $msg));
            }
        }
    }

};
