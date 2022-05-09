<?php

namespace App\Service;


use App\Entity\Item;
use App\Entity\Authority;
use App\Entity\PersonRole;
use App\Entity\ReferenceVolume;
use App\Entity\CanonLookup;

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
        // get item from Germania Sacra
        $authorityGs = Authority::ID['Germania Sacra'];
        $gsn = $person->getIdExternal($authorityGs);
        if (!is_null($gsn)) {
            // Each person from Germania Sacra should have an entry in table id_external with it's GSN.
            // If data are up to date at most of these requests is successful.
            $itemTypeBishopGs = Item::ITEM_TYPE_ID['Bischof GS'];
            $bishopGs = $repository->findByIdExternal($itemTypeBishopGs, $gsn, $authorityGs);
            $item = array_merge($item, $bishopGs);

            $itemTypeCanonGs = Item::ITEM_TYPE_ID['Domherr GS'];
            $canonGs = $repository->findByIdExternal($itemTypeCanonGs, $gsn, $authorityGs);
            $item = array_merge($item, $canonGs);
        }

        // get item from Domherrendatenbank
        $authorityWIAG = Authority::ID['WIAG-ID'];
        $wiagid = $person->getItem()->getIdPublic();
        if (!is_null($wiagid)) {
            $itemTypeCanon = Item::ITEM_TYPE_ID['Domherr'];
            $canon = $repository->findByIdExternal($itemTypeCanon, $wiagid, $authorityWIAG);
            $item = array_merge($item, $canon);
        }

        // get office data and references
        $personRoleRepository = $this->em->getRepository(PersonRole::class);
        $referenceVolumeRepository = $this->em->getRepository(ReferenceVolume::class);
        foreach ($item as $item_loop) {
            $item_id = $item_loop->getId();
            $person = $item_loop->getPerson();
            $person->setRole($personRoleRepository->findRoleWithPlace($item_id));
            $referenceVolumeRepository->addReferenceVolumes($item_loop);
        }

        return $item;
    }

    public function getCanonOfficeData($person) {
        $itemRepository = $this->em->getRepository(Item::class);
        $canonLookupRepository = $this->em->getRepository(CanonLookup::class);
        $ids = $canonLookupRepository->getRoleIds($person->getId());
        $item = array();
        $personRoleRepository = $this->em->getRepository(PersonRole::class);
        $referenceVolumeRepository = $this->em->getRepository(ReferenceVolume::class);
        foreach ($ids as $id) {
            $item_loop = $itemRepository->find($id);
            $person = $item_loop->getPerson();
            $person->setRole($personRoleRepository->findRoleWithPlace($id));
            $referenceVolumeRepository->addReferenceVolumes($item_loop);
            $item[] = $item_loop;
        }

        return $item;
    }

}
