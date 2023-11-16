<?php

namespace App\Entity;

use App\Repository\DioceseRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity(repositoryClass=DioceseRepository::class)
 */
class Diocese
{

    const CORPUS_ID = 'dioc';
    const SKOS_SCHEME_ID = 1;
    const EDIT_FIELD_LIST = [
        'name',
        'note',
        'ecclesiasticalProvince',
        'dioceseStatus',
        'dateOfFounding',
        'dateOfDissolution',
        'noteBishopricSeat',
        'comment'
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
     * @ORM\OneToMany(targetEntity="SkosLabel", mappedBy="diocese", cascade = {"persist"})
     * @ORM\JoinColumn(name="id", referencedColumnName="concept_id")
     * @ORM\OrderBy({"displayOrder" = "ASC"})
     */
    private $altLabels;

    /**
     * @ORM\Column(type="string", length=1023, nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=1023, nullable=true)
     */
    private $note;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $ecclesiasticalProvince;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $dioceseStatus;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $shapefile;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $bishopricSeatId;

    /**
     * @ORM\OneToOne(targetEntity="Place")
     * @ORM\JoinColumn(name="bishopric_seat_id", referencedColumnName="id")
     */
    private $bishopricSeat;

    /**
     * no DB-mapping
     */
    private $formBishopricSeat;

    /**
     * @ORM\Column(type="string", length=1023, nullable=true)
     */
    private $noteBishopricSeat;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $dateOfFounding;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $dateOfDissolution;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $noteAuthorityFile;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isAltesReich;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isDioceseGs;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */

    /**
     * no db mapping
     */
    private $referenceCount;

    public function __construct($user_id) {
        $this->item = new Item($user_id);
        $this->altLabels = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItem() {
        return $this->item;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

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

    public function getEcclesiasticalProvince(): ?string
    {
        return $this->ecclesiasticalProvince;
    }

    public function setEcclesiasticalProvince(?string $ecclesiasticalProvince): self
    {
        $this->ecclesiasticalProvince = $ecclesiasticalProvince;

        return $this;
    }

    public function getDioceseStatus(): ?string
    {
        return $this->dioceseStatus;
    }

    public function setDioceseStatus(?string $dioceseStatus): self
    {
        $this->dioceseStatus = $dioceseStatus;

        return $this;
    }

    public function getShapefile(): ?string
    {
        return $this->shapefile;
    }

    public function setShapefile(?string $shapefile): self
    {
        $this->shapefile = $shapefile;

        return $this;
    }

    public function getBishopricSeatId() {
        return $this->bishopricSeatId;
    }

    public function setBishopricSeatId(?int $id): self
    {
        $this->bishopricSeatId = $id;

        return $this;
    }


    public function getBishopricSeat() {
        return $this->bishopricSeat;
    }

    public function setBishopricSeat($bishopricSeat): self
    {
        $this->bishopricSeat = $bishopricSeat;

        return $this;
    }

    public function getFormBishopricSeat() {
        $seat_name = $this->formBishopricSeat;
        if (is_null($seat_name) or trim($seat_name) == "" ) {
            // 2023-06-14 table contains dummy data from manual input
            if ($this->bishopricSeatId == 0) {
                $seat_name = "";
            } elseif (!is_null($this->bishopricSeat)) {
                $bs = $this->bishopricSeat;
                $seat_name = $bs->getName().' ('.$bs->getGeoNamesId().')';
            }
        }
        return $seat_name;
    }

    public function setFormBishopricSeat(?string $formBishopricSeat): self
    {
        $this->formBishopricSeat = $formBishopricSeat;

        return $this;
    }

    public function getNoteBishopricSeat(): ?string
    {
        return $this->noteBishopricSeat;
    }

    public function setNoteBishopricSeat(?string $noteBishopricSeat): self
    {
        $this->noteBishopricSeat = $noteBishopricSeat;

        return $this;
    }

    public function getDateOfFounding(): ?string
    {
        return $this->dateOfFounding;
    }

    public function setDateOfFounding(?string $dateOfFounding): self
    {
        $this->dateOfFounding = $dateOfFounding;

        return $this;
    }

    public function getDateOfDissolution(): ?string
    {
        return $this->dateOfDissolution;
    }

    public function setDateOfDissolution(?string $dateOfDissolution): self
    {
        $this->dateOfDissolution = $dateOfDissolution;

        return $this;
    }

    public function getNoteAuthorityFile(): ?string
    {
        return $this->noteAuthorityFile;
    }

    public function setNoteAuthorityFile(?string $noteAuthorityFile): self
    {
        $this->noteAuthorityFile = $noteAuthorityFile;

        return $this;
    }

    public function getIsAltesReich(): ?bool
    {
        return $this->isAltesReich;
    }

    public function setIsAltesReich(?bool $isAltesReich): self
    {
        $this->isAltesReich = $isAltesReich;

        return $this;
    }

    public function getIsDioceseGs(): ?bool
    {
        return $this->isDioceseGs;
    }

    public function setIsDioceseGs(?bool $isDioceseGs): self
    {
        $this->isDioceseGs = $isDioceseGs;

        return $this;
    }

    public function getDisplayname(): ?string {
        return $this->dioceseStatus.' '.$this->name;
    }

    public function getAltLabels() {
        return $this->altLabels;
    }

    public function getAltLabelLine() {
        $cLabel = array();
        foreach ($this->altLabels as $label) {
            $lang = $label->getLang();
            $labelTxt = $label->getLabel();
            $cLabel[] = $lang ? $labelTxt.' ('.$lang.')' : $labelTxt;
        }

        return implode("; ", $cLabel);
    }

    public function setReferenceCount($value): self {
        $this->referenceCount = $value;
        return $this;
    }

    public function getReferenceCount() {
        return $this->referenceCount;
    }

    // public function getCorpusId() {
    //     return self::CORPUS_ID;
    // }

    /**
     * provide direct access
     */
    public function getIdInSource() {
        return $this->getItem()->getIdInSource();
    }

    /**
     * do not provide setInputError; use add or remove to manipulate this property
     */
    public function getInputError() {
        if (is_null($this->inputError)) {
            $this->inputError = new ArrayCollection();
        }
        return $this->inputError;
    }

}
