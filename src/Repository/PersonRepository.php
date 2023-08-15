<?php

namespace App\Repository;

use App\Entity\Item;
use App\Entity\ItemProperty;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\ReferenceVolume;
use App\Entity\Authority;
use App\Entity\UrlExternal;

use App\Service\UtilService;

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
     * @return list of IDs compatible with $model
     */
    public function personIds($model, $limit = 0, $offset = 0, $online_only = true) {
        $result = null;

        $corpus = $model->corpus;
        $diocese = null;
        $monastery = null;
        $diocese = $model->diocese;
        $monastery = $model->monastery;
        $office = $model->office;
        $year = $model->year;
        $name = $model->name;
        $place = $model->place;
        $someid = $model->someid;
        $sort_order = $model->sortOrder;

        $qb = $this->createQueryBuilder('p')
                   ->join('p.item', 'i')
                   ->join('i.itemCorpus', 'c')
                   ->leftjoin('p.role', 'pr')
                   ->andWhere('c.corpusId = :corpus')
                   ->andWhere('i.mergeStatus <> :merged')
                   ->setParameter(':corpus', $corpus)
                   ->setParameter(':merged', 'parent');

        if ($online_only) {
            $qb->andWhere('i.isOnline = 1');
        }

        $qb = $this->addPersonConditions($qb, $model);

        // only relevant for bishop queries (not for editing)
        $qb = $this->addFacets($qb, $model);

        // get sort criteria
        $qb->leftjoin('p.role', 'pr_inst')
           ->leftjoin('p.role', 'pr_date')
           ->leftjoin('pr_inst.institution', 'inst_sort')
           ->select('i.id as personId, i.idInSource, i.editStatus, p.givenname, p.familyname, '.
                    '(CASE WHEN p.familyname IS NULL THEN 0 ELSE 1 END)  as hasFamilyname, '.
                    'min(inst_sort.nameShort) as inst_name, '.
                    'min(pr.dioceseName) as dioceseName, '.
                    'min(pr_date.dateSortKey) as dateSortKey, '.
                    'i.commentDuplicate')
           ->addGroupBy('personId');

        $query = $qb->getQuery();

        $result = $query->getResult();

        if ($model->sortBy) {
            $sort_by = $model_sortBy;
        } elseif ($office or $diocese or $monastery or $place) {
            $sort_by = "office";
        } elseif ($name or $someid or $model->isEmpty()) {
            $sort_by = "name";
        } else {
            $sort_by = "year";
        }

        $sort_list = Person::SORT_LIST[$sort_by];

        $result = UtilService::sortByFieldList($result, $sort_list, $sort_order);

        if ($limit > 0) {
            $result = array_slice($result, $offset, $limit);
        }

        return array_column($result, 'personId');
    }

    private function addPersonConditions($qb, $model) {
        // parameters

        $diocese = null;
        $monastery = null;
        $diocese = $model->diocese;
        $monastery = $model->monastery;
        $office = $model->office;
        $place = $model->place;
        $misc = $model->misc;

        if ($diocese) {
            $qb->andWhere("(pr.dioceseName LIKE :paramDiocese ".
                          "OR CONCAT('erzbistum ', pr.dioceseName) LIKE :paramDiocese ".
                          "OR CONCAT('bistum ', pr.dioceseName) LIKE :paramDiocese) ")
               ->setParameter('paramDiocese', '%'.$diocese.'%');
        }

        // if monastery is given, it should be AND-combined with office
        if ($monastery) {
            $qb->leftjoin('p.role', 'pr_cond')
               ->leftjoin('pr_cond.institution', 'inst')
               ->andWhere("(pr_cond.institutionName LIKE :paramInst ".
                          "OR inst.name LIKE :paramInst)")
               ->setParameter('paramInst', '%'.$monastery.'%');
            if ($office) {
                $this->addPersonConditionOffice($qb, $office);
            }
        } elseif ($office) {
            $qb->leftjoin('p.role', 'pr_cond');
        }

        if ($office) {
            $this->addPersonConditionOffice($qb, $office);
        }

        if ($place) {
            // Join places independently from role (other query condition)
            $qb->join('p.role', 'role_place')
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

        $year = $model->year;
        if ($year) {
            $qb->andWhere("p.dateMin - :mgnyear < :q_year ".
                          " AND :q_year < p.dateMax + :mgnyear")
               ->setParameter(':mgnyear', self::MARGINYEAR)
               ->setParameter('q_year', $year);
        }

        $name = $model->name;
        if ($name) {
            // join name_lookup via person or via canon_lookup
            $qb->leftjoin('\App\Entity\CanonLookup', 'clu', 'WITH', 'clu.personIdName = p.id')
               ->join('\App\Entity\NameLookup',
                      'nlu',
                      'WITH',
                      'clu.personIdRole = nlu.personId OR p.id = nlu.personId');
            // require that every word of the search query occurs in the name, regardless of the order
            $q_list = UtilService::nameQueryComponents($name);
            foreach($q_list as $key => $q_name) {
                $qb->andWhere('nlu.gnPrefixFn LIKE :q_name_'.$key)
                   ->setParameter('q_name_'.$key, '%'.$q_name.'%');
            }

        }

        $someid = $model->someid;
        if ($someid) {
            // look for $someid in merged ancestors
            $with_id_in_source = $model->isEdit;
            $list_size_max = 200;
            $descendant_id_list = $this->findIdByAncestor(
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

            $qb->andWhere("i.id in (:q_id_list)")
               ->setParameter('q_id_list', $q_id_list);
        }

        $reference = $model->reference;
        if ($reference) {
            $qb->leftjoin('i.reference', 'ref')
               ->leftjoin('\App\Entity\ReferenceVolume', 'vol', 'WITH', 'vol.referenceId = ref.referenceId')
               ->andWhere('vol.titleShort LIKE :q_ref')
               ->setParameter('q_ref', '%'.$reference.'%');
        }

        $isDeleted = $model->isDeleted;
        if ($isDeleted) {
            $qb->andWhere("i.isDeleted = 1");
        } else {
            $qb->andWhere("i.isDeleted = 0");
        }

        // '- alle -' returns null, which is filtered out by array_filter
        $edit_status = array_filter(array_values($model->editStatus));
        if (!is_null($edit_status) and count($edit_status) > 0) {
            $qb->andWhere("i.editStatus in (:q_status)")
               ->setParameter('q_status', $edit_status);
        } else {
            $qb->andWhere("i.editStatus <> 'Dublette'");
        }

        $commentDuplicate = $model->commentDuplicate;
        if ($commentDuplicate) {
            $qb->andWhere("i.commentDuplicate = :q_commentDuplicate")
               ->setParameter('q_commentDuplicate', $commentDuplicate);
        }

        $comment = $model->comment;
        if ($comment) {
            $qb->andWhere("p.comment LIKE :q_comment")
               ->setParameter('q_comment', '%'.$comment.'%');
        }

        $misc = $model->misc;
        if ($misc) {
            // 2023-06-14 Namen mit einbeziehen
            $qb->leftjoin('p.role', 'pr_misc')
               ->leftjoin('pr_misc.institution', 'inst_misc')
               ->leftjoin('i.itemProperty', 'i_prop')
               ->leftjoin('i_prop.type', 'i_prop_type')
               ->leftjoin('pr_misc.roleProperty', 'r_prop')
               ->leftjoin('r_prop.type', 'r_prop_type')
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

    private function addPersonConditionOffice($qb, $office) {
                $qb->leftjoin('pr_cond.role', 'r_office')
                   ->andWhere("pr_cond.roleName LIKE :q_office OR r_office.name LIKE :q_office")
                   ->setParameter('q_office', '%'.$office.'%');
                return $qb;
            }


    /**
     * add conditions set by facets
     */
    private function addFacets($qb, $model) {
        $itemTypeId = Item::ITEM_TYPE_ID['Bischof']['id'];

        $facetDiocese = isset($model->facetInstitution) ? $model->facetInstitution : null;
        if ($facetDiocese) {
            $valFctDioc = array_column($facetDiocese, 'name');
            $qb->join('App\Entity\PersonRole', 'prfctdioc', 'WITH', 'prfctdioc.personId = i.id')
               ->andWhere("i.itemTypeId = ${itemTypeId}")
               ->andWhere("prfctdioc.dioceseName IN (:valFctDioc)")
               ->setParameter('valFctDioc', $valFctDioc);
        }

        $facetOffice = isset($model->facetOffice) ? $model->facetOffice : null;
        if ($facetOffice) {
            $valFctOfc = array_column($facetOffice, 'name');
            $qb->join('App\Entity\PersonRole', 'prfctofc', 'WITH', 'prfctofc.personId = i.id')
               ->andWhere("i.itemTypeId = ${itemTypeId}")
               ->andWhere("prfctofc.roleName IN (:valFctOfc)")
               ->setParameter('valFctOfc', $valFctOfc);
        }

        return $qb;
    }

        /**
     * countBishopDiocese($model)
     *
     * return array of dioceses related to a person's role (used for facet)
     */
    public function countBishopDiocese($model) {
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
     * 2023-08-15 obsolete?
     * collect office data from different sources
     */
    public function getBishopOfficeData($person_id) {
        $entityManager = $this->getEntityManager();
        $itemRepository = $entityManager->getRepository(Item::class);

        $item = array($itemRepository->find($person_id));
        // get item from Germania Sacra
        $authorityGs = Authority::ID['GS'];
        $gsn = $item[0]->getUrlExternalByAuthorityId($authorityGs);
        if (!is_null($gsn)) {
            // Each person from Germania Sacra should have an entry in table id_external with its GSN.
            // If data are up to date at most one of these types is successful.
            $itemTypeGs = [
                Item::ITEM_TYPE_ID['Domherr GS']['id'],
                Item::ITEM_TYPE_ID['Bischof GS']['id']
            ];
            $itemGs = $itemRepository->findByUrlExternal($itemTypeGs, $gsn, $authorityGs);
            $item = array_merge($item, $itemGs);
        }

        // get item from Domherrendatenbank
        $authorityWIAG = Authority::ID['WIAG-ID'];
        $wiagid = $item[0]->getIdPublic();
        if (!is_null($wiagid)) {
            $itemTypeCanon = Item::ITEM_TYPE_ID['Domherr']['id'];
            $canon = $itemRepository->findByUrlExternal($itemTypeCanon, $wiagid, $authorityWIAG);
            $item = array_merge($item, $canon);
        }


        // set places and references and authorities in one query

        $id_list = array_map(function ($i) {
            return $i->getId();
        }, $item);

        $person_role = $this->findList($id_list);

        $role_list = $this->getRoleList($person_role);
        $entityManager->getRepository(PersonRole::class)->setPlaceNameInRole($role_list);

        $item_role = array_map(function($p) {return $p->getItem();}, $person_role);
        $entityManager->getRepository(ReferenceVolume::class)->setReferenceVolume($item_role);
        $entityManager->getRepository(Authority::class)->setAuthority($item_role);

        return $person_role;

    }

    /**
     *
     */
    public function findList($id_list, $with_deleted = false) {
        $qb = $this->createQueryBuilder('p')
                   ->select('p, i, ip, bp, role, role_type, institution, urlext, ref')
                   ->join('p.item', 'i') # avoid query in twig ...
                   ->leftjoin('i.itemProperty', 'ip')
                   ->leftjoin('i.urlExternal', 'urlext')
                   ->leftjoin('i.reference', 'ref')
                   ->leftjoin('p.birthplace', 'bp')
                   ->leftjoin('p.role', 'role')
                   ->leftjoin('role.role', 'role_type')
                   ->leftjoin('role.institution', 'institution')
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

        // there is not much potential for optimization
        foreach($person_list as $person) {
            $itemRepository->setSibling($person);
        }

        $role_list = $this->getRoleList($person_list);
        $em->getRepository(PersonRole::class)->setPlaceNameInRole($role_list);

        $item_list = array_map(function($p) {return $p->getItem();}, $person_list);

        // set ancestors
        $itemRepository->setAncestor($item_list);

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

    /**
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
                $qb->andWhere('nlu.gnPrefixFn LIKE :q_name_'.$key)
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
        $itemTypeId = Item::ITEM_TYPE_ID['Priester Utrecht'];

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
