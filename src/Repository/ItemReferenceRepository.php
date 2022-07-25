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

    /**
     * set references for items in $person_list
     *
     * (usually the elements of $person_list are all related to one canon)
     */
    public function setReferenceVolume($person_list) {
        // an entry in item_reference belongs to one item at most
        $item_ref_list_meta = array();
        foreach($person_list as $p) {
            $item_ref_list_meta[] = $p->getItem()->getReference()->toArray();
        }
        $item_ref_list = array_merge(...$item_ref_list_meta);

        $id_iref_map = array();
        foreach($item_ref_list as $item_ref) {
            $id_iref_map[$item_ref->getId()] = $item_ref;
        }

        $qb = $this->createQueryBuilder('r')
                   ->select('r.id, ref_volume')
                   ->join('\App\Entity\ReferenceVolume',
                          'ref_volume',
                          'WITH',
                          'ref_volume.itemTypeId = r.itemTypeId and ref_volume.referenceId = r.referenceId')
                   ->andWhere('r.id in (:id_list)')
                   ->setParameter('id_list', array_keys($id_iref_map));

        $query = $qb->getQuery();
        $result = $query->getResult();

        foreach ($result as $r_loop) {
            $ref = $id_iref_map[$r_loop['id']];
            $ref->setReferenceVolume($r_loop[0]);
        }

        return null;

    }

}
