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

    /**
     * set authority for external URLs in $person_list
     */
    public function setAuthority($item_list) {
        // an entry in id_external belongs to one item at most
        $url_external_list_meta = array();
        foreach ($item_list as $item_loop) {
            $url_external_list_meta[] = $item_loop->getUrlExternal()->toArray();
        }
        $url_external_list = array_merge(...$url_external_list_meta);

        $auth_id_list = array();
        foreach ($url_external_list as $url_loop) {
            $auth_id_list[] = $url_loop->getAuthorityId();
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
        foreach ($url_external_list as $url_loop) {
            $auth_id = $url_loop->getAuthorityId();
            $auth = array_filter($result, function($el) use ($auth_id) {
                return ($el->getId() == $auth_id);
            });
            $auth_obj = (!is_null($auth) && count($auth) > 0) ? array_values($auth)[0] : null;
            $url_loop->setAuthority($auth_obj);
        }

        return null;

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
    public function suggestUrlType($name, $hint_size) {
        $repository = $this->getEntityManager()->getRepository(Authority::class);
        $qb = $repository->createQueryBuilder('a')
                         ->select("DISTINCT a.urlType AS suggestion")
                         ->addOrderBy('a.urlType');

        if (!is_null($name)) {
            $qb->andWhere('a.urlType LIKE :name')
               ->setParameter('name', '%'.$name.'%');
        }

        $qb->setMaxResults($hint_size);

        $query = $qb->getQuery();

        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestUrlNameFormatter($q_param, $hint_size) {
        $repository = $this->getEntityManager()->getRepository(Authority::class);
        $qb = $repository->createQueryBuilder('a')
                         ->select("DISTINCT a.urlNameFormatter AS suggestion")
                         ->andWhere('a.urlNameFormatter LIKE :q_param')
                         ->addOrderBy('a.urlNameFormatter')
                         ->setParameter('q_param', '%'.$q_param.'%');

        $qb->setMaxResults($hint_size);

        $query = $qb->getQuery();

        $suggestions = $query->getResult();

        return $suggestions;
    }

    public function findByModel($model) {
        $qb = $this->createQueryBuilder('a')
                   ->select('a');

        if ($model['type'] != '') {
            $type = $model['type'];
            $qb->andWhere('a.urlType = :type')
               ->setParameter('type', $type);
        }

        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     * @return array indexed by ID
     */
    public function findMappedArray($id_list) {
        $qb = $this->createQueryBuilder('a')
                   ->andWhere('a.id in (:id_list)')
                   ->setParameter('id_list', $id_list);

        $query = $qb->getQuery();
        $list = $query->getArrayResult();

        $idx_list = array_column($list, 'id');
        $list_idx = array_combine($idx_list, $list);

        return $list_idx;
    }


}
