<?php

namespace App\Service;

use App\Entity\ParseDate;


class UtilService {

    /**
     * merge sort array
     * obsolete 2022-07-14
     */
    public function mergesort($list, $field_list) {
        if(count($list) < 2 ) return $list;

        $mid = count($list) / 2;
        $left = array_slice($list, 0, $mid);
        $right = array_slice($list, $mid);

        $left = $this->mergesort($left, $field_list);
        $right = $this->mergesort($right, $field_list);
        return $this->merge($left, $right, $field_list);
    }

    private function merge($left, $right, $field_list)
    {
        $result=array();
        $leftIndex=0;
        $rightIndex=0;

        while($leftIndex < count($left) && $rightIndex < count($right)) {
            if($this->lessThan($left[$leftIndex], $right[$rightIndex], $field_list)) {
                $result[]=$left[$leftIndex];
                $leftIndex++;
            }
            else
            {
                $result[] = $right[$rightIndex];
                $rightIndex++;

            }
        }

        while($leftIndex<count($left)) {
            $result[]=$left[$leftIndex];
            $leftIndex++;
        }

        while($rightIndex<count($right)) {
            $result[]=$right[$rightIndex];
            $rightIndex++;
        }

        return $result;
    }

    public function lessThan($a, $b, $field_list) {

        $is_less = false;
        $f_dump = false;
        foreach ($field_list as $field) {
            $a_val = $a[$field];
            $b_val = $b[$field];
            // sort null last
            if (is_null($a_val) && !is_null($b_val)) {
                $is_less = false;
                break;
            }

            if (is_null($b_val) && !is_null($a_val)) {
                $is_less = true;
                break;
            }

            if ($a_val < $b_val) {
                $is_less = true;
                if ($f_dump) dump($a, $b, $is_less, $field);
                break;
            } elseif ($a_val > $b_val) {
                $is_less = false;
                if ($f_dump) dump($a, $b, $is_less, $field);
                break;
            }
        }

        return $is_less;
    }

    /**
     * use $crit_list to compare $a and $b
     */
    static function compare($a, $b, $crit_list) {
        $cmp_val = 0;
        foreach ($crit_list as $field) {
            $getfnc = 'get'.ucfirst($field);
            if (is_object($a)) {
                $a_val = $a->$getfnc();
                $b_val = $b->$getfnc();
            } else {
                $a_val = $a[$field];
                $b_val = $b[$field];
            }
            // sort null last, but not for familyname

            if (is_null($a_val) && is_null($b_val)) {
                $cmp_val = 0;
                continue;
            }

            if ($field == 'familyname') {
                if (is_null($a_val) && !is_null($b_val)) {
                    $cmp_val = -1;
                    break;
                }

                if (is_null($b_val) && !is_null($a_val)) {
                    $cmp_val = 1;
                    break;
                }
            } else {
                if (is_null($a_val) && !is_null($b_val)) {
                    $cmp_val = 1;
                    break;
                }

                if (is_null($b_val) && !is_null($a_val)) {
                    $cmp_val = -1;
                    break;
                }
            }

            if (is_string($a_val) and is_string($b_val)) {
                $cmp_val = strnatcmp($a_val, $b_val);
            } else {
                $cmp_val = $a_val < $b_val ? -1 : ($a_val > $b_val ? 1 : 0);
            }

            if ($cmp_val != 0) {
                break;
            }
        }

        return $cmp_val;
    }


    /**
     * use crit_list in $crit_list to sort $list (array of arrays)
     */
    static function sortByFieldList($list, $crit_list, $dir = 'ASC') {
        usort($list, function($a, $b) use ($crit_list, $dir) {
            $dir_factor = $dir == 'ASC' ? 1 : -1;
            return $dir_factor * self::compare($a, $b, $crit_list);
        });

        return $list;
    }


    /**
     * use criteria in $crit_list to sort $list (array of PersonRole)
     */
   static function sortByDomstift($list, $domstift) {
        // for PHP 8.0.0 and later sorting is stable, until then use second criterion
        uasort($list, function($a, $b) use ($domstift) {

            $a_inst = $a->getInstitution();
            $a_val = $a_inst ? $a_inst->getName() : null;
            $b_inst = $b->getInstitution();
            $b_val = $b_inst ? $b_inst->getName() : null;

            $domstift_long = 'Domstift '.ucfirst($domstift);

            // sort null last
            if (is_null($a_val) && !is_null($b_val)) {
                return 1;
            }

            if (is_null($b_val) && !is_null($a_val)) {
                return -1;
            }

            $result = 0;
            if ($a_val == $domstift || $a_val == $domstift_long) {
                if ($b_val == $domstift || $b_val == $domstift_long) {
                    $result = 0;
                    //
                    $result = self::compare($a, $b, ['dateSortKey', 'id']);
                } else {
                    $result = -1;
                }
            } elseif ($b_val == $domstift || $b_val == $domstift_long) {
                $result = 1;
            } else {
                $result = 0;
            }

            // other criteria
            if ($result == 0) {
                $result = self::compare($a, $b, ['placeName', 'dateSortKey', 'id']);
            }

            return $result;

        });

        return $list;
    }

