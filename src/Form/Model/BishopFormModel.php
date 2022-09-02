<?php
namespace App\Form\Model;

use App\Entity\FacetChoice;

/**
 * Model for bishop form data
 *
 * Facets depend on form data. Therefore it makes sense to store them in a class.
 */
class BishopFormModel {
    public $name = null;
    public $diocese = null;
    public $office = null;
    public $year = null;
    public $someid = null;
    public $isOnline = null;
    public $isDeleted = null;
    public $editStatus = null;
    public $commentDuplicate = null;
    public $facetDiocese = null;
    public $facetOffice = null;
    public $listSize = null;

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

        // set defaults
        if (!array_key_exists('isOnline', $data)) {
            $data['isOnline'] = true;
        }
        if (!array_key_exists('isDeleted', $data)) {
            $data['isDeleted'] = false;
        }
        if (!array_key_exists('commentDuplicate', $data)) {
            $data['commentDuplicate'] = null;
        }
        if (!array_key_exists('listSize', $data)) {
            $data['listSize'] = 5;
        }



        $keys = ['name', 'diocese', 'office', 'year', 'someid', 'isOnline', 'isDeleted', 'commentDuplicate'];
        foreach($keys as $key) {
            $model->$key = $data[$key];
        }

        $model->facetDiocese = self::makeChoices($data, 'facetDiocese');
        $model->facetOffice = self::makeChoices($data, 'facetOffice');
        return $model;
    }

    public function isEmpty() {
        $value = true;
        // list only elements that are relevant in a regular query (not edit)
        $keys = ['name', 'diocese', 'office', 'year', 'someid'];
        foreach($keys as $key) {
            $value = $value && is_null($this->$key);
        }

        return $value;
    }

    public function hasFacets() {
        $value = false;
        $keys = ['facetDiocese', 'facetOffice'];
        foreach($keys as $key) {
            $value = $value || !is_null($this->$key);
        }

        return $value;
    }


}
