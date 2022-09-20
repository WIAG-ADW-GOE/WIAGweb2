<?php

namespace App\Entity;

use App\Repository\RefIdExternalRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RefIdExternalRepository::class)
 */
class RefIdExternal
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
    private $referenceId;

    /**
     * @ORM\ManyToOne(targetEntity="ReferenceVolume", inversedBy="idsExternal")
     * @ORM\JoinColumn(name="reference_id", referencedColumnName="id")
     */
    private $reference;

    /**
     * @ORM\Column(type="integer")
     */
    private $authorityId;

    /**
     * @ORM\ManyToOne(targetEntity="Authority", inversedBy="refIdExternal")
     * @ORM\JoinColumn(name="authority_id", referencedColumnName="id")
     */
    private $authority;

    /**
     * @ORM\Column(type="string", length=127)
     */
    private $value;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $note;

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

    public function getReferenceId(): ?int
    {
        return $this->referenceId;
    }

    public function setReferenceId(int $referenceId): self
    {
        $this->referenceId = $referenceId;

        return $this;
    }

    public function getAuthorityId(): ?int
    {
        return $this->authorityId;
    }

    public function setAuthorityId(int $authorityId): self
    {
        $this->authorityId = $authorityId;

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

    public function getAuthority() {
        return $this->authority;
    }

}
