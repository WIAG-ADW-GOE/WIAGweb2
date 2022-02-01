<?php
namespace App\Form\Model;

use App\Entity\FacetChoice;

/**
 * Model for canon form data
 *
 * Facets depend on form data. Therefore it makes sense to store them in a class.
 */
class CanonFormModel {
    public $name = null;
    public $domstift = null;
    public $office = null;
    public $place = null;
    public $year = null;
    public $someid = null;
    public $facetDomstift = null;
    public $facetOffice = null;
    public $facetPlace = null;

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

        $keys = ['name', 'domstift', 'office', 'place', 'year', 'someid'];
        foreach($keys as $key) {
            $model->$key = $data[$key];
        }

        $model->facetDiocese = self::makeChoices($data, 'facetDiocese');
        $model->facetOffice = self::makeChoices($data, 'facetOffice');
        return $model;
    }

    public function isEmpty() {
        $value = true;
        $keys = ['name', 'domstift', 'office', 'place', 'year', 'someid'];
        foreach($keys as $key) {
            $value = $value && is_null($this->$key);
        }

        return $value;
    }

}
