<?php

namespace App\Repository;

use App\Entity\Item;
use App\Entity\Corpus;
use App\Entity\ItemCorpus;
use App\Entity\ItemProperty;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\ReferenceVolume;
use App\Entity\Authority;
use App\Entity\UrlExternal;

use App\Service\UtilService;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Person|null find($id, $lockMode = null, $lockVersion = null)
 * @method Person|null findOneBy(array $criteria, array $orderBy = null)
 * @method Person[]    findAll()
 * @method Person[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonRepository extends ServiceEntityRepository {

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

    /**
     * findEditPersonIds($model, $limit = 0, $offset = 0) {
     *
     * @return list of IDs matching conditions given by $model.
     *
     */
    public function findEditPersonIds($model, $limit = 0, $offset = 0) {
        $result = null;

        // avoid to join the same table twice
        $joined_list = array();
        $qb = $this->createQueryBuilder('p')
                   ->join('p.item', 'i');

        // pr is required for sorting
        $qb->leftJoin('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = p.id');
        $joined_list[] = 'pr';

        $corpusParam = explode(',', $model->corpus);

        $qb->join('App\Entity\ItemCorpus', 'c', 'WITH', "c.itemId = i.id AND c.corpusId in (:corpus)")
           ->setParameter('corpus', $corpusParam);
        $joined_list[] = 'c';

        $this->addConditions($qb, $model, $joined_list);

        // do sorting in an extra step, see below
        $qb->select('p.id as id,'.
                    'p.givenname,'.
                    '(CASE WHEN p.familyname IS NULL THEN 0 ELSE 1 END)  as hasFamilyname,'.
                    'p.familyname,'.
                    'i.commentDuplicate as comment_duplicate,'.
                    'i.editStatus as edit_status,'.
                    'min(pr.institutionName) as institution_name,'.
                    'min(pr.dioceseName) as diocese_name,'.
                    'min(pr.dateSortKey) as date_sort_key')
           ->andWhere("i.mergeStatus in ('child', 'original')")
           ->andWhere("i.isDeleted = 0");

        if (in_array("- alle -", $model->editStatus)) {
            $qb->andWhere("i.editStatus NOT IN ('Dublette')");
        } else {
            $qb->andWhere("i.editStatus in (:edit_status)")
               ->setParameter('edit_status', $model->editStatus);
        }

        if ($model->monastery || $model->sortBy == "institution" ) { // restrict sorting to matches
            $qb->join('App\Entity\Institution', 'institution',
                      'WITH', "institution.id = pr.institutionId")
               ->addSelect('min(institution.nameShort) as inst_name');
        }

        if ($model->sortBy == "idInSource") {
            $qb->join('App\Entity\ItemCorpus', 'corpus',
                      'WITH', "corpus.itemId = i.id")
               ->addSelect('min(corpus.idInCorpus) as id_in_corpus');
        }

        if ($model->place) {
            $qb->addSelect('min(inst_place.placeName) as place_name');
        }

        $qb->groupBy('p.id');

        $query = $qb->getQuery();
        $result = $query->getResult();

        // sort
        // doctrine min function returns a string; convert it to int
        $result = array_map(function($el) {
            $val = $el['date_sort_key'];
            $el['date_sort_key'] = is_null($val) ? $val : intval($val);
            return $el;
        }, $result);

        $result = $this->sortQuery($result, $model);

        if ($limit > 0) {
            $result = array_slice($result, $offset, $limit);
        }

        return array_column($result, "id");

    }

    private function sortQuery($result, $model) {
        $sort_by = $model->sortBy;
        $sort_list = ['givenname', 'familyname'];
        if ($model->sortBy == 'givenname') {
            $sort_list = ['givenname', 'familyname'];
        } elseif ($model->sortBy == 'familyname') {
            $sort_list = ['hasFamilyname', 'familyname', 'givenname'];
        } elseif ($model->sortBy == 'institution') {
            $sort_list = ['institution_name'];
        } elseif ($model->sortBy == 'diocese') {
            $sort_list = ['diocese_name'];
        } elseif ($model->sortBy == 'year') {
            $sort_list = [];
        } elseif ($model->sortBy == 'commentDuplicate') {
            $sort_list = ['comment_duplicate'];
        } elseif ($model->sortBy == 'idInSource') {
            $sort_list = ['id_in_corpus'];
        } elseif ($model->sortBy == 'editStatus') {
            $sort_list = ['edit_status'];
        }

        $sort_list[] = 'date_sort_key';
        $sort_list[] = 'id';

        return UtilService::sortByFieldList($result, $sort_list, $model->sortOrder);

    }

    public function addConditions($qb, $model, $joined_list = array()) {

        $corpus = $model->corpus;
        $comment_edit = $model->comment;
        $comment_duplicate = $model->commentDuplicate;
        $domstift = $model->domstift;
        $monastery = $model->monastery; // used in edit form
        $diocese = $model->diocese;
        $office = $model->office;
        $name = $model->name;
        $place = $model->place;
        $reference = $model->reference;
        $year = $model->year;
        $someid = $model->someid;

        // include in queries for corpus 'can' also canons from the Digitales Personenregister
        // bishops from Digitales Personenregister have no independent entries in item_name_role,
        // however their offices are visible in detail view (query via item_name_role)

        // if (in_array('epc', $corpus)) {
        //     $qb->join(
        //         'App\Entity\ItemCorpus', 'c',
        //         'WITH', "c.itemId = inr.itemIdName AND c.corpusId = 'epc'");
        // } elseif ($corpus == 'can') {
        //     $qb->join(
        //         'App\Entity\ItemCorpus', 'c',
        //         'WITH', "c.itemId = inr.itemIdRole AND c.corpusId in ('can', 'dreg-can')"
        //     );
        // }

        if ($model->corpus == 'can') {
            $corpusParam = ['can', 'dreg-can'];
        }else {
            $corpusParam = explode(',', $model->corpus);
        }

        if (!in_array('c', $joined_list)) {
            $qb->join('App\Entity\ItemNameRole', 'inr_c',
                      'WITH', 'inr_c.itemIdName = inr.itemIdName')
               ->join('App\Entity\ItemCorpus', 'c',
                      'WITH', "c.itemId = inr_c.itemIdRole AND c.corpusId in (:corpus)")
               ->setParameter('corpus', $corpusParam);
            $joined_list[] = 'c';
        }

        // queries for bishops only consider Gatz-offices (query, sort, facet)
        if (!in_array('pr', $joined_list) and ($office or $domstift or $diocese or $place)) {
            if ($corpus == 'epc') {
                $qb->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = inr.itemIdName');
            } elseif ($corpus == 'can') {
                $qb->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = inr.itemIdRole');
            }
            $joined_list[] = 'pr';
        }

        if ($domstift) {
            $qb->join('App\Entity\Institution', 'domstift', 'WITH',
                      "domstift.id = pr.institutionId")
               ->join('App\Entity\ItemCorpus', 'ic_domstift', 'WITH',
                      "ic_domstift.itemId = domstift.id AND ic_domstift.corpusId = 'cap'");
        }

        if ($reference) {
            $qb->leftJoin('i.reference', 'ref')
               ->leftJoin('\App\Entity\ReferenceVolume', 'vol',
                          'WITH', 'vol.referenceId = ref.referenceId')
               ->andWhere('vol.titleShort LIKE :q_ref')
               ->setParameter('q_ref', '%'.$reference.'%');
        }

        if ($comment_duplicate) {
            $qb->andWhere('i.commentDuplicate LIKE :comment_duplicate')
               ->setParameter('comment_duplicate', '%'.$comment_duplicate.'%');
        }

        if ($comment_edit) {
            $qb->andWhere('p.comment LIKE :comment_edit')
               ->setParameter('comment_edit', '%'.$comment_edit.'%');
        }

        if ($office) {
            $qb->leftJoin('pr.role', 'role')
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

        if ($monastery) {
            $qb->andWhere('institution.name LIKE :q_monastery')
               ->setParameter('q_monastery', '%'.$monastery.'%');
        }

        if ($diocese) {
            // if a diocese is given via diocese_id, there is also a value for diocese_name
            // do not AND-combine diocese and domstift
            $diocese = str_ireplace("erzbistum", "", $diocese);
            $diocese = str_ireplace("bistum", "", $diocese);
            $diocese = trim($diocese);
            if ($corpus == 'epc') {
                $qb ->andWhere("pr.dioceseName LIKE :q_diocese");
            } else {
                $qb->join('App\Entity\PersonRole', 'pr_dioc', 'WITH', 'pr_dioc.personId = pr.personId')
                    ->andWhere("pr_dioc.dioceseName LIKE :q_diocese");

            }

            $qb->setParameter('q_diocese', '%'.$diocese.'%');
        }

        if ($place) {
            $qb->andWhere('inst_place.placeName LIKE :q_place')
               ->setParameter('q_place', '%'.$place.'%');
        }

        if ($name) {
            // search also for name in 'Digitales Personenregister' (?)
            if ($corpus == 'epc' or $corpus == 'can') {
                $qb->join('App\Entity\NameLookup', 'name_lookup',
                          'WITH', 'name_lookup.personId = inr.itemIdRole');
            } else {
                $qb->join('App\Entity\NameLookup', 'name_lookup', 'WITH', 'name_lookup.personId = p.id');
            }
            // require that every word of the search query occurs in the name, regardless of the order
            $q_list = UtilService::nameQueryComponents($name);
            foreach($q_list as $key => $q_name) {
                $qb->andWhere('name_lookup.nameVariant LIKE :q_name_'.$key)
                   ->setParameter('q_name_'.$key, '%'.trim($q_name).'%');
            }
        }

        if ($someid || $year) {
            if ($someid) {

                // look for corpus
                if (in_array($someid, Corpus::EDIT_LIST)) {
                        $qb->andWhere('c.corpusId = :corpus_id')
                           ->setParameter('corpus_id', $someid);
                } else {

                    // ID is more specific

                    $id_list_list = array();
                    // look for $someid in merged ancestors
                    $itemRepository = $this->getEntityManager()->getRepository(Item::class);
                    $with_id_in_corpus = $model->isEdit;
                    $list_size_max = 200;
                    $id_list_list[] = $itemRepository->findIdByAncestor(
                        $someid,
                        $with_id_in_corpus,
                        $list_size_max,
                    );

                    // look for $someid in external links
                    $uextRepository = $this->getEntityManager()->getRepository(UrlExternal::class);
                    // look for any person here, further restrictions on the result set (if neccessary) are made elsewhere
                    if ($model->isEdit) {
                        $corpus_id_list_url_query = ['epc', 'can'];
                    } else {
                        $corpus_id_list_url_query = ['epc', 'can', 'dreg', 'dreg-can'];
                    }
                    $id_list_list[] = $uextRepository->findIdBySomeNormUrl(
                        $someid,
                        $corpus_id_list_url_query,
                        $list_size_max
                    );

                    // look for $someid in item_corpus
                    if ($model->isEdit) {
                        $itemCorpusRepository = $this->getEntityManager()->getRepository(ItemCorpus::class);
                        $iid_q_list = $itemCorpusRepository->findItemIdByCorpusAndId($someid);
                        if (!is_null($iid_q_list) and count($iid_q_list) > 0) {
                            $id_list_list[] = array_column($iid_q_list, 'itemId');
                        }
                    }

                    $q_id_list = array_unique(array_merge(...$id_list_list));
                    if (!$model->isEdit) {
                        //
                        $qb->andWhere("inr.itemIdRole in (:q_id_list)")
                           ->setParameter('q_id_list', $q_id_list);
                    } else {
                        $qb->andWhere("p.id in (:q_id_list)")
                           ->setParameter('q_id_list', $q_id_list);
                    }
                }
            }
            if ($year) {
                // we have no join to person at the level of ItemNameRole.itemIdRole so far
                if ($model->corpus == 'epc' or $model->corpus == 'can') {
                    $qb->join('App\Entity\Person', 'p_year', 'WITH', 'p_year.id = inr.itemIdRole');
                    $qb->andWhere("p_year.dateMin - :mgnyear < :q_year ".
                                  " AND :q_year < p_year.dateMax + :mgnyear")
                       ->setParameter('mgnyear', Person::MARGINYEAR)
                       ->setParameter('q_year', $year);
                } else {
                    $qb->andWhere("p.dateMin - :mgnyear < :q_year ".
                                  " AND :q_year < p.dateMax + :mgnyear")
                       ->setParameter('mgnyear', Person::MARGINYEAR)
                       ->setParameter('q_year', $year);
                }
            }
        }

        $misc = $model->misc;
        if ($misc) {
            $qb->leftJoin('p.role', 'pr_misc')
               ->leftJoin('pr_misc.institution', 'inst_misc')
               ->leftJoin('i.itemProperty', 'i_prop')
               ->leftJoin('i_prop.type', 'i_prop_type')
               ->leftJoin('pr_misc.roleProperty', 'r_prop')
               ->leftJoin('r_prop.type', 'r_prop_type')
               ->andWhere("(i.normdataEditedBy LIKE :misc)".
                          " OR (p.noteName LIKE :misc)".
                          " OR (p.notePerson LIKE :misc)".
                          " OR (p.academicTitle LIKE :misc)".
                          " OR (i.commentDuplicate LIKE :misc)".
                          " OR (i_prop.value LIKE :misc)".
                          " OR (i_prop_type.name LIKE :misc)".
                          " OR (r_prop.value LIKE :misc)".
                          " OR (r_prop_type.name LIKE :misc)".
                          " OR (pr.roleName LIKE :misc)".
                          " OR (pr.note LIKE :misc)".
                          " OR (pr.dioceseName LIKE :misc)".
                          " OR (inst_misc.name LIKE :misc)")
               ->setParameter("misc", '%'.$misc.'%');
        }

        $dateCreated = UtilService::parseDateRange($model->dateCreated);
        if ($dateCreated) {
            $qb->andWhere(":q_min <= i.dateCreated AND i.dateCreated <= :q_max")
               ->setParameter('q_min', $dateCreated[0])
               ->setParameter('q_max', $dateCreated[1]);
        }
        $dateChanged = UtilService::parseDateRange($model->dateChanged);
        if ($dateChanged) {
            $qb->andWhere(":q_min <= i.dateChanged AND i.dateChanged <= :q_max")
               ->setParameter('q_min', $dateChanged[0])
               ->setParameter('q_max', $dateChanged[1]);
        }

        return $qb;
    }

    /**
     * 2023-11-04 obsolete
     * add conditions set by facets
     */
    // private function addFacets($qb, $model) {
    //     $itemTypeId = Item::ITEM_TYPE_ID['Bischof']['id'];

    //     $facetDiocese = isset($model->facetInstitution) ? $model->facetInstitution : null;
    //     if ($facetDiocese) {
    //         $valFctDioc = array_column($facetDiocese, 'name');
    //         $qb->join('App\Entity\PersonRole', 'prfctdioc', 'WITH', 'prfctdioc.personId = i.id')
    //            ->andWhere("i.itemTypeId = ${itemTypeId}")
    //            ->andWhere("prfctdioc.dioceseName IN (:valFctDioc)")
    //            ->setParameter('valFctDioc', $valFctDioc);
    //     }

    //     $facetOffice = isset($model->facetOffice) ? $model->facetOffice : null;
    //     if ($facetOffice) {
    //         $valFctOfc = array_column($facetOffice, 'name');
    //         $qb->join('App\Entity\PersonRole', 'prfctofc', 'WITH', 'prfctofc.personId = i.id')
    //            ->andWhere("i.itemTypeId = ${itemTypeId}")
    //            ->andWhere("prfctofc.roleName IN (:valFctOfc)")
    //            ->setParameter('valFctOfc', $valFctOfc);
    //     }

    //     return $qb;
    // }

    /**
     * TODO 2023-08-29 clean up: moved to ItemNameRoleRepository->countDiocese
     * countBishopDiocese($model)
     *
     * return array of dioceses related to a person's role (used for facet)
     */
    public function countBishopDiocese_legacy($model) {
        $corpus = $model->corpus;

        $qb = $this->createQueryBuilder('p')
                   ->select('DISTINCT prcount.dioceseName AS name, COUNT(DISTINCT(prcount.personId)) AS n')
                   ->join('App\Entity\Item', 'i', 'WITH', 'i.id = p.id')
                   ->join('i.itemCorpus', 'c')
                   ->join('p.role', 'prcount')
                   ->join('p.role', 'pr') # for form conditions
                   ->andWhere('c.corpusId = :corpus')
                   ->andWhere("i.isOnline = 1")
                   ->andWhere("prcount.dioceseName IS NOT NULL")
                   ->setParameter("corpus", $corpus);

        $this->addPersonConditions($qb, $model);
        $this->addFacets($qb, $model);

        $qb->groupBy('prcount.dioceseName');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * TODO 2023-08-29 clean up: moved to ItemNameRoleRepository->countDiocese
     * countBishopOffice($model)
     *
     * return array of offices related to a person's role (used for facet)
     */
    public function countBishopOffice($model) {
        $corpus = $model->corpus;

        $qb = $this->createQueryBuilder('p')
                   ->select('DISTINCT prcount.roleName AS name, COUNT(DISTINCT(prcount.personId)) as n')
                   ->join('App\Entity\Item', 'i', 'WITH', 'i.id = p.id')
                   ->join('i.itemCorpus', 'c')
                   ->join('p.role', 'prcount')
                   ->join('p.role', 'pr') # for form conditions
                   ->andWhere('c.corpusId = :corpus')
                   ->andWhere("i.isOnline = 1")
                   ->andWhere("prcount.roleName IS NOT NULL")
                   ->setParameter("corpus", $corpus);

        $this->addPersonConditions($qb, $model);
        $this->addFacets($qb, $model);

        $qb->groupBy('prcount.roleName');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * @return person data as array
     */
    public function findArray($id_list) {
        $qb = $this->createQueryBuilder('p')
                   ->select('p, i, ic, inr, ip, ipt, urlext, auth, ref, gnv, fnv, monord')
                   ->join('p.item', 'i') # avoid query in twig ...
                   ->join('i.itemCorpus', 'ic')
                   ->leftJoin('i.itemNameRole', 'inr')
                   ->leftJoin('i.itemProperty', 'ip')
                   ->leftJoin('ip.type', 'ipt')
                   ->leftJoin('i.urlExternal', 'urlext')
                   ->leftJoin('urlext.authority', 'auth')
                   ->leftJoin('i.reference', 'ref')
                   ->leftJoin('p.givennameVariants', 'gnv')
                   ->leftJoin('p.familynameVariants', 'fnv')
                   ->leftJoin('p.religiousOrder', 'monord')
                   ->andWhere('p.id in (:id_list)')
                   ->setParameter('id_list', $id_list);

        $query = $qb->getQuery();
        $person_list = $query->getArrayResult();

        $person_list = UtilService::reorderArray($person_list, $id_list, 'id');

        return $person_list;
    }

    /**
     * @return person data as array
     */
    public function findArrayWithRole($id_list) {
        $qb = $this->createQueryBuilder('p')
                   ->select('p, i, ic, inr, ip, ipt, urlext, auth, ref, gnv, fnv, monord, '.
                            'pr, role, diocese, institution, birthplace')
                   ->join('p.item', 'i') # avoid query in twig ...
                   ->join('i.itemCorpus', 'ic')
                   ->leftJoin('i.itemNameRole', 'inr')
                   ->leftJoin('i.itemProperty', 'ip')
                   ->leftJoin('ip.type', 'ipt')
                   ->leftJoin('i.urlExternal', 'urlext')
                   ->leftJoin('urlext.authority', 'auth')
                   ->leftJoin('i.reference', 'ref')
                   ->leftJoin('p.givennameVariants', 'gnv')
                   ->leftJoin('p.familynameVariants', 'fnv')
                   ->leftJoin('p.religiousOrder', 'monord')
                   ->leftJoin('p.role', 'pr')
                   ->leftJoin('pr.role', 'role')
                   ->leftJoin('pr.institution', 'institution')
                   ->leftJoin('pr.diocese', 'diocese')
                   ->leftJoin('p.birthplace', 'birthplace')
                   ->andWhere('p.id in (:id_list)')
                   ->setParameter('id_list', $id_list);

        $query = $qb->getQuery();
        $person_list = $query->getArrayResult();

        $person_list = UtilService::reorderArray($person_list, $id_list, 'id');

        return $person_list;
    }


    /**
     *
     */
    public function findList($id_list, $with_deleted = false, $with_ancestors = false) {
        $qb = $this->createQueryBuilder('p')
                   ->select('p, i, inr, ip, bp, role, role_type, institution, urlext, ref')
                   ->join('p.item', 'i') # avoid query in twig ...
                   ->leftJoin('i.itemNameRole', 'inr')
                   ->leftJoin('i.itemProperty', 'ip')
                   ->leftJoin('i.urlExternal', 'urlext')
                   ->leftJoin('i.reference', 'ref')
                   ->leftJoin('p.birthplace', 'bp')
                   ->leftJoin('p.role', 'role')
                   ->leftJoin('role.role', 'role_type')
                   ->leftJoin('role.institution', 'institution')
                   ->andWhere('p.id in (:id_list)')
                   ->addOrderBy('role.dateSortKey')
                   ->addOrderBy('role.id')
                   ->setParameter('id_list', $id_list);

        if (!$with_deleted) {
            $qb->andWhere('i.isDeleted = 0');
        }

        // sorting of birthplaces see annotation

        $query = $qb->getQuery();
        $person_list = $query->getResult();

        $em = $this->getEntityManager();
        $itemRepository = $em->getRepository(Item::class);

        $role_list = $this->getRoleList($person_list);
        $em->getRepository(PersonRole::class)->setPlaceNameInRole($role_list);

        $item_list = array_map(function($p) {return $p->getItem();}, $person_list);

        // set ancestors
        if ($with_ancestors) {
            $itemRepository->setAncestor($item_list);
        }

        // set reference volumes
        $em->getRepository(ReferenceVolume::class)->setReferenceVolume($item_list);

        // set authorities
        $em->getRepository(Authority::class)->setAuthority($item_list);

        // restore order as in $id_list
        $person_list = UtilService::reorder($person_list, $id_list, "id");

        return $person_list;
    }

    /**
     *
     */
    private function getRoleList($person_list) {
        $role_list = array_map(function($el) {
            # array_merge accepts only an array
            return $el->getRole()->toArray();
        }, $person_list);
        $role_list = array_merge(...$role_list);

        return $role_list;
    }

    // see ItemRepository
    // public function findItemNameRole($id_list) {
    //     $qb = $this->createQueryBuilder('p_name')
    //                ->select('p_name, p_role')
    //                ->join('p.item', 'i')
    //                ->join('i.itemNameRole', 'inr')
    //                ->join('\App\Entity\Person', 'p_role', 'WITH', 'p_role.personId = inr.itemIdRole');

    //     $query = $qb->getQuery();

    //     // TODO order like $id_list
    //     return $query->getResult();
    // }

    /**
     * 2023-10-12 obsolete
     * set sibling (only for bishops)
     */
    public function setSibling($person_list) {
        $itemRepository = $this->getEntityManager()->getRepository(Item::class);
        foreach($person_list as $person) {
            $itemRepository->setSibling($person);

            $sibling = $person->getSibling();
            // add external url, if not yet present
            if (!is_null($sibling)) {
                $person_url_external = $person->getItem()->getUrlExternal();
                $url_value_list = array();
                foreach($person_url_external as $uext) {
                    $url_value_list[] = $uext->getValue();
                }
                foreach($sibling->getItem()->getUrlExternal() as $uext) {
                    if (false === array_search($uext->getValue(), $url_value_list)) {
                        $person_url_external->add($uext);
                    }
                }
            }
        }
    }

    /**
     * 2023-10-12 obsolete?
     * setPersonName($canon_list)
     *
     * set personName foreach element of `$canon_list`.
     */
    public function setPersonName($canon_list) {
        $id_list = array_map(function($el) {return $el->getPersonIdName();}, $canon_list);
        $id_list = array_unique($id_list);

        $qb = $this->createQueryBuilder('p')
                   ->select('p')
                   ->andWhere('p.id in (:id_list)')
                   ->setParameter('id_list', $id_list);

        $query = $qb->getQuery();
        $result = $query->getResult();

        $id_person_map = array();
        foreach($result as $r) {
            $id_person_map[$r->getId()] = $r;
        }

        // set sibling in an extra call to PersonRepository->setSibling
        foreach($canon_list as $canon) {
            $person_id_name = $canon->getPersonIdName();
            $person = $id_person_map[$person_id_name];
            $canon->setPersonName($person);
        }

        return null;
    }

    /**
     * adjust data
     * @return IDs of entries with missing date range information
     */
    public function findMissingDateRange($limit = null, $offset = null) {
        $qb = $this->createQueryBuilder('p')
                   ->select('p.id as personId')
                   ->andWhere('p.dateMin is NULL OR p.dateMax is NULL');

        if (!is_null($limit)) {
            $qb->setMaxResults($limit);
        }
        if (!is_null($offset)) {
            $qb->setFirstResult($offset);
        }

        $query = $qb->getQuery();
        $result = $query->getResult();

        return array_column($result, 'personId');

    }

    /**
     * return list of IDs matching conditions in $model
     */
    public function priestUtIds($model, $limit = 0, $offset = 0) {
        $result = null;

        $name = $model->name;
        $birthplace = $model->birthplace;
        $religious_order = $model->religiousOrder;
        $year = $model->year;
        $someid = $model->someid;

        $qb = $this->createQueryBuilder('p')
                   ->select('distinct(p.id) as personId, ip_ord_date.dateValue as sort')
                   ->join('\App\Entity\Item', 'i', 'WITH', 'i.id = p.id')
                   ->join('i.itemCorpus', 'corpus')
                   ->join('\App\Entity\ItemProperty',
                          'ip_ord_date',
                          'WITH',
                          'ip_ord_date.itemId = i.id AND ip_ord_date.propertyTypeId = :ordination')
                   ->andWhere("corpus.corpusId = 'utp'")
                   ->andWhere('i.isOnline = 1')
                   ->setParameter(':ordination', ItemProperty::ITEM_PROPERTY_TYPE_ID['ordination_priest']);

        $qb = $this->addPriestUtConditions($qb, $model);
        $qb = $this->addPriestUtFacets($qb, $model);

        if ($religious_order) {
            $qb->addOrderBy('rlgord.abbreviation');
        }
        if ($birthplace) {
            $qb->addSelect('bp.placeName as birthplace')
               ->addOrderBy('birthplace');
        }
        $qb->addOrderBy('p.familyname')
           ->addOrderBy('p.givenname')
           ->addOrderBy('sort', 'ASC')
           ->addOrderBy('p.id');

        if ($limit > 0) {
            $qb->setMaxResults($limit)
               ->setFirstResult($offset);
        }

        $query = $qb->getQuery();
        $result =  $query->getResult();

        // doctrine distinct function returns a string
        $result = array_map(function($el) {
            $val = $el['personId'];
            return is_null($val) ? $val : intval($val);
        }, $result);

        return $result;
    }

    private function addPriestUtConditions($qb, $model) {
        $birthplace = $model->birthplace;
        $religious_order = $model->religiousOrder;

        if ($birthplace) {
            $qb->join('\App\Entity\PersonBirthplace', 'bp', 'WITH', 'p.id = bp.personId')
               ->andWhere('bp.placeName LIKE :birthplace')
               ->setParameter('birthplace', '%'.$birthplace.'%');
        }

        if ($religious_order) {
            $qb->join('p.religiousOrder', 'rlgord')
               ->andWhere("rlgord.abbreviation LIKE :religious_order")
               ->setParameter('religious_order', '%'.$religious_order.'%');
        }

        $year = $model->year;
        if ($year) {
            $qb->andWhere("p.dateMin - :mgnyear < :q_year ".
                          " AND :q_year < p.dateMax + :mgnyear")
               ->setParameter(':mgnyear', self::MARGINYEAR)
               ->setParameter('q_year', $year);
        }

        $name = $model->name;
        if ($name) {
            $qb->join('\App\Entity\NameLookup', 'nlu', 'WITH', 'p.id = nlu.personId');
            $q_list = UtilService::nameQueryComponents($name);
            foreach($q_list as $key => $q_name) {
                $qb->andWhere('nlu.nameVariant LIKE :q_name_'.$key)
                   ->setParameter('q_name_'.$key, '%'.trim($q_name).'%');
            }
        }

        $someid = $model->someid;
        if ($someid) {
            $qb->andWhere("i.idPublic = :q_id OR i.idInSource = :q_id OR i.idInSource = :q_id_long")
               ->setParameter('q_id', $someid)
               ->setParameter('q_id_long', 'id_'.$someid);
        }
        return $qb;
    }

    /**
     * add conditions set by facets
     */
    private function addPriestUtFacets($qb, $model) {

        $facetReligiousOrder = $model->facetReligiousOrder;
        if ($facetReligiousOrder) {
            $valFctRo = array_column($facetReligiousOrder, 'name');
            $qb->join('\App\Entity\Person', 'pfctro', 'WITH', 'i.id = pfctro.id')
               ->join('pfctro.religiousOrder', 'rofct')
               ->andWhere("rofct.abbreviation IN (:valFctRo)")
               ->setParameter('valFctRo', $valFctRo);
        }

        return $qb;
    }

    /**
     * countPriestUtOrder($model)
     *
     * return array of religious orders
     */
    public function countPriestUtOrder($model) {

        $qb = $this->createQueryBuilder('p')
                   ->select('ro.abbreviation AS name, COUNT(DISTINCT(p.id)) AS n')
                   ->join('p.item', 'i')
                   ->join('i.itemCorpus', 'corpus')
                   ->join('p.religiousOrder', 'ro')
                   ->andWhere("corpus.corpusId = 'utp'")
                   ->andWhere("i.isOnline = 1")
                   ->andWhere("i.isDeleted = 0")
                   ->andWhere("ro.abbreviation IS NOT NULL");

        $this->addPriestUtConditions($qb, $model);
        // only relevant if there is more than one facet
        // $this->addPriestUtFacets($qb, $model);

        $qb->groupBy('ro.id')
           ->orderBy('ro.abbreviation');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }


}
