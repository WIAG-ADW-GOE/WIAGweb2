<?php

namespace App\Entity;

use App\Repository\PersonBirthplaceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PersonBirthplaceRepository::class)
 */
class PersonBirthplace
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Person", inversedBy="birthplace")
     * @ORM\JoinColumn(name="person_id", referencedColumnName="id")
     */
    private $person;

    /**
     * @ORM\Column(type="integer")
     */
    private $personId;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $placeName;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $placeId;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=8, nullable=true)
     */
    private $weight;

    /**
     * URL in the the World Historical Gazeteer
     */
    private $urlWhg;

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

    public function getPlaceName(): ?string
    {
        return $this->placeName;
    }

    public function setPlaceName(?string $placeName): self
    {
        $this->placeName = $placeName;

        return $this;
    }

    public function getPlaceId(): ?int
    {
        return $this->placeId;
    }

    public function setPlaceId(?int $placeId): self
    {
        $this->placeId = $placeId;

        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function setWeight(?string $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function getUrlWhg(): ?string {
        return $this->urlWhg;
    }

    public function setUrlWhg(?string $url): self {
        $this->urlWhg = $url;
        return $this;
    }


}
