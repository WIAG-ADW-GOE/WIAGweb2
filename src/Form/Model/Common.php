<?php

namespace App\Form\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Common {

    private $formIsExpanded = false;

    private $deleteFlag = false;

    private $isNew = false;

    private $isEdited = false;

    private $inputError;

    public function __construct() {
        $this->inputError = new ArrayCollection();
    }

    public function getFormIsExpanded(): bool {
        return $this->formIsExpanded;
    }

    public function setFormIsExpanded($value) {
        $this->formIsExpanded = $value;
        return $this;
    }

    public function getDeleteFlag() {
        return $this->deleteFlag;
    }

    public function setDeleteFlag($value) {
        $this->deleteFlag = $value;
        return $this;
    }

    public function getIsNew(): bool {
        return $this->isNew;
    }

    public function setIsNew($value) {
        $this->isNew = $value;
        return $this;
    }

    public function getIsEdited(): bool {
        return $this->isEdited;
    }

    public function setIsEdited($value) {
        $this->isEdited = $value;
        return $this;
    }

    public function getInputError() {
        if (is_null($this->inputError)) {
            $this->inputError = new ArrayCollection();
        }
        return $this->inputError;
    }

    public function hasError($min_level): bool {
        if (is_null($this->inputError)) {
            return false;
        }

        foreach($this->inputError as $e_loop) {
            $level = $e_loop->getLevel();
            if (in_array($level, InputError::ERROR_LEVEL[$min_level])) {
                return true;
            }
        }
        return false;
    }

}
