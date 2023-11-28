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

class DownloadService {
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

    // number of roles used for the description field (FactGrid)
    const N_ROLE_FOR_DESCRIPTION = 2;

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

    /**
     * @return header for formatted person data
     */
    static public function formatPersonDataHeader() {
        return [
            'id',
            'givenname',
            'prefix',
            'familyname',
            'displayname',
            'date of birth',
            'date of death',
            'description',
            'GND ID',
            'GSN',
            'FactGrid ID',
            'Wikidata ID',
            'Wikipedia'
        ];
    }

    /**
     * @return formatted person data
     */
    static public function formatPersonData($person, $role_list) {

        $item = $person['item'];
        $itemCorpus = $item['itemCorpus'];
        $urlExternal = $item['urlExternal'];

        $data = array();
        $data['id'] = self::idPublic($person['item']['itemCorpus']);
        $data['givenname'] = $person['givenname'];
        $data['prefix'] = $person['prefixname'];
        $data['familyname'] = $person['familyname'];
        $data['displayname'] = self::displayName($person);
        $data['date of birth'] = $person['dateBirth'];
        $data['date of death'] = $person['dateDeath'];
        $data['description'] = self::describe($person, $role_list);
        foreach (['GND', 'GSN', 'FactGrid', 'Wikidata', 'Wikipedia'] as $auth) {
            $auth_id = Authority::ID[$auth];
            $uext = UtilService::findFirstArray($urlExternal, 'authorityId', $auth_id);
            $data[$auth] = is_null($uext) ? null : $uext['value'];
        }

        return $data;
    }

    /**
     * @return preferred ID public (see Entity/Item)
     */
    static public function idPublic($item_corpus_list) {
        $id_public_cand = null;
        foreach (['epc', 'can', 'dreg-can'] as $corpus_id) {
            foreach ($item_corpus_list as $ic_loop) {
                $id_public_cand = $ic_loop['idPublic'];
                if ($ic_loop['corpusId'] == $corpus_id) {
                    return $id_public_cand;
                }
            }
        }

        return $id_public_cand;
    }

    /**
     * combine name elements
     */
    static public function displayname($person): string {
        $given = $person['givenname'];
        $given = $person['givenname'];
        if (is_null($given) or strlen($given) == 0) {
            $given = '';
        }

        $prefix = $person['prefixname'];
        if (!is_null($prefix) and strlen($prefix) > 0) {
            $prefix = ' '.$prefix;
        } else {
            $prefix = '';
        }
        $family = $person['familyname'];
        if (!is_null($family) and strlen($family) > 0) {
            $family = ' '.$family;
        } else {
            $family = '';
        }

        $agnomen = '';
        $note = $person['noteName'];
        if (!is_null($note) and strlen($note) > 0) {
            $note = str_replace(';', ',', $note);
            $note_list = explode(',', $note);
            $agnomen = ' '.$note_list[0];
        }
        return $given.$prefix.$family.$agnomen;
    }

    /**
     * @return basic information (date of birth, date of death, offices)
     */
    static public function describe($person, $role_list) {
        $date_list = array();
        if (!is_null($person['dateBirth']) and trim($person['dateBirth']) != "") {
            $date_list[] = "* ".$person['dateBirth'];
        }
        if (!is_null($person['dateDeath']) and trim($person['dateDeath']) != "") {
            $date_list[] = "+ ".$person['dateDeath'];
        }

        $date_txt = null;
        if (count($date_list) > 0) {
            $date_txt = implode(", ", $date_list);
        } else {
            $firstRoleDate = self::firstRoleDate($role_list);
            if (!is_null($firstRoleDate)) {
                $date_txt = '~ '.$firstRoleDate;
            }
        }

        $description_list = array();
        if ($date_txt) {
            $description_list[] = $date_txt;
        }
        $role_description = self::describeRoleList($role_list);
        if (!is_null($role_description)) {
            $description_list[] = $role_description;
        }

        return implode(', ', $description_list);
    }

    static public function firstRoleDate($role_list) {
        if (count($role_list) == 0) {
            return null;
        }
        usort($role_list, function($a, $b) {
            return UtilService::compare($a, $b, ['dateSortKey']);
        });

        $role_first = array_values($role_list)[0];
        return !is_null($role_first['dateBegin']) ? $role_first['dateBegin'] : $role_first['dateEnd'];
    }

    /**
     * @return a string with the most import offices
     */
    static public function describeRoleList($role_list) {
        // hard code highest ranked office types(!?)
        $p_dioc = 'Leitungsamt Diözese';
        $p_cap = 'Leitungsamt Domstift';

        if (count($role_list) < 1) {
            return "";
        }

        // sort by priority and time (youngest first)
        usort($role_list, function($a, $b) use ($p_dioc, $p_cap) {
            return self::cmpPersonRole($a, $b, $p_dioc, $p_cap);
        });

        $role_list = self::uniquePersonRole($role_list);

        $n = 0;
        $role_txt_list = array();
        foreach ($role_list as $role_desc) {
            // institution is more specific than diocese
            $role_txt_list[] = self::describePersonRole($role_desc);
            $n += 1;
            if ($n == self::N_ROLE_FOR_DESCRIPTION) {
                break;
            }
        }

        return implode(", ", $role_txt_list);
    }

