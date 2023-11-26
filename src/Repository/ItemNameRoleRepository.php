<?php

namespace App\Repository;

use App\Entity\ItemNameRole;
use App\Entity\Item;
use App\Entity\ItemCorpus;
use App\Entity\ItemReference;
use App\Entity\Person;
use App\Entity\Authority;
use App\Entity\Institution;
use App\Entity\UrlExternal;
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
     */
    public function findList($item_id_name_list) {
        $qb = $this->createQueryBuilder('inr')
                   ->select('inr')
                   ->andWhere('inr.itemIdName in (:item_id_name_list)')
                   ->setParameter('item_id_name_list', $item_id_name_list);

        $query = $qb->getQuery();
        return $query->getResult();
    }


    /**
     *
     */
    public function findPersonIds($model, $limit = 0, $offset = 0) {
        $personRepository = $this->getEntityManager()
                                 ->getRepository(Person::class);

        $result = null;

        // avoid to join the same table twice
        $joined_list = array();
        // p_name is required for sorting
        // all entries in item_name_role should be online
        $qb = $this->createQueryBuilder('inr')
                   ->join('App\Entity\Person', 'p_name', 'WITH', 'p_name.id = inr.itemIdName');
        $joined_list[] = 'p_name';

        // pr is required for sorting; we publish only persons with roles: use innerJoin
        // queries for bishops only consider Gatz-offices (query, sort, facet)

        if ($model->corpus == 'epc') {
            $qb->innerJoin('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = inr.itemIdName');
        } else {
            $qb->innerJoin('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = inr.itemIdRole');
        }
        $joined_list[] = 'pr';

        $personRepository->addConditions($qb, $model, $joined_list);

        // do sorting in an extra step, see below
        $qb->addSelect('inr.itemIdName,'.
                       'p_name.givenname,'.
                       '(CASE WHEN p_name.familyname IS NULL THEN 0 ELSE 1 END)  as hasFamilyname,'.
                       'p_name.familyname,'.
                       'min(pr.dioceseName) as diocese_name,'.
                       'min(pr.dateSortKey) as dateSortKey');

        if ($model->corpus == 'can') {
            if ($model->domstift) { // restrict sorting to matches
                $qb->join('App\Entity\Institution', 'domstift_sort',
                          'WITH', "domstift_sort.id = pr.institutionId")
                   ->join('App\Entity\ItemCorpus', 'ic_domstift_sort',
                           'WITH', "ic_domstift_sort.itemId = domstift_sort.id and ic_domstift_sort.corpusId = 'cap'");
            } else {
                $qb->leftjoin('App\Entity\PersonRole', 'pr_domstift',
                          'WITH', 'pr_domstift.personId = inr.itemIdRole')
                   ->leftjoin('App\Entity\Institution', 'domstift_sort',
                          'WITH', "domstift_sort.id = pr_domstift.institutionId")
                   ->leftjoin('App\Entity\ItemCorpus', 'ic_domstift_sort',
                            'WITH', "ic_domstift_sort.itemId = domstift_sort.id and ic_domstift_sort.corpusId = 'cap'");
            }
            $qb->addSelect('min(domstift_sort.nameShort) as domstift_name');
        }

        $this->addFacets($qb, $model);

        if ($model->place) {
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

        $sort_list = $this->sortList($model);
        $result = UtilService::sortByFieldList($result, $sort_list);

        if ($limit > 0) {
            $result = array_slice($result, $offset, $limit);
        }

        return array_column($result, "itemIdName");

    }

    private function sortList($model) {
        // NULL is sorted last; the field 'hasFamilyname' overrides this behaviour
        $sort_list = ['hasFamilyname', 'familyname',  'givenname', 'dateSortKey', 'itemIdName'];
        $corpus = $model->corpus;

        if ($model->isEmpty()) {
            if ($corpus == 'can') {
                $sort_list = ['domstift_name', 'dateSortKey', 'givenname', 'familyname', 'itemIdName'];
            } else {
                $sort_list = ['hasFamilyname', 'familyname',  'givenname', 'dateSortKey', 'itemIdName'];
            }
        } elseif ($model->domstift) {
            $sort_list = ['domstift_name', 'dateSortKey', 'givenname', 'familyname', 'itemIdName'];
        } elseif ($model->diocese) {
            $sort_list = ['diocese_name', 'dateSortKey', 'givenname', 'familyname', 'itemIdName'];
        } elseif ($model->office) {
            if ($corpus == 'can') {
                $sort_list = ['domstift_name', 'dateSortKey', 'givenname', 'familyname', 'itemIdName'];
            } else {
                $sort_list = ['diocese_name', 'dateSortKey', 'givenname', 'familyname', 'itemIdName'];
            }
        } elseif ($model->name) {
            if ($corpus == 'can') {
                $sort_list = ['hasFamilyname', 'familyname',  'givenname', 'domstift_name', 'dateSortKey', 'itemIdName'];
            } else {
                $sort_list = ['hasFamilyname', 'familyname',  'givenname', 'dateSortKey', 'itemIdName'];
            }
        } elseif ($model->year) {
            $sort_list = ['dateSortKey', 'familyname', 'givenname', 'itemIdName'];
        } elseif ($model->someid) {
            $sort_list = ['dateSortKey', 'familyname', 'givenname', 'itemIdName'];
        } elseif ($model->place) {
            $sort_list = ['place_name', 'dateSortKey', 'familyname', 'givenname', 'itemIdName'];
        }

        return $sort_list;
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
        $personRepository = $this->getEntityManager()->getRepository(Person::class);

        $qb = $this->createQueryBuilder('inr')
                   ->select('DISTINCT pr_count.dioceseName AS name, COUNT(DISTINCT(inr.itemIdName)) AS n');

        $personRepository->addConditions($qb, $model);
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
        $personRepository = $this->getEntityManager()->getRepository(Person::class);

        // $model should not contain domstift facet

        $domstift_list = $this->getEntityManager()
                              ->getRepository(Institution::class)
                              ->findDomstifte();

        $qb = $this->createQueryBuilder('inr')
                   ->select('pr_count.institutionId AS id, COUNT(DISTINCT(inr.itemIdName)) AS n');

        $personRepository->addConditions($qb, $model);
        $this->addFacets($qb, $model);

        $qb->join('App\Entity\PersonRole', 'pr_count', 'WITH', 'pr_count.personId = inr.itemIdRole')
           ->andWhere('pr_count.institutionId IN (:instId_list)')
           ->setParameter('instId_list', UtilService::collectionColumn($domstift_list, 'id'));


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
            $id = $d_loop->getId();;
            if (array_key_exists($id, $count_simple_list)) {
                $result[] = [
                    'name' => $d_loop->getNameShort(),
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
        $personRepository = $this->getEntityManager()->getRepository(Person::class);

        $qb = $this->createQueryBuilder('inr')
                   ->select('role_count.name AS name, COUNT(DISTINCT(inr.itemIdName)) as n');

        $personRepository->addConditions($qb, $model);
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
        $personRepository = $this->getEntityManager()->getRepository(Person::class);
        // $model should not contain place facet

        $qb = $this->createQueryBuilder('inr')
                   ->select('ip_count.placeName AS name, COUNT(DISTINCT(inr.itemIdName)) as n');

        $personRepository->addConditions($qb, $model);
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
        $personRepository = $this->getEntityManager()->getRepository(Person::class);

        // $model should not contain url facet

        $qb = $this->createQueryBuilder('inr')
                   ->select('auth.urlNameFormatter AS name, COUNT(DISTINCT(inr.itemIdName)) as n');

        $personRepository->addConditions($qb, $model);
        $this->addFacets($qb, $model);

        $qb->join('App\Entity\UrlExternal', 'url', 'WITH', 'url.itemId = inr.itemIdName')
           ->join('url.authority', 'auth');

        $qb->groupBy('auth.urlNameFormatter');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * update entries related to $id_list
     */
    public function updateByIdList($id_list) {
        $entityManager = $this->getEntityManager();
        $itemCorpusRepository = $entityManager->getRepository(ItemCorpus::class);
        $personRepository = $entityManager->getRepository(Person::class);
        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class);

        // delete all entries related to persons in $id_list
        $qb = $this->createQueryBuilder('inr')
                   ->andWhere ('inr.itemIdRole in (:id_list)')
                   ->setParameter('id_list', $id_list);
        $inr_list = $qb->getQuery()->getResult();
        $n_del = count($inr_list);
        foreach ($inr_list as $inr_del) {
            $entityManager->remove($inr_del);
        }
        $entityManager->flush();

        // set/restore entries based on online status, corpus and references to the Digitales Personenregister
        $n = 0;
        $person_list = $personRepository->findList($id_list);

        $id_primary_list = array();
        $id_secondary_list = array();
        $id_gs_list = array();
        foreach ($person_list as $p_loop) {
            $item = $p_loop->getItem();
            $corpus_id_list = $item->getCorpusIdList();
            $is_online = $item->getIsOnline();
            // primary entry for 'can', 'epc'; filter for corpus = 'can' or 'epc'
            if ($is_online == 1 and (in_array('can', $corpus_id_list) or in_array('epc', $corpus_id_list))) {
                $id = $p_loop->getId();
                $inr = new ItemNameRole($id, $id);
                $inr->setItem($item);
                $inr->setPersonRole($p_loop);
                $entityManager->persist($inr);
                $id_primary_list[] = $id;
                $n += 1;
                // secondary entry
                $gsn = $item->getGsn();
                $online_flag = true;
                $item_id_dreg_list = $urlExternalRepository->findItemId($gsn, Authority::ID['GSN'], $online_flag);
                foreach ($item_id_dreg_list as $item_id) {
                    $iic_pairs = $itemCorpusRepository->findPairs($item_id, ['dreg', 'dreg-can']);
                    $is_dreg = (!is_null($iic_pairs) and (count($iic_pairs) > 0));
                    if (($item_id != $id) and $is_dreg) {
                        $inr = new ItemNameRole($id, $item_id);
                        $inr->setItem($item);
                        $inr->setPersonRole($personRepository->find($item_id));
                        $entityManager->persist($inr);
                        $n += 1;
                        $id_secondary_list[] = $item_id;
                    }
                }
            }
        }

        // independent GS entries, dreg-can only
        foreach ($person_list as $p_loop) {
            $id = $p_loop->getId();
            $item = $p_loop->getItem();
            $corpus_id_list = $item->getCorpusIdList();
            if ($item->getIsOnline() == 1
                and !in_array($id, $id_secondary_list) // this prevents dublicate entries
                and (in_array('dreg-can', $corpus_id_list))
            ) {
                $id_dreg = $p_loop->getId();
                $inr = new ItemNameRole($id_dreg, $id_dreg);
                $inr->setItem($item);
                $inr->setPersonRole($p_loop);
                $entityManager->persist($inr);

                $n += 1;

            }

        }

        $entityManager->flush();

        return $n;
    }

    /**
     * one page HTML list
     */
    public function referenceListByCorpus($id_list, $corpus_id) {
        $qb = $this->createQueryBuilder('inr')
                   ->select('ref_vol')
                   ->join('\App\Entity\ItemReference', 'ir', 'WITH', 'ir.itemId = inr.itemIdRole')
                   ->join('\App\Entity\ReferenceVolume', 'ref_vol', 'WITH', 'ref_vol.referenceId = ir.referenceId')
                   ->join('\App\Entity\ItemCorpus', 'ic', 'WITH', "ic.itemId = inr.itemIdRole and ic.corpusId = :corpus_id")
                   ->andWhere('inr.itemIdName in (:id_list)')
                   ->setParameter('id_list', $id_list)
                   ->setParameter('corpus_id', $corpus_id);

        $query = $qb->getQuery();
        return $query->getResult();

    }

    /**
     * @return role data for persons in $id_list
     *
     * collect all roles via ItemNameRole
     */
    public function findSimpleRoleList($id_list) {

        $qb = $this->createQueryBuilder('inr')
                   ->select('pr, r, institution, diocese')
                   ->leftJoin('\App\Entity\PersonRole', 'pr',
                              'WITH', 'pr.personId = inr.itemIdRole')
                   ->leftJoin('pr.role', 'r')
                   ->leftJoin('pr.institution', 'institution')
                   ->leftJoin('pr.diocese', 'diocese')
                   ->andWhere('inr.itemIdName in (:id_list)')
                   ->setParameter('id_list', $id_list);

        $query = $qb->getQuery();
        // be economical/careful with memory
        return $query->getArrayResult();

    }

}
