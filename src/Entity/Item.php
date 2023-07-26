<?php

namespace App\Entity;

use App\Repository\ItemRepository;
use App\Service\UtilService;
use App\Entity\Authority;
use App\Entity\ItemReference;

use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity(repositoryClass=ItemRepository::class)
 */
class Item {
    // redundant to table item_type (simpler, faster than a query)
    const ITEM_TYPE_ID = [
        'Bistum'     => ['id' => 1],
        'Kloster'    => ['id' => 2],
        'Domstift'   => ['id' => 3],
        'Bischof'    => [
            'id' => 4,
        ],
        'Domherr'    => [
            'id' => 5,
        ],
        'Domherr GS' => ['id' => 6],
        'Amt'        => ['id' => 8],
        'Bischof GS' => ['id' => 9],
        'Priester Utrecht' => ['id' => 10],
    ];

    const ITEM_TYPE_WIAG_PERSON_LIST = [
        self::ITEM_TYPE_ID['Bischof']['id'],
        self::ITEM_TYPE_ID['Domherr']['id'],
        self::ITEM_TYPE_ID['Bischof GS']['id'],
        self::ITEM_TYPE_ID['Domherr GS']['id'],
        self::ITEM_TYPE_ID['Priester Utrecht']['id'],
    ];

    const ITEM_TYPE = [
        1 => [
            'name' => 'Bistum',
            'id_public_mask' => 'WIAG-Inst-DIOCGatz-#-001',
            'edit_status_default' => 'angelegt',
            'online_status' => 'online',
        ],
        4 => [
            'name' => 'Bischof',
            'id_public_mask' => 'WIAG-Pers-EPISCGatz-#-001',
            'numeric_field_width' => 5,
            'edit_status_default' => 'angelegt',
            'online_status' => 'fertig',
        ],
        5 => [
            'name' => 'Domherr',
            'id_public_mask' => 'WIAG-Pers-CANON-#-001',
            'numeric_field_width' => 5,
            'edit_status_default' => 'angelegt',
            'online_status' => 'online',
        ],
        6 => [
            'name' => 'Domherr GS',
            'id_public_mask' => 'WIAG-Pers-CANON-#-001',
            'numeric_field_width' => 5,
            'online_status' => 'online',
        ],
        8 => [
            'name' => 'Amt',
            'edit_status_default' => 'angelegt',
        ],
        9 => [
            'name' => 'Bischof GS',
            'online_status' => 'online',
        ],
    ];

    // map authority name
    const AUTHORITY_ID_LEGACY = [
            "GND" => 1,
            "Wikidata" => 2,
            "Wikipedia" => 3,
            "WIAG_ID" => 5,
            "VIAF" => 4,
            "GS" => 200,
    ];

    const JOIN_DELIM = "|";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id = 0;

    /**
     * @ORM\OneToMany(targetEntity="ItemProperty", mappedBy="item")
     * @ORM\JoinColumn(name="id", referencedColumnName="item_id")
     */
    private $itemProperty;

    /**
     * @ORM\OneToMany(targetEntity="UrlExternal", mappedBy="item", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinColumn(name="id", referencedColumnName="item_id")
     */
    private $urlExternal;

    /**
     * @ORM\OneToMany(targetEntity="ItemReference", mappedBy="item")
     * @ORM\JoinColumn(name="id", referencedColumnName="item_id")
     */
    private $reference;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemTypeId;

    /**
     * @ORM\Column(type="string", length=511, nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="string", length=1023, nullable=true)
     */
    private $itemInSource;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $idPublic;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $mergedIntoId;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $editStatus;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     *
     * one of: original, parent, child, orphan
     */
    private $mergeStatus;

    /**
     * @ORM\Column(type="integer")
     */
    private $createdBy;

    /**
     * @ORM\Column(type="datetime")
     */
    private $dateCreated;

    /**
     * @ORM\Column(type="integer")
     */
    private $changedBy;

