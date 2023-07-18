<?php

namespace App\Repository;

use App\Entity\NameLookup;
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
    public function clearForPerson($person) {
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
     * insert entries for $person
     * do not flush
     */
    public function insert($person) {
        $entityManager = $this->getEntityManager();

        // insert new entries
        $variant_list = $this->makeVariantList($person);
        foreach ($variant_list as $variant) {
            $nl_new = new NameLookup();
            $nl_new->setPersonId($person->getId());
            $entityManager->persist($nl_new);

            $nl_new->setGnFn($variant[0]);
            $nl_new->setgnPrefixFn($variant[1]);
        }
    }

    /**
     * makeVariantList($person);
     *
     * return array of combinations of name elements
     */
    private function makeVariantList($person) {
        $givenname = $person->getGivenname();
        $prefix = $person->getPrefixName();
        $familyname = $person->getFamilyname();
        $givenname_variants = $person->getGivennameVariants();
        $familyname_variants = $person->getFamilynameVariants();
        $note_name = $person->getNoteName();

        $lookup_list = array();
        // rare case of missing givenname
        if (is_null($givenname) || trim($givenname) == "") {
            if (!is_null($prefix) && $prefix != "") {
                $lookup_list[] = array($familyname, implode(' ', [$prefix, $familyname]));
            } else {
                $lookup_list[] =  array($familyname, null);
            }
            foreach ($familyname_variants as $fn_loop) {
                if (!is_null($prefix) && $prefix != "") {
                    $lookup_list[] = array($fn_loop, implode(' ', [$prefix, $fn_loop]));
                } else {
                    $lookup_list[] =  array($fn_loop, null);
                }
            }
            return $lookup_list;
        }

        // make entry with $givenname
        $lookup_list[] = $this->makeVariant($givenname, $prefix, $familyname, $note_name);
        // - make an entry with the first of several givennames
        $gn_list = explode(' ', $givenname);
        if (count($gn_list) > 1) {
            $lookup_list[] = $this->makeVariant($gn_list[0], $prefix, $familyname, $note_name);
        }
        foreach ($familyname_variants as $fn_loop) {
            $lookup_list[] = $this->makeVariant($givenname, $prefix, $fn_loop, $note_name);
            if (count($gn_list) > 1) {
                $lookup_list[] = $this->makeVariant($gn_list[0], $prefix, $fn_loop, $note_name);
            }
        }

        // make entries with variants of $givenname
        foreach ($givenname_variants as $gn_loop) {
            $lookup_list[] = $this->makeVariant($gn_loop, $prefix, $familyname, $note_name);
            $gnv_list = explode(' ', $gn_loop);
            if (count($gnv_list) > 1) {
                $lookup_list[] = $this->makeVariant($gnv_list[0], $prefix, $familyname, $note_name);
            }
            foreach ($familyname_variants as $fn_loop) {
                $lookup_list[] = $this->makeVariant($gn_loop, $prefix, $fn_loop, $note_name);
                // - make an entry with the first of several givennames
                if (count($gnv_list) > 1) {
                    $lookup_list[] = $this->makeVariant($gnv_list[0], $prefix, $fn_loop, $note_name);
                }
            }
        }

        return $lookup_list;
    }

    private function makeVariant($givenname, $prefix, $familyname, $note_name) {
        $vn_0 = null;
        $vn_1 = null;

        $vn_list_0 = [$givenname];
        if (!is_null($familyname) and trim($familyname) != "") {
            $vn_list_0[] = $familyname;
        }
        if (!is_null($note_name) and trim($note_name) != "") {
            $vn_list_0[] = $note_name;
        }
        $vn_0 = implode($vn_list_0, ' ');

        if (!is_null($prefix) and trim($prefix) != "") {
            $vn_list_1 = [$givenname, $prefix];
            if (!is_null($familyname) and trim($familyname) != "") {
                $vn_list_1[] = $familyname;
            }
            if (!is_null($note_name) and trim($note_name) != "") {
                $vn_list_1[] = $note_name;
            }
            $vn_1 = implode($vn_list_1, ' ');
        }
        return array($vn_0, $vn_1);
    }


}
