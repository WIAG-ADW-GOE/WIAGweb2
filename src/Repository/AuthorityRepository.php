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
     * set authority for external IDs in $person_list
     */
    public function setAuthority($person_list) {
        // an entry in id_external belongs to one item at most
        $id_external_list_meta = array();
        foreach ($person_list as $p) {
            $id_external_list_meta[] = $p->getItem()->getIdExternal()->toArray();
        }
        $id_external_list = array_merge(...$id_external_list_meta);

        $auth_id_list = array();
        foreach ($id_external_list as $id_loop) {
            $auth_id_list[] = $id_loop->getAuthorityId();
        }
        $auth_id_list = array_unique($auth_id_list);

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

}