    /**
     * @ORM\Column(type="datetime")
     */
    private $dateChanged;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isDeleted;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $normdataEditedBy;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $lang;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $idInSource;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $isOnline = 0;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $commentDuplicate;

    /**
     * no DB-mapping
     * hold IDs of merge parents
     */
    private $mergeParent = null;

    /**
     * no DB-mapping
     * hold form input data
     */
    private $formIsEdited = false;

    /**
     * no DB-mapping
     * hold form input data
     */
    private $formIsExpanded = false;

    /**
     * no DB-mapping
     * hold form input data ('insert'|'edit')
     */
    private $formType = "edit";

    /**
     * no DB-mapping
     * flag
     */
    private $wiagChanged = false;

    /**
     * no DB-mapping
     * flag
     */
    private $gsChanged = false;

    /**
     * collection of InputError
     */
    private $inputError;

    /**
     * no DB-mapping, hold user obj
     */
    private $changedByUser = null;

    /**
     * no DB-mapping
     */
    private $isNew = true;

    /**
     * no DB-mapping
     * hold ancestor (array of Item);
     */
    private $ancestor = null;

    public function __construct($item_type_id, $user_wiag_id) {
        $now = new \DateTimeImmutable('now');
        $this->isDeleted = 0;
        $this->reference = new ArrayCollection();
        $this->urlExternal = new ArrayCollection();
        $this->itemProperty = new ArrayCollection();
        $this->idPublic = "";
        $this->idInSource = "";
        $this->mergeStatus = 'original';
        $this->mergeParent = new ArrayCollection();
        $this->isNew = true;
        $this->ancestor = array();

        $this->itemTypeId = $item_type_id;
        $this->createdBy = $user_wiag_id;
        $this->dateCreated = $now;
        $this->changedBy = $user_wiag_id;
        $this->dateChanged = $now;

        $this->editStatus = self::ITEM_TYPE[$item_type_id]['edit_status_default'];

        $this->inputError = new ArrayCollection();

        $this->formType = "edit";
    }

    /**
     * 2023-05-24 obsolete
     */
    static public function newItem_legacy($item_type_id, $user_wiag_id) {
        $now = new \DateTimeImmutable('now');
        $item = new Item();
        $item->setItemTypeId($item_type_id);
        $item->setCreatedBy($user_wiag_id);
        $item->setDateCreated($now);
        $item->setChangedBy($user_wiag_id);
        $item->setDateChanged($now);
        return $item;
    }

