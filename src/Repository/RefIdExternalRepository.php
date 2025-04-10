<?php

namespace App\Repository;

use App\Entity\RefIdExternal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method RefIdExternal|null find($id, $lockMode = null, $lockVersion = null)
 * @method RefIdExternal|null findOneBy(array $criteria, array $orderBy = null)
 * @method RefIdExternal[]    findAll()
 * @method RefIdExternal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RefIdExternalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefIdExternal::class);
    }

    // /**
    //  * Returns RefIdExternal[] Returns an array of RefIdExternal objects
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
    public function findOneBySomeField($value): ?RefIdExternal
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
