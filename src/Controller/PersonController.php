<?php
namespace App\Controller;

use App\Entity\Corpus;
use App\Entity\Item;
use App\Entity\ItemNameRole;
use App\Entity\Role;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\Authority;
use App\Entity\UrlExternal;
use App\Entity\ReferenceVolume;

use App\Form\PersonFormType;
use App\Form\Model\PersonFormModel;

use App\Service\PersonService;
use App\Service\UtilService;
use App\Service\AutocompleteService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Doctrine\ORM\EntityManagerInterface;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;


class PersonController extends AbstractController {
    /** number of items per page */
    const PAGE_SIZE = 20;
    /** number of suggestions in autocomplete list */
    const HINT_SIZE = 8;
    /** max size of data response */
    const DATA_MAX_SIZE = 60000;


    private $autocomplete = null;

    public function __construct(AutocompleteService $service) {
        $this->autocomplete = $service;
    }

    /**
     * display query form for bishops (legacy route)
     *
     * @Route("/bischoefe")
     * @Route("/api/bischoefe")
     */
    public function queryBishop(Request $request,
                                EntityManagerInterface $entityManager) {
        return $this->query('epc', $request, $entityManager);
    }

    /**
     * display query form for canons (legacy route)
     *
     * @Route("/domherren")
     * @Route("/api/domherren")
     */
    public function queryCanon(Request $request,
                               EntityManagerInterface $entityManager) {
        return $this->query('can', $request, $entityManager);
    }