    /**
     * use $field to reorder $list
     *
     * An index in $sorted may occur several times as a value in $list
     */
    static public function reorder($list, $sorted, $field = "id") {
        // function to get the criterion
        $getfnc = 'get'.ucfirst($field);
        $idx_map = array_flip($sorted);

        usort($list, function($a, $b) use ($idx_map, $getfnc) {
            $idx_a = $idx_map[$a->$getfnc()];
            $idx_b = $idx_map[$b->$getfnc()];

            $cmp_val =  $idx_a == $idx_b ? 0 : ($idx_a < $idx_b ? -1 : +1);
            return $cmp_val;
        });

        return $list;
    }

    // regular expressions for dates
    // - test for first century only if other alternatives yield no result
    const RGPCENTURY = "([1-9][0-9]?)\\. (Jahrh|Jh)";

    // these characters are removed, if a pure number is expected
    const DECORATIONYEAR = "()[]?+† \t";
    const RGPYEAR = "([1-9][0-9][0-9]+)";
    const RGPYEARFC = "([1-9][0-9]+)";

    const RGXYEAR = "/^( *|erwählt |belegt )".self::RGPYEAR."/";
    const RGXYEARFC = "/^( *|erwählt |belegt )".self::RGPYEARFC."/";

    // - turn of the century, in century
    const RGXTCENTURY = "~([1-9][0-9]?)\\.(/| oder )".self::RGPCENTURY."~i";
    const RGXICENTURY = "~(wohl im )".self::RGPCENTURY."~i";

    // - quarter
    const RGX1QCENTURY = "/(1\\.|erstes) Viertel +(des )?".self::RGPCENTURY."/i";
    const RGX2QCENTURY = "/(2\\.|zweites) Viertel +(des )?".self::RGPCENTURY."/i";
    const RGX3QCENTURY = "/(3\\.|drittes) Viertel +(des )?".self::RGPCENTURY."/i";
    const RGX4QCENTURY = "/(4\\.|viertes) Viertel +(des )?".self::RGPCENTURY."/i";

    // - begin, middle end
    const RGX1TCENTURY = "/(Anfang|Beginn) (des )?".self::RGPCENTURY."/i";
    const RGX2TCENTURY = "/(Mitte) (des )?".self::RGPCENTURY."/i";
    const RGX3TCENTURY = "/(Ende) (des )?".self::RGPCENTURY."/i";

    // - third
    const RGX1TRDCENTURY = "/(1\\.|erstes) Drittel +(des )?".self::RGPCENTURY."/i";
    const RGX2TRDCENTURY = "/(2\\.|zweites) Drittel +(des )?".self::RGPCENTURY."/i";
    const RGX3TRDCENTURY = "/(3\\.|drittes) Drittel +(des )?".self::RGPCENTURY."/i";

    // - half
    const RGX1HCENTURY = "/(1\\.|erste) Hälfte +(des )?".self::RGPCENTURY."/i";
    const RGX2HCENTURY = "/(2\\.|zweite) Hälfte +(des )?".self::RGPCENTURY."/i";

    // - between
    const RGXBETWEEN = "/zwischen ".self::RGPYEAR." und ".self::RGPYEAR."/i";

    // - early, late
    const RGXEARLYCENTURY = "/frühes ".self::RGPCENTURY."/i";
    const RGXLATECENTURY = "/spätes ".self::RGPCENTURY."/i";

    // - decade
    const RGXDECADE = "/".self::RGPYEAR." ?er Jahre/i";
    const RGXEARLYDECADE = "/(Anfang der )".self::RGPYEAR." ?er Jahre/i";

