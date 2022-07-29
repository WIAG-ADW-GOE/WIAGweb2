<?php

namespace App\Service;


class UtilService {

    public function __construct() {

    }

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
     * use criteria in $crit_list to sort $list (array of arrays)
     */
    public function sortByFieldList($list, $crit_list) {
        uasort($list, function($a, $b) use ($crit_list) {
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
                // sort null last

                if (is_null($a_val) && is_null($b_val)) {
                    $cmp_val = 0;
                    continue;
                }

                if (is_null($a_val) && !is_null($b_val)) {
                    $cmp_val = 1;
                    break;
                }

                if (is_null($b_val) && !is_null($a_val)) {
                    $cmp_val = -1;
                    break;
                }

                if ($a_val < $b_val) {
                    $cmp_val = -1;
                    break;
                } elseif ($a_val > $b_val) {
                    $cmp_val = 1;
                    break;
                } else {
                    $cmp_val = 0;
                }
            }

            return $cmp_val;
        });

        return $list;
    }

    /**
     * use criteria in $crit_list to sort $list (array of arrays)
     */
    public function sortByDomstift($list, $domstift) {
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

            if ($a_val == $domstift || $a_val == $domstift_long) {
                if ($b_val == $domstift || $b_val == $domstift_long) {
                    return 0;
                } else {
                    return -1;
                }
            } elseif ($b_val == $domstift || $b_val == $domstift_long) {
                return 1;
            } else {
                return 0;
            }
        });

        return $list;
    }

    /**
     * use $field to reorder $list
     *
     * An index in $sorted may occur several times as a value in $list
     */
    public function reorder($list, $sorted, $field = "id") {
        // function to get the criterion
        $getfnc = 'get'.ucfirst($field);
        $idx_map = array_flip($sorted);

        uasort($list, function($a, $b) use ($idx_map, $getfnc) {
            $idx_a = $idx_map[$a->$getfnc()];
            $idx_b = $idx_map[$b->$getfnc()];

            $cmp_val =  $idx_a == $idx_b ? 0 : ($idx_a < $idx_b ? -1 : +1);
            return $cmp_val;
        });

        return $list;
    }

}
