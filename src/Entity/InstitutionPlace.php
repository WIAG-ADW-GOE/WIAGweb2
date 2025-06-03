<?php

namespace App\Entity;

use App\Repository\InstitutionPlaceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InstitutionPlaceRepository::class)]
class InstitutionPlace
{
    #[ORM\ManyToOne(targetEntity: Institution::class, inversedBy: 'institutionPlace')]
    #[ORM\JoinColumn(name: 'institution_id', referencedColumnName: 'id')]
    private $institution;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer')]
    private $institutionId;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $placeId;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $comment;

    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    private $placeName;

    #[ORM\Column(type: 'integer')]
    private $dateBeginTaq;

    #[ORM\Column(type: 'integer')]
    private $dateBeginTpq;

    #[ORM\Column(type: 'integer')]
    private $dateEndTaq;

    #[ORM\Column(type: 'integer')]
    private $dateEndTpq;

    #[ORM\Column(type: 'integer')]
    private $numDateBegin;

    #[ORM\Column(type: 'integer')]
    private $numDateEnd;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $noteBegin;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $noteEnd;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInstitution() {
        return $this->institution;
    }

    public function setInstitution($institution) {
        $this->institution = $institution;
        return $this;
    }

    public function getInstitutionId(): ?int
    {
        return $this->institutionId;
    }

    public function setInstitutionId(int $institutionId): self
    {
        $this->institutionId = $institutionId;

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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

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

    public function getDateBeginTaq(): ?string
    {
        return $this->dateBeginTaq;
    }

    public function setDateBeginTaq(?string $dateBeginTaq): self
    {
        $this->dateBeginTaq = $dateBeginTaq;

        return $this;
    }

    public function getDateBeginTpq(): ?string
    {
        return $this->dateBeginTpq;
    }

    public function setDateBeginTpq(?string $dateBeginTpq): self
    {
        $this->dateBeginTpq = $dateBeginTpq;

        return $this;
    }

    public function getDateEndTaq(): ?string
    {
        return $this->dateEndTaq;
    }

    public function setDateEndTaq(?string $dateEndTaq): self
    {
        $this->dateEndTaq = $dateEndTaq;

        return $this;
    }

    public function getDateEndTpq(): ?string
    {
        return $this->dateEndTpq;
    }

    public function setDateEndTpq(?string $dateEndTpq): self
    {
        $this->dateEndTpq = $dateEndTpq;

        return $this;
    }

    public function getNumDateBegin(): ?int
    {
        return $this->numDateBegin;
    }

    public function setNumDateBegin(?int $numDateBegin): self
    {
        $this->numDateBegin = $numDateBegin;

        return $this;
    }

    public function getNumDateEnd(): ?int
    {
        return $this->numDateEnd;
    }

    public function setNumDateEnd(?int $numDateEnd): self
    {
        $this->numDateEnd = $numDateEnd;

        return $this;
    }

    public function getNoteBegin(): ?string
    {
        return $this->noteBegin;
    }

    public function setNoteBegin(?string $noteBegin): self
    {
        $this->noteBegin = $noteBegin;

        return $this;
    }

    public function getNoteEnd(): ?string
    {
        return $this->noteEnd;
    }

    public function setNoteEnd(?string $noteEnd): self
    {
        $this->noteEnd = $noteEnd;

        return $this;
    }
}