    // - around, ...
    const RGXSBEFORE = "/(kurz vor) ".self::RGPYEAR."/i";
    const RGXBEFORE = "/(vor|bis|spätestens|spät\\.|v\\.) ".self::RGPYEAR."/i";
    const RGXCA = "/(circa|ca\\.|wahrscheinlich|wohl|etwa|evtl\\.) ".self::RGPYEAR."/i";
    const RGXAROUND = "/(um) ".self::RGPYEAR."/i";
    const RGXFIRST = "/(erstmals erwähnt) ".self::RGPYEAR."/i";
    const RGXSAFTER = "/(kurz nach|bald nach) ".self::RGPYEAR."/i";
    const RGXAFTER = "/(nach|frühestens|seit|ab) ".self::RGPYEAR."/i";


    const RGXCENTURY = "/^ *".self::RGPCENTURY."/i";

    /**
     * parse $s for an earliest or latest possible date. $dir is 'upper' or 'lower'
     */
    public function parseDate($s, $dir): ?int {
        $year = null;
        $matches = null;

        # turn of the century
        $rgm = preg_match(self::RGXTCENTURY, $s, $matches);

        if ($rgm === 1) {
            if ($dir == 'lower') {
                $century = intval($matches[1]);
                $year = ($century - 1) * 100 + 1;
                return $year;
            } elseif ($dir == 'upper') {
                $century = intval($matches[3]);
                $year = $century * 100 - 1;
                return $year;
            }
        }

        // quarter
        $rgx_list = [
            self::RGX1QCENTURY,
            self::RGX2QCENTURY,
            self::RGX3QCENTURY,
            self::RGX4QCENTURY,
        ];

        $year = self::parseFractionCentury($rgx_list, $s, $dir, 25);
        if (!is_null($year)) { return $year; }

        // begin, middle, end
        $rgx_list = [
            self::RGX1TCENTURY,
            self::RGX2TCENTURY,
            self::RGX3TCENTURY,
        ];

        $year = self::parseFractionCentury($rgx_list, $s, $dir, 33);
        if (!is_null($year)) { return $year; }

        // third
        $rgx_list = [
            self::RGX1TRDCENTURY,
            self::RGX2TRDCENTURY,
            self::RGX3TRDCENTURY,
        ];

        $year = self::parseFractionCentury($rgx_list, $s, $dir, 33);
        if (!is_null($year)) { return $year; }


        // half
        $rgx_list = [
            self::RGX1HCENTURY,
            self::RGX2HCENTURY,
        ];

        $year = self::parseFractionCentury($rgx_list, $s, $dir, 50);
        if (!is_null($year)) { return $year; }

        // between
        $rgm = preg_match(self::RGXBETWEEN, $s, $matches);
        if ($rgm === 1) {
            if ($dir == 'lower') {
                $year = intval($matches[1]);
            } elseif ($dir == 'upper') {
                $year = intval($matches[2]);
            }
            return $year;
        }

        // early, late
        $rgm = preg_match(self::RGXEARLYCENTURY, $s, $matches);
        if ($rgm === 1) {
            $century = intval($matches[1]);
            if ($dir == 'lower') {
                $year = ($century - 1) * 100 + 1;
            } elseif ($dir == 'upper') {
                $year = ($century - 1) * 100 + 20;
            }
            return $year;
        }

        $rgm = preg_match(self::RGXLATECENTURY, $s, $matches);
        if ($rgm === 1) {
            $century = intval($matches[1]);
            if ($dir == 'lower') {
                $year = $century * 100 - 19;
            } elseif ($dir == 'upper') {
                $year = $century * 100;
            }
            return $year;
        }

        // decade
        $match_index = 2;
        $year = self::parseApprox(self::RGXEARLYDECADE, $s, 0, 4, $dir, $match_index);
        if (!is_null($year)) { return $year; }

        $match_index = 1;
        $year = self::parseApprox(self::RGXDECADE, $s, 0, 10, $dir, $match_index);
        if (!is_null($year)) { return $year; }

        // before, around after
        $match_index = 2;
        $year = self::parseApprox(self::RGXSBEFORE, $s, 10, 0, $dir, $match_index);
        if (!is_null($year)) { return $year; }

        $match_index = 2;
        $year = self::parseApprox(self::RGXBEFORE, $s, 50, 0, $dir, $match_index);
        if (!is_null($year)) { return $year; }

        $match_index = 2;
        $year = self::parseApprox(self::RGXFIRST, $s, 5, 5, $dir, $match_index);
        if (!is_null($year)) { return $year; }

        $match_index = 2;
        $year = self::parseApprox(self::RGXSAFTER, $s, 0, 10, $dir, $match_index);
        if (!is_null($year)) { return $year; }

        $match_index = 2;
        $year = self::parseApprox(self::RGXAFTER, $s, 0, 50, $dir, $match_index);
        if (!is_null($year)) { return $year; }

        $match_index = 2;
        $year = self::parseApprox(self::RGXAROUND, $s, 5, +5, $dir, $match_index);
        if (!is_null($year)) { return $year; }

        $match_index = 2;
        $year = self::parseApprox(self::RGXCA, $s, 5, +5, $dir, $match_index);
        if (!is_null($year)) { return $year; }

        // century
        $rgm = preg_match(self::RGXCENTURY, $s, $matches);
        if ($rgm === 1) {
            $century = intval($matches[1]);
            if ($dir == 'lower') {
                $year = ($century - 1) * 100 + 1;
            } elseif ($dir == 'upper') {
                $year = $century * 100;
            }
            return $year;
        }

        // plain year
        // 2023-07-07 allow decorations in date specifications if only a number is expected)
        $s = trim($s, self::DECORATIONYEAR);
        $rgm = preg_match(self::RGXYEAR, $s, $matches);
        if ($rgm === 1) {
            $year = intval($matches[2]);
            return $year;
        }

        // plain year first century
        $rgm = preg_match(self::RGXYEARFC, $s, $matches);
        if ($rgm === 1) {
            $year = intval($matches[2]);
            return $year;
        }

        return $year;
    }

