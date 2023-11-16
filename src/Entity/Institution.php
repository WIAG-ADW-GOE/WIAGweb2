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
    const CORPUS_ID = 'mon'; // domstifte have 'cap'

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
     * @ORM\Column(type="string", length=1023, nullable=true)
     */
    private $note;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $nameShort;

    public function __construct($user_id) {
        $this->institutionPlace = new ArrayCollection();
        $this->item = new Item($user_id);
    }

    public function getItem()
    {
        return $this->item;
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
        $this->item->setIdInSource($idGsn);

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

    public function getInstitutionPlace() {
        return $this->institutionPlace;
    }

}
