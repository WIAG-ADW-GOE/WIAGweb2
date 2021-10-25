<?php

namespace App\Repository;

use App\Entity\PersonRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PersonRole|null find($id, $lockMode = null, $lockVersion = null)
 * @method PersonRole|null findOneBy(array $criteria, array $orderBy = null)
 * @method PersonRole[]    findAll()
 * @method PersonRole[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonRoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonRole::class);
    }

    // /**
    //  * @return PersonRole[] Returns an array of PersonRole objects
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
    public function findOneBySomeField($value): ?PersonRole
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
