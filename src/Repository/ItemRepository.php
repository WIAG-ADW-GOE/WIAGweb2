<?php

namespace App\Repository;

use App\Entity\Item;
use App\Entity\Authority;
use App\Entity\ItemProperty;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\ReferenceVolume;
use App\Entity\PersonBirthplace;
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

    /**
     * 2022-01-08 obsolete?
     */
    public function countBishop_hide($model) {
        $result = array('n' => 0);

        $itemTypeId = Item::ITEM_TYPE_ID['Bischof']['id'];

        // diocese or office
        $diocese = $model->diocese;
        $office = $model->office;
        $year = $model->year;
        $name = $model->name;
        $someid = $model ->someid;

        if ($diocese || $office) {
            $qb = $this->createQueryBuilder('i')
                       ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = i.id')
                       ->select('COUNT(DISTINCT i.id) as n')
                       ->andWhere("i.itemTypeId = ${itemTypeId}")
                       ->andWhere('i.isOnline = 1');
            if($year) {
                $qb->join('\App\Entity\Person', 'p', 'WITH', 'i.id = p.id');
            }
        } elseif ($year) {
            $qb = $this->createQueryBuilder('i')
                       ->join('\App\Entity\Person', 'p', 'WITH', 'i.id = p.id')
                       ->select('COUNT(DISTINCT i.id) as n')
                       ->andWhere("i.itemTypeId = ${itemTypeId}")
                       ->andWhere('i.isOnline = 1');
        } elseif ($model->isEmpty() || $name || $someid) {
            $qb = $this->createQueryBuilder('i')
                       ->select('COUNT(DISTINCT i.id) as n')
                       ->andWhere("i.itemTypeId = ${itemTypeId}")
                       ->andWhere('i.isOnline = 1');
        }

        $qb = $this->addBishopConditions($qb, $model);
        $qb = $this->addBishopFacets($qb, $model);

        $query = $qb->getQuery();
        $result = $query->getOneOrNullResult();

        return $result;
    }


    public function bishopIds($model, $limit = 0, $offset = 0) {
        $result = null;

        $itemTypeBishop = Item::ITEM_TYPE_ID['Bischof']['id'];

        $diocese = $model->diocese;
        $office = $model->office;
        $year = $model->year;
        $name = $model->name;
        $someid = $model->someid;

        $qb = $this->createQueryBuilder('i')
                   ->join('App\Entity\Person', 'p', 'WITH', 'i.id = p.id')
                   ->andWhere('i.itemTypeId = :itemTypeBishop')
                   ->setParameter(':itemTypeBishop', $itemTypeBishop);

        $qb = $this->addBishopConditions($qb, $model);
        $qb = $this->addBishopFacets($qb, $model);

        if ($office || $diocese) {
            // sort: if diocese is a query condition, this filters personRoles
            $qb->select('i.id as personId, min(pr.dateSortKey) as dateSortKey')
               ->leftjoin('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = i.id')
               ->addGroupBy('pr.personId')
               ->addOrderBy('pr.dioceseName')
               ->addOrderBy('dateSortKey');
        } elseif ($model->isEmpty() || $name || $someid || $year) {
            $qb->select('i.id as personId', 'min(role_srt.dateSortKey) as dateSortKey')
               ->leftjoin('p.role', 'role_srt')
               ->addGroupBy('personId');
            if ($year) {
                $qb->addOrderBy('dateSortKey');
            }
        }

        $qb->addOrderBy('p.familyname')
           ->addOrderBy('p.givenname');

        if (($model->isEmpty() || $name || $someid) && !$year) {
            $qb->addOrderBy('dateSortKey');
        }

        $qb->addOrderBy('p.id');

        if ($limit > 0) {
            $qb->setMaxResults($limit)
               ->setFirstResult($offset);
        }
        $query = $qb->getQuery();

        $result =  $query->getResult();

        return array_column($result, 'personId');

    }


    private function addBishopConditions($qb, $model) {
        $diocese = $model->diocese;
        $office = $model->office;

        if ($diocese) {
            $qb->andWhere("(pr.dioceseName LIKE :paramDiocese ".
                          "OR CONCAT('erzbistum ', pr.dioceseName) LIKE :paramDiocese ".
                          "OR CONCAT('bistum ', pr.dioceseName) LIKE :paramDiocese) ")
               ->setParameter('paramDiocese', '%'.$diocese.'%');
        }

        if ($office) {
            $qb->andWhere("pr.roleName LIKE :q_office")
               ->setParameter('q_office', '%'.$office.'%');
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
            $qb->leftjoin('\App\Entity\CanonLookup', 'clu', 'WITH', 'clu.personIdName = p.id')
               ->join('\App\Entity\NameLookup',
                      'nlu',
                      'WITH',
                      'clu.personIdRole = nlu.personId OR p.id = nlu.personId')
               ->andWhere("nlu.gnFn LIKE :q_name OR nlu.gnPrefixFn LIKE :q_name")
               ->setParameter('q_name', '%'.$name.'%');
        }

        $someid = $model->someid;
        if ($someid) {
            $qb->join('i.idExternal', 'ixt')
               ->andWhere("i.idPublic LIKE :q_id ".
                          "OR ixt.value LIKE :q_id")
               ->setParameter('q_id', '%'.$someid.'%');
        }

        $isDeleted = $model->isDeleted;
        if ($isDeleted) {
            $qb->andWhere("i.isDeleted = 1");
        } else {
            $qb->andWhere("i.isDeleted = 0");
        }

        $editStatus = array_filter(array_values($model->editStatus));
        if ($editStatus) {
            $qb->andWhere("i.editStatus in (:q_status)")
               ->setParameter('q_status', $editStatus);
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

        $dateCreated = UtilService::parseDateRange($model->dateCreated);
        if ($dateCreated) {
            if (count($dateCreated) > 1) {
                if ($dateCreated[0] && $dateCreated[1]) {
                    $qb->andWhere(":q_min <= i.dateCreated AND i.dateCreated <= :q_max")
                       ->setParameter('q_min', $dateCreated[0])
                       ->setParameter('q_max', $dateCreated[1]);
                } elseif ($dateCreated[0]) {
                    $qb->andWhere(":q_min <= i.dateCreated")
                       ->setParameter('q_min', $dateCreated[0]);
                } elseif ($dateCreated[1]) {
                    $qb->andWhere("i.dateCreated <= :q_max")
                       ->setParameter('q_max', $dateCreated[1]);
                }
            } else {
                if ($dateCreated[0]) {
                    $q_date_min = $dateCreated[0]->setTime(0, 0, 0);
                    $q_date_max = $dateCreated[0]->setTime(24, 59, 59);
                    $qb->andWhere(":q_min <= i.dateCreated AND i.dateCreated <= :q_max")
                       ->setParameter('q_min', $q_date_min)
                       ->setParameter('q_max', $q_date_max);
                }
            }
        }

        $dateChanged = UtilService::parseDateRange($model->dateChanged);
        if ($dateChanged) {
            if (count($dateChanged) > 1) {
                if ($dateChanged[0] && $dateChanged[1]) {
                    $qb->andWhere(":q_min <= i.dateChanged AND i.dateChanged <= :q_max")
                       ->setParameter('q_min', $dateChanged[0])
                       ->setParameter('q_max', $dateChanged[1]);
                } elseif ($dateChanged[0]) {
                    $qb->andWhere(":q_min <= i.dateChanged")
                       ->setParameter('q_min', $dateChanged[0]);
                } elseif ($dateChanged[1]) {
                    $qb->andWhere("i.dateChanged <= :q_max")
                       ->setParameter('q_max', $dateChanged[1]);
                }
            } else {
                if ($dateChanged[0]) {
                    $q_date_min = $dateChanged[0]->setTime(0, 0, 0);
                    $q_date_max = $dateChanged[0]->setTime(24, 59, 59);
                    $qb->andWhere(":q_min <= i.dateChanged AND i.dateChanged <= :q_max")
                       ->setParameter('q_min', $q_date_min)
                       ->setParameter('q_max', $q_date_max);
                }
            }
        }


        return $qb;
    }

    /**
     * add conditions set by facets
     */
    private function addBishopFacets($qb, $model) {
        $itemTypeId = Item::ITEM_TYPE_ID['Bischof']['id'];

        $facetDiocese = isset($model->facetDiocese) ? $model->facetDiocese : null;
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
        $itemTypeId = Item::ITEM_TYPE_ID['Bischof']['id'];

        $qb = $this->createQueryBuilder('i')
                   ->select('DISTINCT prcount.dioceseName AS name, COUNT(DISTINCT(prcount.personId)) AS n')
            // ->join('i.person', 'p') # for form conditions
                   ->join('App\Entity\Person', 'p', 'WITH', 'i.id = p.id')
                   ->join('p.role', 'prcount')
                   ->join('p.role', 'pr') # for form conditions
                   ->andWhere("i.itemTypeId = ${itemTypeId}")
                   ->andWhere("prcount.dioceseName IS NOT NULL");

        $this->addBishopConditions($qb, $model);
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
                   ->andWhere("prcount.roleName IS NOT NULL");

        $this->addBishopConditions($qb, $model);
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
        $gsn = $item[0]->getIdExternalByAuthorityId($authorityGs);
        if (!is_null($gsn)) {
            // Each person from Germania Sacra should have an entry in table id_external with it's GSN.
            // If data are up to date at most one of these requests is successful.
            $itemTypeCanonGs = Item::ITEM_TYPE_ID['Domherr GS']['id'];
            $canonGs = $this->findByIdExternal($itemTypeCanonGs, $gsn, $authorityGs);
            $item = array_merge($item, $canonGs);

            $itemTypeBishopGs = Item::ITEM_TYPE_ID['Bischof GS']['id'];
            $bishopGs = $this->findByIdExternal($itemTypeBishopGs, $gsn, $authorityGs);
            $item = array_merge($item, $bishopGs);

        }

        // get item from Domherrendatenbank
        $authorityWIAG = Authority::ID['WIAG-ID'];
        $wiagid = $item[0]->getIdPublic();
        if (!is_null($wiagid)) {
            $itemTypeCanon = Item::ITEM_TYPE_ID['Domherr']['id'];
            $canon = $this->findByIdExternal($itemTypeCanon, $wiagid, $authorityWIAG);
            $item = array_merge($item, $canon);
        }


        // set places and references and authorities in one query
        $em = $this->getEntityManager();
        $id_list = array_map(function ($i) {
            return $i->getId();
        }, $item);

        $personRepository = $em->getRepository(Person::class);
        $personRole = $personRepository->findByIdList($id_list);

        $em->getRepository(PersonRole::class)->setPlaceNameInRole($personRole);

        $item_role = array_map(function($p) {return $p->getItem();}, $personRole);
        $em->getRepository(ReferenceVolume::class)->setReferenceVolume($item_role);
        $em->getRepository(Authority::class)->setAuthority($item_role);

        return $personRole;

        // version before 2022-07-21
        // // get office data and references
        // $personRoleRepository = $em->getRepository(PersonRole::class);
        // $referenceVolumeRepository = $em->getRepository(ReferenceVolume::class);
        // foreach ($item as $item_loop) {
        //     $item_id = $item_loop->getId();
        //     $person = $item_loop->getPerson();
        //     $person->setRole($personRoleRepository->findRoleWithPlace($item_id));
        //     $referenceVolumeRepository->addReferenceVolumes($item_loop);
        // }

        // return $item;
    }


    /**
     * AJAX
     */
    public function suggestBishopName($name, $hintSize) {
        $qb = $this->createQueryBuilder('i')
                   ->select("DISTINCT CASE WHEN n.gnPrefixFn IS NOT NULL ".
                            "THEN n.gnPrefixFn ELSE n.gnFn END ".
                            "AS suggestion")
                   ->join('App\Entity\Person', 'p', 'WITH', 'i.id = p.id')
                   ->leftjoin('App\Entity\CanonLookup', 'clu', 'WITH', 'clu.personIdName = p.id')
                   ->join('App\Entity\NameLookup', 'n', 'WITH', 'i.id = n.personId OR clu.personIdRole = n.personId')
                   ->andWhere('i.itemTypeId = :itemType')
                   ->setParameter(':itemType', Item::ITEM_TYPE_ID['Bischof']['id'])
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
    public function suggestBishopDiocese($name, $hintSize) {
        $qb = $this->createQueryBuilder('i')
                   ->select("DISTINCT pr.dioceseName AS suggestion")
                   ->join('App\Entity\PersonRole', 'pr', 'WITH', 'i.id = pr.personId')
                   ->andWhere('i.itemTypeId = :itemType')
                   ->setParameter(':itemType', Item::ITEM_TYPE_ID['Bischof']['id'])
                   ->andWhere('pr.dioceseName like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestBishopOffice($name, $hintSize) {
        $qb = $this->createQueryBuilder('i')
                   ->select("DISTINCT pr.roleName AS suggestion")
                   ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = i.id')
                   ->andWhere('i.itemTypeId = :itemType')
                   ->setParameter(':itemType', Item::ITEM_TYPE_ID['Bischof']['id'])
                   ->andWhere('pr.roleName like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();
        // dd($suggestions);

        return $suggestions;
    }

    /**
     * JavaScript call
     */
    public function suggestBishopCommentDuplicate($name, $hintSize) {
        $qb = $this->createQueryBuilder('i')
                   ->select("DISTINCT i.commentDuplicate AS suggestion")
                   ->andWhere('i.itemTypeId = :itemType')
                   ->setParameter(':itemType', Item::ITEM_TYPE_ID['Bischof']['id'])
                   ->andWhere('i.commentDuplicate like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    public function countPriestUt_hide($model) {
        $result = array('n' => 0);

        $itemTypeId = Item::ITEM_TYPE_ID['Priester Utrecht'];


        $qb = $this->createQueryBuilder('i')
                       ->join('i.person', 'p')
                       ->select('COUNT(DISTINCT i.id) as n')
                       ->andWhere("i.itemTypeId = ${itemTypeId}")
                       ->andWhere('i.isOnline = 1');

        $qb = $this->addPriestUtConditions($qb, $model);
        $qb = $this->addPriestUtFacets($qb, $model);

        $query = $qb->getQuery();
        $result = $query->getOneOrNullResult();

        return $result;
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
                          'ip_ord_date.itemId = i.id AND ip_ord_date.name = :ordination')
                   ->andWhere('i.itemTypeId = :itemTypePriestUt')
                   ->andWhere('i.isOnline = 1')
                   ->setParameter(':ordination', 'ordination_priest')
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
            $qb->join('\App\Entity\NameLookup', 'nlu', 'WITH', 'p.id = nlu.personId')
               ->andWhere("nlu.gnFn LIKE :q_name OR nlu.gnPrefixFn LIKE :q_name")
               ->setParameter('q_name', '%'.$name.'%');
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
        $authorityWIAG = Authority::ID['WIAG-ID'];
        $wiagid = $person->getItem()->getIdPublic();
        $f_found = false;
        if (!is_null($wiagid) && $wiagid != "") {
            $itemTypeCanon = Item::ITEM_TYPE_ID['Domherr']['id'];
            $item = $this->findByIdExternal($itemTypeCanon, $wiagid, $authorityWIAG);
            if ($item) {
                $personRepository = $this->getEntityManager()->getRepository(Person::class);
                $sibling = $personRepository->find($item[0]->getId());
                $person->setSibling($sibling);
                $f_found = true;
            }
        }
        return $f_found;
    }

    public function findByIdExternal($itemTypeId, $value, $authId, $isonline = true) {
        $qb = $this->createQueryBuilder('i')
                   ->addSelect('i')
                   ->join('i.idExternal', 'ext')
                   ->andWhere('i.itemTypeId = :itemTypeId')
                   ->andWhere('ext.value = :value')
                   ->andWhere('ext.authorityId = :authId')
                   ->setParameter(':itemTypeId', $itemTypeId)
                   ->setParameter(':value', $value)
                   ->setParameter(':authId', $authId);

        if ($isonline) {
            $qb->andWhere('i.isOnline = 1');
        }

        $query = $qb->getQuery();
        $item = $query->getResult();

        return $item;
    }

    /**
     * 2022-07-21 obsolete?
     */
    // public function addReferenceVolumes($item) {
    //     $em = $this->getEntityManager();
    //     # add reference volumes (combined key)
    //     $repository = $em->getRepository(ReferenceVolume::class);
    //     foreach ($item->getReference() as $reference) {
    //         $itemTypeId = $reference->getItemTypeId();
    //         $referenceId = $reference->getReferenceId();
    //         $referenceVolume = $repository->findByCombinedKey($itemTypeId, $referenceId);
    //         $reference->setReferenceVolume($referenceVolume);
    //     }
    //     return $item;
    // }

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
                   ->setParameter(':itemType', Item::ITEM_TYPE_ID['Priester Utrecht'])
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

}
