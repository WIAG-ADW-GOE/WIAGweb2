<?php

namespace App\Repository;

use App\Entity\Item;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\Role;
use App\Entity\Diocese;
use App\Entity\Institution;
use App\Entity\ReferenceVolume;
use App\Entity\InstitutionPlace;
use App\Entity\Authority;
use App\Entity\PlaceIdExternal;
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
     * see PriestUtController
     */
    public function findWithOffice($id) {
        $qb = $this->createQueryBuilder('p')
                   ->addSelect('bp')
                   ->join('p.item', 'i')
                   ->leftjoin('i.itemProperty', 'ip')
                   ->leftjoin('p.birthplace', 'bp')
                   ->andWhere('p.id = :id')
                   ->setParameter('id', $id);

        // sorting of birthplaces see annotation

        $query = $qb->getQuery();
        $person = $query->getOneOrNullResult();

        $personRoleRepository = $this->getEntityManager()
                                     ->getRepository(PersonRole::class);

        $person->setRole($personRoleRepository->findRoleWithPlace($id));

        return $person;
    }


    /**
     *
     */
    public function findList($id_list) {
        $qb = $this->createQueryBuilder('p')
                   ->select('p, i, bp, role, role_type, institution')
                   ->join('p.item', 'i') # avoid query in twig ...
                   ->leftjoin('i.itemProperty', 'ip')
                   ->leftjoin('p.birthplace', 'bp')
                   ->leftjoin('p.role', 'role')
                   ->leftjoin('role.role', 'role_type')
                   ->leftjoin('role.institution', 'institution')
                   ->andWhere('p.id in (:id_list)')
                   ->addOrderBy('role.dateSortKey')
                   ->addOrderBy('role.id')
                   ->setParameter('id_list', $id_list);

        // sorting of birthplaces see annotation

        $query = $qb->getQuery();
        $person_list = $query->getResult();

        // restore order as in $id_list
        $person_list = $this->utilService->reorder($person_list, $id_list, "id");

        $em = $this->getEntityManager();
        $itemRepository = $em->getRepository(Item::class);

        // there is not much potential for optimization
        foreach($person_list as $person) {
            $itemRepository->setSibling($person);
        }


        $role_list = $this->getRoleList($person_list);
        $em->getRepository(PersonRole::class)->setPlaceNameInRole($role_list);

         // set reference volumes
        $em->getRepository(ReferenceVolume::class)->setReferenceVolume($person_list);

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
     *
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

        foreach($canon_list as $canon) {
            $person = $id_person_map[$canon->getPersonIdName()];
            $canon->setPersonName($person);
        }

        return null;
    }

    /**
     * 2022-07-21 obsolete?
     */
    // public function addReferenceVolumes($person) {
    //     $em = $this->getEntityManager();
    //     # add reference volumes (combined key)
    //     $repository = $em->getRepository(ReferenceVolume::class);
    //     foreach ($person->getItem()->getReference() as $reference) {
    //         $itemTypeId = $reference->getItemTypeId();
    //         $referenceId = $reference->getReferenceId();
    //         $referenceVolume = $repository->findByCombinedKey($itemTypeId, $referenceId);
    //         $reference->setReferenceVolume($referenceVolume);
    //     }
    //     return $person;
    // }

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
    public function suggestInstitution($item_type_id, $name, $hint_size) {
        $repository = $this->getEntityManager()->getRepository(Institution::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT i.name AS suggestion")
                         ->andWhere('i.name LIKE :name')
                         ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hint_size);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }


    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestTitleShort($item_type_id, $name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(ReferenceVolume::class);
        $qb = $repository->createQueryBuilder('v')
                         ->select("DISTINCT v.titleShort AS suggestion")
                         ->andWhere('v.titleShort LIKE :name')
                         ->andWhere('v.itemTypeId = :item_type_id')
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
