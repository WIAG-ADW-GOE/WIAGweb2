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
     * @ORM\OneToOne(targetEntity="Person")
     * @ORM\JoinColumn(name="item_id_role", referencedColumnName="id")
     */
    private $personRole;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemIdName;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemIdRole;

    public function __construct($item_id_name, $item_id_role) {
        $this->itemIdName = $item_id_name;
        $this->itemIdRole = $item_id_role;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setItem($item): self {
        $this->item = $item;
        return $this;
    }

    public function setPersonRole($person) {
        $this->personRole = $person;
        return $this;
    }

    public function getPersonRole() {
        return $this->personRole;
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
