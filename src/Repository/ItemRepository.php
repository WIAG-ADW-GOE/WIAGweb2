<?php

namespace App\Repository;

use App\Entity\Item;
use App\Entity\Authority;
use App\Entity\ItemProperty;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\ReferenceVolume;
use App\Entity\PersonBirthplace;
use App\Entity\UrlExternal;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Service\UtilService;

/**
 * @method Item|null find($id, $lockMode = null, $lockVersion = null)
 * @method Item|null findOneBy(array $criteria, array $orderBy = null)
 * @method Item[]    findAll()
 * @method Item[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemRepository extends ServiceEntityRepository
{
    // tolerance for the comparison of dates
    const MARGINYEAR = 1;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    // /**
    //  * @return Item[] Returns an array of Item objects
    //  */
    /*
      public function findByExampleField($value)
      {
      return $this->createQueryBuilder('i')
      ->andWhere('i.exampleField = :val')
      ->setParameter('val', $value)
      ->orderBy('i.id', 'ASC')
      ->setMaxResults(10)
      ->getQuery()
      ->getResult()
      ;
      }
    */

    /*
      public function findOneBySomeField($value): ?Item
      {
      return $this->createQueryBuilder('i')
      ->andWhere('i.exampleField = :val')
      ->setParameter('val', $value)
      ->getQuery()
      ->getOneOrNullResult()
      ;
      }
    */

    /**
     * get list of references for a given item type
     */
    public function referenceByItemType($itemTypeId) {
        $qb = $this->createQueryBuilder('i')
                   ->select('r')
                   ->join('\App\Entity\ItemReference', 'ir', 'WITH', 'i.id = ir.itemId')
                   ->join('\App\Entity\ReferenceVolume', 'r', 'WITH', 'ir.referenceId = r.id')
                   ->andWhere('i.itemTypeId = :itemTypeId')
                   ->setParameter(':itemTypeId', $itemTypeId)
                   ->orderBy('r.displayOrder');

        $query = $qb->getQuery();

        $result = $query->getResult();

        return $result;
    }

    public function personIds($model, $limit = 0, $offset = 0, $online_only = true) {
        $result = null;

        $itemTypeId = $model->itemTypeId;
        $diocese = null;
        $monastery = null;
        if ($itemTypeId == 4) {
            $diocese = $model->institution;
        } else {
            $monastery = $model->institution;
        }
        $office = $model->office;
        $year = $model->year;
        $name = $model->name;
        $place = $model->place;
        $someid = $model->someid;
        $sort_by = $model->sortBy;
        $sort_order = $model->sortOrder;

        $qb = $this->createQueryBuilder('i')
                   ->join('App\Entity\Person', 'p', 'WITH', 'i.id = p.id')
                   ->leftjoin('p.role', 'pr')
                   ->andWhere('i.itemTypeId = :itemTypeId')
                   ->andWhere('i.mergeStatus <> :merged')
                   ->setParameter(':itemTypeId', $itemTypeId)
                   ->setParameter(':merged', 'parent');

        if ($online_only) {
            $qb->andWhere('i.isOnline = 1');
        }

        $qb = $this->addPersonConditions($qb, $model);

        // only relevant for bishop queries (not for editing)
        $qb = $this->addBishopFacets($qb, $model);

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

        $sort_list = array();
        if ($sort_by == 'familyname') {
            $sort_list = ['familyname',  'givenname', 'inst_name', 'dateSortKey', 'personId'];
        } elseif ($sort_by == 'givenname') {
            $sort_list = ['givenname',  'familyname', 'inst_name', 'dateSortKey', 'personId'];
        } elseif ($sort_by == 'institution') {
            $sort_list = ['inst_name', 'dateSortKey', 'familyname', 'givenname', 'personId'];
        } elseif ($sort_by == 'diocese') {
            $sort_list = ['dioceseName', 'dateSortKey', 'familyname', 'givenname', 'personId'];

        } elseif ($sort_by == 'year') {
            $sort_list = ['dateSortKey', 'inst_name', 'familyname', 'givenname', 'personId'];
        } elseif ($sort_by == 'commentDuplicate') {
            $sort_list = ['commentDuplicate', 'inst_name', 'familyname', 'givenname', 'personId'];
        } elseif ($sort_by == 'idInSource') {
            $sort_list = ['idInSource', 'familyname', 'givenname', 'personId'];
        } elseif ($sort_by == 'editStatus') {
            $sort_list = ['editStatus', 'idInSource', 'personId'];
        } else {
            if ($office || $diocese || $monastery || $place) {
                $sort_list = ['dioceseName', 'inst_name', 'dateSortKey', 'familyname', 'givenname', 'personId'];
            }
            elseif ($model->isEmpty() || $name || $someid) {
                $sort_list = ['hasFamilyname', 'familyname',  'givenname', 'inst_name', 'dateSortKey', 'personId'];
            }
            else { # year
                $sort_list = ['dateSortKey', 'inst_name', 'familyname', 'givenname', 'personId'];
            }
        }

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
        $itemTypeId = $model->itemTypeId;
        if ($itemTypeId == 4) {
            $diocese = $model->institution;
        } else {
            $monastery = $model->institution;
        }
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
            $qb->leftjoin('p.role', 'pr_filter')
               ->leftjoin('pr_filter.institution', 'inst')
               ->andWhere("(pr_filter.institutionName LIKE :paramInst ".
                          "OR inst.name LIKE :paramInst)")
               ->setParameter('paramInst', '%'.$monastery.'%');
            if ($office) {
                $qb->leftjoin('pr_filter.role', 'r_office')
               ->andWhere("pr_filter.roleName LIKE :q_office OR r_office.name LIKE :q_office")
               ->setParameter('q_office', '%'.$office.'%');

            }
        } elseif ($office) {
            $qb->leftjoin('pr.role', 'r_office')
               ->andWhere("pr.roleName LIKE :q_office OR r_office.name LIKE :q_office")
               ->setParameter('q_office', '%'.$office.'%');
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
               ->leftjoin('\App\Entity\ReferenceVolume', 'vol', 'WITH', 'vol.referenceId = ref.referenceId AND vol.itemTypeId = i.itemTypeId')
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


    /**
     * add conditions set by facets
     */
    private function addBishopFacets($qb, $model) {
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
        $itemTypeId = $model->itemTypeId;

        $qb = $this->createQueryBuilder('i')
                   ->select('DISTINCT prcount.dioceseName AS name, COUNT(DISTINCT(prcount.personId)) AS n')
            // ->join('i.person', 'p') # for form conditions
                   ->join('App\Entity\Person', 'p', 'WITH', 'i.id = p.id')
                   ->join('p.role', 'prcount')
                   ->join('p.role', 'pr') # for form conditions
                   ->andWhere("i.itemTypeId = ${itemTypeId}")
                   ->andWhere("i.isOnline = 1")
                   ->andWhere("prcount.dioceseName IS NOT NULL");

        $this->addPersonConditions($qb, $model);
        $this->addBishopFacets($qb, $model);

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
        $itemTypeId = Item::ITEM_TYPE_ID['Bischof']['id'];

        $qb = $this->createQueryBuilder('i')
                   ->select('DISTINCT prcount.roleName AS name, COUNT(DISTINCT(prcount.personId)) as n')
                   ->join('App\Entity\Person', 'p', 'WITH', 'i.id = p.id')
                   ->join('p.role', 'prcount')
                   ->join('p.role', 'pr') # for form conditions
                   ->andWhere("i.itemTypeId = ${itemTypeId}")
                   ->andWhere("i.isOnline = 1")
                   ->andWhere("prcount.roleName IS NOT NULL");

        $this->addPersonConditions($qb, $model);
        $this->addBishopFacets($qb, $model);

        $qb->groupBy('prcount.roleName');

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }

    /**
     * collect office data from different sources
     */
    public function getBishopOfficeData($person_id) {

        $item = array($this->find($person_id));
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
            $itemGs = $this->findByUrlExternal($itemTypeGs, $gsn, $authorityGs);
            $item = array_merge($item, $itemGs);
        }

        // get item from Domherrendatenbank
        $authorityWIAG = Authority::ID['WIAG-ID'];
        $wiagid = $item[0]->getIdPublic();
        if (!is_null($wiagid)) {
            $itemTypeCanon = Item::ITEM_TYPE_ID['Domherr']['id'];
            $canon = $this->findByUrlExternal($itemTypeCanon, $wiagid, $authorityWIAG);
            $item = array_merge($item, $canon);
        }


        // set places and references and authorities in one query
        $em = $this->getEntityManager();
        $id_list = array_map(function ($i) {
            return $i->getId();
        }, $item);

        $personRepository = $em->getRepository(Person::class);
        $person_role = $personRepository->findList($id_list);

        $em->getRepository(PersonRole::class)->setPlaceNameInRole($person_role);

        $item_role = array_map(function($p) {return $p->getItem();}, $person_role);
        $em->getRepository(ReferenceVolume::class)->setReferenceVolume($item_role);
        $em->getRepository(Authority::class)->setAuthority($item_role);

        return $person_role;

    }

    public function personDoubletIds($model, $limit = 0, $offset = 0, $online_only = true) {
        $urlExternalRepository = $this->getEntityManager()->getRepository(UrlExternal::class);

        // exclude merged and deleted
        // include 'n' in the query as a prerequisite for the HAVING-clause
        $qb = $urlExternalRepository->createQueryBuilder('u')
                                    ->select("u.authorityId, u.value, COUNT(u.value) as n")
                                    ->join("u.item", "i")
                                    ->andWhere("i.itemTypeId = :item_type_id")
                                    ->andWhere("i.mergeStatus <> 'parent'")
                                    ->andWhere("i.isDeleted <> '1'")
                                    ->addGroupBy("u.authorityId")
                                    ->addGroupBy("u.value")
                                    ->andHaving("n > 1")
                                    ->setParameter("item_type_id", $model['itemTypeId']);

        $authority = $model['authority'];
        if ($authority != "") {
            $qb->andWhere("u.authorityId = :authority_id")
               ->setParameter('authority_id', $authority);
        }
        // '- alle -' returns null, which is filtered out by array_filter
        $edit_status = array_filter(array_values($model['editStatus']));
        if (!is_null($edit_status) and count($edit_status) > 0) {
            $qb->andWhere("i.editStatus in (:q_status)")
               ->setParameter('q_status', $edit_status);
        } else {
            $qb->andWhere("i.editStatus <> 'Dublette'");
        }


        $query = $qb->getQuery();
        $group_result = $query->getResult();

        $value_list = array_column($group_result, "value");

        $qb = $this->createQueryBuilder('i')
                   ->select('DISTINCT(i.id) as personId')
                   ->join('i.urlExternal', 'uext')
                   ->addOrderBy("uext.value")
                   ->andWhere("uext.value in (:value_list)")
                   ->andWhere("i.itemTypeId = :item_type_id")
                   ->setParameter("value_list", $value_list)
                   ->setParameter("item_type_id", $model['itemTypeId']);

        if (!is_null($edit_status) and count($edit_status) > 0) {
            $qb->andWhere("i.editStatus in (:q_status)")
               ->setParameter('q_status', $edit_status);
        } else {
            $qb->andWhere("i.editStatus <> 'Dublette'");
        }

        $query = $qb->getQuery();
        $result = $query->getResult();

        return array_column($result, 'personId');
    }

    public function priestUtIds($model, $limit = 0, $offset = 0) {
        $result = null;

        $itemTypePriestUt = Item::ITEM_TYPE_ID['Priester Utrecht'];

        $name = $model->name;
        $birthplace = $model->birthplace;
        $religious_order = $model->religiousOrder;
        $year = $model->year;
        $someid = $model->someid;

        $qb = $this->createQueryBuilder('i')
                   ->select('distinct(i.id) as personId, ip_ord_date.dateValue as sort')
                   ->join('\App\Entity\Person', 'p', 'WITH', 'i.id = p.id')
                   ->join('\App\Entity\ItemProperty',
                          'ip_ord_date',
                          'WITH',
                          'ip_ord_date.itemId = i.id AND ip_ord_date.propertyTypeId = :ordination')
                   ->andWhere('i.itemTypeId = :itemTypePriestUt')
                   ->andWhere('i.isOnline = 1')
                   ->setParameter(':ordination', ItemProperty::ITEM_PROPERTY_TYPE_ID['ordination_priest'])
                   ->setParameter(':itemTypePriestUt', $itemTypePriestUt);

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

    public function setSibling($person) {
        // get person from Domherrendatenbank
        $f_found = false;
        $sibling = $this->findSibling($person);
        if ($sibling) {
            $person->setSibling($sibling);
            $f_found = true;
        }
        return $f_found;
    }

    public function findSibling($person) {
        $authorityWIAG = Authority::ID['WIAG-ID'];
        $wiagid = $person->getItem()->getIdPublic();
        $sibling = null;
        if (!is_null($wiagid) && $wiagid != "") {
            $itemTypeCanon = Item::ITEM_TYPE_ID['Domherr']['id'];
            $item = $this->findByUrlExternal($itemTypeCanon, $wiagid, $authorityWIAG);
            if ($item) {
                $personRepository = $this->getEntityManager()->getRepository(Person::class);
                $sibling_list = $personRepository->findList([$item[0]->getId()]);
                $sibling = $sibling_list[0];
            }
        }
        return $sibling;
    }

    public function findByUrlExternal($itemTypeId, $value, $authId, $isonline = true) {
        if (!is_array($itemTypeId)) {
            $itemTypeId = [$itemTypeId];
        }

        $qb = $this->createQueryBuilder('i')
                   ->addSelect('i')
                   ->join('i.urlExternal', 'ext')
                   ->andWhere('i.itemTypeId in (:itemTypeId)')
                   ->andWhere('ext.value = :value')
                   ->andWhere('ext.authorityId = :authId')
                   ->setParameter(':itemTypeId', $itemTypeId)
                   ->setParameter(':value', $value)
                   ->setParameter(':authId', $authId);

        if ($isonline) {
            $qb->andWhere('i.isOnline = 1');
            // $online_status = Item::ITEM_TYPE[$itemTypeId]['online_status'];
            // $qb->andWhere('i.editStatus = :online_status')
            //    ->setParameter('online_status', $online_status);
        }

        $query = $qb->getQuery();
        $item = $query->getResult();

        return $item;
    }

    /**
     * countPriestUtOrder($model)
     *
     * return array of religious orders
     */
    public function countPriestUtOrder($model) {
        $itemTypeId = Item::ITEM_TYPE_ID['Priester Utrecht']['id'];

        $qb = $this->createQueryBuilder('i')
                   ->select('ro.abbreviation AS name, COUNT(DISTINCT(p.id)) AS n')
                   ->join('\App\Entity\Person', 'p', 'WITH', 'i.id = p.id')
                   ->join('p.religiousOrder', 'ro')
                   ->andWhere("i.itemTypeId = ${itemTypeId}")
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


    /**
     * AJAX
     */
    public function suggestPriestUtName($name, $hintSize) {
        $qb = $this->createQueryBuilder('i')
                   ->select("DISTINCT CASE WHEN n.gnPrefixFn IS NOT NULL ".
                            "THEN n.gnPrefixFn ELSE n.gnFn END ".
                            "AS suggestion")
                   ->join('\App\Entity\Person', 'p', 'WITH', 'i.id = p.id')
                   ->join('\App\Entity\NameLookup', 'n', 'WITH', 'i.id = n.personId')
                   ->andWhere('i.itemTypeId = :itemType')
                   ->setParameter(':itemType', Item::ITEM_TYPE_ID['Priester Utrecht']);
        $q_list = UtilService::nameQueryComponents($name);
        foreach($q_list as $key => $q_name) {
            $qb->andWhere('n.gnPrefixFn LIKE :q_name_'.$key)
               ->setParameter('q_name_'.$key, '%'.trim($q_name).'%');
        }

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestPriestUtBirthplace($name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(PersonBirthplace::class);

        $qb = $repository->createQueryBuilder('b')
                         ->select("DISTINCT b.placeName as suggestion")
                         ->join('b.person', 'person')
                         ->andWhere('person.itemTypeId = :itemType')
                         ->andWhere('b.placeName LIKE :value')
                         ->setParameter('itemType', Item::ITEM_TYPE_ID['Priester Utrecht'])
                         ->setParameter('value', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestPriestUtReligiousOrder($name, $hintSize) {
        $qb = $this->createQueryBuilder('i')
                   ->select("DISTINCT r.abbreviation as suggestion")
                   ->join('\App\Entity\Person', 'p', 'WITH', 'i.id = p.id')
                   ->join('p.religiousOrder', 'r')
                   ->andWhere('i.itemTypeId = :itemType')
                   ->andWhere('r.abbreviation LIKE :value')
                   ->setParameter('itemType', Item::ITEM_TYPE_ID['Priester Utrecht'])
                   ->setParameter('value', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    public function findMaxIdInSource($itemTypeId) {
        $qb = $this->createQueryBuilder('i')
                   ->select("i.idInSource")
                   ->andWhere('i.itemTypeId = :itemTypeId')
                   ->setParameter('itemTypeId', $itemTypeId);
        $query = $qb->getQuery();
        $result = $query->getResult();

        $max_id = 0;
        foreach ($result as $el) {
            $cand = intval($el['idInSource']);
            if ($cand > $max_id) {
                $max_id = $cand;
            }
        }
        return $max_id;

    }

    /**
     * findMergeCandidate($id_in_source, $item_type_id)
     * status 'merged' is excluded
     */
    public function findMergeCandidate($id_in_source, $item_type_id) {
        $qb = $this->createQueryBuilder('i')
                   ->select("i")
                   ->andWhere('i.itemTypeId = :item_type_id')
                   ->andWhere('i.idInSource = :id_in_source')
                   ->andWhere("i.mergeStatus <> 'parent'")
                   ->setParameter('item_type_id', $item_type_id)
                   ->setParameter('id_in_source', $id_in_source);

        $query = $qb->getQuery();
        return $query->getOneOrNullResult();
    }

    /**
     * @return entry matching $id or having $id as a parent (merging); is_online = true
     */
    public function findByIdPublicOrParent($id) {
        $with_id_in_source = false;
        $list_size_max = 200;
        $descendant_id_list = $this->findIdByAncestor(
            $id,
            $with_id_in_source,
            $list_size_max,
        );

        $qb = $this->createQueryBuilder('i')
                   ->andWhere("i.id in (:q_id_list)")
                   ->andWhere("i.isOnline = 1")
                   ->setParameter('q_id_list', $descendant_id_list);

        $query = $qb->getQuery();

        return $query->getResult();
    }

    /**
     *
     */
    public function findParents($item) {
        $qb = $this->createQueryBuilder('i')
                   ->andWhere('i.mergedIntoId = :child_id')
                   ->setParameter('child_id', $item->getId());
        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     *
     */
    public function setAncestor($item_list) {
        foreach($item_list as $i_loop) {
            $i_loop->setAncestor($this->findAncestor($i_loop));
        }
        return null;
    }

    /**
     * @return list of items that were merged into $item
     */
    public function findAncestor(Item $item) {
        $ancestor_list = array();
        $id_list = array($item->getId());
        $result_count = 1;
        while ($result_count > 0) {
            $qb = $this->createQueryBuilder('i')
                       ->andWhere('i.mergedIntoId in (:id_list)')
                       ->setParameter('id_list', $id_list);
            $query = $qb->getQuery();
            $q_result = $query->getResult();
            $id_list = array();
            foreach ($q_result as $i_loop) {
                $id_list[] = $i_loop->getId();
                $ancestor_list[] = $i_loop;
            }
            $result_count = count($q_result);
        }
        return $ancestor_list;
    }

    /**
     * @return items containing $id in ancestor list
     */
    public function findIdByAncestor(string $q_id, $with_id_in_source, $list_size_max) {

        if ($with_id_in_source) {
            $qb = $this->createQueryBuilder('i')
                       ->andWhere("i.idPublic like :q_id OR i.idInSource like :q_id")
                       ->andWhere("i.itemTypeId in (:item_type_list)")
                       ->setParameter('item_type_list', Item::ITEM_TYPE_WIAG_PERSON_LIST)
                       ->setParameter('q_id', '%'.$q_id.'%');
        } else {
            $qb = $this->createQueryBuilder('i')
                       ->andWhere("i.idPublic like :q_id")
                       ->andWhere("i.itemTypeId in (:item_type_list)")
                       ->setParameter('item_type_list', Item::ITEM_TYPE_WIAG_PERSON_LIST)
                       ->setParameter('q_id', '%'.$q_id.'%');
        }

        $qb->setMaxResults($list_size_max);
        $query = $qb->getQuery();

        $q_result = $query->getResult();

        // find id of the current child
        $child_id_list = array();
        foreach($q_result as $i_loop) {
            $child = $this->findCurrentChild($i_loop);
            if ($child) {
                $child_id_list[] = $child->getId();
            }
        }
        return array_unique($child_id_list);
    }

    /**
     * 2023-06-30 obsolete; see findIdByAncestor
     * @return items containing $id in ancestor list
     */
    public function findCurrentChildById(string $q_id, $with_id_in_source, $list_size_max) {

        if ($with_id_in_source) {
            $qb = $this->createQueryBuilder('i')
                       ->andWhere("i.idPublic like :q_id OR i.idInSource like :q_id")
                       ->andWhere("i.itemTypeId in (:item_type_list)")
                       ->setParameter('item_type_list', Item::ITEM_TYPE_WIAG_PERSON_LIST)
                       ->setParameter('q_id', '%'.$q_id.'%');
        } else {
            $qb = $this->createQueryBuilder('i')
                       ->andWhere("i.idPublic like :q_id")
                       ->andWhere("i.itemTypeId in (:item_type_list)")
                       ->setParameter('item_type_list', Item::ITEM_TYPE_WIAG_PERSON_LIST)
                       ->setParameter('q_id', '%'.$q_id.'%');
        }

        $qb->setMaxResults($list_size_max);
        $query = $qb->getQuery();

        $q_result = $query->getResult();

        $child_list = array();
        foreach($q_result as $i_loop) {
            $child = $this->findCurrentChild($i_loop);
            if ($child) {
                $child_list[] = $child;
            }
        }
        return $child_list;
    }


    /**
     * @return first item that is online or has no descendants
     */
    public function findCurrentChild(Item $item) {
        $child = $item;
        $merged_into_id = $item->getMergedIntoId();
        $is_child = $item->getMergeStatus() == 'child';
        while(!is_null($merged_into_id) and $merged_into_id > 0 and !$is_child) {
            $child = $this->find($merged_into_id);
            if ($child) { // avoid error for inconsistent data
                $is_child = $child->getMergeStatus() == $child;
                $merged_into_id = $child->getMergedIntoId();
            } else {
                $is_online = false;
                $merged_into_id = null;
            }
        }
        return $child;
    }

    /**
     * @return maximum value for id_in_source, if it is numerical
     */
    public function maxIdInSource($item_type_id) {
        // Doctrine does not know the function CAST nor CONVERT
        $qb = $this->createQueryBuilder('i')
                   ->select('i.idInSource')
                   ->andWhere('i.itemTypeId = :item_type_id')
                   ->setParameter('item_type_id', $item_type_id);

        $query = $qb->getQuery();
        $q_result = $query->getResult();

        $max = 0;
        foreach($q_result as $val) {
            $val = intval($val['idInSource']);
            if($max < $val) {
                $max = $val;
            }
        }
        return $max;
    }

    /**
     * @return meta data and GSN for items with type in $item_type_id_list
     */
    public function findGsnByItemTypeId($item_type_id_list) {
        $authority_id = Authority::ID['GS'];

        $qb = $this->createQueryBuilder('i')
                   ->select('i.id, i.dateChanged, uext.value as gsn')
                   ->join('i.urlExternal', 'uext')
                   ->andWhere('uext.authorityId = :authority_id')
                   ->andWhere('i.itemTypeId in (:item_type_id_list)')
                   ->setParameter('authority_id', $authority_id)
                   ->setParameter('item_type_id_list', $item_type_id_list);

        $query = $qb->getQuery();
        return $query->getResult();

    }


}
