<?php

namespace App\Repository;

use App\Entity\Item;
use App\Entity\ItemProperty;
use App\Entity\PersonRole;
use App\Entity\ReferenceVolume;
use App\Entity\PersonBirthplace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    public function countBishop($model) {
        $result = array('n' => 0);

        $itemTypeId = Item::ITEM_TYPE_ID['Bischof'];

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
                $qb->join('i.person', 'p');
            }
        } elseif ($year) {
            $qb = $this->createQueryBuilder('i')
                       ->join('i.person', 'p')
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

        $itemTypeBishop = Item::ITEM_TYPE_ID['Bischof'];

        $diocese = $model->diocese;
        $office = $model->office;
        $year = $model->year;
        $name = $model->name;
        $someid = $model->someid;

        $qb = $this->createQueryBuilder('i')
                   ->join('i.person', 'p')
                   ->andWhere('i.itemTypeId = :itemTypeBishop')
                   ->andWhere('i.isOnline = 1')
                   ->setParameter(':itemTypeBishop', $itemTypeBishop);

        $qb = $this->addBishopConditions($qb, $model);
        $qb = $this->addBishopFacets($qb, $model);

        if ($office || $diocese) {
            // sort: if diocese is a query condition, this filters personRoles
            $qb->select('i.id as personId, min(pr.dateSortKey) as dateSortKey')
               ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = i.id')
               ->addGroupBy('pr.personId')
               ->addOrderBy('pr.dioceseName')
               ->addOrderBy('dateSortKey');
        } elseif ($model->isEmpty() || $name || $someid || $year) {
            $qb->select('i.id as personId', 'min(role_srt.dateSortKey) as dateSortKey')
               ->join('p.role', 'role_srt')
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

        return $query->getResult();

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
            $qb->join('i.nameLookup', 'nlu')
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
        return $qb;
    }

    /**
     * add conditions set by facets
     */
    private function addBishopFacets($qb, $model) {
        $itemTypeId = Item::ITEM_TYPE_ID['Bischof'];

        $facetDiocese = $model->facetDiocese;
        if ($facetDiocese) {
            $valFctDioc = array_column($facetDiocese, 'name');
            $qb->join('App\Entity\PersonRole', 'prfctdioc', 'WITH', 'prfctdioc.personId = i.id')
               ->andWhere("i.itemTypeId = ${itemTypeId}")
               ->andWhere('i.isOnline = 1')
               ->andWhere("prfctdioc.dioceseName IN (:valFctDioc)")
               ->setParameter('valFctDioc', $valFctDioc);
        }

        $facetOffice = $model->facetOffice;
        if ($facetOffice) {
            $valFctOfc = array_column($facetOffice, 'name');
            $qb->join('App\Entity\PersonRole', 'prfctofc', 'WITH', 'prfctofc.personId = i.id')
               ->andWhere("i.itemTypeId = ${itemTypeId}")
               ->andWhere('i.isOnline = 1')
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
        $itemTypeId = Item::ITEM_TYPE_ID['Bischof'];

        $qb = $this->createQueryBuilder('i')
                   ->select('DISTINCT prcount.dioceseName AS name, COUNT(DISTINCT(prcount.personId)) AS n')
                   ->join('i.person', 'p') # for form conditions
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
        $itemTypeId = Item::ITEM_TYPE_ID['Bischof'];

        $qb = $this->createQueryBuilder('i')
                   ->select('DISTINCT prcount.roleName AS name, COUNT(DISTINCT(prcount.personId)) as n')
                   ->join('i.person', 'p') # for form conditions
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
     * AJAX
     */
    public function suggestBishopName($name, $hintSize) {
        $qb = $this->createQueryBuilder('i')
                   ->select("DISTINCT CASE WHEN n.gnPrefixFn IS NOT NULL ".
                            "THEN n.gnPrefixFn ELSE n.gnFn END ".
                            "AS suggestion")
                   ->join('i.nameLookup', 'n')
                   ->andWhere('i.itemTypeId = :itemType')
                   ->setParameter(':itemType', Item::ITEM_TYPE_ID['Bischof'])
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
                   ->setParameter(':itemType', Item::ITEM_TYPE_ID['Bischof'])
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
                   ->setParameter(':itemType', Item::ITEM_TYPE_ID['Bischof'])
                   ->andWhere('pr.roleName like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();
        // dd($suggestions);

        return $suggestions;
    }

    public function countPriestUt($model) {
        $result = array('n' => 0);

        $itemTypeId = Item::ITEM_TYPE_ID['Priester Utrecht'];


        $qb = $this->createQueryBuilder('i')
                       ->join('i.person', 'p')
                       ->select('COUNT(DISTINCT i.id) as n')
                       ->andWhere("i.itemTypeId = ${itemTypeId}")
                       ->andWhere('i.isOnline = 1');

        $qb = $this->addPriestUtConditions($qb, $model);
        # $qb = $this->addBishopFacets($qb, $model);

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
                   ->join('i.person', 'p')
                   ->join('\App\Entity\ItemProperty',
                          'ip_ord_date',
                          'WITH',
                          'ip_ord_date.itemId = i.id AND ip_ord_date.name = :ordination')
                   ->andWhere('i.itemTypeId = :itemTypePriestUt')
                   ->andWhere('i.isOnline = 1')
                   ->setParameter(':ordination', 'ordination_priest')
                   ->setParameter(':itemTypePriestUt', $itemTypePriestUt);

        $qb = $this->addPriestUtConditions($qb, $model);
        // TODO $qb = $this->addPriestUtFacets($qb, $model);

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
        return $query->getResult();

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
            $qb->join('i.nameLookup', 'nlu')
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

    public function addReferenceVolumes($item) {
        $em = $this->getEntityManager();
        # add reference volumes (combined key)
        $repository = $em->getRepository(ReferenceVolume::class);
        foreach ($item->getReference() as $reference) {
            $itemTypeId = $reference->getItemTypeId();
            $referenceId = $reference->getReferenceId();
            $referenceVolume = $repository->findByCombinedKey($itemTypeId, $referenceId);
            $reference->setReferenceVolume($referenceVolume);
        }
        return $item;
    }



    /**
     * AJAX
     */
    public function suggestPriestUtName($name, $hintSize) {
        $qb = $this->createQueryBuilder('i')
                   ->select("DISTINCT CASE WHEN n.gnPrefixFn IS NOT NULL ".
                            "THEN n.gnPrefixFn ELSE n.gnFn END ".
                            "AS suggestion")
                   ->join('i.nameLookup', 'n')
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
                   ->join('i.person', 'p')
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

}
