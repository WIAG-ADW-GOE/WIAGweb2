<?php

namespace App\Repository;

use App\Entity\IdExternal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method IdExternal|null find($id, $lockMode = null, $lockVersion = null)
 * @method IdExternal|null findOneBy(array $criteria, array $orderBy = null)
 * @method IdExternal[]    findAll()
 * @method IdExternal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IdExternalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdExternal::class);
    }

    // /**
    //  * @return IdExternal[] Returns an array of IdExternal objects
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
    public function findOneBySomeField($value): ?IdExternal
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    /*
     * BEACON export
     */
    public function findValues($authority_id) {
        $qb = $this->createQueryBuilder('id')
                   ->select('DISTINCT id.value')
                   ->join('id.item', 'item')
                   ->andWhere('id.value is not null')
                   ->andWhere('item.isOnline = 1')
                   ->andWhere('id.authorityId = :authority_id')
                   ->setParameter('authority_id', $authority_id);

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;

    }

}
