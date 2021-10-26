<?php

namespace App\Repository;

use App\Entity\PersonDisplayOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PersonDisplayOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method PersonDisplayOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method PersonDisplayOrder[]    findAll()
 * @method PersonDisplayOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonDisplayOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonDisplayOrder::class);
    }

    // /**
    //  * @return PersonDisplayOrder[] Returns an array of PersonDisplayOrder objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?PersonDisplayOrder
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
