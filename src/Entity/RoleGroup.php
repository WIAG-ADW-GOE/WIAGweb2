<?php

namespace App\Entity;

use App\Repository\RoleGroupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoleGroupRepository::class)]
class RoleGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 127)]
    private $name;

    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    private $nameEn;

    #[ORM\Column(type: 'string', length: 63)]
    private $factgridId;

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

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function setNameEn(?string $nameEn): self
    {
        $this->name_en = $nameEn;

        return $this;
    }

    public function getFactgridId(): ?string
    {
        return $this->factgridId;
    }

    public function setFactgridId(?string $factgridId): self
    {
        $this->factgridId = $factgridId;

        return $this;
    }
}
