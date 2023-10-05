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
     * @ORM\ManyToOne(targetEntity="Item", inversedBy="itemNameRole")
     * @ORM\JoinColumn(name="item_id_name", referencedColumnName="id")
     */
    private $item;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemIdName;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemIdRole;


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

}
