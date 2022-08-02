<?php
namespace App\Form\Model;

use App\Entity\FacetChoice;

/**
 * Model for bishop form data
 *
 * Facets depend on form data. Therefore it makes sense to store them in a class.
 */
class EditBishopFormModel {
    public $name = null;
    public $diocese = null;
    public $office = null;
    public $year = null;
    public $someid = null;
    public $isOnline = null;
    public $editStatus = null;

    public static function newByArray($data) {
        $model = new self();

        $keys = ['name', 'diocese', 'office', 'year', 'someid', 'isOnline', 'editStatus'];
        foreach($keys as $key) {
            $model->$key = $data[$key];
        }

        return $model;
    }

    public function isEmpty() {
        $value = true;
        $keys = ['name', 'diocese', 'office', 'year', 'someid', 'isOnline', 'editStatus'];
        foreach($keys as $key) {
            $value = $value && is_null($this->$key);
        }

        return $value;
    }

}
