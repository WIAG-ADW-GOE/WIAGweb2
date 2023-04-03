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
    public $institution = null;
    public $office = null;
    public $place = null;
    public $year = null;
    public $someid = null;
    public $facetInstitution = null;
    public $facetOffice = null;
    public $facetPlace = null;
    public $facetUrl = null;

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

        $keys = ['name', 'institution', 'office', 'place', 'year', 'someid'];
        foreach($keys as $key) {
            $model->$key = $data[$key];
        }

        $model->facetInstitution = self::makeChoices($data, 'facetInstitution');
        $model->facetOffice = self::makeChoices($data, 'facetOffice');
        $model->facetPlace = self::makeChoices($data, 'facetPlace');
        $model->facetUrl = self::makeChoices($data, 'facetUrl');
        return $model;
    }

    public function isEmpty() {
        $result = true;
        $keys = ['name', 'institution', 'office', 'place', 'year', 'someid'];
        foreach($keys as $key) {
            $result = $result && !$this->$key;
        }

        return $result;
    }

}
