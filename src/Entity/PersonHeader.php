<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class PersonHeader
{

    private $first;

    private $second;

    public function __construct($first) {
        $givennameVariants = new ArrayCollection();
        $familynameVariants = new ArrayCollection();

        $this->first = $first;
    }

    public function combineData($a, $b) {
        if (is_null($a)) {
            return $b;
        }
        if (is_null($b)) {
            return $a;
        }
        if ($a == $b) {
            return $a;
        }
        return $a.'/'.$b;
    }

    public function get($field) {
        $getfnc = 'get'.ucfirst($field);

        if (is_null($this->first) && !is_null($this->second)) {
            return $this->second->$getfnc();
        }
        if (is_null($this->second)) {
            return $this->first->$getfnc();
        }
        return $this->combineData($this->first->$getfnc(), $this->second->$getfnc());
    }

    public function setSecond($second): self {
        $this->second = $second;
        return $this;
    }

    /**
     * concatenate name variants and comments
     */
    public function commentLine($flag_names = true) {
        if (is_null($this->first) && !is_null($this->second)) {
            return $this->second->commentLine($flag_names);
        }


        $strGnVariants = null;
        $strFnVariants = null;
        if ($flag_names) {
            $givennameVariants = $this->first->getGivennameVariants();
            $familynameVariants = $this->first->getFamilynameVariants();

            $gnVariants = array ();
            foreach ($givennameVariants as $gn) {
                $gnVariants[] = $gn->getName();
            }
            $fnVariants = array ();
            foreach ($familynameVariants as $fn) {
                $fnVariants[] = $fn->getName();
            }

            if (!is_null($this->second)) {
                foreach ($this->second->getGivennameVariants() as $gn) {
                    $gnVariants[] = $gn->getName();
                }
                foreach ($this->second->getFamilynameVariants() as $fn) {
                    $fnVariants[] = $fn->getName();
                }

            }
            $gnVariants = array_unique($gnVariants);
            $fnVariants = array_unique($fnVariants);

            $strGnVariants = $gnVariants ? implode(', ', $gnVariants) : null;
            $strFnVariants = $fnVariants ? implode(', ', $fnVariants) : null;
        }


        $eltCands = [
            $strGnVariants,
            $strFnVariants,
            $this->get('noteName'),
            $this->get('notePerson'),
        ];
        // dump($eltCands);

        $lineElts = array();
        foreach ($eltCands as $elt) {
            if (!is_null($elt) && $elt != '') {
                $lineElts[] = $elt;
            }
        }

        $commentLine = null;
        if (count($lineElts) > 0) {
            $commentLine = implode('; ', $lineElts);
        }

        return $commentLine;
    }



}
