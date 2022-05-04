<?php

namespace App\Service;


use App\Entity\Item;
use App\Entity\Authority;

use Doctrine\ORM\EntityManagerInterface;

class ItemService {

    private $em;

    public function __construct(EntityManagerInterface $em) {
        $this->em = $em;
    }

    /**
     * collect office data from different sources
     */
    public function getBishopOfficeData($person) {

        $repository = $this->em->getRepository(Item::class);

        $item = array($person->getItem());
        // get office data from Germania Sacra
        $authorityGs = Authority::ID['Germania Sacra'];
        $gsn = $person->getIdExternal($authorityGs);
        if (!is_null($gsn)) {
            // if data are up to date at most of these requests is successful.
            $itemTypeBishopGs = Item::ITEM_TYPE_ID['Bischof GS'];
            $bishopGs = $repository->findByIdExternal($itemTypeBishopGs, $gsn, $authorityGs);
            $item = array_merge($item, $bishopGs);

            $itemTypeCanonGs = Item::ITEM_TYPE_ID['Domherr GS'];
            $canonGs = $repository->findByIdExternal($itemTypeCanonGs, $gsn, $authorityGs);
            $item = array_merge($item, $canonGs);
        }

        // get data from Domherrendatenbank
        $authorityWIAG = Authority::ID['WIAG-ID'];
        $wiagid = $person->getItem()->getIdPublic();
        if (!is_null($wiagid)) {
            $itemTypeCanon = Item::ITEM_TYPE_ID['Domherr'];
            $canon = $repository->findByIdExternal($itemTypeCanon, $wiagid, $authorityWIAG);
            $item = array_merge($item, $canon);
        }
        return $item;
    }

}
