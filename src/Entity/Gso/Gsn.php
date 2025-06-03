<?php

namespace App\Entity\Gso;

use App\Repository\Gso\GsnRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GsnRepository::class)]
class Gsn
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'boolean')]
    private $deleted;

    #[ORM\Column(type: 'datetime')]
    private $created;

    #[ORM\Column(type: 'datetime')]
    private $modified;

    #[ORM\Column(type: 'integer')]
    private $createdBy;

    #[ORM\Column(type: 'integer')]
    private $modifiedBy;

    #[ORM\Column(type: 'integer')]
    private $itemId;

    #[ORM\ManyToOne(targetEntity: Items::class, inversedBy: 'gsn')]
    private $item;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $itemMergedintoId;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $itemStatus;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $nummer;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isDeleted(): ?bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }

    public function getModified(): ?\DateTimeInterface
    {
        return $this->modified;
    }

    public function setModified(\DateTimeInterface $modified): self
    {
        $this->modified = $modified;

        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getModifiedBy(): ?int
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(int $modifiedBy): self
    {
        $this->modifiedBy = $modifiedBy;

        return $this;
    }

    public function getItemId(): ?int
    {
        return $this->itemId;
    }

    public function setItemId(int $itemId): self
    {
        $this->itemId = $itemId;

        return $this;
    }

    public function getItemMergedintoId(): ?int
    {
        return $this->itemMergedintoId;
    }

    public function setItemMergedintoId(?int $itemMergedintoId): self
    {
        $this->itemMergedintoId = $itemMergedintoId;

        return $this;
    }

    public function getItemStatus(): ?string
    {
        return $this->itemStatus;
    }

    public function setItemStatus(?string $itemStatus): self
    {
        $this->itemStatus = $itemStatus;

        return $this;
    }

    public function getNummer(): ?string
    {
        return $this->nummer;
    }

    public function setNummer(?string $nummer): self
    {
        $this->nummer = $nummer;

        return $this;
    }
}
