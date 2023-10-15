<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Person;
use App\Entity\Authority;
use App\Entity\Institution;
use App\Entity\UrlExternal;
use App\Entity\NameLookup;
use App\Entity\CanonLookup;
use App\Entity\Gso\Persons;
use App\Entity\Gso\Items;

use App\Service\UtilService;
use App\Service\EditPersonService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class GsoController extends AbstractController {
    const CHUNK_SIZE = 1000;

    private $editPersonService;

    public function __construct(EditPersonService $editPersonService) {
        $this->editPersonService = $editPersonService;
    }

    /**
     *
     * @Route("/edit/gso-update-info", name="gso_update_info")
     */
    public function gsoUpdateInfo(Request $request, ManagerRegistry $doctrine) {

        $entityManager_gso = $doctrine->getManager('gso');
        $entityManager = $doctrine->getManager('default');

        $itemRepository = $entityManager->getRepository(Item::class, 'default');
        $personRepository = $entityManager->getRepository(Person::class, 'default');
        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class, 'default');
        $authorityRepository = $entityManager->getRepository(Authority::class, 'default');
        $auth_gs = $authorityRepository->findOneByUrlNameFormatter('Personendatenbank der Germania Sacra');

        $gsoPersonsRepository = $entityManager_gso->getRepository(Persons::class, 'gso');

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


        $id_list = array_keys($update_list);
        $person_update_list = $personRepository->findList($id_list);
        // add visible id_public
        $urlExternalRepository->setIdPublicVisible($person_update_list);

        $id_missing_list = array_column($missing_list, 'id');
        $person_missing_list = $personRepository->findList($id_missing_list);
        // add visible id_public
        $urlExternalRepository->setIdPublicVisible($person_missing_list);

        // find all canons in GSO (by Domstift)
        $canon_gso_ids = $this->canonGsoIds($doctrine);

        $canon_gso_ids_new = UtilService::arrayDiffByField($canon_gso_ids, $gso_item_list, 'person_id');
        $id_new_list = array_column($canon_gso_ids_new, 'person_id');

        $person_new_list = $gsoPersonsRepository->findList($id_new_list);

        return $this->render("gso/update_info.html.twig", [
            'countReferenced' => count($item_list),
            'updateList' => $person_update_list,
            'missingList' => $person_missing_list,
            'newList' => $person_new_list,
            'gsUrl' => $auth_gs->getUrlFormatter(),
        ]);

    }

    /**
     *
     * @Route("/edit/gso-update", name="gso_update")
     */
    public function gsoUpdate(Request $request, ManagerRegistry $doctrine) {
        $entityManager = $doctrine->getManager('default');
        $itemRepository = $doctrine->getRepository(Item::class, 'default');
        $personRepository = $doctrine->getRepository(Person::class, 'default');
        $urlExternalRepository = $doctrine->getRepository(UrlExternal::class, 'default');
        $nameLookupRepository = $doctrine->getRepository(NameLookup::class, 'default');
        $canonLookupRepository = $doctrine->getRepository(CanonLookup::class, 'default');

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

        // update data
        $person_update_list = $this->updateList($doctrine, $update_list);
        $entityManager->flush();
        // - read list from database (e.g. with reference volumes)
        $id_list = array_keys($update_list);
        $person_update_list = $personRepository->findList($id_list);

        // add visible id_public
        $urlExternalRepository->setIdPublicVisible($person_update_list);

        $id_missing_list = array_column($missing_list, 'id');
        $person_missing_list = $personRepository->findList($id_missing_list);

        // find all canons in GSO (by Domstift)
        $canon_gso_ids = $this->canonGsoIds($doctrine);

        $canon_gso_ids_new = UtilService::arrayDiffByField($canon_gso_ids, $gso_item_list, 'person_id');
        $id_new_list = array_column($canon_gso_ids_new, 'person_id');

        // insert new data
        $person_gso_list = $gsoPersonsRepository->findList($id_new_list);

        $person_insert_list = $this->insertList($doctrine, $person_gso_list);

        $entityManager->flush();

        // update lookup_tables
        $insert_id_list = array();
        foreach ($person_insert_list as $person_insert) {
            $insert_id_list[] = $person_insert->getId();
            $nameLookupRepository->update($person_insert);
            $canonLookupRepository->addCanonGsMayBe($person_insert);
        }

        return $this->render("gso/update.html.twig", [
            'countReferenced' => count($item_list),
            'updateList' => $person_update_list,
            'missingList' => $person_missing_list,
            'newList' => $person_insert_list
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
                    $current_a['gso_person_id'] = $current_b['person_id'];
                    $update[$current_a['id']] = $current_a;
                }
                $current_a = next($a_sorted);
                $current_b = next($b_sorted);
                if ($current_a === false and is_null(key($a_sorted))) {
                    break;
                }
                if ($current_b === false and is_null(key($b_sorted))) {
                    while ($current_a and !is_null(key($a_sorted))) {
                        $delta[$current_a['id']] = $current_a;
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
     * @return items.id, gsn and modification date for canons (by Domstift)
     */
    private function canonGsoIds(ManagerRegistry $doctrine) {
        $entityManager_gso = $doctrine->getManager('gso');
        $entityManager = $doctrine->getManager('default');

        $personsRepository = $entityManager_gso->getRepository(Persons::class, 'gso');

        $institutionRepository = $entityManager->getRepository(Institution::class, 'default');

        $item_type_domstift = Item::ITEM_TYPE_ID['Domstift']['id'];
        $domstift_list = $institutionRepository->findByItemTypeId($item_type_domstift);

        $domstift_gsn_list = array();
        foreach($domstift_list as $domstift) {
            $domstift_gsn_list[] = $domstift->getIdGsn();
        }

        return $personsRepository->findCanonIds($domstift_gsn_list);

    }

    /**
     * find GSO entry for each element in $update_list and update it's data
     */
    private function updateList($doctrine, $update_list) {
        $personRepository = $doctrine->getRepository(Person::class, 'default');
        $gsoPersonsRepository = $doctrine->getRepository(Persons::class, 'gso');
        $urlExternalRepository = $doctrine->getRepository(UrlExternal::class, 'default');

        $current_user_id = $this->getUser()->getId();

        $id_list = array_keys($update_list);
        $person_update_list = $personRepository->findList($id_list);

        foreach($person_update_list as $person_target) {
            $item_id = $person_target->getItem()->getId();
            $gso_person_id = $update_list[$item_id]['gso_person_id'];
            $person_gso_list = $gsoPersonsRepository->findList([$gso_person_id]);
            $person_gso = $person_gso_list[0];

            // update GSN, do not flush
            $gsn_old = $person_target->getItem()->getGsn();
            $gsn_new = $person_gso->getItem()->getIdPublic();
            if ($gsn_old != $gsn_new) {
                $urlExternalRepository->updateValue($gsn_old, $gsn_new);
            }

            $this->editPersonService->updateFromGso($person_target, $person_gso, $current_user_id);
        }
        return $person_update_list;
    }

    private function insertList($doctrine, $gso_insert_list) {
        $entityManager = $doctrine->getManager('default');

        $personRepository = $doctrine->getRepository(Person::class, 'default');
        $gsoPersonsRepository = $doctrine->getRepository(Persons::class, 'gso');
        $urlExternalRepository = $doctrine->getRepository(UrlExternal::class, 'default');
        $itemRepository = $doctrine->getRepository(Item::class, 'default');

        $item_type_id = Item::ITEM_TYPE_ID['Domherr GS']['id'];
        $current_user_id = $this->getUser()->getId();

        $person_insert_list = array();

        $next_num_id_public = $itemRepository->findMaxNumIdPublic($item_type_id) + 1;

        foreach($gso_insert_list as $person_gso) {
            $person_new = new Person($item_type_id, $current_user_id);
            $this->editPersonService->initMetaData($person_new, $item_type_id);
            $id_public = EditPersonService::makeIdPublic($item_type_id, $next_num_id_public);
            $next_num_id_public += 1;
            $person_new->getItem()->setIdPublic($id_public);
            $entityManager->persist($person_new);
            $entityManager->flush();
            // read object to obtain ID for roles etc. (ID is only available via Item);
            $person_id = $person_new->getItem()->getId();
            $person_new = $personRepository->findOneById($person_id);

            $this->editPersonService->updateFromGso($person_new, $person_gso, $current_user_id);
            // set GSN in an extra step
            $this->editPersonService->setGsn($person_new->getItem(), $person_gso->getItem()->getIdPublic());
            // canons from GSO are always online
            $person_new->getItem()->setIsOnline(1);
            $person_insert_list[] = $person_new;
        }
        return $person_insert_list;

    }

}
