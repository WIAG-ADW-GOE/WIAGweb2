<?php

namespace App\Repository;

use App\Entity\UrlExternal;
use App\Entity\Item;
use App\Entity\Authority;
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

    public function findIdBySomeNormUrl($someid, $list_size_max = 200) {

        $qb = $this->createQueryBuilder('u')
                   ->select('DISTINCT u.itemId')
                   ->join('u.authority', 'auth')
                   ->join('u.item', 'i')
                   ->andWhere("auth.urlType in ('Normdaten', 'Interner Identifier')")
                   ->andWhere('u.value like :someid')
                   ->andWhere('i.itemTypeId in (:item_type_list)')
                   ->setParameter('item_type_list', Item::ITEM_TYPE_WIAG_PERSON_LIST)
                   ->setParameter(':someid', '%'.$someid.'%');

        $qb->setMaxResults($list_size_max);

        $query = $qb->getQuery();
        return array_column($query->getResult(), 'itemId');
    }

    public function findItemId($someid, $auth_id = null, $online = null) {

        $qb = $this->createQueryBuilder('u')
                   ->select('DISTINCT u.itemId')
                   ->andWhere('u.value like :someid')
                   ->setParameter(':someid', '%'.$someid.'%');

        if (!is_null($auth_id)) {
            $qb->andWhere('u.authorityId = :auth_id')
               ->setParameter(':auth_id', $auth_id);
        }

        if (!is_null($online)) {
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
     * change GSN, do not flush
     */
    public function updateGsn($old, $new) {
        $uext_list = $this->findByValue($old);
        foreach ($uext_list as $uext) {
            $uext->setValue($new);
        }

        return count($uext_list);
    }

    /**
     * set idPublicVisible for external URLs in $person_list via GSN
     * 2023-10-10 update (used for update from Digitales Personenregister)
     */
    public function setIdPublicVisible($person_list) {
        $gsn_list = array();
        foreach ($person_list as $person) {
            $gsn_list[$person->getId()] = $person->getItem()->getGsn();
        }

        $type_id_list = [
            Item::ITEM_TYPE_ID['Domherr']['id'],
            Item::ITEM_TYPE_ID['Bischof']['id'],
        ];

        $qb = $this->createQueryBuilder('uext')
                   ->select('uext.itemId as item_id, '.
                            'i_vis.idPublic as id_public_vis, '.
                            'i_vis.itemTypeId as item_type_id')
                   ->join('\App\Entity\UrlExternal', 'uext_vis', 'WITH', 'uext_vis.value = uext.value')
                   ->join('uext_vis.item', 'i_vis')
                   ->andWhere('i_vis.itemTypeId in (:type_id_list)')
                   ->andWhere('uext.value in (:gsn_list)')
                   ->andWhere('uext.itemId in (:id_list)')
                   ->setParameter('type_id_list', $type_id_list)
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
            foreach ($type_id_list as $type_id) {
                $cand_list = array_filter($result, function($v) use ($item_id, $type_id) {
                    return ($v['item_id'] == $item_id and $v['item_type_id'] == $type_id);
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
     * 2023-10-10 obsolete
     * @return list of pair of IDs for references to Digitales Personenregister
     */
    // public function findDregIds($id_list) {
    //     $auth_dreg_id = Authority::ID['Dreg'];
    //     $qb = $this->createQueryBuilder('uext')
    //                ->select('uext.itemId AS id, i_dreg.id AS id_dreg')
    //                ->join('\App\Entity\UrlExternal', 'uext_dreg', 'WITH', 'uext_dreg.value = uext.value and uext_dreg.authorityId = :auth_dreg_id')
    //                ->join('\App\Entity\Item', 'i_dreg', 'WITH', 'uext_dreg.itemId = i_dreg.id')
    //                ->join('\App\Entity\ItemCorpus', 'ic_dreg', 'WITH', "ic_dreg.itemId = i_dreg.id AND ic_dreg.corpusId in ('dreg-can', 'dreg-epc')")
    //                ->andWhere('uext.itemId in (:id_list)')
    //                ->setParameter('auth_dreg_id', $auth_dreg_id)
    //                ->setParameter('id_list', $id_list);

    //     $query = $qb->getQuery();

    //     return $query->getResult();
    // }



}
