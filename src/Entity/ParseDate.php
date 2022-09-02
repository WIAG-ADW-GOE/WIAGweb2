<?php
namespace App\Entity;

/**
 * auxiliary class to store elements needed for the parsing of dates
 */
class ParseDate {
    public $rgx; # regular expression
    public $match_index; # index of the regular expression match element
    public $sortKey; # sort key

    public function __construct(string $rgx, int $match_index, int $sort_key) {
        $this->rgx = $rgx;
        $this->match_index = $match_index;
        $this->sortKey = $sort_key;
    }
}
