<?php

namespace App\Entity;

use App\Repository\PlaceIdExternalRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PlaceIdExternalRepository::class)
 */
class PlaceIdExternal
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
    private $placeId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $geonamesId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="integer")
     */
    private $authorityId;

    /**
     * @ORM\Column(type="string", length=127)
     */
    private $value;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $note;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlaceId(): ?int
    {
        return $this->placeId;
    }

    public function setPlaceId(int $placeId): self
    {
        $this->placeId = $placeId;

        return $this;
    }

    public function getGeonamesId(): ?int
    {
        return $this->geonamesId;
    }

    public function setGeonamesId(?int $geonamesId): self
    {
        $this->geonamesId = $geonamesId;

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

    public function getAuthorityId(): ?int
    {
        return $this->authorityId;
    }

    public function setAuthorityId(int $authorityId): self
    {
        $this->authorityId = $authorityId;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

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
}
