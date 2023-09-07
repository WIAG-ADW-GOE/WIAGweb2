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


    private $autocomplete = null;

    public function __construct(AutocompleteService $service) {
        $this->autocomplete = $service;
    }

    /**
     * display query form for persons; handle query
     *
     * @Route("/person/query/{corpusId}", name="person_query")
     */
    public function query($corpusId,
                          Request $request,
                          EntityManagerInterface $entityManager,
                          RouterInterface $router) {

        $itemNameRoleRepository = $entityManager->getRepository(ItemNameRole::class);
        $personRepository = $entityManager->getRepository(Person::class);
        $corpusRepository = $entityManager->getRepository(Corpus::class);
        $corpus = $corpusRepository->findOneByCorpusId($corpusId);


        // we need to pass an instance of PersonFormModel, because facets depend on it's data
        $model = new PersonFormModel;

        $model->corpus = $corpusId;

        // call with empty $request?
        $flagInit = count($request->request->all()) == 0;

        $form = $this->createForm(PersonFormType::class, $model, [
            'forceFacets' => $flagInit,
            'repository' => $itemNameRoleRepository,
        ]);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->renderForm('person/query.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'corpus' => $corpusId,
                'pageTitle' => $corpus->getPageTitle(),
            ]);
        } else {
            $offset = $request->request->get('offset');
            $page_number = $request->request->get('pageNumber');

            $id_all = $itemNameRoleRepository->findPersonIds($model);
            $count = count($id_all);

            // set offset to page begin
            $offset = UtilService::offset($offset, $page_number, $count, self::PAGE_SIZE);

            $id_list = array_slice($id_all, $offset, self::PAGE_SIZE);
            $person_list = $personRepository->findList($id_list);

            $template_param_list = [
                'menuItem' => 'collections',
                'form' => $form,
                'corpus' => $corpusId,
                'count' => $count,
                'personList' => $person_list,
                'offset' => $offset,
                'pageSize' => self::PAGE_SIZE,
                'pageTitle' => $corpus->getPageTitle(),
            ];

            return $this->renderForm('person/query_result.html.twig', $template_param_list);
        }
    }

    /**
     * display details
     *
     * @Route("/person/listenelement/{corpusId}", name="person_list_detail")
     */
    public function personListDetail($corpusId,
                                     Request $request,
                                     EntityManagerInterface $entityManager) {

        $personRepository = $entityManager->getRepository(Person::class);
        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class);
        $corpusRepository = $entityManager->getRepository(Corpus::class);
        $corpus = $corpusRepository->findOneByCorpusId($corpusId);
        $itemNameRoleRepository = $entityManager->getRepository(ItemNameRole::class);

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

        // get office data, e.g. from Digitales Personenregister
        $person_role_id_list = $itemNameRoleRepository->findPersonIdRole($person_id);
        $person_role_list = $personRepository->findList($person_role_id_list);
        $person = array_values($person_role_list)[0];

        // TODO 2023-08-15 clean up sibling
        // $personRepository->setSibling([$person]);

        return $this->render('person/person.html.twig', [
            'form' => $form->createView(),
            'corpus' => $model->corpus,
            'personName' => $person,
            'personRole' => $person_role_list,
            'offset' => $offset,
            'hassuccessor' => $hassuccessor,
            'pageTitle' => $corpus->getPageTitle(),
        ]);

    }

    /**
     * return person data
     *
     * @Route("/person/data/{corpusId}", name="person_query_data")
     */
    public function queryData($corpusId,
                              Request $request,
                              EntityManagerInterface $entityManager,
                              PersonService $personService) {

        $itemNameRoleRepository = $entityManager->getRepository(ItemNameRole::class);
        $personRepository = $entityManager->getRepository(Person::class);
        $corpusRepository = $entityManager->getRepository(Corpus::class);
        $corpus = $corpusRepository->findOneByCorpusId($corpusId);

        if ($request->isMethod('POST')) {
            $model = PersonFormModel::newByArray($request->request->get('person_form'));
            $format = $request->request->get('format') ?? 'json';
        } else {
            $model = PersonFormModel::newByArray($request->query->all());
            $format = $request->query->get('format') ?? 'json';
        }
        $format = ucfirst(strtolower($format));

        $model->isDeleted = 0;
        $id_all = $itemNameRoleRepository->findPersonIds($model);

        $chunk_offset = 0;
        $chunk_size = 50;
        // split up in chunks
        $id_list = array_slice($id_all, $chunk_offset, $chunk_size);
        $node_list = array();
        while (count($id_list) > 0) {

            $person_list = $personRepository->findList($id_list);

            // find all sources (persons with roles) for the elements of $canon_personName
            foreach($person_list as $person) {
                // get roles
                $person_id = $person->getId();
                $dreg_id_list = $itemNameRoleRepository->findPersonIdRole($person_id);
                $person_id_list = array_merge(array($person_id), $dreg_id_list);
                $person_role_list = $personRepository->findList($person_id_list);

                $node = $personService->personData($format, $person, $person_role_list);
                $node_list[] = $node;

            }
            $chunk_offset += $chunk_size;
            $id_list = array_slice($id_all, $chunk_offset, $chunk_size);
        }


        if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
            throw $this->createNotFoundException('Unbekanntes Format: '.$format);
        }

        return $personService->createResponse($format, $node_list);

    }


    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/person-suggest/{corpus}/{field}", name="person_suggest")
     * response depends on item type
     */
    public function autocomplete(Request $request,
                                 String $corpus,
                                 String $field) {
        $query_param = $request->query->get('q');
        $fnName = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $suggestions = $this->autocomplete->$fnName($corpus, $query_param, self::HINT_SIZE);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/person-suggest-online/{corpus}/{field}", name="person_suggest_online")
     * filter by Item->isOnline
     */
    public function autocompleteOnline(Request $request,
                                       String $corpus,
                                       String $field) {
        $query_param = $request->query->get('q');
        $fnName = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $is_online = 1;
        $suggestions = $this->autocomplete->$fnName($corpus, $query_param, self::HINT_SIZE, $is_online);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/person-suggest-base/{field}", name="person_suggest_base")
     * response is independent from corpus
     */
    public function autocompleteBase(Request $request,
                                     String $field) {
        $query_param = $request->query->get('q');
        $fnName = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $suggestions = $this->autocomplete->$fnName($query_param, self::HINT_SIZE);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

}
