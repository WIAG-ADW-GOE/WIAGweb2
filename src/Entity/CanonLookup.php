<?php

namespace App\Entity;

use App\Entity\PersonRole;
use App\Entity\Person;

use App\Repository\CanonLookupRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CanonLookupRepository::class)
 */
class CanonLookup
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="Person")
     * @ORM\JoinColumn(name="person_id_role", referencedColumnName="id")
     */
    private $person;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $personIdName;

    private $personName;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $personIdRole;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $prioRole;

    private $hasSibling = null;

    // see personIdRole
    private $personRole = null;

    private $itemTypeId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    // flag for more than one source for offices
    private $otherSource = false;

    public function getPerson(): ?Person
    {
        return $this->person;
    }

    public function setPerson(Person $person): self
    {
        $this->person = $person;

        return $this;
    }


    public function getPersonIdName(): ?int
    {
        return $this->personIdName;
    }

    public function setPersonIdName(int $personIdName): self
    {
        $this->personIdName = $personIdName;

        return $this;
    }

    public function getPersonName(): ?Person
    {
        return $this->personName;
    }

    public function setPersonName(Person $person): self
    {
        $this->personName = $person;

        return $this;
    }


    public function getPersonIdRole(): ?int
    {
        return $this->personIdRole;
    }

    public function setCanonGs(?int $personIdRole): self
    {
        $this->personIdRole = $personIdRole;

        return $this;
    }

    public function getPrioRole(): ?int
    {
        return $this->prioRole;
    }

    public function setPrioRole(?int $prioRole): self
    {
        $this->prioRole = $prioRole;

        return $this;
    }

    public function getPersonRole()
    {
        return $this->personRole;
    }

    public function setPersonRole($personRole): self
    {
        $this->personRole = $personRole;

        return $this;
    }

    public function getItemTypeId()
    {
        return $this->itemTypeId;
    }

    public function setItemTypeId($itemTypeId): self
    {
        $this->itemTypeId = $itemTypeId;

        return $this;
    }

    public function getOtherSource(): bool {
        return $this->otherSource;
    }


    public function setOtherSource($otherSource): self {
        $this->otherSource = $otherSource;
        return $this;
    }

}
