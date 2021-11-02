<?php

namespace App\Repository;

use App\Entity\NameLookup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NameLookup|null find($id, $lockMode = null, $lockVersion = null)
 * @method NameLookup|null findOneBy(array $criteria, array $orderBy = null)
 * @method NameLookup[]    findAll()
 * @method NameLookup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NameLookupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NameLookup::class);
    }

    // /**
    //  * @return NameLookup[] Returns an array of NameLookup objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('n.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?NameLookup
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

}
