<?php

namespace App\Service;

use App\Entity\Diocese;
use App\Entity\UrlExternal;
use App\Entity\SkosLabel;
use App\Entity\Authority;
use App\Entity\InputError;

use Doctrine\ORM\EntityManagerInterface;



/**
 * provide mapping functions
 */
class EditService {

    /**
     * fill url external with $data
     */
    static public function mapUrlExternal($item, $data, $entityManager) {

        $authorityRepository = $entityManager->getRepository(Authority::class);

        $uext_list = $item->getUrlExternal();
        foreach ($data as $data_loop) {
            $id = $data_loop['id'];
            $uext = null;
            if (!($id > 0)) {
                $uext = new UrlExternal();
                $uext->setItem($item);
                if (!is_null($item->getId())) {
                    $uext->setItemId($item->getId());
                }
                $uext_list->add($uext);
            } else {
                // find uext
                foreach ($uext_list as $uext_loop) {
                    if ($uext_loop->getId() == $id) {
                        $uext = $uext_loop;
                        break;
                    }
                }
            }

            $authority_name = $data_loop['urlName'];
            $value = $data_loop['value'];
            $delete_flag = $data_loop['deleteFlag'];
            // if $value is empty the external URL is removed.
            if (trim($value) != "" and $delete_flag != 'delete') {
                if (trim($authority_name) == "") {
                    $msg = "'URL Typ' kann nicht leer sein.";
                    $item->getInputError()->add(new InputError('external id', $msg));
                } else {
                    $auth_query = $authorityRepository->findByUrlNameFormatter($authority_name);
                    if (!is_null($auth_query) && count($auth_query) > 0) {
                        $authority = $auth_query[0];
                        $uext->setAuthorityId($authority->getId());
                        $uext->setAuthority($authority);
                    } else {
                        $msg = "Keine eindeutige Institution für '".$authority_name."' gefunden.";
                        $item->getInputError()->add(new InputError('external id', $msg));
                    }
                }
            }

            // drop base URL if present
            if ($authority_name == 'Wikipedia-Artikel') {
                $val_list = explode('/', $value);
                $value = array_slice($val_list, -1)[0];
            }

            $key_list = ['deleteFlag', 'value', 'note'];
            UtilService::setByKeys($uext, $data_loop, $key_list);
        }

        return $item;
    }

    /**
     * remove external URLs that are marked for deletion
     */
    static public function removeUrlExternalMayBe($item, $entityManager) {
        $uext_list = $item->getUrlExternal();

        foreach ($uext_list as $uext) {
            if ($uext->getDeleteFlag() == "delete" or $uext->getValue() == "") {
                $uext_list->removeElement($uext); // seems to be stable
                if (!$item->getIsNew()) {
                    $entityManager->remove($uext);
                }
            }
        }
        return $item;
    }

    /**
     * fill skos label with $data
     */
    static public function mapSkosLabel($obj, $data) {
        $label_list = $obj->getAltLabels();
        foreach ($data as $data_loop) {
            $id = $data_loop['id'];
            $label = null;
            if (!($id > 0)) {
                $label = new SkosLabel(Diocese::SKOS_SCHEME_ID);
                // $concept_id = $obj->getItem()->getId() ?? 0;
                // $label->setConceptId($concept_id);
                $label->setDiocese($obj);
                $label_list->add($label);
            } else {
                // find label
                foreach ($label_list as $label_loop) {
                    if ($label_loop->getId() == $id) {
                        $label = $label_loop;
                        break;
                    }
                }
            }

            $key_list = ['deleteFlag', 'lang', 'label', 'displayOrder', 'comment'];
            // TODO
            UtilService::setByKeys($label, $data_loop, $key_list);
        }

        return $obj;
    }

    /**
     * remove external URLs that are marked for deletion
     */
    static public function removeSkosLabelMayBe($obj, $entityManager) {
        $label_list = $obj->getAltLabels();

        foreach ($label_list as $label) {
            if ($label->getDeleteFlag() == "delete" or $label->getLabel() == "") {
                $label_list->removeElement($label); // seems to be stable
                if (!$obj->getItem()->getIsNew()) {
                    $entityManager->remove($label);
                }
            }
        }
        return $obj;
    }


}
