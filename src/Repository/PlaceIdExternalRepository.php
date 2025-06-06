<?php

namespace App\Repository;

use App\Entity\PlaceIdExternal;
use App\Entity\Authority;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PlaceIdExternal|null find($id, $lockMode = null, $lockVersion = null)
 * @method PlaceIdExternal|null findOneBy(array $criteria, array $orderBy = null)
 * @method PlaceIdExternal[]    findAll()
 * @method PlaceIdExternal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlaceIdExternalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlaceIdExternal::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(PlaceIdExternal $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(PlaceIdExternal $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * Returns PlaceIdExternal[] Returns an array of PlaceIdExternal objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?PlaceIdExternal
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    /**
      Returns URL in the World Historical Gazetteer
     */
    public function findUrlWhg($id) {
        $qb = $this->createQueryBuilder('p')
                   ->addSelect('p, authority')
                   ->join('\App\Entity\Authority', 'authority', 'WITH', 'p.authorityId = authority.id')
                   ->andWhere('p.placeId = :placeId')
                   ->andWhere("authority.urlNameFormatter = 'World Historical Gazetteer'")
                   ->setParameter('placeId', $id);

        $query = $qb->getQuery();

        $pie = $query->getResult();

        $url = null;
        if ($pie) {
            $url_format = $pie[1]->getUrlFormatter();
            $url = str_replace('{id}', $pie[0]->getValue(), $url_format);
        }

        return $url;
    }

    /**
     * Returns array indexed by place ID
     */
    public function findMappedArray() {
        $qb = $this->createQueryBuilder('bp');

        $query = $qb->getQuery();
        $list = $query->getArrayResult();

        $idx_list = array_column($list, 'placeId');
        $list_idx = array_combine($idx_list, $list);

        $auth_id_list = array_unique(array_column($list, 'authorityId'));

        $auth_list = $this->getEntityManager()
                          ->getRepository(Authority::class)
                          ->findMappedArray($auth_id_list);

        foreach ($list_idx as &$id_ext) {
            $auth_id = $id_ext['authorityId'];
            $id_ext['format'] = $auth_list[$auth_id]['urlFormatter'];
        }

        return $list_idx;

    }

}
