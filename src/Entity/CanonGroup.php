<?php

namespace App\Entity;

class CanonGroup
{
    public $ep = null;
    public $dh = null;
    public $gs = null;

    /**
     * get primary
     * priority for public id as in canon_lookup: ep > dh > gs
     */
    public function getPrimary() {
        return $this->dh ?: ($this->ep ?: $this->gs);
    }

    public function getIdPublic() {
        $owner = $this->ep ?: ($this->dh ?: $this->gs);
        if (is_null($owner)) {
            return null;
        }
        return $owner->getItem()->getIdPublic();
    }

    public function countValid() {
        return ($this->ep ? 1 : 0)
            + ($this->dh ? 1 : 0)
            + ($this->gs ? 1 : 0);
    }
}
