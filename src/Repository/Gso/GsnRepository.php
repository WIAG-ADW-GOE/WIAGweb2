<?php

namespace App\Repository\Gso;

use App\Entity\Gso\Gsn;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Gsn>
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
//     * @return Gsn[] Returns an array of Gsn objects
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
}
