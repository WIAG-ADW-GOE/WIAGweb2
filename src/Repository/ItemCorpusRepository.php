<?php

namespace App\Repository;

use App\Entity\ItemCorpus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemCorpus>
 *
 * @method ItemCorpus|null find($id, $lockMode = null, $lockVersion = null)
 * @method ItemCorpus|null findOneBy(array $criteria, array $orderBy = null)
 * @method ItemCorpus[]    findAll()
 * @method ItemCorpus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemCorpusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemCorpus::class);
    }

    public function add(ItemCorpus $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ItemCorpus $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return ItemCorpus[] Returns an array of ItemCorpus objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('i.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ItemCorpus
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
