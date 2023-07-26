<?php

namespace App\Entity\Gso;

use App\Repository\Gso\BooksRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=BooksRepository::class)
 * 2023-07-12 the framework does not assign the correct database automatically (see doctrine.yaml and .env.local)
 * @ORM\Table(name="books", schema="gsdatenbank")
 */
class Books
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $nummer;

    /**
     * @ORM\Column(type="string", length=500, nullable=true)
     */
    private $titel;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $jahr;

    /**
     * @ORM\Column(type="string", length=300)
     */
    private $kurztitel;

    /**
     * @ORM\Column(type="string", length=200, nullable=true)
     */
    private $autoren;

    /**
     * @ORM\Column(type="string", length=200, nullable=true)
     */
    private $uri;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNummer(): ?string
    {
        return $this->nummer;
    }

    public function setNummer(?string $nummer): self
    {
        $this->nummer = $nummer;

        return $this;
    }

    public function getTitel(): ?string
    {
        return $this->titel;
    }

    public function setTitel(?string $titel): self
    {
        $this->titel = $titel;

        return $this;
    }

    public function getJahr(): ?string
    {
        return $this->jahr;
    }

    public function setJahr(?string $jahr): self
    {
        $this->jahr = $jahr;

        return $this;
    }

    public function getKurztitel(): ?string
    {
        return $this->kurztitel;
    }

    public function setKurztitel(string $kurztitel): self
    {
        $this->kurztitel = $kurztitel;

        return $this;
    }

    public function getAutoren(): ?string
    {
        return $this->autoren;
    }

    public function setAutoren(?string $autoren): self
    {
        $this->autoren = $autoren;

        return $this;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(?string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }
}
