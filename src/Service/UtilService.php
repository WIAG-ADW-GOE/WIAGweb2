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
            $f_dump = false;
            foreach ($crit_list as $field) {
                $a_val = $a[$field];
                $b_val = $b[$field];
                // sort null last
                if (is_null($a_val) && !is_null($b_val)) {
                    $is_less = false;
                    $cmp_val = 1;
                    break;
                }

                if (is_null($b_val) && !is_null($a_val)) {
                    $is_less = true;
                    $cmp_val = -1;
                    break;
                }

                if ($a_val < $b_val) {
                    $cmp_val = -1;
                    if ($f_dump) dump($a, $b, $cmp_val, $field);
                    break;
                } elseif ($a_val > $b_val) {
                    $cmp_val = 1;
                    if ($f_dump) dump($a, $b, $cmp_val, $field);
                    break;
                }
            }

            return $cmp_val;
        });

        return $list;
    }

    /**
     * use $field to reorder $list
     */
    public function reorder($list, $sorted, $field = "id") {
        $sorted_flip = array_flip($sorted);
        $reordered = array();
        $getfnc = 'get'.ucfirst($field);
        foreach($list as $el) {
            $key = $sorted_flip[$el->$getfnc()];
            // dump($key);
            $reordered[$key] = $el;
        }

        ksort($reordered);
        return $reordered;
    }

}
