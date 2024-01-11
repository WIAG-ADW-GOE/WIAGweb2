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
use App\Service\DownloadService;

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
            "owl" => "http://www.w3.org/2002/07/owl#",
            "gndo" => "https://d-nb.info/standards/elementset/gnd#",
            "schema" => "https://schema.org/",
            "dcterms" => "http://purl.org/dc/terms/", # Dublin Core
            "variantNamesByLang" => [
                "@id" => "https://d-nb.info/standards/elementset/gnd#variantName",
                "@container" => "@language",
            ],
        ],
    ];

    // property type ID for ordination
    const ORDINATION_ID = 11;

    private $router;
    private $entityManager;

    public function __construct(UrlGeneratorInterface $router,
                                EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
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
     * personObjDataPlain
     *
     * build data array for $person with office data in $personRole
     */
    public function personObjDataPlain($person, $personRole) {

        $pj = array();
        $pj['wiagId'] = $person->getItem()->getIdPublic();

        $fv = $person->getFamilyname();
        if ($fv) $pj['familyName'] = $fv;

        $pj['givenName'] = $person->getGivenname();

        // $pj = array_merge($pj, $person->getItem()->arrayItemProperty());

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

        // item properties
        $nd = array();

        $item_property = $person->getItem()->getItemProperty();
        foreach ($item_property as $ip_loop) {
            $prop_type = $ip_loop->getType()->getName();
            $nd[$prop_type] = $ip_loop->getValue();
        }

        if (count($nd) > 0) {
            $pj['biographicalNotes'] = $nd;
        }

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

    /**
     * personDataPlain
     *
     * build data array for `person` with office data in `personRole`
     */
    public function personDataPlain($person, $personRole) {
        $pj = array();

        $pj['wiagId'] = DownloadService::idPublic($person['item']['itemCorpus']);

        $copy_list = [
            'givenname' => 'givenname',
            'prefixname' => 'prefix',
            'familyname' => 'familyname',
            'noteName' => 'noteName',
            'notePerson' => 'notePerson',
            'dateBirth' => 'dateOfBirth',
            'dateDeath' => 'dateOfDeath',
            'academicTitle' => 'academicTitle'
        ];

        foreach ($copy_list as $key => $field) {
            $fv = $person[$key];
            if (!is_null($fv) and trim($fv) != "" ) {
                $pj[$field] = trim($fv);
            }
        }

        // name variants
        $nv = $person['familynameVariants'];
        $nv_list = array();
        foreach ($nv as $vi) {
            $nv_list[] = $vi['name'];
        }

        if ($nv_list) {
            $pj['familyNameVariant'] = implode(', ', $nv_list);
        }

        $nv = $person['givennameVariants'];
        $nv_list = array();
        foreach ($nv as $vi) {
            $nv_list[] = $vi['name'];
        }

        if ($nv_list) {
            $pj['givenNameVariant'] = implode(', ', $nv_list);
        }

        // religious order
        $fv = $person['religiousOrder'];
        if($fv) {
            $pj['religiousOrder'] = $fv['abbreviation'];
        }

        // item properties
        $nd = array();

        $item_property = $person['item']['itemProperty'];
        foreach ($item_property as $ip_loop) {
            $prop_type = $ip_loop['type']['name'];
            $nd[$prop_type] = $ip_loop['value'];
        }

        if (count($nd) > 0) {
            $pj['biographicalNotes'] = $nd;
        }


        // external identifiers
        $nd = array();

        $url_external = $person['item']['urlExternal'];
        foreach ($url_external as $uext_loop) {
            $auth = $uext_loop['authority'];
            $nd[$auth['urlNameFormatter']] = self::makeUrl($uext_loop);
        }

        if ($nd) {
            $pj['identifier'] = $nd;
        }

        // roles (offices)
        $nd = array();
        foreach ($personRole as $p_loop) {
            $role_list = $p_loop['role'];
            $ref_list = $p_loop['item']['reference'];
            foreach ($role_list as $role) {
                $fv = $this->roleData($role, $ref_list);
                if (!is_null($fv) and count($fv) > 0) {
                    $nd[] = $fv;
                }
            }
        }

        if ($nd) {
            $pj['offices'] = $nd;
        }

        // birthplace



        return $pj;

        // ### version before 2023-12-20
        // TODO 2024-01-04

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
     * 2024-01-14 obsolete?!
     */
    public function personObjLinkedData($person, $personRole) {
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
        $flag_names = false;
        $flag_properties = true;
        $fv = $person->commentLine($flag_names, $flag_properties);
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

        $personId = DownloadService::idPublic($person['item']['itemCorpus']);

        $fn = $person['familyname'];
        $fndt = RDFService::xmlStringData($fn);

        $gn = $person['givenname'];
        $gndt = RDFService::xmlStringData($gn);

        $prefixname = $person['prefixname'];
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

        $gnvs = $person['givennameVariants'];

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

        $fnvs = $person['familynameVariants'];
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
        // $flag_names = false;
        // $flag_properties = true;
        // $fv = $person->commentLine($flag_names, $flag_properties);
        // if($fv) {
        //     $bhi[] = $fv;
        // }

        // $fv = $person->getItem()->arrayItemProperty();
        // if ($fv) {
        //     $ipt = $this->itemPropertyText($fv, 'ordination_priest');
        //     if ($ipt) {
        //         $bhi[] = $ipt;
        //     }
        // }

                $fv = $person['academicTitle'];
        if($fv) {
            $bhi[] = $fv;
        }

        $fv = $person['noteName'];
        if($fv) {
            $bhi[] = $fv;
        }

        $prop_list = $person['item']['itemProperty'];
        foreach($prop_list as $prop) {
            if ($prop['propertyTypeId'] == self::ORDINATION_ID) {
                $ord_text = 'Weihe zum '.$prop['value'];
                $fv = $prop['dateValue'];
                if (!is_null($fv)) {
                    // $date_value = date('d.m.Y', $fv);
                    $ord_text = $ord_text.' am '.$fv;
                }
                $fv = $prop['placeValue'];
                if (!is_null($fv)) {
                    $ord_text = $text.' in '.$fv;
                }
                $bhi[] = $ord_text;
            }
        }

        if ($bhi) {
            $bhi_string = RDFService::xmlStringData(implode('; ', $bhi));
            $pld[$gfx.'biographicalOrHistoricalInformation'] = $bhi_string;
        }

        $fv = $person['dateBirth'];
        if($fv) $pld[$gfx.'dateOfBirth'] = RDFService::xmlStringData($fv);

        $fv = $person['dateDeath'];
        if($fv) $pld[$gfx.'dateOfDeath'] = RDFService::xmlStringData($fv);

        // 2024-01-11 16:00 ###

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
    public function objReferenceCitation($ref_list) {
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


    /**
     * @return string for bibliographic citation
     */
    static public function referenceCitation($ref_list) {
        $nd = array();
        foreach ($ref_list as $ref) {
            // citation
            $vol = $ref['referenceVolume'];
            if ($vol) {
                $cce = [$vol['fullCitation']];
                $ce = preg_replace('~</?[a-z]+>~', '', $ref['page']);
                if ($ce) {
                    $cce[] = "S. ".$ce;
                }
                $ce = $ref['idInReference'];
                if ($ce) {
                    $cce[] = "ID/Nr. ".$ce;
                }
                $nd[] = implode(', ', $cce);
            }
        }
        return $nd;
    }


    /**
     * personJSONLinkedData
     *
     * build data array for $person with office data in $personRole
     */
    public function personJSONLinkedData($person, $personRole) {
        // collect data in $pld
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

        $wiag_id = DownloadService::idPublic($person['item']['itemCorpus']);

        $personID = [
            "@id" => $wiag_id,
            "@type" => $gfx."DifferentiatedPerson",
        ];

        $fn = $person['familyname'];
        $gn = $person['givenname'];

        $prefixname = $person['prefixname'];

        $aname = array_filter([$gn, $prefixname, $fn],
                              function($v){return $v !== null;});
        $pld[$gfx.'preferredName'] = implode(' ', $aname);

        $pfeftp[$gfx.'forename'] = $gn;
        if($prefixname)
            $pfeftp[$gfx.'prefix'] = $prefixname;
        if($fn)
            $pfeftp[$gfx.'surname'] = $fn;

        $pld[$gfx.'preferredNameEntityForThePerson'] = $pfeftp;

        $gnvs = $person['givennameVariants'];

        $vneftps = array();
        // one or more variants for the given name
        if($gnvs) {
            foreach($gnvs as $gnvi) {
                $vneftp = [];
                $vneftp[$gfx.'forename'] = $gnvi['name'];
                if($prefixname)
                    $vneftp[$gfx.'prefix'] = $prefixname;
                if($fn)
                    $vneftp[$gfx.'surname'] = $fn;
                $vneftps[] = $vneftp;
            }
        }

        $fnvs = $person['familynameVariants'];
        // one or more variants for the familyname
        if($fnvs) {
            foreach($fnvs as $fnvi) {
                $vneftp = [];
                $vneftp[$gfx.'forename'] = $gn;
                if($prefixname)
                    $vneftp[$gfx.'prefix'] = $prefixname;
                $vneftp[$gfx.'surname'] = $fnvi['name'];
                $vneftps[] = $vneftp;
            }
        }

        if($gnvs && $fnvs) {
            foreach($fnvs as $fnvi) {
                foreach($gnvs as $gnvi) {
                    $vneftp = [];
                    $vneftp[$gfx.'forename'] = $gnvi['name'];
                    if($prefixname)
                        $vneftp[$gfx.'prefix'] = $prefixname;
                    $vneftp[$gfx.'surname'] = $fnvi['name'];
                    $vneftps[] = $vneftp;
                }
            }
        }

        // set 'variantNameEntityForThePerson'
        if(count($vneftps) > 0)
            $pld[$gfx.'variantNameEntityForThePerson'] = $vneftps;

        // additional information
        //
        $bhi = array();

        $fv = $person['academicTitle'];
        if($fv) {
            $bhi[] = $fv;
        }

        $fv = $person['noteName'];
        if($fv) {
            $bhi[] = $fv;
        }

        $prop_list = $person['item']['itemProperty'];
        foreach($prop_list as $prop) {
            if ($prop['propertyTypeId'] == self::ORDINATION_ID) {
                $ord_text = 'Weihe zum '.$prop['value'];
                $fv = $prop['dateValue'];
                if (!is_null($fv)) {
                    // $date_value = date('d.m.Y', $fv);
                    $ord_text = $ord_text.' am '.$fv;
                }
                $fv = $prop['placeValue'];
                if (!is_null($fv)) {
                    $ord_text = $text.' in '.$fv;
                }
                $bhi[] = $ord_text;
            }
        }

        if ($bhi) $pld[$gfx.'biographicalOrHistoricalInformation'] = implode('; ', $bhi);

        $fv = $person['dateBirth'];
        if($fv) $pld[$scafx.'birthDate'] = $fv;

        $fv = $person['dateDeath'];
        if($fv) $pld[$scafx.'deathDate'] = $fv;

        // birthplace
        // TODO 2024-01-04
        // $nd = array();
        // foreach ($person->getBirthPlace() as $bp) {
        //     $bpd = array();
        //     $bpd[$scafx.'name'] = $bp->getPlaceName();
        //     $urlwhg = $bp->getUrlWhg();
        //     if ($urlwhg) {
        //         $bpd[$scafx.'sameAs'] = $urlwhg;
        //     }
        //     $nd[] = $bpd;
        // }

        // if ($nd) {
        //     $fv = count($nd) > 1 ? $nd : $nd[0];
        //     $pld[$scafx.'birthPlace'] = $fv;
        // }

        // external IDs/URLs
        $exids = array();
        $wikipedia = null;
        $gnd = null;
        foreach ($person['item']['urlExternal'] as $id) {
            $auth = $id['authority'];
            $url_complete = $auth['urlFormatter'].$id['value'];
            if ($auth['id'] == Authority::ID['Wikipedia']) {
                $wikipedia = $url_complete;
            } elseif ($auth['id'] == Authority::ID['GND']) {
                $gnd = $id['value'];
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
        foreach ($personRole as $p_loop) {
            $role_list = $p_loop['role'];
            $ref_list = $p_loop['item']['reference'];
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
                $nd = self::referenceCitation($ref_list);
                if ($nd) {
                    $fv = count($nd) > 1 ? $nd : $nd[0];
                    $pld[$dctermsfx.'bibliographicCitation'] = $fv;
                }
            }
        }

        return array_merge($personID, $pld);

    }

    /**
     * personObjJSONLinkedData
     *
     * build data array for $person with office data in $personRole
     */
    public function personObjJSONLinkedData($person, $personRole) {
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
        $fv = $person->commentLine(false, false);
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

    /**
     * 2024-01-04 obsolete?
     */
    public function jsonObjRoleNode($office, $ref_list) {
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

    /**
     * @return array of role data with meta data
     */
    public function jsonRoleNode($office, $ref_list) {
        $scafx = "schema:";
        $gndfx = "gndo:";
        $dctermsfx = "dcterms:";

        $ocld['@type'] = RDFService::NAMESP_SCHEMA.'Role';


        $fv = $office['role'];
        $name = null;
        if ($fv) {
            $name = $fv['name'];
        } else {
            $name = $office['roleName'];
        }

        if (!is_null($name)) {
            $ocld[$scafx.'roleName'] = $name;
        }

        $fv = $office['dateBegin'];
        if($fv) $ocld[$scafx.'startDate'] = $fv;

        $fv = $office['dateEnd'];
        if($fv) $ocld[$scafx.'endDate'] = $fv;

        // monastery or diocese
        $inst_name = null;
        $inst_url = null;
        $fv = $office['institution'];
        if ($fv) {
            $inst_name = $fv['name'];
            $inst_url = self::URL_KLOSTERDATENBANK.$fv['idGsn'];
        } else {
            $fv = $office['institutionName'];
            if ($fv) {
                $inst_name = $fv;
            } else {
                $fv = $office['diocese'];
                if($fv) {
                    $inst_name = $fv['dioceseStatus'].' '.$fv['name'];
                    // before 2024-01-04 $inst_url = $this->uriWiagId($fv->getItem()->getIdPublic());
                    $wiag_id = DownloadService::idPublic($fv['item']['itemCorpus']);
                    $inst_url = $this->uriWiagId($wiag_id);
                } else {
                    $fv = $office['dioceseName'];
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

    /**
     * 2024-01-04 obsolete?
     * @return array of role data
     */
    public function roleObjData($role, $ref_list) {
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
     * map array $role to an output array
     */
    public function roleData($role, $ref_list) {
        $pj = array();

        $fv = $role['role'];
        if ($fv) {
            $pj['title'] = $fv['name'];
        } else {
            $fv = $role['roleName'];
            if ($fv) { $pj['title'] = $fv; }
        }

        $fv = null;
        $diocese = $role['diocese'];
        if ($diocese) {
            $fv = $diocese['dioceseStatus'].' '.$diocese['name'];
        } else {
            $fv = $role['dioceseName'];
        }
        if ($fv) { $pj['diocese'] = $fv; }

        $fv = $role['dateBegin'];
        if ($fv) { $pj['dateBegin'] = $fv; }

        $fv = $role['dateEnd'];
        if ($fv) { $pj['dateEnd'] = $fv; }

        $fv = $role['institution'];
        if ($fv) { $pj['institution'] = $fv['name']; }

        $reference_nodes = $this->referenceArrayData($ref_list);
        # $reference_nodes = null;
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
     * collect reference data
     * @return list of reference nodes
     */
    public function referenceArrayData($ref_list) {
        $nd = array();

        foreach ($ref_list as $ref) {
            $rd = array();
            $cce = array();
            // citation
            $vol = $ref['referenceVolume'];

            if ($vol) {
                $cce = [$vol['fullCitation']];

                $ce = preg_replace('~</?[a-z]+>~', '', $ref['page']);
                if ($ce) {
                    $cce[] = "S. ".$ce;
                }
                $ce = $ref['idInReference'];
                if ($ce) {
                    $cce[] = "ID/Nr. ".$ce;
                }
                $rd['citation'] = implode(', ', $cce);
                // authorOrEditor, RiOpac, shortTitle
                $rd['authorOrEditor'] = $vol['authorEditor'];
                $fvi = $vol['riOpacId'];
                if ($fvi) {
                    $rd['RiOpac'] = $fvi;
                }
                $fvi = $vol['titleShort'];
                if ($fvi) {
                    $rd['shortTitle'] = $fvi;
                }
            }
            $nd[] = $rd;
        }

        return $nd;
    }

    /**
     * 2024-01-04 obsolete?
     * @return a text version for a set of elements in `properties`
     */
    public function itemPropertyText($properties, string $key): ?string {

        if (array_key_exists('ordination_priest', $properties)) {
            $text = 'Weihe zum '.$properties[$key][0]['value'];
            $fv = $properties[$key][0]['date'];
            if (!is_null($fv)) {
                // $date_value = date('d.m.Y', $fv);
                $text = $text.' am '.$fv;
            }
            if (array_key_exists($key.'_place', $properties)) {
                $text = $text.' in '.$properties[$key.'_place'][0]['value'];
            }
            return $text;
        }

        return null;

    }

    /**
     * set volume for all references in $person_list
     */
    static public function setVolume(&$person_list, $volume_list) {
        // make volumes accessible via referenceId
        $reference_id_list = array_column($volume_list, 'referenceId');
        $volume_list_idx = array_combine($reference_id_list, $volume_list);


        foreach ($person_list as &$person) {
            foreach ($person['item']['reference'] as &$ref) {
                $ref['referenceVolume'] = $volume_list_idx[$ref['referenceId']];
            }
        }

        return count($person_list);

    }

    /**
     *
     */
    static public function makeUrl($uext) {
        // check if the complete URL is stored in `value`.
        if (str_starts_with($uext['value'], "http")) {
            return $uext['value'];
        } else {
            return $uext['authority']['urlFormatter'].$uext['value'];
        }
    }

}
