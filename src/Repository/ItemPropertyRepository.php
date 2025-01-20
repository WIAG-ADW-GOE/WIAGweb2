<?php

namespace App\Repository;

use App\Entity\ItemProperty;
use App\Service\UtilService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ItemProperty|null find($id, $lockMode = null, $lockVersion = null)
 * @method ItemProperty|null findOneBy(array $criteria, array $orderBy = null)
 * @method ItemProperty[]    findAll()
 * @method ItemProperty[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemPropertyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemProperty::class);
    }

    // /**
    //  * @return ItemProperty[] Returns an array of ItemProperty objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ItemProperty
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    /**
     * set ItemProperty for $item_list
     */
    public function setItemProperty($item_list) {

        $id_list = UtilService::collectionColumn($item_list, "id");

        $qb = $this->createQueryBuilder('ip')
                   ->andWhere('ip.itemId in (:list)')
                   ->setParameter(':list', $id_list)
                   ->select('ip');

        $query = $qb->getQuery();
        $result = $query->getResult();

        // lookup table for $item_list
        $item_dict = array_combine($id_list, $item_list);

        foreach ($result as $ip) {
            $item_id = $ip->getItemId();
            $item_dict[$item_id]->addItemProperty($ip);
        }

    }


    public function referenceCount($type_id) {
        $qb = $this->createQueryBuilder('ip')
                   ->select('COUNT(DISTINCT(ip.id)) as count')
                   ->andWhere('ip.propertyTypeId = :type_id')
                   ->setParameter('type_id', $type_id);
        $query = $qb->getQuery();

        return $query->getSingleResult()['count'];
    }

}
