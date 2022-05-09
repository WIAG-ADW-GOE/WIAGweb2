<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Person;
use App\Entity\Canon;
use App\Entity\CanonLookup;
use App\Entity\UrlExternal;
use App\Entity\PersonRole;
use App\Entity\Role;
use App\Repository\PersonRepository;
use App\Repository\ItemRepository;
use App\Repository\CanonLookupRepository;
use App\Service\ItemService;
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
                          CanonLookupRepository $repository) {

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
            $offset = $request->request->get('offset') ?? 0;
            // set offset to page begin
            $offset = (int) floor($offset / self::PAGE_SIZE) * self::PAGE_SIZE;

            $idAll = $repository->canonIds($model);
            $count = count($idAll);

            $id = array_slice($idAll, $offset, self::PAGE_SIZE);

            $personRoleRepository = $this->getDoctrine()->getRepository(PersonRole::class);
            $canon = array();
            // easy way to keep the order of the entries
            foreach($id as $idLoop) {
                $canon_loop = $repository->findPrioRoleOne($idLoop["personIdName"]);
                $personRole = $personRoleRepository->findRoleWithPlace($canon_loop->getPersonIdRole());
                $canon_loop->setRoleListView($personRole);
                $canon[] = $canon_loop;
            }

            return $this->renderForm('canon/query_result.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'count' => $count,
                'canon' => $canon,
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
                                    CanonLookupRepository $repository,
                                    ItemService $service) {
        $model = new CanonFormModel;

        $form = $this->createForm(CanonFormType::class, $model);
        $form->handleRequest($request);

        $offset = $request->request->get('offset');

        $model = $form->getData();

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

        $dcn = $this->getDoctrine();

        // get person (name, date of birth ...)
        $personRepository = $dcn->getRepository(Person::class);
        $person_id = $ids[$idx]['personIdName'];
        $person = $personRepository->find($person_id);

        // collect external URLs
        $urlExternalRepository = $dcn->getRepository(UrlExternal::class);
        $urlByType = $urlExternalRepository->groupByType($person_id);
        $person->setUrlByType($urlByType);

        // collect office data in an array of Items
        $item = $service->getCanonOfficeData($person);

        return $this->render('canon/person.html.twig', [
            'form' => $form->createView(),
            'person' => $person,
            'item' => $item,
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
                              CanonLookupRepository $repository,
                              ItemService $itemService,
                              PersonRepository $personRepository,
                              PersonService $personService) {

        if ($request->isMethod('POST')) {
            $model = new CanonFormModel();
            $form = $this->createForm(CanonFormType::class, $model);
            $form->handleRequest($request);
            $model = $form->getData();
            $format = $request->request->get('format');
        } else {
            $model = CanonFormModel::newByArray($request->query->all());
            $format = $request->query->get('format') ?? 'json';
        }


        $ids = $repository->canonIds($model);

        $node_list = array();
        foreach ($ids as $id) {

            $person = $personRepository->find($id['personIdName']);

            // collect office data in an array of Items
            $item_list = $itemService->getCanonOfficeData($person);
            $node_list[] = $personService->personData($person, $item_list);
        }

        $format = ucfirst(strtolower($format));
        if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
            throw $this->createNotFoundException('Unbekanntes Format: '.$format);
        }
        $fncResponse = 'createResponse'.$format; # e.g. 'createResponseRdf'
        return $personService->$fncResponse($node_list);

    }

    /**
     * AJAX
     *
     * @Route("/canon-suggest/{field}", name="canon_suggest")
     */
    public function autocomplete(Request $request,
                                 CanonLookupRepository $repository,
                                 String $field) {
        $name = $request->query->get('q');
        $fnName = 'suggestCanon'.ucfirst($field);
        $suggestions = $repository->$fnName($name, self::HINT_SIZE);

        return $this->render('canon/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

}
