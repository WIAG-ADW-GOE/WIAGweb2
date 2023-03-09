<?php

namespace App\Repository;

use App\Entity\Item;
use App\Entity\ItemProperty;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\Role;
use App\Entity\Diocese;
use App\Entity\Institution;
use App\Entity\ReferenceVolume;
use App\Entity\InstitutionPlace;
use App\Entity\Authority;
use App\Entity\PlaceIdExternal;
use App\Entity\UrlExternal;
use App\Form\Model\BishopFormModel;
use App\Service\UtilService;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;


/**
 * @method Person|null find($id, $lockMode = null, $lockVersion = null)
 * @method Person|null findOneBy(array $criteria, array $orderBy = null)
 * @method Person[]    findAll()
 * @method Person[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonRepository extends ServiceEntityRepository {

    private $utilService;

    public function __construct(ManagerRegistry $registry, UtilService $utilService)
    {
        parent::__construct($registry, Person::class);

        $this->utilService = $utilService;
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
    public function findList($id_list) {
        $qb = $this->createQueryBuilder('p')
                   ->select('p, i, bp, role, role_type, institution, idext')
                   ->join('p.item', 'i') # avoid query in twig ...
                   ->leftjoin('i.itemProperty', 'ip')
                   ->leftjoin('i.idExternal', 'idext')
                   ->leftjoin('p.birthplace', 'bp')
                   ->leftjoin('p.role', 'role')
                   ->leftjoin('role.role', 'role_type')
                   ->leftjoin('role.institution', 'institution')
                   ->andWhere('p.id in (:id_list)')
                   ->andWhere('i.isDeleted = 0')
                   ->addOrderBy('role.dateSortKey')
                   ->addOrderBy('role.id')
                   ->setParameter('id_list', $id_list);

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
        // set reference volumes
        $em->getRepository(ReferenceVolume::class)->setReferenceVolume($item_list);

        // set authorities
        $em->getRepository(Authority::class)->setAuthority($item_list);

        // restore order as in $id_list
        $person_list = $this->utilService->reorder($person_list, $id_list, "id");

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
     * setPersonName($canon_list)
     *
     * set personName foreach element of `$canon_list`.
     */
    public function setPersonName($canon_list) {

        $id_list = array_map(function($el) {return $el->getPersonIdName();}, $canon_list);

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

        // set personName, personName->sibling, personName->urlByType, personName->sibling->urlByType
        $em = $this->getEntityManager();
        $itemRepository = $em->getRepository(Item::class);
        $urlExternalRepository = $em->getRepository(UrlExternal::class);
        foreach($canon_list as $canon) {
            $person_id_name = $canon->getPersonIdName();
            $person = $id_person_map[$person_id_name];
            // set sibling (only relevant for bishops)
            if ($person->getItem()->getSource() == 'Bischof') {
                $itemRepository->setSibling($person);
            }

            $urlByType = $urlExternalRepository->groupByType($person_id_name);
            $person->setUrlByType($urlByType);

            $sibling = $person->getSibling();
            if ($sibling) {
                $urlByType = $urlExternalRepository->groupByType($sibling->getId());
                $sibling->setUrlByType($urlByType);
            }

            $canon->setPersonName($person);
        }

        return null;
    }


    /**
     * usually used for asynchronous JavaScript request
     *
     * $item_type_id is not used here (needed for uniform signature)
     */
    public function suggestRole($item_type_id, $name, $hint_size) {
        $repository = $this->getEntityManager()->getRepository(Role::class);
        $qb = $repository->createQueryBuilder('r')
                             ->select("DISTINCT r.name AS suggestion")
                             ->andWhere('r.name LIKE :name')
                             ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hint_size);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     *
     * $item_type is not used here (needed for uniform signature)
     */
    public function suggestDiocese($item_type_id, $name, $hint_size) {
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
     */
    public function suggestInstitution($item_type_id, $name, $hint_size) {
        $repository = $this->getEntityManager()->getRepository(Institution::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT i.name AS suggestion")
                         ->andWhere('i.name LIKE :name')
                         ->andWhere('i.itemTypeId in (:item_type_id)')
                         ->addOrderBy('i.name')
                         ->setParameter('name', '%'.$name.'%')
                         ->setParameter('item_type_id', $item_type_id);

        $qb->setMaxResults($hint_size);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }


    /**
     * autocomplete for references
     *
     * usually used for asynchronous JavaScript request
     */
    public function suggestTitleShort($item_type_id, $name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(ReferenceVolume::class);
        $qb = $repository->createQueryBuilder('v')
                         ->select("DISTINCT v.titleShort AS suggestion")
                         ->andWhere('v.titleShort LIKE :name')
                         ->andWhere('v.itemTypeId = :item_type_id')
                         ->addOrderBy('v.titleShort')
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
    public function suggestPropertyName($item_type_id, $name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT prop.name AS suggestion")
                         ->join('i.itemProperty', 'prop')
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
                         ->setParameter('item_type_id', $item_type_id)
                         ->orderBy('i.editStatus');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

}
