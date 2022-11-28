<?php

namespace App\Entity;

use App\Repository\ItemRepository;
use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


/**
 * @ORM\Entity(repositoryClass=ItemRepository::class)
 */
class Item {
    // redundant to table item_type (simpler, faster than a query)
    const ITEM_TYPE_ID = [
        'Kloster' => ['id' => 2],
        'Domstift' => ['id' => 3],
        'Bischof' => [
            'id' => 4,
            'id_public_mask' => 'WIAG-Pers-EPISCGatz-#-001',
            'numeric_field_width' => 5,
        ],
        'Domherr' => [
            'id' => 5,
            'id_public_mask' => 'WIAG-Pers-CANON-#-001',
            'numeric_field_width' => 5,
        ],
        'Domherr GS' => ['id' => 6, 'id_public_mask' => 'WIAG-Pers-CANON-#-001'],
        'Bischof GS' => ['id' => 9],
        'Priester Utrecht' => ['id' => 10],
    ];

    // map online status
    const ONLINE_STATUS = [
        4 => 'fertig', # bishop
        'Bischof' => 'fertig',
        5 => 'online', # canon
        'Domherr' => 'online',
        6 => 'importiert', # canon gs; import only if online in Personendatenbank
        9 => 'importiert', # bishop gs; import only if online in Personendatenbank
    ];

    // map authority name
    const AUTHORITY_ID = [
            "GND" => 1,
            "Wikidata" => 2,
            "Wikipedia" => 3,
            "WIAG" => 5,
            "VIAF" => 4,
            "GS" => 200,
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToMany(targetEntity="ItemProperty", mappedBy="item")
     * @ORM\JoinColumn(name="id", referencedColumnName="item_id")
     */
    private $itemProperty;

    /**
     * @ORM\OneToMany(targetEntity="IdExternal", mappedBy="item")
     * @ORM\JoinColumn(name="id", referencedColumnName="item_id")
     */
    private $idExternal;

    /**
     * @ORM\OneToMany(targetEntity="UrlExternal", mappedBy="item")
     * @ORM\JoinColumn(name="id", referencedColumnName="item_id")
     */
    private $urlExternal;


    /**
     * @ORM\OneToMany(targetEntity="ItemReference", mappedBy="item")
     * @ORM\JoinColumn(name="id", referencedColumnName="item_id")
     */
    private $reference;

    /**
     * @ORM\OneToOne(targetEntity="Institution")
     * @ORM\JoinColumn(name="id", referencedColumnName="id")
     */
    private $institution;

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
     * @ORM\Column(type="integer", nullable=true)
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
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isOnline;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $commentDuplicate;

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

    public function __construct() {
        $this->isDeleted = 0;
        $this->reference = new ArrayCollection();
        $this->idExternal = new ArrayCollection();
        $this->urlExternal = new ArrayCollection();
        $this->itemProperty = new ArrayCollection();
    }

    static public function newItem($userWiagId, $itemType) {
        $now = new \DateTimeImmutable('now');
        $item = new Item();
        $item->setItemTypeId(self::ITEM_TYPE_ID[$itemType]['id']);
        $item->setCreatedBy($userWiagId);
        $item->setDateCreated($now);
        $item->setChangedBy($userWiagId);
        $item->setDateChanged($now);
        return $item;
    }

    public function setId($id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItemProperty() {
        return $this->itemProperty;
    }

    public function getIdExternal() {
        return $this->idExternal;
    }

    public function getUrlExternal() {
        return $this->urlExternal;
    }

    public function getReference() {
        return $this->reference;
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

    public function getNormdataEditedBy(): ?int
    {
        return $this->normdataEditedBy;
    }

    public function setNormdataEditedBy(?int $normdataEditedBy): self
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


    /**
     *
     */
    public function isa($itemTypeName) {
        return $this->itemTypeId == self::ITEMTYPEID[$itemTypeName];
    }

    private function findAuthorityId($authorityIdOrName) {
        if (is_int($authorityIdOrName)) {
            $authorityId = $authorityIdOrName;
        } else {
            $authorityId = self::AUTHORITY_ID[$authorityIdOrName];
        }
        return $authorityId;
    }


    /**
     * @return object of type IdExternal for $authorityIdOrName
     */
    public function getIdExternalObj($authorityIdOrName) {
        $authorityId = $this->findAuthorityId($authorityIdOrName);

        $result = null;
        foreach ($this->idExternal as $id) {
            if ($id->getAuthorityId() == $authorityId) {
                $result = $id;
                break;
            }
        }

        return $result;
    }

    public function getIdExternalByAuthority($authorityIdOrName) {
        $authorityId = $this->findAuthorityId($authorityIdOrName);
        return $this->getIdExternalByAuthorityId($authorityId);
    }

    public function getIdExternalByAuthorityId($authorityId) {
        $id = $this->getIdExternalObj($authorityId);
        return $id ? $id->getValue() : null;
    }

    public function getUriExtByAuth($authorityIdOrName) {
        $authorityId = $this->findAuthorityId($authorityIdOrName);
        $id = $this->getIdExternalObj($authorityId);
        return $id ? $id->getAuthority()->getUrlFormatter().$id->getValue() : null;
    }

    public function getUriExtByAuthId($authorityId) {
        $id = $this->getIdExternalObj($authorityId);
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
     * @return elements of `itemProperty` as array
     */
    public function combineItemProperty() {
        $itemPropByName = array();
        foreach ($this->itemProperty as $ip) {
            $key = $ip->getType()->getName();
            $itemPropByName[$key] = $ip->getValue();
            $itemPropByName[$key.'_date'] = $ip->getDateValue();
        }
        return $itemPropByName;
    }

    /**
     * get a value in `itemProperty` if present or null
     */
    public function itemPropertyValue(string $key) {
        $itemPropertyList = $this->combineItemProperty();
        $value = null;
        if (array_key_exists($key, $itemPropertyList)) {
            $value =  $itemPropertyList[$key];
        }
        return $value;
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

    public function mergeIdExternal(Item $candidate) {
        $this->mergeCollection('idExternal', $candidate);

        $list = $this->idExternal->toArray();
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
        $last_ref_ext = null;
        foreach ($list as $ref_ext) {
            if (is_null($last_ref_ext) || $ref_ext->getAuthorityId() != $last_ref_ext->getAuthorityId()) {
                $last_ref_ext = $ref_ext;
                $merged_list->add($ref_ext);
            } else {
                $last_value = $last_ref_ext->getValue();
                $merge_value = $ref_ext->getValue();
                $last_ref_ext->setValue(implode([$last_value, $merge_value], " | "));
            }
        }
        $this->idExternal = $merged_list;

    }


}
