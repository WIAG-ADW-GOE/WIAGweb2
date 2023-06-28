<?php
namespace App\Form\Model;


use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


/**
 * Model for property type
 *
 */
class PropertyTypeFormModel {
    public $id = null;
    public $formIsEdited = 1;
    public $name = null;
    public $label = null;
    public $comment = null;
    public $inputError = null;
    public $displayOrder = null;

    public function __construct() {
        $this->inputError = new ArrayCollection();
    }


    // for compatibility
    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    public function getId($id) {
        $this->id = $id;
        return $this;
    }

    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    public function setLabel($label) {
        $this->label = $label;
        return $this;
    }

    public function setComment($comment) {
        $this->comment = $comment;
        return $this;
    }

    public function getFormIsEdited() {
        return $this->formIsEdited;
    }

}
