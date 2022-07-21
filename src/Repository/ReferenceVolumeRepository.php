<?php

namespace App\Repository;

use App\Entity\ReferenceVolume;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ReferenceVolume|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReferenceVolume|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReferenceVolume[]    findAll()
 * @method ReferenceVolume[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReferenceVolumeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReferenceVolume::class);
    }

    // /**
    //  * @return ReferenceVolume[] Returns an array of ReferenceVolume objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ReferenceVolume
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    /**
     * 2022-07-21 obsolete
     */
    // public function addReferenceVolumes($item) {
    //     foreach ($item->getReference() as $reference) {
    //         $itemTypeId = $reference->getItemTypeId();
    //         $referenceId = $reference->getReferenceId();
    //         $referenceVolume = $this->findByCombinedKey($itemTypeId, $referenceId);
    //         $reference->setReferenceVolume($referenceVolume);
    //     }
    //     return $item;
    // }

    public function findByCombinedKey($itemTypeId, $referenceId) {
        $qb = $this->createQueryBuilder('r')
                   ->andWhere('r.itemTypeId = :itemTypeId')
                   ->andWhere('r.referenceId = :referenceId')
                   ->setParameter('itemTypeId', $itemTypeId)
                   ->setParameter('referenceId', $referenceId);
        $query = $qb->getQuery();

        return $query->getOneOrNullResult();
    }
}
