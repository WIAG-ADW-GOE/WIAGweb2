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

class EditPersonService {
    const URL_KLOSTERDATENBANK = 'https://klosterdatenbank.adw-goe.de/gsn/';

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


    /**
     * map/validate content of form
     *
     * @return list of persons containing the data in $form_data
     */
    public function mapFormData($item_type_id, $form_data) {

        $person_list = array();

        foreach($form_data as $data) {
            $id = $data['id'];
            // skip blank forms
            if ($id == 0 && !isset($data['item']['formIsEdited'])) {
                continue;
            }
            $item = new Item();
            $item->setId($id);
            $item->setItemTypeId($item_type_id);
            $person = new Person();
            $person->setItem($item); // sets ID

            $this->mapAndValidatePerson($person, $data);

            // set form collapse state
            $expanded_param = isset($data['item']['formIsExpanded']) ? 1 : 0;
            $person->getItem()->setFormIsExpanded($expanded_param);

            $person_list[] = $person;
        }

        return $person_list;
    }

    /**
     * update $target with data in $person
     */
    public function update($target, $person, $current_user_id) {

        $this->copyItem($target, $person, $current_user_id);

        // item property
        $target_item = $target->getItem();
        $target_prop = $target_item->getItemProperty();
        $source_prop = $person->getItem()->getItemProperty();
        $this->setItemAttributeList($target_item, $target_prop, $source_prop);
        // reference
        $target_ref = $target_item->getReference();
        $source_ref = $person->getItem()->getReference();
        $this->setItemAttributeList($target_item, $target_ref, $source_ref);
        // id external
        $target_ref = $target_item->getIdExternal();
        $source_ref = $person->getItem()->getIdExternal();
        $this->setItemAttributeList($target_item, $target_ref, $source_ref);

        // merging?
        if ($target_item->getMergeStatus() == 'merging') {
            $this->updateMergeMetaData($target, $person);
        }

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
        foreach($person->getInputError() as $e) {
            $target->getInputError()->add($e);
        }

        // roles
        $this->setRole($target, $person);

        $expanded = $person->getItem()->getFormIsExpanded();
        $target->getItem()->setFormIsExpanded($expanded);

    }

