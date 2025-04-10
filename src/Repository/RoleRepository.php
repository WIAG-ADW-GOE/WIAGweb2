<?php

namespace App\Repository;

use App\Entity\Role;
use App\Entity\RoleGroup;

use App\Service\UtilService;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Role|null find($id, $lockMode = null, $lockVersion = null)
 * @method Role|null findOneBy(array $criteria, array $orderBy = null)
 * @method Role[]    findAll()
 * @method Role[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    // /**
    //  * Returns Role[] Returns an array of Role objects
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
    public function findOneBySomeField($value): ?Role
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    /**
     * Test: find persons by role
     */
    public function findPersons($name) {
        $qb = $this->createQueryBuilder('r')
                   ->join('r.personRoles', 'pr')
                   ->select('pr.personId')
                   ->andWhere('r.name = :name')
                   ->setParameter(':name', $name);

        $query = $qb->getQuery();

        $result = $query->getResult();

        return $result;
    }

    public function findByModel($model) {
        $qb = $this->createQueryBuilder('r')
                   ->select('r', 'i')
                   ->join('r.item', 'i')
                   ->leftjoin('i.urlExternal', 'ext')
                   ->addOrderBy('r.name');

        if ($model['roleGroup'] != '') {
            $qb->join('r.roleGroup', 'rg')
               ->andWhere('rg.name = :roleGroup')
               ->setParameter('roleGroup', $model['roleGroup']);
        }

        if ($model['name'] != '') {
            $qb->andWhere('r.name like :name')
               ->setParameter('name', '%'.$model['name'].'%');
        }

        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     * find roles by ID
     */
    public function findList($id_list) {
        $qb = $this->createQueryBuilder('r')
                   ->select('r')
                   ->andWhere('r.id in (:id_list)')
                   ->setParameter('id_list', $id_list);

        $query = $qb->getQuery();
        $role_list = $query->getResult();

        $role_list = UtilService::reorder($role_list, $id_list, "id");
        return $role_list;
    }

    /**
     * Returns list of role names for autocompletion
     */
    public function suggestName($q_param, $size) {
        $qb = $this->createQueryBuilder('r')
                   ->select('DISTINCT r.name AS suggestion')
                   ->andWhere('r.name like :q_param')
                   ->setParameter('q_param', '%'.$q_param.'%');

        $qb->setMaxResults($size);

        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     * Returns list of role names for autocompletion
     */
    public function suggestGender($q_param) {
        $qb = $this->createQueryBuilder('r')
                   ->select('DISTINCT r.gender AS suggestion')
                   ->andWhere('r.gender like :q_param')
                   ->setParameter('q_param', '%'.$q_param.'%');

        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     * Returns list of role names for autocompletion
     */
    public function suggestRoleGroup($q_param) {
        $qb = $this->getEntitymanager()->createQueryBuilder()
                   ->select('DISTINCT rg.name AS suggestion')
                   ->andWhere('rg.name like :q_param')
                   ->setParameter('q_param', '%'.$q_param.'%');

        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     * Returns list of role names for autocompletion
     */
    public function suggestRoleGroupEn($q_param) {
        $qb = $this->getEntitymanager()->createQueryBuilder()
                   ->select('DISTINCT rg.name_en AS suggestion')
                   ->andWhere('rg.name_en like :q_param')
                   ->setParameter('q_param', '%'.$q_param.'%');

        $query = $qb->getQuery();
        return $query->getResult();
    }


}
