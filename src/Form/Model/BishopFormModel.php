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
        $model->name = $data['name'] ?? null;
        $model->diocese = $data['diocese'] ?? null;
        $model->office = $data['office'] ?? null;
        $model->year = $data['year'] ?? null;
        $model->someid = $data['someid'] ?? null;
        $model->facetDiocese = self::makeChoices($data, 'facetDiocese');
        $model->facetOffice = self::makeChoices($data, 'facetOffice');
        return $model;
    }

    public function isEmpty() {
        return (is_null($this->name)
                && is_null($this->diocese)
                && is_null($this->office)
                && is_null($this->year)
                && is_null($this->someid)
                && is_null($this->facetDiocese)
                && is_null($this->facetOffice));

    }

}
