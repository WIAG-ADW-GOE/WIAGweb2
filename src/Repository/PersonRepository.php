<?php

namespace App\Repository;

use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\ReferenceVolume;
use App\Entity\InstitutionPlace;
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
                   ->join('p.role', 'pr')
                   ->addSelect('pr')
                   ->andWhere('p.id = :id')
                   ->setParameter('id', $id);
        $query = $qb->getQuery();
        $person = $query->getOneOrNullResult();

        $this->addInstitutionPlace($person);

        return $person;
    }

    public function addInstitutionPlace($person) {
        if ($person) {
            $em = $this->getEntityManager();
            $repository = $em->getRepository(InstitutionPlace::class);
            foreach ($person->getRole() as $role) {
                $institutionId = $role->getInstitutionId();
                if (is_null($institutionId)) {
                    continue;
                }
                $dateBegin = $role->getNumDateBegin();
                $dateEnd = $role->getNumDateEnd();
                $dateQuery = $dateBegin;
                if (!is_null($dateBegin) && !is_null($dateEnd)) {
                    $dateQuery = intdiv($dateBegin + $dateEnd, 2);
                } elseif (!is_null($dateEnd)) {
                    $dateQuery = $dateEnd;
                }

                $placeName = $repository->findByDate($institutionId, $dateQuery);
                $role->setPlaceName($placeName);
            }
        }

        return $person;
    }

    public function findWithAssociations($id) {
        $qb = $this->createQueryBuilder('p')
                   ->join('p.role', 'pr')
                   ->join('p.item', 'i')
                   ->join('i.itemReference', 'r')
                   ->addSelect('pr')
                   ->addSelect('r')
                   ->andWhere('p.id = :id')
                   ->setParameter('id', $id);

        $person = $this->findWithOffice($id);
        if ($person) {
            $this->addReferenceVolumes($person);
        }
        return $person;
    }

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
