<?php

namespace App\Repository;

use App\Entity\CanonLookupLine;
use App\Entity\Institution;
use App\Entity\Item;
use App\Entity\CanonGroup;
use App\Entity\Person;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CanonLookupLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method CanonLookupLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method CanonLookupLine[]    findAll()
 * @method CanonLookupLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CanonLookupLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CanonLookupLine::class);
    }

    // /**
    //  * @return CanonLookupLine[] Returns an array of CanonLookupLine objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?CanonLookupLine
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */


    public function findWithOffice($id, $itemTypeId) {
        if ($itemTypeId == 4) {// bishop
            $group = $this->findByPersonIdCanon($id);
            dump($group);
        }

        $personRepository = $this->getEntityManager()->getRepository(Person::class);


        $qb = $personRepository->createQueryBuilder('p')
                               ->join('p.role', 'pr')
                               ->addSelect('pr')
                               ->andWhere('p.id = :id')
                               ->setParameter('id', $id);
        $query = $qb->getQuery();
        $person = $query->getOneOrNullResult();

        // TODO $this->addInstitutionPlace($person);

        return $person;
    }

    /**
     * find all items that are related to the same canon with offices
     */
    function findRelatedCanon($id) {
        $qb = $this->createQueryBuilder('cr')
                   ->join('\App\Entity\Person', 'p', 'WITH', 'p.id = cr.personId')
                   ->join('p.item', 'i')
                   ->join('p.role', 'pr')
                   ->select('p, i, pr')
                   ->andWhere('cr.personIdCanon = :id')
                   ->setParameter('id', $id);

        $query = $qb->getQuery();

        $result = $query->getResult();

        $personRepository = $this->getEntityManager()->getRepository(Person::class);

        $typeMap = [4 => 'ep', 5 => 'dh', 6 => 'gs'];
        $cnGroup = new CanonGroup();
        // foreach($result as $p) {
        //     $personRepository->addInstitutionPlace($p);
        //     $elementName = $typeMap[$p->getItem()->getItemTypeId()];
        //     $cnGroup->$elementName = $p;
        // }
        return $cnGroup;
    }

    /**
     * find all items that are related to the same canon with offices and references
     */
    function findRelatedCanonWithAssociations($id) {
        $cnGroup = $this->findRelatedCanon($id);
        $personRepository = $this->getEntityManager()->getRepository(Person::class);

        foreach (['ep', 'dh', 'gs'] as $cn) {
            $person = $cnGroup->$cn;
            if ($person) {
                $personRepository->addReferenceVolumes($person);
            }
        }

        return $cnGroup;
    }

    /**
     * AJAX
     */
    public function suggestCanonName($name, $hintSize) {
        $qb = $this->createQueryBuilder('cl')
                   ->select("DISTINCT CASE WHEN n.gnPrefixFn IS NOT NULL ".
                            "THEN n.gnPrefixFn ELSE n.gnFn END ".
                            "AS suggestion")
                   ->join('App\Entity\NameLookup', 'n', 'WITH', 'n.personId = cl.personId')
                   ->andWhere('n.gnFn LIKE :name OR n.gnPrefixFn LIKE :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestCanonDomstift($name, $hintSize) {
        /* in order to have a uniform function call in the Controller we start here
         * instead of InstitutionRepository
         */
        $repository = $this->getEntityManager()->getRepository(Institution::class);

        $qb = $repository->createQueryBuilder('it')
                         ->select("DISTINCT it.name AS suggestion")
                         ->join('it.institutionPlace', 'ip')
                         ->join('it.item','i')
                         ->andWhere('i.itemTypeId = :itemType')
                         ->setParameter(':itemType', Item::ITEM_TYPE_ID['Domstift'])
                         ->andWhere('it.name LIKE :name OR ip.placeName LIKE :name')
                         ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestCanonOffice($name, $hintSize) {
        $qb = $this->createQueryBuilder('cl')
                   ->select("DISTINCT pr.roleName AS suggestion")
                   ->join('App\Entity\PersonRole', 'pr', 'WITH', 'cl.personId = pr.personId')
                   ->andWhere('pr.roleName like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();
        // dd($suggestions);

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestCanonPlace($name, $hintSize) {
        $qb = $this->createQueryBuilder('cl')
                   ->select("DISTINCT ip.placeName AS suggestion")
                   ->join('App\Entity\PersonRole', 'pr', 'WITH', 'cl.personId = pr.personId')
                   ->join('App\Entity\Institution', 'it', 'WITH', 'it.id = pr.institutionId')
                   ->join('it.institutionPlace', 'ip')
                   ->andWhere('ip.placeName like :name')
                   ->setParameter(':name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();
        // dd($suggestions);

        return $suggestions;
    }

}
