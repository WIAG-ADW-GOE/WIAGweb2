<?php

namespace App\Repository\Gso;

use App\Entity\Gso\Items;
use App\Entity\Item;

// use Doctrine\Bundle\DoctrineBundle\Repository\EntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends EntityRepository<Items>
 *
 */
class ItemsRepository extends EntityRepository
{

    public function add(Items $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Items $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * Returns Items[] Returns an array of Items objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('g.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Items
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Returns items.id and other meta data for entries in $gsn_list, online
     */
    public function findIdsByGsnList($gsn_list) {
        $qb = $this->createQueryBuilder('i')
                   ->select('i.id, i.modified as dateChanged, p.id as person_id, g.nummer as gsn')
                   ->join('i.gsn', 'g')
                   ->join('App\Entity\Gso\Persons', 'p', 'WITH', 'p.itemId = i.id')
                   ->andWhere('i.deleted = 0')
                   ->andWhere("i.status = 'online'")
                   ->andWhere("g.deleted = 0")
                   ->andWhere('g.nummer in (:gsn_list)')
                   ->setParameter('gsn_list', $gsn_list);

        $query = $qb->getQuery();
        return $query->getResult();
    }

}
