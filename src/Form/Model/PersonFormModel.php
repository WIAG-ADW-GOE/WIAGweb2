<?php
namespace App\Form\Model;


use App\Entity\FacetChoice;
use App\Entity\InputError;
use App\Service\UtilService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Model for person query form data
 *
 * Facets depend on form data. Therefore it makes sense to store them in a class.
 */
class PersonFormModel {
    public $corpus = null;
    public $name = null;
    public $domstift = null;
    public $diocese = null;
    public $monastery = null;
    public $office = null;
    public $place = null;
    public $year = null;
    public $someid = null;
    public $isOnline = null;
    public $isDeleted = 0;
    public $editStatus = [];
    public $commentDuplicate = null;
    public $comment = null;
    public $misc = null;
    public $reference = null;
    public $dateCreated = null;
    public $dateChanged = null;
    public $listSize = null;
    public $facetDiocese = null;
    public $facetDomstift = null;
    public $facetOffice = null;
    public $facetPlace = null;
    public $facetUrl = null;
    public $sortBy = null;
    public $sortOrder = 'ASC';
    public $isEdit = false;


    /**
     * collection of InputError in the query form
     */
    private $inputError;

    public function __construct() {
        $this->inputError = new ArrayCollection();
    }

    /**
     * do not provide setInputError; use add or remove to manipulate this property
     */
    public function getInputError() {
        return $this->inputError;
    }

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
            'corpus' => '',
            'isOnline' => true,
            'isDeleted' => false,
            'commentDuplicate' => null,
            'comment' => null,
            'misc' => null,
            'dateCreated' => null,
            'dateChanged' => null,
            'listSize' => 5,
            'name' => null,
            'domstift' => null,
            'diocese' => null,
            'monastery' => null,
            'office' => null,
            'place' => null,
            'year' => null,
            'someid' => null,
            'sortBy' => null,
            'sortOrder' => 'ASC',
            'isEdit' => false,
        ];

        foreach ($default_list as $key => $value) {
            if (array_key_exists($key, $data)) {
                $model->$key = $data[$key];
            } else {
                $model->$key = $value;
            }
        }

        $model->facetDomstift = self::makeChoices($data, 'facetDomstift');
        $model->facetDiocese = self::makeChoices($data, 'facetDiocese');
        $model->facetOffice = self::makeChoices($data, 'facetOffice');
        $model->facetPlace = self::makeChoices($data, 'facetPlace');
        $model->facetUrl = self::makeChoices($data, 'facetUrl');

        return $model;
    }

    public function isEmpty() {
        $value = true;
        // list only elements that are relevant in a regular query (not edit)
        $keys = ['name', 'diocese', 'domstift', 'place', 'office', 'year', 'someid'];
        foreach($keys as $key) {
            $value = $value && (is_null($this->$key) or trim($this->$key) == "");
        }

        return $value;
    }

    public function isValid() {
        // check dates
        $format_msg = "Format: dd.mm.[20]yy oder Datum-Datum.";
        $dateCreated = trim($this->dateCreated);
        if ($dateCreated != "") {
            $range = UtilService::dateRange($dateCreated);
            foreach ($range as $range_elmt) {
                if ($range_elmt === false) {
                    $msg = "Ungültiges Datumsformat: ".$dateCreated."; ".$format_msg;
                    $this->inputError->add(new InputError('two', $msg, 'error'));
                    break;
                }
            }
        }

        $dateChanged = trim($this->dateChanged);
        if ($dateChanged != "") {
            $range = UtilService::dateRange($dateChanged);
            foreach ($range as $range_elmt) {
                $msg = "Ungültiges Datumsformat: ".$dateChanged."; ".$format_msg;
                if ($range_elmt === false) {
                    $this->inputError->add(new InputError('two', $msg, 'error'));
                    break;
                }
            }
        }

        return $this->inputError->isEmpty();
    }


}
