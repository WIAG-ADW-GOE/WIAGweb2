<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Person;
use App\Entity\Canon;
use App\Repository\PersonRepository;
use App\Repository\ItemRepository;
use App\Repository\CanonLookupRepository;
use App\Form\CanonFormType;
use App\Form\Model\CanonFormModel;
use App\Entity\Role;

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

        $form = $this->createForm(CanonFormType::class, $model);
        $offset = 0;


        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $model = $form->getData();

            $countResult = $repository->countCanon($model);
            $count = $countResult["n"];


            $offset = $request->request->get('offset');
            // set offset to page begin
            $offset = (int) floor($offset / self::PAGE_SIZE) * self::PAGE_SIZE;

            $ids = $repository->canonIds($model,
                                          self::PAGE_SIZE,
                                          $offset);

            $c_cnGroup = array();
            foreach($ids as $id) {
                $c_cnGroup[] = $repository->findRelatedCanon($id);
            }

            // find persons in the template to keep order
            $personRepository = $this->getDoctrine()->getRepository(Person::class);

            return $this->renderForm('canon/query_result.html.twig', [
                'menuItem' => 'collections',
                'repository' => $personRepository,
                'form' => $form,
                'count' => $count,
                'ccngroup' => $c_cnGroup,
                'offset' => $offset,
                'pageSize' => self::PAGE_SIZE,
            ]);

        }

        return $this->renderForm('canon/query.html.twig', [
            'menuItem' => 'collections',
            'form' => $form,
        ]);

    }

    /**
     * display details for a canon
     *
     * @Route("/domherr/listenelement", name="canon_list_detail")
     */
    public function canonListDetail(Request $request,
                                    CanonLookupRepository $repository) {
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

        $personRepository = $this->getDoctrine()->getRepository(Person::class);
        $person = $personRepository->findWithAssociations($ids[$idx]);

        return $this->render('canon/person.html.twig', [
            'form' => $form->createView(),
            'person' => $person,
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
                              PersonRepository $repository,
                              PersonService $service) {

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

        # TODO 2022-01-26 call $repository->canonIds
        $result = $repository->canonWithOfficeByModel($model);

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
