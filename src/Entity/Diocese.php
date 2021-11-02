<?php

namespace App\Entity;

use App\Repository\DioceseRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DioceseRepository::class)
 */
class Diocese
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="Item")
     * @ORM\JoinColumn(name="id", referencedColumnName="id")
     */
    private $item;

    /**
     * @ORM\Column(type="string", length=1023, nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=1023, nullable=true)
     */
    private $note;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $ecclesiasticalProvince;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $dioceseStatus;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $shapefile;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $bishopricSeat;

    /**
     * @ORM\Column(type="string", length=1023, nullable=true)
     */
    private $noteBishopricSeat;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $dateOfFounding;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $dateOfDissolution;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $commentAuthorityFile;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isAltesReich;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isDioceseGs;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItem() {
        return $this->item;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
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

    public function getEcclesiasticalProvince(): ?string
    {
        return $this->ecclesiasticalProvince;
    }

    public function setEcclesiasticalProvince(?string $ecclesiasticalProvince): self
    {
        $this->ecclesiasticalProvince = $ecclesiasticalProvince;

        return $this;
    }

    public function getDioceseStatus(): ?string
    {
        return $this->dioceseStatus;
    }

    public function setDioceseStatus(?string $dioceseStatus): self
    {
        $this->dioceseStatus = $dioceseStatus;

        return $this;
    }

    public function getShapefile(): ?string
    {
        return $this->shapefile;
    }

    public function setShapefile(?string $shapefile): self
    {
        $this->shapefile = $shapefile;

        return $this;
    }

    public function getBishopricSeat(): ?int
    {
        return $this->bishopricSeat;
    }

    public function setBishopricSeat(?int $bishopricSeat): self
    {
        $this->bishopricSeat = $bishopricSeat;

        return $this;
    }

    public function getNoteBishopricSeat(): ?string
    {
        return $this->noteBishopricSeat;
    }

    public function setNoteBishopricSeat(?string $noteBishopricSeat): self
    {
        $this->noteBishopricSeat = $noteBishopricSeat;

        return $this;
    }

    public function getDateOfFounding(): ?string
    {
        return $this->dateOfFounding;
    }

    public function setDateOfFounding(?string $dateOfFounding): self
    {
        $this->dateOfFounding = $dateOfFounding;

        return $this;
    }

    public function getDateOfDissolution(): ?string
    {
        return $this->dateOfDissolution;
    }

    public function setDateOfDissolution(?string $dateOfDissolution): self
    {
        $this->dateOfDissolution = $dateOfDissolution;

        return $this;
    }

    public function getCommentAuthorityFile(): ?string
    {
        return $this->commentAuthorityFile;
    }

    public function setCommentAuthorityFile(?string $commentAuthorityFile): self
    {
        $this->commentAuthorityFile = $commentAuthorityFile;

        return $this;
    }

    public function getIsAltesReich(): ?bool
    {
        return $this->isAltesReich;
    }

    public function setIsAltesReich(?bool $isAltesReich): self
    {
        $this->isAltesReich = $isAltesReich;

        return $this;
    }

    public function getIsDioceseGs(): ?bool
    {
        return $this->isDioceseGs;
    }

    public function setIsDioceseGs(?bool $isDioceseGs): self
    {
        $this->isDioceseGs = $isDioceseGs;

        return $this;
    }

    public function getDisplayname(): ?string {
        return $this->dioceseStatus.' '.$this->name;
    }
}
