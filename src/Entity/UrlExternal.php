<?php

namespace App\Entity;

use App\Repository\UrlExternalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UrlExternalRepository::class)]
class UrlExternal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: \Item::class, inversedBy: 'urlExternal')]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id')]
    private $item;

    #[ORM\OneToOne(targetEntity: \Authority::class)]
    #[ORM\JoinColumn(name: 'authority_id', referencedColumnName: 'id')]
    private $authority;

    #[ORM\Column(type: 'integer')]
    private $itemId;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $note;

    #[ORM\Column(type: 'integer')]
    private $authorityId;

    #[ORM\Column(type: 'string', length: 255)]
    private $value;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $comment;

    /**
     * hold form data
     */
    private $deleteFlag;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthority() {
        return $this->authority;
    }

    public function setAuthority($authority): self {
        $this->authority = $authority;
        $this->authorityId = $authority->getId();
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

    public function setItem($item): self {
        $this->item = $item;
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

    public function getAuthorityId(): ?int
    {
        return $this->authorityId;
    }

    public function setAuthorityId(int $authorityId): self
    {
        $this->authorityId = $authorityId;

        return $this;
    }

    public function getValue(): ?string {
        return $this->value;
    }

    /**
     * check type before saving to the database, not here
     */
    public function setValue($value): self
    {
        $this->value = $value;

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
