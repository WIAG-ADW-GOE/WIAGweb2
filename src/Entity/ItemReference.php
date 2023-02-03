<?php

namespace App\Entity;

use App\Repository\ItemReferenceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ItemReferenceRepository::class)
 */
class ItemReference
{

    /**
     * @ORM\ManyToOne(targetEntity="Item", inversedBy="reference")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id")
     */
    private $item;

    /**
     * set this manually join columns: item_type_id, reference_id
     */
    private $referenceVolume;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $note;

    /**
     * @ORM\Column(type="string", length=511, nullable=true)
     */
    private $page;


    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $idInReference;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isPreferred;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemId;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemTypeId;

    /**
     * @ORM\Column(type="integer")
     */
    private $referenceId;

    /**
     * hold form input data
     */
    private $volumeTitleShort;

    /**
     * hold form input data
     */
    private $deleteFlag;

    public function __construct($id = 0) {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }

    public function getPage(): ?string
    {
        return $this->page;
    }

    /**
     * @return page without markup
     */
    public function getPagePlain(): ?string
    {
        return preg_replace('~</?[a-z]+>~', '', $this->page);
    }


    public function setPage(?string $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function getReferenceVolume() {
        return $this->referenceVolume;
    }


    public function setReferenceVolume($referenceVolume) {
        return $this->referenceVolume = $referenceVolume;
    }

    public function getIdInReference(): ?string
    {
        return $this->idInReference;
    }

    public function setIdInReference(?string $idInReference): self
    {
        $this->idInReference = $idInReference;

        return $this;
    }

    public function getIsPreferred(): ?bool
    {
        return $this->isPreferred;
    }

    public function setIsPreferred(?bool $isPreferred): self
    {
        $this->isPreferred = $isPreferred;

        return $this;
    }

    public function setItem($item): self {
        $this->item = $item;
        return $this;
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

    public function setItemTypeId(int $itemTypeId): self
    {
        $this->itemTypeId = $itemTypeId;

        return $this;
    }

    public function getItemTypeId(): ?int
    {
        return $this->itemTypeId;
    }

    public function setReferenceId(int $referenceId): self {
        $this->referenceId = $referenceId;

        return $this;
    }

    public function getReferenceId(): ?int
    {
        return $this->referenceId;
    }

    public function setVolumeTitleShort(string $volumeTitleShort): self {
        $this->volumeTitleShort = $volumeTitleShort;

        return $this;
    }

    public function getVolumeTitleShort(): ?string
    {
        return $this->volumeTitleShort;
    }

    public function getDeleteFlag(): ?string
    {
        return $this->deleteFlag;
    }

    public function setDeleteFlag(?string $deleteFlag): self
    {
        $this->deleteFlag = $deleteFlag;

        return $this;
    }


    public function containsBio() {
        $cpage = $this->splitPage();
        $value = false;

        foreach ($cpage as $p) {
            if ($p['isbio']) {
                return true;
            }
        }

        return $value;
    }


    /**
     * check if a reference contains a biogram
     * return list of pages
     */
    public function splitPage() {
        $s = $this->page;
        if (is_null($s)) {
            return array();
        }

        $cs = array();
        preg_match_all("~<b>.*?</b>|[0-9f\.–-]+~", $s, $cs);
        $cs = array_map('trim', $cs[0]);


        $cpage = [];
        $matches = [];
        foreach ($cs as $es) {
            $matches = [];
            preg_match("~<b>(.*)</b>~", $es, $matches);
            $isbio = count($matches) > 1;
            $page = $isbio ? $matches[1] : $es;
            $cpage[] = [
                'page' => $page,
                'isbio' => $isbio,
            ];
        }

        return $cpage;
    }

}
