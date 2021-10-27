<?php

namespace App\Repository;

use App\Entity\Person;
use App\Form\Model\BishopFormModel;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;


/**
 * @method Person|null find($id, $lockMode = null, $lockVersion = null)
 * @method Person|null findOneBy(array $criteria, array $orderBy = null)
 * @method Person[]    findAll()
 * @method Person[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonRepository extends ServiceEntityRepository {

    // allowed deviation in the query parameter for 'year'
    const MARGIN_YEAR = 1;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Person::class);
    }

    // /**
    //  * @return Person[] Returns an array of Person objects
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
    public function findOneBySomeField($value): ?Person
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function bishopCountByModel(BishopFormModel $model) {
        $result = array(1 => 0);
        if($model->isEmpty()) return $result;

        $qb = $this->createQueryBuilder('p')
                   ->select('COUNT(DISTINCT p.id)');

        $this->bishopQueryConditions($qb, $model);

        $query = $qb->getQuery();

        $result = $query->getOneOrNullResult();
        return $result;
    }


    private function bishopQueryConditions($qb, BishopFormModel $model) {

        # identifier
        $someid = $model->someid;
        if ($someid && $someid != "") {
            $qb->join('p.item', 'item')
               ->join('item.idExternal', 'idExternal')
               ->andWhere(':someid = item.idPublic OR :someid = item.idInSource OR :someid = idExternal.value')
               ->setParameter('someid', $someid);
        }

        # name
        $name = $model->name;
        if ($name && $name != "") {
            $qb->join('p.nameLookup', 'nameLookup')
               ->andWhere('nameLookup.gnFn LIKE :name OR nameLookup.gnPrefixFn LIKE :name')
               ->setParameter(':name', '%'.$name.'%');
        }

        # diocese
        $diocese = $model->diocese;
        if ($diocese && $diocese != "") {
            $qb->join('p.roles', 'prdioc')
               ->join('prdioc.diocese', 'dioc')
               ->andWhere('prdioc.dioceseName LIKE :diocese OR dioc.name LIKE :diocese')
               ->setParameter(':diocese', '%'.$diocese.'%');
        }

        # office
        $office = $model->office;
        if ($office && $office != "") {
            # join PersonRole a second time, because it is only needed for filtering here
            $qb->join('p.roles', 'prrole')
               ->join('prrole.role', 'role')
               ->andWhere('role.name LIKE :office')
               ->setParameter('office', '%'.$office.'%');

            // allowed but slower
            // $qb->andWhere('EXISTS (SELECT copr.roleId FROM App\Entity\PersonRole copr '.
            //               'WHERE copr.roleId = :roleid '.
            //               'AND copr.personId = p.id)')
            //    ->setParameter(':roleid', 5356);
        }

        $year = $model->year;
        if ($year && $year != "") {
            $qb->andWhere('p.dateMin - :mgnyear < :year AND :year < p.dateMax + :mgnyear')
                ->setParameter(':mgnyear', self::MARGIN_YEAR)
                ->setParameter(':year', $year);
        }

        return $qb;
    }

    public function bishopSortParameter($qb, $model) {

        $sort = 'displayorder';
        $name = $model->name;
        $year = $model->year;
        $office = $model->office;
        $diocese = $model->diocese;

        if($year || $office || $name) $sort = 'displayOrder';
        if($diocese) $sort = 'diocese';

        /**
         * a reliable order is required, therefore person.givenname shows up
         * in each sort clause
         */

        switch($sort) {
        case 'displayOrder':
            $qb->leftjoin('p.displayOrder', 'do')
               ->andWhere('do.diocese = :diocese')
               ->setParameter(':diocese', 'any')
               ->orderBy('do.displayOrder, p.givenname, p.id');
            break;
        case 'diocese':
            $qb->leftjoin('p.displayOrder', 'do')
               ->andWhere('do.diocese LIKE :doDiocese')
               ->setParameter(':doDiocese', '%'.$diocese.'%')
               ->orderBy('do.displayOrder, p.givenname, p.id');
            break;
        }

        return $qb;
    }

    public function bishopWithOfficeByModel(BishopFormModel $model, $limit = 0, $offset = 0) {
        $qb = $this->createQueryBuilder('p')
                   ->addSelect('pr')
                   ->addSelect('r')
                   ->leftJoin('p.roles', 'pr')
                   ->leftJoin('pr.role', 'r');

        $this->bishopQueryConditions($qb, $model);
        $this->bishopSortParameter($qb, $model);

        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);

        $query = $qb->getQuery();

        $result = new Paginator($query, true);

        return $result;
    }

    /**
     * countDiocese(BishopFormModel $model)
     *
     * return array of dioceses related to a person's role (used for facet)
     */
    public function countDiocese(BishopFormModel $model) {
        $qb = $this->createQueryBuilder('p')
                   ->select('DISTINCT pr.dioceseName AS name, COUNT(DISTINCT(p.id)) as n')
                   ->join('p.roles', 'pr')
                   ->andWhere("pr.dioceseName IS NOT NULL");

        $this->bishopQueryConditions($qb, $model);

        $qb->groupBy('pr.dioceseName');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }


    /**
     * Test
     */
    public function findByRole($name) {
        $qb = $this->createQueryBuilder('p')
                   ->join('p.roles', 'pr')
                   ->join('pr.role', 'r')
                   ->andWhere('r.name = :name')
                   ->setParameter(':name', $name);
        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }


}
