<?php

namespace App\Entity;

use App\Repository\ItemNameRoleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ItemNameRoleRepository::class)
 */
class ItemNameRole
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemIdName;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemIdRole;

    /**
     * @ORM\Column(type="string", length=31)
     */
    private $corpusId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItemIdName(): ?int
    {
        return $this->itemIdName;
    }

    public function setItemIdName(int $itemIdName): self
    {
        $this->itemIdName = $itemIdName;

        return $this;
    }

    public function getItemIdRole(): ?int
    {
        return $this->itemIdRole;
    }

    public function setItemIdRole(int $itemIdRole): self
    {
        $this->itemIdRole = $itemIdRole;

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
}
