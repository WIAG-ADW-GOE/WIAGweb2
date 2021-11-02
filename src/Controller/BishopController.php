<?php
namespace App\Controller;

use App\Entity\Person;
use App\Repository\PersonRepository;
use App\Form\BishopFormType;
use App\Form\Model\BishopFormModel;
use App\Entity\Role;

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
                          PersonRepository $repository) {

        // we need to pass an instance of BishopFormModel, because facets depend on it's data
        $model = new BishopFormModel;

        $form = $this->createForm(BishopFormType::class, $model);
        $offset = 0;


        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $model = $form->getData();

            $count = $repository->bishopCountByModel($model);

            $offset = $request->request->get('offset');
            // set offset to page begin
            $offset = (int) floor($offset / self::PAGE_SIZE) * self::PAGE_SIZE;

            $result = $repository->bishopWithOfficeByModel($model, self::PAGE_SIZE, $offset);

            return $this->renderForm('bishop/query_result.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'data' => $result,
                'offset' => $offset,
                'pageSize' => self::PAGE_SIZE,
            ]);

        }

        return $this->renderForm('bishop/query.html.twig', [
            'menuItem' => 'collections',
            'form' => $form,
        ]);

    }

    /**
     * display details for a bishop
     *
     * @Route("/bischof/listenelement", name="bishop_list_detail")
     */
    public function bishopListDetail(Request $request) {
        $model = new BishopFormModel;

        $form = $this->createForm(BishopFormType::class, $model);
        $form->handleRequest($request);

        $offset = $request->request->get('offset');

        $model = $form->getData();

        $repository = $this->getDoctrine()
                           ->getRepository(Person::class);
        $hassuccessor = false;
        if($offset == 0) {
            $result = $repository->bishopWithOfficeByModel($model, 2, $offset);
            $iterator = $result->getIterator();
            if(count($iterator) == 2) $hassuccessor = true;

        } else {
            $result = $repository->bishopWithOfficeByModel($model, 3, $offset - 1);
            $iterator = $result->getIterator();
            if(count($iterator) == 3) $hassuccessor = true;
            $iterator->next();
        }
        $person = $iterator->current();

        // fetch data from domherren database or GS (Personendatenbank)
        // TODO check item_property
        // $cnonlineRepository = $this->getDoctrine()
        //                            ->getRepository(CnOnline::class);
        // $cnonline = $cnonlineRepository->findOneByIdEp($person->getWiagid());
        // $canon = null;
        // $canon_gs = null;
        // if (!is_null($cnonline)) {
        //     $cnonlineRepository->fillData($cnonline);
        //     $canon = $cnonline->getCanonDh();
        //     $canon_gs = $cnonline->getCanonGs();
        // }

        // $canon_merged = array();
        // if (!is_null($canon)) {
        //     $cycle = 1;
        //     $canon_merged = $this->getDoctrine()
        //                          ->getRepository(Canon::class)
        //                          ->collectMerged($canon_merged, $canon, $cycle);
        //     array_unshift($canon_merged, $canon);
        // }

        return $this->render('bishop/person.html.twig', [
            'form' => $form->createView(),
            'person' => $person,
            'offset' => $offset,
            'hassuccessor' => $hassuccessor,
        ]);


    }


    /**
     * AJAX
     *
     * @Route("/bischof_name", name="bishop_name")
     */
    public function bishopName(Request $request) {
        $name = $request->query->get('q');
        $suggestions = $this->getDoctrine()
                            ->getRepository(Person::class)
                            ->suggestName($request->query->get('q'),
                                          self::HINT_SIZE);

        return $this->render('bishop/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);

    }

    /**
     * AJAX
     *
     * @Route("/bischof_diocese", name="bishop_diocese")
     */
    public function bishopDiocese(Request $request) {
        $name = $request->query->get('q');
        $suggestions = $this->getDoctrine()
                            ->getRepository(Person::class)
                            ->suggestDiocese($request->query->get('q'),
                                             self::HINT_SIZE);

        return $this->render('bishop/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);

    }

    /**
     * AJAX
     *
     * @Route("/bischof_office", name="bishop_office")
     */
    public function bishopOffice(Request $request) {
        $name = $request->query->get('q');
        $suggestions = $this->getDoctrine()
                            ->getRepository(Person::class)
                            ->suggestOffice($request->query->get('q'),
                                            self::HINT_SIZE);

        return $this->render('bishop/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);

    }

    /**
     * Test
     * @Route("/bischof_aemter/{name}", name="bishop_person_roles")
     */
    public function bishopPersonRoles($name) {
        $repository = $this->getDoctrine()
                           ->getRepository(Person::class);

        $persons = $repository->findByRole($name);
        dd($persons);

        return new Response("bishopPersonRoles");
    }


}
