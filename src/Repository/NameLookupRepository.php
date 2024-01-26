<?php

namespace App\Repository;

use App\Entity\NameLookup;
use App\Service\UtilService;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NameLookup|null find($id, $lockMode = null, $lockVersion = null)
 * @method NameLookup|null findOneBy(array $criteria, array $orderBy = null)
 * @method NameLookup[]    findAll()
 * @method NameLookup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NameLookupRepository extends ServiceEntityRepository
{

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NameLookup::class);
    }

    // /**
    //  * @return NameLookup[] Returns an array of NameLookup objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('n.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?NameLookup
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    /**
     * clear entries for $person
     * do not flush
     */
    public function clearPerson($person) {
        $entityManager = $this->getEntityManager();

        $id_list = array($person->getId());

        $qb = $this->createQueryBuilder('nl')
                   ->andWhere('nl.personId in (:id_list)')
                   ->setParameter('id_list', $id_list);
        $nl_list = $qb->getQuery()->getResult();
        foreach ($nl_list as $nl) {
            $entityManager->remove($nl);
        }
        return count($nl_list);
    }


    /**
     * update entries for $person
     * do not flush
     */
    public function update($person) {
        $entityManager = $this->getEntityManager();

        $this->clearPerson($person);

        // insert new entries

        $variant_list = $this->makeVariantList($person);
        foreach ($variant_list as $variant) {
            $nl_new = new NameLookup();
            $nl_new->setPersonId($person->getId());
            $entityManager->persist($nl_new);

            $nl_new->setNameVariant($variant[1]);
        }
    }

    private function makeVariantList($person) {
        $givenname = $person->getGivenname();
        $prefix = $person->getPrefixName();
        $familyname = $person->getFamilyname();
        $givenname_variants = $person->getGivennameVariants();
        $familyname_variants = $person->getFamilynameVariants();
        $note_name = $person->getNoteName();

        $choice_list = array();
        if (!is_null($givenname)) {
            if (!$givenname_variants->isEmpty()) {
                $gnv = $givenname_variants->map(function($v) { return $v->getName(); });
                $choice_list[] = array($givenname, ...$gnv);
            } else {
                $choice_list[] = array($givenname);
            }
        }
        if (!is_null($prefix)) {
            $choice_list[] = array($prefix);
        }
        if (!is_null($familyname)) {
            if (!$familyname_variants->isEmpty()) {
                $fnv = $familyname_variants->map(function($v) { return $v->getName(); });
                $choice_list[] = array($familyname, ...$fnv);
            } else {
                $choice_list[] = array($familyname);
            }
        }
        if (trim($note_name) != "") {
            $note_name = str_replace(';', ',', $note_name);
            $note_name_list = explode(",", $note_name);
            $note_name_list = array_map('trim', $note_name_list);
            $choice_list[] = $note_name_list;
        }

        $choice_prod_list = Utilservice::array_cartesian(...$choice_list);
        $lookup_list = array();
        foreach($choice_prod_list as $p_list) {
            $lookup_str = join(" ", $p_list);
            $lookup_list[] = array($lookup_str, $lookup_str);
        }
        return $lookup_list;
    }

}
