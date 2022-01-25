<?php

namespace App\Repository;

use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\ReferenceVolume;
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

    // redundant to table item_type (simpler, faster than a query)
    const ITEM_TYPE_ID = [
        'Bischof' => 4,
        'Domherr' => 5,
        'Domherr GS' => 6,
    ];

    // conditions
    const COND_DIOCESE = "AND (pr.diocese_name LIKE :param_diocese ".
                       "OR CONCAT('erzbistum ', pr.diocese_name) LIKE :param_diocese ".
                       "OR CONCAT('bistum ', pr.diocese_name) LIKE :param_diocese) ";

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
        $result = array('n' => 0);
        if ($model->isEmpty()) return $result;

        // main query parameters: diocese, office
        $repository = $this->getEntityManager()->getRepository(PersonRole::class);

        // diocese or office
        $diocese = $model->diocese;
        $office = $model->office;
        $name = $model->name;
        if ($diocese || $office) {
            $qb = $repository->createQueryBuilder('pr')
                             ->select('COUNT(DISTINCT pr.personId) as n')
                             ->join('pr.item', 'i')
                             ->andWhere('i.itemTypeId = '.self::ITEM_TYPE_ID['Bischof'])
                             ->andWhere('i.isOnline = 1');
            $qb = $this->addConditions($qb, $model);
            $qb = $this->addFacetsPersonRole($qb, $model);
        } elseif ($name) {
            $qb = $this->createQueryBuilder('p')
                       ->select('COUNT(DISTINCT p.id) as n')
                       ->join('p.item', 'i')
                       ->andWhere('i.itemTypeId = '.self::ITEM_TYPE_ID['Bischof'])
                       ->andWhere('i.isOnline = 1');
            $qb = $this->addConditions($qb, $model);
            //
        }



        $query = $qb->getQuery();
        $result = $query->getOneOrNullResult();

        return $result;
    }

    private function addConditions($qb, $model) {
        $diocese = $model->diocese;
        if ($diocese) {
            $qb->andWhere("(pr.dioceseName LIKE :paramDiocese ".
                          "OR CONCAT('erzbistum ', pr.dioceseName) LIKE :paramDiocese ".
                          "OR CONCAT('bistum ', pr.dioceseName) LIKE :paramDiocese) ")
               ->setParameter('paramDiocese', '%'.$diocese.'%');
        }

        $office = $model->office;
        if ($office) {
            $qb->andWhere("pr.roleName LIKE :value")
               ->setParameter('value', '%'.$office.'%');
        }

        $name = $model->name;
        if ($name) {
            $qb->join('i.nameLookup', 'nlu')
               ->andWhere("nlu.gnFn LIKE :qname OR nlu.gnPrefixFn LIKE :qname")
               ->setParameter('qname', '%'.$name.'%');
        }

        return $qb;
    }


    private function bishopQueryConditions($qb, BishopFormModel $model) {
        # bishop
        $qb->join('p.item', 'itemtype')
           ->andWhere('itemtype.itemTypeId = :itemTypeId')
           ->setParameter(':itemTypeId', self::ITEM_TYPE_ID['Bischof']);

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
            // assume that diocese name is always stored directy in table person_roles
            $qb->join('p.roles', 'prdioc')
               ->andWhere('prdioc.dioceseName LIKE :diocese')
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
            // TODO 2022-01-21 check also PersonRole.roleName

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

        $this->bishopFacets($model, $qb);

        return $qb;
    }

    /**
     * add conditions set by facets
     */
    public function bishopFacets($model, $qb) {
        if($model->facetDiocese) {
            $facetDiocese = array_column($model->facetDiocese, 'name');
            $qb->join('p.roles', 'prfctdioc')
               ->andWhere("prfctdioc.dioceseName IN (:dioceses)")
               ->setParameter(':dioceses', $facetDiocese);
        }
        if($model->facetOffice) {
            $facetOffice = array_column($model->facetOffice, 'name');
            $qb->join('p.roles', 'prfctrole')
               ->andWhere("prfctrole.roleName IN (:roles)")
               ->setParameter(':roles', $facetOffice);
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

    public function bishopWithOfficeByModel_a20220121(BishopFormModel $model, $limit = 0, $offset = 0) {
        $qb = $this->createQueryBuilder('p')
                   ->addSelect('pr')
                   ->addSelect('r')
                   ->leftJoin('p.roles', 'pr')
                   ->leftJoin('pr.role', 'r');

        $this->bishopQueryConditions($qb, $model);
        $this->bishopSortParameter($qb, $model);

        $query = $qb->getQuery();

        if ($limit > 0) {
            $query->setMaxResults($limit);
            $query->setFirstResult($offset);
            $result = new Paginator($query, true);
        } else {
            $result = $query->getResult();
        }

        $debug = false;
        if ($debug) {
            $resultArray = array();
            foreach($result as $p) {
                $resultArray[] = $p;
            }
        }

        return $result;
    }

    public function bishopByModel(BishopFormModel $model, $limit = 0, $offset = 0) {
        $result = null;
        if ($model->isEmpty()) return $result;

        $em = $this->getEntityManager();

        $diocese = $model->diocese;
        $office = $model->office;
        $name = $model->name;
        if ($office || $diocese) {
            $repository = $em->getRepository(PersonRole::class);

            $qb = $repository->createQueryBuilder('pr')
                             ->select('pr.personId, min(pr.numDateBegin) as sort')
                             ->join('pr.item', 'i')
                             ->join('pr.person', 'p')
                             ->andWhere('i.itemTypeId = '.self::ITEM_TYPE_ID['Bischof'])
                             ->andWhere('i.isOnline = 1')
                             ->addGroupBy('pr.personId')
                             ->addOrderBy('pr.dioceseName')
                             ->addOrderBy('sort')
                             ->addOrderBy('p.familyname')
                             ->addOrderBy('p.givenname')
                             ->addOrderBy('p.id');
            $qb = $this->addConditions($qb, $model);
            $qb = $this->addFacetsPersonRole($qb, $model);
        } elseif ($name) {
            $qb = $repository->createQueryBuilder('pr')
                             ->select('pr.personId')
                             ->join('pr.item', 'i')
                             ->join('pr.person', 'p')
                             ->andWhere('i.itemTypeId = '.self::ITEM_TYPE_ID['Bischof'])
                             ->andWhere('i.isOnline = 1')
                             ->addGroupBy('pr.personId')
                             ->addOrderBy('p.familyname')
                             ->addOrderBy('p.givenname')
                             ->addOrderBy('p.id');
            $qb = $this->addConditions($qb, $model);
            $qb = $this->addFacetsPersonRole($qb, $model);
        }

        $qb->setMaxResults($limit)
           ->setFirstResult($offset);
        $query = $qb->getQuery();

        $ids = array_map(function($a) { return $a["personId"]; },
                         $query->getResult());
        return $ids;

    }

    /**
     * countDiocese(BishopFormModel $model)
     *
     * return array of dioceses related to a person's role (used for facet)
     */
    public function countDiocese(BishopFormModel $model, $itemTypeId) {
        $repository = $this->getEntityManager()->getRepository(PersonRole::class);

        $qb = $repository->createQueryBuilder('pr')
                         ->select('DISTINCT pr.dioceseName AS name, COUNT(DISTINCT(pr.personId)) as n')
                         ->join('pr.item', 'i')
                         ->andWhere("i.itemTypeId = ${itemTypeId}")
                         ->andWhere("pr.dioceseName IS NOT NULL");

        $this->addConditions($qb, $model);

        $qb->groupBy('pr.dioceseName');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * countOffice(BishopFormModel $model)
     *
     * return array of offices related to a person's role (used for facet)
     */
    public function countOffice(BishopFormModel $model, $itemTypeId) {
        $repository = $this->getEntityManager()->getRepository(PersonRole::class);

        $qb = $repository->createQueryBuilder('pr')
                         ->select('DISTINCT pr.roleName AS name, COUNT(DISTINCT(pr.personId)) as n')
                         ->join('pr.item', 'i')
                         ->andWhere("i.itemTypeId = ${itemTypeId}")
                         ->andWhere("pr.roleName IS NOT NULL");

        $this->addConditions($qb, $model);

        $qb->groupBy('pr.roleName');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * AJAX
     */
    public function suggestName($name, $hintSize, $itemTypeId) {
        $qb = $this->createQueryBuilder('p')
                   ->select("DISTINCT CASE WHEN n.gnPrefixFn IS NOT NULL ".
                            "THEN n.gnPrefixFn ELSE n.gnFn END ".
                            "AS suggestion")
                   ->join('p.nameLookup', 'n')
                   ->join('p.item', 'item')
                   ->andWhere('item.itemTypeId = :itemType')
                   ->setParameter(':itemType', $itemTypeId)
                   ->andWhere('n.gnFn LIKE :name OR n.gnPrefixFn LIKE :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();
        // dd($suggestions);

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestDiocese($name, $hintSize) {
        $qb = $this->createQueryBuilder('p')
                   ->select("DISTINCT r.dioceseName AS suggestion")
                   ->join('p.roles', 'r')
                   ->join('p.item', 'item')
                   ->andWhere('item.itemTypeId = :itemType')
                   ->setParameter(':itemType', self::ITEM_TYPE_ID['Bischof'])
                   ->andWhere('r.dioceseName like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();
        // dd($suggestions);

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestOffice($name, $hintSize) {
        $qb = $this->createQueryBuilder('p')
                   ->select("DISTINCT r.roleName AS suggestion")
                   ->join('p.roles', 'r')
                   ->join('p.item', 'item')
                   ->andWhere('item.itemTypeId = :itemType')
                   ->setParameter(':itemType', self::ITEM_TYPE_ID['Bischof'])
                   ->andWhere('r.roleName like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();
        // dd($suggestions);

        return $suggestions;
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

    /**
     * add conditions set by facets
     */
    private function addFacetsPersonRole($qb, $model) {

        $facetDiocese = $model->facetDiocese;
        if ($facetDiocese) {
            $values = array_column($facetDiocese, 'name');
            $qb->join('\App\Entity\PersonRole', 'prfctdioc', 'WITH', 'prfctdioc.personId = pr.personId')
               ->andWhere("prfctdioc.dioceseName IN (:values)")
               ->setParameter('values', $values);
        }

        $facetOffice = $model->facetOffice;
        if ($facetOffice) {
            $values = array_column($facetOffice, 'name');
            $qb->join('\App\Entity\PersonRole', 'prfctofc', 'WITH', 'prfctofc.personId = pr.personId')
               ->andWhere("prfctofc.roleName IN (:values)")
               ->setParameter('values', $values);
        }

        return $qb;
    }


    /**
     * add conditions set by facets
     */
    private function addFacets($qb, $model) {
        if (false && $model->facetLocations) {
            $locations = array_column($querydata->facetLocations, 'id');
            $qb->join('co.officelookup', 'ocfctl')
               ->andWhere('ocfctl.locationName IN (:locations)')
               ->setParameter('locations', $locations);
        }
        if (false && $model->facetMonasteries) {
            $ids_monastery = array_column($querydata->facetMonasteries, 'id');
            // $facetMonasteries = array_map(function($a) {return 'Domstift '.$a;}, $facetMonasteries);
            $qb->join('co.officelookup', 'ocfctp')
               ->join('ocfctp.monastery', 'mfctp')
               ->andWhere('mfctp.wiagid IN (:places)')
               ->setParameter('places', $ids_monastery);
        }

        // version where `pr` is part of the query already
        $facetDiocese = $model->facetDiocese;
        if ($facetDiocese) {
            $values = array_column($facetDiocese, 'name');
            $qb->join('\App\Entity\PersonRole', 'prfctdioc', 'WITH', 'prfctdioc.personId = pr.personId')
               ->andWhere("prfctdioc.dioceseName IN (:values)")
               ->setParameter('values', $values);
        }

        if ($model->facetOffice) {
            $facetOffices = array_column($querydata->facetOffices, 'name');
            $qb->join('co.officelookup', 'ocfctoc')
               ->andWhere("ocfctoc.officeName IN (:offices)")
               ->setParameter('offices', $facetOffices);
        }

        return $qb;
    }

    public function findWithOffice($id) {
        $qb = $this->createQueryBuilder('p')
                   ->join('p.roles', 'pr')
                   ->addSelect('pr')
                   ->andWhere('p.id = :id')
                   ->setParameter('id', $id);
        $query = $qb->getQuery();
        return $query->getOneOrNullResult();
    }

    public function findWithAssociations($id) {
        $qb = $this->createQueryBuilder('p')
                   ->join('p.roles', 'pr')
                   ->join('p.item', 'i')
                   ->join('i.itemReference', 'r')
                   ->addSelect('pr')
                   ->addSelect('r')
                   ->andWhere('p.id = :id')
                   ->setParameter('id', $id);

        $person = $this->findWithOffice($id);
        if ($person) {
            $repository = $this->getEntityManager()->getRepository(ReferenceVolume::class);
            foreach ($person->getItem()->getReference() as $reference) {
                $itemTypeId = $reference->getItemTypeId();
                $referenceId = $reference->getReferenceId();
                $referenceVolume = $repository->findByCombinedKey($itemTypeId, $referenceId);
                $reference->setReferenceVolume($referenceVolume);
            }
        }
        return $person;
    }
}