    /**
     * parse dates related to a fraction of a century
     */
    static function parseFractionCentury($rgx_list, $s, $dir, $span) {
        $year = null;

        foreach($rgx_list as $q => $rgx) {
            $rgm = preg_match($rgx, $s, $matches);
            if ($rgm === 1) {
                $century = intval($matches[3]);
                if ($dir == 'lower') {
                    $year = ($century - 1) * 100 + $q * $span + 1;
                    return $year;
                } elseif ($dir == 'upper') {
                    $year = ($century - 1) * 100 + ($q + 1) * $span;
                    return $year;
                }
            }
        }

        return $year;
    }

    /**
     * parse an approximate date information
     */
    static function parseApprox($rgx, $s, $left, $right, $dir, $match_index) {
        $year = null;
        $matches = null;
        $rgm = preg_match($rgx, $s, $matches);
        if ($rgm === 1) {
            $year = intval($matches[$match_index]);
            if ($dir == 'lower') {
                $year -= $left;
            } elseif ($dir = 'upper') {
                $year += $right;
            }
        }

        return $year;
    }

    /**
     * rgxCtyList
     *
     * regular expressions to build sort keys from dates (century)
     * structure: regular expression, index of the relevant part, sort key
     */
    private function rgxCtyList() {
        $rgx_cty_list = [
            new ParseDate(self::RGXTCENTURY, 1, 850),
            new ParseDate(self::RGX1QCENTURY, 3, 530),
            new ParseDate(self::RGX2QCENTURY, 3, 560),
            new ParseDate(self::RGX3QCENTURY, 3, 580),
            new ParseDate(self::RGX4QCENTURY, 3, 595),
            new ParseDate(self::RGX1TCENTURY, 3, 500),
            new ParseDate(self::RGX2TCENTURY, 3, 570),
            new ParseDate(self::RGX3TCENTURY, 3, 594),
            new ParseDate(self::RGX1TRDCENTURY, 3, 500),
            new ParseDate(self::RGX2TRDCENTURY, 3, 570),
            new ParseDate(self::RGX3TRDCENTURY, 3, 594),
            new ParseDate(self::RGX1HCENTURY, 3, 550),
            new ParseDate(self::RGX2HCENTURY, 3, 590),
            new ParseDate(self::RGXICENTURY, 2, 810),
            new ParseDate(self::RGXEARLYCENTURY, 1, 555),
            new ParseDate(self::RGXLATECENTURY, 1, 593),
            new ParseDate(self::RGXCENTURY, 1, 800)
        ];
        return $rgx_cty_list;
    }

    /**
     * rgxYearList
     *
     * regular expressions to build sort keys from dates
     * structure: regular expression, index of the relevant part, sort key
     */
    private function rgxYearList() {
        $rgx_year_list = [
            new ParseDate(self::RGXSBEFORE, 2, 105),
            new ParseDate(self::RGXBEFORE, 2, 100),
            new ParseDate(self::RGXAROUND, 2, 210),
            new ParseDate(self::RGXCA, 2, 200),
            new ParseDate(self::RGXFIRST, 2, 110),
            new ParseDate(self::RGXSAFTER, 2, 303),
            new ParseDate(self::RGXEARLYDECADE, 2, 305),
            new ParseDate(self::RGXAFTER, 2, 309),
            new ParseDate(self::RGXDECADE, 1, 310),
        ];
        return $rgx_year_list;
    }

