<?php

namespace App\Repository;

use App\Entity\UrlExternal;
use App\Entity\Item;
use App\Entity\Authority;
use App\Service\UtilService;


use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method UrlExternal|null find($id, $lockMode = null, $lockVersion = null)
 * @method UrlExternal|null findOneBy(array $criteria, array $orderBy = null)
 * @method UrlExternal[]    findAll()
 * @method UrlExternal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UrlExternalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UrlExternal::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(UrlExternal $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(UrlExternal $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * @return UrlExternal[] Returns an array of UrlExternal objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?UrlExternal
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function findIdBySomeNormUrl($someid, $corpus_id_list, $list_size_max = 200) {

        $qb = $this->createQueryBuilder('u')
                   ->select('DISTINCT u.itemId')
                   ->join('u.authority', 'auth')
                   ->join('u.item', 'i')
                   ->join('\App\Entity\ItemCorpus', 'ic', 'WITH', 'ic.itemId = u.itemId')
                   ->andWhere("auth.urlType in ('Normdaten', 'Interner Identifier')")
                   ->andWhere('ic.corpusId in (:cil)')
                   ->andWhere('u.value like :someid')
                   ->setParameter('cil', $corpus_id_list)
                   ->setParameter(':someid', '%'.$someid.'%');

        $qb->setMaxResults($list_size_max);

        $query = $qb->getQuery();
        return array_column($query->getResult(), 'itemId');
    }

    /**
     *
     */
    public function findValue($item_id, $auth_id) {
        $qb = $this->createQueryBuilder('u')
                   ->select('DISTINCT u.value')
                   ->andWhere('u.itemId = :item_id')
                   ->andWhere('u.authorityId = :auth_id')
                   ->setParameter(':item_id', $item_id)
                   ->setParameter(':auth_id', $auth_id);

        $query = $qb->getQuery();
        return $query->getOneOrNullResult();

    }


    /**
     *
     */
    public function findItemId($someid, $auth_id = null, $online_flag = false) {

        $qb = $this->createQueryBuilder('u')
                   ->select('DISTINCT u.itemId')
                   ->andWhere('u.value = :someid')
                   ->setParameter(':someid', $someid);

        if (!is_null($auth_id)) {
            $qb->andWhere('u.authorityId = :auth_id')
               ->setParameter(':auth_id', $auth_id);
        }

        if ($online_flag) {
            $qb->join('u.item', 'i')
               ->andWhere('i.isOnline = 1');
        }


        $q_result = $qb->getQuery()->getResult();
        return array_column($q_result, 'itemId');
    }


    /**
     *
     */
    public function groupByType($item_id) {

        $qb = $this->createQueryBuilder('u')
                   ->select('auth.urlType, u')
                   ->innerJoin('u.authority', 'auth')
                   ->andWhere('u.itemId = :itemId')
                   ->addOrderBy('auth.displayOrder')
                   ->addOrderBy('u.note', 'DESC')
                   ->setParameter('itemId', $item_id);

        $query = $qb->getQuery();
        $query_result = $query->getResult();

        if (!$query_result) {
            return $query_result;
        }

        $url_by_type = array();
        $current_type = null;
        foreach ($query_result as $qr) {
            $loop_type = $qr["urlType"];
            if ($loop_type != $current_type) {
                $current_type = $loop_type;
                $url_by_type[$current_type] = array();
            }
            // add object of type UrlExternal
            $url_by_type[$current_type][] = $qr[0];
        }
        return $url_by_type;
    }

    /**
     * 2023-10-10 obsolete
     */
    // public function findByValueAndItemType($value, $item_type_id) {
    //     $qb = $this->createQueryBuilder('u')
    //                ->innerJoin('u.item', 'i')
    //                ->andWhere('i.itemTypeId = :item_type_id')
    //                ->andWhere('u.value = :value')
    //                ->setParameter('item_type_id', $item_type_id)
    //                ->setParameter('value', $value);
    //     $query = $qb->getQuery();
    //     return $query->getResult();
    // }

    /**
     * BEACON export
     */
    public function findValues($authority_id) {
        $qb = $this->createQueryBuilder('id')
                   ->select('DISTINCT id.value')
                   ->join('id.item', 'item')
                   ->andWhere('id.value is not null')
                   ->andWhere('item.isOnline = 1')
                   ->andWhere('id.authorityId = :authority_id')
                   ->setParameter('authority_id', $authority_id);

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * @return number of items with an URL external for $authority_id
     */
    public function referenceCount($authority_id) {
        $qb = $this->createQueryBuilder('uext')
                   ->select('COUNT(DISTINCT(uext.id)) as count')
                   ->join('uext.item', 'item')
                   ->andWhere('uext.authorityId = :authority_id')
                   ->andWhere('item.isOnline = 1')
                   ->setParameter('authority_id', $authority_id);
        $query = $qb->getQuery();

        return $query->getSingleResult()['count'];
    }

    /**
     * change value, do not flush
     */
    public function updateValue($old, $new) {
        $uext_list = $this->findByValue($old);
        foreach ($uext_list as $uext) {
            $uext->setValue($new);
        }

        return count($uext_list);
    }

    /**
     * set idPublicVisible for external URLs in $person_list via GSN
     * 2023-10-10 used for update from Digitales Personenregister
     */
    public function setIdPublicVisible($person_list) {
        $gsn_list = array();
        foreach ($person_list as $person) {
            $gsn_list[$person->getId()] = $person->getItem()->getGsn();
        }

        $corpus_id_list = ['epc', 'can'];

        $qb = $this->createQueryBuilder('uext')
                   ->select('uext.itemId as item_id, '.
                            'ic_vis.idPublic as id_public_vis, '.
                            'ic_vis.corpusId as corpus_id')
                   ->join('\App\Entity\UrlExternal', 'uext_vis', 'WITH', 'uext_vis.value = uext.value')
                   ->join('\App\Entity\ItemCorpus', 'ic_vis', 'WITH', 'ic_vis.itemId = uext_vis.itemId')
                   ->join('\App\Entity\Item', 'i_vis', 'WITH', 'i_vis.id = uext_vis.itemId')
                   ->andWhere('i_vis.isOnline = 1')
                   ->andWhere('ic_vis.corpusId in (:corpus_id_list)')
                   ->andWhere('uext.value in (:gsn_list)')
                   ->andWhere('uext.itemId in (:id_list)')
                   ->setParameter('corpus_id_list', $corpus_id_list)
                   ->setParameter('gsn_list', $gsn_list)
                   ->setParameter('id_list', array_keys($gsn_list));


        $query = $qb->getQuery();
        $result = $query->getResult();

        // match id_public by id
        // the result list is not large so the filter is no performance problem
        foreach ($person_list as $person) {
            $item_id = $person->getId();
            // look for canon IDs first, bishop wins if present
            $match_flag = false;
            foreach ($corpus_id_list as $corpus_id) {
                $cand_list = array_filter($result, function($v) use ($item_id, $corpus_id) {
                    return ($v['item_id'] == $item_id and $v['corpus_id'] == $corpus_id);
                });
                foreach($cand_list as $cand) {
                    $person->getItem()->setIdPublicVisible($cand['id_public_vis']);
                    $match_flag = true;
                }
            }
            // default: own id_public
            if (!$match_flag) {
                $person->getItem()->setIdPublicVisible($person->getItem()->getIdPublic());
            }
        }

        return count($person_list);

    }

    /**
     * find GSN without item (dreg), for items that are online; (GSO update)
     */
    public function findNewGsn() {
        // get GSN for corpora 'dreg-can', 'dreg'
        $qb_item = $this->createQueryBuilder('uext')
                        ->select('uext.value as gsn')
                        ->join('\App\Entity\ItemCorpus', 'ic', 'WITH', "ic.itemId = uext.itemId AND ic.corpusId IN ('dreg', 'dreg-can')")
                        ->andWhere('uext.authorityId = :dreg')
                        ->setParameter('dreg', Authority::ID['GSN']);

        $query_item = $qb_item->getQuery();
        $uext_item_list = $query_item->getResult();

        $gsn_item_list = array_column($uext_item_list, 'gsn');

        // get all GSN references of active items
        $qb_ref = $this->createQueryBuilder('uext')
                       ->select('uext.value as gsn')
                       ->join('\App\Entity\ItemCorpus', 'ic', 'WITH', "ic.itemId = uext.itemId AND ic.corpusId NOT IN ('dreg', 'dreg-can')")
                       ->join('\App\Entity\Item', 'i', 'WITH', "i.id = uext.itemId AND i.isOnline = 1")
                        ->andWhere('uext.authorityId = :dreg')
                        ->setParameter('dreg', Authority::ID['GSN']);

        $query_ref = $qb_ref->getQuery();
        $uext_ref_list = $query_ref->getResult();

        $gsn_ref_list = array_column($uext_ref_list, 'gsn');

        return array_diff($gsn_ref_list, $gsn_item_list);

    }

    public function findAllGsn($value_list = null) {
        // get all GSN references of active items
        $qb_ref = $this->createQueryBuilder('uext')
                       ->select('uext')
                       ->join('\App\Entity\ItemCorpus', 'ic', 'WITH', "ic.itemId = uext.itemId")
                       ->join('\App\Entity\Item', 'i', 'WITH', "i.id = uext.itemId and i.mergeStatus in ('child', 'original') and i.isDeleted = 0")
                       ->andWhere('uext.authorityId = :dreg')
                       ->setParameter('dreg', Authority::ID['GSN']);

        if (!is_null($value_list)) {
            $qb_ref->andWhere('uext.value in (:value_list)')
                   ->setParameter('value_list', $value_list);
        }

        $query = $qb_ref->getQuery();
        return $query->getResult();
    }

    /**
     * find all referencing IDs (active merge_status only)
     */
    public function findIdsByIdList($id_list, $authority_id) {
        $qb = $this->createQueryBuilder('uext')
                   ->select('uext_ref.itemId as id')
                   ->join('\App\Entity\UrlExternal', 'uext_ref',
                          'WITH', 'uext.value = uext_ref.value')
                   ->join('\App\Entity\Item', 'item',
                          'WITH', "item.id = uext_ref.itemId AND item.mergeStatus in ('child', 'original')")
                   ->andWhere('uext.authorityId = :authority_id')
                   ->andWhere('uext.itemId in (:id_list)')
                   ->setParameter('authority_id', $authority_id)
                   ->setParameter('id_list', $id_list);
        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     * find all referencing IDs (active merge_status only)
     */
    public function findIdsByValueList($value_list, $authority_id) {
        $qb = $this->createQueryBuilder('uext')
                   ->select('uext.itemId as id')
                   ->andWhere('uext.authorityId = :authority_id')
                   ->andWhere('uext.value in (:value_list)')
                   ->setParameter('authority_id', $authority_id)
                   ->setParameter('value_list', $value_list);
        $query = $qb->getQuery();
        return $query->getResult();
    }


    /**
     * 2023-10-10 obsolete
     * @return list of pair of IDs for references to Digitales Personenregister
     */
    // public function findDregIds($id_list) {
    //     $auth_dreg_id = Authority::ID['Dreg'];
    //     $qb = $this->createQueryBuilder('uext')
    //                ->select('uext.itemId AS id, i_dreg.id AS id_dreg')
    //                ->join('\App\Entity\UrlExternal', 'uext_dreg', 'WITH', 'uext_dreg.value = uext.value and uext_dreg.authorityId = :auth_dreg_id')
    //                ->join('\App\Entity\Item', 'i_dreg', 'WITH', 'uext_dreg.itemId = i_dreg.id')
    //                ->join('\App\Entity\ItemCorpus', 'ic_dreg', 'WITH', "ic_dreg.itemId = i_dreg.id AND ic_dreg.corpusId in ('dreg-can', 'dreg')")
    //                ->andWhere('uext.itemId in (:id_list)')
    //                ->setParameter('auth_dreg_id', $auth_dreg_id)
    //                ->setParameter('id_list', $id_list);

    //     $query = $qb->getQuery();

    //     return $query->getResult();
    // }

    public function personDoubletIds($model) {
        // collect IDs with an entry in url_external
        $qb_id = $this->createQueryBuilder('u')
                   ->select('distinct(ic.itemId) as id')
                   ->join('\App\Entity\ItemCorpus', 'ic',
                          'WITH', "ic.itemId = u.itemId AND ic.corpusId in ('can', 'epc')")
                   ->join('\App\Entity\Item', 'i',
                          'WITH', "i.id = u.itemId AND i.mergeStatus in ('child', 'original')")
                   ->andWhere('u.authorityId = :auth_id')
                   ->setParameter('auth_id', $model['authority']);

        if (!in_array('- alle -', $model['editStatus'])) {
            $qb_id->andWhere('i.editStatus in (:status)')
                  ->setParameter('status', $model['editStatus']);
        }

        $query_id = $qb_id->getQuery();
        $id_list = array_column($query_id->getResult(), 'id');

        // find multiple entries
        $qb_m = $this->createQueryBuilder('u')
                     ->select('u.value, count(u.value) as n')
                     ->andWhere('u.itemId in (:id_list)')
                     ->andWhere('u.authorityId = :auth_id')
                     ->groupBy('u.value')
                     ->having('n > 1')
                     ->setParameter('auth_id', $model['authority'])
                     ->setParameter('id_list', $id_list);


        $query_m = $qb_m->getQuery();
        $m_list = $query_m->getResult();
        $value_list = array_column($m_list, 'value');

        // map back to IDs
        $qb = $this->createQueryBuilder('u')
                   ->select('distinct(ic.itemId) as id')
                   ->join('\App\Entity\ItemCorpus', 'ic',
                          'WITH', "ic.itemId = u.itemId AND ic.corpusId in ('can', 'epc')")
                   ->join('\App\Entity\Item', 'i',
                          'WITH', "i.id = u.itemId AND i.mergeStatus in ('child', 'original')")
                   ->andWhere('u.value in (:value_list)')
                   ->setParameter('value_list', $value_list);

        if (!in_array('- alle -', $model['editStatus'])) {
            $qb->andWhere('i.editStatus in (:status)')
                  ->setParameter('status', $model['editStatus']);
        }

        $query= $qb->getQuery();
        $d_list = $query->getResult();

        return array_column($d_list, 'id');

    }


}
