<?php
namespace App\Form\Model;


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
