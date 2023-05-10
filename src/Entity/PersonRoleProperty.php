<?php

namespace App\Entity;

use App\Repository\PersonRolePropertyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PersonRolePropertyRepository::class)
 */
class PersonRoleProperty
{
    /**
     * @ORM\ManyToOne(targetEntity="PersonRole", inversedBy="roleProperty")
     */
    private $personRole;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id = 0;

    /**
     * @ORM\OneToOne(targetEntity="RolePropertyType", fetch="EAGER")
     * @ORM\JoinColumn(name="property_type_id", referencedColumnName="id")
     */
    private $type;

    /**
     * @ORM\Column(type="integer")
     */
    private $personRoleId;

    /**
     * @ORM\Column(type="integer")
     */
    private $personId;


    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="string", length=63)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=511)
     */
    private $value;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $note;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $propertyTypeId;

    /**
     * hold form data
     */
    private $deleteFlag = "";

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType() {
        return $this->type;
    }

    public function setType($type): self {
        $this->type = $type;
        return $this;
    }

    public function setPersonRole($role): self {
        $this->personRole = $role;
        return $this;
    }

    public function getPersonRoleId(): ?int
    {
        return $this->personRoleId;
    }

    public function setPersonRoleId(int $personRoleId): self
    {
        $this->personRoleId = $personRoleId;

        return $this;
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
