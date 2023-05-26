<?php

namespace App\Service;

use App\Entity\Person;
use App\Entity\Diocese;
use App\Entity\Institution;
use App\Entity\Item;
use App\Entity\Role;
use App\Entity\ReferenceVolume;
use App\Entity\Authority;
use App\Entity\CanonLookup;


use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * provide autocomplete functions for Person forms
 */
class AutocompleteService extends ServiceEntityRepository {

    public function __construct(ManagerRegistry $registry, UtilService $utilService)
    {
        parent::__construct($registry, Person::class);

    }

    /**
     * usually used for asynchronous JavaScript request
     *
     * $item_type_id is not used here (needed for uniform signature)
     */
    public function suggestRole($itemTypeId, $queryParam, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(Role::class);
        $qb = $repository->createQueryBuilder('r')
                         ->select("DISTINCT r.name AS suggestion")
                         ->andWhere('r.name LIKE :name')
                         ->setParameter('name', '%'.$queryParam.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     *
     * $item_type_id is not used here (needed for uniform signature)
     */
    public function suggestOffice($itemTypeId, $queryParam, $hintSize, $online_only = true) {
        if ($itemTypeId == 4 or ($itemTypeId == 5 and !$online_only)) {
            $repository = $this->getEntityManager()->getRepository(Item::class);
            $qb = $repository->createQueryBuilder('i')
                             ->select("DISTINCT pr.roleName AS suggestion")
                             ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = i.id')
                             ->andWhere('i.itemTypeId = :itemType')
                             ->setParameter(':itemType', $itemTypeId)
                             ->andWhere('pr.roleName like :name')
                             ->setParameter(':name', '%'.$queryParam.'%')
                             ->orderBy('pr.roleName');
            if ($online_only) {
                $qb->andWhere('i.isOnline = 1');
            }
        } elseif ($itemTypeId == 5) {
            $repository = $this->getEntityManager()->getRepository(CanonLookup::class);
            $qb = $repository->createQueryBuilder('c')
                             ->select("DISTINCT pr.roleName AS suggestion")
                             ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = c.personIdRole')
                             ->andWhere('pr.roleName like :name')
                             ->setParameter('name', '%'.$queryParam.'%')
                             ->orderBy('pr.roleName');
        }

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestDiocese($name, $hint_size) {
        $repository = $this->getEntityManager()->getRepository(Diocese::class);
        $qb = $repository->createQueryBuilder('d')
                         ->select("DISTINCT d.name AS suggestion")
                         ->andWhere('d.name LIKE :name')
                         ->orderBy('d.name')
                         ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hint_size);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }


    /**
     * usually used for asynchronous JavaScript request
     *
     */
    public function suggestDomstift($queryParam, $hintSize) {
        $itemTypeIdDomstift = 3;
        $repository = $this->getEntityManager()->getRepository(CanonLookup::class);
        $qb = $repository->createQueryBuilder('c')
                         ->select("DISTINCT inst.name AS suggestion")
                         ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = c.personIdRole')
                         ->join('pr.institution', 'inst')
                         ->andWhere("inst.itemTypeId = $itemTypeIdDomstift")
                         ->andWhere('inst.name like :name')
                         ->setParameter('name', '%'.$queryParam.'%')
                         ->orderBy('inst.name');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     *
     * return the names of  monasteries and domstifte (type 2 und 3)
     */
    public function suggestInstitution($queryParam, $hintSize) {
        $itemTypeInst = [2, 3];

        // 2023-05-02 for editing the list should not be restricted to institution
        // that are already referenced by a canon
        // $repository = $this->getEntityManager()->getRepository(CanonLookup::class);
        // $qb = $repository->createQueryBuilder('c')
        //                  ->select("DISTINCT inst.name AS suggestion")
        //                  ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = c.personIdRole')
        //                  ->join('pr.institution', 'inst')
        //                  ->andWhere("inst.itemTypeId in (:itemTypeInst)")
        //                  ->andWhere('inst.name like :name')
        //                  ->setParameter('itemTypeInst', $itemTypeInst)
        //                  ->setParameter('name', '%'.$queryParam.'%')
        //                  ->orderBy('inst.name');

        $repository = $this->getEntityManager()->getRepository(Institution::class);
        $qb = $repository->createQueryBuilder('inst')
                         ->select("DISTINCT inst.name AS suggestion")
                         ->andWhere("inst.itemTypeId in (:itemTypeInst)")
                         ->andWhere('inst.name like :name')
                         ->setParameter('itemTypeInst', $itemTypeInst)
                         ->setParameter('name', '%'.$queryParam.'%')
                         ->orderBy('inst.name');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * autocomplete function for references
     *
     * usually used for asynchronous JavaScript request
     */
    public function suggestTitleShort($item_type_id, $name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(ReferenceVolume::class);
        $qb = $repository->createQueryBuilder('v')
                         ->select("DISTINCT v.titleShort AS suggestion")
                         ->andWhere('v.titleShort LIKE :name')
                         ->addOrderBy('v.titleShort')
                         ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestPropertyValue($item_type_id, $name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT prop.value AS suggestion")
                         ->join('i.itemProperty', 'prop')
                         ->andWhere('prop.value LIKE :name')
                         ->andWhere('i.itemTypeId = :item_type_id')
                         ->setParameter('item_type_id', $item_type_id)
                         ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();

        // dd($query->getDql());
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestRolePropertyName($item_type_id, $name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT prop.name AS suggestion")
                         ->join('App\Entity\PersonRoleProperty', 'prop', 'WITH', 'i.id = prop.personId')
                         ->andWhere('prop.name LIKE :name')
                         ->andWhere('i.itemTypeId = :item_type_id')
                         ->setParameter('item_type_id', $item_type_id)
                         ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestRolePropertyValue($item_type_id, $name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT prop.value AS suggestion")
                         ->join('App\Entity\PersonRoleProperty', 'prop', 'WITH', 'i.id = prop.personId')
                         ->andWhere('prop.value LIKE :name')
                         ->andWhere('i.itemTypeId = :item_type_id')
                         ->setParameter('item_type_id', $item_type_id)
                         ->setParameter('name', '%'.$name.'%');


        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestEditStatus($item_type_id, $name, $hintSize) {
        // do not filter by name 'i.editStatus LIKE :name'
        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT i.editStatus AS suggestion")
                         ->andWhere('i.itemTypeId = :item_type_id')
                         ->andWhere('i.editStatus IS NOT NULL')
                         ->andWhere("i.mergeStatus <> 'parent'")
                         ->setParameter('item_type_id', $item_type_id)
                         ->orderBy('i.editStatus');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestNormdataEditedBy($item_type_id, $name, $hintSize) {
        // do not filter by name 'i.editStatus LIKE :name'
        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT i.normdataEditedBy AS suggestion")
                         ->andWhere('i.itemTypeId = :item_type_id')
                         ->andWhere('i.normdataEditedBy like :name')
                         ->setParameter('item_type_id', $item_type_id)
                         ->setParameter('name', '%'.$name.'%')
                         ->orderBy('i.normdataEditedBy');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestName($itemTypeId, $queryParam, $resultSize, $online_only = true) {
        // canons: case $online_only == false is relevant for the editing query
        if ($itemTypeId == 4 or ($itemTypeId == 5 and !$online_only)) {
            $qb = $this->createQueryBuilder('p')
                       ->select("DISTINCT CASE WHEN n.gnPrefixFn IS NOT NULL ".
                                "THEN n.gnPrefixFn ELSE n.gnFn END ".
                                "AS suggestion")
                       ->join('App\Entity\Item', 'i', 'WITH', 'i.id = p.id')
                       ->leftjoin('App\Entity\CanonLookup', 'clu', 'WITH', 'clu.personIdName = p.id')
                       ->join('App\Entity\NameLookup', 'n', 'WITH', 'i.id = n.personId OR clu.personIdRole = n.personId')
                       ->andWhere('i.itemTypeId = :itemType')
                       ->setParameter(':itemType', $itemTypeId)
                       ->andWhere('n.gnFn LIKE :name OR n.gnPrefixFn LIKE :name')
                       ->setParameter(':name', '%'.$queryParam.'%');
            if ($online_only) {
                $qb->andWhere('i.isOnline = 1');
            }
        } elseif ($itemTypeId == 5) {
            $repository = $this->getEntityManager()->getRepository(CanonLookup::class);
            $qb = $repository->createQueryBuilder('c')
                             ->select("DISTINCT CASE WHEN n.gnPrefixFn IS NOT NULL ".
                                      "THEN n.gnPrefixFn ELSE n.gnFn END ".
                                      "AS suggestion")
                             ->join('App\Entity\NameLookup', 'n', 'WITH', 'n.personId = c.personIdRole')
                             ->andWhere('n.gnPrefixFn LIKE :name OR n.gnFn LIKE :name')
                             ->setParameter('name', '%'.$queryParam.'%');

        }


        $qb->setMaxResults($resultSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    public function suggestCommentDuplicate($itemTypeId, $queryParam, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT i.commentDuplicate AS suggestion")
                         ->andWhere('i.itemTypeId = :itemType')
                         ->setParameter(':itemType', $itemTypeId)
                         ->andWhere('i.commentDuplicate like :name')
                         ->setParameter(':name', '%'.$queryParam.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestPlace($itemTypeId, $queryParam, $hintSize, $online_only = false) {
        if ($itemTypeId == 5) {
            if ($online_only) {
                $repository = $this->getEntityManager()->getRepository(CanonLookup::class);
                $qb = $repository->createQueryBuilder('c')
                                 ->select("DISTINCT ip.placeName AS suggestion")
                                 ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.personId = c.personIdRole')
                                 ->join('App\Entity\InstitutionPlace', 'ip', 'WITH', 'ip.institutionId = pr.institutionId')
                                 ->andWhere('ip.placeName like :name')
                                 ->setParameter('name', '%'.$queryParam.'%');
            } else {
                $qb = $this->createQueryBuilder('p')
                           ->select("DISTINCT ip.placeName AS suggestion")
                           ->join('p.role', 'pr')
                           ->join('App\Entity\InstitutionPlace', 'ip', 'WITH', 'ip.institutionId = pr.institutionId')
                           ->andWhere('ip.placeName like :name')
                           ->setParameter('name', '%'.$queryParam.'%');
            }

            $qb->setMaxResults($hintSize);

            $query = $qb->getQuery();
            $suggestions = $query->getResult();

            return $suggestions;
        } else { // not relevent up to now 2023-03-30
            return array();
        }
    }

    /**
     * @return list of WIAG-IDs of bishops
     */
    public function suggestBishop($queryParam, $hint_size) {
        $repository = $this->getEntityManager()->getRepository(Item::class);

        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT i.idPublic AS suggestion")
                         ->andWhere('i.idPublic LIKE :queryParam')
                         ->andWhere("i.itemTypeId = 4")
                         ->setParameter('queryParam', '%'.$queryParam.'%');

        $qb->setMaxResults($hint_size);

        $query = $qb->getQuery();

        $suggestions = $query->getResult();

        return $suggestions;
    }


    public function suggestUrlName($name, $hint_size) {
        // exclude core data and internal references
        $core_id_list = Authority::ESSENTIAL_ID_LIST;

        $repository = $this->getEntityManager()->getRepository(Authority::class);
        $qb = $repository->createQueryBuilder('a')
                         ->select("DISTINCT a.urlNameFormatter AS suggestion")
                         ->andWhere('a.urlNameFormatter LIKE :name')
                         ->andWhere('a.id not in (:core)')
                         ->andWhere("a.urlType != 'Interner Identifier'")
                         ->addOrderBy('a.urlNameFormatter')
                         ->setParameter('core', $core_id_list)
                         ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hint_size);

        $query = $qb->getQuery();

        $suggestions = $query->getResult();

        return $suggestions;
    }


}
