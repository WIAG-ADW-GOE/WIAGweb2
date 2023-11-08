<?php

namespace App\Repository;

use App\Entity\PersonRole;
use App\Entity\Role;
use App\Entity\Diocese;
use App\Entity\Institution;
use App\Entity\InstitutionPlace;
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


    /**
     * find roles for `$personId`; set place names
     * 2022-07-20 obsolete?
     * @return PersonRole[]
     */
    public function findRoleWithPlace($personId) {
        $qb = $this->createQueryBuilder('r')
                   ->addSelect('r, ip.placeName')
                   ->leftjoin('App\Entity\InstitutionPlace', 'ip', 'WITH',
                              'r.institutionId = ip.institutionId '.
                              'AND ( '.
                              'r.numDateBegin IS NULL AND r.numDateEnd IS NULL '.
                              'OR (ip.numDateBegin < r.numDateBegin AND r.numDateBegin < ip.numDateEnd) '.
                              'OR (ip.numDateBegin < r.numDateEnd AND r.numDateEnd < ip.numDateEnd) '.
                              'OR (r.numDateBegin < ip.numDateBegin AND ip.numDateBegin < r.numDateEnd) '.
                              'OR (r.numDateBegin < ip.numDateEnd AND ip.numDateEnd < r.numDateEnd))')
                   ->addOrderBy('r.dateSortKey')
                   ->andWhere('r.personId = :personId')
                   ->setParameter('personId', $personId);

        $query = $qb->getQuery();
        $result = $query->getResult();
        $role = array();
        $roleLast = null;
        $placeName = array();

        // collect places for roles
        foreach ($result as $r_loop) {
            // save current role with places or collect places for current role
            if ($roleLast !== $r_loop[0]) {
                if (!is_null($roleLast)) {
                    $roleLast->setPlaceName($placeName);
                    $role[] = $roleLast;
                }
                $roleLast = $r_loop[0];
                if (!is_null($r_loop['placeName'])) {
                    $placeName = array($r_loop['placeName']);
                } else {
                    $placeName = array();
                }
            } else {
                if (!is_null($r_loop['placeName'])) {
                    $placeName = array($r_loop['placeName']);
                }
            }
        }
        if (!is_null($roleLast)) {
            $roleLast->setPlaceName($placeName);
            $role[] = $roleLast;
        }

        return($role);
    }

    /**
     * set place names for $role
     */
    public function setPlaceName(PersonRole $role) {
        $qb = $this->createQueryBuilder('r')
                   ->addSelect('ip.placeName')
                   ->leftjoin('App\Entity\InstitutionPlace', 'ip', 'WITH',
                              'r.institutionId = ip.institutionId '.
                              'AND ( '.
                              'r.numDateBegin IS NULL AND r.numDateEnd IS NULL '.
                              'OR (ip.numDateBegin < r.numDateBegin AND r.numDateBegin < ip.numDateEnd) '.
                              'OR (ip.numDateBegin < r.numDateEnd AND r.numDateEnd < ip.numDateEnd) '.
                              'OR (r.numDateBegin < ip.numDateBegin AND ip.numDateBegin < r.numDateEnd) '.
                              'OR (r.numDateBegin < ip.numDateEnd AND ip.numDateEnd < r.numDateEnd))')
                   ->addOrderBy('r.dateSortKey')
                   ->andWhere('r.id = :id')
                   ->setParameter('id', $role->getId());

        $query = $qb->getQuery();
        $result = $query->getResult();

        return null;
    }

    /**
     *
     */
    public function setPlaceNameInRole($role_list) {

        $id_role_map = array();
        foreach ($role_list as $role) {
            $id_role_map[$role->getId()] = $role;
        }

        if (count($id_role_map) == 0) {
            return null;
        }

        // hopefully we get an entry in $result for each role, even if roles
        // belong to the same place
        $qb = $this->createQueryBuilder('r')
                   ->select('r.id, ip.placeName')
                   ->leftjoin('App\Entity\InstitutionPlace', 'ip', 'WITH',
                              'r.institutionId = ip.institutionId '.
                              'AND ( '.
                              'r.numDateBegin IS NULL AND r.numDateEnd IS NULL '.
                              'OR (ip.numDateBegin < r.numDateBegin AND r.numDateBegin < ip.numDateEnd) '.
                              'OR (ip.numDateBegin < r.numDateEnd AND r.numDateEnd < ip.numDateEnd) '.
                              'OR (r.numDateBegin < ip.numDateBegin AND ip.numDateBegin < r.numDateEnd) '.
                              'OR (r.numDateBegin < ip.numDateEnd AND ip.numDateEnd < r.numDateEnd))')
                   ->addOrderBy('r.dateSortKey')
                   ->andWhere('r.id in (:id_list)')
                   ->setParameter('id_list', array_keys($id_role_map));

        $query = $qb->getQuery();
        $result = $query->getResult();

        // if there are several places (which is possible, but rare), we get randomly one of the results
        // better: concatenate multiple results
        foreach ($result as $r_loop) {
            $role = $id_role_map[$r_loop['id']];
            $role->setPlaceName($r_loop['placeName']);
        }

        return null;
    }

    /**
     * @return number of items that refer to the role with $role_id
     */
    public function referenceCount($role_id) {
        $qb = $this->createQueryBuilder('r')
                   ->select('COUNT(DISTINCT i.id) AS n')
                   ->join('App\Entity\Item', 'i', 'WITH', 'i.id = r.personId')
                   ->andWhere('i.isOnline = 1')
                   ->andWhere('r.roleId = :role_id')
                   ->setParameter('role_id', $role_id);
        $query = $qb->getQuery();

        $result = $query->getOneOrNullResult();

        return $result ? $result['n'] : 0;
    }

    /**
     * @return number of items that refer to the role with $role_id
     */
    public function dioceseReferenceCount($diocese_id) {
        $qb = $this->createQueryBuilder('r')
                   ->select('COUNT(DISTINCT i.id) AS n')
                   ->join('App\Entity\Item', 'i', 'WITH', 'i.id = r.personId')
                   ->andWhere('i.isOnline = 1')
                   ->andWhere('r.dioceseId = :diocese_id')
                   ->setParameter('diocese_id', $diocese_id);
        $query = $qb->getQuery();

        $result = $query->getOneOrNullResult();

        return $result ? $result['n'] : 0;
    }

}
