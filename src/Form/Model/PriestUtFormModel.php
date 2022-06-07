<?php
namespace App\Form\Model;

use App\Entity\FacetChoice;

/**
 * Model for priestUt form data
 *
 * Facets depend on form data. Therefore it makes sense to store them in a class.
 */
class PriestUtFormModel {
    public $name = null;
    public $birthplace = null;
    public $religiousOrder = null;
    public $year = null;
    public $someid = null;
    public $facetReligiousOrder = null;

    static public function makeChoices($data, $key) {
        $choices = [];
        if (array_key_exists($key, $data)) {
            $choices;
            foreach($data[$key] as $item) {
                $choices[] = new FacetChoice($item, 0);
            }
            return $choices;
        } else {
            return null;
        }
    }

    public static function newByArray($data) {
        $model = new self();

        $keys = ['name', 'birthplace', 'religiousOrder', 'year', 'someid'];
        foreach($keys as $key) {
            $model->$key = $data[$key];
        }

        $model->facetReligiousOrder = self::makeChoices($data, 'facetReligiousOrder');
        return $model;
    }

    public function isEmpty() {
        $value = true;
        $keys = ['name', 'birthplace', 'religiousOrder', 'year', 'someid'];
        foreach($keys as $key) {
            $value = $value && is_null($this->$key);
        }

        return $value;
    }

    public function hasFacets() {
        $value = false;
        $keys = ['facetReligiousOrder'];
        foreach($keys as $key) {
            $value = $value || !is_null($this->$key);
        }

        return $value;
    }


}
