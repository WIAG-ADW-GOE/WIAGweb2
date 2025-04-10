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
