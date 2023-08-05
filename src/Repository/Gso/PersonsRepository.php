<?php

namespace App\Repository\Gso;

use App\Entity\Gso\Persons;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends EntityRepository<Persons>
 *
 * @method Persons|null find($id, $lockMode = null, $lockVersion = null)
 * @method Persons|null findOneBy(array $criteria, array $orderBy = null)
 * @method Persons[]    findAll()
 * @method Persons[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonsRepository extends EntityRepository
{

    public function add(Persons $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Persons $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return Persons[] Returns an array of Persons objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Persons
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }


    /**
     * @return ID, and modification date for persons with an office in $domstift_gsn_list
     */
    public function findCanonIDs($domstift_gsn_list) {
        $qb = $this->createQueryBuilder('p')
                   ->select('p.id as person_id')
                   ->join('p.role', 'o')
                   ->join('p.item', 'i')
                   ->join('i.gsn', 'gsn') // exclude the rare case where a GSN is missing
                   ->andWhere('o.klosterid in (:mon_list)')
                   ->andWhere('o.deleted = 0')
                   ->andWhere('i.deleted = 0')
                   ->andWhere("i.status = 'online'")
                   ->andWhere("gsn.deleted = 0")
                   ->addGroupBy('i.id')
                   ->setParameter('mon_list', $domstift_gsn_list);

        $query = $qb->getQuery();
        $result = $query->getResult();

        return $result;

    }

    /**
     *
     */
    public function findList($id_list, $with_deleted = false) {
        $qb = $this->createQueryBuilder('p')
                   ->select('p, i, role, ref, vol')
                   ->join('p.item', 'i') # avoid query in twig ...
                   ->leftjoin('i.reference', 'ref')
                   ->leftjoin('ref.referenceVolume', 'vol')
                   ->leftjoin('p.role', 'role')
                   ->andWhere('p.id in (:id_list)')
                   ->addOrderBy('p.familienname')
                   ->addOrderBy('p.vorname')
                   ->setParameter('id_list', $id_list);

        if (!$with_deleted) {
            $qb->andWhere('i.deleted = 0');
            $qb->andWhere('role.deleted = 0');
        }

        $query = $qb->getQuery();
        return $query->getResult();
    }

}
