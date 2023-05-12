<?php

namespace App\Repository;

use App\Entity\UrlExternal;
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

    public function findIdBySomeNormUrl($someid, $item_type_id, $list_size_max = 200) {

        $qb = $this->createQueryBuilder('u')
                   ->select('DISTINCT u.itemId')
                   ->join('u.authority', 'auth')
                   ->join('u.item', 'i')
                   ->andWhere("auth.urlType = 'Normdaten'")
                   ->andWhere('u.value like :someid')
                   ->andWhere('i.itemTypeId = :item_type_id')
                   ->setParameter('item_type_id', $item_type_id)
                   ->setParameter(':someid', '%'.$someid.'%');

        $qb->setMaxResults($list_size_max);

        $query = $qb->getQuery();
        return array_column($query->getResult(), 'itemId');
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

}
