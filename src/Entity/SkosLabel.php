<?php

namespace App\Entity;

use App\Repository\SkosLabelRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SkosLabelRepository::class)
 */
class SkosLabel
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
    private $skosSchemeId;

    /**
     * @ORM\Column(type="integer")
     */
    private $conceptId;

    /**
     * @ORM\ManyToOne(targetEntity="Diocese", inversedBy="altLabels")
     * @ORM\JoinColumn(name="concept_id", referencedColumnName="id")
     */
    private $diocese;

    /**
     * @ORM\Column(type="string", length=127)
     */
    private $label;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isPreferred;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $lang;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $displayOrder;

    /**
     * hold form data
     */
    private $deleteFlag;

    public function __construct($skosSchemeId) {
        $this->skosSchemeId = $skosSchemeId;
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

    public function getSkosSchemeId(): ?int
    {
        return $this->skosSchemeId;
    }

    public function setSkosSchemeId(int $skosSchemeId): self
    {
        $this->skosSchemeId = $skosSchemeId;

        return $this;
    }

    public function getConceptId(): ?int
    {
        return $this->conceptId;
    }

    public function setConceptId(int $conceptId): self
    {
        $this->conceptId = $conceptId;

        return $this;
    }

    public function getDiocese() {
        return $this->diocese;
    }

    public function setDiocese($diocese): self
    {
        $this->diocese = $diocese;

        return $this;
    }


    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel($label): self
    {
        $this->label = $label;

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

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setLang(?string $lang): self
    {
        $this->lang = $lang;

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
