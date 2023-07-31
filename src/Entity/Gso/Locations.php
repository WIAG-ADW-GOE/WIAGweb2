<?php

namespace App\Entity\Gso;

use App\Repository\Gso\LocationsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=LocationsRepository::class)
 * 2023-07-12 the framework does not assign the correct database automatically (see doctrine.yaml and .env.local)
 * @ORM\Table(name="locations", schema="gsdatenbank")
 */
class Locations
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Items", inversedBy="reference")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id")
     */
    private $item;

    /**
     * @ORM\OneToOne(targetEntity="Books")
     * @ORM\JoinColumn(name="book_id", referencedColumnName="id")
     */
    private $referenceVolume;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $itemMergedintoId;

    /**
     * @ORM\Column(type="integer")
     */
    private $bookId;

    /**
     * @ORM\Column(type="string", length=1000)
     */
    private $seiten;

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

    public function getItemMergedintoId(): ?int
    {
        return $this->itemMergedintoId;
    }

    public function setItemMergedintoId(?int $itemMergedintoId): self
    {
        $this->itemMergedintoId = $itemMergedintoId;

        return $this;
    }

    public function getBookId(): ?int
    {
        return $this->bookId;
    }

    public function setBookId(int $bookId): self
    {
        $this->bookId = $bookId;

        return $this;
    }

    public function getReferenceVolume() {
        return $this->referenceVolume;
    }

    public function getSeiten(): ?string
    {
        return $this->seiten;
    }

    public function setSeiten(string $seiten): self
    {
        $this->seiten = $seiten;

        return $this;
    }
}
