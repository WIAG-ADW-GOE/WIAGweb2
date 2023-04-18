<?php

namespace App\Repository;

use App\Entity\ReferenceVolume;
use App\Service\UtilService;
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


    public function findByCombinedKey($itemTypeId, $referenceId) {
        $qb = $this->createQueryBuilder('r')
                   ->andWhere('r.itemTypeId = :itemTypeId')
                   ->andWhere('r.referenceId = :referenceId')
                   ->setParameter('itemTypeId', $itemTypeId)
                   ->setParameter('referenceId', $referenceId);
        $query = $qb->getQuery();

        return $query->getOneOrNullResult();
    }

    /**
     * set reference volume for references in $person_list
     */
    public function setReferenceVolume($item_list) {
        // an entry in item_reference belongs to one item at most
        $item_ref_list_meta = array();
        foreach ($item_list as $item_loop) {
            $item_ref_list_meta[] = $item_loop->getReference()->toArray();
        }
        $item_ref_list = array_merge(...$item_ref_list_meta);

        $item_type_list = array();
        foreach ($item_ref_list as $ref) {
            $item_type_list[] = $ref->getItemTypeId();
        }
        $item_type_list = array_unique($item_type_list);

        if (count($item_type_list) == 0) {
            return null;
        }

        // get all volumes for relevant item_types
        $qb = $this->createQueryBuilder('r')
                   ->select('r')
                   ->andWhere('r.itemTypeId in (:item_type_list)')
                   ->setParameter('item_type_list', $item_type_list);

        $query = $qb->getQuery();
        $result = $query->getResult();

        // match volumes by item_type_id and ref_id
        // - the result list is not large so the filter is no performance problem
        foreach ($item_ref_list as $ref) {
            $item_type_id = $ref->getItemTypeId();
            $ref_id = $ref->getReferenceId();
            $vol = array_filter($result, function($el) use ($item_type_id, $ref_id) {
                return ($el->getReferenceId() == $ref_id) && ($el->getItemTypeId() == $item_type_id);
            });
            $vol_obj = !is_null($vol) ? array_values($vol)[0] : null;
            $ref->setReferenceVolume($vol_obj);
        }

        return null;

    }

    public function findByTitleShortAndType($title, $item_type_id) {
        $qb = $this->createQueryBuilder('v')
                   ->select('v')
                   ->andWhere('v.titleShort LIKE :title')
                   ->andWhere('v.itemTypeId = :item_type_id')
                   ->setParameter('title', '%'.$title.'%')
                   ->setParameter('item_type_id', $item_type_id);
        $query = $qb->getQuery();
        return $query->getResult();
    }

    public function findList($id_list) {
        $qb = $this->createQueryBuilder('v')
                   ->select('v')
                   ->andWhere('v.id in (:id_list)')
                   ->addOrderBy('v.displayOrder', 'ASC')
                   ->setParameter('id_list', $id_list);
        $query = $qb->getQuery();
        $reference_list = $query->getResult();

        $reference_list = UtilService::reorder($reference_list, $id_list, "id");

        return $reference_list;

    }

    public function findByModel($model) {
        $qb = $this->createQueryBuilder('v')
                   ->select('v');

        if ($model['itemType'] != '') {
            $item_type_id = explode(', ', $model['itemType']);
            $qb->join('\App\Entity\ItemReference', 'ir', 'WITH', 'ir.itemTypeId = v.itemTypeId AND ir.referenceId = v.referenceId')
               ->join('\App\Entity\Item', 'i', 'WITH', 'i.id = ir.itemId')
               ->andWhere('i.itemTypeId in (:item_type_id)')
               ->setParameter('item_type_id', $item_type_id);
        }

        if ($model['searchText'] != '') {
            $qb->andWhere('v.titleShort LIKE :q_search '.
                          'OR v.authorEditor LIKE :q_search '.
                          'OR v.fullCitation LIKE :q_search '.
                          'OR v.gsCitation LIKE :q_search '.
                          'OR v.note LIKE :q_search')
               ->setParameter('q_search', '%'.trim($model['searchText'].'%'));
        }

        $query = $qb->getQuery();
        return $query->getResult();
    }


}
