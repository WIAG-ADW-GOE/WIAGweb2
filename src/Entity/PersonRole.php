<?php

namespace App\Entity;

use App\Repository\PersonRoleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PersonRoleRepository::class)
 */
class PersonRole
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
     * @ORM\Column(type="integer")
     */
    private $personId;

    /**
     * @ORM\ManyToOne(targetEntity="Person", inversedBy="role")
     */
    private $person;

    /**
     * @ORM\ManyToOne(targetEntity="Item", inversedBy="personRole")
     * @ORM\JoinColumn(name="person_id", referencedColumnName="id")
     */
    private $item;

    /**
     * @ORM\Column(type="integer")
     */
    private $roleId;

    /**
     * @ORM\ManyToOne(targetEntity="Role")
     */
    private $role;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private $roleName;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $dioceseId;

    /**
     * @ORM\OneToOne(targetEntity="Diocese")
     */
    private $diocese;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $dioceseName;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $dateBegin;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $dateEnd;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $numDateBegin;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $numDateEnd;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $institutionId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $displayOrder;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $note;

    /**
     * no db mapping
     */
    private $placeName = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItem()
    {
        return $this->item;
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

    public function getPersonId(): ?int
    {
        return $this->personId;
    }

    public function setPersonId(int $personId): self
    {
        $this->personId = $personId;

        return $this;
    }

    public function getPerson() {
        return $this->person;
    }

    public function getRoleId(): ?int
    {
        return $this->roleId;
    }

    public function setRoleId(int $roleId): self
    {
        $this->roleId = $roleId;

        return $this;
    }

    public function getRole() {
        return $this->role;
    }

    public function getRoleName(): ?string
    {
        return $this->roleName;
    }

    public function setRoleName(?string $roleName): self
    {
        $this->roleName = $roleName;

        return $this;
    }

    public function getDioceseId(): ?int
    {
        return $this->dioceseId;
    }

    public function setDioceseId(?int $dioceseId): self
    {
        $this->dioceseId = $dioceseId;

        return $this;
    }

    public function getDiocese() {
        return $this->diocese;
    }

    public function getDioceseName(): ?string
    {
        return $this->dioceseName;
    }

    public function setDioceseName(?string $dioceseName): self
    {
        $this->dioceseName = $dioceseName;

        return $this;
    }

    public function getDateBegin(): ?string
    {
        return $this->dateBegin;
    }

    public function setDateBegin(?string $dateBegin): self
    {
        $this->dateBegin = $dateBegin;

        return $this;
    }

    public function getDateEnd(): ?string
    {
        return $this->dateEnd;
    }

    public function setDateEnd(?string $dateEnd): self
    {
        $this->dateEnd = $dateEnd;

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

    public function getInstitutionId(): ?int
    {
        return $this->institutionId;
    }

    public function setInstitutionId(?int $institutionId): self
    {
        $this->institutionId = $institutionId;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(?int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;

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

    public function setPlaceName(string $placeName): self
    {
        $this->placeName = $placeName;
        return $this;
    }

    public function getPlaceName(): ?string
    {
        return $this->placeName;
    }
}
