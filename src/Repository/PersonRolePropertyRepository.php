<?php

namespace App\Repository;

use App\Entity\PersonRoleProperty;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PersonRoleProperty|null find($id, $lockMode = null, $lockVersion = null)
 * @method PersonRoleProperty|null findOneBy(array $criteria, array $orderBy = null)
 * @method PersonRoleProperty[]    findAll()
 * @method PersonRoleProperty[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonRolePropertyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonRoleProperty::class);
    }

    // /**
    //  * Returns PersonRoleProperty[] Returns an array of PersonRoleProperty objects
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
    public function findOneBySomeField($value): ?PersonRoleProperty
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
