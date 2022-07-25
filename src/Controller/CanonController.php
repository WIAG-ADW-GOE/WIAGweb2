<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Person;
use App\Entity\Canon;
use App\Entity\CanonLookup;
use App\Entity\UrlExternal;
use App\Entity\PersonRole;
use App\Entity\Role;
use App\Entity\ItemReference;
use App\Repository\PersonRepository;
use App\Repository\ItemRepository;
use App\Repository\CanonLookupRepository;
use App\Service\UtilService;
use App\Form\CanonFormType;
use App\Form\Model\CanonFormModel;

use App\Service\PersonService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Doctrine\ORM\EntityManagerInterface;


class CanonController extends AbstractController {
    /** number of items per page */
    const PAGE_SIZE = 20;
    /** number of suggestions in autocomplete list */
    const HINT_SIZE = 8;

    /**
     * display query form for canons; handle query
     *
     * @Route("/domherr", name="canon_query")
     */
    public function query(Request $request,
                          EntityManagerInterface $em,
                          CanonLookupRepository $repository,
                          UtilService $utilService) {

        // we need to pass an instance of CanonFormModel, because facets depend on it's data
        $model = new CanonFormModel;

        $flagInit = count($request->request->all()) == 0;

        $form = $this->createForm(CanonFormType::class, $model, [
            'forceFacets' => $flagInit,
        ]);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->renderForm('canon/query.html.twig', [
                    'menuItem' => 'collections',
                    'form' => $form,
            ]);
        } else {

            // allow GET query for domstift
            $get_param_domstift = $request->query->get('domstift');
            if (!$form->isSubmitted() && !is_null($get_param_domstift)) {
                $model->domstift = $get_param_domstift;
                $form->get('domstift')->setData($get_param_domstift);
            }

            $id_all = $repository->canonIds($model);

            $count = count($id_all);

            $offset = $request->request->get('offset');
            $page_number = $request->request->get('pageNumber');

            // set offset to page begin
            if (!is_null($offset)) {
                $offset = intdiv($offset, self::PAGE_SIZE) * self::PAGE_SIZE;
            } elseif (!is_null($page_number) && $page_number > 0) {
                $page_number = min($page_number, intdiv($count, self::PAGE_SIZE) + 1);
                $offset = ($page_number - 1) * self::PAGE_SIZE;
            } else {
                $offset = 0;
            }

            $id_list = array_slice($id_all, $offset, self::PAGE_SIZE);


            $canonLookupRepository = $em->getRepository(CanonLookup::class);
            $canon_list = $canonLookupRepository->findList($id_list, 1);

            return $this->renderForm('canon/query_result.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'count' => $count,
                'canon' => $canon_list,
                'offset' => $offset,
                'pageSize' => self::PAGE_SIZE,
            ]);
        }
    }


    /**
     * display details for a canon
     *
     * @Route("/domherr/listenelement", name="canon_list_detail")
     */
    public function canonListDetail(Request $request,
                                    EntityManagerInterface $entityManager,
                                    UtilService $utilService) {
        $model = new CanonFormModel;

        $form = $this->createForm(CanonFormType::class, $model);
        $form->handleRequest($request);

        $offset = $request->request->get('offset');

        $model = $form->getData();

        $repository = $entityManager->getRepository(CanonLookup::class);
        $hassuccessor = false;
        $idx = 0;
        if($offset == 0) {
            $ids = $repository->canonIds($model,
                                           2,
                                           $offset);
            if(count($ids) == 2) $hassuccessor = true;

        } else {
            $ids = $repository->canonIds($model,
                                         3,
                                         $offset - 1);
            if(count($ids) == 3) $hassuccessor = true;
            $idx += 1;
        }

        $person_id = $ids[$idx];

        $canonLookupRepository = $entityManager->getRepository(CanonLookup::class);
        $canon_list = $canonLookupRepository->findList([$person_id], null);
        // dd($ids, $person_id, $canon_list);

        // extract Person object to be compatible with bishops
        $personName = $canon_list[0]->getPersonName();
        $canon_list = $utilService->sortByFieldList($canon_list, ['prioRole']);
        $personRole = array_map(function($el) {
            return $el->getPerson();
        }, $canon_list);


        // $itemReferenceRepository = $entityManager->getRepository(ItemReference::class);
        // $itemReferenceRepository->setReferenceVolume($personRole);

        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class);
        $urlByType = $urlExternalRepository->groupByType($personName->getId());
        $personName->setUrlByType($urlByType);

        // * version before 2022-07-18
        // // get person (name, date of birth ...)
        // $personRepository = $entityManager->getRepository(Person::class);
        // $person = $personRepository->find($person_id);

        // // collect external URLs
        // $urlExternalRepository = $dcn->getRepository(UrlExternal::class);
        // $urlByType = $urlExternalRepository->groupByType($person_id);
        // $person->setUrlByType($urlByType);

        // // collect office data in an array of Items
        // $item = $service->getCanonOfficeData($person);

        // // collect comments, name variants from other sources
        // $sibling = $service->getSibling($person);
        // $person->setSibling($sibling);

        return $this->render('canon/person.html.twig', [
            'form' => $form->createView(),
            'personName' => $personName,
            'personRole' => $personRole,
            'offset' => $offset,
            'hassuccessor' => $hassuccessor,
        ]);


    }

    /**
     * return canon data
     *
     * @Route("/domherr/data", name="canon_query_data")
     */
    public function queryData(Request $request,
                              EntityManagerInterface $entityManager,
                              PersonService $personService) {

        if ($request->isMethod('POST')) {
            $model = CanonFormModel::newByArray($request->request->get('canon_form'));
            $format = $request->request->get('format');

        } else {
            $model = CanonFormModel::newByArray($request->query->all());
            $format = $request->query->get('format') ?? 'json';
        }


        $format = ucfirst(strtolower($format));
        if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
            throw $this->createNotFoundException('Unbekanntes Format: '.$format);
        }

        $canonLookupRepository = $entityManager->getRepository(CanonLookup::class);
        $itemReferenceRepository = $entityManager->getRepository(ItemReference::class);

        $id_all = $canonLookupRepository->canonIds($model);


        $chunk_offset = 0;
        $limit = 50;
        // split up in chunks
        $id_list = array_slice($id_all, $chunk_offset, $limit);
        $node_list = array();
        while (count($id_list) > 0) {

            $canon_list = $canonLookupRepository->findList($id_list, null);

            $canon_personName = array_filter($canon_list, function($el) {
                return $el->getPrioRole() == 1;
            });

            // find all sources (persons with roles) for the elements of $canon_personName
            foreach($canon_personName as $personName) {
                // get roles
                $personIdName = $personName->getPersonIdName();
                $canon_personRole = array_filter($canon_list, function($el) use ($personIdName) {
                    return $el->getPersonIdName() == $personIdName;
                });
                $personRole = array();
                foreach($canon_personRole as $canon) {
                    $personRole[] = $canon->getPerson();
                }

                $node_canon = $personService->personData($format, $personName->getPersonName(), $personRole);
                $node_list[] = $node_canon;

            }
            $chunk_offset += $limit;
            $id_list = array_slice($id_all, $chunk_offset, $limit);
        }

        // return $this->render("base.html.twig"); # debug; check performance

        return $personService->createResponse($format, $node_list);

    }

    /**
     * show selected canons (e.g. by domstift) in one page
     * @Route("/domherr/onepage", name="canon_onepage")
     */
    public function onepage(Request $request,
                            EntityManagerInterface $entityManager,
                            UtilService $utilService) {

        $model = new CanonFormModel();
        $form = $this->createForm(CanonFormType::class, $model);
        $form->handleRequest($request);

        $canonLookupRepository = $entityManager->getRepository(CanonLookup::class);
        $itemRepository = $entityManager->getRepository(Item::class);

        $id_all = $canonLookupRepository->canonIds($model);

        // set global limit here (avoid server crash!)
        $global_limit = 5000;
        $id_all = array_slice($id_all, 0, $global_limit);

        // set sorting parameters
        $domstift = $model->domstift;

        $chunk_offset = 0;
        $limit = 30;
        // split up in chunks
        $id_list = array_slice($id_all, $chunk_offset, $limit);
        $canon_node_list = array();

        while (count($id_list) > 0) {
            $canon_list = $canonLookupRepository->findList($id_list, null);

            $canon_personName = array_filter($canon_list, function($el) {
                return $el->getPrioRole() == 1;
            });

            // find all sources (persons with roles) for the elements of $canon_personName
            foreach($canon_personName as $personName) {
                $node['personName'] = $personName;
                /* get roles
                 */
                $personIdName = $personName->getPersonIdName();
                $canon_personRole = array_filter($canon_list, function($el) use ($personIdName) {
                    return $el->getPersonIdName() == $personIdName;
                });
                // sort by prioRole
                $canon_personRole = $utilService->sortByFieldList($canon_personRole, ['prioRole']);

                // extract object of type person and sort roles
                $personRole = array();
                foreach($canon_personRole as $canon) {
                    $person = $canon->getPerson();
                    $role_list = $person->getRole()->toArray();
                    $role_list = $utilService->sortByFieldList($role_list, ['placeName', 'dateSortKey', 'id']);
                    if ($domstift) {
                        $role_list = $utilService->sortByDomstift($role_list, $domstift);
                    }

                    $person->setRole($role_list);
                    $personRole[] = $canon->getPerson();
                }

                $node['personRole'] = $personRole;
                $canon_node_list[] = $node;
            }
            $chunk_offset += $limit;
            $id_list = array_slice($id_all, $chunk_offset, $limit);
        }

        // dump($canon_list);

        // $query_result = $canonLookupRepository->findWithOfficesByModel($model);
        // // dd($query_result);

        // $referenceVolumeRepository = $entityManager->getRepository(ReferenceVolume::class);
        // $canon_list = array();
        // $canon = array();
        // $item_list = array();
        // foreach($query_result as $obj) {
        //     if (is_a($obj, Person::class)) {
        //         if (count($canon) > 0) {
        //             $canon['item'] = $item_list;
        //             $canon_list[] = $canon;
        //             $item_list = array();
        //             $canon = array();
        //             }
        //             $canon['person'] = $obj;
        //     } elseif (is_a($obj, Item::class)) {
        //         $referenceVolumeRepository->addReferenceVolumes($obj);
        //         $item_list[] = $obj;
        //     } else {}
        // }

        $title = $model->domstift;
        if ($title) {
            $part_list = explode(" ", $title);
            if (count($part_list) == 1) {
                $title = 'Domstift '.$title;
            }
            $title = ucwords($title);
        } else {
            $title = "Domherren";
        }

        return $this->render('canon/onepage_result.html.twig', [
            'title' => $title,
            'canon_node_list' => $canon_node_list,
        ]);

    }

    /**
     * show selected canons (e.g. by domstift) in one page
     * @Route("/domherr/onepage_legacy", name="canon_onepage_legacy")
     */
    public function onepage_legacy (Request $request,
                                    EntityManagerInterface $entityManager) {

        $model = new CanonFormModel();
        $form = $this->createForm(CanonFormType::class, $model);
        $form->handleRequest($request);

        $canonLookupRepository = $entityManager->getRepository(CanonLookup::class);

        $query_result = $canonLookupRepository->findWithOfficesByModel($model);
        // dd($query_result);

        $referenceVolumeRepository = $entityManager->getRepository(ReferenceVolume::class);
        $canon_list = array();
        $canon = array();
        $item_list = array();
        foreach($query_result as $obj) {
            if (is_a($obj, Person::class)) {
                if (count($canon) > 0) {
                    $canon['item'] = $item_list;
                    $canon_list[] = $canon;
                    $item_list = array();
                    $canon = array();
                    }
                    $canon['person'] = $obj;
            } elseif (is_a($obj, Item::class)) {
                $referenceVolumeRepository->addReferenceVolumes($obj);
                $item_list[] = $obj;
            } else {}
        }

        $title = $model->domstift;
        if ($title) {
            $part_list = explode(" ", $title);
            if (count($part_list) == 1) {
                $title = 'Domstift '.$title;
            }
            $title = ucwords($title);
        } else {
            $title = "Domherren";
        }

        return $this->render('canon/onepage_result.html.twig', [
            'title' => $title,
            'canon_list' => $canon_list,
        ]);

    }

     /**
     * show references for selected canons
     * @Route("/domherr/onepage/literatur/{itemType}", name="canon_onepage_references")
     */
    public function references(Request $request,
                               EntityManagerInterface $entityManager,
                               $itemType) {

        // itemType:
        // 6 references GS
        // 5 other references

        $model = new CanonFormModel();
        $form = $this->createForm(CanonFormType::class, $model);
        $form->handleRequest($request);

        $canonLookupRepository = $entityManager->getRepository(CanonLookup::class);

        $reference_list = $canonLookupRepository->findReferencesByModel($model, $itemType);
        // dd($reference_list);
        $title = $itemType == 6 ? 'Literatur Germania Sacra' : 'Literatur andere';

        return $this->render('canon/onepage_references.html.twig', [
            'title' => $title,
            'reference_list' => $reference_list,
        ]);
    }


    /**
     * AJAX
     *
     * @Route("/canon-suggest/{field}", name="canon_suggest")
     */
    public function autocomplete(Request $request,
                                 EntityManagerInterface $entityManager,
                                 String $field) {
        $name = $request->query->get('q');
        $fnName = 'suggestCanon'.ucfirst($field);
        $canonLookupRepository = $entityManager->getRepository(CanonLookup::class);
        $suggestions = $canonLookupRepository->$fnName($name, self::HINT_SIZE);

        return $this->render('canon/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

}
