<?php

namespace App\Entity;

use App\Repository\ItemPropertyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ItemPropertyRepository::class)
 */
class ItemProperty
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Item", inversedBy="itemProperty")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id")
     */
    private $item;

    /**
     * @ORM\OneToOne(targetEntity="ItemPropertyType", fetch="EAGER")
     * @ORM\JoinColumn(name="property_type_id", referencedColumnName="id")
     */
    private $type;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="string", length=63)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $value;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $dateValue;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $propertyTypeId;

    /**
     * hold form input data
     */
    private $deleteFlag;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function setItem($item): self {
        $this->item = $item;

        return $this;
    }

    public function getType() {
        return $this->type;
    }

    public function setType($type): self {
        $this->type = $type;
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

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getDateValue(): ?\DateTimeInterface
    {
        return $this->dateValue;
    }

    public function setDateValue(?\DateTimeInterface $date_value): self
    {
        $this->dateValue = $date_value;

        return $this;
    }

    public function getPropertyTypeId(): ?int
    {
        return $this->propertyTypeId;
    }

    public function setPropertyTypeId(?int $propertyTypeId): self
    {
        $this->propertyTypeId = $propertyTypeId;

        return $this;
    }

    public function getDeleteFlag(): ?string
    {
        return $this->deleteFlag;
    }

    public function setDeleteFlag(?string $deleteFlag): self
    {
        $this->deleteFlag = $deleteFlag;

        return $this;
    }

}
