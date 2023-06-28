<?php

namespace App\Entity;

use App\Entity\InputError;
use App\Repository\ItemPropertyTypeRepository;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


/**
 * @ORM\Entity(repositoryClass=ItemPropertyTypeRepository::class)
 */
class ItemPropertyType
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=63)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $displayOrder;

    /**
     * no db mapping
     */
    private $referenceCount = 0;

    /**
     * no db mapping
     */
    private $deleteFlag = false;

    /**
     * no db mapping
     */
    private $isNew = false;

    /**
     * no db mapping
     */
    private $isEdited = false;

    /**
     * no db mapping
     */
    private $inputError;

    public function __construct() {
        $this->inputError = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }


    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

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

    public function getDisplayOrder()
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder($displayOrder): self
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    public function getReferenceCount(): int {
        return $this->referenceCount;
    }

    public function setReferenceCount($count) {
        $this->referenceCount = $count;
        return $this;
    }

    public function getDeleteFlag(): bool {
        return $this->deleteFlag;
    }

    public function setDeleteFlag($value) {
        $this->deleteFlag = $value;
        return $this;
    }

    public function getIsNew(): bool {
        return $this->isNew;
    }

    public function setIsNew($value) {
        $this->isNew = $value;
        return $this;
    }

    public function getIsEdited(): bool {
        return $this->isEdited;
    }

    public function setIsEdited($value) {
        $this->isEdited = $value;
        return $this;
    }

    public function getInputError() {
        if (is_null($this->inputError)) {
            $this->inputError = new ArrayCollection();
        }
        return $this->inputError;
    }

}
