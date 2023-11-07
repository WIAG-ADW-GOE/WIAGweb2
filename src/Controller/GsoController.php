<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemCorpus;
use App\Entity\ItemNameRole;
use App\Entity\Person;
use App\Entity\Authority;
use App\Entity\Institution;
use App\Entity\UrlExternal;
use App\Entity\NameLookup;
use App\Entity\CanonLookup;
use App\Entity\Gso\Persons;
use App\Entity\Gso\Items;
use App\Entity\Gso\Gsn;

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
        $entityManager = $doctrine->getManager('default');
        $authorityRepository = $entityManager->getRepository(Authority::class, 'default');

        // The element type of $data_transfer['person_new_list'] is PersonGso.
        // The elements of $data_transfer['person_update_list'] belong to 'dreg' or 'dreg-can'.
        $data_transfer = $this->collectPerson($doctrine);

        $auth_gs = $authorityRepository->find(Authority::ID['GSN']);
        return $this->render("gso/update_info.html.twig", [
            'countReferenced' => $data_transfer['count_ref'],
            'updateList' => $data_transfer['person_update_list'],
            'missingList' => $data_transfer['person_missing_list'],
            'newList' => $data_transfer['person_new_list'],
            'gsUrl' => $auth_gs->getUrlFormatter(),
            'isInfo' => true,
        ]);

    }

    /**
     * GSN may change in Digitales Personenregister; adopt the current GSN
     *
     * global update of GSN in WIAG (time consuming); see also collectPerson
     */
    public function updateGsn($doctrine) {
        $entityManager = $doctrine->getManager('default');
        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class, 'default');
        $gsoGsnRepository = $doctrine->getRepository(Gsn::class, 'gso');

        $uext_gsn_list = $urlExternalRepository->findAllGsn();

        $gsn_map = [];
        foreach ($uext_gsn_list as $uext_gsn) {
            $gsn = $uext_gsn->getValue();
            $gsn_current = $gsoGsnRepository->findCurrentGsn($gsn);
            if (!is_null($gsn_current) and $gsn_current['gsn'] != $gsn) {
                $gsn_map[$gsn] = $gsn_current['gsn'];
            }
        }


        $uext_list = $urlExternalRepository->findAllGsn(array_keys($gsn_map));
        foreach ($uext_list as $uext) {
            $uext->setValue($gsn_map[$uext->getValue()]);
        }
        $entityManager->flush();


        return $gsn_map;
    }

    /**
     *
     * @Route("/edit/gso-update", name="gso_update")
     */
    public function gsoUpdate(Request $request, ManagerRegistry $doctrine) {
        $entityManager = $doctrine->getManager('default');
        $authorityRepository = $entityManager->getRepository(Authority::class, 'default');
        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class, 'default');
        $itemNameRoleRepository = $entityManager->getRepository(itemNameRole::class, 'default');
        $nameLookupRepository = $entityManager->getRepository(nameLookup::class, 'default');


        // $data_transfer['meta_list'] contains GSO meta data for persons in $person_update_list.
        // The element type of $data_transfer['person_new_list'] is PersonGso.
        // The elements of $data_transfer['person_update_list'] belong to 'dreg' or 'dreg-can'.
        $data_transfer = $this->collectPerson($doctrine);

        // update data
        // - update entries and return list with all office data (corpus = 'dreg' or 'dreg-can');
        $person_updated_list = $this->updateList($doctrine, $data_transfer['meta_update_list']);
        $entityManager->flush();


        // insert new data
        // The element type of $person_inserted_list is Person
        $person_inserted_list = $this->insertList($doctrine, $data_transfer['person_new_list']);
        $entityManager->flush();

        // update item_name_role
        // Collect affected IDs of all corpora.
        $id_updated_list_dreg = UtilService::collectionColumn($person_updated_list, 'id');
        $iul_db = $urlExternalRepository->findIdsByIdList($id_updated_list_dreg, Authority::ID['GSN']);
        $id_updated_list = array_column($iul_db, 'id');

        $id_inserted_list_dreg = UtilService::collectionColumn($person_inserted_list, 'id');
        $iil_db = $urlExternalRepository->findIdsByIdList($id_inserted_list_dreg, Authority::ID['GSN']);
        $id_inserted_list = array_column($iil_db, 'id');

        $affected_id_list = array_merge($id_updated_list, $id_inserted_list);
        $itemNameRoleRepository->updateByIdList($affected_id_list);

        // update name_role
        foreach ($person_updated_list as $p_loop) {
            $nameLookupRepository->update($p_loop);
        }

        foreach ($person_inserted_list as $p_loop) {
            $nameLookupRepository->update($p_loop);
        }
        $entityManager->flush();


        $auth_gs = $authorityRepository->find(Authority::ID['GSN']);
        return $this->render("gso/update_info.html.twig", [
            'countReferenced' => $data_transfer['count_ref'],
            'updateList' => $person_updated_list,
            'missingList' => $data_transfer['person_missing_list'],
            'newList' => $person_inserted_list,
            'isInfo' => false,
            'gsUrl' => $auth_gs->getUrlFormatter(),
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

        $domstift_list = $institutionRepository->findByCorpusId('cap');

        $domstift_gsn_list = array();
        foreach($domstift_list as $domstift) {
            $domstift_gsn_list[] = $domstift->getIdGsn();
        }

        return $personsRepository->findCanonIds($domstift_gsn_list);

    }

    /**
     * find GSO entry for each element in $update_list and update it's data
     */
    private function updateList($doctrine, $meta_data_list) {
        $personRepository = $doctrine->getRepository(Person::class, 'default');
        $gsoPersonsRepository = $doctrine->getRepository(Persons::class, 'gso');
        $urlExternalRepository = $doctrine->getRepository(UrlExternal::class, 'default');

        $current_user_id = $this->getUser()->getId();

        $id_list = array_keys($meta_data_list);
        $person_update_list = $personRepository->findList($id_list);

        foreach($person_update_list as $person_target) {
            $item_id = $person_target->getItem()->getId();
            $gso_person_id = $meta_data_list[$item_id]['gso_person_id'];
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

        $current_user_id = $this->getUser()->getId();

        $person_insert_list = array();

        $n_insert = 0;
        foreach($gso_insert_list as $person_gso) {
            $item = new Item($current_user_id);
            $entityManager->persist($item);
            $person = new Person($item);
            $entityManager->persist($person);
            $entityManager->flush();
            // read object to obtain ID for roles etc. (ID is only available via Item);
            $person_id = $person->getItem()->getId();
            $person = $personRepository->findOneById($person_id);
            // set GSN in an extra step; Gso/Items->getIdPublic() returns the current GSN
            $this->editPersonService->setGsn($item, $person_gso->getItem()->getIdPublic());
            $this->editPersonService->updateFromGso($person, $person_gso, $current_user_id);
            // canons from GSO are always online
            $item->setIsOnline(1);

            $person_insert_list[] = $person;
            $n_insert += 1;
        }
        return $person_insert_list;

    }


    /**
     * get all person records that need an update or that are missing in GSO
     */
    private function collectPerson($doctrine) {
        $entityManager = $doctrine->getManager('default');
        $entityManager_gso = $doctrine->getManager('gso');

        $itemRepository = $entityManager->getRepository(Item::class, 'default');
        $personRepository = $entityManager->getRepository(Person::class, 'default');
        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class, 'default');
        $gsoPersonsRepository = $entityManager_gso->getRepository(Persons::class, 'gso');

        // consider only active entries (online);
        $dreg_item_list = $itemRepository->findGsnByCorpusId(['dreg-can', 'dreg']);

        // * in WIAG
        $dreg_gsn_list = array_column($dreg_item_list, 'gsn');

        // get GSO meta data for canons in WIAG
        $dreg_gso_meta_list = $this->personGsoIdsByList($doctrine, $dreg_gsn_list);

        // check update date; missing list holds elements of $item_list not found in GSO
        list($meta_update_list, $missing_list) = $this->updateRequired($dreg_item_list, $dreg_gso_meta_list, 'gsn');

        // get office data for updated persons and missing persons
        $id_list = array_keys($meta_update_list);
        $person_update_list = $personRepository->findList($id_list);
        // add visible id_public
        $urlExternalRepository->setIdPublicVisible($person_update_list);

        $id_missing_list = array_column($missing_list, 'id');
        $person_missing_list = $personRepository->findList($id_missing_list);
        // add visible id_public
        $urlExternalRepository->setIdPublicVisible($person_missing_list);

        // * not yet in WIAG
        // find all canons in GSO (by Domstift)
        $canon_cap_gso_all = $this->canonGsoIds($doctrine);
        $canon_cap_gso = UtilService::arrayDiffByField(
            $canon_cap_gso_all,
            $dreg_gso_meta_list,
            'person_id'
        );

        $cap_pid_list = array_column($canon_cap_gso, 'person_id');

        // * find all references of active entries (online)
        // without an corresponding item (dreg, dreg-can) in WIAG
        $niw_gsn_list = $urlExternalRepository->findNewGsn();

        $niw_gso_meta_list = $this->personGsoIdsByList($doctrine, $niw_gsn_list);

        // missing (online in WIAG, but not in Digitales Personenregister)
        $niw_missing_gsn_list = array_diff($niw_gsn_list, array_column($niw_gso_meta_list, 'gsn'));
        $niw_missing_id_list = $urlExternalRepository->findIdsByValueList($niw_missing_gsn_list, Authority::ID['GSN']);
        $person_niw_missing_list = $personRepository->findList(array_column($niw_missing_id_list, 'id'));

        // get new entries for import
        $niw_pid_list = array_column($niw_gso_meta_list, 'person_id');
        $new_pid_list = array_unique(array_merge($cap_pid_list, $niw_pid_list));

        $person_new_list = $gsoPersonsRepository->findList($new_pid_list);
        // $person_new_list is usually smaller than $new_pid_list,
        // because entries without offices are dropped.

        // check if there are new GSN in Digitales Personenregister
        $updated_gsn_list = array();
        foreach ($niw_gsn_list as $niw_gsn) {
            foreach ($person_new_list as $p_new) {
                $gso_item = $p_new->getItem();
                if ($gso_item->hasGsn($niw_gsn)
                    and $gso_item->getIdPublic() != $niw_gsn) {
                    $urlExternalRepository->updateValue($niw_gsn, $gso_item->getIdPublic());
                    $updated_gsn_list[$niw_gsn] = $gso_item->getIdPublic();
                }
            }
        }
        $entityManager->flush();

        $person_missing_all = array_merge($person_missing_list, $person_niw_missing_list);
        $data_transfer = [
            'count_ref' => count($dreg_item_list),
            'meta_update_list' => $meta_update_list,
            'person_update_list' => $person_update_list,
            'person_new_list' => $person_new_list,
            'person_missing_list' => $person_missing_all
        ];

        return $data_transfer;
    }


}
