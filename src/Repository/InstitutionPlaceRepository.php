<?php

namespace App\Repository;

use App\Entity\InstitutionPlace;
use App\Entity\PersonRole;
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

    /**
     * 2022-03-11 obsolete?!
     */
    public function findByDate($id, $date) {
        if (is_null($date)) {
            $qb = $this->createQueryBuilder('ip')
                       ->select('ip.placeName')
                       ->andWhere('ip.institutionId = :id')
                       ->setParameter('id', $id);
        } else {
            $qb = $this->createQueryBuilder('ip')
                       ->select('ip.placeName')
                       ->andWhere('ip.institutionId =:id')
                       ->andWhere('ip.numDateBegin < :date')
                       ->andWhere(':date < ip.numDateEnd')
                       ->setParameter('id', $id)
                       ->setParameter('date', $date)
                       ->addOrderBy('ip.numDateBegin', 'ASC');
        }

        $query = $qb->getQuery();

        return $query->getResult();
    }

    /**
     * find all places matching the parameters in `role`;
     */
    public function findPlaceForRole(PersonRole $role) {
        $institution_id = $role->getInstitutionId();
        if (is_null($institution_id)) {
            return null;
        }
        $roleBegin = $role->getNumDateBegin();
        $roleEnd = $role->getNumDateEnd();
        if (is_null($roleBegin) && is_null($roleEnd)) {
            $roleBegin = 0;
            $roleEnd = 4000;
        } elseif (is_null($roleBegin)) {
            $roleBegin = $roleEnd - 40;
        } elseif (is_null($roleEnd)) {
            $roleEndn = $roleBegin + 40;
        }

        $qb = $this->createQueryBuilder('ip')
                   ->select('ip.placeName')
                   ->andWhere('ip.institutionId = :institution_id')
                   ->andWhere(':roleBegin < ip.numDateBegin AND ip.numDateBegin < :roleEnd '.
                              'OR :roleBegin < ip.numDateEnd AND ip.numDateEnd < :roleEnd ')
                   ->setParameter('institution_id', $role->getInstitutionId())
                   ->setParameter('roleBegin', $roleBegin)
                   ->setParameter('roleEnd', $roleEnd);

        $query = $qb->getQuery();
        $result = $query->getResult();
        if (!empty($result)) {
            $place = array_column($result, 'placeName');
            return implode(", ", $place);
        } else {
            return null;
        }
    }
}