    /**
     * like rgxYearList but for pure numbers
     */
    private function rgxYearNumList() {
        $rgx_year_num_list = [
            new ParseDate(self::RGXYEAR, 2, 150),
            new ParseDate(self::RGXYEARFC, 2, 150)
        ];
        return $rgx_year_num_list;
    }



    const SORT_KEY_MAX = 9000900;

    /**
     * parse year in $s and return a sort key
     */
    public function sortKeyVal($s): ?int {

        // between
        $matches = null;
        $rgm = preg_match(self::RGXBETWEEN, $s, $matches);
        if ($rgm === 1) {
            $sort = 150;
            $year_lower = intval($matches[1]);
            $year_upper = intval($matches[2]);
            $year = intdiv($year_lower + $year_upper, 2);
            return $year * 1000 + $sort;
        }

        foreach ($this->rgxCtyList() as $rgx_obj) {
            $matches = null;
            $rgm = preg_match($rgx_obj->rgx, $s, $matches);
            if ($rgm === 1) {
                $sort = $rgx_obj->sortKey;
                $century = $matches[$rgx_obj->match_index];
                $year = ($century - 1) * 100;
                return $year * 1000 + $sort;
            }
        }

        foreach ($this->rgxYearList() as $rgx_obj) {
            $matches = null;
            $rgm = preg_match($rgx_obj->rgx, $s, $matches);
            if ($rgm === 1) {
                $sort = $rgx_obj->sortKey;
                $year = $matches[$rgx_obj->match_index];
                return $year * 1000 + $sort;
            }
        }

        // 2023-07-07 allow decorations in date specifications if only a number is expected)
        $s = trim($s, self::DECORATIONYEAR);
        foreach ($this->rgxYearNumList() as $rgx_obj) {
            $matches = null;
            $rgm = preg_match($rgx_obj->rgx, $s, $matches);
            if ($rgm === 1) {
                $sort = $rgx_obj->sortKey;
                $year = $matches[$rgx_obj->match_index];
                return $year * 1000 + $sort;
            }
        }

        return null;
    }

    static public function dateRange($s) {
        $range = array();
        if (is_null($s) || trim($s) == "") {
            return $range;
        }
        $s_list = explode('-', $s);

        foreach($s_list as $index => $date) {
            $date = trim($date);
            if ($date == "") {
                $range[] = null;
            }
            else {
                $d_obj = \DateTime::createFromFormat('d.m.y', $date);
                if (!$d_obj) {
                    $d_obj = \DateTime::createFromFormat('d.m.Y', $date);
                }

                $range[] = $d_obj;
            }
        }
        return $range;
    }

    /**
     * parse date or date range
     *
     * usually date of creation or date of last modification
     */
    static public function parseDateRange($s) {
        // return empty array or a two element array
        // only one value
        // first not null, second null
        // first null, second not null
        // else empty array

        $range = UtilService::dateRange($s);
        if (count($range) == 0) {
            return $range;
        }

        if (count($range) > 1) {
            if (!$range[0] && !$range[1]) {
                $range = array();
            } elseif (!$range[0] && $range[1]) {
                $range[0] = \DateTimeImmutable::createFromFormat('d.m.Y', "01.01.1000");
            } elseif ($range[0] && !$range[1]) {
                $range[1] = \DateTimeImmutable::createFromFormat('d.m.Y', "31.12.2999");
            }
        } elseif (!$range[0]) {
            $range = array();
        } else {
            $range[1] = clone $range[0];
            $range[1]->modify('+1 day');
        }

        $str_range = array();
        foreach($range as $r_loop) {
            $str_range[] = $r_loop->format("Y-m-d");
        }

        return $str_range;
    }

    public function no_data($a, $key_list) {
        foreach($key_list as $key) {
            if (array_key_exists($key, $a) && trim($a[$key]) != "") {
                return false;
            }
        }
        return true;
    }

