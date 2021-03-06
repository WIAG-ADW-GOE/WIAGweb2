<?php

namespace App\Entity;

use App\Repository\NameLookupRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NameLookupRepository::class)
 */
class NameLookup
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $personId;

    /**
     * @ORM\ManyToOne(targetEntity="Item", inversedBy="nameLookup")
     * @ORM\JoinColumn(name="person_id", referencedColumnName="id")
     */
    private $item;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $gnFn;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $gnPrefixFn;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPersonId(): ?int
    {
        return $this->personId;
    }

    public function setPersonId(int $personId): self
    {
        $this->personId = $personId;

        return $this;
    }

    public function getGnFn(): ?string
    {
        return $this->gnFn;
    }

    public function setGnFn(string $gnFn): self
    {
        $this->gnFn = $gnFn;

        return $this;
    }

    public function getGnPrefixFn(): ?string
    {
        return $this->gnPrefixFn;
    }

    public function setGnPrefixFn(string $gnPrefixFn): self
    {
        $this->gnPrefixFn = $gnPrefixFn;

        return $this;
    }
}