    /**
     * copy collection $source_list to $target_list
     */
    private function setItemAttributeList($target, $target_list, $source_list) {

        // - remove entries
        // $target_ref = $target->getItem()->getReference();
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
    private function copyItem($target, $source, $current_user_id) {
        $field_list = [
            'editStatus',
            'commentDuplicate',
            'mergeStatus',
        ];

        foreach ($field_list as $field) {
            $get_fnc = 'get'.ucfirst($field);
            $set_fnc = 'set'.ucfirst($field);
            $target->getItem()->$set_fnc($source->getItem()->$get_fnc());
        }

        $this->updateChangedMetaData($target->getItem(), $current_user_id);
    }

    private function copyCore($target, $source) {
        $field_list = [
            'givenname',
            'familyname',
            'prefixname',
            'dateBirth',
            'dateDeath',
            'comment',
            'noteName',
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

    private function emptyDate($s) {
        return (is_null($s) || $s == '?' || $s == 'unbekannt');
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

    /**
     * compose ID public
     */
    public function makeIdPublic($item_type_id, $numeric_part)  {

        $width = Item::ITEM_TYPE[$item_type_id]['numeric_field_width'];
        $numeric_field = str_pad($numeric_part, $width, "0", STR_PAD_LEFT);

        $mask = Item::ITEM_TYPE[$item_type_id]['id_public_mask'];
        $id_public = str_replace("#", $numeric_field, $mask);
        return $id_public;
    }

    /**
     * create IdExternal object
     */
    public function makeIdExternal($item, $authority) {
        $id_external = new IdExternal();
        if (!is_null($item->getId())) {
            $id_external->setItemId($item->getId());
        }
        $id_external->setItem($item);
        $id_external->setAuthorityId($authority->getId());
        $id_external->setAuthority($authority);
        return $id_external;
    }

    /**
     * create Person object
     */
    public function makePerson($item_type_id, $user_wiag_id) {
        $item = Item::newItem($item_type_id, $user_wiag_id);
        $person = Person::newPerson($item);
        $edit_status_default = Item::ITEM_TYPE[$item_type_id]['edit_status_default'];
        $person->getItem()->setEditStatus($edit_status_default);

        return $person;
    }

    /**
     * create person object and persist
     */
    public function initMetaData($person, $item_type_id, $user_wiag_id) {

        $item = $person->getItem();
        $item->setItemTypeId($item_type_id);
        $person->setItemTypeId($item_type_id);

        $now_date = new \DateTimeImmutable('now');
        $item->setCreatedBy($user_wiag_id);
        $item->setDateCreated($now_date);

        // redundant but neccessary to meet DB constraints
        $this->updateChangedMetaData($item, $user_wiag_id);

        $itemRepository = $this->entityManager->getRepository(Item::class);
        $id_in_source = intval($itemRepository->findMaxIdInSource($item_type_id)) + 1;
        $id_in_source = strval($id_in_source);
        $item->setIdInSource($id_in_source);

        $id_public = $this->makeIdPublic($item_type_id, $id_in_source);
        $item->setIdPublic($id_public);

        return $person;
    }

    /**
     * updateMergeMetaData($person)
     *
     * find merged entities; update merge meta data
     */
    public function updateMergeMetaData($target, $person) {
        $parent_id = $person->getItem()->getMergeParent();

        $itemRepository = $this->entityManager->getRepository(Item::class);

        $parent_list = array();
        foreach($parent_id as $p_id) {
            $parent_list[] = $itemRepository->find($p_id);
        }

        // child
        $target->getItem()->setMergeStatus('child');
        $target->getItem()->setIdPublic($parent_list[0]->getIdPublic());
        // parents
        $child_id = $target->getId();
        foreach ($parent_list as $item) {
            $item->setMergedIntoId($child_id);
            $item->setMergeStatus('parent');
            $item->setIsOnline(0);
        }
    }

    /**
     * updateEditMetaData($item, $edit_status, $user_wiag_id, $child_id)
     *
     * update meta data for $item
     */
    public function updateChangedMetaData($item, $user_wiag_id) {

        $now_date = new \DateTimeImmutable('now');
        $item->setChangedBy($user_wiag_id);
        $item->setDateChanged($now_date);
        return($item);
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
                $person->getInputError()->add(new InputError('name', $msg));
            }
        }
    }

    /**
     * map content of $data to $obj_list
     */
    public function mapAndValidatePerson($person, $data) {
        // item
        $item = $person->getItem();

        $edit_status = trim($data['item']['editStatus']);
        if ($edit_status == "") {
            $msg = "Das Feld 'Status' darf nicht leer sein.";
            $person->getInputError()->add(new InputError('status', $msg));
        }

        // item: checkboxes
        $key_list = ['formIsEdited'];
        foreach($key_list as $key) {
            $set_fnc = 'set'.ucfirst($key);
            $item->$set_fnc(isset($data['item'][$key]));
        }

        // copy meta data even if they are still empty (new entry)
        $item->setIdPublic($data['item']['idPublic']);
        $item->setIdInSource($data['item']['idInSource']);

        // item: status values, editorial notes
        $key_list = ['editStatus', 'mergeStatus', 'changedBy', 'commentDuplicate'];
        UtilService::setByKeys($item, $data['item'], $key_list);

        $collect_merge_parent = array();
        if (array_key_exists('mergeParent', $data['item'])) {
            foreach ($data['item']['mergeParent'] as $parent_id) {
                $collect_merge_parent[] = $parent_id;
            }
        }
        $item->setMergeParent($collect_merge_parent);

        if (isset($data['item']['dateChanged'])) {
            $dateChanged = new \DateTimeImmutable($data['item']['dateChanged']);
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
                     'notePerson'];
        UtilService::setByKeys($person, $data, $key_list);

        // add error if $needle occurs in one of the fields
        $needle = Item::JOIN_DELIM;
        $this->complainSubstring($person, $key_list, Item::JOIN_DELIM);

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
            'role'  => 'mapRole',
            'ref'   => 'mapReference',
            'prop'  => 'mapItemProperty',
            'idext' => 'mapIdExternal'
        ];

        foreach($section_map as $key => $mapFunction) {
            if (array_key_exists($key, $data)) {
                foreach($data[$key] as $data_loop) {
                    $this->$mapFunction($person, $data_loop);
                }
            }
        }

        // date min/date max
        $this->updateDateRange($person);

        // validation
        if ($item->getIsOnline() && $item->getIsDeleted()) {
            $msg = "Der Eintrag kann nicht gleichzeitig online und gelöscht sein.";
            $person->getInputError()->add(new InputError('status', $msg));
        }

        return $person;
    }

    /**
     * map name variants do not mark them as persistent
     */
    private function mapNameVariants($person, $data) {
        // givenname
        $gnv_data = trim($data['givennameVariants']);
        $person->setFormGivennameVariants($gnv_data);
        $person_id = $person->getId();

        // - remove entries; not neccessary since $person is empty
        $person_gnv = $person->getGivennameVariants();

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
            }
        }

        // familyname
        $fnv_data = trim($data['familynameVariants']);
        $person->setFormFamilynameVariants($fnv_data);

        // - remove entries; not neccessary since $person is empty
        $person_fnv = $person->getFamilynameVariants();

