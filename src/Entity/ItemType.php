<?php

namespace App\Entity;

use App\Repository\ItemTypeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ItemTypeRepository::class)
 */
class ItemType
{
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
     * @ORM\Column(type="string", length=31)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $note;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $idPublicKey;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $nameApp;

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

    public function getIdPublicKey(): ?string
    {
        return $this->idPublicKey;
    }

    public function setIdPublicKey(?string $idPublicKey): self
    {
        $this->idPublicKey = $idPublicKey;

        return $this;
    }

    public function getNameApp(): ?string
    {
        return $this->nameApp;
    }

    public function setNameApp(?string $nameApp): self
    {
        $this->nameApp = $nameApp;

        return $this;
    }
}
