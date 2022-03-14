<?php

namespace App\Repository;

use App\Entity\CanonLookup;
use App\Entity\Item;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\InstitutionPlace;
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

    public function canonIds($model, $limit = 0, $offset = 0) {
        $result = null;

        $itemTypeId = Item::ITEM_TYPE_ID['Domherr'];
        $institutionItemTypeId = Item::ITEM_TYPE_ID['Domstift'];

        $domstift = $model->domstift;
        $office = $model->office;
        $place = $model->place;
        $year = $model->year;
        $name = $model->name;
        $someid = $model->someid;
        if ($model->isEmpty() || $domstift || $office) {
            // sort: if domstift is a query condition, this filters personRoles
            // we need not to check for item.is_online because the is guaranteed for entries in canon_lookup


            $qb = $this->createQueryBuilder('c')
                       ->select('c.personIdName, 0 as sortA')
                       ->join('app\Entity\Person', 'p', 'WITH', 'p.id = c.personIdName')
                       ->join('App\Entity\PersonRole', 'role_srt', 'WITH', 'role_srt.personId = c.personIdRole')
                // whithout this join, the query time is below 100 ms
                       // ->join('App\Entity\ItemProperty',
                       //        'prp',
                       //        'WITH', "role_srt.institutionId = prp.itemId AND prp.name = 'domstift_short'")
                       ->groupBy('c.personIdName')
                       ->addOrderBy('sortA')
                       ->addOrderBy('p.familyname')
                       ->addOrderBy('p.givenname')
                       ->addOrderBy('p.id');
            // 2022-03-10 TODO add dateSortKey
        } elseif ($place) {
            $qb = $this->createQueryBuilder('i')
                       ->select('i.id, i.itemTypeId, min(role_place.numDateBegin) as sort')
                       ->join('App\Entity\CanonLookup', 'cr', 'WITH', 'i.id = cr.personIdCanon')
                       ->join('i.person', 'p')
                       ->join('p.role', 'role_place')
                       ->join('App\Entity\InstitutionPlace', 'ip', 'WITH', 'ip.institutionId = role_place.institutionId')
                       ->andWhere('i.isOnline = 1')
                       ->addGroupBy('i.id')
                       ->addOrderBy('ip.placeName')
                       ->addOrderBy('sort')
                       ->addOrderBy('p.familyname')
                       ->addOrderBy('p.givenname')
                       ->addOrderBy('p.id');
        } elseif ($year) {
            $qb = $this->createQueryBuilder('i')
                       ->select('i.id')
                       ->join('i.person', 'p')
                       ->join('App\Entity\CanonLookup', 'cr', 'WITH', 'i.id = cr.personIdCanon')
                       ->andWhere('i.isOnline = 1')
                       ->addOrderBy('p.dateMin')
                       ->addOrderBy('p.dateMax')
                       ->addOrderBy('p.familyname')
                       ->addOrderBy('p.givenname')
                       ->addOrderBy('p.id');
        } elseif ($name || $someid) {
            $qb = $this->createQueryBuilder('i')
                       ->select('DISTINCT(cr.personIdCanon) AS id')
                       ->join('App\Entity\CanonLookup', 'cr', 'WITH', 'i.id = cr.personIdCanon')
                       ->join('i.person', 'p')
                       ->andWhere('i.isOnline = 1')
                       ->addGroupBy('p.id')
                       ->addOrderBy('p.familyname')
                       ->addOrderBy('p.givenname')
                       ->addOrderBy('p.id');
        }

        // TODO 2022-03-10
        // $qb = $this->addCanonConditions($qb, $model);
        // TODO 2022-02-15
        // $qb = $this->addCanonFacets($qb, $model);

        if ($limit > 0) {
            $qb->setMaxResults($limit)
               ->setFirstResult($offset);
        }
        $query = $qb->getQuery();

        // $ids = array_map(function($a) { return $a["id"]; },
        //                  $query->getResult());

        return $query->getResult();

    }

    public function findWithOffice($id) {
        $qb = $this->createQueryBuilder('c')
                   ->addSelect('p')
                   ->addSelect('r')
                   ->join('App\Entity\Person', 'p', 'WITH', 'c.personIdName = p.id')
                   ->join('App\Entity\PersonRole', 'r', 'WITH', 'c.personIdRole = r.personId')
                   ->andWhere('c.personIdName = :id')
                   ->andWhere('c.prioRole = 1')
                   ->setParameter('id', $id);

        $query = $qb->getQuery();

        $result = $query->getResult();

        $institutionPlaceRepository = $this->getEntityManager()
                                           ->getRepository(InstitutionPlace::class);

        $canon = null;
        $person = null;
        $role = array();
        foreach ($result as $r) {
            if (is_a($r, CanonLookup::class)) {
                $canon = $r;
            }
            if (is_a($r, Person::class)) {
                $person = $r;
            }
            if (is_a($r, PersonRole::class)) {
                $r->setPlaceName($institutionPlaceRepository->findPlaceForRole($r));
                $role[] = $r;
            }
        }

        if (!is_null($canon)) {
            $canon->setPerson($person);
            $canon->setRoleListView($role);
        }

        return $canon;

    }

}
