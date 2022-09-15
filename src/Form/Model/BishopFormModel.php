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
    public $isOnline = true;
    public $isDeleted = 0;
    public $editStatus = ['fertig'];
    public $commentDuplicate = null;
    public $comment = null;
    public $dateCreated = null;
    public $dateChanged = null;
    public $listSize = null;
    public $facetDiocese = null;
    public $facetOffice = null;

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
        $default_list = [
            'isOnline' => true,
            'isDeleted' => false,
            'commentDuplicate' => null,
            'comment' => null,
            'dateCreated' => null,
            'dateChanged' => null,
            'listSize' => 5,
        ];

        foreach ($default_list as $key => $value) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }

        $keys = ['name', 'diocese', 'office', 'year', 'someid', 'isOnline', 'isDeleted', 'commentDuplicate', 'comment', 'dateCreated', 'dateChanged'];
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
