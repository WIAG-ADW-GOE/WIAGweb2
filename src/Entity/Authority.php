<?php

namespace App\Entity;

use App\Repository\AuthorityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AuthorityRepository::class)
 */
class Authority {

    const CORE = [
        'GND',
        'GS',
        'Wikidata',
        'Wikipedia',
    ];

    const ID = [
        'GND' => 1,
        'Wikidata' => 2,
        'Wikipedia' => 3,
        'VIAF' => 4,
        'WIAG-ID' => 5,
        'GS' => 200,
        'World Historical Gazetteer' => 54,
    ];

    static public function coreIDs() {
        return array_map(function($v) {
            return self::ID[$v];
        }, self::CORE);
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToMany(targetEntity="RefIdExternal", mappedBy="authority")
     * @ORM\JoinColumn(name="id", referencedColumnName="authority_id")
     */
    private $refIdExternal;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private $url;

    /**
     * @ORM\Column(type="string", length=127)
     */
    private $urlFormatter;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $urlType;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private $urlNameFormatter;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private $urlValueExample;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $displayOrder;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getUrlFormatter(): ?string
    {
        return $this->urlFormatter;
    }

    public function setUrlFormatter(string $urlFormatter): self
    {
        $this->urlFormatter = $urlFormatter;

        return $this;
    }

    public function getUrlType(): ?string
    {
        return $this->urlType;
    }

    public function setUrlType(?string $urlType): self
    {
        $this->urlType = $urlType;

        return $this;
    }

    public function getUrlNameFormatter(): ?string
    {
        return $this->urlNameFormatter;
    }

    public function setUrlNameFormatter(?string $urlNameFormatter): self
    {
        $this->urlNameFormatter = $urlNameFormatter;

        return $this;
    }

    public function getUrlValueExample(): ?string
    {
        return $this->urlValueExample;
    }

    public function setUrlValueExample(?string $urlValueExample): self
    {
        $this->urlValueExample = $urlValueExample;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(?int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }
}
