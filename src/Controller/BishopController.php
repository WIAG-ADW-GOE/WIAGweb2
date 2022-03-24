<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Person;
use App\Entity\Authority;
use App\Repository\PersonRepository;
use App\Repository\ItemRepository;
use App\Form\BishopFormType;
use App\Form\Model\BishopFormModel;
use App\Entity\Role;

use App\Service\PersonService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;


class BishopController extends AbstractController {
    /** number of items per page */
    const PAGE_SIZE = 20;
    /** number of suggestions in autocomplete list */
    const HINT_SIZE = 8;

    /**
     * display query form for bishops; handle query
     *
     * @Route("/bischof", name="bishop_query")
     */
    public function query(Request $request,
                          ItemRepository $repository) {

        $personRepository = $this->getDoctrine()->getRepository(Person::class);

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
            $offset = $request->request->get('offset') ?? 0;
            // set offset to page begin
            $offset = (int) floor($offset / self::PAGE_SIZE) * self::PAGE_SIZE;

            $countResult = $repository->countBishop($model);
            $count = $countResult["n"];

            $ids = $repository->bishopIds($model,
                                          self::PAGE_SIZE,
                                          $offset);

            # easy way to get all persons in the right order
            $cPerson = array();
            foreach($ids as $id) {
                $cPerson[] = $personRepository->findWithOffice($id["personId"]);
            }

            return $this->renderForm('bishop/query_result.html.twig', [
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
     * display details for a bishop
     *
     * @Route("/bischof/listenelement", name="bishop_list_detail")
     */
    public function bishopListDetail(Request $request,
                                     ItemRepository $repository) {

        $model = new BishopFormModel;

        $form = $this->createForm(BishopFormType::class, $model);
        $form->handleRequest($request);

        $offset = $request->request->get('offset');

        $model = $form->getData();

        $hassuccessor = false;
        $idx = 0;
        if($offset == 0) {
            $ids = $repository->bishopIds($model,
                                           2,
                                           $offset);
            if(count($ids) == 2) $hassuccessor = true;

        } else {
            $ids = $repository->bishopIds($model,
                                           3,
                                           $offset - 1);
            if(count($ids) == 3) $hassuccessor = true;
            $idx += 1;
        }

        $personRepository = $this->getDoctrine()->getRepository(Person::class);
        $person = $personRepository->findWithAssociations($ids[$idx]['personId']);

        // get data from Germania Sacra
        $authorityGs = Authority::ID['Germania Sacra'];
        $gsn = $person->getIdExternal($authorityGs);
        $personGs = array();
        if (!is_null($gsn)) {
            $itemTypeBishopGs = Item::ITEM_TYPE_ID['Bischof GS'];
            $bishopGs = $personRepository->findByIdExternal($itemTypeBishopGs, $gsn, $authorityGs);
            $personGs = array_merge($personGs, $bishopGs);

            $itemTypeCanonGs = Item::ITEM_TYPE_ID['Domherr GS'];
            $canonGs = $personRepository->findByIdExternal($itemTypeCanonGs, $gsn, $authorityGs);
            $personGs = array_merge($personGs, $canonGs);
        }

        // get data from Domherrendatenbank
        $authorityWIAG = Authority::ID['WIAG-ID'];
        $wiagid = $person->getItem()->getIdPublic();
        $canon = array();
        if (!is_null($wiagid)) {
            $itemTypeCanon = Item::ITEM_TYPE_ID['Domherr'];
            $canon = $personRepository->findByIdExternal($itemTypeCanon, $wiagid, $authorityWIAG);
        }

        return $this->render('bishop/person.html.twig', [
            'form' => $form->createView(),
            'person' => $person,
            'canon' => $canon,
            'persongs' => $personGs,
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
                              PersonRepository $repository,
                              PersonService $service) {

        if ($request->isMethod('POST')) {
            $model = new BishopFormModel();
            $form = $this->createForm(BishopFormType::class, $model);
            $form->handleRequest($request);
            $model = $form->getData();
            $format = $request->request->get('format');
        } else {
            $model = BishopFormModel::newByArray($request->query->all());
            $format = $request->query->get('format') ?? 'json';
        }

        # TODO 2022-01-26 call $repository->bishopIds
        $result = $repository->bishopWithOfficeByModel($model);

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
     * @Route("/bischof-suggest/{field}", name="bishop_suggest")
     */
    public function autocomplete(Request $request,
                                 ItemRepository $repository,
                                 String $field) {
        $name = $request->query->get('q');
        $fnName = 'suggestBishop'.ucfirst($field);
        $suggestions = $repository->$fnName($name, self::HINT_SIZE);

        return $this->render('bishop/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

}
