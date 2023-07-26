<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Person;
use App\Entity\Authority;
use App\Entity\Institution;
use App\Entity\Gso\Persons;
use App\Entity\Gso\Items;

use App\Service\UtilService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class GsoController extends AbstractController {
    const CHUNK_SIZE = 1000;

    /**
     *
     * @Route("/edit/gso-update-canons", name="gso_update_canons")
     */
    public function gsoUpdateCanons(Request $request, ManagerRegistry $doctrine) {

        $entityManager_gso = $doctrine->getManager('gso');
        $entityManager = $doctrine->getManager('default');

        $itemRepository = $entityManager->getRepository(Item::class, 'default');
        $personRepository = $entityManager->getRepository(Person::class, 'default');

        $gsoPersonsRepository = $doctrine->getRepository(Persons::class, 'gso');

        // get canons in WIAG
        $item_type_id = [
            Item::ITEM_TYPE_ID['Domherr GS']['id'],
            Item::ITEM_TYPE_ID['Bischof GS']['id'],
        ];

        $item_list = $itemRepository->findGsnByItemTypeId($item_type_id);
        $gsn_list = array_column($item_list, 'gsn');

        // get meta data for those canons from GSO
        $gso_item_list = $this->personGsoIdsByList($doctrine, $gsn_list);

        // missing list holds elements of $item_list not found in GSO
        list($update_list, $missing_list) = $this->updateRequired($item_list, $gso_item_list, 'gsn');

        $id_list = array_column($update_list, 'id');
        $person_update_list = $personRepository->findList($id_list);
        $id_missing_list = array_column($missing_list, 'id');
        $person_missing_list = $personRepository->findList($id_missing_list);

        // find all canons in GSO (by Domstift)
        $canon_gso_ids = $this->canonGsoIds($doctrine);

        $canon_gso_ids_new = UtilService::arrayDiffByField($canon_gso_ids, $gso_item_list, 'id');
        $id_new_list = array_column($canon_gso_ids_new, 'id');

        $person_new_list = $gsoPersonsRepository->findList($id_new_list);

        return $this->render("gso/update.html.twig", [
            'countReferenced' => count($item_list),
            'updateList' => $person_update_list,
            'missingList' => $person_missing_list,
            'newList' => $person_new_list,
        ]);

    }

    /**
     * return array of elements in $a that need an update and array of missing elements in $b
     */
    private function updateRequired($a, $b, $field) {
        $delta = array();
        $update = array();
        if (count($a) == 0) {
            return $delta;
        }
        if (count($b) == 0) {
            return $a;
        }

        $a_sorted = UtilService::sortByFieldList($a, [$field]);
        $b_sorted = UtilService::sortByFieldList($b, [$field]);
        $key = 0;
        $current_a = current($a_sorted);
        $current_b = current($b_sorted);
        while(true) {
            if ($current_a[$field] == $current_b[$field]) {
                if ($current_a['dateChanged'] < $current_b['dateChanged']) {
                    $current_a['gso_id'] = $current_b['id'];
                    $update[] = $current_a;
                }
                $current_a = next($a_sorted);
                $current_b = next($b_sorted);
                if ($current_a === false and is_null(key($a_sorted))) {
                    break;
                }
                if ($current_b === false and is_null(key($b_sorted))) {
                    while ($current_a and !is_null(key($a_sorted))) {
                        $delta[] = $current_a;
                        $current_a = next($a_sorted);
                    }
                    break;
                }
            } elseif ($current_a[$field] < $current_b[$field]) {
                $delta[] = $current_a;
                $current_a = next($a_sorted);
                if ($current_a === false and key($a_sorted) == null) {
                    break;
                }
            } else {
                $current_b = next($b_sorted);
                if ($current_b === false and is_null(key($b_sorted))) {
                    while ($current_a and !is_null(key($a_sorted))) {
                        $delta[] = $current_a;
                        $current_a = next($a_sorted);
                    } break;
                }
            }
        }
        return [$update, $delta];

    }

    /**
     * @ return meta data for entries with GSN in $gsn_list
     */
    private function personGsoIdsByList(ManagerRegistry $doctrine, $gsn_list) {
        $gsoItemsRepository = $doctrine->getRepository(Items::class, 'gso');

        $gso_list = array();
        $chunk_offset = 0;
        $gsn_list_cycle = array_slice($gsn_list, $chunk_offset, self::CHUNK_SIZE);
        while (count($gsn_list_cycle) > 0) {
            $gso_list_cycle = $gsoItemsRepository->findIdsByGsnList($gsn_list_cycle);
            $gso_list = array_merge($gso_list, $gso_list_cycle);
            $chunk_offset+= self::CHUNK_SIZE;
            $gsn_list_cycle = array_slice($gsn_list, $chunk_offset, self::CHUNK_SIZE);
        }

        return $gso_list;
    }


    /**
     * @return id, gsn and modification date for canons (by Domstift)
     */
    private function canonGsoIds(ManagerRegistry $doctrine) {
        $entityManager_gso = $doctrine->getManager('gso');
        $entityManager = $doctrine->getManager('default');

        $personsRepository = $entityManager_gso->getRepository(Persons::class, 'gso');

        $institutionRepository = $entityManager->getRepository(Institution::class, 'default');

        $domstift_list = $institutionRepository->findIfHasItemProperty('domstift_short');

        $domstift_gsn_list = array();
        foreach($domstift_list as $domstift) {
            $domstift_gsn_list[] = $domstift->getIdGsn();
        }

        return $personsRepository->findCanonIds($domstift_gsn_list);

    }

}
