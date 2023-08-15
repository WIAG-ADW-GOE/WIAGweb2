<?php

namespace App\Entity;

use App\Repository\ItemDregRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ItemDregRepository::class)
 */
class ItemDreg
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
    private $itemId;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemIdDreg;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getItemIdDreg(): ?int
    {
        return $this->itemIdDreg;
    }

    public function setItemIdDreg(int $itemIdDreg): self
    {
        $this->itemIdDreg = $itemIdDreg;

        return $this;
    }
}
