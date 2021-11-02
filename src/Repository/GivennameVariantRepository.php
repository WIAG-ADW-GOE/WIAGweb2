<?php

namespace App\Repository;

use App\Entity\GivennameVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method GivennameVariant|null find($id, $lockMode = null, $lockVersion = null)
 * @method GivennameVariant|null findOneBy(array $criteria, array $orderBy = null)
 * @method GivennameVariant[]    findAll()
 * @method GivennameVariant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GivennameVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GivennameVariant::class);
    }

    // /**
    //  * @return GivennameVariant[] Returns an array of GivennameVariant objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('g.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?GivennameVariant
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