    public function setId($id): self {
        $this->id = $id;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return sorted list of item properties
     */
    public function getItemProperty() {
        $sorted_list = $this->itemProperty->toArray();
        usort($sorted_list, function($a, $b) {
            $a_key = $a->getType()->getDisplayOrder();
            $b_key = $b->getType()->getDisplayOrder();

            return $a_key < $b_key ? -1 : ($a_key > $b_key ? 1 : 0);
        });
        $this->itemProperty = new ArrayCollection($sorted_list);
        return $this->itemProperty;
    }

    public function getUrlExternal() {
        return $this->urlExternal;
    }

    /**
     * filter external URLs by $authority->urlType
     */
    public function getUrlExternalByType(string $url_type) {
        $url_filter = $this->urlExternal->filter(function ($u) use ($url_type) {
            return $u->getAuthority()->getUrlType() == $url_type;
        });

        $url_list = $url_filter->toArray();

        usort($url_list, function($a, $b) {
            $a_key = $a->getAuthority()->getDisplayOrder();
            $b_key = $b->getAuthority()->getDisplayOrder();

            return $a_key == $b_key ? 0 : ($a_key < $b_key ? -1 : 1);
        });

        return $url_list;
    }

    /**
     *
     */
    public function getEssentialUrlExternal() {
        $id_ext_list = $this->getUrlExternalSorted();

        $essential_auth_ids = Authority::ESSENTIAL_ID_LIST;

        return $id_ext_list->filter(function($id_ext) use ($essential_auth_ids) {
            $auth = $id_ext->getAuthority();
            if (is_null($auth)) {
                return false;
            } else {
                return array_search($auth->getId(), $essential_auth_ids) !== false;
            }
        });

    }

    /**
     *
     */
    public function getUrlExternalNonEssential() {
        $id_ext_list = $this->getUrlExternalSorted();

        $core = Authority::ESSENTIAL_ID_LIST;

        return $id_ext_list->filter(function($id_ext) use ($core) {
            $auth = $id_ext->getAuthority();
            if (is_null($auth)) {
                return true;
            } else {
                return (array_search($auth->getId(), $core) === false
                        and $auth->getUrlType() != 'Interner Identifier');
            }
        });
    }

    /**
     * return sorted ArrayCollection
     */
    public function getUrlExternalSorted() {
        if ($this->urlExternal->isEmpty()) {
            return $this->urlExternal;
        }
        $urlExternal_sorted = $this->urlExternal->toArray();
        uasort($urlExternal_sorted, function($a, $b) {
            $a_authority = $a->getAuthority();
            $b_authority = $b->getAuthority();

            $display_order_a = $a_authority ? $a_authority->getDisplayOrder() : 90000;
            $display_order_b = $b_authority ? $b_authority->getDisplayOrder() : 90000;
            return $display_order_a < $display_order_b ? -1 : ($display_order_a > $display_order_b ? 1 : 0);
        });
        return new ArrayCollection($urlExternal_sorted);
    }

    public function getReference() {
        return $this->reference;
    }

    public function setReference(ArrayCollection $reference) {
        $this->reference = $reference;
        return $this;
    }


    public function getSortedReference(string $field) {
        // sort by referece_volume.display_order

        $ref_list = $this->reference->toArray();
        uasort($ref_list, function($a, $b) use ($field) {
            $a_vol = $a->getReferenceVolume();
            $b_vol = $b->getReferenceVolume();

            if (is_null($b_vol)) {
                return -1;
            }

            if (is_null($a_vol)) {
                return 1;
            }

            // cv bk 2022-10-26 sort by title_short for single page
            // if ($a_vol->getDisplayOrder() <= $b_vol->getDisplayOrder()) {
            $getter = 'get'.ucfirst($field);

            if ($a_vol->$getter() <= $b_vol->$getter()) {
                return -1;
            } else {
                return 1;
            }
        });

        return $ref_list;
    }

    public function getItemTypeId(): ?int
    {
        return $this->itemTypeId;
    }

    public function setItemTypeId(int $itemTypeId): self
    {
        $this->itemTypeId = $itemTypeId;

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

    public function getItemInSource(): ?string
    {
        return $this->itemInSource;
    }

    public function setItemInSource(?string $itemInSource): self
    {
        $this->itemInSource = $itemInSource;

        return $this;
    }

    public function getIdPublic(): ?string
    {
        return $this->idPublic;
    }

    public function getIdPublicNumber(): ?string {
        $rgx = '/-([0-9]{5})-/';
        $matches = null;
        preg_match($rgx, $this->idPublic, $matches);
        return $matches ? $matches[1] : null;
    }

    public function setIdPublic(?string $idPublic): self
    {
        $this->idPublic = $idPublic;

        return $this;
    }

    public function getMergedIntoId(): ?int
    {
        return $this->mergedIntoId;
    }

    public function setMergedIntoId(?int $mergedIntoId): self
    {
        $this->mergedIntoId = $mergedIntoId;

        return $this;
    }

    public function getEditStatus(): ?string
    {
        return $this->editStatus;
    }

    public function setEditStatus(?string $editStatus): self
    {
        $this->editStatus = $editStatus;

        return $this;
    }

    public function getMergeStatus(): ?string
    {
        return $this->mergeStatus;
    }

    public function setMergeStatus(?string $mergeStatus): self
    {
        $this->mergeStatus = $mergeStatus;

        return $this;
    }


    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getDateCreated(): ?\DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(\DateTimeInterface $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getChangedBy(): ?int
    {
        return $this->changedBy;
    }

    public function setChangedBy(int $changedBy): self
    {
        $this->changedBy = $changedBy;

        return $this;
    }

    public function getDateChanged(): ?\DateTimeInterface
    {
        return $this->dateChanged;
    }

    public function setDateChanged(\DateTimeInterface $dateChanged): self
    {
        $this->dateChanged = $dateChanged;

        return $this;
    }

    public function getIsDeleted(): ?bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(?bool $isDeleted): self
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

    public function getNormdataEditedBy(): ?string
    {
        return $this->normdataEditedBy;
    }

    public function setNormdataEditedBy(?string $normdataEditedBy): self
    {
        $this->normdataEditedBy = $normdataEditedBy;

        return $this;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setLang(?string $lang): self
    {
        $this->lang = $lang;

        return $this;
    }

    public function getIdInSource(): ?string
    {
        return $this->idInSource;
    }

    public function setIdInSource(string $idInSource): self
    {
        $this->idInSource = $idInSource;

        return $this;
    }

    public function getIsOnline(): ?bool
    {
        return $this->isOnline;
    }

    public function setIsOnline(?bool $isOnline): self
    {
        $this->isOnline = $isOnline;
        return $this;
    }

    public function updateIsOnline() {
        $online_status = self::ITEM_TYPE[$this->itemTypeId]['online_status'];
        $this->isOnline = $this->editStatus == $online_status ? 1 : 0;
    }

    public function setWiagChanged(?bool $value): self {
        $this->wiagChanged = $value;
        return $this;
    }

    public function getWiagChanged() {
        return $this->wiagChanged;
    }

    public function setGsChanged(?bool $value): self {
        $this->gsChanged = $value;
        return $this;
    }

    public function getGsChanged() {
        return $this->gsChanged;
    }

    public function setFormIsEdited($value): self {
        $this->formIsEdited = $value;
        return $this;
    }

    public function getFormIsEdited() {
        return $this->formIsEdited;
    }

    public function setFormIsExpanded($value): self {
        $this->formIsExpanded = $value;
        return $this;
    }

    public function getFormIsExpanded() {
        return $this->formIsExpanded;
    }

    public function setFormType($value): self {
        $this->formType = $value;
        return $this;
    }

    public function getFormType() {
        return $this->formType;
    }


    public function setMergeParent($value): self {
        $this->mergeParent = $value;
        return $this;
    }

    public function getMergeParent() {
        return $this->mergeParent;
    }

    public function setIsNew($value): self {
        $this->isNew = $value;
        return $this;
    }

    public function getIsNew() {
        return $this->isNew;
    }


    public function setAncestor($value): self {
        $this->ancestor = $value;
        return $this;
    }

    public function getAncestor() {
        return $this->ancestor;
    }

    public function setChangedByUser($value): self {
        $this->changedByUser = $value;
        return $this;
    }

    public function getChangedByUser() {
        return $this->changedByUser;
    }

    public function getMergeParentTxt() {
        if (is_null($this->mergeParent) || count($this->mergeParent) < 1) {
            return null;
        } else {
            $id_list = array_map(
                function ($v) {return $v->getIdInSource();},
                $this->mergeParent);
            return implode($id_list, ", ");
        }
    }

    /**
     *
     */
    public function isa($itemTypeName) {
        return $this->itemTypeId == self::ITEMTYPEID[$itemTypeName];
    }

    private function findAuthorityId($authorityIdOrName) {
        $authority_id = intval($authorityIdOrName, 10);
        if ($authority_id == 0) {
            $authority_id = Authority::ID[$authorityIdOrName];
        }
        return $authority_id;
    }

    /**
     * @return object of type UrlExternal for $authorityIdOrName
     */
    public function getUrlExternalObj($authorityIdOrName) {
        $authorityId = $this->findAuthorityId($authorityIdOrName);

        $result = null;
        foreach ($this->urlExternal as $id) {
            if ($id->getAuthorityId() == $authorityId) {
                $result = $id;
                break;
            }
        }

        return $result;
    }

    public function getUrlExternalByAuthority($authorityIdOrName) {
        $authorityId = $this->findAuthorityId($authorityIdOrName);
        return $this->getUrlExternalByAuthorityId($authorityId);
    }

    public function getUrlExternalByAuthorityId($authorityId) {
        $id = $this->getUrlExternalObj($authorityId);
        return $id ? $id->getValue() : null;
    }

    public function getUriExtByAuth($authorityIdOrName) {
        $authorityId = $this->findAuthorityId($authorityIdOrName);
        $id = $this->getUrlExternalObj($authorityId);
        return $id ? $id->getAuthority()->getUrlFormatter().$id->getValue() : null;
    }

    public function getUriExtByAuthId($authorityId) {
        $id = $this->getUrlExternalObj($authorityId);
        return $id ? $id->getAuthority()->getUrlFormatter().$id->getValue() : null;
    }

    /**
     * return name for $typeId
     */
    public function getSource() {
        $typeId = $this->itemTypeId;
        if (!is_null($typeId)) {
            foreach(Item::ITEM_TYPE_ID as $key => $i) {
                if ($i['id'] == $typeId) {
                    return $key;
                }
            }
        }
        return $typeId;
    }

    /**
     * @return elements of `itemProperty` as array with `item_property_type.name` as key
     */
    public function arrayItemProperty() {
        $itemPropByName = array();
        // prepare list
        foreach ($this->itemProperty as $ip) {
            $key = $ip->getType()->getName();
            $itemPropByName[$key] = array();
        }
        // collect values
        foreach ($this->itemProperty as $ip) {
            $key = $ip->getType()->getName();
            $entry = array();
            $entry['value'] = $ip->getValue();
            if ($ip->getDateValue()) {
                $entry['date'] = $ip->getDateValue()->format('d.m.Y');
            }
            $itemPropByName[$key][] = $entry;
        }

        return $itemPropByName;
    }

    /**
     * @return elements of `itemProperty` as array with `item_property_type.name` as key
     */
    public function arrayItemPropertyWithName() {
        $itemPropByName = array();
        // prepare list
        foreach ($this->itemProperty as $ip) {
            $key = $ip->getType()->getName();
            $entry['name'] = $ip->getType()->getName();
            $entry['value'] = array();
            $entry['date'] = array();
            $itemPropByName[$key] = $entry;
        }
        // collect values
        foreach ($this->itemProperty as $ip) {
            $key = $ip->getType()->getName();
            $itemPropByName[$key]['value'][] = $ip->getValue();
            if ($ip->getDateValue()) {
                $date_value = $ip->getDateValue()->format('d.m.Y');
                $itemPropByName[$key]['date'][] = $date_value;
            }
        }
        // list to string
        foreach ($itemPropByName as $key => $ipn) {
            $itemPropByName[$key]['value'] = implode(', ', $ipn['value']);
            $itemPropByName[$key]['date'] = implode(', ', $ipn['date']);
        }

        return $itemPropByName;
    }


    /**
     * get a value in `itemProperty` if present or null
     */
    public function itemPropertyValue(string $key) {
        $prop = array();
        foreach ($this->itemProperty as $ip) {
            if ($ip->getType()->getName() == $key) {
                $prop[] = $ip;
            }
        }
        return $prop;
    }

    public function getCommentDuplicate(): ?string
    {
        return $this->commentDuplicate;
    }

    public function setCommentDuplicate(?string $commentDuplicate): self
    {
        $this->commentDuplicate = $commentDuplicate;

        return $this;
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


    /**
     * do not provide setInputError; use add or remove to manipulate this property
     */
    public function getInputError() {
        if (is_null($this->inputError)) {
            $this->inputError = new ArrayCollection;
        }
        return $this->inputError;
    }

    /**
     * get list of input errors for section $name
     */
    public function getInputErrorSection($name) {
        $list = new ArrayCollection();
        if (!is_null($this->inputError)) {
            $list = $this->inputError->filter(function($v) use ($name) {
                return $v->getSection() == $name;
            });
        }
        return $list;
    }

    /**
     * updateChangedMetaData($user)
     *
     * update meta data for $item
     */
    public function updateChangedMetaData($user) {
        $now_date = new \DateTimeImmutable('now');
        $this->changedBy = $user->getId();
        $this->changedByUser = $user;
        $this->dateChanged = $now_date;
        return $this;
    }

    /**
     * merge data from $candidate
     */
    public function mergeData(Item $candidate) {
        $field_list = [
            'normdataEditedBy'
        ];

        foreach ($field_list as $field) {
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
        }

        return $this;
    }

    public function mergeCollection($collection_name, Item $candidate) {
        $getfn = 'get'.ucfirst($collection_name);
        $setfn = 'set'.ucfirst($collection_name);
        $data = $candidate->$getfn();
        if (is_null($this->$getfn())) {
            return $this->$setfn($data);
        } elseif (!is_null($data)) {
            foreach ($data as $data_elmt) {
                $this->$getfn()->add($data_elmt);
            }
        }
        return $this;
    }

    public function mergeUrlExternal(Item $candidate) {
        $this->mergeCollection('urlExternal', $candidate);

        // combine entries for the same authorities
        $list = $this->urlExternal->toArray();
        // sort by authority
        usort($list, function($a, $b) {
            $cmp = 0;
            if ($a->getAuthorityId() < $b->getAuthorityId()) {
                $cmp = -1;
            } elseif ($a->getAuthorityId() > $b->getAuthorityId())  {
                $cmp = 1;
            }
            return $cmp;
        });

        $merged_list = new ArrayCollection();
        $last_ref_ext = new UrlExternal();
        foreach ($list as $ref_ext) {
            if ($ref_ext->getAuthorityId() != $last_ref_ext->getAuthorityId()) { // other authority
                $last_ref_ext = $ref_ext;
                $merged_list->add($ref_ext);
            } else {
                $last_value = $last_ref_ext->getValue();
                $merge_value = $ref_ext->getValue();
                if ($last_value != $merge_value) {
                    // essential authority?
                    if (!in_array($ref_ext->getAuthorityId(), Authority::ESSENTIAL_ID_LIST)) {
                        $last_ref_ext = $ref_ext;
                        $merged_list->add($ref_ext);
                    } else {
                        $last_ref_ext->setValue(implode([$last_value, $merge_value], " | "));
                    }
                }
            }
        }
        $this->urlExternal = $merged_list;

    }


    public function mergeItemProperty(Item $candidate) {
        $this->mergeCollection('itemProperty', $candidate);

        // combine entries for the same attribute type
        $list = $this->itemProperty->toArray();
        // sort by type
        usort($list, function($a, $b) {
            $cmp = 0;
            if ($a->getPropertyTypeId() < $b->getPropertyTypeId()) {
                $cmp = -1;
            } elseif ($a->getPropertyTypeId() > $b->getPropertyTypeId())  {
                $cmp = 1;
            }
            return $cmp;
        });

        $merged_list = new ArrayCollection();
        $last_prop = new ItemProperty();
        foreach ($list as $prop) {
            if ($prop->getPropertyTypeId() != $last_prop->getPropertyTypeId()) { // other type
                $last_prop = $prop;
                $merged_list->add($prop);
            } else {
                $last_value = $last_prop->getValue();
                $merge_value = $prop->getValue();
                if ($last_value != $merge_value) {
                    $last_prop->setValue(implode([$last_value, $merge_value], " | "));
                }
            }
        }
        $this->itemProperty = $merged_list;
    }

}
