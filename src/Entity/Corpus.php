<?php

namespace App\Entity;

use App\Repository\CorpusRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CorpusRepository::class)
 */
class Corpus
{

    const EDIT_LIST = ['can', 'epc'];

    const CORPUS_PRETTY = [
        'epc' => 'Bischöfe',
        'can' => 'Domherren',
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=63)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=511, nullable=true)
     */
    private $note;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $idPublicMask;

    /**
     * @ORM\Column(type="string", length=31)
     */
    private $corpusId;

    /**
     * @ORM\Column(type="string", length=511, nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $pageTitle;

    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $onlineStatus;


    /**
     * @ORM\Column(type="string", length=63, nullable=true)
     */
    private $defaultStatus;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $editForm;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $editChoiceOrder;



    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
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

    public function getIdPublicMask(): ?string
    {
        return $this->idPublicMask;
    }

    public function setIdPublicMask(?string $idPublicMask): self
    {
        $this->idPublicMask = $idPublicMask;

        return $this;
    }

    public function getCorpusId(): ?string
    {
        return $this->corpusId;
    }

    public function setCorpusId(string $corpusId): self
    {
        $this->corpusId = $corpusId;

        return $this;
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

    public function getPageTitle(): ?string
    {
        return $this->pageTitle;
    }

    public function setPageTitle(?string $pageTitle): self
    {
        $this->pageTitle = $pageTitle;

        return $this;
    }

    public function getOnlineStatus(): ?string
    {
        return $this->onlineStatus;
    }

    public function getDefaultStatus(): ?string
    {
        return $this->defaultStatus;
    }

    public function getEditForm(): ?string
    {
        return $this->editForm;
    }

    public function setEditForm(string $editForm): self
    {
        $this->editForm = $editForm;

        return $this;
    }

    public function getEditChoiceOrder(): ?int
    {
        return $this->editChoiceOrder;
    }

    public function setEditChoiceOrder(?int $editChoiceOrder): self
    {
        $this->editChoiceOrder = $editChoiceOrder;

        return $this;
    }


}
