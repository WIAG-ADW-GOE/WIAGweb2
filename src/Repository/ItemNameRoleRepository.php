<?php

namespace App\Repository;

use App\Entity\ItemNameRole;
use App\Entity\Person;
use App\Entity\Institution;
use App\Service\UtilService;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemNameRole>
 *
 * @method ItemNameRole|null find($id, $lockMode = null, $lockVersion = null)
 * @method ItemNameRole|null findOneBy(array $criteria, array $orderBy = null)
 * @method ItemNameRole[]    findAll()
 * @method ItemNameRole[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemNameRoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemNameRole::class);
    }

    public function add(ItemNameRole $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ItemNameRole $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return ItemNameRole[] Returns an array of ItemNameRole objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('i.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ItemNameRole
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }


    /**
     *
     */
    public function findPersonIds($model, $limit = 0, $offset = 0) {
        $result = null;

        $corpus = $model->corpus;
        $diocese = $model->diocese;
        $domstift = $model->domstift;
        $office = $model->office;
        $place = $model->place;
        $year = $model->year;
        $name = $model->name;
        $someid = $model->someid;

        // avoid to join the same table twice
        $joined_list = array();
        // p_name is required for sorting
        $qb = $this->createQueryBuilder('inr')
                   ->join('App\Entity\Person', 'p_name', 'WITH', 'p_name.id = inr.itemIdName');
        $joined_list[] = 'p_name';

        // pr is required for sorting
        // queries for bishops only consider Gatz-offices (query, sort, facet)
        if ($corpus == 'epc') {
            $qb->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = inr.itemIdName');
        } else {
            $qb->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = inr.itemIdRole');
        }
        $joined_list[] = 'pr';

        $this->addConditions($qb, $model, $joined_list);
        $this->addFacets($qb, $model);

        // do sorting in an extra step, see below
        $qb->select('inr.itemIdName,'.
                    'p_name.givenname,'.
                    '(CASE WHEN p_name.familyname IS NULL THEN 0 ELSE 1 END)  as hasFamilyname,'.
                    'p_name.familyname,'.
                    'min(pr.dioceseName) as diocese_name,'.
                    'min(pr.dateSortKey) as dateSortKey');

        if ($corpus == 'can') {
            if ($domstift) { // restrict sorting to matches
                $qb->join('App\Entity\Institution', 'domstift_sort',
                          'WITH', "domstift_sort.id = pr.institutionId and domstift_sort.corpusId = 'cap'");
            } else {
                $qb->leftjoin('App\Entity\PersonRole', 'pr_domstift',
                              'WITH', 'pr_domstift.personId = pr.personId')
                   ->leftjoin('App\Entity\Institution', 'domstift_sort',
                              'WITH', "domstift_sort.id = pr_domstift.institutionId and domstift_sort.corpusId = 'cap'");
            }
            $qb->addSelect('min(domstift_sort.nameShort) as domstift_name');
        }

        if ($place) {
            $qb->addSelect('min(inst_place.placeName) as place_name');
        }


        $qb->groupBy('inr.itemIdName');

        $query = $qb->getQuery();
        $result = $query->getResult();

        // sort
        // doctrine min function returns a string; convert it to int
        $result = array_map(function($el) {
            $val = $el['dateSortKey'];
            $el['dateSortKey'] = is_null($val) ? $val : intval($val);
            return $el;
        }, $result);

        // NULL is sorted last; the field 'hasFamilyname' overrides this behaviour
        if ($model->isEmpty()) {
            if ($corpus == 'can') {
                $sort_list = ['domstift_name', 'dateSortKey', 'givenname', 'familyname', 'itemIdName'];
            } else {
                $sort_list = ['hasFamilyname', 'familyname',  'givenname', 'dateSortKey', 'itemIdName'];
            }
        } elseif ($domstift) {
            $sort_list = ['domstift_name', 'dateSortKey', 'givenname', 'familyname', 'itemIdName'];
        } elseif ($diocese) {
            $sort_list = ['diocese_name', 'dateSortKey', 'givenname', 'familyname', 'itemIdName'];
        } elseif ($office) {
            if ($corpus == 'can') {
                $sort_list = ['domstift_name', 'dateSortKey', 'givenname', 'familyname', 'itemIdName'];
            } else {
                $sort_list = ['diocese_name', 'dateSortKey', 'givenname', 'familyname', 'itemIdName'];
            }
        } elseif ($name) {
            if ($corpus == 'can') {
                $sort_list = ['hasFamilyname', 'familyname',  'givenname', 'domstift_name', 'dateSortKey', 'itemIdName'];
            } else {
                $sort_list = ['hasFamilyname', 'familyname',  'givenname', 'dateSortKey', 'itemIdName'];
            }
        } elseif ($year) {
            $sort_list = ['dateSortKey', 'familyname', 'givenname', 'itemIdName'];
        } elseif ($someid) {
            $sort_list = ['dateSortKey', 'familyname', 'givenname', 'itemIdName'];
        } elseif ($place) {
            $sort_list = ['place_name', 'dateSortKey', 'familyname', 'givenname', 'itemIdName'];
        }

        $result = UtilService::sortByFieldList($result, $sort_list);

        if ($limit > 0) {
            $result = array_slice($result, $offset, $limit);
        }

        return array_column($result, "itemIdName");

    }

    public function addConditions($qb, $model, $joined_list = array()) {

        $corpus = $model->corpus;
        $domstift = $model->domstift;
        $diocese = $model->diocese;
        $office = $model->office;
        $name = $model->name;
        $place = $model->place;
        $year = $model->year;
        $someid = $model->someid;

        // include in queries for corpus 'can' also canons from the Digitales Personenregister
        // bishops from Digitales Personenregister have no independent entries in item_name_role,
        // however their offices are visible in detail view (query via item_name_role)
        $query_corpus = [
            'epc' => ['epc'],
            'can' => ['can', 'dreg'],
        ][$corpus];

        $qb->join('App\Entity\ItemCorpus', 'c', 'WITH', 'c.itemId = inr.itemIdName AND c.corpusId in (:corpus)')
           ->setParameter('corpus', $query_corpus);

        if (!in_array('p_name', $joined_list) and ($name)) {
            $qb->join('App\Entity\Person', 'p_name', 'WITH', 'p_name.id = inr.itemIdName');
        }

        // queries for bishops only consider Gatz-offices (query, sort, facet)
        if (!in_array('pr', $joined_list) and ($office or $domstift or $diocese or $place)) {
            if ($corpus == 'epc') {
                $qb->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = inr.itemIdName');
            } else {
                $qb->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = inr.itemIdRole');
            }
        }

        if ($domstift) {
            $qb->join('App\Entity\Institution', 'domstift', 'WITH',
                      "domstift.id = pr.institutionId and domstift.corpusId = 'cap'");
        }

        if ($office) {
            $qb->leftjoin('pr.role', 'role')
               ->andWhere('pr.roleName LIKE :q_office OR role.name LIKE :q_office')
               ->setParameter('q_office', '%'.$office.'%');
        }

        if ($place) {
            $qb->join('App\Entity\PersonRole', 'pr_place', 'WITH', 'pr_place.personId = pr.personId')
               ->join('App\Entity\InstitutionPlace', 'inst_place', 'WITH',
                      'pr_place.institutionId = inst_place.institutionId '.
                      'AND ( '.
                      'pr_place.numDateBegin IS NULL AND pr_place.numDateEnd IS NULL '.
                      'OR (inst_place.numDateBegin < pr_place.numDateBegin AND pr_place.numDateBegin < inst_place.numDateEnd) '.
                      'OR (inst_place.numDateBegin < pr_place.numDateEnd AND pr_place.numDateEnd < inst_place.numDateEnd) '.
                      'OR (pr_place.numDateBegin < inst_place.numDateBegin AND inst_place.numDateBegin < pr_place.numDateEnd) '.
                      'OR (pr_place.numDateBegin < inst_place.numDateEnd AND inst_place.numDateEnd < pr_place.numDateEnd))');
        }

        // add conditions

        // $domstift is AND-combined with $office because both are joined via 'pr'
        if ($domstift) {
            $qb->andWhere('domstift.name LIKE :q_domstift')
               ->setParameter('q_domstift', '%'.$domstift.'%');
        }

        if ($diocese) {
            // if a diocese is given via diocese_id, there is also a value for diocese_name
            // do not AND-combine diocese and domstift
            if ($domstift) {
                $qb->join('App\Entity\PersonRole', 'pr_dioc', 'WITH', 'pr_dioc.personId = inr.itemIdRole')
                   ->andWhere("(pr_dioc.dioceseName LIKE :q_diocese ".
                              "OR CONCAT('erzbistum ', pr_dioc.dioceseName) LIKE :q_diocese ".
                              "OR CONCAT('bistum ', pr_dioc.dioceseName) LIKE :q_diocese) ");
            } else {
                $qb ->andWhere("(pr.dioceseName LIKE :q_diocese ".
                               "OR CONCAT('erzbistum ', pr.dioceseName) LIKE :q_diocese ".
                               "OR CONCAT('bistum ', pr.dioceseName) LIKE :q_diocese) ");
            }
            $qb->setParameter('q_diocese', '%'.$diocese.'%');
        }

        if ($place) {
            $qb->andWhere('inst_place.placeName LIKE :q_place')
               ->setParameter('q_place', '%'.$place.'%');
        }

        if ($name) {
            // search alos for name in 'Digitales Personenregister'
            $qb->join('App\Entity\NameLookup', 'name_lookup', 'WITH', 'name_lookup.personId = inr.itemIdRole');
            // require that every word of the search query occurs in the name, regardless of the order
            $q_list = UtilService::nameQueryComponents($name);
            foreach($q_list as $key => $q_name) {
                $qb->andWhere('name_lookup.gnPrefixFn LIKE :q_name_'.$key)
                   ->setParameter('q_name_'.$key, '%'.trim($q_name).'%');
            }
        }

        if ($someid || $year) {
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

                $qb->andWhere("inr.itemIdRole in (:q_id_list)")
                   ->setParameter('q_id_list', $q_id_list);
            }
            if ($year) {
                // we have no join to person at the level of ItemNameRole.itemIdRole so far
                $qb->join('App\Entity\Person', 'p_year', 'WITH', 'p_year.id = inr.itemIdRole');
                $qb->andWhere("p_year.dateMin - :mgnyear < :q_year ".
                              " AND :q_year < p_year.dateMax + :mgnyear")
                   ->setParameter('mgnyear', Person::MARGINYEAR)
                   ->setParameter('q_year', $year);
            }
        }

        return $qb;
    }

    /**
     * add conditions set by facets
     */
    private function addFacets($qb, $model) {

        $facetDomstift = $model->facetDomstift;
        if ($facetDomstift) {
            $valFctDft = array_column($facetDomstift, 'name');
            $qb->join('App\Entity\PersonRole', 'prfctdft', 'WITH', 'prfctdft.personId = inr.itemIdRole')
               ->join('prfctdft.institution', 'instfctdft')
               ->andWhere("instfctdft.nameShort IN (:valFctDft)")
               ->setParameter('valFctDft', $valFctDft);
        }

        $facetDiocese = isset($model->facetDiocese) ? $model->facetDiocese : null;
        if ($facetDiocese) {
            $valFctDioc = array_column($facetDiocese, 'name');
            // queries for bishops only consider Gatz-offices (query, sort, facet)
            if ($model->corpus == 'epc') {
                $qb->join('App\Entity\PersonRole', 'prfctdioc', 'WITH', 'prfctdioc.personId = inr.itemIdName');
            } else {
                $qb->join('App\Entity\PersonRole', 'prfctdioc', 'WITH', 'prfctdioc.personId = inr.itemIdRole');
            }
            $qb->andWhere("prfctdioc.dioceseName IN (:valFctDioc)")
               ->setParameter('valFctDioc', $valFctDioc);
        }

        $facetOffice = isset($model->facetOffice) ? $model->facetOffice : null;
        if ($facetOffice) {
            $valFctOfc = array_column($facetOffice, 'name');
            // queries for bishops only consider Gatz-offices (query, sort, facet)
            if ($model->corpus == 'epc') {
                $qb->join('App\Entity\PersonRole', 'prfctofc', 'WITH', 'prfctofc.personId = inr.itemIdName');
            } else {
                $qb->join('App\Entity\PersonRole', 'prfctofc', 'WITH', 'prfctofc.personId = inr.itemIdRole');
            }

            $qb->leftjoin('App\Entity\Role', 'rfctofc', 'WITH', 'rfctofc.id = prfctofc.roleId')
                ->andWhere("rfctofc.name in (:valFctOfc)")
                ->setParameter('valFctOfc', $valFctOfc);
        }

        $facetPlace = $model->facetPlace;
        if ($facetPlace) {
            $valFctPlc = array_column($facetPlace, 'name');
            $qb->join('App\Entity\PersonRole', 'prfctplc', 'WITH', 'prfctplc.personId = inr.itemIdRole')
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

        $facetUrl = $model->facetUrl;
        if ($facetUrl) {
            $valFctUrl = array_column($facetUrl, 'name');
            $qb->join('App\Entity\UrlExternal', 'url', 'WITH', 'url.itemId = inr.itemIdName')
               ->join('url.authority', 'auth')
               ->andWhere('auth.urlNameFormatter in (:valFctUrl)')
               ->setParameter('valFctUrl', $valFctUrl);
        }

        return $qb;
    }

    /**
     * countDiocese($model)
     * return array of dioceses related to a person's role (used for facet)
     */
    public function countDiocese($model) {

        $qb = $this->createQueryBuilder('inr')
                   ->select('DISTINCT pr_count.dioceseName AS name, COUNT(DISTINCT(inr.itemIdName)) AS n');

        $this->addConditions($qb, $model);
        $this->addFacets($qb, $model);

        // queries for bishops only consider Gatz-offices (query, sort, facet)
        if ($model->corpus == 'epc') {
            $qb->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = inr.itemIdName')
               ->andWhere("pr_count.dioceseName IS NOT NULL");
        } else {
            $qb->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = inr.itemIdRole')
               ->andWhere("pr_count.dioceseName IS NOT NULL");
        }

        $qb->groupBy('pr_count.dioceseName');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * countDomstift($model)
     *
     * return array of domstift names related to a person's role (used for facet)
     */
    public function countDomstift($model) {
        // $model should not contain domstift facet

        $em = $this->getEntityManager();
        $qbi = $em->getRepository(Institution::class)
                  ->createQueryBuilder('i')
                  ->select('i.id AS id, i.nameShort AS name')
                  ->andWhere("i.corpusId = 'cap'")
                  ->addOrderBy('i.nameShort');

        $domstift_list = $qbi->getQuery()->getResult();

        $qb = $this->createQueryBuilder('inr')
                   ->select('pr_count.institutionId AS id, COUNT(DISTINCT(inr.itemIdName)) AS n');

        $this->addConditions($qb, $model);
        $this->addFacets($qb, $model);

        $qb->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = inr.itemIdRole')
           ->andWhere('pr_count.institutionId IN (:instId_list)')
           ->setParameter('instId_list', array_column($domstift_list, 'id'));


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
     * countOffice($model)
     *
     * return array of offices related to a person's role (used for facet)
     */
    public function countOffice($model) {

        $qb = $this->createQueryBuilder('inr')
                   ->select('role_count.name AS name, COUNT(DISTINCT(inr.itemIdName)) as n');

        $this->addConditions($qb, $model);
        $this->addFacets($qb, $model);

        // queries for bishops only consider Gatz-offices (query, sort, facet)
        if ($model->corpus == 'epc') {
            $qb->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = inr.itemIdName');
        } else {
            $qb->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = inr.itemIdRole');
        }
        $qb->join('pr_count.role', 'role_count')
           ->andWhere("role_count.name IS NOT NULL");

        $qb->groupBy('role_count.name');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * countPlace($model)
     *
     * return array of places related to a person's role (used for facet)
     */
    public function countPlace($model) {
        // $model should not contain place facet

        $qb = $this->createQueryBuilder('inr')
                   ->select('ip_count.placeName AS name, COUNT(DISTINCT(inr.itemIdName)) as n');

        $this->addConditions($qb, $model);
        $this->addFacets($qb, $model);

        $qb->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = inr.itemIdRole')
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

        $qb->groupBy('ip_count.placeName');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * countUrl($model)
     *
     * return array of urls (used for facet)
     */
    public function countUrl($model) {
        // $model should not contain url facet

        $qb = $this->createQueryBuilder('inr')
                   ->select('auth.urlNameFormatter AS name, COUNT(DISTINCT(inr.itemIdName)) as n');

        $this->addConditions($qb, $model);
        $this->addFacets($qb, $model);

        $qb->join('App\Entity\UrlExternal', 'url', 'WITH', 'url.itemId = inr.itemIdName')
           ->join('url.authority', 'auth');

        $qb->groupBy('auth.urlNameFormatter');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }


    /**
     * @return list of person_id_role values
     */
    public function findPersonIdRole($person_id_name) {
            $inr_list = $this->findByItemIdName($person_id_name);
            // sort corpus 'dreg' last
            uasort($inr_list, function($a, $b) {
                return $a->getCorpusId() == 'dreg' ? 1 : -1;
            });
            return UtilService::collectionColumn($inr_list, 'itemIdRole');
    }


}
