<?php

namespace App\Repository;

use App\Entity\CanonLookup;
use App\Entity\Institution;
use App\Entity\Item;
use App\Entity\CanonGroup;
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
     * return number of canons matching the criteria in `model`
     */
    public function countCanon($model) {
        $result = array('n' => 0);
        if ($model->isEmpty()) return $result;

        $itemTypeId = Item::ITEM_TYPE_ID['Domherr'];


        // diocese or office
        $domstift = $model->domstift;
        $office = $model->office;
        $place = $model->place;
        $year = $model->year;
        $name = $model->name;
        $someid = $model ->someid;
        if ($domstift || $office) { # 2022-01-27 TODO
            $qb = $this->createQueryBuilder('i')
                       ->join('i.personRole', 'pr')
                       ->select('COUNT(DISTINCT i.id) as n')
                       ->andWhere("i.itemTypeId = ${itemTypeId}")
                       ->andWhere('i.isOnline = 1');
            if($year) {
                $qb->join('i.person', 'p');
            }
        } elseif ($year) { # 2022-01-27 TODO
            $qb = $this->createQueryBuilder('i')
                       ->join('i.person', 'p')
                       ->select('COUNT(DISTINCT i.id) as n')
                       ->andWhere("i.itemTypeId = ${itemTypeId}")
                       ->andWhere('i.isOnline = 1');
        } elseif ($name || $someid) { # 2022-01-27 start here
            $qb = $this->createQueryBuilder('cr')
                       ->select('COUNT(DISTINCT cr.personIdCanon) as n');
            /* all entries in canon_lookup.person_id_canon should be online */
        }

        $qb = $this->addCanonConditions($qb, $model);
        // $qb = $this->addCanonFacets($qb, $model);

        $query = $qb->getQuery();
        $result = $query->getOneOrNullResult();

        return $result;
    }

    public function canonIds($model, $limit = 0, $offset = 0) {
        $result = null;
        if ($model->isEmpty()) return $result;

        $itemTypeId = Item::ITEM_TYPE_ID['Domherr'];

        $domstift = $model->domstift;
        $office = $model->office;
        $year = $model->year;
        $name = $model->name;
        $someid = $model->someid;
        if ($office || $domstift) { # ###
            $qb = $this->createQueryBuilder('cr')
                       ->select('i.id, min(pr.numDateBegin) as sort')
                       ->join('i.personRole', 'pr')
                       ->join('i.person', 'p')
                       ->andWhere("i.itemTypeId = ${itemTypeId}")
                       ->andWhere('i.isOnline = 1')
                       ->addGroupBy('pr.personId')
                       ->addOrderBy('pr.dioceseName')
                       ->addOrderBy('sort')
                       ->addOrderBy('p.familyname')
                       ->addOrderBy('p.givenname')
                       ->addOrderBy('p.id');
        } elseif ($year) { # ###
            $qb = $this->createQueryBuilder('cr')
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
        } elseif ($name || $someid) {
            $qb = $this->createQueryBuilder('cr')
                       ->select('DISTINCT(cr.personIdCanon) AS id')
                       ->join('cr.person', 'p')
                       ->addGroupBy('p.id')
                       ->addOrderBy('p.familyname')
                       ->addOrderBy('p.givenname')
                       ->addOrderBy('p.id');
        }


        $qb = $this->addCanonConditions($qb, $model);
        // $qb = $this->addCanonFacets($qb, $model);

        $qb->setMaxResults($limit)
           ->setFirstResult($offset);
        $query = $qb->getQuery();

        $ids = array_map(function($a) { return $a["id"]; },
                         $query->getResult());

        return $ids;

    }

    private function addCanonConditions($qb, $model) {
        $domstift = $model->domstift;
        $office = $model->office;

        if ($domstift) { # TODO 2022-01-27
            $qb->andWhere("(pr.dioceseName LIKE :paramDiocese ".
                          "OR CONCAT('erzbistum ', pr.dioceseName) LIKE :paramDiocese ".
                          "OR CONCAT('bistum ', pr.dioceseName) LIKE :paramDiocese) ")
               ->setParameter('paramDiocese', '%'.$diocese.'%');
        }

        if ($office) { # TODO 2022-01-27
            $qb->andWhere("pr.roleName LIKE :value")
               ->setParameter('value', '%'.$office.'%');
        }

        $year = $model->year;
        if ($year) { # TODO 2022-01-27
            $qb->andWhere("p.dateMin - :mgnyear < :value ".
                          " AND :value < p.dateMax + :mgnyear")
               ->setParameter(':mgnyear', self::MARGINYEAR)
               ->setParameter('value', $year);
        }

        $name = $model->name;
        if ($name) {
            $qb->join('App\Entity\NameLookup', 'nl', 'WITH', 'cr.personId = nl.personId')
               ->andWhere("nl.gnFn LIKE :qname OR nl.gnPrefixFn LIKE :qname")
               ->setParameter('qname', '%'.$name.'%');
        }

        $someid = $model->someid;
        if ($someid) {
            $qb->join('i.idExternal', 'ixt')
               ->andWhere("i.idPublic LIKE :value ".
                          "OR ixt.value LIKE :value")
               ->setParameter('value', '%'.$someid.'%');
        }
        return $qb;
    }

    /**
     * find all items that are related to the same canon
     */
    function findRelatedCanon($id) {
        $cnGroup = new CanonGroup();

        $qb = $this->createQueryBuilder('cr')
                   ->join('\App\Entity\Item', 'i', 'WITH', 'i.id = cr.personId')
                   ->select('cr.personId AS id, i.itemTypeId')
                   ->andWhere('cr.personIdCanon = :id')
                   ->setParameter('id', $id);

        $query = $qb->getQuery();

        $result = $query->getResult();

        $typeMap = [4 => 'Ep', 5 => 'Dh', 6 => 'Gs'];
        foreach($result as $r) {
            $elementName = 'id'.$typeMap[$r['itemTypeId']];
            $cnGroup->$elementName = $r['id'];
        }
        return $cnGroup;
    }

    /**
     * countCanonDomstift($model)
     *
     * return array of dioceses related to a person's role (used for facet)
     */
    public function countCanonDomstift($model) {
        $itemTypeId = Item::ITEM_TYPE_ID['Domherr'];

        return [['name' => 'Augsburg', 'n' => 7],
                ['name' => 'Bambert', 'n' => 3]];

        # 2022-01-27 TODO update rest of the function

        $qb = $this->createQueryBuilder('cr')
                   ->select('DISTINCT prcount.dioceseName AS name, COUNT(DISTINCT(prcount.personId)) as n')
                   ->join('i.personRole', 'prcount')
                   ->join('i.personRole', 'pr') # for form conditions
                   ->join('i.person', 'p') # for form conditions
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
     * countCanonOffice($model)
     *
     * return array of offices related to a person's role (used for facet)
     */
    public function countCanonOffice($model) {
        $itemTypeId = Item::ITEM_TYPE_ID['Domherr'];

        return [['name' => 'Domherr', 'n' => 7],
                ['name' => 'Scholaster', 'n' => 3]];

        # 2022-01-27 TODO update rest of the function
        $qb = $this->createQueryBuilder('cr')
                   ->select('DISTINCT prcount.roleName AS name, COUNT(DISTINCT(prcount.personId)) as n')
                   ->join('i.personRole', 'prcount')
                   ->join('i.personRole', 'pr') # for form conditions
                   ->join('i.person', 'p') # for form conditions
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
    public function suggestCanonName($name, $hintSize) {
        $qb = $this->createQueryBuilder('cl')
                   ->select("DISTINCT CASE WHEN n.gnPrefixFn IS NOT NULL ".
                            "THEN n.gnPrefixFn ELSE n.gnFn END ".
                            "AS suggestion")
                   ->join('App\Entity\NameLookup', 'n', 'WITH', 'n.personId = cl.personId')
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
    public function suggestCanonDomstift($name, $hintSize) {
        /* in order to have a uniform function call in the Controller we start here
         * instead of InstitutionRepository
         */
        $repository = $this->getEntityManager()->getRepository(Institution::class);

        $qb = $repository->createQueryBuilder('it')
                         ->select("DISTINCT it.name AS suggestion")
                         ->join('it.institutionPlace', 'ip')
                         ->join('it.item','i')
                         ->andWhere('i.itemTypeId = :itemType')
                         ->setParameter(':itemType', Item::ITEM_TYPE_ID['Domstift'])
                         ->andWhere('it.name LIKE :name OR ip.placeName LIKE :name')
                         ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestCanonOffice($name, $hintSize) {
        $qb = $this->createQueryBuilder('cl')
                   ->select("DISTINCT pr.roleName AS suggestion")
                   ->join('App\Entity\PersonRole', 'pr', 'WITH', 'cl.personId = pr.personId')
                   ->andWhere('pr.roleName like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();
        // dd($suggestions);

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestCanonPlace($name, $hintSize) {
        $qb = $this->createQueryBuilder('cl')
                   ->select("DISTINCT ip.placeName AS suggestion")
                   ->join('App\Entity\PersonRole', 'pr', 'WITH', 'cl.personId = pr.personId')
                   ->join('App\Entity\Institution', 'it', 'WITH', 'it.id = pr.institutionId')
                   ->join('it.institutionPlace', 'ip')
                   ->andWhere('ip.placeName like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();
        // dd($suggestions);

        return $suggestions;
    }

}
