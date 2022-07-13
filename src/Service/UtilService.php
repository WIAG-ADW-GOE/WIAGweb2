<?php

namespace App\Service;


class UtilService {

    public function __construct() {

    }

    /**
     * merge sort array
     */
    public function mergesort($list, $field_list)
    {
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

            if (is_string($a_val)) {
                $a_val = str_replace("ü", "ue", $a_val);
                $b_val = str_replace("ü", "ue", $b_val);
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

}
