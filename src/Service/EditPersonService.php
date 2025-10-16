<?php

namespace App\Service;

use App\Entity\Item;
use App\Entity\Corpus;
use App\Entity\ItemCorpus;
use App\Entity\ItemProperty;
use App\Entity\ItemPropertyType;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\PersonRoleProperty;
use App\Entity\ItemReference;
use App\Entity\ReferenceVolume;
use App\Entity\Role;
use App\Entity\Diocese;
use App\Entity\Institution;
use App\Entity\UrlExternal;
use App\Entity\Authority;
use App\Entity\GivennameVariant;
use App\Entity\FamilynameVariant;
use App\Entity\InputError;
use App\Entity\UserWiag;
use App\Service\EditService;
use App\Service\UtilService;

use Symfony\Component\HttpFoundation\Response;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EditPersonService {
    const URL_KLOSTERDATENBANK = 'https://klosterdatenbank.adw-goe.de/gsn/';

    private $router;
    private $entityManager;

    public function __construct(UrlGeneratorInterface $router,
                                EntityManagerInterface $entityManager) {

        $this->entityManager = $entityManager;
        $this->router = $router;
    }


    /**
     * map/validate content of form
     *
     * Returns list of persons containing the data in $form_data
     */
    public function mapFormData($form_data, $user_id) {

        $person_repository = $this->entityManager->getRepository(Person::class);
        $person_list = array();

        foreach($form_data as $data) {
            $id = $data['id'];
            // skip blank forms
            $person = null;
            if ($id == 0 && !isset($data['item']['formIsEdited'])) {
                continue;
            } elseif (!isset($data['item']['formIsEdited'])) {
                $query_result = $person_repository->findList([$id]);
                $person = $query_result[0];
                // set form collapse state
                $expanded_param = isset($data['item']['formIsExpanded']) ? 1 : 0;
                $person->getItem()->setFormIsExpanded($expanded_param);
            } else {
                $item = new Item($user_id);
                $person = new Person($item);
                $this->mapAndValidatePerson($person, $data);

                // set form collapse state
                $expanded_param = isset($data['item']['formIsExpanded']) ? 1 : 0;
                $person->getItem()->setFormIsExpanded($expanded_param);
            }

            $person_list[] = $person;
        }

        return $person_list;
    }

    /**
     * update $target with data in $person, call entitymanager->persist()
     */
    public function update($target, $person, $current_user_id) {

        $this->copyItem($target, $person, $current_user_id);

        // item property
        $target_item = $target->getItem();
        $source_item = $person->getItem();

        // corpus
        $target_corpus = $target_item->getItemCorpus();
        $source_corpus = $source_item->getItemCorpus();
        $this->setItemAttributeList($target_item, $target_corpus, $source_corpus);

        // item property
        $target_prop = $target_item->getItemProperty();
        $source_prop = $source_item->getItemProperty();
        $this->setItemAttributeList($target_item, $target_prop, $source_prop);
        // reference
        $target_ref = $target_item->getReference();
        $source_ref = $source_item->getReference();
        $this->setItemAttributeList($target_item, $target_ref, $source_ref);
        // url external
        $target_uext = $target_item->getUrlExternal();
        $source_uext = $source_item->getUrlExternal();
        // - look for changes
        // -- 2023-10-06 obsolete in wiag3?
        $target_uext_wiag = $target_item->getUrlExternalByAuthority('WIAG-ID');
        $source_uext_wiag = $source_item->getUrlExternalByAuthority('WIAG-ID');
        if ($target_uext_wiag != $source_uext_wiag) {
            $target_item->setWiagChanged(true);
        }

        $target_uext_gs = $target_item->getUrlExternalByAuthority('GSN');
        $source_uext_gs = $source_item->getUrlExternalByAuthority('GSN');
        if ($target_uext_gs != $source_uext_gs) {
            $target_item->setGsChanged(true);
        }
        // - copy url external
        $this->setItemAttributeList($target_item, $target_uext, $source_uext);


        $this->copyCore($target, $person);

        // name variants
        $this->setPersonAttributeList(
            $target,
            $target->getGivennameVariants(),
            $person->getGivennameVariants(),
        );
        $this->setPersonAttributeList(
            $target,
            $target->getFamilynameVariants(),
            $person->getFamilynameVariants(),
        );
        // errors (relevant for warnings)
        foreach($person->getItem()->getInputError() as $e) {
            $target_item->getInputError()->add($e);
        }

        // roles
        $this->setRole($target, $person);

        // online?
        $item = $target->getItem();
        $this->updateIsOnline($item);
    }

    public function updateIsOnline($item) {
        $corpusRepository = $this->entityManager->getRepository(Corpus::class);

        $item->setIsOnline(0);
        $corpus_id_list = $item->getCorpusIdList();
        // if at least one corpus matches, the item goes online
        foreach ($corpus_id_list as $corpus_id) {
            $corpus = $corpusRepository->findOneByCorpusId($corpus_id);
            $corpus_online_status = $corpus->getOnlineStatus();
            if ($item->getEditStatus() == $corpus_online_status) {
                $item->setIsOnline(1);
                break;
            }
        }

        return $item;
    }

    /**
     * clear $target_list; copy collection $source_list to $target_list;
     */
    private function setItemAttributeList($target, $target_list, $source_list) {

        // - remove entries
        // e.g. $target_ref = $target->getItem()->getReference();
        foreach ($target_list as $t) {
            $target_list->removeElement($t);
            $t->setItem(null);
            $this->entityManager->remove($t);
        }

        // - set new entries
        foreach ($source_list as $i) {
            if (!$i->getDeleteFlag()) {
                $target_list->add($i);
                $i->setItem($target);
                $this->entityManager->persist($i);
            }
        }
    }

    /**
     * copy (validated) data from $source to $target
     */
    private function copyItem($target, $source, $user_id) {
        $field_list = [
            'isOnline',
            'isDeleted',
            'editStatus',
            'commentDuplicate',
            'mergeStatus',
            'normdataEditedBy',
            'formAncestorString',
        ];

        foreach ($field_list as $field) {
            $get_fnc = 'get'.ucfirst($field);
            $set_fnc = 'set'.ucfirst($field);
            $target->getItem()->$set_fnc($source->getItem()->$get_fnc());
        }
        // only relevant in the merging step
        foreach ($source->getItem()->getMergeParent() as $parent_id) {
            $target->getItem()->getMergeParent()->add($parent_id);
        }

        $userWiagRepository = $this->entityManager->getRepository(UserWiag::class);
        $user = $userWiagRepository->find($user_id);

        $target->getItem()->updateChangedMetaData($user);
    }

    private function copyCore($target, $source) {
        $field_list = [
            'givenname',
            'familyname',
            'prefixname',
            'dateBirth',
            'dateDeath',
            'numDateBirth',
            'numDateDeath',
            'dateMin',
            'dateMax',
            'comment',
            'noteName',
            'academicTitle',
            'notePerson',
        ];
        foreach ($field_list as $field) {
            $get_fnc = 'get'.ucfirst($field);
            $set_fnc = 'set'.ucfirst($field);
            $target->$set_fnc($source->$get_fnc());
        }

        return $target;
    }

    /**
     * move elements from $source_list to $target_list
     */
    private function setPersonAttributeList($target, $target_list, $source_list) {
        foreach($target_list as $r) {
            $target_list->removeElement($r);
            $r->setPerson(null);
            $this->entityManager->persist($r);
        }

        foreach($source_list as $s) {
            $target_list->add($s);
            $s->setPerson($target);
            $this->entityManager->persist($s);
        }
    }

    /**
     * move role and role properties from $source to $target
     */
    private function setRole($target, $source) {

        $target_list = $target->getRole();
        foreach($target_list as $r) {
            $r_prop = $r->getRoleProperty();
            foreach($r_prop as $r_p) {
                $r_prop->removeElement($r_p);
                $this->entityManager->remove($r_p);
            }
            $target_list->removeElement($r);
            $r->setPerson(null);
            $this->entityManager->remove($r);
        }

        $source_list = $source->getRole();
        foreach($source_list as $s) {
            if (!$s->getDeleteFlag()) {
                $s_prop = $s->getRoleProperty();
                foreach($s_prop as $s_p) {
                    if (!$s_p->getDeleteFlag()) {
                        $this->entityManager->persist($s_p);
                    } else {
                        $s_prop->removeElement($s_p);
                    }
                }
                $target_list->add($s);
                $s->setPerson($target);
                $this->entityManager->persist($s);
            }
        }
    }

    static function emptyDate($s) {
        return (is_null($s) || $s == "" || $s == '?' || $s == 'unbekannt');
    }

    /**
     * updateDateRange($person)
     *
     * update dateMin and dateMax
     */
    static function updateDateRange($person) {
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


    /**
     * create UrlExternal object
     */
    public function makeUrlExternal($item, $authority) {
        $url_external = new UrlExternal();
        if (!is_null($item->getId())) {
            $url_external->setItemId($item->getId());
        }
        $url_external->setItem($item);
        $url_external->setAuthority($authority); // sets authorityId
        return $url_external;
    }

    public function readParentList($person) {
        $parent_id = $person->getItem()->getMergeParent();

        $personRepository = $this->entityManager->getRepository(Person::class);

        $parent_list = array();
        foreach($parent_id as $p_id) {
            $parent_list[] = $personRepository->find($p_id);
        }

        return $parent_list;
    }

    /**
     * updateItemAsParent(Person $parent, $childId)
     *
     */
    public function updateItemAsParent(Item $item, $childId) {
        $item->setMergedIntoId($childId);
        $item->setMergeStatus('parent');
        $item->setIsOnline(0);
    }

    /**
     * validateSubstring($person, $key_list, $substring)
     *
     * Set error if fields contain $substring
     */
    public function complainSubstring($person, $key_list, $substring) {
        foreach($key_list as $key) {
            $get_fnc = 'get'.ucfirst($key);
            if (str_contains($person->$get_fnc(), $substring)) {
                $field = Person::EDIT_FIELD_LIST[$key];
                $msg = "Das Feld '".$field."' enthält '".$substring."'.";
                $person->getItem()->getInputError()->add(new InputError('name', $msg));
            }
        }
    }

    /**
     * map content of $data to $person
     */
    public function mapAndValidatePerson($person, $data) {
        $itemRepository = $this->entityManager->getRepository(Item::class);


        // item
        $item = $person->getItem();
        $item->setId($data['id']);
        $person->setId($data['id']);

        // check mergeStatus
        // It is possible that a form is visible in the merging process.
        // Prevent a merged person from being edited.
        if ($data['id'] > 0) {
            $item_db = $itemRepository->find($data['id']);
            if ($item_db->getMergeStatus() == 'parent' OR $item_db->getMergeStatus() == 'orphan') {
                $msg = "Der Datensatz kann nicht verändert werden. Er wurde mit einem anderen zusammengeführt.";
                $item->getInputError()->add(new InputError('status', $msg));
            }
        }

        $edit_status = trim($data['item']['editStatus']);
        if ($edit_status == "") {
            $msg = "Das Feld 'Status' darf nicht leer sein.";
            $item->getInputError()->add(new InputError('status', $msg));
        }

        // item: checkboxes
        $key_list = ['formIsEdited'];
        foreach($key_list as $key) {
            $set_fnc = 'set'.ucfirst($key);
            $item->$set_fnc(isset($data['item'][$key]));
        }

        // copy meta data even if they are still empty (new entry)
        $item->setIdInSource($data['item']['idInSource']);

        // item: status values, editorial notes
        $key_list = [
            'editStatus',
            'mergeStatus',
            'changedBy',
            'commentDuplicate',
            'normdataEditedBy',
            'formAncestorString'
        ];

        UtilService::setByKeys($item, $data['item'], $key_list);

        // online?
        $this->updateIsOnline($item);

        $collect_merge_parent = array();
        if (array_key_exists('mergeParent', $data['item'])) {
            foreach ($data['item']['mergeParent'] as $parent_id) {
                $collect_merge_parent[] = $itemRepository->find($parent_id);
            }
        }
        $item->setMergeParent($collect_merge_parent);

        if (isset($data['item']['dateChanged'])) {
            $dateChanged = new \DateTime($data['item']['dateChanged']);
            $item->setDateChanged($dateChanged);
        }

        // person
        $key_list = ['givenname',
                     'prefixname',
                     'familyname',
                     'dateBirth',
                     'dateDeath',
                     'comment',
                     'noteName',
                     'academicTitle',
                     'notePerson'];
        UtilService::setByKeys($person, $data, $key_list);

        // add an error if $needle occurs in one of the fields
        $needle = Item::JOIN_DELIM;
        $this->complainSubstring($person, $key_list, Item::JOIN_DELIM);

        if (is_null($person->getGivenname())) {
            $msg = "Das Feld 'Vorname' kann nicht leer sein.";
            $item->getInputError()->add(new InputError('name', $msg));
        }

        // name variants
        $givenname_variants = trim($data['givennameVariants']);
        $familyname_variants = trim($data['familynameVariants']);
        self::mapNameVariants($person, $givenname_variants, $familyname_variants);

        // numerical values for dates
        self::setNumDates($person);

        // 2023-10-21 obsolete
        // reference to a bishop is stored as an external url
        if (array_key_exists('bishop', $data)) {
            $data['urlext'][] = [
                'deleteFlag' => "",
                'urlName' => "WIAG-ID",
                'value' => $data['bishop'],
                'note' => "",
            ];
        }

        // role
        if (array_key_exists('role', $data)) {
            foreach($data['role'] as $data_loop) {
                $this->mapRole($person, $data_loop);
            }
        }

        // corpus
        $corpus_found_in_data = false;
        if (array_key_exists('corpus', $data)) {
            // examine selection
            foreach ($data['corpus'] as $key => $corpus) {
                if (array_key_exists('checked', $corpus)) {
                    $corpus_found_in_data = true;
                    $this->mapItemCorpus($item, $key, $corpus);
                }
            }
            if (!$corpus_found_in_data) {
                // keep existing data
                foreach ($data['corpus'] as $key => $corpus) {
                    $this->mapItemCorpus($item, $key, $corpus);
                }
                $msg = "Mindestens ein Corpus sollte ausgewählt sein.";
                $item->getInputError()->add(new InputError('status', $msg));
            }
        } else {
            $msg = "Keine Angaben zu Corpus gefunden: Formularfehler";
            $item->getInputError()->add(new InputError('status', $msg));
        }


        // reference
        if (array_key_exists('ref', $data)) {
            foreach($data['ref'] as $data_loop) {
                $this->mapReference($item, $data_loop);
            }
        }

        // property
        if (array_key_exists('prop', $data)) {
            foreach($data['prop'] as $data_loop) {
                $this->mapItemProperty($item, $data_loop, $person);
            }
        }

        // url external
        if (array_key_exists('urlext', $data)) {
            foreach($data['urlext'] as $data_loop) {
                $this->mapUrlExternal($item, $data_loop);
            }
        }

        // set warning when role list is empty
        $this->checkRoleList($person);

        // date min/date max
        self::updateDateRange($person);

        // validation
        if ($item->getIsOnline() && $item->getIsDeleted()) {
            $msg = "Der Eintrag kann nicht gleichzeitig online und gelöscht sein.";
            $item->getInputError()->add(new InputError('status', $msg));
        }

        return $person;
    }

    /**
     * checkRoleList($person)
     *
     * add a warning if no valid role is found
     */
    private function checkRoleList($person) {
        $role_found = false;
        $role_list = $person->getRole();
        foreach ($role_list as $person_role) {
            if ($person_role->getDeleteFlag() != "delete") {
                $role_found = true;
            }
        }

        if (!$role_found) {
            $msg = "Hinweis: Der Eintrag hat keine Angaben zu Ämtern!";
            $person->getItem()->getInputError()->add(new InputError('role', $msg, 'warning'));
        }

    }

    static function setNumDates($person) {
        $date_birth = $person->getDateBirth();
        if (!self::emptyDate($date_birth)) {
            $year = UtilService::parseDate($date_birth, 'lower');
            if (!is_null($year)) {
                $person->setNumDateBirth($year);
            } else {
                $msg = "Keine gültige Datumsangabe in '".$date_birth."' gefunden.";
                $person->getItem()->getInputError()->add(new InputError('name', $msg));
            }
        } else {
            $person->setNumDateBirth(null);
        }

        $date_death = $person->getDateDeath();
        if (!self::emptyDate($date_death)) {
            $year = UtilService::parseDate($date_death, 'upper');
            if (!is_null($year)) {
                $person->setNumDateDeath($year);
            } else {
                $msg = "Keine gültige Datumsangabe in '".$date_death."' gefunden.";
                $person->getItem()->getInputError()->add(new InputError('name', $msg));
            }
        } else {
            $person->setNumDateDeath(null);
        }
    }

    /**
     * map name variants do not mark them as persistent
     */
    static function mapNameVariants($person, $gnv_str, $fnv_str) {
        // givenname
        $person->setFormGivennameVariants($gnv_str);
        $person_id = $person->getId();

        // - remove entries; not neccessary since $person is empty
        $person_gnv = $person->getGivennameVariants();

        // - set new entries
        // -- ';' is an alternative separator
        $gnv_str = str_replace(';', ',', $gnv_str);
        $gnv_list = explode(',', $gnv_str);
        foreach ($gnv_list as $gnv) {
            if (trim($gnv) != "") {
                $gnv_new = new GivenNameVariant();
                $person_gnv->add($gnv_new);
                $gnv_new->setName(trim($gnv));
                $gnv_new->setLang('de');
            }
        }

        // familyname
        $person->setFormFamilynameVariants($fnv_str);

        // - remove entries; not neccessary since $person is empty
        $person_fnv = $person->getFamilynameVariants();

        // - set new entries
        // -- ';' is an alternative separator
        $fnv_str = str_replace(';', ',', $fnv_str);
        $fnv_list = explode(',', $fnv_str);
        foreach ($fnv_list as $fnv) {
            if (trim($fnv) != "") {
                $fnv_new = new FamilyNameVariant();
                $person_fnv->add($fnv_new);
                $fnv_new->setName(trim($fnv));
                $fnv_new->setLang('de');
            }
        }

    }

    private function mapRole($person, $data) {
        $roleRepository = $this->entityManager->getRepository(PersonRole::class);
        $roleRoleRepository = $this->entityManager->getRepository(Role::class);

        $id = $data['id'];

        // $key_list = ['role', 'institution', 'date_begin', 'date_end'];
        $key_list = ['role', 'institution'];
        $no_data = UtilService::no_data($data, $key_list);

        $role = null;

        // new role
        if ($no_data) {
            return null;
        } else {
            $role = new PersonRole();
            $person->getRole()->add($role);
            if ($person->getId() > 0) {
                $role->setPerson($person);
            }
        }

        $role_name = trim($data['role']);
        $role_role = $roleRoleRepository->findOneByName($role_name);
        if ($role_role) {
            $role->setRole($role_role);
        } elseif ($role_name == "") {
            $role->setRole(null);
            $msg = "Warnung: Es ist kein Amt angegeben.";
            $role->getInputError()->add(new InputError('role', $msg, 'warning'));
        } else {
            $role->setRole(null);
            $msg = "Das Amt '{$role_name}' ist nicht in der Liste der Ämter eingetragen.";
            $role->getInputError()->add(new InputError('role', $msg, 'error'));
        }
        $role->setRoleName($role_name);

        // set institution or diocese
        $inst_name = substr(trim($data['institution']), 0, 255);
        $inst_type_id = $data['instTypeId'];
        if ($inst_name != "") {
            $this->setInstitution($role, $inst_name, $inst_type_id);
        }

        // other fields
        $role_uncertain = isset($data['uncertain']) ? 1 : 0;
        $role->setUncertain($role_uncertain);

        $data['note'] = substr(trim($data['note']), 0, 1023);
        UtilService::setByKeys($role, $data, ['deleteFlag', 'note', 'dateBegin', 'dateEnd']);

        // numerical values for dates
        $date_begin = $role->getDateBegin();
        if (!self::emptyDate($date_begin)) {
            $year = UtilService::parseDate($date_begin, 'lower');
            if (!is_null($year)) {
                UtilService::setByKeys($role, $data, ['dateBegin']);
                $role->setNumDateBegin($year);
            } else {
                $msg = "Keine gültige Datumsangabe in '".$date_begin."' gefunden.";
                $role->getInputError()->add(new InputError('role', $msg));
            }
        } else {
            $role->setNumDateBegin(null);
        }

        $date_end = $role->getDateEnd();

        if (!self::emptyDate($date_end)) {
            $year = UtilService::parseDate($date_end, 'upper');
            if (!is_null($year)) {
                UtilService::setByKeys($role, $data, ['dateEnd']);
                $role->setNumDateEnd($year);
            } else {
                $msg = "Keine gültige Datumsangabe in '".$date_end."' gefunden.";
                $role->getInputError()->add(new InputError('role', $msg));
            }
        } else {
            $role->setNumDateEnd(null);
        }

        // sort key
        $sort_key = UtilService::SORT_KEY_MAX;
        if (!$this->emptyDate($date_begin)) {
            $sort_key = UtilService::sortKeyVal($date_begin);
        } elseif (!$this->emptyDate($date_end)) {
            $sort_key = UtilService::sortKeyVal($date_end);
        }

        // - we got a parse result or both, $date_begin and $date_end are empty
        $role->setDateSortKey($sort_key);

        // free role properties
        $key = 'prop';
        if (array_key_exists($key, $data)) {
            foreach($data[$key] as $data_prop) {
                $this->mapRoleProperty($person, $role, $data_prop);
            }
        }

        // copy input errors
        if ($data['deleteFlag'] != "delete") {
            foreach($role->getInputError() as $r_e) {
                $person->getItem()->getInputError()->add($r_e);
            }
        }

        return $role;
    }

    /**
     *
     */
    private function setInstitution($role, $inst_name, $inst_type_id) {
        $dioceseRepository = $this->entityManager->getRepository(Diocese::class);
        $institutionRepository = $this->entityManager->getRepository(Institution::class);

        $inst_type_id = intval($inst_type_id);
        $institution = null;
        $diocese = null;
        if ($inst_name != "") {
            if ($inst_type_id == 1) { // diocese
                $role->setInstitution(null);
                $role->setInstitutionName(null);
                $diocese = $dioceseRepository->findOneByName($inst_name);
                if (is_null($diocese)) {
                    $msg = "Das Bistum '{$inst_name}' ist nicht in der Liste der Bistümer eingetragen.";
                    $role->getInputError()->add(new InputError('role', $msg, 'warning'));
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
                ]);
                // dd($inst_name, $inst_type_id, $query_result);
                if (count($query_result) < 1) {
                    $msg = "'{$inst_name}' ist nicht in der Liste der Klöster/Domstifte eingetragen.";
                    $role->getInputError()->add(new InputError('role', $msg, 'warning'));
                    $role->setInstitution(null);
                    $role->setInstitutionName($inst_name);
                } else {
                    $institution = $query_result[0];
                    $role->setInstitution($institution);
                    $role->setInstitutionName($institution->getName());
                }
            }
        }
        return $role;
    }

    /**
     *
     */
    private function mapRoleProperty($person, $role, $data) {
        $rolePropertyRepository = $this->entityManager->getRepository(PersonRoleProperty::class);
        $id = $data['id'];

        // the property entry is considered empty if no value is set
        $key_list = ['value'];
        $no_data = UtilService::no_data($data, $key_list);
        $roleProperty = null;

        // new roleProperty
        if ($no_data) {
            return null;
        } else {
            $roleProperty = new PersonRoleProperty();
            $role->getRoleProperty()->add($roleProperty);
            $roleProperty->setPersonRole($role);
            $roleProperty->setPersonId($person->getId());
        }

        // set data
        UtilService::setByKeys($roleProperty, $data, ['deleteFlag']);

        $property_type = $this->entityManager->getRepository(ItemPropertyType::class)
                                             ->find($data['type']);
        $roleProperty->setPropertyTypeId($property_type->getId());
        $roleProperty->setType($property_type);

        // case of completely missing data see above
        if (trim($data['value']) == "") {
            $msg = "Das Feld 'Attribut-Wert' darf nicht leer sein.";
            $person->getItem()->getInputError()->add(new InputError('role', $msg));
        } else {
            $roleProperty->setValue($data['value']);
        }

        return $roleProperty;
    }

    /**
     * fill person's references with $data
     */
    private function mapReference($item, $data) {
        $referenceRepository = $this->entityManager->getRepository(ItemReference::class);
        $volumeRepository = $this->entityManager->getRepository(ReferenceVolume::class);

        $key_list = ['volume', 'page', 'idInReference'];
        $no_data = UtilService::no_data($data, $key_list);
        $reference = null;

        if ($no_data) {
            return null;
        } else {
            $reference = new ItemReference();
            $item->getReference()->add($reference);
            $reference->setItem($item);
        }

        // set data
        $volume_name = trim($data['volume']);
        $reference->setVolumeTitleShort($volume_name); # save data for the form

        if ($volume_name != "") {
            $volume_query_result = $volumeRepository->findByTitleShort($volume_name);
            if ($volume_query_result) {
                $volume = $volume_query_result[0];
                $reference->setReferenceId($volume->getReferenceId());
            } else {
                $error_msg = "Keinen Band für '".$volume_name."' gefunden.";
                $item->getInputError()->add(new InputError('reference', $error_msg));
            }
        } else {
            $error_msg = "Das Feld 'Bandtitel' darf nicht leer sein.";
            $item->getInputError()->add(new InputError('reference', $error_msg));
        }

        $key_list = ['deleteFlag', 'page','idInReference'];
        UtilService::setByKeys($reference, $data, $key_list);

        return $reference;
    }

    /**
     * initialize itemCorpus if necessary
     */
    public function initItemCorpusMayBe($item) {
        $itemCorpusRepository = $this->entityManager->getRepository(ItemCorpus::class);
        $corpusRepository = $this->entityManager->getRepository(Corpus::class);

        foreach ($item->getItemCorpus() as $ic) {
            $corpus_id = $ic->getCorpusId();
            if ($ic->getIdInCorpus() == "") {
                $id_in_corpus = intval($itemCorpusRepository->findMaxIdInCorpus($corpus_id)) + 1;
                $id_in_corpus = strval($id_in_corpus);
                $ic->setIdInCorpus($id_in_corpus);
            }

            if ($ic->getIdPublic() == "") {
                $corpus = $corpusRepository->findOneByCorpusId($corpus_id);
                $mask = $corpus->getIdPublicMask();
                $next_id = $corpus->getNextIdPublic();

                $id_public = EditService::makeIdPublic($mask, $next_id);
                $ic->setIdPublic($id_public);

                $corpus->setNextIdPublic($next_id + 1);
            }
        }
    }

    /**
     * set corpus, copy data
     */
    private function mapItemCorpus($item, $corpus_id, $data) {
        $item_corpus = new ItemCorpus();
        $item_corpus->setItem($item);
        $item->getItemCorpus()->add($item_corpus);
        $item_corpus->setCorpusId($corpus_id);

        if (array_key_exists('idInCorpus', $data)) {
            $item_corpus->setIdInCorpus($data['idInCorpus']);
        } else {
            $item_corpus->setIdInCorpus("");
        }
        if (array_key_exists('idPublic', $data)) {
            $item_corpus->setIdPublic($data['idPublic']);
        } else {
            $item_corpus->setIdPublic("");
        }

        return $item_corpus;
    }


    private function mapItemProperty($item, $data, $person) {

        $id = $data['id'];
        // the property entry is considered empty if no value is set
        $key_list = ['value'];
        $no_data = UtilService::no_data($data, $key_list);
        $itemProperty = null;

        // new itemProperty
        if ($no_data) {
            return null;
        } else {
            $itemProperty = new ItemProperty();
            $itemProperty->setItem($item);
            $item->getItemProperty()->add($itemProperty);
        }

        // set data
        UtilService::setByKeys($itemProperty, $data, ['deleteFlag']);

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
     * fill url external with $data
     */
    private function mapUrlExternal($item, $data) {
        $urlExternalRepository = $this->entityManager->getRepository(UrlExternal::class);
        $authorityRepository = $this->entityManager->getRepository(Authority::class);

        $url_external_list = $item->getUrlExternal();
        $url_external = null;
        $value = is_null($data['value']) ? null : trim($data['value']);
        if (is_null($value) || $value == "") {
            return $url_external;
            } else {
            $authority_name = $data["urlName"];
            $auth_query = $authorityRepository->findByUrlNameFormatter($authority_name);
            if (!is_null($auth_query) && count($auth_query) > 0) {
                $authority = $auth_query[0];
                // remove base URL in $value if present
                if ($authority_name == 'Wikipedia-Artikel') {
                    $val_list = explode('/', $value);
                    $value = array_slice($val_list, -1)[0];
                }

                $url_external = $this->makeUrlExternal($item, $authority);
                $key_list = ['deleteFlag', 'value', 'note'];
                UtilService::setByKeys($url_external, $data, $key_list);

                $url_external_list->add($url_external);

                // validate: avoid merge separator
                $separator = "|";
                if (str_contains($value, $separator)) {
                    $msg = "Eine externe ID enthält '".$separator."'.";
                    $item->getInputError()->add(new InputError('external id', $msg));
                }
            } else {
                $msg = "Keine eindeutige Institution für '".$authority_name."' gefunden.";
                $item->getInputError()->add(new InputError('external id', $msg));
            }
        }

        return $url_external;
    }

    /**
     * update $person with data in $person_gso
     */
    public function updateFromGso($person, $person_gso, $current_user_id) {
        $userWiagRepository = $this->entityManager->getRepository(UserWiag::class);
        // item
        $item = $person->getItem();

        $user = $userWiagRepository->find($current_user_id);
        $item->updateChangedMetaData($user);

        $edit_status = $person_gso->getItem()->getStatus();
        $item->setEditStatus($edit_status);

        // item property: no data in $person_gso

        // reference
        $this->copyReferenceFromGso($person, $person_gso);

        // GND
        $this->copyGndFromGso($item, $person_gso);

        // core
        $this->copyCoreFromGso($person, $person_gso);

        // name variants
        // - function call is the same for form data
        $givenname_variants = $person_gso->getVornamenVarianten();
        $familyname_variants = $person_gso->getFamiliennamenVarianten();
        self::mapNameVariants($person, $givenname_variants, $familyname_variants);
        // call of persist can not be part of mapNameVariants
        foreach ($person->getGivennameVariants() as $gnv) {
            $this->entityManager->persist($gnv);
        }
        foreach ($person->getFamilynameVariants() as $fnv) {
            $this->entityManager->persist($fnv);
        }

        // roles
        $this->mapRoleFromGso($person, $person_gso);
        $this->updateDateRange($person);

        return $person;
    }


    /**
     * clear $target_list; copy collection $source_list to $target_list;
     */
    private function copyReferenceFromGso($person, $person_gso) {
        $volumeRepository = $this->entityManager->getRepository(ReferenceVolume::class);

        $ref_list_gso = $person_gso->getItem()->getReference();
        $ref_list = $person->getItem()->getReference();


        // - remove entries
        foreach ($ref_list as $r) {
            $ref_list->removeElement($r);
            $r->setItem(null);
            $this->entityManager->remove($r);
        }

        // - set new entries
        foreach ($ref_list_gso as $ref_gso) {
            $page = $ref_gso->getSeiten();
            $is_bio = str_contains($page, "<b>");
            if ($is_bio) {
                $gs_volume_nr = $ref_gso->getReferenceVolume()->getNummer();
                $vol = $volumeRepository->findOneByGsVolumeNr($gs_volume_nr);

                if (!is_null($vol)) {
                    $ref = new ItemReference();
                    $ref->setReferenceId($vol->getReferenceId());
                    $ref->setPage($page);

                    $ref_list->add($ref);
                    $ref->setItem($person->getItem());
                    $this->entityManager->persist($ref);
                } else {
                    // dump("Fehlende Band-Nummer: ", $gs_volume_nr);
                    // 2023-10-31 TODO Vor dem Import auf fehlende Literatur prüfen,
                    // und/oder den gesamten Datensatz nicht aktualisieren.
                }
            }
        }

        return count($ref_list);

    }

    private function copyGndFromGso($item, $person_gso) {
        $authorityRepository = $this->entityManager->getRepository(Authority::class);

        // - remove entries, but not GSN
        $target_uext = $item->getUrlExternal();
        foreach ($target_uext as $t) {
            if ($t->getAuthorityId() != Authority::ID['GSN']) {
                $target_uext->removeElement($t);
                $t->setItem(null);
                $this->entityManager->remove($t);
            }
        }

        $count_url = 0;
        $gnd = $person_gso->getGndnummer();
        if (!is_null($gnd) and trim($gnd) != "") {
            $uext = new UrlExternal();

            $uext->setItem($item);
            $authority_gnd = $authorityRepository->find(Authority::ID['GND']);
            $uext->setAuthority($authority_gnd); // sets authorityId
            $uext->setValue($gnd);
            $item->getUrlExternal()->add($uext);
            $this->entityManager->persist($uext);

            $count_url += 1;
        }

        return $count_url;
    }

    public function setGsn($item, $gsn) {
        $authorityRepository = $this->entityManager->getRepository(Authority::class);

        if (!is_null($gsn) and trim($gsn) != "") {
            $uext = new UrlExternal();

            $uext->setItem($item);
            $authority_gs = $authorityRepository->find(Authority::ID['GSN']);
            $uext->setAuthority($authority_gs);
            $uext->setValue($gsn);
            $item->getUrlExternal()->add($uext);
            $this->entityManager->persist($uext);
        }
    }


    private function copyCoreFromGso($person, $person_gso) {
        $field_list = [
            ['givenname', 'vorname'],
            ['familyname', 'familienname'],
            ['prefixname', 'namenspraefix'],
            ['dateBirth', 'geburtsdatum'],
            ['dateDeath', 'sterbedatum'],
            ['noteName', 'namenszusatz'],
            ['academicTitle', 'titel'],
            ['notePerson', 'anmerkungen'],
        ];

        foreach ($field_list as $field) {
            $get_fnc = 'get'.ucfirst($field[1]);
            $set_fnc = 'set'.ucfirst($field[0]);
            $person->$set_fnc($person_gso->$get_fnc());
        }

        // numerical values for dates
        self::setNumDates($person);

        return $person;
    }

    /**
     *
     */
    private function mapRoleFromGso($person, $person_gso) {
        $roleRepository = $this->entityManager->getRepository(PersonRole::class);
        $roleRoleRepository = $this->entityManager->getRepository(Role::class);

        // clear roles in $person
        $role_list = $person->getRole();
        foreach($role_list as $r) {
            $r_prop = $r->getRoleProperty();
            // $r_prop should always be empty
            foreach($r_prop as $r_p) {
                $r_prop->removeElement($r_p);
                $this->entityManager->remove($r_p);
            }
            $role_list->removeElement($r);
            $r->setPerson(null);
            $this->entityManager->remove($r);
        }

        $role_list_gso = $person_gso->getRole();
        $count_role = 0;
        foreach ($role_list_gso as $role_gso) {
            if ($role_gso->isEmpty() or $role_gso->isDeleted()) {
                continue;
            }

            $role = new PersonRole();
            $person->getRole()->add($role);
            $role->setPerson($person);

            $this->fillRoleFromGso($person->getItem()->getInputError(), $role, $role_gso);
            $this->entityManager->persist($role);

            $count_role += 1;
        }

        return $count_role;
    }

    /**
     * parse data in $role_gso and fill $role
     */
    private function fillRoleFromGso($inputError, $role, $role_gso) {

        $field_list = [
            ['roleName', 'bezeichnung'],
            ['dioceseName', 'dioezese'],
            ['dateBegin', 'von'],
            ['dateEnd', 'bis'],
            ['note', 'anmerkung']
        ];

        foreach ($field_list as $field) {
            $get_fnc = 'get'.ucfirst($field[1]);
            $set_fnc = 'set'.ucfirst($field[0]);
            $role->$set_fnc($role_gso->$get_fnc());
        }

        $role->setUncertain(0);

        $roleRepository = $this->entityManager->getRepository(Role::class);
        $role_name = $role->getRoleName();
        $role_role = $roleRepository->findOneByName($role_name);
        if ($role_role) {
            $role->setRole($role_role);
        } else {
            $role->setRole(null);
            $msg = "Das Amt '{$role_name}' ist nicht in der Liste der Ämter eingetragen.";
            $inputError->add(new InputError('role', $msg, 'warning'));
        }

        // set institution
        $institutionRepository = $this->entityManager->getRepository(Institution::class);
        $institution_id_gsn = $role_gso->getKlosterid();
        $institution = $institutionRepository->findOneByIdGsn($institution_id_gsn);
        if (!is_null($institution)) {
            $role->setInstitution($institution);
        }

        // set diocese
        $dioceseRepository = $this->entityManager->getRepository(Diocese::class);
        $diocese_name = $role->getDioceseName();
        if (!is_null($diocese_name) and trim($diocese_name) != "") {
            $diocese = $dioceseRepository->findOneByName($role->getDioceseName());
            if (!is_null($diocese)) {
                $role->setDiocese($diocese);
            }
        }

        // numerical values for dates
        $date_begin = $role->getDateBegin();
        if (!$this->emptyDate($date_begin)) {
            $year = UtilService::parseDate($date_begin, 'lower');
            if (!is_null($year)) {
                $role->setNumDateBegin($year);
            } else {
                $msg = "Keine gültige Datumsangabe in '".$date_begin."' gefunden.";
                $inputError->add(new InputError('role', $msg, 'warning'));
            }
        } else {
            $role->setNumDateBegin(null);
        }

        $date_end = $role->getDateEnd();

        if (!$this->emptyDate($date_end)) {
            $year = UtilService::parseDate($date_end, 'upper');
            if (!is_null($year)) {
                $role->setNumDateEnd($year);
            } else {
                $msg = "Keine gültige Datumsangabe in '".$date_end."' gefunden.";
                $inputError->add(new InputError('role', $msg, 'warning'));
            }
        } else {
            $role->setNumDateEnd(null);
        }

        // sort key
        $sort_key = UtilService::SORT_KEY_MAX;
        if (!$this->emptyDate($date_begin)) {
            $sort_key = UtilService::sortKeyVal($date_begin);
        } elseif (!$this->emptyDate($date_end)) {
            $sort_key = UtilService::sortKeyVal($date_end);
        }

        // - we got a parse result or both, $date_begin and $date_end are empty
        $role->setDateSortKey($sort_key);

        // free role properties are not present in GSO

        return $role;
    }

}
