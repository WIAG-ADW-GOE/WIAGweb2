<?php

namespace App\Repository;

use App\Entity\Person;
use App\Entity\ItemNameRole;
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
        if ($model->corpus == 'epc') {
            return $this->findBishopIds($model, $limit, $offset);
        } elseif ($model->corpus == 'can') {
            return $this->findCanonIds($model, $limit, $offset);
        }
        return null;
    }

    /**
     *
     */
    public function findBishopIds($model, $limit = 0, $offset = 0) {
        $result = null;

        $corpus = $model->corpus;
        $diocese = $model->diocese;
        $office = $model->office;
        $place = $model->place;
        $year = $model->year;
        $name = $model->name;
        $someid = $model->someid;

        // consider only offices in Gatz: join offices using itemIdName
        $qb = $this->createQueryBuilder('inr')
                   ->join('App\Entity\ItemCorpus', 'c', 'WITH', 'c.itemId = inr.itemIdName AND c.corpusId = :corpus')
                   ->join('App\Entity\Person', 'p_name', 'WITH', 'p_name.id = inr.itemIdName')
                   ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = inr.itemIdName')
                   ->setParameter('corpus', $corpus);

        // table item_name_role only contains entries with status 'online'
        $add_joins = false;
        $this->addConditions($qb, $model, $add_joins);
        $this->addFacets($qb, $model);

        // do sorting in an extra step, see below
        $qb->select('inr.itemIdName,'.
                    'p_name.givenname,'.
                    '(CASE WHEN p_name.familyname IS NULL THEN 0 ELSE 1 END)  as hasFamilyname,'.
                    'p_name.familyname,'.
                    'min(pr.dioceseName) as diocese_name,'.
                    'min(pr.dateSortKey) as dateSortKey');

        $qb->groupBy('inr.itemIdName');

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
        if ($model->isEmpty() || $name) {
            $sort_list = ['hasFamilyname', 'familyname',  'givenname', 'dateSortKey', 'itemIdName'];
        } elseif ($diocese || $office) {
            $sort_list = ['diocese_name', 'dateSortKey', 'givenname', 'familyname', 'itemIdName'];
        } elseif ($year) {
            $sort_list = ['dateSortKey', 'familyname', 'givenname', 'itemIdName'];
        } elseif ($someid) {
            $sort_list = ['dateSortKey', 'familyname', 'givenname', 'itemIdName'];
        }

        $result = UtilService::sortByFieldList($result, $sort_list);

        if ($limit > 0) {
            $result = array_slice($result, $offset, $limit);
        }

        return array_column($result, "itemIdName");

    }

    /**
     *
     */
    public function findCanonIds($model, $limit = 0, $offset = 0) {
        $result = null;

        $corpus = $model->corpus;
        $domstift = $model->monastery;
        $office = $model->office;
        $place = $model->place;
        $year = $model->year;
        $name = $model->name;
        $someid = $model->someid;

        // TODO clean up
        // c (group by and query conditions)
        $qb = $this->createQueryBuilder('inr')
                   ->join('App\Entity\ItemCorpus', 'c', 'WITH', 'c.itemId = inr.itemIdName AND c.corpusId = :corpus')
                   ->join('App\Entity\Person', 'p_name', 'WITH', 'p_name.id = inr.itemIdName')
                   ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = inr.itemIdRole')
                   ->setParameter('corpus', $corpus);

        // table item_name_role only contains entries with status 'online'
        $add_joins = false;
        $this->addConditions($qb, $model, $add_joins);
        $this->addFacets($qb, $model);

        // do sorting in an extra step, see below
        // domstift takes priority in sorting, therefore it is joined via pr
        if ($domstift) {
            // Use institution and dateSortKey of all roles for sorting, which is not transparent for the user
            // if a role is present in 'Domherr GS' but not in 'Domherr'.
            $qb->join('App\Entity\Institution', 'domstift', 'WITH',
                      "domstift.id = pr.institutionId and domstift.corpusId = 'cap'");
            $qb->select('inr.itemIdName,'.
                        'p_name.givenname,'.
                        '(CASE WHEN p_name.familyname IS NULL THEN 0 ELSE 1 END)  as hasFamilyname,'.
                        'p_name.familyname,'.
                        'min(domstift.nameShort) as sort_domstift,'.
                        'min(pr.dateSortKey) as dateSortKey');
        }
        elseif ($model->isEmpty() || $office || $name || $year || $someid) {
            // Join domstift (sorting) independently from role (query condition).
            $qb->leftjoin('App\Entity\PersonRole', 'pr_sort', 'WITH', 'pr_sort.personId = inr.itemIdRole')
               ->leftjoin('App\Entity\Institution', 'domstift_sort', 'WITH', 'domstift_sort.id = pr_sort.institutionId');
            $qb->select('inr.itemIdName,'.
                        'p_name.givenname,'.
                        '(CASE WHEN p_name.familyname IS NULL THEN 0 ELSE 1 END)  as hasFamilyname,'.
                        'p_name.familyname,'.
                        'min(domstift_sort.nameShort) as sort_domstift,'.
                        'min(pr_sort.dateSortKey) as dateSortKey');
        } elseif ($place) {
            $qb->select('inr.itemIdName,'.
                        'min(ip.placeName) as placeName,'.
                        'p_name.givenname,'.
                        'p_name.familyname,'.
                        'min(pr.dateSortKey) as dateSortKey');
        }

        $qb->groupBy('inr.itemIdName');

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
            $sort_list = ['sort_domstift', 'dateSortKey', 'givenname', 'familyname', 'itemIdName'];
        } elseif ($name) {
            $sort_list = ['hasFamilyname', 'familyname',  'givenname', 'sort_domstift', 'dateSortKey', 'itemIdName'];
        } elseif ($year) {
            $sort_list = ['dateSortKey', 'sort_domstift', 'familyname', 'givenname', 'itemIdName'];
        } elseif ($someid) {
            $sort_list = ['sort_domstift', 'dateSortKey', 'familyname', 'givenname', 'itemIdName'];
        } elseif ($place) {
            $sort_list = ['placeName', 'dateSortKey', 'familyname', 'givenname', 'itemIdName'];
        }

        $result = UtilService::sortByFieldList($result, $sort_list);

        if ($limit > 0) {
            $result = array_slice($result, $offset, $limit);
        }

        return array_column($result, "itemIdName");

    }

    public function addConditions($qb, $model, $add_joins = true) {
        $domstift = $model->monastery;
        $diocese = $model->diocese;
        $office = $model->office;
        $name = $model->name;
        $place = $model->place;
        $year = $model->year;
        $someid = $model->someid;

        // join tables when called for facet counts
        if ($add_joins) {
            // TODO join via itemIdRole for canons?!
            $qb->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = inr.itemIdName')
               ->join('App\Entity\Person', 'p', 'WITH', 'p.id = inr.itemIdName');

            if ($domstift) {
                $qb->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = inr.itemIdRole')
                   ->join('App\Entity\Institution', 'domstift', 'WITH',
                          "domstift.id = r.institutionId and domstift.corpusId = 'cap'");
            }
        }

        if ($office) {
            $qb->leftjoin('pr.role', 'role')
               ->andWhere('pr.roleName LIKE :q_office OR role.name LIKE :q_office')
               ->setParameter('q_office', '%'.$office.'%');
        }

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
            // Join places independently from role (other query condition)
            $qb->join('App\Entity\PersonRole', 'pr_place', 'WITH', 'pr_place.personId = inr.itemIdRole')
               ->join('App\Entity\InstitutionPlace', 'inst_place', 'WITH',
                      'pr_place.institutionId = inst_place.institutionId '.
                      'AND ( '.
                      'pr_place.numDateBegin IS NULL AND pr_place.numDateEnd IS NULL '.
                      'OR (inst_place.numDateBegin < pr_place.numDateBegin AND pr_place.numDateBegin < inst_place.numDateEnd) '.
                      'OR (inst_place.numDateBegin < pr_place.numDateEnd AND pr_place.numDateEnd < inst_place.numDateEnd) '.
                      'OR (pr_place.numDateBegin < inst_place.numDateBegin AND inst_place.numDateBegin < pr_place.numDateEnd) '.
                      'OR (pr_place.numDateBegin < inst_place.numDateEnd AND inst_place.numDateEnd < pr_place.numDateEnd))')
                ->andWhere('inst_place.placeName LIKE :q_place')
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
     * countBishopDiocese($model)
     * return array of dioceses related to a person's role (used for facet)
     */
    public function countDiocese($model) {
        $corpus = $model->corpus;

        // TODO 2023-08-29 join PersonRole via itemIdRole for canons?!
        $qb = $this->createQueryBuilder('inr')
                   ->select('DISTINCT pr_count.dioceseName AS name, COUNT(DISTINCT(pr_count.personId)) AS n')
                   ->join('App\Entity\Item', 'i', 'WITH', 'i.id = inr.itemIdName')
                   ->join('i.itemCorpus', 'c')
                   ->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = inr.itemIdName')
                   ->andWhere('c.corpusId = :corpus')
                   ->andWhere("i.isOnline = 1")
                   ->andWhere("pr_count.dioceseName IS NOT NULL")
                   ->setParameter("corpus", $corpus);

        $add_joins = true;
        $this->addConditions($qb, $model, $add_joins);
        $this->addFacets($qb, $model);

        $qb->groupBy('pr_count.dioceseName');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * countOffice($model)
     *
     * return array of offices related to a person's role (used for facet)
     */
    public function countOffice($model) {
        $corpus = $model->corpus;

        // TODO 2023-08-29 join PersonRole via itemIdRole for canons?!
        $qb = $this->createQueryBuilder('inr')
                   ->select('DISTINCT pr_count.roleName AS name, COUNT(DISTINCT(pr_count.personId)) as n')
                   ->join('App\Entity\Item', 'i', 'WITH', 'i.id = inr.itemIdName')
                   ->join('i.itemCorpus', 'c')
                   ->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = inr.itemIdName')
                   ->andWhere('c.corpusId = :corpus')
                   ->andWhere("i.isOnline = 1")
                   ->andWhere("pr_count.roleName IS NOT NULL")
                   ->setParameter("corpus", $corpus);

        $add_joins = true;
        $this->addConditions($qb, $model, $add_joins);
        // TODO
        // $this->addFacets($qb, $model);

        $qb->groupBy('pr_count.roleName');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * add conditions set by facets
     */
    private function addFacets($qb, $model) {

        // 2023-08-29 bishops: join offices via itemIdName
        $facetDiocese = isset($model->facetInstitution) ? $model->facetInstitution : null;
        if ($facetDiocese) {
            $valFctDioc = array_column($facetDiocese, 'name');
            $qb->join('App\Entity\PersonRole', 'prfctdioc', 'WITH', 'prfctdioc.personId = inr.itemIdName')
               ->andWhere("prfctdioc.dioceseName IN (:valFctDioc)")
               ->setParameter('valFctDioc', $valFctDioc);
        }

        $facetOffice = isset($model->facetOffice) ? $model->facetOffice : null;
        if ($facetOffice) {
            $valFctOfc = array_column($facetOffice, 'name');
            $qb->join('App\Entity\PersonRole', 'prfctofc', 'WITH', 'prfctofc.personId = inr.itemIdName')
               ->andWhere("prfctofc.roleName IN (:valFctOfc)")
               ->setParameter('valFctOfc', $valFctOfc);
        }

        return $qb;
    }

    /**
     * @return list of person_id_role values
     */
    public function findPersonIdRole($person_id_name) {
            $inr_list = $this->findByItemIdName($person_id_name);
            return UtilService::collectionColumn($inr_list, 'itemIdRole');
    }


}
