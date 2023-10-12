<?php

namespace App\Repository;

use App\Entity\Diocese;
use App\Entity\ReferenceVolume;
use App\Entity\Authority;

use App\Service\UtilService;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Common\Collections\Collection;

/**
 * @method Diocese|null find($id, $lockMode = null, $lockVersion = null)
 * @method Diocese|null findOneBy(array $criteria, array $orderBy = null)
 * @method Diocese[]    findAll()
 * @method Diocese[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DioceseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Diocese::class);
    }

    // /**
    //  * @return Diocese[] Returns an array of Diocese objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Diocese
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    /**
     * countByName($name)
     *
     * return number of matching dioceses (altes Reich), take alternative names into account
     */
    public function countByName($name) {
        $qb = $this->createQueryBuilder('d')
                   ->select('COUNT(DISTINCT d.id) AS count')
                   ->leftJoin('d.altLabels', 'altLabels')
                   ->andWhere('d.isAltesReich = 1');

        if($name != "")
            $qb->andWhere('d.name LIKE :name OR altLabels.label LIKE :name')
               ->setParameter('name', '%'.$name.'%');

        $query = $qb->getQuery();
        $result = $query->getOneOrNullResult();
        return $result ? $result['count'] : 0;
    }

    public function dioceseWithBishopricSeat($name_or_id, $limit = null, $offset = 0) {
        $qb = $this->createQueryBuilder('d')
                   ->addSelect('i')
                   ->addSelect('bishopricSeat')
                   ->join('d.item', 'i')
                   ->leftJoin('d.bishopricSeat', 'bishopricSeat')
                   ->leftJoin('d.altLabels', 'altLabels')
                   ->andWhere('d.isAltesReich = 1');

        if(!is_null($name_or_id) && $name_or_id != "") {
            if (is_numeric($name_or_id)) {
                $qb->andWhere('i.id = :id')
                   ->setParameter('id', $name_or_id);
            } else {
                $qb->andWhere('d.name LIKE :name OR altLabels.label LIKE :name')
                   ->setParameter('name', '%'.$name_or_id.'%');
            }
        }

        if($limit) {
            $qb->orderBy('d.name')
               ->setFirstResult($offset)
               ->setMaxResults($limit);
        }

        $query = $qb->getQuery();

        $result = new Paginator($query, true);
        // $result = $query->getResult();

        $item_list = array();
        $diocese_list = array();
        foreach ($result as $diocese) {
            $item_list[] = $diocese->getItem();
            $diocese_list[] = $diocese;
        }

        $entityManager = $this->getEntityManager();
        $entityManager->getRepository(ReferenceVolume::class)
                      ->setReferenceVolume($item_list);
        $entityManager->getRepository(Authority::class)
                      ->setAuthority($item_list);

        return $diocese_list;

    }


    /**
     * 2022-10-07 obsolete see dioceseWithBishopricSeat
     */
    // public function dioceseWithBishopricSeatById_hide($id) {
    //     $qb = $this->createQueryBuilder('d')
    //                ->join('d.item', 'i')
    //                ->leftJoin('d.bishopricSeat', 'bishopricSeat')
    //                ->addSelect('i')
    //                ->addSelect('bishopricSeat')
    //                ->join('d.altLabels', 'altLabels')
    //                ->andWhere('d.id = :id')
    //                ->setParameter('id', $id);

    //     $query = $qb->getQuery();

    //     $diocese = $query->getOneOrNullResult();
    //     // $result = $query->getResult();

    //     if ($diocese) {
    //         $this->addReferenceVolumes($diocese);
    //     }

    //     return $diocese;

    // }

    /**
     * 2022-10-07 obsolete see dioceseWithBishopricSeat
     */
    // public function addReferenceVolumes($diocese) {
    //     $em = $this->getEntityManager();
    //     # add reference volumes (combined key)
    //     $repository = $em->getRepository(ReferenceVolume::class);
    //     foreach ($diocese->getItem()->getReference() as $reference) {
    //         $itemTypeId = $reference->getItemTypeId();
    //         $referenceId = $reference->getReferenceId();
    //         $referenceVolume = $repository->findByCombinedKey($itemTypeId, $referenceId);
    //         $reference->setReferenceVolume($referenceVolume);
    //     }
    //     return $diocese;
    // }


    /**
     * AJAX
     */
    public function suggestName($name, $hintSize, $altes_reich_flag) {
        $qb = $this->createQueryBuilder('d')
                   ->select("DISTINCT d.name AS suggestion")
                   ->andWhere('d.name like :name')
                   ->setParameter(':name', '%'.$name.'%');

        if (!is_null($altes_reich_flag)) {
            $qb->andWhere('d.isAltesReich = 1');
        }

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();
        // dd($suggestions);

        return $suggestions;
    }

    /**
     * autocomplete function for references
     *
     * usually used for asynchronous JavaScript request
     */
    public function suggestTitleShort($name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(ReferenceVolume::class);
        $qb = $repository->createQueryBuilder('v')
                         ->select("DISTINCT v.titleShort AS suggestion")
                         ->andWhere('v.titleShort LIKE :name')
                         ->addOrderBy('v.titleShort')
                         ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    public function findByModel($model) {
        $referenceRepository = $this->getEntityManager()->getRepository(ReferenceVolume::class);

        $qb = $this->createQueryBuilder('d')
                   ->select('d', 'i', 'ref')
                   ->join('d.item', 'i')
                   ->leftjoin('i.urlExternal', 'ext')
                   ->leftjoin('i.reference', 'ref')
                   ->addOrderBy('d.name');

        if ($model['name'] != '') {
            $qb->andWhere('d.name like :name')
               ->setParameter('name', '%'.$model['name'].'%');
        }

        $query = $qb->getQuery();
        $list = $query->getResult();

        $item_list = array();
        foreach ($list as $dioc) {
            $item_list[] = $dioc->getItem();
        }
        // set reference volumes
        $referenceRepository->setReferenceVolume($item_list);

        return $list;
    }

    /**
     * find dioceses by ID
     */
    public function findList($id_list) {
        $referenceRepository = $this->getEntityManager()->getRepository(ReferenceVolume::class);

        $qb = $this->createQueryBuilder('d')
                   ->select('d, i, ic, ref')
                   ->join('d.item', 'i')
                   ->join('i.itemCorpus', 'ic')
                   ->leftJoin('i.reference', 'ref')
                   ->andWhere('d.id in (:id_list)')
                   ->setParameter('id_list', $id_list);

        $query = $qb->getQuery();
        $list = $query->getResult();

        $item_list = array();
        foreach ($list as $dioc) {
            $item_list[] = $dioc->getItem();
        }
        // set reference volumes
        $referenceRepository->setReferenceVolume($item_list);

        $list = UtilService::reorder($list, $id_list, "id");
        return $list;
    }


}
