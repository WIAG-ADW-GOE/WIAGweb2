<?php

namespace App\Service;

use App\Entity\Item;
use App\Entity\Corpus;
use App\Entity\Role;
use App\Entity\Person;
use App\Entity\PersonBirthplace;
use App\Entity\Diocese;
use App\Entity\Institution;
use App\Entity\InstitutionPlace;
use App\Entity\PersonRole;
use App\Entity\ReferenceVolume;
use App\Entity\Authority;

use App\Service\UtilService;


use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * provide autocomplete functions for Person forms
 */
class AutocompleteService extends ServiceEntityRepository {

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);

    }

    /**
     * usually used for asynchronous JavaScript request
     *
     */
    public function suggestRole($queryParam, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(Role::class);
        $qb = $repository->createQueryBuilder('r')
                         ->select("DISTINCT r.name AS suggestion")
                         ->andWhere('r.name LIKE :name')
                         ->setParameter('name', '%'.$queryParam.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     *
     * $corpus and $isOnline are not used here (needed for uniform signature)
     */
    public function suggestOffice($queryParam, $hintSize) {
        // use all office names as a basis;
        // this is simple and it does no harm, if office names show up that are not referenced anywhere
        $repository = $this->getEntityManager()->getRepository(PersonRole::class);
        $qb = $repository->createQueryBuilder('pr')
                         ->select("DISTINCT (CASE WHEN pr.roleId IS NULL THEN pr.roleName ELSE r.name END) AS suggestion")
                         ->leftjoin('pr.role', 'r')
                         ->andWhere('pr.roleName like :name OR r.name like :name')
                         ->setParameter(':name', '%'.$queryParam.'%')
                         ->orderBy('suggestion');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestDiocese($name, $hint_size) {
        $repository = $this->getEntityManager()->getRepository(Diocese::class);
        $qb = $repository->createQueryBuilder('d')
                         ->select("DISTINCT d.name AS suggestion")
                         ->andWhere('d.name LIKE :name')
                         ->orderBy('d.name')
                         ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hint_size);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request     *
     */
    public function suggestDomstift($queryParam, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(Institution::class);
        $qb = $repository->createQueryBuilder('inst')
                         ->select("DISTINCT inst.name AS suggestion")
                         ->join('\App\Entity\ItemCorpus', 'ic',
                                'WITH', "ic.itemId = inst.id AND ic.corpusId = 'cap'")
                         ->andWhere('inst.name like :name')
                         ->setParameter('name', '%'.$queryParam.'%')
                         ->orderBy('inst.name');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * 2023-09-06 TODO check how and where this is used in edit mode
     * usually used for asynchronous JavaScript request
     *
     * return the names of monasteries and domstifte
     */
    public function suggestInstitution($queryParam, $hintSize) {

        $repository = $this->getEntityManager()->getRepository(Institution::class);
        $qb = $repository->createQueryBuilder('inst')
                         ->select("DISTINCT inst.name AS suggestion")
                         ->andWhere("inst.corpusId in ('cap', 'mon')")
                         ->andWhere('inst.name like :name')
                         ->setParameter('name', '%'.$queryParam.'%')
                         ->orderBy('inst.name');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * autocomplete function for references
     *
     * usually used for asynchronous JavaScript request
     */
    public function suggestTitleShort($name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(ReferenceVolume::class);
        $qb = $repository->createQueryBuilder('v')
                         ->select("DISTINCT v.titleShort AS suggestion")
                         ->andWhere('v.titleShort LIKE :name')
                         ->addOrderBy('v.titleShort')
                         ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestPropertyValue($name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT prop.value AS suggestion")
                         ->join('i.itemProperty', 'prop')
                         ->andWhere('prop.value LIKE :name')
                         ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();

        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestRolePropertyName($name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT prop.name AS suggestion")
                         ->join('App\Entity\PersonRoleProperty', 'prop', 'WITH', 'i.id = prop.personId')
                         ->andWhere('prop.name LIKE :name')
                         ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestRolePropertyValue($name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT prop.value AS suggestion")
                         ->join('App\Entity\PersonRoleProperty', 'prop', 'WITH', 'i.id = prop.personId')
                         ->andWhere('prop.value LIKE :name')
                         ->setParameter('name', '%'.$name.'%');


        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * @return suggestions for edit status values for editable corpora
     *
     * usually used for asynchronous JavaScript request
     */
    public function suggestEditStatus($name, $hintSize) {
        // do not filter by name 'i.editStatus LIKE :name'
        // in a new single entry.
        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT i.editStatus AS suggestion")
                         ->join('i.itemCorpus', 'ic')
                         ->andWhere('ic.corpusId in (:corpus_id_list)')
                         ->andWhere('i.editStatus IS NOT NULL')
                         ->andWhere("i.mergeStatus in ('child', 'original')")
                         ->andWhere("i.isDeleted = 0")
                         ->setParameter('corpus_id_list', Corpus::EDIT_LIST)
                         ->orderBy('i.editStatus');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestNormdataEditedBy($name, $hintSize) {
        // do not filter by name 'i.editStatus LIKE :name'
        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT i.normdataEditedBy AS suggestion")
                         ->andWhere('i.normdataEditedBy like :name')
                         ->setParameter('name', '%'.$name.'%')
                         ->orderBy('i.normdataEditedBy');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * usually used for asynchronous JavaScript request
     */
    public function suggestName($q_param, $resultSize, $isOnline = 0, $corpus_id_list = null) {

        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT n.nameVariant AS suggestion")
                         ->join('App\Entity\NameLookup', 'n', 'WITH', 'n.personId = i.id')
                         ->join('i.itemCorpus', 'c')
                         ->andWhere('i.isDeleted = 0')
                         ->andWhere("i.mergeStatus in ('child', 'original')");

        if (!is_null($corpus_id_list)) {
            $qb->andWhere('c.corpusId in (:cil)')
               ->setParameter('cil', $corpus_id_list);
        }

        // require that every word of the search query occurs in the name, regardless of the order
        $q_list = Utilservice::nameQueryComponents($q_param);
        foreach($q_list as $key => $q_name) {
            $qb->andWhere('n.nameVariant LIKE :q_name_'.$key)
               ->setParameter('q_name_'.$key, '%'.trim($q_name).'%');
        }

        if ($isOnline != 0) {
            $qb->andWhere('i.isOnline = :is_online')
            ->setParameter('is_online', $isOnline);
        }

        $qb->setMaxResults($resultSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     *
     */
    public function suggestCommentDuplicate($queryParam, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(Item::class);
        $qb = $repository->createQueryBuilder('i')
                         ->select("DISTINCT i.commentDuplicate AS suggestion")
                         ->andWhere('i.commentDuplicate like :name')
                         ->setParameter(':name', '%'.$queryParam.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestPlace($queryParam, $hintSize) {
        // restrict suggestions to places referenced by person_role (any corpus)
        $repository = $this->getEntityManager()->getRepository(InstitutionPlace::class);
        $qb = $repository->createQueryBuilder('ip')
                         ->select("DISTINCT ip.placeName AS suggestion")
                         ->join('App\Entity\PersonRole', 'pr', 'WITH', 'pr.institutionId = ip.institutionId')
                         ->andWhere('ip.placeName like :name')
                         ->setParameter('name', '%'.$queryParam.'%');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * @return list of WIAG-IDs of bishops
     */
    public function suggestBishop($queryParam, $hint_size) {
        $repository = $this->getEntityManager()->getRepository(Item::class);

        $qb = $repository->createQueryBuilder('i')
                         ->join('i.itemCorpus', 'corpus')
                         ->select("DISTINCT i.idPublic AS suggestion")
                         ->andWhere('i.idPublic LIKE :queryParam')
                         ->andWhere("corpus.corpusId = 'epc'")
                         ->setParameter('queryParam', '%'.$queryParam.'%');

        $qb->setMaxResults($hint_size);

        $query = $qb->getQuery();

        $suggestions = $query->getResult();

        return $suggestions;
    }


    public function suggestUrlName($name, $hint_size) {
        // exclude core data and internal references
        $core_id_list = Authority::ESSENTIAL_ID_LIST;

        $repository = $this->getEntityManager()->getRepository(Authority::class);
        $qb = $repository->createQueryBuilder('a')
                         ->select("DISTINCT a.urlNameFormatter AS suggestion")
                         ->andWhere('a.urlNameFormatter LIKE :name')
                         ->andWhere('a.id not in (:core)')
                         ->andWhere("a.urlType != 'Interner Identifier'")
                         ->addOrderBy('a.urlNameFormatter')
                         ->setParameter('core', $core_id_list)
                         ->setParameter('name', '%'.$name.'%');

        $qb->setMaxResults($hint_size);

        $query = $qb->getQuery();

        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestPriestUtName($name, $hintSize) {
        $qb = $this->createQueryBuilder('i')
                   ->select("DISTINCT CASE WHEN n.nameVariant IS NOT NULL ".
                            "THEN n.nameVariant ELSE n.gnFn END ".
                            "AS suggestion")
                   ->join('\App\Entity\NameLookup', 'n', 'WITH', 'i.id = n.personId')
                   ->join('i.itemCorpus', 'corpus')
                   ->andWhere("corpus.corpusId = 'utp'");

        $q_list = UtilService::nameQueryComponents($name);
        foreach($q_list as $key => $q_name) {
            $qb->andWhere('n.nameVariant LIKE :q_name_'.$key)
               ->setParameter('q_name_'.$key, '%'.trim($q_name).'%');
        }

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestPriestUtBirthplace($name, $hintSize) {
        $repository = $this->getEntityManager()->getRepository(PersonBirthplace::class);

        $qb = $repository->createQueryBuilder('b')
                         ->select("DISTINCT b.placeName as suggestion")
                         ->join('App\Entity\Item', 'i', 'WITH', 'i.id = b.personId')
                         ->join('i.itemCorpus', 'corpus')
                         ->andWhere("i.isOnline = 1")
                         ->andWhere("i.isDeleted = 0")
                         ->andWhere("corpus.corpusId = 'utp'")
                         ->andWhere('b.placeName LIKE :value')
                         ->setParameter('value', '%'.$name.'%')
                         ->orderBy('suggestion');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }

    /**
     * AJAX
     */
    public function suggestPriestUtReligiousOrder($name, $hintSize) {
        $itemRepository = $this->getEntityManager()->getRepository(Item::class);

        $qb = $itemRepository->createQueryBuilder('i')
                             ->select("DISTINCT r.abbreviation as suggestion")
                             ->join('\App\Entity\Person', 'p', 'WITH', 'i.id = p.id')
                             ->join('p.religiousOrder', 'r')
                             ->join('i.itemCorpus', 'corpus')
                             ->andWhere("i.isOnline = 1")
                             ->andWhere("i.isDeleted = 0")
                             ->andWhere("corpus.corpusId = 'utp'")
                             ->andWhere('r.abbreviation LIKE :value')
                             ->setParameter('value', '%'.$name.'%')
                             ->orderBy('suggestion');

        $qb->setMaxResults($hintSize);

        $query = $qb->getQuery();
        $suggestions = $query->getResult();

        return $suggestions;
    }



}
