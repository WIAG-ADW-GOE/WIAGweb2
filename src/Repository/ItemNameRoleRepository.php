<?php

namespace App\Repository;

use App\Entity\ItemNameRole;
use App\Entity\Item;
use App\Entity\ItemCorpus;
use App\Entity\ItemReference;
use App\Entity\ReferenceVolume;
use App\Entity\Person;
use App\Entity\Authority;
use App\Entity\Institution;
use App\Entity\UrlExternal;
use App\Service\UtilService;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemNameRole>
 *
 * @method ItemNameRole|null find($id, $lockMode = null, $lockVersion = null)
 * @method ItemNameRole|null findOneBy(array $criteria, array $orderBy = null)
 * @method ItemNameRole[]    findAll()
 * @method ItemNameRole[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemNameRoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemNameRole::class);
    }

    public function add(ItemNameRole $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ItemNameRole $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * Returns ItemNameRole[] Returns an array of ItemNameRole objects
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

//    public function findOneBySomeField($value): ?ItemNameRole
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }


    /**
     */
    public function findList($item_id_name_list) {
        $qb = $this->createQueryBuilder('inr')
                   ->select('inr')
                   ->andWhere('inr.itemIdName in (:item_id_name_list)')
                   ->setParameter('item_id_name_list', $item_id_name_list);

        $query = $qb->getQuery();
        return $query->getArrayResult();
    }

    /**
     * updateByIdList($id_list)
     *
     * update entries related to $id_list
     */
    public function updateByIdList($id_list) {
        $entityManager = $this->getEntityManager();
        $itemCorpusRepository = $entityManager->getRepository(ItemCorpus::class);
        $personRepository = $entityManager->getRepository(Person::class);
        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class);

        // delete all entries related to persons in $id_list
        $qb = $this->createQueryBuilder('inr')
                   ->andWhere ('inr.itemIdRole in (:id_list)')
                   ->setParameter('id_list', $id_list);
        $inr_list = $qb->getQuery()->getResult();
        $n_del = count($inr_list);
        foreach ($inr_list as $inr_del) {
            $entityManager->remove($inr_del);
        }
        $entityManager->flush();

        // set/restore entries based on online status, corpus and references to the Digitales Personenregister
        $n = 0;
        $person_list = $personRepository->findPureList($id_list);

        $id_primary_list = array();
        $id_secondary_list = array();
        $id_gs_list = array();
        foreach ($person_list as $p_loop) {
            $item = $p_loop->getItem();
            $corpus_id_list = $item->getCorpusIdList();
            $is_online = $item->getIsOnline();
            // primary entry
            if ($is_online == 1 and !(in_array('dreg-can', $corpus_id_list) or in_array('dreg', $corpus_id_list))) {
                $id = $p_loop->getId();
                $inr = new ItemNameRole($id, $id);
                $inr->setItem($item);
                $inr->setPersonRolePerson($p_loop);
                $entityManager->persist($inr);
                $id_primary_list[] = $id;
                $n += 1;
                // secondary entry
                $gsn = $item->getGsn();
                $online_flag = true;
                $item_id_dreg_list = $urlExternalRepository->findItemId($gsn, Authority::ID['GSN'], $online_flag);
                foreach ($item_id_dreg_list as $item_id) {
                    $iic_pairs = $itemCorpusRepository->findPairs($item_id, ['dreg', 'dreg-can']);
                    $is_dreg = (!is_null($iic_pairs) and (count($iic_pairs) > 0));
                    if (($item_id != $id) and $is_dreg) {
                        $inr = new ItemNameRole($id, $item_id);
                        $inr->setItem($item);
                        $inr->setPersonRolePerson($personRepository->find($item_id));
                        $entityManager->persist($inr);
                        $n += 1;
                        $id_secondary_list[] = $item_id;
                    }
                }
            }
        }

        // independent GS entries, dreg-can only
        foreach ($person_list as $p_loop) {
            $id = $p_loop->getId();
            $item = $p_loop->getItem();
            $corpus_id_list = $item->getCorpusIdList();
            if ($item->getIsOnline() == 1
                and !in_array($id, $id_secondary_list) // this prevents dublicate entries
                and (in_array('dreg-can', $corpus_id_list))
            ) {
                $id_dreg = $p_loop->getId();
                $inr = new ItemNameRole($id_dreg, $id_dreg);
                $inr->setItem($item);
                $inr->setPersonRolePerson($p_loop);
                $entityManager->persist($inr);

                $n += 1;

            }

        }

        $entityManager->flush();

        return $n;
    }

    /**
     * one page HTML list
     */
    public function referenceListByCorpus($id_list, $corpus_id) {
        $qb = $this->createQueryBuilder('inr')
                   ->select('ref_vol')
                   ->join('\App\Entity\ItemReference', 'ir', 'WITH', 'ir.itemId = inr.itemIdRole')
                   ->join('\App\Entity\ReferenceVolume', 'ref_vol', 'WITH', 'ref_vol.referenceId = ir.referenceId')
                   ->join('\App\Entity\ItemCorpus', 'ic', 'WITH', "ic.itemId = inr.itemIdRole and ic.corpusId = :corpus_id")
                   ->andWhere('inr.itemIdName in (:id_list)')
                   ->setParameter('id_list', $id_list)
                   ->setParameter('corpus_id', $corpus_id);

        $query = $qb->getQuery();
        return $query->getResult();

    }

    /**
     * Returns role data for persons in $id_list
     *
     * collect roles for a person via ItemNameRole; consider only one source
     */
    public function findRoleArray($person_id_list) {

        $qb = $this->createQueryBuilder('inr')
                   ->select('pr, r, rg, institution, diocese, dioc_item, dioc_item_corpus')
                   ->leftJoin('\App\Entity\PersonRole', 'pr',
                              'WITH', 'pr.personId = inr.itemIdName')
                   ->leftJoin('pr.role', 'r')
                   ->leftJoin('pr.institution', 'institution')
                   ->leftJoin('pr.diocese', 'diocese')
                   ->leftJoin('diocese.item', 'dioc_item')
                   ->leftJoin('dioc_item.itemCorpus', 'dioc_item_corpus')
                   ->leftJoin('r.roleGroup', 'rg')
                   ->andWhere('inr.itemIdName in (:id_list)')
                   ->setParameter('id_list', $person_id_list);

        $query = $qb->getQuery();
        // be economical/careful with memory
        return $query->getArrayResult();

    }

    /**
     * Returns persons in $id_list with role data
     *
     */
    public function findPersonRoleArray($person_id_list) {

        $qb = $this->createQueryBuilder('inr')
                   ->select('p, i, ref, pr, r, institution, diocese, dioc_item, dioc_item_corpus')
                   ->leftJoin('\App\Entity\Person', 'p',
                              'WITH', 'p.id = inr.itemIdRole')
                   ->innerJoin('p.item', 'i')
                   ->leftJoin('i.reference', 'ref')
                   ->leftJoin('p.role', 'pr')
                   ->leftJoin('pr.role', 'r')
                   ->leftJoin('pr.institution', 'institution')
                   ->leftJoin('pr.diocese', 'diocese')
                   ->leftJoin('diocese.item', 'dioc_item')
                   ->leftJoin('dioc_item.itemCorpus', 'dioc_item_corpus')
                   ->andWhere('inr.itemIdName in (:id_list)')
                   ->setParameter('id_list', $person_id_list);

        $query = $qb->getQuery();
        // be economical/careful with memory
        return $query->getArrayResult();

    }


    /**
     * Returns reference data for persons in $id_list
     *
     * collect all references for a person via ItemNameRole
     */
    public function findSimpleReferenceList($id_list) {
        // map person_id to reference volumes
        // find item_reference list
        $qb = $this->createQueryBuilder('inr')
                   ->select('ir')
                   ->join('\App\Entity\ItemReference', 'ir',
                          'WITH', 'ir.itemId = inr.itemIdRole')
                   ->andWhere('inr.itemIdName in (:id_list)')
                   ->setParameter('id_list', $id_list);

        $query = $qb->getQuery();
        $ir_list = $query->getArrayResult();

        $ref_id_list = array_unique(array_column($ir_list, 'referenceId'));

        $ref_vol_list = $this->getEntityManager()
                             ->getRepository(ReferenceVolume::class)
                             ->findArray($ref_id_list);

        foreach ($ir_list as $key => $ir) {
            $reference_id = $ir['referenceId'];
            $volume = array_filter($ref_vol_list, function($r) use ($reference_id) {
                return $r['referenceId'] == $reference_id;
            });
            if (count($volume) > 0) {
                $ir_list[$key]['volume'] = array_values($volume)[0];
            } else {
                $ir_list[$key]['volume'] = [
                    'referenceId' => $reference_id,
                    'fullCitation' => "Band nicht gefunden",
                    'yearPublication' => null,
                ];
            }
        }

        // be economical/careful with memory
        unset($ref_vol_list);
        return $ir_list;

    }


}