        // - set new entries
        $fnv_list = explode(',', $fnv_data);
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
        $no_data = $this->utilService->no_data($data, $key_list);

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
        } else {
            $role->setRole(null);
            $msg = "Das Amt '{$role_name}' ist nicht in der Liste der Ämter eingetragen.";
            $person->getInputError()->add(new InputError('role', $msg, 'error'));
        }
        $role->setRoleName($role_name);

        // set institution or diocese
        $input_error = $person->getInputError();
        $inst_name = substr(trim($data['institution']), 0, 255);
        $inst_type_id = $data['instTypeId'];
        if ($inst_name != "") {
            $this->setInstitution($role, $input_error, $inst_name, $inst_type_id);
        }

        // other fields
        $role_uncertain = isset($data['uncertain']) ? 1 : 0;
        $role->setUncertain($role_uncertain);

        $data['note'] = substr(trim($data['note']), 0, 1023);
        UtilService::setByKeys($role, $data, ['deleteFlag', 'note', 'dateBegin', 'dateEnd']);

        // numerical values for dates
        $date_begin = $role->getDateBegin();
        if (!$this->emptyDate($date_begin)) {
            $year = $this->utilService->parseDate($date_begin, 'lower');
            if (!is_null($year)) {
                UtilService::setByKeys($role, $data, ['dateBegin']);
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
                UtilService::setByKeys($role, $data, ['dateEnd']);
                $role->setNumDateEnd($year);
            } else {
                $msg = "Keine gültige Datumsangabe in '".$date_end."' gefunden.";
                $person->getInputError()->add(new InputError('role', $msg));
            }
        } else {
            $role->setNumDateEnd(null);
        }

        // sort key
        $sort_key = UtilService::SORT_KEY_MAX;
        if (!$this->emptyDate($date_begin)) {
            $sort_key = $this->utilService->sortKeyVal($date_begin);
        } elseif (!$this->emptyDate($date_end)) {
            $sort_key = $this->utilService->sortKeyVal($date_end);
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

        return $role;
    }

    /**
     *
     */
    private function setInstitution($role, $input_error, $inst_name, $inst_type_id) {
        $dioceseRepository = $this->entityManager->getRepository(Diocese::class);
        $institutionRepository = $this->entityManager->getRepository(Institution::class);

        $institution = null;
        $diocese = null;
        if ($inst_name != "") {
            $role->setInstitutionTypeId($inst_type_id);
            if ($inst_type_id == 1) { // diocese
                $role->setInstitution(null);
                $role->setInstitutionName(null);
                $diocese = $dioceseRepository->findOneByName($inst_name);
                if (is_null($diocese)) {
                    $msg = "Das Bistum '{$inst_name}' ist nicht in der Liste der Bistümer eingetragen.";
                    $input_error->add(new InputError('role', $msg, 'warning'));
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
                    $inputError->add(new InputError('role', $msg, 'warning'));
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
        $no_data = $this->utilService->no_data($data, $key_list);
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

        $key_list = ['deleteFlag', 'page','idInReference'];
        UtilService::setByKeys($reference, $data, $key_list);

        return $reference;
    }

    private function mapItemProperty($person, $data) {

        $id = $data['id'];
        $item = $person->getItem();

        // the property entry is considered empty if no value is set
        $key_list = ['value'];
        $no_data = $this->utilService->no_data($data, $key_list);
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
     * fill id external with $data
     */
    private function mapIdExternal($person, $data) {
        $idExternalRepository = $this->entityManager->getRepository(IdExternal::class);
        $authorityRepository = $this->entityManager->getRepository(Authority::class);

        $item = $person->getItem();
        $id_external_list = $item->getIdExternal();
        $id_external = null;
        $value = is_null($data['value']) ? null : trim($data['value']);
        if (is_null($value) || $value == "") {
            return $id_external;
            } else {
            $authority_name = $data["urlName"];
            $auth_query = $authorityRepository->findByUrlNameFormatter($authority_name);
            if (!is_null($auth_query) && count($auth_query) > 0) {
                $authority = $auth_query[0];
                // drop base URL if present
                if ($authority_name == 'Wikipedia-Artikel') {
                    $val_list = explode('/', $value);
                    $value = array_slice($val_list, -1)[0];
                }

                $id_external = $this->makeIdExternal($item, $authority);
                UtilService::setByKeys($id_external, $data, ['deleteFlag', 'value']);

                $id_external_list->add($id_external);

                // validate: avoid merge separator
                $separator = "|";
                if (str_contains($value, $separator)) {
                    $msg = "Eine externe ID enthält '".$separator."'.";
                    $person->getInputError()->add(new InputError('reference', $msg));
                }
            } else {
                $msg = "Keine Institution für '".$authority_name."' gefunden.";
                $person->getInputError()->add(new InputError('reference', $msg));
            }
        }

        return $id_external;
    }

}
