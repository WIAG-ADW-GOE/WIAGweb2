<?php

namespace App\Service;


class EditService {

    /**
     * clear $target_list; copy collection $source_list to $target_list;
     */
    static function setItemAttributeList($target, $target_list, $source_list, $entityManager) {

        // - remove entries
        // $target_ref = $target->getItem()->getReference();
        foreach ($target_list as $t) {
            $target_list->removeElement($t);
            $t->setItem(null);
            $entityManager->remove($t);
        }

        // - set new entries
        foreach ($source_list as $i) {
            if (!$i->getDeleteFlag()) {
                $target_list->add($i);
                $i->setItem($target);
                $entityManager->persist($i);
            }
        }
    }

}
