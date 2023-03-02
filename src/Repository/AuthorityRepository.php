<?php

namespace App\Repository;

use App\Entity\Authority;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Authority|null find($id, $lockMode = null, $lockVersion = null)
 * @method Authority|null findOneBy(array $criteria, array $orderBy = null)
 * @method Authority[]    findAll()
 * @method Authority[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AuthorityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Authority::class);
    }

    // /**
    //  * @return Authority[] Returns an array of Authority objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Authority
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */


    public function findByNameAndIDRange($name, $id_max) {
        return $this->createQueryBuilder('a')
                    ->andWhere('a.urlNameFormatter = :name')
                    ->andWhere('a.id < :id_max')
                    ->setParameter('name', $name)
                    ->setParameter('id_max', $id_max)
                    ->getQuery()
                    ->getResult();
    }


    /**
     * set authority for external IDs in $person_list
     */
    public function setAuthority($item_list) {
        // an entry in id_external belongs to one item at most
        $id_external_list_meta = array();
        foreach ($item_list as $item_loop) {
            $id_external_list_meta[] = $item_loop->getIdExternal()->toArray();
        }
        $id_external_list = array_merge(...$id_external_list_meta);

        $auth_id_list = array();
        foreach ($id_external_list as $id_loop) {
            $auth_id_list[] = $id_loop->getAuthorityId();
        }
        $auth_id_list = array_unique($auth_id_list);

        if (count($auth_id_list) == 0) {
            return null;
        }

        // get all relevant authorities
        $qb = $this->createQueryBuilder('a')
                   ->select('a')
                   ->andWhere('a.id in (:auth_id_list)')
                   ->setParameter('auth_id_list', $auth_id_list);

        $query = $qb->getQuery();
        $result = $query->getResult();


        // match authorities by id
        // the result list is not large so the filter is no performance problem
        $id_loop = null;
        foreach ($id_external_list as $id_loop) {
            $auth_id = $id_loop->getAuthorityId();
            $auth = array_filter($result, function($el) use ($auth_id) {
                return ($el->getId() == $auth_id);
            });
            $auth_obj = !is_null($auth) ? array_values($auth)[0] : null;
            $id_loop->setAuthority($auth_obj);
        }

        return null;

    }

    /**
     *
     */
    public function baseUrlList_legacy($id_list) {
        $qb = $this->createQueryBuilder('a')
                   ->select('a.id, a.url')
                   ->andWhere('a.id in (:auth_id_list)')
                   ->setParameter('auth_id_list', $id_list);

        $query = $qb->getQuery();
        $query_result = $query->getResult();

        $id_short = array_flip(Authority::ID);
        $result = [];
        foreach($query_result as $url_loop) {
            $result[$id_short[$url_loop['id']]] = $url_loop['url'];
        }

        return $result;

    }

    /**
     * 2023-03-02 obsolete?
     */
    public function baseUrlList($id_list) {
        $qb = $this->createQueryBuilder('a')
                   ->select('a.id, a.url')
                   ->andWhere('a.id in (:auth_id_list)')
                   ->setParameter('auth_id_list', $id_list);

        $query = $qb->getQuery();
        $query_result = $query->getResult();

        $result = array();
        foreach($query_result as $r) {
            $result[$r['id']] = $r['url'];
        }

        return $result;
    }

    public function findList($id_list) {
        $qb = $this->createQueryBuilder('a')
                   ->andWhere('a.id in (:auth_id_list)')
                   ->setParameter('auth_id_list', $id_list);

        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestUrlName($name, $hint_size, $exclude_ids) {
        $repository = $this->getEntityManager()->getRepository(Authority::class);
        $qb = $repository->createQueryBuilder('a')
                         ->select("DISTINCT a.urlNameFormatter AS suggestion")
                         ->andWhere('a.urlNameFormatter LIKE :name')
                         ->addOrderBy('a.urlNameFormatter')
                         ->setParameter('name', '%'.$name.'%')
                         ->andWhere('a.id not in (:exclude_ids)')
                         ->andWhere('a.id < 1000')
                         ->setParameter('exclude_ids', $exclude_ids);

        $qb->setMaxResults($hint_size);

        $query = $qb->getQuery();

        $suggestions = $query->getResult();

        return $suggestions;
    }


}
