<?php

namespace App\Repository;

use App\Entity\Item;
use App\Entity\PersonRole;
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
                   ->join('\App\Entity\ReferenceVolume', 'r', 'WITH', 'ir.referenceVolumeId = r.id')
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

        $itemTypeId = Item::ITEM_TYPE_ID['Bischof'];

        $diocese = $model->diocese;
        $office = $model->office;
        $year = $model->year;
        $name = $model->name;
        $someid = $model->someid;

        if ($office || $diocese) {
            // sort: if diocese is a query condition, this filters personRoles
            $qb = $this->createQueryBuilder('i')
                       ->select('i.id, min(pr.numDateBegin) as sort')
                       ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = i.id')
                       ->join('i.person', 'p')
                       ->andWhere("i.itemTypeId = ${itemTypeId}")
                       ->andWhere('i.isOnline = 1')
                       ->addGroupBy('pr.personId')
                       ->addOrderBy('pr.dioceseName')
                       ->addOrderBy('sort')
                       ->addOrderBy('p.familyname')
                       ->addOrderBy('p.givenname')
                       ->addOrderBy('p.id');
        } elseif ($year) {
            $qb = $this->createQueryBuilder('i')
                       ->select('i.id')
                       ->join('i.person', 'p')
                       ->andWhere("i.itemTypeId = ${itemTypeId}")
                       ->andWhere('i.isOnline = 1')
                       ->addGroupBy('p.id')
                       ->addOrderBy('p.dateMin')
                       ->addOrderBy('p.dateMax')
                       ->addOrderBy('p.familyname')
                       ->addOrderBy('p.givenname')
                       ->addOrderBy('p.id');
        } elseif ($model->isEmpty() || $name || $someid) {
            $qb = $this->createQueryBuilder('i')
                       ->select('i.id')
                       ->join('i.person', 'p')
                       ->andWhere("i.itemTypeId = ${itemTypeId}")
                       ->andWhere('i.isOnline = 1')
                       ->addGroupBy('p.id')
                       ->addOrderBy('p.familyname')
                       ->addOrderBy('p.givenname')
                       ->addOrderBy('p.id');
        }

        $qb = $this->addBishopConditions($qb, $model);
        $qb = $this->addBishopFacets($qb, $model);

        $qb->setMaxResults($limit)
           ->setFirstResult($offset);
        $query = $qb->getQuery();

        $ids = array_map(function($a) { return $a["id"]; },
                         $query->getResult());
        return $ids;

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
               ->andWhere("nlu.gnFn LIKE :qname OR nlu.gnPrefixFn LIKE :q_name")
               ->setParameter('q_name', '%'.$name.'%');
        }

        $someid = $model->someid;
        if ($someid) {
            $qb->join('i.idExternal', 'ixt')
               ->andWhere("i.idPublic LIKE :q_id ".
                          "OR ixt.value LIKE :value")
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


}
