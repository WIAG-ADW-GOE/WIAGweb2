<?php
namespace App\Entity;

class FacetChoice {
    public $name;
    public $count;

    public function __construct($n, $c) {
        $this->name = $n;
        $this->count = $c;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function getLabel(): string {
        return $this->name.' ('.$this->count.')';
    }


    public static function islessByName(FacetChoice $a, FacetChoice $b) {
        if($a->name == $b->name) {
            return 0;
        }
        return $a->name < $b->name ? -1 : 1;
    }

    /**
     * mergeByName($a, $b)
     *
     * merge array of FacetChoice $a with array of FacetChoice $b; return extended $a
     */
    public static function mergeByName(&$a, $b) {
        if (!$b) return $a;
        // collect names of database choices
        $namesA = array();
        foreach ($a as $ea) {
            $namesA[] = $ea->getName();
        }

        foreach ($b as $eb) {
            $nameB = $eb->getName();
            if (!in_array($nameB, $namesA)) {
                $a[] = new FacetChoice($nameB, 0);
            }
        }
        uasort($a, array('self', 'islessByName'));

        return $a;
    }

}
