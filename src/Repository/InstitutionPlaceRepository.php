<?php

namespace App\Repository;

use App\Entity\InstitutionPlace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method InstitutionPlace|null find($id, $lockMode = null, $lockVersion = null)
 * @method InstitutionPlace|null findOneBy(array $criteria, array $orderBy = null)
 * @method InstitutionPlace[]    findAll()
 * @method InstitutionPlace[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InstitutionPlaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstitutionPlace::class);
    }

    // /**
    //  * @return InstitutionPlace[] Returns an array of InstitutionPlace objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?InstitutionPlace
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
