<?php

namespace App\Entity;

use App\Repository\ItemReferenceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ItemReferenceRepository::class)
 */
class ItemReference
{

    /**
     * @ORM\ManyToOne(targetEntity="Item", inversedBy="reference")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id")
     */
    private $item;

    /**
     * set this manually join columns: item_type_id, reference_id
     */
    private $referenceVolume;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $note;

    /**
     * @ORM\Column(type="string", length=511, nullable=true)
     */
    private $page;


    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $idInReference;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isPreferred;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemId;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemTypeId;

    /**
     * @ORM\Column(type="integer")
     */
    private $referenceId;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPage(): ?string
    {
        return $this->page;
    }

    public function setPage(?string $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function getReferenceVolume() {
        return $this->referenceVolume;
    }


    public function setReferenceVolume($referenceVolume) {
        return $this->referenceVolume = $referenceVolume;
    }

    public function getIdInReference(): ?string
    {
        return $this->idInReference;
    }

    public function setIdInReference(?string $idInReference): self
    {
        $this->idInReference = $idInReference;

        return $this;
    }

    public function getIsPreferred(): ?bool
    {
        return $this->isPreferred;
    }

    public function setIsPreferred(?bool $isPreferred): self
    {
        $this->isPreferred = $isPreferred;

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

    public function getItemTypeId(): ?int
    {
        return $this->itemTypeId;
    }

    public function getReferenceId(): ?int
    {
        return $this->referenceId;
    }


}
