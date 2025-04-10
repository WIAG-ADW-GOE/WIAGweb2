<?php

namespace App\Entity;

use App\Entity\Item;
use App\Repository\PersonRoleRepository;
use App\Service\UtilService;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


/**
 * @ORM\Entity(repositoryClass=PersonRoleRepository::class)
 */
class PersonRole
{
    const MAX_DATE_SORT_KEY = 9000900;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToMany(targetEntity="PersonRoleProperty", mappedBy="personRole")
     * @ORM\JoinColumn(name="id", referencedColumnName="person_role_id")
     */
    private $roleProperty;

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
     * @ORM\OneToOne(targetEntity="Institution")
     */
    private $institution;

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
     * @ORM\Column(type="string", length=255, nullable=true)
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
     * @ORM\Column(type="integer", nullable=true)
     */
    private $uncertain;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $note;

    /**
     * no db mapping
     */
    private $placeName = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $institutionName;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $dateSortKey;

    /**
     * store form data
     */
    private $deleteFlag;

    /**
     * store form input errors
     */
    private $inputError;


    public function __construct() {
        $this->roleProperty = new ArrayCollection();
        $this->inputError = new ArrayCollection();
    }

    public function getRoleProperty()
    {
        return $this->roleProperty;
    }

    public function setRoleProperty($property): self
    {
        $this->roleProperty = $property;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self {
        $this->id = $id;
        return $this;
    }

    public function getInstitution() {
        return $this->institution;
    }

    public function setInstitution(?Institution $institution): self {
        $this->institution = $institution;
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

    public function setPerson(?Person $person): self {
        $this->person = $person;
        return $this;
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

    public function setRole(?Role $role): self {
        $this->role = $role;
        return $this;
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

    public function getDisplayRoleName(): ?string {
        $role_txt = null;
        if (!is_null($this->role)) {
            $role_txt = $this->role->getName();
        } elseif (!is_null($this->roleName) and trim($this->roleName) != "") {
            $role_txt = $this->roleName;
        }
        if ($this->uncertain > 0) {
            $role_txt = $role_txt." ?";
        }
        return $role_txt;
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

    public function setDiocese(?Diocese $diocese): self {
        $this->diocese = $diocese;
        return $this;
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

    /**
     * return preferred type to select the institution or diocese
     */
    public function getInstitutionCorpusIdHint(): ?string {
        if ($this->institution or (!is_null($this->institutionName) and $this->institutionName != "")) {
            return 'mon';
        } elseif ($this->diocese or (!is_null($this->dioceseName) and $this->dioceseName != "")) {
            return 'dioc';
        } else {
            return null;
        }
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

    public function getUncertain(): ?int
    {
        return $this->uncertain;
    }

    public function setUncertain(int $uncertain): self
    {
        $this->uncertain = $uncertain;

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

    public function setPlaceName($placeName): self
    {
        $this->placeName = $placeName;
        return $this;
    }

    public function getPlaceName()
    {
        return $this->placeName;
    }

    public function getInstitutionName(): ?string
    {
        return $this->institutionName;
    }

    public function setInstitutionName(?string $institutionName): self
    {
        $this->institutionName = $institutionName;

        return $this;
    }

    public function getDateSortKey(): ?int
    {
        return $this->dateSortKey;
    }

    public function setDateSortKey(?int $dateSortKey): self
    {
        $this->dateSortKey = $dateSortKey;

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

    /**
     * do not provide setInputError; use add or remove to manipulate this property
     */
    public function getInputError() {
        if (is_null($this->inputError)) {
            $this->inputError = new ArrayCollection;
        }
        return $this->inputError;
    }

    public function roleDisplayName(): ?string {
        $name = null;
        if($this->role && $this->role->getName()) {
            $name = $this->role->getName();
        } else {
            $name = $this->roleName;
        }
        return $name;
    }

    public function dioceseDisplayName(): ?string {
        $name = null;
        if($this->diocese && $this->diocese->getName()) {
            $name = $this->diocese->getName();
        } else {
            $name = $this->dioceseName;
        }
        return $name;
    }

    /**
     * Returns institution name
     */
    public function institutionDisplayName(): ?string {
        $name = null;
        if ($this->institution && $this->institution->getName()) {
            $name = $this->institution->getName();
        } else {
            $name = $this->institutionName;
        }
        return $name;
    }


    /**
     * compose string containing basic information
     */
    public function describe(): string {

        $role_name = $this->roleDisplayName();

        $inst_or_dioc = null;
        if($this->institution){
            $inst_or_dioc = $this->institution->getName();
        } elseif($this->institutionName) {
            $inst_or_dioc = $this->institutionName;
        } elseif($this->diocese) {
            $inst_or_dioc = $this->diocese->getName();
        } elseif($this->dioceseName) {
            $inst_or_dioc = $this->dioceseName;
        }


        $date_info = null;
        if($this->dateBegin && $this->dateEnd) {
            $date_info = $this->dateBegin.'-'.$this->dateEnd;
        } elseif($this->dateBegin) {
            $date_info = $this->dateBegin;
        } elseif($this->dateEnd) {
            $date_info = 'bis '.$this->dateEnd;
        }

        $description = '';
        if($role_name) {
            $description = $role_name;
        }
        if($inst_or_dioc) {
            $description = $description.' '.$inst_or_dioc;
        }
        if($date_info) {
            $description = $description.' '.$date_info;
        }

        return $description;
    }

    /**
     *
     */
    public function isAtInstitutionList($inst_id_list) {
        $inst_id = null;
        if (!is_null($this->institutionId)) {
            $inst_id = $this->institutionId;
        } elseif (!is_null($this->institution)) {
            $inst_id = $this->institution->getId();
        }

        return (!is_null($inst_id)
                and in_array($inst_id, $inst_id_list));
    }

}
