<?php

namespace App\Repository;

use App\Entity\Institution;
use App\Entity\ItemProperty;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Institution|null find($id, $lockMode = null, $lockVersion = null)
 * @method Institution|null findOneBy(array $criteria, array $orderBy = null)
 * @method Institution[]    findAll()
 * @method Institution[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InstitutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Institution::class);
    }

    // /**
    //  * @return Institution[] Returns an array of Institution objects
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
    public function findOneBySomeField($value): ?Institution
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function findIfHasItemProperty(int $item_prop_id) {

        $qb = $this->createQueryBuilder('inst')
                   ->join('inst.item', 'i')
                   ->join('i.itemProperty', 'iprop')
                   ->andWhere('i.isOnline = 1')
                   ->andWhere('iprop.propertyTypeId = :item_prop_id')
                   ->setparameter('item_prop_id', $item_prop_id);

        $query = $qb->getQuery();
        return $query->getResult();

    }

}
