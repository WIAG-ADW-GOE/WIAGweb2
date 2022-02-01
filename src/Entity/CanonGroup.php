<?php

namespace App\Entity;

class CanonGroup
{
    public $idEp = null;
    public $idDh = null;
    public $idGs = null;

    /**
     * get primary id: bishop > canon > canon_gs
     */
    public function getPrimaryId() {
        return $this->idEp ?? ($this->idDh ?? $this->idGs);
    }

    public function countValid() {
        return ($this->idEp ? 1 : 0)
            + ($this->idDh ? 1 : 0)
            + ($this->idGs ? 1 : 0);
    }
}
