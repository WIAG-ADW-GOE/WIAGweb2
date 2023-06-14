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
use App\Form\Model\PersonFormModel;

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

        // we need to pass an instance of PersonFormModel, because facets depend on it's data
        $model = new PersonFormModel;
        $model->itemTypeId = Item::ITEM_TYPE_ID['Domherr']['id'];

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
            $offset = UtilService::offset($offset, $page_number, $count, self::PAGE_SIZE);

            $id_list = array_slice($id_all, $offset, self::PAGE_SIZE);

            $canonLookupRepository = $em->getRepository(CanonLookup::class);
            $prio_role = 1;
            $canon_list = $canonLookupRepository->findList($id_list, $prio_role);
            // set siblings for bishops
            $bishop_list = array();
            foreach($canon_list as $canon) {
                $personName = $canon->getPersonName();
                if ($personName->getItem()->getSource() == 'Bischof') {
                    $bishop_list[] = $personName;
                }
            }
            $personRepository = $em->getRepository(Person::class);
            $personRepository->setSibling($bishop_list);

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
        $model = new PersonFormModel;

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
        $personRepository = $entityManager->getRepository(Person::class);
        $canon_list = $canonLookupRepository->findList([$person_id], null);

        // extract Person object to be compatible with bishops
        $personName = $canon_list[0]->getPersonName();
        $personRepository->setSibling([$personName]);

        $canon_list = UtilService::sortByFieldList($canon_list, ['prioRole']);
        $personRole = array_map(function($el) {
            return $el->getPerson();
        }, $canon_list);
        $personUrl = null;
        foreach($personRole as $person) {
            if (count($person->getItem()->getUrlExternal()) > 0) {
                $personUrl = $person;
                break;
            }
        }

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
            $model = PersonFormModel::newByArray($request->request->get('canon_form'));
            $format = $request->request->get('format') ?? 'json';

        } else {
            $model = PersonFormModel::newByArray($request->query->all());
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
        $chunk_size = 50;
        // split up in chunks
        $id_list = array_slice($id_all, $chunk_offset, $chunk_size);
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
            $chunk_offset += $chunk_size;
            $id_list = array_slice($id_all, $chunk_offset, $chunk_size);
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

        $model = new PersonFormModel();
        $form = $this->createForm(CanonFormType::class, $model);
        $form->handleRequest($request);

        $canonLookupRepository = $entityManager->getRepository(CanonLookup::class);
        $itemRepository = $entityManager->getRepository(Item::class);

        $id_all = $canonLookupRepository->canonIds($model);

        // set global limit here (avoid server crash!)
        $global_limit = 5000;
        $id_all = array_slice($id_all, 0, $global_limit);

        // set sorting parameters
        $domstift = $model->institution;

        $chunk_offset = 0;
        // sort the list by office criteria (see below), therefore splitting up in chunks is not useful
        $limit = $global_limit; # chunk size
        // split up in chunks
        $id_list = array_slice($id_all, $chunk_offset, $limit);
        // element of $canon_node_list
        // array [
        //   "personName" => CanonLookup,
        //   "personRole" => array [
        //      0 => Person,
        //      ...
        //   ],
        // ]
        $canon_node_list = array();

        while (count($id_list) > 0) {
            $canon_list = $canonLookupRepository->findList($id_list, null);

            $canon_personName = array_filter($canon_list, function($el) {
                return $el->getPrioRole() == 1;
            });

            // find all sources (persons with roles) for the elements of $canon_personName
            foreach($canon_personName as $personName) {
                $node['personName'] = $personName;
                // get roles
                $personIdName = $personName->getPersonIdName();
                $canon_personRole = array_filter($canon_list, function($el) use ($personIdName) {
                    return $el->getPersonIdName() == $personIdName;
                });
                // sort by prioRole
                $canon_personRole = UtilService::sortByFieldList($canon_personRole, ['prioRole']);

                // extract object of type person and sort roles
                $personRole = array();
                foreach($canon_personRole as $canon) {
                    $person = $canon->getPerson();
                    if (is_array($person->getRole())) {
                        $role_list = $person->getRole();
                    } else {
                        $role_list = $person->getRole()->toArray();
                    }
                    $crit_list = ['placeName', 'dateSortKey', 'id'];
                    $role_list = UtilService::sortByFieldList($role_list, $crit_list );
                    if ($domstift) {
                        $crit_list = ['dateSortKey', 'placeName', 'dateSortKey', 'id'];
                        $role_list = UtilService::sortByDomstift($role_list, $domstift);
                     }


                    $person->setRole($role_list);
                    $personRole[] = $person;
                }

                $node['personRole'] = $personRole;
                $canon_node_list[] = $node;
            }

            $chunk_offset += $limit;
            $id_list = array_slice($id_all, $chunk_offset, $limit);
        }


        // sort elements of $canon_node_list by first relevant office, then by name
        if ($domstift) {
            uasort($canon_node_list, function($a, $b) {
                $a_key = PersonRole::MAX_DATE_SORT_KEY;
                if (count($a["personRole"]) > 0) {
                    $a_fpr = $a["personRole"][0];
                    $a_key = $a_fpr->getFirstRoleSortKey();
                }

                $b_key = PersonRole::MAX_DATE_SORT_KEY;
                if (count($b["personRole"]) > 0) {
                    $b_fpr = $b["personRole"][0];
                    $b_key = $b_fpr->getFirstRoleSortKey();
                }

                $result = $a_key < $b_key ? -1 : ($a_key > $b_key ? 1 : 0);

                // second criterion: name
                if ($result == 0) {
                    $a_name = $a["personName"]->getPerson()->getDisplayname();
                    $b_name = $b["personName"]->getPerson()->getDisplayname();

                    $result =  $a_name < $b_name ? -1 : ($a_name > $b_name ? 1 : 0);
                }

                // third criterion: id
                if ($result == 0) {
                    $a_id = $a["personName"]->getPerson()->getId();
                    $b_id = $b["personName"]->getPerson()->getId();

                    $result =  $a_id < $b_id ? -1 : ($a_id > $b_id ? 1 : 0);
                }

                return $result;
            });
        }

        $title = $model->institution;
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
            'canonNodeList' => $canon_node_list,
            'limitReached' => count($canon_node_list) >= $global_limit,
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

        $model = new PersonFormModel();
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
