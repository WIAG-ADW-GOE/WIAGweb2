<?php

namespace App\Entity;

use App\Entity\Item;
use App\Entity\PersonRole;
use App\Entity\UrlExternal;
use App\Repository\PersonRepository;
use App\Service\UtilService;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity(repositoryClass=PersonRepository::class)
 */
class Person {

    const EDIT_FIELD_LIST = [
        'givenname' => 'Vorname',
        'familyname' => 'Familienname',
        'dateBirth' => 'geboren',
        'dateDeath' => 'gestorben',
        'comment' => 'Kommentar (red.)',
        'noteName' => 'Namenszusätze',
        'notePerson' => 'Bemerkung zur Person (online)'
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="Item", cascade={"persist"})
     * @ORM\JoinColumn(name="id", referencedColumnName="id")
     */
    private $item;

    /**
     * @ORM\OneToMany(targetEntity="PersonRole", mappedBy="person", fetch="EAGER")
     * @ORM\JoinColumn(name="id", referencedColumnName="person_id")
     * @ORM\OrderBy({"dateSortKey" = "ASC"})
     */
    private $role;

    /**
     * @ORM\OneToMany(targetEntity="GivennameVariant", mappedBy="person")
     * @ORM\JoinColumn(name="id", referencedColumnName="person_id")
     */
    private $givennameVariants;

    /**
     * @ORM\OneToMany(targetEntity="FamilynameVariant", mappedBy="person")
     * @ORM\JoinColumn(name="id", referencedColumnName="person_id")
     */
    private $familynameVariants;

    /**
     * @ORM\OneToMany(targetEntity="PersonBirthplace", mappedBy="person")
     * @ORM\JoinColumn(name="id", referencedColumnName="person_id")
     * @ORM\OrderBy({"weight" = "DESC"})
     */
    private $birthplace;

    /**
     * @ORM\Column(type="string", length=1023, nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="string", length=1023, nullable=true)
     */
    private $notePerson;

    /**
     * @ORM\Column(type="string", length=127)
     */
    private $givenname;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private $familyname;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $prefixname;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $noteName;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $academicTitle;


    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $religiousOrderId;

    /**
     * @ORM\OneToOne(targetEntity="ReligiousOrder")
     */
    private $religiousOrder;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $dateBirth;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $dateDeath;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $dateMin;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $dateMax;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $noteDates;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $numDateBirth;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $numDateDeath;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $itemTypeId;

    /**
     * data from alternative source (canon)
     */
    private ?Person $sibling = null;

    /**
     * collection of InputError
     */
    private $inputError;

    /**
     * no DB-mapping
     * hold form input data
     */
    private $formGivennameVariants;

    /**
     * no DB-mapping
     * hold form input data
     */
    private $formFamilynameVariants;

    /**
     * no DB-mapping
     * hold IDs of other persons
     */
    private $seeAlso;


    public function __construct() {
        $this->givennameVariants = new ArrayCollection();
        $this->familynameVariants = new ArrayCollection();
        $this->nameLookup = new ArrayCollection();
        $this->birthPlace = new ArrayCollection();
        $this->inputError = new ArrayCollection();
        $this->role = new ArrayCollection();
        $this->seeAlso = new ArrayCollection();
    }

