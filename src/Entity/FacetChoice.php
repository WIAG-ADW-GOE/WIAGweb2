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


    public static function isless(OfficeCount $a, OfficeCount $b) {
        if($a->name == $b->name) {
            return 0;
        }
        return $a->name < $b->name ? -1 : 1;
    }


}
