<?php

namespace App\Entity;

use App\Repository\CanonSortRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CanonSortRepository::class)
 */
class CanonSort
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $domstiftShort;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $dateSortKey;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDomstiftShort(): ?string
    {
        return $this->domstiftShort;
    }

    public function setDomstiftShort(?string $domstiftShort): self
    {
        $this->domstiftShort = $domstiftShort;

        return $this;
    }

    public function getDateSortKey(): ?int
    {
        return $this->dateSortKey;
    }

    public function setDateSortKey(?int $dateSortKey): self
    {
        $this->dateSortKey = $dateSortKey;

        return $this;
    }
}
