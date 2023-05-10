<?php

namespace App\Entity;

use App\Repository\ReferenceVolumeRepository;
use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


/**
 * @ORM\Entity(repositoryClass=ReferenceVolumeRepository::class)
 */
class ReferenceVolume {

    const EDIT_FIELD_LIST = [
        // 'itemTypeId', 2023-05-05 obsolete
        'authorEditor',
        'yearPublication',
        'isbn',
        'riOpacId',
        'displayOrder',
        'fullCitation',
        'titleShort',
        'gsVolumeNr',
        'gsCitation',
        'onlineResource',
        'note',
        'comment',
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToMany(targetEntity="RefIdExternal", mappedBy="reference")
     * @ORM\JoinColumn(name="id", referencedColumnName="reference_id")
     */
    private $idsExternal;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $isOnline;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemTypeId;

    /**
     * @ORM\Column(type="integer")
     */
    private $referenceId = 0;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $fullCitation = "";

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $titleShort = null;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private $authorEditor = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $note;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private $riOpacId;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $isbn;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $yearPublication;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $displayOrder;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private $gsVolumeNr;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $gsDoi;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private $gsUrl;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private $onlineResource;

    /**
     * @ORM\Column(type="string", length=511, nullable=true)
     */
    private $gsCitation;

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
     * collection of InputError
     */
    private $inputError;

    public function __construct() {
        $this->inputError = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdsExternal() {
        return $this->idsExternal;
    }

    public function getIsOnline(): int
    {
        return $this->isOnline;
    }

    public function setIsOnline($is_online): self
    {
        $this->isOnline = $is_online;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getItemTypeId(): int
    {
        return $this->itemTypeId;
    }

    public function setItemTypeId($id): self
    {
        $this->itemTypeId = $id;
        return $this;
    }

    public function getReferenceId(): int
    {
        return $this->referenceId;
    }

    public function setReferenceId($id): self
    {
        $this->referenceId = $id;
        return $this;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getFullCitation(): ?string
    {
        return $this->fullCitation;
    }

    public function setFullCitation(?string $fullCitation): self
    {
        $this->fullCitation = $fullCitation;

        return $this;
    }

    public function getTitleShort(): ?string
    {
        return $this->titleShort;
    }

    public function setTitleShort(?string $titleShort): self
    {
        $this->titleShort = $titleShort;

        return $this;
    }

    public function getAuthorEditor(): ?string
    {
        return $this->authorEditor;
    }

    public function setAuthorEditor(?string $authorEditor): self
    {
        $this->authorEditor = $authorEditor;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }

    public function getRiOpacId(): ?string
    {
        return $this->riOpacId;
    }

    public function setRiOpacId(?string $riOpacId): self
    {
        $this->riOpacId = $riOpacId;

        return $this;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(?string $isbn): self
    {
        $this->isbn = $isbn;

        return $this;
    }

    public function getYearPublication(): ?string
    {
        return $this->yearPublication;
    }

    public function setYearPublication(?string $yearPublication): self
    {
        $this->yearPublication = $yearPublication;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(?int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    public function getGsVolumeNr(): ?string
    {
        return $this->gsVolumeNr;
    }

    public function setGsVolumeNr(?string $gsVolumeNr): self
    {
        $this->gsVolumeNr = $gsVolumeNr;

        return $this;
    }

    public function getGsDoi(): ?string
    {
        return $this->gsDoi;
    }

    public function setGsDoi(?string $gsDoi): self
    {
        $this->gsDoi = $gsDoi;

        return $this;
    }

    public function getGsUrl(): ?string
    {
        return $this->gsUrl;
    }

    public function setGsUrl(?string $gsUrl): self
    {
        $this->gsUrl = $gsUrl;

        return $this;
    }

    public function getOnlineResource(): ?string
    {
        return $this->onlineResource;
    }

    public function setOnlineResource(?string $onlineResource): self
    {
        $this->onlineResource = $onlineResource;

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
     * getIdExternalByAuthorityId($authorityId)
     *
     * look in the list of external ids for the those with matching `$authorityId`.
     */
    public function getIdExternalByAuthorityId($authorityId) {
        $ids = array();
        foreach ($this->idsExternal as $item) {
            if ($item->getAuthorityId() == $authorityId) {
                $ids[] = $item;
            }
        }
        return $ids;
    }

    public function getGsCitation(): ?string
    {
        return $this->gsCitation;
    }

    public function setGsCitation(?string $gsCitation): self
    {
        $this->gsCitation = $gsCitation;

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

}
