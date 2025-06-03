<?php

namespace App\Entity\Gso;

use App\Service\UtilService;

use App\Repository\Gso\ItemsRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ItemsRepository::class)]
class Items
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\OneToMany(targetEntity: Gsn::class, mappedBy: 'item')]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'item_id')]
    private $gsn;

    #[ORM\OneToMany(targetEntity: Locations::class, mappedBy: 'item')]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'item_id')]
    private $reference;

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

    #[ORM\Column(type: 'integer', nullable: true)]
    private $mergedintoId;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $merged;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
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

    public function getGsn() {
        return $this->gsn;
    }

    public function hasGsn($gsn) {
        foreach ($this->gsn as $gsn_loop) {
            if ($gsn_loop->getNummer() == $gsn) {
                return true;
            }
        }
        return false;
    }

    /**
     * return current GSN
     */
    public function getCurrentGsn() {
        if ($this->gsn->isEmpty()) {
            return null;
        }

        $gsn_active = $this->gsn->filter(function($v) {
            return !$v->isDeleted();
        });

        if ($gsn_active->isEmpty()) {
            return null;
        }

        $gsn_sorted = UtilService::sortByFieldList($gsn_active->toArray(), ['id']);
        $current_gsn = array_values($gsn_sorted)[0];
        return $current_gsn->getNummer();
    }

    /**
     * compatible with class Item
     */
    public function getIdPublicVisible() {
        return null;
    }

    /**
     * compatible with class Item
     */
    public function getUrlExternalObj($authorityIdOrName) {
        return null;
    }

    /**
     * compatible with class Item
     */
    public function getInputError() {
        return null;
    }


    public function getReference() {
        return $this->reference;
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
