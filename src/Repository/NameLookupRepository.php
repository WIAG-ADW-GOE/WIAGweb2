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
        // name_lookup.gn_fn is obsolete but is still filled to be consistent
        $lookup_list = array();
        foreach($choice_prod_list as $p_list) {
            $lookup_str = join(" ", $p_list);
            $lookup_list[] = array($lookup_str, $lookup_str);
        }
        return $lookup_list;
    }


    /**
     * makeVariantList($person);
     *
     * return array of combinations of name elements
     */
    private function makeVariantList_legacy($person) {
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

            $lookup_list = $this->addNoteName($lookup_list, $note_name);
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

        $lookup_list = $this->addNoteName($lookup_list, $note_name);
        return $lookup_list;
    }

    private function addNoteName($list, $note_name) {
        if (is_null($note_name) or trim($note_name) == "") {
            return $list;
        }
        $note_name = str_replace(';', ',', $note_name);
        $note_list = explode(',', $note_name);
        $list_with_note = array();
        foreach ($list as $e) {
            foreach ($note_list as $note_loop) {
                $note_loop = trim($note_loop);
                $e_note = is_null($e[0]) ? $e[0] : $e[0].' '.$note_loop;
                $e_prefix_note = is_null($e[1]) ? $e[1] : $e[1].' '.$note_loop;
                $list_with_note[] = array($e_note, $e_prefix_note);
            }
        }
        return $list_with_note;
    }

    private function makeVariant($givenname, $prefix, $familyname) {
        $vn_0 = null;
        $vn_1 = null;

        // without prefix
        $vn_list_0 = [$givenname];
        if (!is_null($familyname) and trim($familyname) != "") {
            $vn_list_0[] = $familyname;
        }
        $vn_0 = implode($vn_list_0, ' ');

        // with prefix
        if (!is_null($prefix) and trim($prefix) != "") {
            $vn_list_1 = [$givenname, $prefix];
            if (!is_null($familyname) and trim($familyname) != "") {
                $vn_list_1[] = $familyname;
            }
            $vn_1 = implode($vn_list_1, ' ');
        }
        return array($vn_0, $vn_1);
    }


}
