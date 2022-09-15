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
        'Domherr GS' => ['id' => 5, 'id_public_mask' => 'WIAG-Pers-CANON-#-001'],
        'Bischof GS' => ['id' => 9],
        'Priester Utrecht' => ['id' => 10],
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

    public function __construct() {
        $this->reference = new ArrayCollection();
        $this->idExternal = new ArrayCollection();
        $this->urlExternal = new ArrayCollection();
    }

    static public function newItem($userWiagId, $itemType) {
        $item = new Item();
        $item->setItemTypeId(self::ITEM_TYPE_ID[$itemType]['id']);
        $item->setCreatedBy($userWiagId);
        $item->setDateCreated(new \DateTimeImmutable('now'));
        $item->setChangedBy($userWiagId);
        $item->setDateChanged(new \DateTimeImmutable('now'));
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

    public function getSortedReference() {
        // sort by referece_volume.display_order

        $ref_list = $this->reference->toArray();
        uasort($ref_list, function($a, $b) {
            $a_vol = $a->getReferenceVolume();
            $b_vol = $b->getReferenceVolume();

            if (is_null($b_vol)) {
                return -1;
            }

            if (is_null($a_vol)) {
                return 1;
            }

            if ($a_vol->getDisplayOrder() <= $b_vol->getDisplayOrder()) {
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

    public function getUriExternalByAuthority($authorityIdOrName) {
        $authorityId = $this->findAuthorityId($authorityIdOrName);
        $id = $this->getIdExternalObj($authorityId);
        return $id ? $id->getAuthority()->getUrlFormatter().$id->getValue() : null;
    }

    public function getUriExternalByAuthorityId($authorityId) {
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
        return null;
    }

    /**
     * @return elements of `itemProperty` as array
     */
    public function combineItemProperty() {
        $itemPropByName = array();
        foreach ($this->itemProperty as $ip) {
            $itemPropByName[$ip->getName()] = $ip->getValue();
            $itemPropByName[$ip->getName().'_date'] = $ip->getDateValue();
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

}
