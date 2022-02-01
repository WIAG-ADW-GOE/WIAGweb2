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

    public function findByDate($id, $date) {
        if (is_null($date)) {
            $qb = $this->createQueryBuilder('ip')
                       ->addSelect('ip.placeName')
                       ->andWhere('ip.institutionId = :id')
                       ->setParameter('id', $id);
        } else {
            $qb = $this->createQueryBuilder('ip')
                       ->addSelect('ip.placeName')
                       ->andWhere('ip.institutionId =:id')
                       ->andWhere('ip.numDateBegin < :date')
                       ->andWhere(':date < ip.numDateEnd')
                       ->setParameter('id', $id)
                       ->setParameter('date', $date)
                       ->addOrderBy('ip.numDateBegin', 'ASC');
        }

        $result = $qb->getQuery()
                     ->getResult();

        if ($result) {
            return $result[0]['placeName'];
        }

    }
}
