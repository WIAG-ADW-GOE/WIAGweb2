<?php

namespace App\Repository;

use App\Entity\ReligiousOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ReligiousOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReligiousOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReligiousOrder[]    findAll()
 * @method ReligiousOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReligiousOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReligiousOrder::class);
    }

    // /**
    //  * Returns ReligiousOrder[] Returns an array of ReligiousOrder objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ReligiousOrder
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
