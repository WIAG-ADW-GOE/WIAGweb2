<?php

namespace App\Repository;

use App\Entity\ItemReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ItemReference|null find($id, $lockMode = null, $lockVersion = null)
 * @method ItemReference|null findOneBy(array $criteria, array $orderBy = null)
 * @method ItemReference[]    findAll()
 * @method ItemReference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemReference::class);
    }

    // /**
    //  * @return ItemReference[] Returns an array of ItemReference objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ItemReference
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function referenceCount($reference_id) {
        $qb = $this->createQueryBuilder('ir')
                   ->select('COUNT(DISTINCT(ir.id)) as count')
                   ->join('ir.item', 'item')
                   ->andWhere('ir.referenceId = :reference_id')
                   ->andWhere('item.isOnline = 1')
                   ->setParameter('reference_id', $reference_id);
        $query = $qb->getQuery();

        return $query->getSingleResult()['count'];
    }


}