    /**
     * display query form for persons; handle query
     *
     * @Route("/query/{corpusId}", name="person_query")
     * $corpusId denotes the topic. Several corpora may be involved
     */
    public function query($corpusId,
                          Request $request,
                          EntityManagerInterface $entityManager) {

        $itemNameRoleRepository = $entityManager->getRepository(ItemNameRole::class);
        $personRepository = $entityManager->getRepository(Person::class);
        $corpusRepository = $entityManager->getRepository(Corpus::class);
        $corpus = $corpusRepository->findOneByCorpusId($corpusId);

        // call with empty $request?
        $flagInit = count($request->request->all()) == 0;

        $model = PersonFormModel::newByArray($request->query->all());
        $model->corpus = $corpusId;

        $form = $this->createForm(PersonFormType::class, $model, [
            'forceFacets' => $flagInit,
            'repository' => $itemNameRoleRepository,
        ]);

        if ($request->isMethod('GET')) {
            $offset = $request->query->get('offset');
            $page_number = $request->query->get('pageNumber');
        } else {
            $form->handleRequest($request);
            $model = $form->getData();
            $offset = $request->request->get('offset');
            $page_number = $request->request->get('pageNumber');
        }


        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->renderForm('person/query.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'corpus' => $corpusId,
                'pageTitle' => $corpus->getPageTitle(),
                'msg' => null,
            ]);
        }

        $id_all = $itemNameRoleRepository->findPersonIds($model);
        $count = count($id_all);

        // set offset to page begin
        $offset = UtilService::offset($offset, $page_number, $count, self::PAGE_SIZE);

        $id_list = array_slice($id_all, $offset, self::PAGE_SIZE);
        $person_list = $personRepository->findList($id_list);

        $template_param_list = [
            'menuItem' => 'collections',
            'form' => $form,
            'count' => $count,
            'personList' => $person_list,
            'roleSortCritList' => ['dateSortKey', 'id'],
            'offset' => $offset,
            'pageSize' => self::PAGE_SIZE,
            'pageTitle' => $corpus->getPageTitle(),
        ];

        return $this->renderForm('person/query_result.html.twig', $template_param_list);
    }


    /**
     * 2023-09-29 obsolete? was relevant for can, epc, dreg
     * set $person->role to dreg-roles if present
     */
    private function setListViewRole($person_list, $entityManager) {
        $itemNameRoleRepository = $entityManager->getRepository(ItemNameRole::class);
        $personRepository = $entityManager->getRepository(Person::class);

        foreach ($person_list as $p_epc_maybe) {
            $corpus_id_list = $p_epc_maybe->getItem()->getCorpusIdList();
            if (count($corpus_id_list) == 1 and $corpus_id_list[0] == 'epc') {
                // look up item_name_role
                $pir_list = $itemNameRoleRepository->findPersonIdRole($p_epc_maybe->getId());
                // find person, set role
                if (count($pir_list) > 1) {
                    $p_dreg = $personRepository->find($pir_list[1]);
                    $p_epc_maybe->setRole($p_dreg->getRole());
                }
            }
        }
        return $person_list;
    }

    /**
     * display details
     *
     * @Route("/listelement/{corpusId}", name="person_list_detail")
     */
    public function personListDetail($corpusId,
                                     Request $request,
                                     EntityManagerInterface $entityManager) {

        $personRepository = $entityManager->getRepository(Person::class);
        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class);
        $corpusRepository = $entityManager->getRepository(Corpus::class);
        $corpus = $corpusRepository->findOneByCorpusId($corpusId);
        $itemNameRoleRepository = $entityManager->getRepository(ItemNameRole::class);
        $itemRepository = $entityManager->getRepository(Item::class);

        $model = new PersonFormModel;

        $model->corpus = $corpusId;

        $form = $this->createForm(PersonFormType::class, $model, [
            'forceFacets' => false,
            'repository' => $itemNameRoleRepository,
        ]);
        $form->handleRequest($request);

        $offset = $request->request->get('offset');

        $model = $form->getData();

        $hassuccessor = false;
        $idx = 0;
        if($offset == 0) {
            $ids = $itemNameRoleRepository->findPersonIds($model,
                                                          2,
                                                          $offset);
            if(count($ids) == 2) $hassuccessor = true;

        } else {
            $ids = $itemNameRoleRepository->findPersonIds($model,
                                                          3,
                                                          $offset - 1);
            if(count($ids) == 3) $hassuccessor = true;
            $idx += 1;
        }

        $person_id = $ids[$idx];

        // get person data with offices

        $item_list = $itemRepository->findItemNameRole([$person_id]);
        $item = array_values($item_list)[0];
        $person = $item->getPerson();
        $person_role_list = $item->getPersonRole();

        return $this->render('person/person.html.twig', [
            'form' => $form->createView(),
            'personName' => $person,
            'personRole' => $person_role_list,
            'roleSortCritList' => ['dateSortKey', 'id'],
            'offset' => $offset,
            'hassuccessor' => $hassuccessor,
            'pageTitle' => $corpus->getPageTitle(),
        ]);

    }

    /**
     * return bishop data (legacy route)
     * @Route("/bischof/data")
     */
    public function queryBishopData(Request $request,
                                    EntityManagerInterface $entityManager,
                                    PersonService $personService) {

        return $this->queryData('epc', $request, $entityManager, $personService);
    }


    /**
     * return canon data (legacy routes)
     * @Route("/domherr/data")
     */
    public function queryCanonData(Request $request,
                                   EntityManagerInterface $entityManager,
                                   PersonService $personService) {

        return $this->queryData('can', $request, $entityManager, $personService);
    }

    /**
     * return person data
     *
     * @Route("/data/{corpusId}", name="person_query_data")
     */
    public function queryData($corpusId,
                              Request $request,
                              EntityManagerInterface $entityManager,
                              PersonService $personService) {

        $itemNameRoleRepository = $entityManager->getRepository(ItemNameRole::class);
        $personRepository = $entityManager->getRepository(Person::class);
        $personRoleRepository = $entityManager->getRepository(PersonRole::class);
        $referenceVolumeRepository = $entityManager->getRepository(ReferenceVolume::class);
        $corpusRepository = $entityManager->getRepository(Corpus::class);
        $corpus = $corpusRepository->findOneByCorpusId($corpusId);

        if ($request->isMethod('POST')) {
            $model = PersonFormModel::newByArray($request->request->get('person_form'));
            $format = $request->request->get('format') ?? 'json';
        } else {
            $model = PersonFormModel::newByArray($request->query->all());
            $format = $request->query->get('format') ?? 'json';
            $allowed_list = ['name', 'diocese', 'domstift', 'place', 'office', 'year', 'someid', 'format'];
            $unkown_list = array_diff(array_keys($request->query->all()), $allowed_list);
            $error_node_list['error']['message'] = "Query contains unknown parameter keys.";
            foreach ($unkown_list as $ukk) {
                $error_node_list['error']['unknown key'] = $ukk;
            }
            if (count($unkown_list) > 0) {
                return $personService->createResponse($format, $error_node_list);
            }
        }
        $format = ucfirst(strtolower($format));

        $model->corpus = $corpusId;
        $model->isDeleted = 0; # 2023-10-12 obsolete?
        $id_all = $itemNameRoleRepository->findPersonIds($model);

        if (count($id_all) >= self::DATA_MAX_SIZE) {
            if ($request->isMethod('POST')) {
                $msg = "Das Maximum von $data_max_size für die Zahl der Datensätze bei der Ausgabe strukturierter Daten ist überschritten. Schränken Sie die Auswahl ein.";
                return $this->queryError($corpusId, $msg, $entityManager);
            } else {
                $error_node_list['error'] = [
                'message' => "Query result is larger than the upper limmit",
                'limit' => $data_max_size,
                ];
                return $personService->createResponse($format, $error_node_list);
            }
        }

        $volume_list = $referenceVolumeRepository->findArray();

        $chunk_offset = 0;
        $chunk_size = 200;
        // split up in chunks
        $count = count($id_all);
        $node_list = array();
        while ($chunk_offset < $count) {
            $id_chunk = array_slice($id_all, $chunk_offset, $chunk_size);
            $chunk_offset += $chunk_size;

            $person_chunk = $personRepository->findArray($id_chunk);
            // list of persons with role data
            $person_role_chunk = $itemNameRoleRepository->findPersonRoleArray($id_chunk);
            PersonService::setVolume($person_role_chunk, $volume_list);

            // fill $node_list
            foreach($person_chunk as $person) {
                $inr = $person['item']['itemNameRole'];
                $item_id_role_list = array_column($inr, 'itemIdRole');
                $person_role_list = UtilService::findAllArray($person_role_chunk, 'id', $item_id_role_list);
                $node_list[] = $personService->personData($format, $person, $person_role_list);
            }


            // avoid memory overflow
            unset($person_chunk);
            unset($person_role_chunk);

        }

        $corpus_txt = str_replace(' ', '-', $corpus->getName());
        $filename = "WIAG-".$corpus_txt;

        return $personService->createResponse($format, $node_list, $filename);

    }

    public function queryError($corpusId,
                               $msg,
                               EntityManagerInterface $entityManager) {

        $itemNameRoleRepository = $entityManager->getRepository(ItemNameRole::class);
        $personRepository = $entityManager->getRepository(Person::class);
        $corpusRepository = $entityManager->getRepository(Corpus::class);
        $corpus = $corpusRepository->findOneByCorpusId($corpusId);


        // we need to pass an instance of PersonFormModel, because facets depend on it's data
        $model = new PersonFormModel;
        $model->corpus = $corpusId;

        $form = $this->createForm(PersonFormType::class, $model, [
            'forceFacets' => false,
            'repository' => $itemNameRoleRepository,
            'action' => $this->generateUrl('person_query', ['corpusId' => $corpusId]),
        ]);

        return $this->renderForm('person/query.html.twig', [
            'menuItem' => 'collections',
            'form' => $form,
            'corpus' => $corpusId,
            'pageTitle' => $corpus->getPageTitle(),
            'msg' => $msg,
        ]);

    }


    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/person-suggest/{field}/{hintSizeParam}", name="person_suggest")
     * response depends on item type
     */
    public function autocomplete(Request $request,
                                 String $field,
                                 int $hintSizeParam = 0) {
        $query_param = $request->query->get('q');
        $fnName = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $hint_size = $hintSizeParam == 0 ? self::HINT_SIZE : $hintSizeParam;

        $suggestions = $this->autocomplete->$fnName($query_param, $hint_size);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/person-suggest-edit/{corpusId}/{field}", name="person_suggest_edit")
     */
    public function autocompleteEdit(String $corpusId,
                                     String $field,
                                     Request $request) {
        $query_param = $request->query->get('q');
        $fnName = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $hint_size = self::HINT_SIZE;
        $is_online = 0;
        $corpus_id_list = explode(',', $corpusId);

        $suggestions = $this->autocomplete->$fnName($query_param, $hint_size, $is_online, $corpus_id_list);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }


    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/person-suggest-online/{corpusId}/{field}", name="person_suggest_online")
     * filter by Item->isOnline
     */
    public function autocompleteOnline(Request $request,
                                       String $corpusId,
                                       String $field) {
        $query_param = $request->query->get('q');
        $fnName = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $corpus_id_list = [$corpusId];
        if ($corpusId == 'can') {
            $corpus_id_list[] = 'dreg-can';
        }

        $is_online = 1;
        $suggestions = $this->autocomplete->$fnName($query_param, self::HINT_SIZE, $is_online, $corpus_id_list);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }


}
