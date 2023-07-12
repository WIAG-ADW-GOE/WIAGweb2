<?php

namespace App\Entity\Gso;

use App\Repository\GsoItemsRepository;
use Doctrine\ORM\Mapping as ORM;


/**
 * @ORM\Entity(repositoryClass=GsoItemsRepository::class)
 * 2023-07-12 the framework does not assign the correct database automatically (see doctrine.yaml and .env.local)
 * @ORM\Table(name="items", schema="gso_in_202306")
 */
class GsoItems
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="boolean")
     */
    private $deleted;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @ORM\Column(type="datetime")
     */
    private $modified;

    /**
     * @ORM\Column(type="integer")
     */
    private $createdBy;

    /**
     * @ORM\Column(type="integer")
     */
    private $modifiedBy;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $mergedintoId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $merged;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $status;

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

    public function getMergedintoId(): ?int
    {
        return $this->mergedintoId;
    }

    public function setMergedintoId(?int $mergedintoId): self
    {
        $this->mergedintoId = $mergedintoId;

        return $this;
    }

    public function getMerged(): ?int
    {
        return $this->merged;
    }

    public function setMerged(?int $merged): self
    {
        $this->merged = $merged;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }
}
