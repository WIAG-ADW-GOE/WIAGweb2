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
     * @ORM\Column(type="integer")
     */
    private $personId;

    /**
     * @ORM\Column(type="integer")
     */
    private $personIdCanon;

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

    public function getPersonIdCanon(): ?int
    {
        return $this->personIdCanon;
    }

    public function setPersonIdCanon(int $personIdCanon): self
    {
        $this->personIdCanon = $personIdCanon;

        return $this;
    }
}
