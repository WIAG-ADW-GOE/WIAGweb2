<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Person;
use App\Entity\Authority;
use App\Repository\PersonRepository;
use App\Repository\ItemRepository;
use App\Form\PriestUtFormType;
use App\Form\Model\PriestUtFormModel;
use App\Entity\Role;

use App\Service\PersonService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;


class PriestUtController extends AbstractController {
    /** number of items per page */
    const PAGE_SIZE = 20;
    /** number of suggestions in autocomplete list */
    const HINT_SIZE = 8;

    /**
     * display query form for priestUts; handle query
     *
     * @Route("/priest_utrecht", name="priest_ut_query")
     */
    public function query(Request $request,
                          ItemRepository $repository) {

        $personRepository = $this->getDoctrine()->getRepository(Person::class);

        // we need to pass an instance of PriestUtFormModel, because facets depend on it's data
        $model = new PriestUtFormModel;

        // TODO $flagInit = count($request->request->all()) == 0;
        $flagInit = false;

        $form = $this->createForm(PriestUtFormType::class, $model, [
            'forceFacets' => $flagInit,
        ]);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->renderForm('priest_ut/query.html.twig', [
                    'menuItem' => 'collections',
                    'form' => $form,
            ]);
        } else {
            $offset = $request->request->get('offset') ?? 0;
            // set offset to page begin
            $offset = (int) floor($offset / self::PAGE_SIZE) * self::PAGE_SIZE;

            $countResult = $repository->countPriestUt($model);
            $count = $countResult["n"];

            $ids = $repository->priestUtIds($model,
                                            self::PAGE_SIZE,
                                            $offset);

            # easy way to get all persons in the right order
            $cPerson = array();
            foreach($ids as $id) {
                $cPerson[] = $personRepository->findWithOffice($id["personId"]);
            }

            return $this->renderForm('priest_ut/query_result.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'count' => $count,
                'cperson' => $cPerson,
                'offset' => $offset,
                'pageSize' => self::PAGE_SIZE,
            ]);
        }
    }

    /**
     * display details for a priestUt
     *
     * @Route("/priest_utrecht/listenelement", name="priest_ut_list_detail")
     */
    public function priestUtListDetail(Request $request,
                                       ItemRepository $repository) {

        $model = new PriestUtFormModel;

        $form = $this->createForm(PriestUtFormType::class, $model);
        $form->handleRequest($request);

        $offset = $request->request->get('offset');

        $model = $form->getData();

        $hassuccessor = false;
        $idx = 0;
        if($offset == 0) {
            $ids = $repository->priestUtIds($model,
                                           2,
                                           $offset);
            if(count($ids) == 2) $hassuccessor = true;

        } else {
            $ids = $repository->priestUtIds($model,
                                           3,
                                           $offset - 1);
            if(count($ids) == 3) $hassuccessor = true;
            $idx += 1;
        }

        $personRepository = $this->getDoctrine()->getRepository(Person::class);
        $person = $personRepository->findWithAssociations($ids[$idx]['personId']);

        return $this->render('priest_ut/person.html.twig', [
            'form' => $form->createView(),
            'person' => $person,
            'offset' => $offset,
            'hassuccessor' => $hassuccessor,
        ]);


    }

    /**
     * return priestUt data
     *
     * @Route("/priest_utrecht/data", name="priest_ut_query_data")
     */
    public function queryData(Request $request,
                              PersonRepository $repository,
                              PersonService $service) {

        if ($request->isMethod('POST')) {
            $model = new PriestUtFormModel();
            $form = $this->createForm(PriestUtFormType::class, $model);
            $form->handleRequest($request);
            $model = $form->getData();
            $format = $request->request->get('format');
        } else {
            $model = PriestUtFormModel::newByArray($request->query->all());
            $format = $request->query->get('format') ?? 'json';
        }

        # TODO 2022-01-26 call $repository->priestUtIds
        $result = $repository->priestUtWithOfficeByModel($model);

        $format = ucfirst(strtolower($format));
        if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
            throw $this->createNotFoundException('Unbekanntes Format: '.$format);
        }
        $fncResponse = 'createResponse'.$format; # e.g. 'createResponseRdf'
        return $service->$fncResponse($result);

    }


    /**
     * AJAX
     *
     * @Route("/priest-utrecht-suggest/{field}", name="priest_ut_suggest")
     */
    public function autocomplete(Request $request,
                                 ItemRepository $repository,
                                 String $field) {
        $name = $request->query->get('q');
        $fnName = 'suggestPriestUt'.ucfirst($field);
        $suggestions = $repository->$fnName($name, self::HINT_SIZE);

        return $this->render('priest_ut/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

}
