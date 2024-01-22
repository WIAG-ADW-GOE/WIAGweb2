<?php

namespace App\Service;

use App\Entity\Corpus;
use App\Entity\Diocese;
use App\Entity\UrlExternal;
use App\Entity\SkosLabel;
use App\Entity\Authority;
use App\Entity\InputError;
use App\Entity\ItemCorpus;
use App\Entity\ItemReference;
use App\Entity\ReferenceVolume;

use Doctrine\ORM\EntityManagerInterface;


/**
 * provide mapping functions
 *
 * most functions have $entityManager in their argument lists,
 * thus they can be declared as static
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
    static public function removeReferenceMayBe($item, $entityManager) {
        $ref_list = $item->getReference();

        foreach ($ref_list as $ref) {
            if ($ref->getDeleteFlag() == "delete") {
                $ref_list->removeElement($ref); // seems to be stable
                if (!$item->getIsNew()) {
                    $entityManager->remove($ref);
                }
            }
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
     * fill items's references with $data
     */
    static public function mapReference($item, $data, $entityManager) {
        $referenceRepository = $entityManager->getRepository(ItemReference::class);
        $volumeRepository = $entityManager->getRepository(ReferenceVolume::class);
        $ref_list = $item->getReference();

        $n = 0;
        foreach($data as $data_loop) {

            $key_list = ['volume', 'page', 'idInReference'];
            $no_data = UtilService::no_data($data_loop, $key_list);
            $reference = null;


            if ($no_data) {
                continue;
            } else {
                $id = $data_loop['id'];
                if (!($id > 0)) {
                    $reference = new ItemReference();
                    $ref_list->add($reference);
                    $reference->setItem($item);
                } else {
                    // find reference
                    foreach ($ref_list as $ref_loop) {
                        if ($ref_loop->getId() ==$id) {
                            $reference = $ref_loop;
                            break;
                        }
                    }
                }
            }
            // set data
            $volume_name = trim($data_loop['volume']);
            $reference->setVolumeTitleShort($volume_name); # save data for the form

            if ($volume_name != "") {
                $volume_query_result = $volumeRepository->findByTitleShort($volume_name);
                if ($volume_query_result) {
                    $volume = $volume_query_result[0];
                    $reference->setReferenceId($volume->getReferenceId());
                } else {
                    $error_msg = "Keinen Band für '".$volume_name."' gefunden.";
                    $item->getInputError()->add(new InputError('reference', $error_msg));
                }
                $n += 1;
            } else {
                $error_msg = "Das Feld 'Bandtitel' darf nicht leer sein.";
                $item->getInputError()->add(new InputError('reference', $error_msg));
            }

            $key_list = ['deleteFlag', 'page','idInReference'];
            UtilService::setByKeys($reference, $data_loop, $key_list);
        }

        return $n;
    }

    /**
     * compose ID public
     */
    static public function makeIdPublic($corpus_id, $entityManager)  {
        $corpusRepository = $entityManager->getRepository(Corpus::class);
        $corpus = $corpusRepository->findOneByCorpusId($corpus_id);

        // find number fields
        $match_list = null;
        $mask = $corpus->getIdPublicMask();
        $next_id = $corpus->getNextIdPublic();
        $id_public = null;
        if (!is_null($mask)) {
            preg_match_all("/#+/", $mask, $match_list);

            $field = $match_list[0][0];
            $width = strlen($field);
            $numeric_str = str_pad($next_id, $width, "0", STR_PAD_LEFT);
            $id_public = preg_replace("/#+/", $numeric_str, $mask, 1);

            $corpus->setNextIdPublic($next_id + 1);

            // second numeric_field: default is '001'
            $field = $match_list[0][1];
            $width = strlen($field);
            $numeric_field = str_pad("1", $width, "0", STR_PAD_LEFT);
            $id_public = str_replace($field, $numeric_field, $id_public);
        }

        return $id_public;
    }

    /**
     *
     */
    static public function setNewItemCorpus($item, $corpus_id, $entityManager) {
        $itemCorpusRepository = $entityManager->getRepository(ItemCorpus::class);

        $item_corpus = new ItemCorpus();
        $item_corpus->setItem($item);
        $item->getItemCorpus()->add($item_corpus);
        $item_corpus->setCorpusId($corpus_id);


        // ID in corpus
        $id_in_corpus = intval($itemCorpusRepository->findMaxIdInCorpus($corpus_id)) + 1;
        $id_in_corpus = strval($id_in_corpus);
        $item_corpus->setIdInCorpus($id_in_corpus);

        // public ID
        $id_public = self::makeIdPublic($corpus_id, $entityManager);
        $item_corpus->setIdPublic($id_public);

        return $item_corpus;
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
