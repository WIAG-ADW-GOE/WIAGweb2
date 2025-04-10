<?php

namespace App\Repository;

use App\Entity\FamilynameVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FamilynameVariant|null find($id, $lockMode = null, $lockVersion = null)
 * @method FamilynameVariant|null findOneBy(array $criteria, array $orderBy = null)
 * @method FamilynameVariant[]    findAll()
 * @method FamilynameVariant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FamilynameVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FamilynameVariant::class);
    }

    // /**
    //  * Returns FamilynameVariant[] Returns an array of FamilynameVariant objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?FamilynameVariant
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