    static public function cmpPersonRole($a, $b, $p_dioc, $p_cap) {
        if (!is_null($a['role']) and !is_null($b['role'])) {
            $a_rg = $a['role']['roleGroup'];
            $b_rg = $a['role']['roleGroup'];

            if ($a_rg == $p_dioc and $b_rg != $p_dioc) {
                return -1;
            }
            if ($a_rg != $p_dioc and $b_rg == $p_dioc) {
                return 1;
            }
            if ($a_rg == $p_cap and $b_rg != $p_cap) {
                return -1;
            }
            if ($a_rg != $p_cap and $b_rg == $p_cap) {
                return 1;
            }
        }
        if (is_null($a['role']) and !is_null($b['role'])) {
            return 1;
        }
        if (is_null($b['role']) and !is_null($b['role'])) {
            return -1;
            }
        // both are null or have the same roleGroup
        // latest data first
        return -1 * UtilService::compare($a, $b, ['dateSortKey']);

    }

    static public function uniquePersonRole($role_list) {
        // remove duplicate entries (there may be several sources)
        // - This procedure removes equal entries only if they are adjacent in the list,
        // - this is sufficient, because we need only two elements of the list.
        $pr_last = null;
        $role_list_unique = array();
        foreach ($role_list as $pr) {
            if (is_null($pr_last)) {
                $role_list_unique[] = $pr;
                $pr_last = $pr;
                continue;
            }
            // the last three digits of dateSortKey are used to code approcimate date specifications
            if ($pr_last['roleId'] == $pr['roleId']
                and (UtilService::equalNotNull($pr_last['institutionId'], $pr['institutionId'])
                     or UtilService::equalNotNull($pr_last['dioceseId'], $pr['dioceseId']))
                and (abs($pr_last['dateSortKey'] - $pr['dateSortKey']) < 2000)) {
                continue;
            }
            $role_list_unique[] = $pr;
            $pr_last = $pr;
        }

        return $role_list_unique;
    }

    /**
     * compose string containing basic information for $role
     *
     * compare PersonRole->describe()
     */
    static public function describePersonRole($person_role): string {

        $name = null;
        if (!is_null($person_role['role'])) {
            $name = $person_role['role']['name'];
        } else {
            $name = $person_role['roleName'];
        }

        $inst_or_dioc = null;
        if (!is_null($person_role['institution'])) {
            $inst_or_dioc = $person_role['institution']['name'];
        } elseif (!is_null($person_role['institutionName'])) {
            $inst_or_dioc = $person_role['institutionName'];
        } elseif (!is_null($person_role['diocese'])) {
            $inst_or_dioc = $person_role['diocese']['name'];
        } elseif (!is_null($person_role['dioceseName'])) {
            $inst_or_dioc = $person_role['dioceseName'];
        }

        $date_info = null;
        $dbc = $person_role['dateBegin'];
        $date_begin = is_null($dbc) ? null : (strlen(trim($dbc)) == 0 ? null : $dbc);
        $dec = $person_role['dateEnd'];
        $date_end = is_null($dec) ? null : (strlen(trim($dec)) == 0 ? null : $dec);

        if (!is_null($date_begin) and !is_null($date_end)) {
            $date_info = $date_begin.'-'.$date_end;
        } elseif (!is_null($date_begin)) {
            $date_info = $date_begin;
        } elseif (!is_null($date_end)) {
            $date_info = 'bis '.$date_end;
        }

        $description = '';
        if ($name) {
            $description = $name;
        }
        if ($inst_or_dioc) {
            $description = $description.' '.$inst_or_dioc;
        }
        if ($date_info) {
            $description = $description.' '.$date_info;
        }

        return $description;
    }

    /**
     * @return header for formatted person data
     */
    static public function formatPersonRoleDataHeader() {
        return [
            'person_id',
            'id',
            'name',
            'institution',
            'diocese',
            'date begin',
            'date end',
            'GND',
            'GSN',
            'FactGrid'
        ];
    }

    /**
     * @return formatted person role data
     */
    static public function formatPersonRoleData($person, $role) {

        $item = $person['item'];
        $itemCorpus = $item['itemCorpus'];
        $urlExternal = $item['urlExternal'];

        $data = array();
        $data['person_id'] = self::idPublic($person['item']['itemCorpus']);
        $data['id'] = $role['id'];
        $data['name'] = !is_null($role['role']) ? $role['role']['name'] : $role['roleName'];
        $data['institution'] = !is_null($role['institution']) ? $role['institution']['name'] : $role['institutionName'];
        $diocese = $role['diocese'];
        if (!is_null($diocese)) {
            $data['diocese'] = $diocese['dioceseStatus'].' '.$diocese['name'];
        } else {
            $data['diocese'] = $role['dioceseName'];
        }
        $data['date begin'] = $role['dateBegin'];
        $data['date end'] = $role['dateEnd'];
        foreach (['GND', 'GSN', 'FactGrid'] as $auth) {
            $auth_id = Authority::ID[$auth];
            $uext = UtilService::findFirstArray($urlExternal, 'authorityId', $auth_id);
            $data[$auth] = is_null($uext) ? null : $uext['value'];
        }


        return $data;
    }

}
