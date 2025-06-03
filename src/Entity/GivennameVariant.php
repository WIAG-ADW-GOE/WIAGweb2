<?php

namespace App\Entity;

use App\Repository\GivennameVariantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GivennameVariantRepository::class)]
class GivennameVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer')]
    private $personId;

    #[ORM\ManyToOne(targetEntity: Person::class, inversedBy: 'givennameVariants')]
    #[ORM\JoinColumn(name: 'person_id', referencedColumnName: 'id')]
    private $person;

    #[ORM\Column(type: 'string', length: 127)]
    private $name;

    #[ORM\Column(type: 'string', length: 31)]
    private $lang;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setLang(string $lang): self
    {
        $this->lang = $lang;

        return $this;
    }

    public function setPerson(?Person $person): self {
        $this->person = $person;
        return $this;
    }

    public function __toString(): string {
        return $this->getName();
    }

}
