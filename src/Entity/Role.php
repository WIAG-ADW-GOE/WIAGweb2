<?php

namespace App\Entity;

use App\Repository\RoleRepository;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;


/**
 * @ORM\Entity(repositoryClass=RoleRepository::class)
 */
class Role
{

    const CORPUS_ID = 'ofcm'; # officium

    const EDIT_FIELD_LIST = [
        'name',
        'plural',
        'gender',
        'lang',
        'genericTerm',
        'gsRegId',
        'note',
        'definition',
        'comment'
    ];

    /**
     * @ORM\OneToOne(targetEntity="Item", cascade={"persist"})
     * @ORM\JoinColumn(name="id", referencedColumnName="id")
     */
    private $item;

    /**
     * @ORM\OneToOne(targetEntity="RoleGroup")
     * @ORM\JoinColumn(name="role_group_id", referencedColumnName="id")
     */
    private $roleGroup;

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
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $note;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $gsRegId;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $genericTerm;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $plural;

    /**
     * @ORM\Column(type="string", length=1023, nullable=true)
     */
    private $definition;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $gender;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $roleGroupId;

    /**
     * @ORM\Column(type="string", length=31)
     */
    private $lang = "de";

    /**
     * no DB-mapping
     */
    private $referenceCount = 0;


    public function __construct($user_id) {
        $this->item = new Item($user_id);
    }

    public function setItem($item) {
        $this->item = $item;
        $this->id = $item->getId();
        return $this;
    }

    public function getItem() {
        return $this->item;
    }

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

    public function setName(?string $name): self
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

    public function getGsRegId() {
        return $this->gsRegId;
    }

    /**
     * check type before saving to the database, not here
     */
    public function setGsRegId($gsRegId): self
    {
        $this->gsRegId = $gsRegId;

        return $this;
    }

    public function getGenericTerm(): ?string
    {
        return $this->genericTerm;
    }

    public function setGenericTerm(?string $genericTerm): self
    {
        $this->genericTerm = $genericTerm;

        return $this;
    }

    public function getPlural(): ?string
    {
        return $this->plural;
    }

    public function setPlural(?string $plural): self
    {
        $this->plural = $plural;

        return $this;
    }

    public function getDefinition(): ?string
    {
        return $this->definition;
    }

    public function setDefinition(?string $definition): self
    {
        $this->definition = $definition;

        return $this;
    }

    public function getRoleGroupId()
    {
        return $this->roleGroupId;
    }

    public function setRoleGroupId(?string $roleGroupId): self
    {
        $this->roleGroupId = $roleGroupId;

        return $this;
    }

    public function getRoleGroup()
    {
        return $this->roleGroup;
    }

    public function setRoleGroup($roleGroup)
    {
        $this->roleGroup = $roleGroup;
        return $this;
    }


    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setLang(?string $lang): self
    {
        $this->lang = $lang;

        return $this;
    }

    public function setReferenceCount($value): self {
        $this->referenceCount = $value;
        return $this;
    }

    public function getReferenceCount() {
        return $this->referenceCount;
    }

    /**
     * provide direct access
     */
    public function getIdInSource() {
        return $this->getItem()->getIdInSource();
    }

}
