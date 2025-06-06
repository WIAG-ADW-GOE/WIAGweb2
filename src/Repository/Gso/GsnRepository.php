<?php

namespace App\Repository\Gso;

use App\Entity\Gso\Gsn;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends EntityRepository<Gsn>
 *
 * @method Gsn|null find($id, $lockMode = null, $lockVersion = null)
 * @method Gsn|null findOneBy(array $criteria, array $orderBy = null)
 * @method Gsn[]    findAll()
 * @method Gsn[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GsnRepository extends EntityRepository
{

    public function add(Gsn $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Gsn $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * Returns Gsn[] Returns an array of Gsn objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('g.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Gsn
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }


    /**
     * GSNs may change in Digitales Personenregister
     */
    public function findCurrentGsn($gsn) {
        $qb = $this->createQueryBuilder('g')
                   ->select('g_min.nummer as gsn, g_min.id')
                   ->join('\App\Entity\Gso\Gsn', 'g_min', 'WITH', "g.itemId = g_min.itemId and g_min.deleted = 0 and g_min.itemStatus = 'online'")
                   ->orderBy('g_min.id', 'ASC')
                   ->andWhere('g.nummer = :gsn')
                   ->setParameter('gsn', $gsn);

        $query = $qb->getQuery();
        $result = $query->getResult();

        if (count($result) == 0) {
            return null;
        } else {
            return $result[0];
        }

    }


}
