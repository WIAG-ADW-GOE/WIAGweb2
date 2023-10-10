<?php

namespace App\Entity;

use App\Repository\AuthorityRepository;
use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity(repositoryClass=AuthorityRepository::class)
 */
class Authority {

    const ID = [
        'GND' => 1,
        'Wikidata' => 2,
        'Wikipedia' => 3,
        'VIAF' => 4,
        'WIAG-ID' => 5,
        'GSN' => 200,
        'World Historical Gazetteer' => 54,
    ];

    const ESSENTIAL_ID_LIST = [1, 2, 3, 200];

    const EDIT_FIELD_LIST = [
        'urlNameFormatter',
        'urlType',
        'urlFormatter',
        'urlValueExample',
        'url',
        'displayOrder',
        'comment'
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToMany(targetEntity="RefIdExternal", mappedBy="authority")
     * @ORM\JoinColumn(name="id", referencedColumnName="authority_id")
     */
    private $refIdExternal;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private $url;

    /**
     * @ORM\Column(type="string", length=127)
     */
    private $urlFormatter;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $urlType;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private $urlNameFormatter;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private $urlValueExample;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $displayOrder;

    /**
     * no DB-mapping
     * hold form input data
     */
    private $formIsEdited = false;

    /**
     * no DB-mapping
     * hold form input data
     */
    private $formIsExpanded = false;

    /**
     * collection of InputError
     */
    private $inputError;

    private $referenceCount = 0;

    public function __construct() {
        $this->inputError = new ArrayCollection();
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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getUrlFormatter(): ?string
    {
        return $this->urlFormatter;
    }

    public function setUrlFormatter(?string $urlFormatter): self
    {
        $this->urlFormatter = $urlFormatter;

        return $this;
    }

    public function getUrlType(): ?string
    {
        return $this->urlType;
    }

    public function setUrlType(?string $urlType): self
    {
        $this->urlType = $urlType;

        return $this;
    }

    public function getUrlNameFormatter(): ?string
    {
        return $this->urlNameFormatter;
    }

    public function setUrlNameFormatter(?string $urlNameFormatter): self
    {
        $this->urlNameFormatter = $urlNameFormatter;

        return $this;
    }

    public function getUrlValueExample(): ?string
    {
        return $this->urlValueExample;
    }

    public function setUrlValueExample(?string $urlValueExample): self
    {
        $this->urlValueExample = $urlValueExample;

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

    public function getNameShort() {
        $short = array_flip(self::ID);
        if (array_key_exists($this->id, $short)) {
            return $short[$this->id];
        } else {
            return "";
        }
    }

    public function setFormIsEdited($value): self {
        $this->formIsEdited = $value;
        return $this;
    }

    public function getFormIsEdited() {
        return $this->formIsEdited;
    }

    public function setFormIsExpanded($value): self {
        $this->formIsExpanded = $value;
        return $this;
    }

    public function getFormIsExpanded() {
        return $this->formIsExpanded;
    }

    public function getReferenceCount(): int {
        return $this->referenceCount;
    }

    public function setReferenceCount($count) {
        $this->referenceCount = $count;
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

    public function hasError($min_level): bool {
        // the database is not aware of inputError and it's type
        if (is_null($this->inputError)) {
            return false;
        }

        foreach($this->inputError as $e_loop) {
            $level = $e_loop->getLevel();
            if (in_array($level, InputError::ERROR_LEVEL[$min_level])) {
                return true;
            }
        }
        return false;
    }


}
