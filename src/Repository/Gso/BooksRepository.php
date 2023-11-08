<?php

namespace App\Repository\Gso;

use App\Entity\Gso\Books;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Books>
 *
 * @method Books|null find($id, $lockMode = null, $lockVersion = null)
 * @method Books|null findOneBy(array $criteria, array $orderBy = null)
 * @method Books[]    findAll()
 * @method Books[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BooksRepository extends EntityRepository
{

    public function add(Books $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Books $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return Books[] Returns an array of Books objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('b.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Books
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    public function findNummerByGsn($gsn_list) {
        $qb = $this->createQueryBuilder('b')
                   ->select('DISTINCT b.nummer AS nummer')
                   ->join('\App\Entity\Gso\Locations', 'l', 'WITH', 'l.bookId = b.id')
                   ->join('\App\Entity\Gso\Gsn', 'gsn',
                          'WITH', "gsn.itemId = l.itemId AND gsn.itemStatus = 'online'")
                   ->andWhere('gsn.nummer in (:gsn_list)')
                   ->setParameter('gsn_list', $gsn_list);

        $query = $qb->getQuery();
        return $query->getResult();
    }

    public function findByNummer($nummer_list, $deleted_flag) {
        $qb = $this->createQueryBuilder('b')
                   ->andWhere('b.nummer in (:nummer_list)')
                   ->andWhere('b.deleted = :deleted_flag')
                   ->setParameter('deleted_flag', $deleted_flag)
                   ->setParameter('nummer_list', $nummer_list);

        $query = $qb->getQuery();
        return $query->getResult();
    }

}
