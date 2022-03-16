<?php

namespace App\Repository;

use App\Entity\CanonLookup;
use App\Entity\Item;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\InstitutionPlace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CanonLookup|null find($id, $lockMode = null, $lockVersion = null)
 * @method CanonLookup|null findOneBy(array $criteria, array $orderBy = null)
 * @method CanonLookup[]    findAll()
 * @method CanonLookup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CanonLookupRepository extends ServiceEntityRepository
{
    // tolerance for the comparison of dates
    const MARGINYEAR = 1;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CanonLookup::class);
    }

    // /**
    //  * @return CanonLookup[] Returns an array of CanonLookup objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?CanonLookup
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function canonIds($model, $limit = 0, $offset = 0) {
        $result = null;

        $itemTypeId = Item::ITEM_TYPE_ID['Domherr'];
        $instTypeId = Item::ITEM_TYPE_ID['Domstift'];

        $domstift = $model->domstift;
        $office = $model->office;
        $place = $model->place;
        $year = $model->year;
        $name = $model->name;
        $someid = $model->someid;
        if ($model->isEmpty() || $domstift || $office) {
            // we need not to check for item.is_online because the is guaranteed
            // for entries in canon_lookup
            $qb = $this->createQueryBuilder('c')
                       ->select('c.personIdName, inst.nameShort as sortA')
                       ->join('app\Entity\Person', 'p', 'WITH', 'p.id = c.personIdName')
                       ->join('App\Entity\PersonRole', 'role_domstift', 'WITH', 'role_domstift.personId = c.personIdRole')
                       ->join('App\Entity\Institution',
                              'inst',
                              'WITH', "role_domstift.institutionId = inst.id")
                       ->andWhere('inst.itemTypeId = :inst_type_id')
                       ->groupBy('c.personIdName')
                       ->addOrderBy('sortA')
                       ->addOrderBy('role_domstift.dateSortKey')
                       ->addOrderBy('p.familyname')
                       ->addOrderBy('p.givenname')
                       ->addOrderBy('p.id')
                       ->setParameter('inst_type_id', $instTypeId);
        } elseif ($place) {
            $qb = $this->createQueryBuilder('c')
                       ->select('c.personIdName')
                       ->join('app\Entity\Person', 'p', 'WITH', 'p.id = c.personIdName')
                       ->join('App\Entity\PersonRole', 'role', 'WITH', 'role.personId = c.personIdRole')
                       ->groupBy('c.personIdName')
                       ->addOrderBy('ip.placeName')
                       ->addOrderBy('role.dateSortKey')
                       ->addOrderBy('p.familyname')
                       ->addOrderBy('p.givenname')
                       ->addOrderBy('p.id');
        } elseif ($year) {
            $qb = $this->createQueryBuilder('c')
                       ->select('c.personIdName')
                       ->join('App\Entity\Person', 'p', 'WITH', 'p.id = c.personIdName')
                       ->addGroupBy('c.personIdName')
                       ->addOrderBy('p.dateMin')
                       ->addOrderBy('p.dateMax')
                       ->addOrderBy('p.familyname')
                       ->addOrderBy('p.givenname')
                       ->addOrderBy('p.id');
        } elseif ($name || $someid) {
            $qb = $this->createQueryBuilder('c')
                       ->select('c.personIdName')
                       ->join('App\Entity\Person', 'p', 'WITH', 'p.id = c.personIdName')
                       ->addGroupBy('c.personIdName')
                       ->addOrderBy('p.familyname')
                       ->addOrderBy('p.givenname')
                       ->addOrderBy('p.id');
        }

        $qb = $this->addCanonConditions($qb, $model);
        // TODO 2022-02-15
        // $qb = $this->addCanonFacets($qb, $model);

        if ($limit > 0) {
            $qb->setMaxResults($limit)
               ->setFirstResult($offset);
        }
        $query = $qb->getQuery();

        // $ids = array_map(function($a) { return $a["id"]; },
        //                  $query->getResult());

        return $query->getResult();

    }

    public function addCanonConditions($qb, $model) {
        $domstift = $model->domstift;
        $office = $model->office;
        $name = $model->name;
        $place = $model->place;
        $year = $model->year;
        $someid = $model->someid;

        if ($domstift) {
            $qb->andWhere('inst.name LIKE :q_domstift')
               ->setParameter('q_domstift', '%'.$domstift.'%');
            // combine queries at the level of offices
            if ($office) {
            $qb->join('App\Entity\PersonRole', 'role', 'WITH', 'role.institutionId = inst.id AND role.personId = c.personIdRole')
               ->join('role.role', 'role_type')
               ->andWhere('role.roleName LIKE :q_office OR role_type.name LIKE :q_office')
               ->setParameter('q_office', '%'.$office.'%');
            }
        } elseif ($office) {
            $qb->join('App\Entity\PersonRole', 'role', 'WITH', 'role.personId = c.personIdRole')
               ->join('role.role', 'role_type')
               ->andWhere('role.roleName LIKE :q_office OR role_type.name LIKE :q_office')
               ->setParameter('q_office', '%'.$office.'%');
        }

        if ($place) {
            $qb->join('App\Entity\InstitutionPlace', 'ip', 'WITH',
                          'role.institutionId = ip.institutionId '.
                          'AND ( '.
                          'role.numDateBegin IS NULL AND role.numDateEnd IS NULL '.
                          'OR (ip.numDateBegin < role.numDateBegin AND role.numDateBegin < ip.numDateEnd) '.
                          'OR (ip.numDateBegin < role.numDateEnd AND role.numDateEnd < ip.numDateEnd) '.
                          'OR (role.numDateBegin < ip.numDateBegin AND ip.numDateBegin < role.numDateEnd) '.
                          'OR (role.numDateBegin < ip.numDateEnd AND ip.numDateEnd < role.numDateEnd))')
                ->andWhere('ip.placeName LIKE :q_place')
                ->setParameter('q_place', '%'.$place.'%');
        }

        if ($year) {
            // link person with all canons via person_id_role
            $qb->join('App\Entity\Person', 'p_year', 'WITH', 'p_year.id = c.personIdRole')
               ->andWhere("p_year.dateMin - :mgnyear < :q_year ".
                          " AND :q_year < p_year.dateMax + :mgnyear")
               ->setParameter(':mgnyear', self::MARGINYEAR)
               ->setParameter('q_year', $year);
        }

        if ($name) {
            // link name_lookup with all canons via person_id_role
            $qb->join('App\Entity\NameLookup', 'n', 'WITH', 'n.personId = c.personIdRole')
               ->andWhere('n.gnFn LIKE :q_name OR n.gnPrefixFn LIKE :q_name')
               ->setParameter('q_name', '%'.$name.'%');
        }

        if ($someid) {
            $qb->join('App\Entity\Person', 'p_id', 'WITH', 'p_id.id = c.personIdRole')
               ->join('p_id.item', 'i')
               ->join('i.idExternal', 'ixt')
               ->andWhere("i.idPublic LIKE :q_id ".
                          "OR ixt.value LIKE :q_id")
               ->setParameter('q_id', '%'.$someid.'%');
        }

        return $qb;
    }

    /**
     * find canon with person via personIdName and roles via personIdRole where prioRole == 1
     * @return CanonLookup
     */
    public function findWithRoleListView($id) {
        $qb = $this->createQueryBuilder('c')
                   ->select('c, p')
                   ->join('App\Entity\Person', 'p', 'WITH', 'c.personIdName = p.id')
                   ->andWhere('c.personIdName = :id')
                   ->orderBy('c.prioRole')
                   ->setParameter('id', $id);

        $query = $qb->getQuery();

        $result = $query->getResult();

        $personRoleRepository = $this->getEntityManager()
                                     ->getRepository(PersonRole::class);

        $canon = null;
        $person = null;
        foreach ($result as $r) {
            if (is_a($r, CanonLookup::class)) {
                if ($r->getPrioRole() == 1) {
                    $canon = $r;
                } else {
                    $canon->setHasSibling(true);
                }
            } elseif (is_a($r, Person::class)) {
                $person = $r;
            }
        }

        if (!is_null($canon)) {
            $canon->setPerson($person);
            $canon->setRoleListView($personRoleRepository->findRoleWithPlace($canon->getPersonIdRole()));
        }

        return $canon;

    }

    /**
     * find canons with person via personIdName and personIdRole, respectively
     * @return CanonLookup[]
     */
    public function findWithPerson($id) {
        $qb = $this->createQueryBuilder('c')
                   ->select('c')
                   ->andWhere('c.personIdName = :id')
                   ->addOrderBy('c.prioRole')
                   ->setParameter('id', $id);

        $query = $qb->getQuery();
        $result = $query->getResult();

        $personRepository = $this->getEntityManager()
                                 ->getRepository(Person::class);
        $personRoleRepository = $this->getEntityManager()
                                     ->getRepository(PersonRole::class);

        foreach ($result as $r) {
            $person = $personRepository->find($r->getPersonIdName());
            $r->setPerson($person);
            $personRole = $personRepository->findWithAssociations($r->getPersonIdRole());
            $r->setPersonRole($personRole);
        }

        return $result;

    }

    /**
     * AJAX
     */
    public function suggestCanonName($name, $hintSize) {
        // join name_lookup via personIdRole (all canons)
        $qb = $this->createQueryBuilder('c')
                   ->select("DISTINCT CASE WHEN n.gnPrefixFn IS NOT NULL ".
                            "THEN n.gnPrefixFn ELSE n.gnFn END ".
                            "AS suggestion")
                   ->join('App\Entity\NameLookup', 'n', 'WITH', 'n.personId = c.personIdRole')
                   ->andWhere('n.gnFn LIKE :name OR n.gnPrefixFn LIKE :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestCanonDomstift($name, $hintSize) {
        $itemTypeIdDomstift = 3;
        $qb = $this->createQueryBuilder('c')
                   ->select("DISTINCT inst.name AS suggestion")
                   ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = c.personIdRole')
                   ->join('pr.institution', 'inst')
                   ->andWhere("inst.itemTypeId = $itemTypeIdDomstift")
                   ->andWhere('inst.name like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestCanonOffice($name, $hintSize) {
        $itemTypeIdDomstift = 3;
        $qb = $this->createQueryBuilder('c')
                   ->select("DISTINCT pr.roleName AS suggestion")
                   ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = c.personIdRole')
                   ->andWhere('pr.roleName like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestCanonPlace($name, $hintSize) {
        $qb = $this->createQueryBuilder('c')
                   ->select("DISTINCT ip.placeName AS suggestion")
                   ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = c.personIdRole')
                   ->join('App\Entity\InstitutionPlace', 'ip', 'WITH', 'ip.institutionId = pr.institutionId')
                   ->andWhere('ip.placeName like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }





}
