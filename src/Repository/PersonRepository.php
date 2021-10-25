<?php

namespace App\Repository;

use App\Entity\Person;
use App\Form\Model\BishopFormModel;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Person|null find($id, $lockMode = null, $lockVersion = null)
 * @method Person|null findOneBy(array $criteria, array $orderBy = null)
 * @method Person[]    findAll()
 * @method Person[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Person::class);
    }

    // /**
    //  * @return Person[] Returns an array of Person objects
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
    public function findOneBySomeField($value): ?Person
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function bishopCountByModel(BishopFormModel $model) {
        $result = array(1 => 0);
        if($model->isEmpty()) return $result;

        $qb = $this->createQueryBuilder('p')
                   ->select('COUNT(DISTINCT p.id)');

        $this->bishopQueryConditions($qb, $model);

        $query = $qb->getQuery();

        $result = $query->getOneOrNullResult();
        return $result;
    }


    private function bishopQueryConditions($qb, BishopFormModel $model) {

        # identifier
        $someid = $model->someid;
        # TODO extract numerical part of idPublic
        if($someid && $someid != "") {
            $qb->from('App\Entity\Item', 'item')
               ->andWhere('item.id = p.id')
               ->andWhere(':someid = item.idPublic')
               ->setParameter('someid', $someid);
        }

        # name


        return $qb;
    }

    private function bishopAndOfficeByModel(BishopFormModel $model, $limit = 0, $offset = 0) {


    }




}
