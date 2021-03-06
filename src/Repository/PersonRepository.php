<?php

namespace App\Repository;

use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\ReferenceVolume;
use App\Entity\InstitutionPlace;
use App\Entity\Authority;
use App\Entity\PlaceIdExternal;
use App\Form\Model\BishopFormModel;

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
     * 2022-05-03: copy in ItemRepository
     */
    public function addReferenceVolumes($person) {
        $em = $this->getEntityManager();
        # add reference volumes (combined key)
        $repository = $em->getRepository(ReferenceVolume::class);
        foreach ($person->getItem()->getReference() as $reference) {
            $itemTypeId = $reference->getItemTypeId();
            $referenceId = $reference->getReferenceId();
            $referenceVolume = $repository->findByCombinedKey($itemTypeId, $referenceId);
            $reference->setReferenceVolume($referenceVolume);
        }
        return $person;
    }

}
