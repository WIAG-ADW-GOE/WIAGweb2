<?php

namespace App\Repository;

use App\Entity\ReferenceVolume;
use App\Service\UtilService;
use Doctrine\Common\Collections\ArrayCollection;
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
    //  * Returns ReferenceVolume[] Returns an array of ReferenceVolume objects
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
     * set reference volume for references in $item_list
     */
    public function setReferenceVolume($item_list) {
        // an entry in item_reference belongs to one item at most
        $item_ref_list_meta = array();
        foreach ($item_list as $item_loop) {
            $item_ref_list_meta[] = $item_loop->getReference()->toArray();
        }
        $item_ref_list = array_merge(...$item_ref_list_meta);

        $ref_id_list_all = UtilService::collectionColumn($item_ref_list, "referenceId");
        $ref_id_list = array_unique($ref_id_list_all);

        $qb = $this->createQueryBuilder('r')
                   ->andWhere('r.referenceId in (:ril)')
                   ->setParameter(':ril', $ref_id_list)
                   ->select('r');

        $query = $qb->getQuery();
        $result = $query->getResult();

        // assign volumes
        $vol_id_list = UtilService::collectionColumn($result, "referenceId");
        $vol_dict = array_combine($vol_id_list, $result);

        foreach ($item_ref_list as $ir) {
             $ir->setReferenceVolume($vol_dict[$ir->getReferenceId()]);
        }

        // sort references
        foreach ($item_list as $item_loop) {
            $ref_list = $item_loop->getReference()->toArray();
            usort($ref_list, function($a, $b) {
                // new criterion 2023-06-23
                $gsc_a = $a->getReferenceVolume()->getDisplayOrder();
                $gsc_b = $b->getReferenceVolume()->getDisplayOrder();
                $cmp = $gsc_a == $gsc_b ? 0 : ($gsc_a < $gsc_b ? -1 : 1);
                if ($cmp == 0) {
                    $gsc_a = $a->getReferenceVolume()->getGsCitation();
                    $gsc_b = $b->getReferenceVolume()->getGsCitation();
                    $cmp = $gsc_a == $gsc_b ? 0 : ($gsc_a < $gsc_b ? -1 : 1);
                }
                return $cmp;
            });
            $item_loop->setReference(new ArrayCollection($ref_list));
        }

        return null;

    }

    /**
     * Returns volumes as array by list of reference_id
     */
    public function findArray($ref_id_list = null) {
        $qb = $this->createQueryBuilder('r')
                   ->select('r');

        if (!is_null($ref_id_list)) {
            $qb->andWhere ('r.referenceId in (:ril)')
               ->setParameter('ril', $ref_id_list);
        }

        $query = $qb->getQuery();
        return $query->getArrayResult();
    }


    /**
     * find by $id_list, keep order of $id_list
     */
    public function findList($id_list) {
        $qb = $this->createQueryBuilder('v')
                   ->select('v')
                   ->andWhere('v.id in (:id_list)')
                   ->setParameter('id_list', $id_list);
        $query = $qb->getQuery();
        $reference_list = $query->getResult();

        $reference_list = UtilService::reorder($reference_list, $id_list, "id");

        return $reference_list;
    }

    /**
     * find references by $model holding criteria for elements in reference_volume
     */
    public function findByModel($model) {
        $qb = $this->createQueryBuilder('v')
                   ->select('v');

        if ($model['corpus'] != '') {
            $cid_list = explode(', ', $model['corpus']);
            $qb->join('\App\Entity\ItemReference', 'ir', 'WITH', 'ir.referenceId = v.referenceId')
               ->join('\App\Entity\ItemCorpus', 'ic', 'WITH', 'ic.itemId = ir.itemId')
               ->andWhere('ic.corpusId in (:corpus_id)')
               ->setParameter('corpus_id', $cid_list);
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

    public function nextId() {
            $qb = $this->createQueryBuilder('v')
                       ->select('max(v.referenceId) AS nextId');
            $query = $qb->getQuery();
            $list = $query->getOneOrNullResult();

            $nextId = null;
            if (!is_null($list)) {
                return($list['nextId'] + 1);
            }
    }

    public function findByCorpusId($corpus_id) {
        $qb = $this->createQueryBuilder('v')
                   ->select('v, idsExternal')
                   ->innerJoin('App\Entity\ItemReference', 'ir', 'WITH', 'ir.referenceId = v.referenceId')
                   ->innerJoin('App\Entity\ItemCorpus', 'ic', 'WITH', 'ic.itemId = ir.itemId and ic.corpusId = :corpus_id')
                   ->leftJoin('v.idsExternal', 'idsExternal')
                   ->addOrderBy('v.displayOrder')
                   ->addOrderBy('v.gsCitation')
                   ->setParameter('corpus_id', $corpus_id);

        $query = $qb->getQuery();
        return $query->getResult();
    }


    public function suggestEntry($query_param) {
        $qb = $this->createQueryBuilder('v')
                   ->select('v.gsCitation as suggestion')
                   ->andWhere('v.titleShort LIKE :q_search '.
                              'OR v.authorEditor LIKE :q_search '.
                              'OR v.fullCitation LIKE :q_search '.
                              'OR v.gsCitation LIKE :q_search '.
                              'OR v.note LIKE :q_search')
                   ->addOrderBy('v.gsCitation')
                   ->setParameter('q_search', '%'.$query_param.'%');

        $query = $qb->getQuery();
        return $query->getResult();

    }

    public function findGsVolumeNumber($book_nummer_list) {
        $qb = $this->createQueryBuilder('v')
                   ->select('v.gsVolumeNr')
                   ->andWhere('v.gsVolumeNr in (:book_nummer_list)')
                   ->setParameter('book_nummer_list', $book_nummer_list);

        $query = $qb->getQuery();
        return $query->getResult();

    }


}
