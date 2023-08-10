<?php

namespace App\Entity;

use App\Repository\CorpusRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CorpusRepository::class)
 */
class Corpus
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=63)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=511, nullable=true)
     */
    private $note;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $idPublicKey;

    /**
     * @ORM\Column(type="string", length=31)
     */
    private $corpusId;

    /**
     * @ORM\Column(type="string", length=511, nullable=true)
     */
    private $comment;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }

    public function getIdPublicKey(): ?string
    {
        return $this->idPublicKey;
    }

    public function setIdPublicKey(?string $idPublicKey): self
    {
        $this->idPublicKey = $idPublicKey;

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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }
}
