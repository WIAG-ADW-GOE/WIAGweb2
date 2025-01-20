<?php

namespace App\Repository;

use App\Entity\ItemReference;
use App\Entity\ReferenceVolume;
use App\Service\UtilService;
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
     * @return reference for items in $id_list as array
     */
    public function findArray($item_id_list) {
        $qb = $this->createQueryBuilder('ref')
                   ->join('App\Entity\ReferenceVolume', 'vol', 'WITH', 'ref.referenceId = vol.referenceId')
                   ->select('ref, vol')
                   ->andWhere('ref.itemId in (:item_id_list)')
                   ->setParameter('item_id_list', $item_id_list);

        $query = $qb->getQuery();
        return $query->getArrayResult();
    }


    /**
     *
     */
    public function findVolumeByItemIdList($item_id_list) {
        $qb = $this->createQueryBuilder('ref')
                   ->join('App\Entity\ReferenceVolume', 'vol', 'WITH', 'ref.referenceId = vol.referenceId')
                   ->select('vol')
                   ->andWhere('ref.itemId in (:item_id_list)')
                   ->setParameter('item_id_list', $item_id_list);

        $query = $qb->getQuery();
        return $query->getResult();
    }


    public function referenceCount($reference_id) {
        $qb = $this->createQueryBuilder('ir')
                   ->select('COUNT(DISTINCT(ir.id)) as count')
                   ->andWhere('ir.referenceId = :reference_id')
                   ->setParameter('reference_id', $reference_id);
        $query = $qb->getQuery();

        return $query->getSingleResult()['count'];
    }

    /**
     * set reference in $item_list
     */
    public function setReference($item_list) {

        $id_list = UtilService::collectionColumn($item_list, "id");

        $qb = $this->createQueryBuilder('ir')
                   ->andWhere('ir.itemId in (:list)')
                   ->setParameter(':list', $id_list)
                   ->select('ir');

        $query = $qb->getQuery();
        $result = $query->getResult();

        // lookup table for $item_list
        $item_dict = array_combine($id_list, $item_list);

        foreach ($result as $ref) {
            $item_id = $ref->getItemId();
            $item_dict[$item_id]->addReference($ref);
        }

    }

}
