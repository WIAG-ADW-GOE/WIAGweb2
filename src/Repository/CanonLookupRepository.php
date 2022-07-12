<?php

namespace App\Repository;

use App\Entity\CanonLookup;
use App\Entity\Item;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\InstitutionPlace;
use App\Entity\Institution;
use App\Entity\UrlExternal;
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
    // item type for domstift
    const ITEMTYPEDOMSTIFT = 3;

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
        $itemTypeIdDomstift = Item::ITEM_TYPE_ID['Domstift'];

        $domstift = $model->domstift;
        $office = $model->office;
        $place = $model->place;
        $year = $model->year;
        $name = $model->name;
        $someid = $model->someid;

        // table person (linked via personIdName is always used for sorting
        // all queries are combined at the level of a person, so join it here once and forever
        $qb = $this->createQueryBuilder('c')
                   ->join('App\Entity\Person', 'p', 'WITH', 'p.id = c.personIdName');

        $this->addCanonConditions($qb, $model);
        $this->addCanonFacets($qb, $model);

        $add_dateSortKey = false;
        if ($domstift) {
            $qb->select('c.personIdName, inst_domstift.nameShort as sortA')
               ->join('App\Entity\PersonRole', 'role_list_view',
                      'WITH', 'role_list_view.personId = c.personIdRole AND c.prioRole = 1')
               ->groupBy('c.personIdName')
               ->addOrderBy('sortA')
               ->addOrderBy('role_list_view.dateSortKey');
        } elseif ($office || $model->isEmpty()) {
            // use role with prio 1 for sorting; requires join of CanonLookup to itself
            $qb->select('DISTINCT(c.personIdName) as personIdName')
               ->join('App\Entity\CanonSort', 'c_sort', 'WITH', 'c.personIdName = c_sort.id')
               ->addOrderBy('CASE WHEN c_sort.domstiftShort IS NULL THEN 1 ELSE 0 END')
               ->addOrderBy('c_sort.domstiftShort')
               ->addOrderBy('c_sort.dateSortKey');
        } elseif ($place) {
            // use role with prio 1 for sorting; requires join of CanonLookup to itself
            $qb->select('c.personIdName')
               ->join('App\Entity\CanonLookup', 'c_all', 'WITH', 'c.personIdName = c_all.personIdName')
               ->join('App\Entity\PersonRole', 'role_all',
                      'WITH', 'role_all.personId = c_all.personIdRole and c_all.prioRole = 1')
                ->groupBy('c.personIdName')
                ->addOrderBy('ip.placeName')
                ->addOrderBy('role_all.dateSortKey');
        } elseif ($name || $someid || $year) {
            $qb->select('c.personIdName, min(role_all.dateSortKey) as dateSortKey')
               ->join('App\Entity\CanonLookup', 'c_all', 'WITH', 'c.personIdName = c_all.personIdName')
               ->join('App\Entity\PersonRole', 'role_all',
                      'WITH', 'role_all.personId = c_all.personIdRole')
               ->groupBy('c.personIdName');
            if ($year) {
                $qb->addOrderBy('dateSortKey');
            } else {
                $add_dateSortKey = true;
            }
        }

        $qb->addOrderBy('p.familyname')
           ->addOrderBy('p.givenname');

        if ($add_dateSortKey) {
            $qb->addOrderBy('dateSortKey');
        }

        // make sure that the list is stable
        $qb->addOrderBy('p.id');

        if ($limit > 0) {
            $qb->setMaxResults($limit)
               ->setFirstResult($offset);
        }
        $query = $qb->getQuery();

        return $query->getResult();

    }

    public function addCanonConditions($qb, $model) {
        $itemTypeDomstift = Item::ITEM_TYPE_ID['Domstift'];

        $domstift = $model->domstift;
        $office = $model->office;
        $name = $model->name;
        $place = $model->place;
        $year = $model->year;
        $someid = $model->someid;



        if ($domstift) {
            // apply query criteria to all roles and join with canon_lookup
            $qb->innerjoin('\App\Entity\CanonLookup', 'c_q', 'WITH', 'c_q.personIdName = c.personIdName')
               ->join('App\Entity\PersonRole', 'role', 'WITH', 'role.personId = c_q.personIdRole')
               ->join('role.institution', 'inst_domstift')
               ->andWhere('inst_domstift.name LIKE :q_domstift')
               ->setParameter('q_domstift', '%'.$domstift.'%');
            // combine queries for domstift and office at the level of PersonRole
            if ($office) {
                $qb->join('role.role', 'role_type')
                   ->andWhere('role.roleName LIKE :q_office OR role_type.name LIKE :q_office')
                   ->setParameter('q_office', '%'.$office.'%');
            }
            $qb->andWhere('inst_domstift.itemTypeId = :itemTypeDomstift')
               ->setParameter('itemTypeDomstift', $itemTypeDomstift);
        } elseif ($office) {
            $qb->join('App\Entity\PersonRole', 'role', 'WITH', 'role.personId = c.personIdRole')
               ->join('role.role', 'role_type')
               // ->leftjoin('role.institution', 'inst_domstift') // sorting
               // ->andWhere('inst_domstift.itemTypeId = :itemTypeDomstift')
               // ->setParameter('itemTypeDomstift', $itemTypeDomstift)
               ->andWhere('role.roleName LIKE :q_office OR role_type.name LIKE :q_office')
               ->setParameter('q_office', '%'.$office.'%');
        }

        if ($place) {
            // combine queries for place only at the level of Person
            $qb->join('App\Entity\PersonRole', 'role_place', 'WITH', 'role_place.personId = c.personIdRole')
               ->join('App\Entity\InstitutionPlace', 'ip', 'WITH',
                          'role_place.institutionId = ip.institutionId '.
                          'AND ( '.
                          'role_place.numDateBegin IS NULL AND role_place.numDateEnd IS NULL '.
                          'OR (ip.numDateBegin < role_place.numDateBegin AND role_place.numDateBegin < ip.numDateEnd) '.
                          'OR (ip.numDateBegin < role_place.numDateEnd AND role_place.numDateEnd < ip.numDateEnd) '.
                          'OR (role_place.numDateBegin < ip.numDateBegin AND ip.numDateBegin < role_place.numDateEnd) '.
                          'OR (role_place.numDateBegin < ip.numDateEnd AND ip.numDateEnd < role_place.numDateEnd))')
                ->andWhere('ip.placeName LIKE :q_place')
                ->setParameter('q_place', '%'.$place.'%');
        }

        if ($name) {
            $qb->join('App\Entity\NameLookup', 'name_lookup', 'WITH', 'name_lookup.personId = c.personIdRole')
               ->andWhere('name_lookup.gnFn LIKE :q_name OR name_lookup.gnPrefixFn LIKE :q_name')
               ->setParameter('q_name', '%'.$name.'%');
        }

        if ($someid || $year || $name) {
            // case name: add p_by_role for sorting
            $qb->join('App\Entity\Person', 'p_by_role', 'WITH', 'p_by_role.id = c.personIdRole');
            if ($someid) {
                $qb->join('p_by_role.item', 'item')
                   ->leftJoin('item.idExternal', 'ixt')
                   ->andWhere("item.idPublic LIKE :q_id ".
                              "OR ixt.value LIKE :q_id")
                   ->setParameter('q_id', '%'.$someid.'%');
            }
            if ($year) {
                $qb->andWhere("p_by_role.dateMin - :mgnyear < :q_year ".
                              " AND :q_year < p_by_role.dateMax + :mgnyear")
                   ->setParameter('mgnyear', self::MARGINYEAR)
                   ->setParameter('q_year', $year);
            }
        }

        return $qb;

    }

    /**
     * add conditions set by facets
     */
    private function addCanonFacets($qb, $model) {

        $facetDomstift = $model->facetDomstift;
        if ($facetDomstift) {
            $valFctDft = array_column($facetDomstift, 'name');
            $qb->join('App\Entity\PersonRole', 'prfctdft', 'WITH', 'prfctdft.personId = c.personIdRole')
               ->join('prfctdft.institution', 'instfctdft')
               ->andWhere("instfctdft.nameShort IN (:valFctDft)")
               ->setParameter('valFctDft', $valFctDft);
        }

        $facetPlace = $model->facetPlace;
        if ($facetPlace) {
            $valFctPlc = array_column($facetPlace, 'name');
            $qb->join('App\Entity\PersonRole', 'prfctplc', 'WITH', 'prfctplc.personId = c.personIdRole')
               ->join('App\Entity\InstitutionPlace', 'ipfct', 'WITH',
                      'prfctplc.institutionId = ipfct.institutionId '.
                      'AND ( '.
                      'prfctplc.numDateBegin IS NULL AND prfctplc.numDateEnd IS NULL '.
                      'OR (ipfct.numDateBegin < prfctplc.numDateBegin AND prfctplc.numDateBegin < ipfct.numDateEnd) '.
                      'OR (ipfct.numDateBegin < prfctplc.numDateEnd AND prfctplc.numDateEnd < ipfct.numDateEnd) '.
                      'OR (prfctplc.numDateBegin < ipfct.numDateBegin AND ipfct.numDateBegin < prfctplc.numDateEnd) '.
                      'OR (prfctplc.numDateBegin < ipfct.numDateEnd AND ipfct.numDateEnd < prfctplc.numDateEnd))')
               ->andWhere('ipfct.placeName IN (:valFctPlc)')
               ->setParameter('valFctPlc', $valFctPlc);
        }

        $facetOffice = $model->facetOffice;
        if ($facetOffice) {
            $valFctOfc = array_column($facetOffice, 'name');
            $qb->join('App\Entity\PersonRole', 'prfctofc', 'WITH', 'prfctofc.personId = c.personIdRole')
               ->join('prfctofc.role', 'rolefctofc')
               ->andWhere('rolefctofc.name in (:valFctOfc)')
               ->setParameter('valFctOfc', $valFctOfc);
        }

        $facetUrl = $model->facetUrl;
        if ($facetUrl) {
            $valFctUrl = array_column($facetUrl, 'name');
            $qb->join('App\Entity\UrlExternal', 'url', 'WITH', 'url.itemId = c.personIdName')
               ->join('url.authority', 'auth')
               ->andWhere('auth.urlNameFormatter in (:valFctUrl)')
               ->setParameter('valFctUrl', $valFctUrl);
        }

        return $qb;
    }

    public function findPrioOne($id) {
        $qb = $this->createQueryBuilder('c')
                   ->select('c, p')
                   ->join('App\Entity\Person', 'p', 'WITH', 'c.personIdName = p.id')
                   ->andWhere('c.personIdName = :id')
                   ->andWhere('c.prioRole = 1')
                   ->setParameter('id', $id);

        $query = $qb->getQuery();
        $result = $query->getResult();
        $canon = null;
        if ($result) {
            $canon = $result[0];
            $canon->setPerson($result[1]);
        }

    }

    /**
     * find canon with person via personIdName where prioRole == 1
     *
     * @return CanonLookup
     */
    public function findPrioRoleOne($id) {
        // person can not be retrieved via association, because pesonIdName is not unique
        $qb = $this->createQueryBuilder('c')
                   ->select('c, p')
                   ->join('App\Entity\Person', 'p', 'WITH', 'c.personIdName = p.id')
                   ->andWhere('c.personIdName = :id')
                   ->orderBy('c.prioRole')
                   ->setParameter('id', $id);

        $query = $qb->getQuery();

        $result = $query->getResult();

        $canon = null;
        if ($result) {
            $canon = $result[0];
            $canon->setPerson($result[1]);
            $canon->setHasSibling(count($result) > 2);
        }
        return $canon;

    }

    public function getRoleIds($id) {
        $qb = $this->createQueryBuilder('c')
                   ->select('c.personIdRole')
                   ->andWhere('c.personIdName = :id')
                   ->addOrderBy('c.prioRole')
                   ->setParameter('id', $id);

        $query = $qb->getQuery();
        return array_column($query->getResult(), 'personIdRole');
    }


    /**
     * 2022-05-04 obsolete?
     * find canons with person via personIdName and personIdRole, respectively
     * @return CanonLookup[]
     */
    // public function findWithPerson($id) {
    //     $qb = $this->createQueryBuilder('c')
    //                ->select('c')
    //                ->andWhere('c.personIdName = :id')
    //                ->addOrderBy('c.prioRole')
    //                ->setParameter('id', $id);

    //     $query = $qb->getQuery();
    //     $result = $query->getResult();

    //     $em = $this->getEntityManager();

    //     $personRepository = $em->getRepository(Person::class);
    //     $personRoleRepository = $em->getRepository(PersonRole::class);

    //     foreach ($result as $r) {
    //         $person_id_name = $r->getPersonIdName();
    //         $person = $personRepository->find($person_id_name);
    //         $r->setPerson($person);
    //         // 2022-05-04 TODO
    //         $personRole = $personRepository->findWithOffice($r->getPersonIdRole());
    //         $personRepository->addReferenceVolumes($personRole);
    //         $r->setPersonRole($personRole);
    //     }

    //     return $result;

    // }

    public function findPersonIdName($id) {
        $qb = $this->createQueryBuilder('c')
                   ->select('c.personIdName')
                   ->andWhere('c.personIdRole = :id')
                   ->setParameter('id', $id);

        $query = $qb->getQuery();
        $result = $query->getResult();

        $personIdName = null;
        if ($result) {
            $personIdName = $result[0]['personIdName'];
        }

        return $personIdName;
    }

    /**
     * find person with offices and sort by date
     */
    public function findWithOfficesByModel($model) {
        // c is subject to query conditions, c_sel collects data
        $qb = $this->createQueryBuilder('c')
                   ->select('p_sel, i_sel')
                   ->innerjoin('\App\Entity\CanonLookup', 'c_sel', 'WITH', 'c_sel.personIdName = c.personIdName')
                   ->innerjoin('\App\Entity\Person', 'p_sel', 'WITH', 'p_sel.id = c_sel.personIdName')
                   ->innerjoin('\App\Entity\Item', 'i_sel', 'WITH', 'i_sel.id = c_sel.personIdRole')
                   ->innerjoin('c.canonSort', 'c_sort')
                   ->addOrderBy('c_sort.dateSortKey', 'ASC');

        $this->addCanonConditions($qb, $model);

        $query = $qb->getQuery();
        $result = $query->getResult();

        return $result;
    }

    /**
     * find references
     */
    public function findReferencesByModel($model, $item_type_id) {
        $qb = $this->createQueryBuilder('c')
                   ->select('distinct v')
                   ->innerjoin('\App\Entity\Item', 'i_sel', 'WITH', 'i_sel.id = c.personIdRole')
                   ->innerjoin('i_sel.reference', 'item_ref')
                   ->innerjoin('\App\Entity\ReferenceVolume',
                               'v',
                               'WITH',
                               'v.itemTypeId = item_ref.itemTypeId AND v.referenceId = item_ref.referenceId')
                   ->andWhere('i_sel.itemTypeId = :item_type_id')
                   ->setParameter('item_type_id', $item_type_id)
                   ->addOrderBy('v.displayOrder', 'ASC');

        $this->addCanonConditions($qb, $model);

        $query = $qb->getQuery();
        $result = $query->getResult();

        return $result;
    }


    /**
     * countCanonDomstift($model)
     *
     * return array of domstift names related to a person's role (used for facet)
     */
    public function countCanonDomstift($model) {
        // $model should not contain domstift facet

        // all in one query (time consuming)
        // $qb = $this->createQueryBuilder('c')
        //            ->select('inst_count.nameShort AS name, COUNT(DISTINCT(c.personIdName)) AS n')
        //            ->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = c.personIdRole')
        //            ->join('pr_count.institution', 'inst_count')
        //            ->andWhere("inst_count.itemTypeId = :itemTypeDomstift")
        //            ->setParameter('itemTypeDomstift', Item::ITEM_TYPE_ID["Domstift"]);

        $em = $this->getEntityManager();
        $qbi = $em->getRepository(Institution::class)
                  ->createQueryBuilder('i')
                  ->select('i.id AS id, i.nameShort AS name')
                  ->andWhere('i.itemTypeId = :itemTypeDomstift')
                  ->setParameter('itemTypeDomstift', Item::ITEM_TYPE_ID['Domstift'])
                  ->addOrderBy('i.nameShort');

        $domstift_list = $qbi->getQuery()->getResult();

        $qb = $this->createQueryBuilder('c')
                   ->select('pr_count.institutionId AS id, COUNT(DISTINCT(c.personIdName)) AS n')
                   ->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = c.personIdRole')
                   ->andWhere('pr_count.institutionId IN (:instId_list)')
                   ->setParameter('instId_list', array_column($domstift_list, 'id'));

        $this->addCanonConditions($qb, $model);
        $this->addCanonFacets($qb, $model);

        $qb->groupBy('pr_count.institutionId');

        $query = $qb->getQuery();
        $count_list = $query->getResult();

        // add names to the list of domstifte

        $count_simple_list = array();
        foreach($count_list as $c_loop) {
            $count_simple_list[$c_loop['id']] = $c_loop['n'];
        }
        $result = array();
        // loop over $name_list to keep order
        foreach($domstift_list as $d_loop) {
            $id = $d_loop['id'];
            if (array_key_exists($id, $count_simple_list)) {
                $result[] = [
                    'name' => $d_loop['name'],
                    'n' => $count_simple_list[$id],
                ];
            }
        }

        return $result;
    }

    /**
     * countCanonOffice($model)
     *
     * return array of role names (used for facet)
     */
    public function countCanonOffice($model) {
        // $model should not contain office facet

        $qb = $this->createQueryBuilder('c')
                   ->select('role_count.name AS name, COUNT(DISTINCT(c.personIdName)) AS n')
                   ->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = c.personIdRole')
                   ->join('pr_count.role', 'role_count');

        $this->addCanonConditions($qb, $model);
        $this->addCanonFacets($qb, $model);

        $qb->groupBy('role_count.name');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * countCanonPlace($model)
     *
     * return array of places related to a person's role (used for facet)
     */
    public function countCanonPlace($model) {
        // $model should not contain place facet

        $qb = $this->createQueryBuilder('c')
                   ->select('ip_count.placeName AS name, COUNT(DISTINCT(c.personIdName)) AS n')
                   ->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = c.personIdRole')
                   ->join('pr_count.institution', 'inst_count')
                   ->join('inst_count.institutionPlace', 'ip_count')
                   ->andWhere('ip_count.placeName IS NOT NULL');

        $this->addCanonConditions($qb, $model);
        $this->addCanonFacets($qb, $model);

        $qb->groupBy('ip_count.placeName');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * countCanonUrl($model)
     *
     * return array of urls (used for facet)
     */
    public function countCanonUrl($model) {
        // $model should not contain url facet

        $qb = $this->createQueryBuilder('c')
                   ->select('auth.urlNameFormatter AS name, COUNT(DISTINCT(c.personIdName)) AS n')
                   ->join('App\Entity\UrlExternal', 'url', 'WITH', 'url.itemId = c.personIdName')
                   ->join('url.authority', 'auth');

        $this->addCanonConditions($qb, $model);
        $this->addCanonFacets($qb, $model);

        $qb->groupBy('auth.urlNameFormatter');

        $query = $qb->getQuery();
        $result = $query->getResult();
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
                   ->setParameter('name', '%'.$name.'%');

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
                   ->setParameter('name', '%'.$name.'%');

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
                   ->setParameter('name', '%'.$name.'%');

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
                   ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }





}
