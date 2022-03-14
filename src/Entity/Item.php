<?php

namespace App\Entity;

use App\Repository\ItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ItemRepository::class)
 */
class Item {
    // redundant to table item_type (simpler, faster than a query)
    const ITEM_TYPE_ID = [
        'Kloster' => 2,
        'Domstift' => 3,
        'Bischof' => 4,
        'Domherr' => 5,
        'Domherr GS' => 6,
    ];

    // map authority name
    const AUTHORITY_ID = [
            "GS" => 200,
            "WIAG" => 5,
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
     * @ORM\OneToMany(targetEntity="ItemReference", mappedBy="item")
     * @ORM\JoinColumn(name="id", referencedColumnName="item_id")
     */
    private $reference;

    /**
     * @ORM\OneToOne(targetEntity="Person")
     * @ORM\JoinColumn(name="id", referencedColumnName="id")
     */
    private $person;

    /**
     * @ORM\OneToMany(targetEntity="PersonRole", mappedBy="item")
     * @ORM\JoinColumn(name="id", referencedColumnName="person_id")
     */
    // TODO 2022-02-25
    // link roles via person?!
    // private $personRole;

    /**
     * @ORM\OneToOne(targetEntity="Institution")
     * @ORM\JoinColumn(name="id", referencedColumnName="id")
     */
    private $institution;

    /**
     * @ORM\OneToMany(targetEntity="NameLookup", mappedBy="item")
     * @ORM\JoinColumn(name="id", referencedColumnName="person_id")
     */
    private $nameLookup;

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

    public function getReference() {
        return $this->reference;
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

    public function getIdExternalObj($authorityIdOrName) {
        $authorityId = 5;
        if (is_int($authorityIdOrName)) {
            $authorityId = $authorityIdOrName;
        } else {
            $authorityId = self::AUTHORITY_ID[$authorityIdOrName];
        }

        $result = null;
        foreach ($this->idExternal as $id) {
            if ($id->getAuthorityId() == $authorityId) {
                $result = $id;
                break;
            }
        }
        return $result;
    }

    public function getIdExternalByAuthorityId($authorityId) {
        $id = $this->getIdExternalObj($authorityId);
        return $id ? $id->getValue() : null;
    }

    public function getUriExternalByAuthorityId($authorityId) {
        $id = $this->getIdExternalObj($authorityId);
        return $id ? $id->getAuthority()->getUrlFormatter().$id->getValue() : null;
    }

    public function getSource() {
        $typeId = $this->itemTypeId;
        return $typeId ? array_flip(self::ITEM_TYPE_ID)[$typeId] : null;
    }

}
