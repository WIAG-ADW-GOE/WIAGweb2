<?php

namespace App\Entity;

use App\Repository\LangRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LangRepository::class)]
class Lang
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 63)]
    private $name;

    #[ORM\Column(type: 'string', length: 63, nullable: true)]
    private $nameIso;

    #[ORM\Column(type: 'string', length: 31)]
    private $isoKey;

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

    public function getNameIso(): ?string
    {
        return $this->nameIso;
    }

    public function setNameIso(?string $nameIso): self
    {
        $this->nameIso = $nameIso;

        return $this;
    }

    public function getIsoKey(): ?string
    {
        return $this->isoKey;
    }

    public function setIsoKey(string $iso): self
    {
        $this->isoKey = $iso;

        return $this;
    }
}
