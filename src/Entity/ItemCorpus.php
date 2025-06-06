<?php

namespace App\Entity;

use App\Repository\ItemCorpusRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemCorpusRepository::class)]
class ItemCorpus
{
    const CORPUS_ID_DEFAULT = 'can';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Item::class, inversedBy: 'itemCorpus')]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id')]
    private $item;

    #[ORM\Column(type: 'integer')]
    private $itemId;

    #[ORM\Column(type: 'string', length: 31)]
    private $corpusId;

    #[ORM\Column(type: 'string', length: 63, nullable: true)]
    private $idPublic;

    #[ORM\Column(type: 'string', length: 63)]
    private $idInCorpus;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setItem($item): self {
        $this->item = $item;

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

    public function getCorpusId(): ?string
    {
        return $this->corpusId;
    }

    public function setCorpusId(string $corpusId): self
    {
        $this->corpusId = $corpusId;

        return $this;
    }

    public function getIdInCorpusWithPrefix(): ?string
    {
        if (is_null($this->idInCorpus)) {
            return null;
        }
        return $this->corpusId.'-'.$this->idInCorpus;
    }


    public function getIdPublic(): ?string
    {
        return $this->idPublic;
    }

    public function setIdPublic(?string $idPublic): self
    {
        $this->idPublic = $idPublic;

        return $this;
    }

    public function getIdInCorpus(): ?string
    {
        return $this->idInCorpus;
    }

    public function setIdInCorpus(string $idInCorpus): self
    {
        $this->idInCorpus = $idInCorpus;

        return $this;
    }

    /**
     * provide this function to be compatible with item properties or references
     */
    public function getDeleteFlag(): ?string {
        return null;
    }
}
