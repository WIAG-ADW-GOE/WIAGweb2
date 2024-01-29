<?php

namespace App\Repository;

use App\Entity\ItemCorpus;
use App\Service\UtilService;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemCorpus>
 *
 * @method ItemCorpus|null find($id, $lockMode = null, $lockVersion = null)
 * @method ItemCorpus|null findOneBy(array $criteria, array $orderBy = null)
 * @method ItemCorpus[]    findAll()
 * @method ItemCorpus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemCorpusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemCorpus::class);
    }

    public function add(ItemCorpus $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ItemCorpus $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return ItemCorpus[] Returns an array of ItemCorpus objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('i.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ItemCorpus
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    public function findCorpusPrio($id) {
        $qb = $this->createQueryBuilder('ic')
                   ->select('c')
                   ->join('App\Entity\Corpus', 'c', 'WITH', 'c.corpusId = ic.corpusId')
                   ->andWhere('ic.itemId = :q_id')
                   ->setParameter('q_id', $id)
                   ->orderBy('c.id');

        $query = $qb->getQuery();
        return $query->getResult()[0];
    }

    /**
     *
     */
    public function findMaxIdInCorpus($corpus_id) {
        $qb = $this->createQueryBuilder('i')
                   ->select("i.idInCorpus")
                   ->andWhere('i.corpusId = :corpus_id')
                   ->setParameter('corpus_id', $corpus_id);
        $query = $qb->getQuery();
        $result = $query->getResult();

        $max_id = 0;
        foreach ($result as $el) {
            $cand = intval($el['idInCorpus']);
            if ($cand > $max_id) {
                $max_id = $cand;
            }
        }
        return $max_id;

    }

    /**
     *
     */
    public function findMaxNumIdPublic($corpus_id) {
        $qb = $this->createQueryBuilder('i')
                   ->select("i.idPublic")
                   ->andWhere('i.corpusId = :corpus_id')
                   ->setParameter('corpus_id', $corpus_id);
        $query = $qb->getQuery();
        $result = $query->toIterable();

        $rgx = "/([[:alpha:]-]+)-([0-9]{3,})/";

        $num_id = 1;
        foreach ($result as $q_ic) {
            $match_list = array();
            preg_match($rgx, $q_ic['idPublic'], $match_list);
            if (count($match_list) > 1) {
                $cand = intval($match_list[2]);
                if ($cand > $num_id) {
                    $num_id = $cand;
                }
            }
        }
        return $num_id;
    }

    /**
     *
     */
    public function findItemIdByCorpusAndId($corpus_id, $id_in_corpus, $with_merged = false) {
        if (is_null($corpus_id)) {
            return null;
        }
        $qb = $this->createQueryBuilder('ic')
                   ->select('ic.itemId')
                   ->join('ic.item', 'i')
                   ->andWhere('ic.corpusId = :corpus_id')
                   ->andWhere('i.isDeleted = 0')
                   ->setParameter('corpus_id', $corpus_id);

        if (!$with_merged) {
            $qb->andWhere("i.mergeStatus in ('child', 'original')");
        }

        if (!is_null($id_in_corpus)) {
            $qb->andWhere('ic.idInCorpus LIKE :id')
            ->setParameter('id', $id_in_corpus);
        }

        $query = $qb->getQuery();
        return $query->getResult();
    }

    public function findPairs($item_id, $corpus_id_list) {
        $qb = $this->createQueryBuilder('ic')
                   ->select('ic.itemId, ic.corpusId')
                   ->andWhere('ic.itemId = :item_id')
                   ->andWhere('ic.corpusId in (:corpus_id_list)')
                   ->setParameter('item_id', $item_id)
                   ->setParameter('corpus_id_list', $corpus_id_list);

        $query = $qb->getQuery();
        $result = $query->getResult();
        return $result;
    }


    // 2023-09-08 obsolete
    // public function findItemIdByIdPublic($id_public) {
    //     $cand_list = $this->findByIdPublic($id_public);
    //     if (is_null($cand_list) or count($cand_list) == 0) {
    //         return null;
    //     }
    //     return $cand_list[0]->getItemId();
    // }
}
