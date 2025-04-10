<?php

namespace App\Repository;

use App\Entity\Item;
use App\Entity\ItemCorpus;
use App\Entity\Authority;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\ReferenceVolume;
use App\Entity\UrlExternal;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Service\UtilService;

/**
 * @method Item|null find($id, $lockMode = null, $lockVersion = null)
 * @method Item|null findOneBy(array $criteria, array $orderBy = null)
 * @method Item[]    findAll()
 * @method Item[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemRepository extends ServiceEntityRepository
{
    // tolerance for the comparison of dates
    const MARGINYEAR = 1;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    // /**
    //  * Returns Item[] Returns an array of Item objects
    //  */
    /*
      public function findByExampleField($value)
      {
      return $this->createQueryBuilder('i')
      ->andWhere('i.exampleField = :val')
      ->setParameter('val', $value)
      ->orderBy('i.id', 'ASC')
      ->setMaxResults(10)
      ->getQuery()
      ->getResult()
      ;
      }
    */

    /*
      public function findOneBySomeField($value): ?Item
      {
      return $this->createQueryBuilder('i')
      ->andWhere('i.exampleField = :val')
      ->setParameter('val', $value)
      ->getQuery()
      ->getOneOrNullResult()
      ;
      }
    */

    /**
     * get list of references for a given item type
     * 2023-11-06 obsolete
     */
    // public function referenceByItemType($itemTypeId) {
    //     $qb = $this->createQueryBuilder('i')
    //                ->select('r')
    //                ->join('\App\Entity\ItemReference', 'ir', 'WITH', 'i.id = ir.itemId')
    //                ->join('\App\Entity\ReferenceVolume', 'r', 'WITH', 'ir.referenceId = r.id')
    //                ->andWhere('i.itemTypeId = :itemTypeId')
    //                ->setParameter(':itemTypeId', $itemTypeId)
    //                ->orderBy('r.displayOrder');

    //     $query = $qb->getQuery();

    //     $result = $query->getResult();

    //     return $result;
    // }

    /**
     * 2023-10-25; new version in UrlExternalRepository
     */
    // public function personDoubletIds($model, $limit = 0, $offset = 0, $online_only = true) {
    //     $urlExternalRepository = $this->getEntityManager()->getRepository(UrlExternal::class);

    //     // exclude merged and deleted
    //     // include 'n' in the query as a prerequisite for the HAVING-clause
    //     $qb = $urlExternalRepository->createQueryBuilder('u')
    //                                 ->select("u.authorityId, u.value, COUNT(u.value) as n")
    //                                 ->join("u.item", "i")
    //                                 ->join("i.itemCorpus", "ic")
    //                                 ->andWhere("i.mergeStatus <> 'parent'")
    //                                 ->andWhere("i.isDeleted <> '1'")
    //                                 ->andWhere("ic.corpusId in ('epc', 'can')")
    //                                 ->addGroupBy("u.authorityId")
    //                                 ->addGroupBy("u.value")
    //                                 ->andHaving("n > 1");


    //     $authority = $model['authority'];
    //     if ($authority != "") {
    //         $qb->andWhere("u.authorityId = :authority_id")
    //            ->setParameter('authority_id', $authority);
    //     }
    //     // '- alle -' returns null, which is filtered out by array_filter
    //     $edit_status = array_filter(array_values($model['editStatus']));
    //     if (!is_null($edit_status) and count($edit_status) > 0) {
    //         $qb->andWhere("i.editStatus in (:q_status)")
    //            ->setParameter('q_status', $edit_status);
    //     } else {
    //         $qb->andWhere("i.editStatus <> 'Dublette'");
    //     }


    //     $query = $qb->getQuery();
    //     $group_result = $query->getResult();

    //     $value_list = array_column($group_result, "value");

    //     $qb = $this->createQueryBuilder('i')
    //                ->select('DISTINCT(i.id) as personId')
    //                ->join('i.urlExternal', 'uext')
    //                ->join('i.itemCorpus', 'ic')
    //                ->addOrderBy("uext.value")
    //                ->andWhere("i.mergeStatus in ('child', 'original')")
    //                ->andWhere("uext.value in (:value_list)")
    //                ->andWhere("ic.corpusId in ('epc', 'can')")
    //                ->setParameter("value_list", $value_list);

    //     if (!is_null($edit_status) and count($edit_status) > 0) {
    //         $qb->andWhere("i.editStatus in (:q_status)")
    //            ->setParameter('q_status', $edit_status);
    //     } else {
    //         $qb->andWhere("i.editStatus <> 'Dublette'");
    //     }

    //     $query = $qb->getQuery();
    //     $result = $query->getResult();

    //     return array_column($result, 'personId');
    // }

    /**
     * 2023-11-06 obsolete
     */
    // public function setSibling($person) {
    //     // get person from Domherrendatenbank
    //     $f_found = false;
    //     $sibling = $this->findSibling($person);
    //     if ($sibling) {
    //         $person->setSibling($sibling);
    //         $f_found = true;
    //     }
    //     return $f_found;
    // }

    /**
     * 2023-11-06 obsolete
     */
    // public function findSibling($person) {
    //     $authorityWIAG = Authority::ID['WIAG-ID'];
    //     $wiagid = $person->getItem()->getIdPublic();
    //     $sibling = null;
    //     if (!is_null($wiagid) && $wiagid != "") {
    //         $itemTypeCanon = Item::ITEM_TYPE_ID['Domherr']['id'];
    //         $item = $this->findByUrlExternal($itemTypeCanon, $wiagid, $authorityWIAG);
    //         if ($item) {
    //             $personRepository = $this->getEntityManager()->getRepository(Person::class);
    //             $sibling_list = $personRepository->findList([$item[0]->getId()]);
    //             $sibling = $sibling_list[0];
    //         }
    //     }
    //     return $sibling;
    // }


    /**
     * 2023-11-06 obsolete
     */
    // public function findByUrlExternal($itemTypeId, $value, $authId, $isonline = true) {
    //     if (!is_array($itemTypeId)) {
    //         $itemTypeId = [$itemTypeId];
    //     }

    //     $qb = $this->createQueryBuilder('i')
    //                ->addSelect('i')
    //                ->join('i.urlExternal', 'ext')
    //                ->andWhere('i.itemTypeId in (:itemTypeId)')
    //                ->andWhere('ext.value = :value')
    //                ->andWhere('ext.authorityId = :authId')
    //                ->setParameter(':itemTypeId', $itemTypeId)
    //                ->setParameter(':value', $value)
    //                ->setParameter(':authId', $authId);

    //     if ($isonline) {
    //         $qb->andWhere('i.isOnline = 1');
    //         // $online_status = Item::ITEM_TYPE[$itemTypeId]['online_status'];
    //         // $qb->andWhere('i.editStatus = :online_status')
    //         //    ->setParameter('online_status', $online_status);
    //     }

    //     $query = $qb->getQuery();
    //     $item = $query->getResult();

    //     return $item;
    // }

    /**
     * 2023-10-05 obsolete
     */
    // public function findMaxIdInSource($itemTypeId) {
    //     $qb = $this->createQueryBuilder('i')
    //                ->select("i.idInSource")
    //                ->andWhere('i.itemTypeId = :itemTypeId')
    //                ->setParameter('itemTypeId', $itemTypeId);
    //     $query = $qb->getQuery();
    //     $result = $query->getResult();

    //     $max_id = 0;
    //     foreach ($result as $el) {
    //         $cand = intval($el['idInSource']);
    //         if ($cand > $max_id) {
    //             $max_id = $cand;
    //         }
    //     }
    //     return $max_id;

    // }

    /**
     * 2023-10-10 obsolete
     * findMergeCandidate($id_in_source, $item_type_id)
     * status 'merged' is excluded
     */
    // public function findMergeCandidate($id_in_source, $item_type_id) {
    //     $qb = $this->createQueryBuilder('i')
    //                ->select("i")
    //                ->andWhere('i.itemTypeId = :item_type_id')
    //                ->andWhere('i.idInSource = :id_in_source')
    //                ->andWhere("i.mergeStatus <> 'parent'")
    //                ->setParameter('item_type_id', $item_type_id)
    //                ->setParameter('id_in_source', $id_in_source);

    //     $query = $qb->getQuery();
    //     return $query->getOneOrNullResult();
    // }

    /**
     * Returns entry matching $id_public or having $id_public as a parent (merging); is_online = true
     */
    public function findByIdPublicOrParent($id_public) {
        $itemCorpusRepository = $this->getEntityManager()->getRepository(ItemCorpus::class);

        $item_corpus_list = $itemCorpusRepository->findByIdPublic($id_public);

        if (count($item_corpus_list) == 0) {
            return null;
        }

        $id = $item_corpus_list[0]->getItemId();
        $corpus_id = $item_corpus_list[0]->getCorpusId();

        if ($corpus_id != 'epc' && $corpus_id != 'can' && $corpus_id != 'dreg') {
            return (array($this->find($id)));
        }

        // else: check if id_public points to a current item or to an ancestor

        $with_id_in_corpus = false;
        $list_size_max = 200;
        $descendant_id_list = $this->findIdByAncestor(
            $id_public,
            $with_id_in_corpus,
            $list_size_max,
        );

        $qb = $this->createQueryBuilder('i')
                   ->andWhere("i.id in (:q_id_list)")
                   ->andWhere("i.isOnline = 1")
                   ->setParameter('q_id_list', $descendant_id_list);

        $query = $qb->getQuery();

        return $query->getResult();
    }

    /**
     *
     */
    public function findParents($item) {
        $qb = $this->createQueryBuilder('i')
                   ->andWhere('i.mergedIntoId = :child_id')
                   ->setParameter('child_id', $item->getId());
        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     *
     */
    public function setAncestor($item_list) {
        foreach($item_list as $i_loop) {
            $i_loop->setAncestor($this->findAncestor($i_loop));
        }
        return null;
    }

    /**
     * Returns list of items that were merged into $item in one or several steps
     */
    public function findAncestor(Item $item) {
        $ancestor_list = array();
        $id_list = array($item->getId());
        $result_count = 1;
        while ($result_count > 0) {
            $qb = $this->createQueryBuilder('i')
                       ->andWhere('i.mergedIntoId in (:id_list)')
                       ->setParameter('id_list', $id_list);
            $query = $qb->getQuery();
            $q_result = $query->getResult();
            $id_list = array();
            foreach ($q_result as $i_loop) {
                $id_list[] = $i_loop->getId();
                $ancestor_list[] = $i_loop;
            }
            $result_count = count($q_result);
        }
        return $ancestor_list;
    }

    /**
     * call this function only for persons
     * Returns items containing $q_id as id_public in ancestor list
     */
    public function findIdByAncestor(string $q_id, $with_id_in_corpus, $list_size_max) {

        if ($with_id_in_corpus) {
            $qb = $this->createQueryBuilder('i')
                       ->join('i.itemCorpus', 'ic')
                       ->andWhere("ic.idPublic like :q_id OR ic.idInCorpus like :q_id")
                       ->setParameter('q_id', '%'.$q_id.'%');
        } else {
            $qb = $this->createQueryBuilder('i')
                       ->join('i.itemCorpus', 'ic')
                       ->andWhere("ic.idPublic like :q_id")
                       ->setParameter('q_id', '%'.$q_id.'%');
        }

        $qb->setMaxResults($list_size_max);
        $query = $qb->getQuery();

        $q_result = $query->getResult();

        // find id of the current child
        $child_id_list = array();
        foreach($q_result as $i_loop) {
            $child = $this->findCurrentChild($i_loop);
            if ($child) {
                $child_id_list[] = $child->getId();
            }
        }
        return array_unique($child_id_list);
    }

    /**
     * 2023-06-30 obsolete; see findIdByAncestor
     * Returns items containing $id in ancestor list
     */
    public function findCurrentChildById(string $q_id, $with_id_in_source, $list_size_max) {

        if ($with_id_in_source) {
            $qb = $this->createQueryBuilder('i')
                       ->join('i.itemCorpus', 'ic')
                       ->andWhere("ic.idPublic like :q_id OR ic.idInSource like :q_id")
                       ->setParameter('q_id', '%'.$q_id.'%');
        } else {
            $qb = $this->createQueryBuilder('i')
                       ->join('i.itemCorpus', 'ic')
                       ->andWhere("ic.idPublic like :q_id")
                       ->setParameter('q_id', '%'.$q_id.'%');
        }

        $qb->setMaxResults($list_size_max);
        $query = $qb->getQuery();

        $q_result = $query->getResult();

        $child_list = array();
        foreach($q_result as $i_loop) {
            $child = $this->findCurrentChild($i_loop);
            if ($child) {
                $child_list[] = $child;
            }
        }
        return $child_list;
    }


    /**
     * Returns first item that is online or has no descendants
     */
    public function findCurrentChild(Item $item) {
        $child = $item;
        $merged_into_id = $item->getMergedIntoId();
        $is_child = $item->getMergeStatus() == 'child';
        while(!is_null($merged_into_id) and $merged_into_id > 0 and !$is_child) {
            $child = $this->find($merged_into_id);
            if ($child) { // avoid error for inconsistent data
                $is_child = $child->getMergeStatus() == $child;
                $merged_into_id = $child->getMergedIntoId();
            } else {
                $is_online = false;
                $merged_into_id = null;
            }
        }
        return $child;
    }

    /**
     * 2023-11-04 obsolete
     * Returns maximum value for id_in_source, if it is numerical
     */
    // public function maxIdInSource($item_type_id) {
    //     // Doctrine does not know the function CAST nor CONVERT
    //     $qb = $this->createQueryBuilder('i')
    //                ->select('i.idInSource')
    //                ->andWhere('i.itemTypeId = :item_type_id')
    //                ->setParameter('item_type_id', $item_type_id);

    //     $query = $qb->getQuery();
    //     $q_result = $query->getResult();

    //     $max = 0;
    //     foreach($q_result as $val) {
    //         $val = intval($val['idInSource']);
    //         if($max < $val) {
    //             $max = $val;
    //         }
    //     }
    //     return $max;
    // }

    /**
     * Returns meta data and GSN for items with corpus_id in $corpus_id_list
     */
    public function findGsnByCorpusId($corpus_id_list) {
        $authority_id = Authority::ID['GSN'];

        $qb = $this->createQueryBuilder('i')
                   ->select('i.id, i.dateChanged, uext.value as gsn')
                   ->join('i.urlExternal', 'uext')
                   ->join('i.itemCorpus', 'ic')
                   ->andWhere('i.isOnline = 1')
                   ->andWhere('uext.authorityId = :authority_id')
                   ->andWhere('ic.corpusId in (:corpus_id_list)')
                   ->setParameter('authority_id', $authority_id)
                   ->setParameter('corpus_id_list', $corpus_id_list)
                   ->groupBy('i.id');

        $query = $qb->getQuery();
        return $query->getResult();

    }

    public function findMaxId() {
        $qb = $this->createQueryBuilder('i')
                   ->select('MAX(i.id)');

        $query = $qb->getQuery();
        $result = $query->getOneOrNullResult();
        return array_values($result)[0];
    }


    /**
     * 2023-10-18 obsolete
     */
    // public function findMaxNumIdPublic($item_type_id) {
    //     $item_type_id_params = Item::ITEM_TYPE[$item_type_id];
    //     $field_start = $item_type_id_params["numeric_field_start"];
    //     $field_width = $item_type_id_params["numeric_field_width"];

    //     // parameter binding for SUBSTRING does not work?!
    //     $qb = $this->createQueryBuilder('i')
    //                ->select('MAX(SUBSTRING(i.idPublic,'.$field_start.','.$field_width.')) as max_id')
    //                ->andWhere('i.itemTypeId = :item_type_id')
    //                ->setParameter('item_type_id', $item_type_id);

    //     $query = $qb->getQuery();
    //     $result = $query->getOneOrNullResult();

    //     if (!is_null($query)) {
    //         return intval($result['max_id']);
    //     } else {
    //         return null;
    //     }

    // }

    /**
     * Returns list of items with associated persons via item_name_role and all data
     */
    public function findItemNameRole($id_list) {
        $entityManager = $this->getEntityManager();
        $qb = $this->createQueryBuilder('i')
                   ->select('i')
                   ->join('i.person', 'p')
                   ->join('i.itemNameRole', 'inr')
                   ->join('inr.personRolePerson', 'p_role')
                   ->andWhere('i.id in (:id_list)')
                   ->setParameter('id_list', $id_list);

        $query = $qb->getQuery();

        $item_list = $query->getResult();

        $item_role_list = [];
        foreach ($item_list as $item) {
            foreach ($item->getItemNameRole() as $inr) {
                $item_role_list[] = $inr->getPersonRolePerson()->getItem();
            }
        }

        // set reference volumes
        $entityManager->getRepository(ReferenceVolume::class)->setReferenceVolume($item_role_list);

        // set authorities
        $entityManager->getRepository(Authority::class)->setAuthority($item_role_list);

        // restore order as in $id_list
        $item_list = UtilService::reorder($item_list, $id_list, "id");

        return $item_list;
    }

}
