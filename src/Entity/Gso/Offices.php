<?php

namespace App\Entity\Gso;

use App\Repository\Gso\OfficesRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=OfficesRepository::class)
 */
class Offices
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
    private $personId;

    /**
     * @ORM\ManyToOne(targetEntity="Persons", inversedBy="role")
     */
    private $person;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $bezeichnung;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $art;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $ort;

    /**
     * @ORM\Column(type="string", length=300)
     */
    private $institution;

    /**
     * @ORM\Column(type="string", length=300)
     */
    private $dioezese;

    /**
     * @ORM\Column(type="string", length=10)
     */
    private $klosterid;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $weihegrad;

    /**
     * @ORM\Column(type="string", length=300)
     */
    private $von;

    /**
     * @ORM\Column(type="string", length=250)
     */
    private $bis;

    /**
     * @ORM\Column(type="string", length=500)
     */
    private $anmerkung;

    /**
     * @ORM\Column(type="boolean")
     */
    private $deleted;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPersonId(): ?int
    {
        return $this->personId;
    }

    public function setPersonId(int $personId): self
    {
        $this->personId = $personId;

        return $this;
    }

    public function getBezeichnung(): ?string
    {
        return $this->bezeichnung;
    }

    public function setBezeichnung(string $bezeichnung): self
    {
        $this->bezeichnung = $bezeichnung;

        return $this;
    }

    public function getArt(): ?string
    {
        return $this->art;
    }

    public function setArt(string $art): self
    {
        $this->art = $art;

        return $this;
    }

    public function getOrt(): ?string
    {
        return $this->ort;
    }

    public function setOrt(string $ort): self
    {
        $this->ort = $ort;

        return $this;
    }

    public function getInstitution(): ?string
    {
        return $this->institution;
    }

    public function setInstitution(string $institution): self
    {
        $this->institution = $institution;

        return $this;
    }

    public function getDioezese(): ?string
    {
        return $this->dioezese;
    }

    public function setDioezese(string $dioezese): self
    {
        $this->dioezese = $dioezese;

        return $this;
    }

    public function getKlosterid(): ?string
    {
        return $this->klosterid;
    }

    public function setKlosterid(string $klosterid): self
    {
        $this->klosterid = $klosterid;

        return $this;
    }

    public function getWeihegrad(): ?string
    {
        return $this->weihegrad;
    }

    public function setWeihegrad(string $weihegrad): self
    {
        $this->weihegrad = $weihegrad;

        return $this;
    }

    public function getVon(): ?string
    {
        return $this->von;
    }

    public function setVon(string $von): self
    {
        $this->von = $von;

        return $this;
    }

    public function getBis(): ?string
    {
        return $this->bis;
    }

    public function setBis(string $bis): self
    {
        $this->bis = $bis;

        return $this;
    }

    public function getAnmerkung(): ?string
    {
        return $this->anmerkung;
    }

    public function setAnmerkung(string $anmerkung): self
    {
        $this->anmerkung = $anmerkung;

        return $this;
    }

    public function isDeleted(): ?bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * compose string containing basic information
     */
    public function describe(): string {

        $role_name = $this->bezeichnung;

        $inst_or_dioc = null;
        if($this->institution){
            $inst_or_dioc = $this->institution;
        } elseif($this->dioezese) {
            $inst_or_dioc = $this->dioezese;
        }

        $date_info = null;
        if($this->von && $this->bis) {
            $date_info = $this->von.'-'.$this->bis;
        } elseif($this->von) {
            $date_info = $this->von;
        } elseif($this->bis) {
            $date_info = 'bis '.$this->bis;
        }

        $description = '';
        if($role_name) {
            $description = $role_name;
        }
        if($inst_or_dioc) {
            $description = $description.' '.$inst_or_dioc;
        }
        if($date_info) {
            $description = $description.' '.$date_info;
        }

        return $description;

    }

    public function isEmpty() {
        return (
            (is_null($this->bezeichnung) or trim($this->bezeichnung) == "")
            and (is_null($this->institution) or trim($this->institution) == "")
            and (is_null($this->klosterid) or trim($this->klosterid) == "")
        );
    }

}
