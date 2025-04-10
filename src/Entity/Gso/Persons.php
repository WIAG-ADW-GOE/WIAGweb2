<?php

namespace App\Entity\Gso;

use App\Entity\Gso\Items;

use App\Repository\Gso\PersonsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonsRepository::class)]
class Persons
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\OneToOne(targetEntity: \Items::class)]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id')]
    private $item;

    #[ORM\OneToMany(targetEntity: \Offices::class, mappedBy: 'person', fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'person_id')]
    private $role;

    #[ORM\Column(type: 'integer')]
    private $itemId;

    #[ORM\Column(type: 'string', length: 500)]
    private $vorname;

    #[ORM\Column(type: 'string', length: 300, nullable: true)]
    private $vornamenvarianten;

    #[ORM\Column(type: 'string', length: 300)]
    private $familienname;

    #[ORM\Column(type: 'string', length: 300, nullable: true)]
    private $familiennamenvarianten;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $titel;

    #[ORM\Column(type: 'string', length: 400, nullable: true)]
    private $anmerkungen;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private $namenspraefix;

    #[ORM\Column(type: 'string', length: 300, nullable: true)]
    private $namenszusatz;

    #[ORM\Column(type: 'string', length: 300, nullable: true)]
    private $herkunftsname;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private $orden;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private $belegdaten;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $zeitraumBis;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $zeitraumVon;

    #[ORM\Column(type: 'string', length: 300, nullable: true)]
    private $geburtsdatum;

    #[ORM\Column(type: 'string', length: 300, nullable: true)]
    private $sterbedatum;

    #[ORM\Column(type: 'string', length: 300, nullable: true)]
    private $gndnummer;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private $cerlid;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private $viaf;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItemId(): ?int
    {
        return $this->itemId;
    }

    public function getItem()
    {
        return $this->item;
    }

    public function getRole() {
        return $this->role;
    }

    public function setItemId(int $itemId): self
    {
        $this->itemId = $itemId;

        return $this;
    }

    public function getVorname(): ?string
    {
        return $this->vorname;
    }

    public function setVorname(string $vorname): self
    {
        $this->vorname = $vorname;

        return $this;
    }

    public function getVornamenvarianten(): ?string
    {
        return $this->vornamenvarianten;
    }

    public function setVornamenvarianten(?string $vornamenvarianten): self
    {
        $this->vornamenvarianten = $vornamenvarianten;

        return $this;
    }

    public function getFamilienname(): ?string
    {
        return $this->familienname;
    }

    public function setFamilienname(string $familienname): self
    {
        $this->familienname = $familienname;

        return $this;
    }

    public function getDisplayname() {
        $prefixpart = ($this->namenspraefix !== null && strlen($this->namenspraefix) > 0) ? ' '.$this->namenspraefix : '';
        $familypart = ($this->familienname !== null && strlen($this->familienname)) > 0 ? ' '.$this->familienname : '';
        $agnomenpart = '';
        if (!is_null($this->namenszusatz) and strlen($this->namenszusatz) > 0) {
            $note_name = str_replace(';', ',', $this->namenszusatz);
            $note_list = explode(',', $note_name);
            $agnomenpart = ' '.$note_list[0];
        }
        return $this->vorname.$prefixpart.$familypart.$agnomenpart;
    }


    public function getFamiliennamenvarianten(): ?string
    {
        return $this->familiennamenvarianten;
    }

    public function setFamiliennamenvarianten(?string $familiennamenvarianten): self
    {
        $this->familiennamenvarianten = $familiennamenvarianten;

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

    public function getAnmerkungen(): ?string
    {
        return $this->anmerkungen;
    }

    public function setAnmerkungen(?string $anmerkungen): self
    {
        $this->anmerkungen = $anmerkungen;

        return $this;
    }

    public function getNamenspraefix(): ?string
    {
        return $this->namenspraefix;
    }

    public function setNamenspraefix(?string $namenspraefix): self
    {
        $this->namenspraefix = $namenspraefix;

        return $this;
    }

    public function getNamenszusatz(): ?string
    {
        return $this->namenszusatz;
    }

    public function setNamenszusatz(?string $namenszusatz): self
    {
        $this->namenszusatz = $namenszusatz;

        return $this;
    }

    public function getHerkunftsname(): ?string
    {
        return $this->herkunftsname;
    }

    public function setHerkunftsname(?string $herkunftsname): self
    {
        $this->herkunftsname = $herkunftsname;

        return $this;
    }

    public function getOrden(): ?string
    {
        return $this->orden;
    }

    public function setOrden(?string $orden): self
    {
        $this->orden = $orden;

        return $this;
    }

    public function getBelegdaten(): ?string
    {
        return $this->belegdaten;
    }

    public function setBelegdaten(?string $belegdaten): self
    {
        $this->belegdaten = $belegdaten;

        return $this;
    }

    public function getZeitraumBis(): ?int
    {
        return $this->zeitraumBis;
    }

    public function setZeitraumBis(?int $zeitraumBis): self
    {
        $this->zeitraumBis = $zeitraumBis;

        return $this;
    }

    public function getZeitraumVon(): ?int
    {
        return $this->zeitraumVon;
    }

    public function setZeitraumVon(?int $zeitraumVon): self
    {
        $this->zeitraumVon = $zeitraumVon;

        return $this;
    }

    public function getGeburtsdatum(): ?string
    {
        return $this->geburtsdatum;
    }

    public function setGeburtsdatum(?string $geburtsdatum): self
    {
        $this->geburtsdatum = $geburtsdatum;

        return $this;
    }

    public function getSterbedatum(): ?string
    {
        return $this->sterbedatum;
    }

    public function setSterbedatum(?string $sterbedatum): self
    {
        $this->sterbedatum = $sterbedatum;

        return $this;
    }

    public function birthInfo(): ?string {
        $birth_info = null;
        if($this->geburtsdatum && $this->sterbedatum) {
            $birth_info = '* '.$this->geburtsdatum.' † '.$this->sterbedatum;
        } elseif($this->geburtsdatum) {
            $birth_info = '* '.$this->geburtsdatum;
        } elseif($this->sterbedatum) {
            $birth_info = '† '.$this->sterbedatum;
        }
        return $birth_info;
    }

    public function getGndnummer(): ?string
    {
        return $this->gndnummer;
    }

    public function setGndnummer(?string $gndnummer): self
    {
        $this->gndnummer = $gndnummer;

        return $this;
    }

    public function getCerlid(): ?string
    {
        return $this->cerlid;
    }

    public function setCerlid(?string $cerlid): self
    {
        $this->cerlid = $cerlid;

        return $this;
    }

    public function getViaf(): ?string
    {
        return $this->viaf;
    }

    public function setViaf(?string $viaf): self
    {
        $this->viaf = $viaf;

        return $this;
    }

    public function describe(): string {
        $description = $this->getDisplayname();

        $birth_info = $this->birthInfo();
        if($birth_info) {
            $description = $description.' ('.$birth_info.')';
        }

        return $description;
    }

    /**
     * get information about offices; $nOffice: number of offices
     */
    public function describeRole($nOffice = 3): ?string {

        $office_list = array();
        foreach(array_slice($this->role->toArray(), 0, $nOffice) as $role) {
            $office_list[] = $role->describe();
        }

        $description = null;
        if(count($office_list) > 0) {
            $description = implode(', ', $office_list);
        }

        return($description);

    }

    /**
     * get information about references
     */
    public function describeReference($nRef = 3): ?string {

        $vol_txt_list = array();
        foreach(array_slice($this->getItem()->getReference()->toArray(), 0, $nRef) as $ref) {
            if ($ref->getReferenceVolume()) {
                $vol_txt_list[] = $ref->getReferenceVolume()->getKurztitel();
            }
        }

        $description = null;
        if(count($vol_txt_list) > 0) {
            $description = implode(', ', $vol_txt_list);
        }

        return($description);

    }



}
