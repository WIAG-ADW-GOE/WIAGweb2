<?php

namespace App\Entity;

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
     * @ORM\Column(type="integer", nullable=true)
     */
    private $personIdName;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $personIdRole;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $prioRole;

    private $person;

    private $roleListView;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPerson()
    {
        return $this->person;
    }

    public function setPerson($person): self
    {
        $this->person = $person;

        return $this;
    }

    public function getRoleListView()
    {
        return $this->roleListView;
    }

    public function setRoleListView($role): self
    {
        $this->roleListView = $role;

        return $this;
    }




}
