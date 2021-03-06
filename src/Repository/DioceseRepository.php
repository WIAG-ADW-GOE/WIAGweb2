<?php

namespace App\Repository;

use App\Entity\Diocese;
use App\Entity\ReferenceVolume;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use Doctrine\ORM\Tools\Pagination\Paginator;

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
     * return number of matching dioceses, take alternative names into account
     */
    public function countByName($name) {
        $qb = $this->createQueryBuilder('d')
                   ->select('COUNT(DISTINCT d.id) AS count')
                   ->join('d.altLabels', 'altLabels');

        if($name != "")
            $qb->andWhere('d.name LIKE :name OR altLabels.label LIKE :name')
               ->setParameter('name', '%'.$name.'%');

        $query = $qb->getQuery();
        $result = $query->getOneOrNullResult();
        return $result ? $result['count'] : 0;
    }

    public function dioceseWithBishopricSeatByName($name, $limit = null, $offset = 0) {
        $qb = $this->createQueryBuilder('d')
                   ->join('d.item', 'i')
                   ->leftJoin('d.bishopricSeat', 'bishopricSeat')
                   ->addSelect('i')
                   ->addSelect('bishopricSeat')
                   ->join('d.altLabels', 'altLabels');

        if(!is_null($name) && $name != "") {
            $qb->andWhere('d.name LIKE :name OR altLabels.label LIKE :name')
               ->setParameter('name', '%'.$name.'%');
        }

        if($limit) {
            $qb->orderBy('d.name')
               ->setFirstResult($offset)
               ->setMaxResults($limit);
        }

        $query = $qb->getQuery();

        $result = new Paginator($query, true);
        // $result = $query->getResult();

        $cDiocese = array();
        foreach ($result as $diocese) {
            $cDiocese[] = $this->addReferenceVolumes($diocese);
        }

        return $cDiocese;

    }

    public function dioceseWithBishopricSeatById($id) {
        $qb = $this->createQueryBuilder('d')
                   ->join('d.item', 'i')
                   ->leftJoin('d.bishopricSeat', 'bishopricSeat')
                   ->addSelect('i')
                   ->addSelect('bishopricSeat')
                   ->join('d.altLabels', 'altLabels')
                   ->andWhere('d.id = :id')
                   ->setParameter('id', $id);

        $query = $qb->getQuery();

        $diocese = $query->getOneOrNullResult();
        // $result = $query->getResult();

        if ($diocese) {
            $this->addReferenceVolumes($diocese);
        }

        return $diocese;

    }


    public function addReferenceVolumes($diocese) {
        $em = $this->getEntityManager();
        # add reference volumes (combined key)
        $repository = $em->getRepository(ReferenceVolume::class);
        foreach ($diocese->getItem()->getReference() as $reference) {
            $itemTypeId = $reference->getItemTypeId();
            $referenceId = $reference->getReferenceId();
            $referenceVolume = $repository->findByCombinedKey($itemTypeId, $referenceId);
            $reference->setReferenceVolume($referenceVolume);
        }
        return $diocese;
    }


    /**
     * AJAX
     */
    public function suggestName($name, $hintSize) {
        $qb = $this->createQueryBuilder('d')
                   ->select("DISTINCT d.name AS suggestion")
                   ->andWhere('d.name like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();
        // dd($suggestions);

        return $suggestions;
    }




}
