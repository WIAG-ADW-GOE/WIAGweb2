<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Person;
use App\Entity\Authority;
use App\Entity\UrlExternal;
use App\Repository\PersonRepository;
use App\Repository\ItemRepository;
use App\Form\BishopFormType;
use App\Form\Model\BishopFormModel;
use App\Entity\Role;

use App\Service\PersonService;
use App\Service\UtilService;

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

class BishopController extends AbstractController {
    /** number of items per page */
    const PAGE_SIZE = 20;
    /** number of suggestions in autocomplete list */
    const HINT_SIZE = 8;

    private $itemTypeId;

    public function __construct() {

        $this->itemTypeId = Item::ITEM_TYPE_ID['Bischof']['id'];
    }

    /**
     * display query form for bishops; handle query
     *
     * @Route("/bischof", name="bishop_query")
     */
    public function query(Request $request,
                          EntityManagerInterface $entityManager,
                          RouterInterface $router) {

        $personRepository = $entityManager->getRepository(Person::class);

        // we need to pass an instance of BishopFormModel, because facets depend on it's data
        $model = new BishopFormModel;

        $flagInit = count($request->request->all()) == 0;

        $form = $this->createForm(BishopFormType::class, $model, [
            'forceFacets' => $flagInit,
        ]);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->renderForm('bishop/query.html.twig', [
                    'menuItem' => 'collections',
                    'form' => $form,
            ]);
        } else {

            $offset = $request->request->get('offset');
            $page_number = $request->request->get('pageNumber');

            $itemRepository = $entityManager->getRepository(Item::class);
            $id_all = $itemRepository->bishopIds($model);
            $count = count($id_all);

            // set offset to page begin
            $offset = UtilService::offset($offset, $page_number, $count, self::PAGE_SIZE);

            $itemRepository = $entityManager->getRepository(Item::class);
            $model->editStatus = [Item::ITEM_TYPE[$this->itemTypeId]['online_status']];
            $id_all = $itemRepository->bishopIds($model);
            $count = count($id_all);

            $id_list = array_slice($id_all, $offset, self::PAGE_SIZE);
            $person_list = $personRepository->findList($id_list);

            return $this->renderForm('bishop/query_result.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'count' => $count,
                'personList' => $person_list,
                'offset' => $offset,
                'pageSize' => self::PAGE_SIZE,
            ]);
        }
    }

    /**
     * display details for a bishop
     *
     * @Route("/bischof/listenelement", name="bishop_list_detail")
     */
    public function bishopListDetail(Request $request,
                                     EntityManagerInterface $entityManager) {

        $itemRepository = $entityManager->getRepository(Item::class);
        $personRepository = $entityManager->getRepository(Person::class);
        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class);

        $model = new BishopFormModel;

        $form = $this->createForm(BishopFormType::class, $model);
        $form->handleRequest($request);

        $offset = $request->request->get('offset');

        $model = $form->getData();

        $hassuccessor = false;
        $idx = 0;
        if($offset == 0) {
            $ids = $itemRepository->bishopIds($model,
                                              2,
                                              $offset);
            if(count($ids) == 2) $hassuccessor = true;

        } else {
            $ids = $itemRepository->bishopIds($model,
                                              3,
                                              $offset - 1);
            if(count($ids) == 3) $hassuccessor = true;
            $idx += 1;
        }

        $person_id = $ids[$idx];

        // collect office data in Person[] from different sources
        $personRole = $itemRepository->getBishopOfficeData($person_id);
        $person = null;
        foreach($personRole as $person_loop) {
            if ($person_loop->getId() == $person_id) {
                $person = $person_loop;
                break;
            }
        }

        $itemRepository->setSibling($person);
        // find external URLs for sibling (Domherr GS)
        $sibling = $person->getSibling();
        if (!is_null($sibling)) {
            $urlByType = $urlExternalRepository->groupByType($sibling->getId());
            $sibling->setUrlByType($urlByType);
        }

        return $this->render('bishop/person.html.twig', [
            'form' => $form->createView(),
            'personName' => $person,
            'personRole' => $personRole,
            'offset' => $offset,
            'hassuccessor' => $hassuccessor,
        ]);


    }

    /**
     * return bishop data
     *
     * @Route("/bischof/data", name="bishop_query_data")
     */
    public function queryData(Request $request,
                              ItemRepository $itemRepository,
                              PersonRepository $personRepository,
                              PersonService $personService) {

        if ($request->isMethod('POST')) {
            $model = BishopFormModel::newByArray($request->request->get('bishop_form'));
            $format = $request->request->get('format') ?? 'json';
        } else {
            $model = BishopFormModel::newByArray($request->query->all());
            $format = $request->query->get('format') ?? 'json';
        }

        $id_all = $itemRepository->bishopIds($model);

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
                $personRole = $itemRepository->getBishopOfficeData($person);
                $node = $personService->personData($format, $person, $personRole);
                $node_list[] = $node;

            }
            $chunk_offset += $chunk_size;
            $id_list = array_slice($id_all, $chunk_offset, $chunk_size);
        }


        $format = ucfirst(strtolower($format));
        if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
            throw $this->createNotFoundException('Unbekanntes Format: '.$format);
        }

        // 2022-07-21 collect data in one db-query
        // $node_list = array();
        // foreach ($ids as $id) {

        //     $person = $personRepository->find($id['personId']);

        //     // collect office data in an array of Items
        //     $item_list = $repository->getBishopOfficeData($person);
        //     $node_list[] = $personService->personData($format, $person, $item_list);
        // }

        return $personService->createResponse($format, $node_list);

    }


    /**
     * usually used for asynchronous JavaScript request
     *
     * @Route("/bischof-suggest/{field}", name="bishop_suggest")
     */
    public function autocomplete(Request $request,
                                 ItemRepository $repository,
                                 String $field) {
        $name = $request->query->get('q');

        $edit_status = Item::ITEM_TYPE[$this->itemTypeId]['online_status'];
        return $this->handleAutocomplete($repository, $name, $field, $edit_status);

    }

    /**
     * usually used for asynchronous JavaScript request
     *
     * @Route("/bischof-suggest-all/{field}", name="bishop_suggest_all")
     */
    public function autocompleteAll(Request $request,
                                    ItemRepository $repository,
                                    String $field) {
        $name = $request->query->get('q');

        $edit_status = null;
        return $this->handleAutocomplete($repository, $name, $field, $edit_status);
    }


    private function handleAutocomplete($repository,
                                        $name,
                                        $field,
                                        $edit_status) {

        $fnName = 'suggestBishop'.ucfirst($field);
        if ($field == 'name') {
            $suggestions = $repository->$fnName($name, self::HINT_SIZE, $edit_status);
        } else {
            $suggestions = $repository->$fnName($name, self::HINT_SIZE);
        }

        return $this->render('bishop/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }



}
