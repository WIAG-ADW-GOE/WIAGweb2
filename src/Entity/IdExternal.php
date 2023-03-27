<?php

namespace App\Entity;

use App\Repository\IdExternalRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=IdExternalRepository::class)
 */
class IdExternal
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
    private $itemId;

    /**
     * @ORM\ManyToOne(targetEntity="Item", inversedBy="idExternal")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id")
     */
    private $item;

    /**
     * @ORM\Column(type="integer")
     */
    private $authorityId;

    /**
     * set this manually join column: authority_id
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

    /**
     * hold form data
     */
    private $deleteFlag;

    public function setItem($item): self {
        $this->item = $item;
        return $this;
    }

    public function setAuthority($authority): self {
        $this->authority = $authority;
        return $this;
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

    public function getItemId(): ?int
    {
        return $this->itemId;
    }

    public function setItemId(int $itemId): self
    {
        $this->itemId = $itemId;

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

    public function getAuthority() {
        return $this->authority;
    }

    public function getValue(): ?string {
        return $this->value;
    }

    public function getPrettyValue(): ?string
    {
        if (!$this->value) {
            return null;
        }
        $val_elts = explode('/',$this->value);
        $value = end($val_elts);
        $prettyValue = urldecode($value);
        $prettyValue = str_replace('_', ' ', $prettyValue);

        return $prettyValue;
    }

    public function getUrl(): ?string {
        if (!$this->value) {
            return null;
        }
        // check if the complete URL is stored in `value`.
        if (str_starts_with($this->value, "http")) {
            return $this->value;
        } else {
            return $this->authority->getUrlFormatter().$this->value;
        }
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
