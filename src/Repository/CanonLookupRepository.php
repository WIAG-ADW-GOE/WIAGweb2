<?php

namespace App\Repository;

use App\Entity\CanonLookup;
use App\Entity\Item;
use App\Entity\ItemReference;
use App\Entity\ReferenceVolume;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\InstitutionPlace;
use App\Entity\Institution;
use App\Entity\Authority;
use App\Entity\UrlExternal;
use App\Service\UtilService;

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

    /**
     *
     */
    public function canonIds($model, $limit = 0, $offset = 0) {
        $result = null;

        $item_type_id = Item::ITEM_TYPE_ID['Domherr']['id'];
        $domstift_type_id = Item::ITEM_TYPE_ID['Domstift']['id'];

        $domstift = $model->institution;
        $office = $model->office;
        $place = $model->place;
        $year = $model->year;
        $name = $model->name;
        $someid = $model->someid;

        // c (group by and query conditions)
        // p_prio, r (sort) use person with prioRole 1
        // p_name with c.prioRole = 1: we need each person only once
        $qb = $this->createQueryBuilder('c')
                   ->join('App\Entity\CanonLookup', 'c_prio', 'WITH', 'c_prio.personIdName = c.personIdName AND c_prio.prioRole = 1')
                   ->join('App\Entity\PersonRole', 'r', 'WITH', 'r.personId = c.personIdRole')
                   ->join('App\Entity\Person', 'p_role', 'WITH', 'p_role.id = c_prio.personIdRole')
                   ->join('App\Entity\Person', 'p_name', 'WITH', 'p_name.id = c_prio.personIdName');

        // table canon_lookup only contains entries with status 'online'
        $this->addCanonConditions($qb, $model, false);
        $this->addCanonFacets($qb, $model);

        // sort in an extra step, see below
        if ($domstift) {
            // Use institution and dateSortKey of all roles for sorting, which is not transparent for the user
            // if a role is present in 'Domherr GS' but not in 'Domherr'.
            // Join domstift via role.
            $qb->join('App\Entity\Institution', 'domstift', 'WITH', 'domstift.id = r.institutionId and domstift.itemTypeId = :domstift_type_id')
               ->setParameter('domstift_type_id', $domstift_type_id);
            $qb->select('c.personIdName, p_name.givenname, (CASE WHEN p_name.familyname IS NULL THEN 0 ELSE 1 END)  as hasFamilyname, p_name.familyname, min(domstift.nameShort) as sort_domstift, min(r.dateSortKey) as dateSortKey');
        }
        elseif ($model->isEmpty() || $office || $name || $year || $someid) {
            // Join domstift (sorting) independently from role (query condition).
            $qb->leftjoin('App\Entity\PersonRole', 'r_sort', 'WITH', 'r_sort.personId = p_role.id')
               ->leftjoin('App\Entity\Institution', 'domstift_sort', 'WITH', 'domstift_sort.id = r_sort.institutionId')
               ->select('c.personIdName, p_name.givenname, (CASE WHEN p_name.familyname IS NULL THEN 0 ELSE 1 END)  as hasFamilyname, p_name.familyname, min(domstift_sort.nameShort) as sort_domstift, min(r_sort.dateSortKey) as dateSortKey');
        } elseif ($place) {
            $qb->select('c.personIdName, min(ip.placeName) as placeName, p_name.givenname, p_name.familyname, min(r.dateSortKey) as dateSortKey');
        }

        $qb->groupBy('c.personIdName');

        $query = $qb->getQuery();

        $result = $query->getResult();

        // sort
        // doctrine min function returns a string
        $result = array_map(function($el) {
            $val = $el['dateSortKey'];
            $el['dateSortKey'] = is_null($val) ? $val : intval($val);
            return $el;
        }, $result);

        // NULL is sorted last; the field 'hasFamilyname' overrides this behaviour
        if ($model->isEmpty() || $domstift || $office) {
            $sort_list = ['sort_domstift', 'dateSortKey', 'givenname', 'familyname', 'personIdName'];
        } elseif ($name) {
            $sort_list = ['hasFamilyname', 'familyname',  'givenname', 'sort_domstift', 'dateSortKey', 'personIdName'];
        } elseif ($year) {
            $sort_list = ['dateSortKey', 'sort_domstift', 'familyname', 'givenname', 'personIdName'];
        } elseif ($someid) {
            $sort_list = ['sort_domstift', 'dateSortKey', 'familyname', 'givenname', 'personIdName'];
        } elseif ($place) {
            $sort_list = ['placeName', 'dateSortKey', 'familyname', 'givenname', 'personIdName'];
        }

        $result = UtilService::sortByFieldList($result, $sort_list);

        if ($limit > 0) {
            $result = array_slice($result, $offset, $limit);
        }

        return array_column($result, "personIdName");

    }

    public function canonIdsAll($model, $limit = 0, $offset = 0) {
        $result = null;

        $item_type_id = Item::ITEM_TYPE_ID['Domherr']['id'];
        $domstift_type_id = Item::ITEM_TYPE_ID['Domstift']['id'];

        $domstift = $model->institution;
        $office = $model->office;
        $place = $model->place;
        $year = $model->year;
        $name = $model->name;
        $someid = $model->someid;

        $repository = $this->getEntityManager()->getRepository(Person::class);
        $qb = $repository->createQueryBuilder('p')
                         ->select('p.id')
                         ->andWhere('p.itemTypeId = :item_type_id')
                         ->setParameter('item_type_id', $item_type_id);


        $query = $qb->getQuery();

        $result = $query->getResult();

        return array_column($result, 'id');

    }

    public function addCanonConditions($qb, $model, $add_joins = true) {
        $item_type_id = Item::ITEM_TYPE_ID['Domherr']['id'];
        $domstift_type_id = Item::ITEM_TYPE_ID['Domstift']['id'];

        $domstift = $model->institution;
        $diocese = $model->diocese;
        $office = $model->office;
        $name = $model->name;
        $place = $model->place;
        $year = $model->year;
        $someid = $model->someid;

        // join tables when called for facet counts
        if ($add_joins) {
            if ($domstift) {
                $qb->join('App\Entity\PersonRole', 'r', 'WITH', 'r.personId = c.personIdRole')
                   ->join('App\Entity\Institution', 'domstift', 'WITH', 'domstift.id = r.institutionId and domstift.itemTypeId = :domstift_type_id')
                   ->setParameter('domstift_type_id', $domstift_type_id);
            } elseif ($office) {
                $qb->join('App\Entity\PersonRole', 'r', 'WITH', 'r.personId = c.personIdRole');
            }
        }

        // if a diocese is given via diocese_id, there is also a value for diocese_name
        if ($diocese) {
            $qb ->join('App\Entity\PersonRole', 'r_dioc', 'WITH', 'r_dioc.personId = c.personIdRole')
                ->andWhere("(r_dioc.dioceseName LIKE :paramDiocese ".
                          "OR CONCAT('erzbistum ', r_dioc.dioceseName) LIKE :paramDiocese ".
                          "OR CONCAT('bistum ', r_dioc.dioceseName) LIKE :paramDiocese) ")
               ->setParameter('paramDiocese', '%'.$diocese.'%');
        }

        if ($domstift) {
            // apply query criteria to all roles and join with canon_lookup
            $qb->andWhere('domstift.name LIKE :q_domstift')
               ->setParameter('q_domstift', '%'.$domstift.'%');
            // combine queries for domstift and office at the level of PersonRole
            if ($office) {
                $qb->leftjoin('r.role', 'role_type')
                   ->andWhere('r.roleName LIKE :q_office OR role_type.name LIKE :q_office')
                   ->setParameter('q_office', '%'.$office.'%');
            }
        } elseif ($office) {
            $qb->leftjoin('r.role', 'role_type')
               ->andWhere('r.roleName LIKE :q_office OR role_type.name LIKE :q_office')
               ->setParameter('q_office', '%'.$office.'%');
        }

        if ($place) {
            // Join places independently from role (other query condition)
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
            $qb->join('App\Entity\NameLookup', 'name_lookup', 'WITH', 'name_lookup.personId = c.personIdRole');
            // require that every word of the search query occurs in the name, regardless of the order
            $q_list = UtilService::nameQueryComponents($name);
            foreach($q_list as $key => $q_name) {
                $qb->andWhere('name_lookup.gnPrefixFn LIKE :q_name_'.$key)
                   ->setParameter('q_name_'.$key, '%'.trim($q_name).'%');
            }
        }

        if ($someid || $year) {
            // year and id are linked now via p_all
            $qb->join('App\Entity\Person', 'p_id_year', 'WITH', 'p_id_year.id = c.personIdRole');
            if ($someid) {

                // look for $someid in merged ancestors
                $itemRepository = $this->getEntityManager()->getRepository(Item::class);
                $with_id_in_source = $model->isEdit;
                $list_size_max = 200;
                $descendant_id_list = $itemRepository->findIdByAncestor(
                    $someid,
                    $with_id_in_source,
                    $list_size_max,
                );

                // look for $someid in external links
                $uextRepository = $this->getEntityManager()->getRepository(UrlExternal::class);
                $uext_id_list = $uextRepository->findIdBySomeNormUrl(
                    $someid,
                    $list_size_max
                );

                $q_id_list = array_unique(array_merge($descendant_id_list, $uext_id_list));

                $qb->join('p_id_year.item', 'item')
                   ->andWhere("item.id in (:q_id_list)")
                   ->setParameter('q_id_list', $q_id_list);
            }
            if ($year) {
                $qb->andWhere("p_id_year.dateMin - :mgnyear < :q_year ".
                              " AND :q_year < p_id_year.dateMax + :mgnyear")
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

        $facetDomstift = $model->facetInstitution;
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


    /**
     * collect data where personIdName is in $id_list
     * @return CanonLookup[]
     */
    public function findList($id_list, $prio_role = null) {
        $qb = $this->createQueryBuilder('c')
                   ->select('c, p, i, ref, urlext, i_prop, role, role_type, institution')
                   ->join('c.person', 'p')
                   ->join('p.item', 'i') # avoid query in twig ...
                   ->leftjoin('i.reference', 'ref')
                   ->leftjoin('i.urlExternal', 'urlext')
                   ->leftjoin('i.itemProperty', 'i_prop')
                   ->leftjoin('p.role', 'role')
                   ->leftjoin('role.role', 'role_type')
                   ->leftjoin('role.institution', 'institution')
                   ->andWhere('c.personIdName in (:id_list)')
                   ->addOrderBy('role.dateSortKey')
                   ->addOrderBy('role.id')
                   ->setParameter('id_list', $id_list);

        if (!is_null($prio_role)) {
            $qb->andWhere('c.prioRole = :prio_role')
               ->setParameter('prio_role', $prio_role);
        }

        $query = $qb->getQuery();
        $canon_list = $query->getResult();

        $em = $this->getEntityManager();

        // set $canon->personName
        // with sibling and urlExternal
        $em->getRepository(Person::class)->setPersonName($canon_list);

        // set $canon->otherSource
        $this->setOtherSource($canon_list);

        $person_list = $this->getPersonList($canon_list);

        $item_list = array_map(function($p) {return $p->getItem();}, $person_list);
        // set reference volumes
        $em->getRepository(ReferenceVolume::class)->setReferenceVolume($item_list);

        // set authorities
        $em->getRepository(Authority::class)->setAuthority($item_list);

        // set place names
        $role_list = $this->getRoleList($canon_list);
        $em->getRepository(PersonRole::class)->setPlaceNameInRole($role_list);

        $canon_list = UtilService::reorder($canon_list, $id_list, "personIdName");

        return $canon_list;

    }

    /**
     *
     */
    public function getPersonList($canon_list) {
        $person_list = array_map(function($el) {
            # array_merge accepts only an array
            return $el->getPerson();
        }, $canon_list);

        return $person_list;
    }

    /**
     *
     */
    public function getRoleList($canon_list) {
        $role_list = array_map(function($el) {
            # array_merge accepts only an array
            return $el->getPerson()->getRole()->toArray();
        }, $canon_list);
        $role_list = array_merge(...$role_list);

        return $role_list;
    }

    /**
     * set flag $canon->otherSource
     */
    public function setOtherSource($canon_list) {

        $id_canon_map = array();
        foreach ($canon_list as $canon) {
            $id_canon_map[$canon->getId()] = $canon;
        }

        $qb = $this->createQueryBuilder('c')
                   ->select('c.id, count(c_all) as n')
                   ->join('\App\Entity\CanonLookup', 'c_all', 'WITH', 'c_all.personIdName = c.personIdName')
                   ->andWhere('c.id in (:id_list)')
                   ->addGroupBy('c.personIdName')
                   ->setParameter('id_list', array_keys($id_canon_map));

        $query = $qb->getQuery();
        $result = $query->getResult();

        foreach ($result as $r_loop) {
            $canon = $id_canon_map[$r_loop['id']];
            $canon->setOtherSource($r_loop['n'] > 1);
        }

        return null;
    }

    /**
     * 2023-07-18 obsolete?
     */
    public function findWithOffice_legacy($personIdName) {
        $qb = $this->createQueryBuilder('c')
                   ->select('c, p_head, p, role, role_type, institution')
                   ->join('App\Entity\Person', 'p_head', 'WITH', 'c.personIdName = p_head.id')
                   ->join('c.person', 'p')
                   ->join('p.role', 'role') # avoid query in twig
                   ->join('role.role', 'role_type') # avoid query in twig
                   ->join('role.institution', 'institution') # avoid query in twig
                   ->andWhere('c.personIdName = :personIdName')
                   ->setParameter('personIdName', $personIdName)
                   ->addOrderBy('role.dateSortKey')
                   ->setParameter('id_list', $id_list);

        $query = $qb->getQuery();

        $result = $query->getResult();
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
     * 2023-07-18 obsolete?
     * find person with offices and sort by date
     */
    public function findWithOfficesByModel_legacy($model) {
        // c is subject to query conditions, c_sel collects data
        // to select person and item and to combine them in the controller is only possible because they are unique (?)
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
     *
     */
    public function referenceListByItemType($id_list, $item_type_list) {
        $referenceRepository = $this->getEntityManager()->getRepository(ItemReference::class);
        $qb = $this->createQueryBuilder('c')
                   ->select('distinct(c.personIdRole) as id')
                   ->join('\App\Entity\Item', 'i', 'WITH', 'i.id = c.personIdRole and i.itemTypeId in (:item_type_list)')
                   ->andWhere('c.personIdName in (:id_list)')
                   ->setParameter('id_list', $id_list)
                   ->setParameter('item_type_list', $item_type_list);
        $query = $qb->getQuery();
        $id_list_2 = $query->getResult();

        $reference_list = null;
        if (!is_null($id_list_2)) {
            $reference_list = $referenceRepository->findVolumeByItemIdList($id_list_2);
        }

        return $reference_list;
    }

    /**
     * find references
     * 2023-06-27 obsolete?! see referenceListByItemType()
     */
    public function findReferencesByModel_legacy($model, $item_type_id) {
        $qb = $this->createQueryBuilder('c')
                   ->select('distinct v')
                   ->innerjoin('\App\Entity\CanonLookup', 'c_sel', 'WITH', 'c_sel.personIdName = c.personIdName')
                   ->innerjoin('\App\Entity\ItemReference', 'item_ref', 'WITH', 'item_ref.itemId = c_sel.personIdRole')
                   ->innerjoin('\App\Entity\ReferenceVolume',
                               'v',
                               'WITH',
                               'v.referenceId = item_ref.referenceId')
                   ->andWhere('item_ref.itemTypeId = :item_type_id')
                   ->setParameter('item_type_id', $item_type_id);


        if ($item_type_id = Item::ITEM_TYPE_ID['Domherr GS']
            || $item_type_id = Item::ITEM_TYPE_ID['Bischof GS']) {
            $qb->addOrderBy('v.displayOrder', 'ASC')
               ->addOrderBy('v.titleShort', 'ASC')
               ->addOrderBy('v.itemTypeId', 'ASC')
               ->addOrderBy('v.referenceId', 'ASC');
        } else {
            $qb->addOrderBy('v.titleShort', 'ASC')
               ->addOrderBy('v.itemTypeId', 'ASC')
               ->addOrderBy('v.displayOrder', 'ASC')
               ->addOrderBy('v.referenceId', 'ASC');
        }

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

        $em = $this->getEntityManager();
        $qbi = $em->getRepository(Institution::class)
                  ->createQueryBuilder('i')
                  ->select('i.id AS id, i.nameShort AS name')
                  ->andWhere('i.itemTypeId = :itemTypeDomstift')
                  ->setParameter('itemTypeDomstift', Item::ITEM_TYPE_ID['Domstift']['id'])
                  ->addOrderBy('i.nameShort');

        $domstift_list = $qbi->getQuery()->getResult();

        $qb = $this->createQueryBuilder('c')
                   ->select('pr_count.institutionId AS id, COUNT(DISTINCT(c.personIdName)) AS n')
            // ->join('App\Entity\CanonLookup', 'cfct', 'WITH', 'cfct.personIdName = c.personIdName')
                   ->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = c.personIdRole')
                   ->andWhere('pr_count.institutionId IN (:instId_list)')
                   ->setParameter('instId_list', array_column($domstift_list, 'id'));

        $this->addCanonConditions($qb, $model, true);
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
            //->join('App\Entity\CanonLookup', 'cfct', 'WITH', 'cfct.personIdName = c.personIdName')
                   ->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = c.personIdRole')
                   ->join('pr_count.role', 'role_count');

        $this->addCanonConditions($qb, $model, true);
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
            // ->join('App\Entity\CanonLookup', 'cfct', 'WITH', 'cfct.personIdName = c.personIdName')
                   ->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = c.personIdRole')
                   ->join('pr_count.institution', 'inst_count')
                   ->join('App\Entity\InstitutionPlace', 'ip_count', 'WITH',
                          'pr_count.institutionId = ip_count.institutionId '.
                          'AND ( '.
                          'pr_count.numDateBegin IS NULL AND pr_count.numDateEnd IS NULL '.
                          'OR (ip_count.numDateBegin < pr_count.numDateBegin AND pr_count.numDateBegin < ip_count.numDateEnd) '.
                          'OR (ip_count.numDateBegin < pr_count.numDateEnd AND pr_count.numDateEnd < ip_count.numDateEnd) '.
                          'OR (pr_count.numDateBegin < ip_count.numDateBegin AND ip_count.numDateBegin < pr_count.numDateEnd) '.
                          'OR (pr_count.numDateBegin < ip_count.numDateEnd AND ip_count.numDateEnd < pr_count.numDateEnd))')
                   ->andWhere('ip_count.placeName IS NOT NULL');

        $this->addCanonConditions($qb, $model, true);
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
            // ->join('App\Entity\CanonLookup', 'cfct', 'WITH', 'cfct.personIdName = c.personIdName')
                   ->join('App\Entity\UrlExternal', 'url', 'WITH', 'url.itemId = c.personIdName')
                   ->join('url.authority', 'auth');

        $this->addCanonConditions($qb, $model, true);
        $this->addCanonFacets($qb, $model);

        $qb->groupBy('auth.urlNameFormatter');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * clear entries related to $id_list, they may be restored by insertByListMayBe
     */
    public function clearByIdRole($id_list) {
        $entityManager = $this->getEntityManager();
        $qb = $this->createQueryBuilder('c')
                   ->andWhere ('c.personIdRole in (:id_list)')
                   ->setParameter('id_list', $id_list);
        $canon_lookup_list = $qb->getQuery()->getResult();
        $n_del = count($canon_lookup_list);
        foreach ($canon_lookup_list as $canon_lookup_del) {
            $entityManager->remove($canon_lookup_del);
        }
        $entityManager->flush();
        return ($n_del);
    }



    private function addCanon($person) {
        $n_persist = 0;
        $em = $this->getEntityManager();
        $c2 = null;
        $c3 = null;

        $c1 = new CanonLookup();
        $c1->setPerson($person); // corresponds to person_id_role
        $c1->setPrioRole(1);
        $person_id_name = $person->getId(); // may be reset, if there is a bishop

        // gs
        $c2 = $this->newCanonGS($person);

        // ep
        $c3 = $this->newCanonEP($person);

        // find gs via ep
        if (!$c2 and $c3) {
            $c2 = $this->newCanonGS($c3->getPerson());
        }


        // set priority for role ep, set person_id_name
        if ($c3) {
            $prio_role_ep = $c2 ? 3 : 2;
            $c3->setPrioRole($prio_role_ep);
            $person_id_name = $c3->getPerson()->getId();
            $c3->setPersonIdName($person_id_name);
            $em->persist($c3);
            $n_persist += 1;
        }

        $c1->setPersonIdName($person_id_name);
        $em->persist($c1);
        $n_persist += 1;

        if ($c2) {
            $c2->setPersonIdName($person_id_name);
            $em->persist($c2);
            $n_persist += 1;
        }
        $em->flush();
        return $n_persist;
    }

    private function persistForBishop($person) {
        $em = $this->getEntityManager();
        $c2 = null;

        // gs (maybe)
        $c2 = $this->newCanonGS($person);
        if (!$c2) { // bishop needs no entry in canon_lookup
            return 0;
        }

        $c2->setPersonIdName($person->getId());
        $c2->setPrioRole(1);
        $em->persist($c2);

        $c1 = new CanonLookup();
        $c1->setPerson($person); // corresponds to person_id_role
        $c1->setPrioRole(2);
        $c1->setPersonIdName($person->getId());
        $em->persist($c1);
        return 2;
    }


    /**
     * @return object of type CanonLookup if $person refers to Personendatenbank
     */
    private function newCanonGS(Person $person) {
        $gs_auth_id = Authority::ID['GS'];
        $itemRepository = $this->getEntityManager()->getRepository(Item::class);
        $personRepository = $this->getEntityManager()->getRepository(Person::class);

        $gs_gsn = $person->getItem()->getUrlExternalByAuthorityId($gs_auth_id);
        $c_gs = null;
        if ($gs_gsn) {
            // find GS by it's entry in url_external
            $itemTypeId = [
                Item::ITEM_TYPE_ID['Domherr GS']['id'],
                Item::ITEM_TYPE_ID['Bischof GS']['id']
            ];

            $param_is_online = false;
            $gs_cand = $itemRepository->findByUrlExternal($itemTypeId, $gs_gsn, $gs_auth_id, $param_is_online);
            if (count($gs_cand) > 0) {
                $gs = $personRepository->find($gs_cand[0]->getId());
                $c_gs = new CanonLookup();
                $c_gs->setPerson($gs);
                $c_gs->setPrioRole(2);
            }
        }
        return $c_gs;
    }

    /**
     * @return object of type CanonLookup if $person refers to Gatz (WIAG-ID)
     */
    private function newCanonEP(Person $person) {
        $wiag_auth_id = Authority::ID['WIAG-ID'];
        $itemRepository = $this->getEntityManager()->getRepository(Item::class);
        $personRepository = $this->getEntityManager()->getRepository(Person::class);

        $ep_wiag_id = $person->getItem()->getUrlExternalByAuthorityId($wiag_auth_id);
        $c_ep = null;
        if ($ep_wiag_id) {
            // find EP
            $ep_cand = $itemRepository->findBy(['idPublic' => $ep_wiag_id]);
            if (count($ep_cand) > 0 and $ep_cand[0]->getIsOnline()) {
                $ep_id = $ep_cand[0]->getId();
                $ep = $personRepository->find($ep_id);
                $c_ep = new CanonLookup();
                $c_ep->setPerson($ep);
                $person_id_name = $ep_id;
            }
        }
        return $c_ep;
    }

    /**
     * 2023-07-18 not in use; keep it for potential data corrections
     * restore entries for canons from Digitales Personenregister
     * this approach is brute force but avoids complicated procedures to identify relevant changes
     */
    public function addCanonGsGlob() {
        $entityManager = $this->getEntityManager();
        // find existing entries
        $id_canon_gs = Item::ITEM_TYPE_ID['Domherr GS']['id'];
        $qb = $this->createQueryBuilder('c')
                   ->addSelect('c.personIdRole as id')
                   ->join('App\Entity\Item', 'i', 'WITH', 'i.id = c.personIdRole and i.itemTypeId = :id_canon_gs')
                   ->setParameter('id_canon_gs', $id_canon_gs);
        $query = $qb->getQuery();
        $id_current_list = array_column($query->getResult(), 'id');

        // find candidates
        $itemRepository = $entityManager->getRepository(Item::class);
        $qb = $itemRepository->createQueryBuilder('i')
                             ->addSelect('i.id as id')
                             ->andWhere('i.itemTypeId = :id_canon_gs')
                             ->andWhere('i.isOnline = 1') // all of them should be online
                             ->setParameter('id_canon_gs', $id_canon_gs);
        $query = $qb->getQuery();
        $id_candidate_list_all = array_column($query->getResult(), 'id');
        $id_candidate_list = array_diff($id_candidate_list_all, $id_current_list);

        $personRepository = $entityManager->getRepository(Person::class);
        foreach ($id_candidate_list as $id_new) {
            $person = $personRepository->find($id_new);
            $canon_lookup = new CanonLookup();
            $canon_lookup->setPerson($person);
            $canon_lookup->setPersonIdName($id_new);
            $canon_lookup->setPrioRole(1);
            $entityManager->persist($canon_lookup);
        }
        $entityManager->flush();
        return $id_candidate_list;
    }

    /**
     * add entry for an independent canon GS
     */
    public function addCanonGsMayBe(Person $person) {
        $n_persist = 0;

        $entityManager = $this->getEntityManager();

        // is there already/still an entry for this canon?
        $qb = $this->createQueryBuilder('c')
                   ->andWhere('c.personIdRole = :item_id')
                   ->setParameter('item_id', $person->getId());

        $result = $qb->getQuery()->getResult();
        if ($result and count($result) > 0) {
            return $n_persist;
        }


        $canon_lookup = new CanonLookup();
        $canon_lookup->setPerson($person);
        $canon_lookup->setPersonIdName($person->getId());
        $canon_lookup->setPrioRole(1);
        $entityManager->persist($canon_lookup);
        $entityManager->flush();
        $n_persist += 1;

        return $n_persist;
    }

    /**
     * 2023-07-18 not in use; keep it for potential data corrections
     * restore entries for bishops
     * this approach is brute force but avoids complicated procedures to identify relevant changes
     */
    public function addBishopGlob() {
        $entityManager = $this->getEntityManager();
        // find existing entries
        $type_id_bishop = Item::ITEM_TYPE_ID['Bischof']['id'];
        $type_id_canon_gs = Item::ITEM_TYPE_ID['Domherr GS']['id'];
        $qb = $this->createQueryBuilder('c')
                   ->addSelect('c.personIdRole as id')
                   ->join('App\Entity\Item', 'i', 'WITH', 'i.id = c.personIdRole and i.itemTypeId = :type_id_bishop')
                   ->setParameter('type_id_bishop', $type_id_bishop);
        $query = $qb->getQuery();
        $id_current_list = array_column($query->getResult(), 'id');

        // find candidates (only performant with an INDEX on value)
        $urlExtRepository = $entityManager->getRepository(UrlExternal::class);
        $qb = $urlExtRepository->createQueryBuilder('uext')
                               ->addSelect('i.id as id')
                               ->join('uext.item', 'i')
                               ->join('App\Entity\UrlExternal', 'uext_gs', 'WITH', 'uext.value = uext_gs.value')
                               ->join('uext_gs.item', 'i_gs')
                               ->andWhere('uext.authorityId = :auth_gs')
                               ->andWhere('i.itemTypeId = :type_id_bishop')
                               ->andWhere('i_gs.itemTypeId = :type_id_canon_gs')
                               ->andWhere('i.isOnline = 1')
                               ->setParameter('auth_gs', Authority::ID['GS'])
                               ->setParameter('type_id_canon_gs', $type_id_canon_gs)
                               ->setParameter('type_id_bishop', $type_id_bishop);
        $query = $qb->getQuery();
        $id_candidate_list_all = array_column($query->getResult(), 'id');

        $id_candidate_list = array_diff($id_candidate_list_all, $id_current_list);

        $personRepository = $entityManager->getRepository(Person::class);
        $bishop_list = $personRepository->findList($id_candidate_list);

        foreach ($bishop_list as $bishop) {
            $this->persistForBishop($bishop);
        }
        $entityManager->flush();
        return $id_candidate_list;
    }


    /**
     * restore entry for bishop
     */
    public function addBishopMayBe(Person $person) {

        // is an entry for this bishop already/still there?
        $qb = $this->createQueryBuilder('c')
                   ->andWhere('c.personIdRole = :item_id')
                   ->setParameter('item_id', $person->getId());

        $result = $qb->getQuery()->getResult();
        $n_persist = 0;
        if ($result and count($result) > 0) {
            return $n_persist;
        }

        $n_persist = $this->persistForBishop($person);
        $this->getEntityManager()->flush();
    }

    /**
     *
     */
    public function insertByListMayBe($id_list) {
        $personRepository = $this->getEntityManager()->getRepository(Person::class);
        $person_list = $personRepository->findList($id_list);

        // canons
        foreach ($person_list as $person) {
            if ($person->getItem()->getIsOnline()) {
                if ($person->getItem()->getItemTypeId() == Item::ITEM_TYPE_ID['Domherr']['id']) {
                    $this->addCanon($person);
                }
            }
        }

        // bishops
        foreach ($person_list as $person) {
            if ($person->getItem()->getIsOnline()) {
                if ($person->getItem()->getItemTypeId() == Item::ITEM_TYPE_ID['Bischof']['id']) {
                    $this->addBishopMayBe($person);
                }
            }
        }

        // canons GS
        foreach ($person_list as $person) {
            if ($person->getItem()->getIsOnline()) {
                if ($person->getItem()->getItemTypeId() == Item::ITEM_TYPE_ID['Domherr GS']['id']) {
                    $this->addCanonGsMayBe($person);
                }
            }
        }

    }

}
