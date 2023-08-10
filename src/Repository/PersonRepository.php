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
