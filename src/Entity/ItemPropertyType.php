<?php

namespace App\Entity;

use App\Form\Model\Common as Model;
use App\Entity\InputError;
use App\Repository\ItemPropertyTypeRepository;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


#[ORM\Entity(repositoryClass: ItemPropertyTypeRepository::class)]
class ItemPropertyType extends Model {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 63)]
    private $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $comment;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $displayOrder;

    /**
     * no db mapping
     */
    private $referenceCount = 0;



    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getDisplayOrder()
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder($displayOrder): self
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    public function getReferenceCount(): int {
        return $this->referenceCount;
    }

    public function setReferenceCount($count) {
        $this->referenceCount = $count;
        return $this;
    }


}