    /**
     * setByKeys($obj, $data, $key_list)
     *
     * set elements of $obj
     */
    static public function setByKeys($obj, $data, $key_list) {
        // 2023-01-17 debug
        if (true) {
            $missing_key_list = array();
            $missing_flag = false;
            foreach($key_list as $key) {
                if (!array_key_exists($key, $data)) {
                    $missing_key_list[] = $key;
                    $missing_flag = true;
                }
            }
            if ($missing_flag) {
                dd($data, $missing_key_list);
            }
        }

        foreach($key_list as $key) {
            $value = trim($data[$key]);
            // this is helpful to clear the value of a field
            if (strlen($value) == 0) {
                $value = null;
            }
            $set_fnc = 'set'.ucfirst($key);
            $obj->$set_fnc($value);
        }
    }

    static public function missingKeyList($data, $key_list) {
        $missing_key_list = array();
        foreach($key_list as $key) {
            if (!array_key_exists($key, $data)) {
                $missing_key_list[] = $key;
            }
        }
        return $missing_key_list;
    }

    /**
     * find max value for $attribute in a list of objects
     */
    static public function maxInList($list, $attribute, $init) {
        $get_fnc = 'get'.ucfirst($attribute);
        $value = $init;
        foreach($list as $elmt) {
            if ($value < $elmt->$get_fnc()) {
                $value = $elmt->$get_fnc();
            }
        }
        return $value;
    }

    static public function offset($offset, $page_number, $count, $page_size) {
        if (!is_null($offset)) {
            $offset = intdiv($offset, $page_size) * $page_size;
        } elseif (!is_null($page_number) && $page_number > 0) {
            $page_number = min($page_number, intdiv($count - 1, $page_size) + 1);
            $offset = ($page_number - 1) * $page_size;
        } else {
            $offset = 0;
        }

        return $offset;
    }

    /**
     * find first object in $list where the content of $field equals $value .
     */
    static public function findFirst($list, $field, $value) {
        $getfnc = 'get'.ucfirst($field);
        $match = null;
        foreach ($list as $item) {
            if ($item->$getfnc() == $value) {
                $match = $item;
                break;
            }
        }

        return $match;
    }

    /**
     * replace object in $list where the value of $field matches
     */
    static public function replaceInList($list, $obj, $field) {
        $getfnc = 'get'.ucfirst($field);
        $match = false;
        foreach ($list as $key => $item) {
            if ($item->$getfnc() == $obj->$getfnc()) {
                $list[$key] = $obj;
                $match = true;
                break;
            }
        }

        return $match;
    }

    static public function validPropertyList($list, $field, $positive = 'positive') {
        $prop_value_list = array();
        $getfnc = 'get'.ucfirst($field);
        foreach ($list as $item) {
            $val = $item->$getfnc();
            if (!is_null($val)) {
                if ($positive == 'positive') {
                    if ($val > 0) {
                        $prop_value_list[] = $val;
                    }
                } else {
                    $prop_value_list[] = $val;
                }
            }
        }
        return $prop_value_list;
    }

    static public function nestedArray($iterator) {
        $arr = array();
        foreach ($iterator as $item) {
            $arr[] = $item->toArray();
        }

        return $arr;
    }

    /**
     * permute the elements of $items and store the result in $result
     */
    static public function pc_permute($items, &$result, $perms = array( )) {
        if (empty($items)) {
            $result[] = $perms;
        }  else {
            for ($i = count($items) - 1; $i >= 0; --$i) {
                $newitems = $items;
                $newperms = $perms;
                list($foo) = array_splice($newitems, $i, 1);
                array_unshift($newperms, $foo);
                self::pc_permute($newitems, $result, $newperms);
            }
        }
    }

    /**
     * return cartesian product of the input arrays
     * see https://stackoverflow.com/questions/2516599/cartesian-product-of-n-arrays
     */
    static public function array_cartesian() {
        $_ = func_get_args();
        if(count($_) == 0)
            return array(array());
        $a = array_shift($_);
        $c = call_user_func_array(__METHOD__, $_);
        // (__FUNCTION__, $_);
        $r = array();
        foreach($a as $v) {
            foreach($c as $p) {
                $r[] = array_merge(array($v), $p);
            }
        }
        return $r;
    }

    /**
     * split up $param
     * before 2023-08-01: but not words beginning with lowercase characters
     */
    static public function nameQueryComponents(string $param) {
        $param_list = array_filter(explode(" ", trim($param)));

        // before 2023-08-01
        // $list = array();
        // $rest = array();
        // foreach ($param_list as $p) {
        //         $rest[] = $pt;
        //         if (\IntlChar::isupper($p[0])) {
        //             $list[] = implode(" ", $rest);
        //             $rest = array();
        //         }
        //     }
        // if (count($rest) > 0) {
        //     $list[] = implode(" ", $rest);
        // }

        return $param_list;
    }

}
