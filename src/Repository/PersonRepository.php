<?php

namespace App\Repository;

use App\Entity\Item;
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
                   ->select('p, i, bp, role, role_type, institution, urlext')
                   ->join('p.item', 'i') # avoid query in twig ...
                   ->leftjoin('i.itemProperty', 'ip')
                   ->leftjoin('i.urlExternal', 'urlext')
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

}
