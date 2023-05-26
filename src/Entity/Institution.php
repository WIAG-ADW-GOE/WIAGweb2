<?php

namespace App\Entity;

use App\Entity\Item;
use App\Repository\InstitutionRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


/**
 * @ORM\Entity(repositoryClass=InstitutionRepository::class)
 */
class Institution
{
    /**
     * @ORM\OneToOne(targetEntity="Item")
     * @ORM\JoinColumn(name="id", referencedColumnName="id")
     */
    private $item;

    /**
     * @ORM\OneToMany(targetEntity="InstitutionPlace", mappedBy="institution")
     * @ORM\JoinColumn(name="id", referencedColumnName="institution_id")
     */
    private $institutionPlace;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $idGsn;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $dateBegin;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $dateEnd;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $dateMin;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $dateMax;

    /**
     * @ORM\Column(type="string", length=1023, nullable=true)
     */
    private $note;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $nameShort;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemTypeId;

    public function __construct($item_type_id, $user_id) {
        $this->institutionPlace = new ArrayCollection();
        $this->item = new Item($item_type_id, $user_id);
        $this->itemTypeId = $item_type_id;
    }

    public function setItem($item) {
        $this->item = $item;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getIdGsn(): ?int
    {
        return $this->idGsn;
    }

    public function setIdGsn(?int $idGsn): self
    {
        $this->idGsn = $idGsn;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDateBegin(): ?string
    {
        return $this->dateBegin;
    }

    public function setDateBegin(?string $dateBegin): self
    {
        $this->dateBegin = $dateBegin;

        return $this;
    }

    public function getDateEnd(): ?string
    {
        return $this->dateEnd;
    }

    public function setDateEnd(?string $dateEnd): self
    {
        $this->dateEnd = $dateEnd;

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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }

    public function getNameShort(): ?string
    {
        return $this->nameShort;
    }

    public function setNameShort(?string $nameShort): self
    {
        $this->nameShort = $nameShort;

        return $this;
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

    public function getInstitutionPlace() {
        return $this->institutionPlace;
    }

}
