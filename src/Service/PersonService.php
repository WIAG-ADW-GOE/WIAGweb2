<?php

namespace App\Service;

use App\Entity\Item;
use App\Entity\ItemProperty;
use App\Entity\ItemPropertyType;
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
use App\Entity\NameLookup;
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

        $pj = array_merge($pj, $person->getItem()->arrayItemProperty());

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

        $url_external = $person->getItem()->getUrlExternal();
        foreach ($url_external as $uext_loop) {
            $auth = $uext_loop->getAuthority();
            $nd[$auth->getUrlNameFormatter()] = $uext_loop->getUrl();
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

        $fv = $person->getItem()->arrayItemProperty();
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


        // 2023-03-24
        // neue Version siehe unten
        // $fv = $person->getItem()->getUrlExternalByAuthorityId(Authority::ID['GND']);
        // if($fv) $pld[$gfx.'gndIdentifier'] = RDFService::xmlStringData($fv);


        // $exids = array();

        // foreach ($person->getItem()->getUrlExternal() as $id) {
        //     $exids[] = [
        //         '@rdf:resource' => $id->getAuthority()->getUrlFormatter().$id->getValue(),
        //     ];
        // }

        // if(count($exids) > 0) {
        //     $pld[$owlfx.'sameAs'] = count($exids) > 1 ? $exids : $exids[0];
        // }

        // $fv = $person->getItem()->getUriExtByAuthId(Authority::ID['Wikipedia']);
        // if($fv) {
        //     $pld[$foaffx.'page'] = $fv;
        // }

        // external IDs/URLs
        $exids = array();
        $wikipedia = null;
        $gnd = null;
        foreach ($person->getItem()->getUrlExternal() as $id) {
            $url_complete = $id->getAuthority()->getUrlFormatter().$id->getValue();
            $auth_name_formatter = $id->getAuthority()->getUrlNameFormatter();
            if ($auth_name_formatter == 'Wikipedia-Artikel') {
                $wikipedia = $url_complete;
            } elseif ($auth_name_formatter == 'Gemeinsame Normdatei (GND) ID') {
                $gnd = $id->getValue();
            }
            else {
                $exids[] = [
                    '@rdf:resource' => $url_complete
                ];
            }
        }

        if($gnd) {
            $pld[$gfx.'gndIdentifier'] = RDFService::xmlStringData($gnd);
        }
        if($wikipedia) {
            $pld[$foaffx.'page'] = $wikipedia;
        }
        if(count($exids) > 0) {
            $pld[$owlfx.'sameAs'] = count($exids) > 1 ? $exids : $exids[0];
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

        $fv = $person->getItem()->arrayItemProperty();
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

        // external IDs/URLs
        $exids = array();
        $wikipedia = null;
        $gnd = null;
        foreach ($person->getItem()->getUrlExternal() as $id) {
            $url_complete = $id->getAuthority()->getUrlFormatter().$id->getValue();
            $auth_name_formatter = $id->getAuthority()->getUrlNameFormatter();
            if ($auth_name_formatter == 'Wikipedia-Artikel') {
                $wikipedia = $url_complete;
            } elseif ($auth_name_formatter == 'Gemeinsame Normdatei (GND) ID') {
                $gnd = $id->getValue();
            }
            else {
                $exids[] = $url_complete;
            }
        }

        if($gnd) {
            $pld[$gfx.'gndIdentifier'] = $gnd;
        }
        if($wikipedia) {
            $pld[$foaffx.'page'] = $wikipedia;
        }
        if(count($exids) > 0) {
            $pld[$owlfx.'sameAs'] = count($exids) > 1 ? $exids : $exids[0];
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
            $text = 'Weihe zum '.$properties[$key]['value'];
            $fv = $properties[$key]['date'];
            if (!is_null($fv)) {
                // $date_value = date('d.m.Y', $fv);
                $text = $text.' am '.$fv;
            }
            if (array_key_exists($key.'_place', $properties)) {
                $text = $text.' in '.$properties[$key.'_place']['value'];
            }
            return $text;
        }

        return null;

    }

}
