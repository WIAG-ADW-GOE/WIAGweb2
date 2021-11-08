<?php

namespace App\Repository;

use App\Entity\SkosLabel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SkosLabel|null find($id, $lockMode = null, $lockVersion = null)
 * @method SkosLabel|null findOneBy(array $criteria, array $orderBy = null)
 * @method SkosLabel[]    findAll()
 * @method SkosLabel[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SkosLabelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SkosLabel::class);
    }

    // /**
    //  * @return SkosLabel[] Returns an array of SkosLabel objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?SkosLabel
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