    static public function newPerson(Item $item) {
        $person = new Person();
        $person->setItem($item);
        $person->setItemTypeId($item->getItemTypeId());
        $person->setIsNew = true;
        return $person;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setItem($item) {
        $this->item = $item;
        $this->id = $item->getId();
        return $this;
    }

    public function getItem() {
        return $this->item;
    }

    public function getRole() {
        return $this->role;
    }

    public function setRole($role) {
       $this->role = $role;
       return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getNotePerson(): ?string
    {
        return $this->notePerson;
    }

    public function setNotePerson(?string $notePerson): self
    {
        $this->notePerson = $notePerson;

        return $this;
    }

    public function getGivenname(): ?string
    {
        return $this->givenname;
    }

    public function setGivenname(?string $givenname): self
    {
        $this->givenname = $givenname;

        return $this;
    }

    public function getFamilyname(): ?string
    {
        return $this->familyname;
    }

    public function setFamilyname(?string $familyname): self
    {
        $this->familyname = $familyname;

        return $this;
    }

    public function getPrefixname(): ?string
    {
        return $this->prefixname;
    }

    public function setPrefixname(?string $prefixname): self
    {
        $this->prefixname = $prefixname;

        return $this;
    }

    public function getNoteName(): ?string
    {
        return $this->noteName;
    }

    public function setNoteName(?string $noteName): self
    {
        $this->noteName = $noteName;

        return $this;
    }

    public function getAcademicTitle(): ?string
    {
        return $this->academicTitle;
    }

    public function setAcademicTitle(?string $title): self
    {
        $this->academicTitle = $title;

        return $this;
    }

    public function getReligiousOrderId(): ?int
    {
        return $this->religiousOrderId;
    }

    public function setReligiousOrderId(?int $religiousOrderId): self
    {
        $this->religiousOrderId = $religiousOrderId;

        return $this;
    }

    public function getReligiousOrder() {
        return $this->religiousOrder;
    }


    public function getDateBirth(): ?string
    {
        return $this->dateBirth;
    }

    public function setDateBirth(?string $dateBirth): self
    {
        $this->dateBirth = $dateBirth;

        return $this;
    }

    public function getDateDeath(): ?string
    {
        return $this->dateDeath;
    }

    public function setDateDeath(?string $dateDeath): self
    {
        $this->dateDeath = $dateDeath;

        return $this;
    }

    public function getDateMin(): ?int
    {
        return $this->dateMin;
    }

    public function setDateMin(?int $dateMin): self
    {
        $this->dateMin = $dateMin;

        return $this;
    }

    public function getDateMax(): ?int
    {
        return $this->dateMax;
    }

    public function setDateMax(?int $dateMax): self
    {
        $this->dateMax = $dateMax;

        return $this;
    }

    public function getNoteDates(): ?string
    {
        return $this->noteDates;
    }

    public function setNoteDates(?string $noteDates): self
    {
        $this->noteDates = $noteDates;

        return $this;
    }

    public function getNumDateBirth(): ?int
    {
        return $this->numDateBirth;
    }

    public function setNumDateBirth(?int $numDateBirth): self
    {
        $this->numDateBirth = $numDateBirth;

        return $this;
    }

    public function getNumDateDeath(): ?int
    {
        return $this->numDateDeath;
    }

    public function setNumDateDeath(?int $numDateDeath): self
    {
        $this->numDateDeath = $numDateDeath;

        return $this;
    }

    public function getCanon() {
        return $this->canon;
    }

    public function getPersonGs() {
        return $this->personGs;
    }

    public function getDisplayname() {
        $prefixpart = strlen($this->prefixname) > 0 ? ' '.$this->prefixname : '';
        $familypart = strlen($this->familyname) > 0 ? ' '.$this->familyname : '';
        return $this->givenname.$prefixpart.$familypart;
    }

    public function getGivennameVariants() {
        return $this->givennameVariants;
    }

    public function getFamilynameVariants() {
        return $this->familynameVariants;
    }

    public function getBirthplace() {
        return $this->birthplace;
    }

    public function getItemTypeId(): ?int
    {
        return $this->itemTypeId;
    }

    public function setItemTypeId(?int $itemTypeId): self
    {
        $this->itemTypeId = $itemTypeId;

        return $this;
    }

    public function setFormGivennameVariants($variants): self {
        $this->formGivennameVariants = $variants;
        return $this;
    }

    public function getFormGivenNameVariants(): ?string {
        if ($this->formGivennameVariants) {
            return $this->formGivennameVariants;
        }
        if ($this->givennameVariants) {
            return implode(', ', $this->givennameVariants->toArray());
        }
        return null;
    }

    public function setFormFamilynameVariants($variants): self {
        $this->formFamilynameVariants = $variants;
        return $this;
    }

    public function getFormFamilyNameVariants(): ?string {
        if ($this->formFamilynameVariants) {
            return $this->formFamilynameVariants;
        }
        if ($this->familynameVariants) {
            return implode(', ', $this->familynameVariants->toArray());
        }
        return null;
    }

    private function setIsNew($flag) {
        $this->isNew = $flag;
        return $this;
    }

    public function getIsNew() {
        return $this->isNew;
    }

    static private function concatData($a, $b) {
        if (is_null($a)) {
            return $b;
        }
        if (is_null($b)) {
            return $a;
        }
        if ($a == $b) {
            return $a;
        }
        return $a.'/'.$b;
    }

    /**
     * combine names, dates, ... from different sources
     */
    public function combine($field) {
        $getfnc = 'get'.ucfirst($field);

        if (is_null($this->sibling)) {
            return $this->$getfnc();
        }
        return $this->concatData($this->$getfnc(), $this->sibling->$getfnc());
    }

    /**
     * combine item properties
     */
    public function combineProperty($key) {
        if (is_null($this->sibling)) {
            return $this->item->itemPropertyValue($key);
        }
        $a = $this->item->itemPropertyValue($key);
        $b = $this->sibling->getItem()->itemPropertyValue($key);
        return $this->concatData($a, $b);
    }

    /**
     * combine complete item property list
     */
    public function combinePropertyList() {
        $a_prop_list = $this->item->arrayItemPropertyWithLabel();
        $b_prop_list = array();
        if ($this->sibling) {
            $b_prop_list = $this->sibling->getItem()->arrayItemPropertyWithLabel();
        }

        $key_list = array_merge(array_keys($a_prop_list), array_keys($b_prop_list));
        $key_list = array_unique($key_list);

        $prop_list = array();
        foreach($key_list as $key) {
            $a_value = array_key_exists($key, $a_prop_list) ? $a_prop_list[$key]['value'] : null;
            $b_value = array_key_exists($key, $b_prop_list) ? $b_prop_list[$key]['value'] : null;

            $entry['value'] = $this->concatData($a_value, $b_value);
            $label = array_key_exists($key, $a_prop_list) ? $a_prop_list[$key]['label'] : $b_prop_list[$key]['label'];
            $entry['label'] = $label;
            $prop_list[$key] = $entry;
        }

        return $prop_list;
    }


    public function getSibling(): ?Person {
        return $this->sibling;
    }

    public function setSibling($sibling): self {
        $this->sibling = $sibling;
        return $this;
    }

    /**
     * do not provide setInputError; use add or remove to manipulate this property
     */
    public function getInputError() {
        if (is_null($this->inputError)) {
            $this->inputError = new ArrayCollection;
        }
        return $this->inputError;
    }

    public function getSeeAlso() {
        return $this->seeAlso;
    }

    /**
     * concatenate name variants and comments
     */
    public function commentLine($flag_names = true) {

        $academic_title = $this->combine('academicTitle');

        $str_gn_variants = null;
        $str_fn_variants = null;
        if ($flag_names) {
            $givennameVariants = $this->getGivennameVariants();
            $familynameVariants = $this->getFamilynameVariants();

            $gn_variants = array ();
            foreach ($givennameVariants as $gn) {
                $gn_variants[] = $gn->getName();
            }
            $fn_variants = array ();
            foreach ($familynameVariants as $fn) {
                $fn_variants[] = $fn->getName();
            }

            if (!is_null($this->sibling)) {
                foreach ($this->sibling->getGivennameVariants() as $gn) {
                    $gn_variants[] = $gn->getName();
                }
                foreach ($this->sibling->getFamilynameVariants() as $fn) {
                    $fn_variants[] = $fn->getName();
                }

            }
            $gn_variants = array_unique($gn_variants);
            $fn_variants = array_unique($fn_variants);

            $str_gn_variants = $gn_variants ? implode(', ', $gn_variants) : null;
            $str_fn_variants = $fn_variants ? implode(', ', $fn_variants) : null;
        }

        $elt_cands = [
            $academic_title,
            $str_gn_variants,
            $str_fn_variants,
            $this->combine('noteName'),
            $this->combine('notePerson'),
        ];

        $property_list = $this->combinePropertyList();
        foreach ($property_list as $prop) {
            $elt_cands[] = $prop['label'].': '.$prop['value'];
        }

        $line_elts = array();
        foreach ($elt_cands as $elt) {
            if (!is_null($elt) && $elt != '') {
                $line_elts[] = $elt;
            }
        }

        $comment_line = null;
        if (count($line_elts) > 0) {
            $comment_line = implode('; ', $line_elts);
        }

        return $comment_line;
    }

    public function describe(): string {
        $description = $this->getDisplayname();

        $birth_info = $this->birthInfo();
        if($birth_info) {
            $description = $description.' ('.$birth_info.')';
        }

        return $description;
    }

    /**
     * get information about offices; $nOffice: number of offices
     */
    public function describeRole($nOffice = 3): ?string {

        $office_list = array();
        foreach(array_slice($this->role->toArray(), 0, $nOffice) as $role) {
            $office_list[] = $role->describe();
        }

        $description = null;
        if(count($office_list) > 0) {
            $description = implode(', ', $office_list);
        }

        return($description);

    }

    public function birthInfo(): ?string {
        $birth_info = null;
        if($this->dateBirth && $this->dateDeath) {
            $birth_info = '* '.$this->dateBirth.' † '.$this->dateDeath;
        } elseif($this->dateBirth) {
            $birth_info = '* '.$this->dateBirth;
        } elseif($this->dateDeath) {
            $birth_info = '† '.$this->dateDeath;
        }
        return $birth_info;
    }

    public function hasError($min_level): bool {
        // the database is not aware of inputError and it's type
        if (is_null($this->inputError)) {
            return false;
        }

        foreach($this->inputError as $e_loop) {
            $level = $e_loop->getLevel();
            if (in_array($level, InputError::ERROR_LEVEL[$min_level])) {
                return true;
            }
        }
        return false;
    }

    public function getFirstRoleSortKey() {
        $key = PersonRole::MAX_DATE_SORT_KEY;
        if (count($this->role) > 0) {
            if (is_array($this->role)) {
                $role_list = $this->role;
            } else {
                $role_list = $this->role->toArray();
            }
            $key = array_values($role_list)[0]->getDateSortKey();
        }
        return $key;
    }

    /**
     * read data from $parent_person_list
     */
    public function merge($parent_person_list) {
        $parent_item_list = array();
        foreach ($parent_person_list as $p) {
            $parent_item_list[] = $p->getItem();
            $this->mergeData($p);
        }
        $this->getItem()->setMergeParent($parent_item_list);
        return $this;
    }

    public function mergeData(Person $candidate) {
        $field_list = [
            'givenname',
            'prefixname',
            'familyname',
            'dateBirth',
            'dateDeath',
            'comment',
            'noteName',
            'notePerson',
        ];

        foreach ($field_list as $field) {
            $this->mergeField($field, $candidate);
        }

        $collection_list = [
            'givennameVariants',
            'familynameVariants',
            'role',
        ];

        foreach($collection_list as $collection_name) {
            $this->mergeCollection($collection_name, $candidate);
        }

        $item_collection_list = [
            'reference',
            'itemProperty',
        ];

        foreach($item_collection_list as $item_collection_name) {
            $this->item->mergeCollection($item_collection_name, $candidate->getItem());
        }

        $this->item->mergeUrlExternal($candidate->getItem());

        return $this;
    }

    public function mergeField($field, Person $candidate) {
        $getfn = 'get'.ucfirst($field);
        $setfn = 'set'.ucfirst($field);
        $data = $candidate->$getfn();
        if (is_null($this->$getfn())) {
            return $this->$setfn($data);
        } elseif (!is_null($data)) {
            if ($this->$getfn() != $data) {
                $value = $this->$getfn()." | ".$data;
                return $this->$setfn($value);
            }
        }
        return $this;
    }

    public function mergeCollection($collection_name, Person $candidate) {
        $getfn = 'get'.ucfirst($collection_name);
        $setfn = 'set'.ucfirst($collection_name);
        $data = $candidate->$getfn();
        if (is_null($this->$getfn())) {
            return $this->$setfn($data);
        } elseif (!is_null($data)) {
            $collection = $this->$getfn();
            foreach ($data as $data_elmt) {
                if (!$collection->contains($data_elmt)) {
                    $this->$getfn()->add($data_elmt);
                }
            }
        }

        return $this;
    }

    public function addEmptyDefaultElements($auth_list) {
        $role_list = $this->getRole();
        if (count($role_list) < 1) {
            $role_list->add(new PersonRole());
        }
        $reference_list = $this->getItem()->getReference();
        if (count($reference_list) < 1) {
            $reference_list->add(new ItemReference());
        }

        $url_ext_list = $this->getItem()->getUrlExternal();

        // placeholder for all essential authorities
        $url_ext_e_list = $this->getItem()->getUrlExternalCore();

        $core_ids = Authority::coreIDs();

        foreach ($core_ids as $auth_id) {
            $flag_found = false;
            foreach ($url_ext_list as $url_ext_e) {
                if ($url_ext_e->getAuthority()->getId() == $auth_id) {
                    $flag_found = true;
                    break;
                }
            }
            if (!$flag_found) {
                // dd($auth_id, $auth_list);
                $auth = null;
                foreach($auth_list as $a) {
                    if ($a->getId() == $auth_id) {
                        $auth = $a;
                        break;
                    }
                }
                $url_ext_new = new UrlExternal();
                $url_ext_new->setAuthority($auth);
                $url_ext_list->add($url_ext_new);
            }
        }

        // there should be at least one non-essential external url
        $url_ext_ne_list = $this->getItem()->getUrlExternalNonCore();
        if (count($url_ext_ne_list) < 1) {
            $url_ext_list->add(new UrlExternal());
        }
    }

    public function extractSeeAlso() {
        if (is_null($this->comment)) {
            return null;
        }
        $matches = array();
        preg_match_all("/WIAG-Pers-EPISCGatz-([0-9]{3}[0-9]?[0-9]?-[0-9]{3})/",
                       $this->comment,
                       $matches);
        if (is_null($this->seeAlso)) {
            $this->seeAlso = new ArrayCollection();
        }
        foreach($matches[0] as $see_also) {
            $this->seeAlso->add($see_also);
        }

    }

}
