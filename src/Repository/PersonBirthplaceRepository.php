<?php

namespace App\Repository;

use App\Entity\PersonBirthplace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PersonBirthplace|null find($id, $lockMode = null, $lockVersion = null)
 * @method PersonBirthplace|null findOneBy(array $criteria, array $orderBy = null)
 * @method PersonBirthplace[]    findAll()
 * @method PersonBirthplace[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonBirthplaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonBirthplace::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(PersonBirthplace $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(PersonBirthplace $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * @return PersonBirthplace[] Returns an array of PersonBirthplace objects
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
    public function findOneBySomeField($value): ?PersonBirthplace
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
