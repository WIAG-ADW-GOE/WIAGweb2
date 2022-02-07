<?php
namespace App\Controller;

use App\Entity\Diocese;
use App\Form\DioceseFormType;
use App\Repository\DioceseRepository;
use App\Service\DioceseService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;


class DioceseController extends AbstractController {
    /** number of items per page */
    const PAGE_SIZE = 20;
    /** number of suggestions in autocomplete list */
    const HINT_SIZE = 8;


    /**
     *
     * @Route("/bistum", name="diocese_query")
     */
    public function diocese(Request $request, DioceseRepository $repository) {

        $form = $this->createForm(DioceseFormType::class);

        $form->handlerequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $name = $data['name'];

            $count = $repository->countByName($name);

            $offset = $request->request->get('offset') ?? 0;
            $offset = floor($offset / self::PAGE_SIZE) * self::PAGE_SIZE;

            $result = $repository->dioceseWithBishopricSeatByName($name, self::PAGE_SIZE, $offset);

            return $this->render('diocese/query_result.html.twig', [
                'menuItem' => 'collections',
                'form' => $form->createView(),
                'offset' => $offset,
                'data' => $result,
                'pageSize' => self::PAGE_SIZE,
            ]);
         }

        // show all dioceses as a default
        $count = $repository->countByName(null);
        $offset = 0;
        $result = $repository->dioceseWithBishopricSeatByName(null, self::PAGE_SIZE, $offset);

        return $this->render('diocese/query_result.html.twig', [
            'menuItem' => 'collections',
            'form' => $form->createView(),
            'offset' => $offset,
            'data' => $result,
            'pageSize' => self::PAGE_SIZE,
        ]);

    }

    /**
     * display details for a bishop
     *
     * @Route("/bistum/listenelement", name="diocese_list_detail")
     */
    public function dioceseListDetail(Request $request) {

        $form = $this->createForm(DioceseFormType::class);
        $form->handlerequest($request);

        $data = $form->getData();
        $name = $data['name'];

        $repository = $this->getDoctrine()
                           ->getRepository(Diocese::class);

        $offset = $request->request->get('offset');

        $hassuccessor = false;
        $idx = 0;
        if($offset == 0) {
            $result = $repository->dioceseWithBishopricSeatByName($name, 2, $offset);
            if(count($result) == 2) $hassuccessor = true;
        } else {
            $result = $repository->dioceseWithBishopricSeatByName($name, 3, $offset - 1);
            if(count($result) == 3) $hassuccessor = true;
            $idx += 1;
        }
        $diocese = $result[$idx];

        if (!$diocese) {
            throw $this->createNotFoundException("Bistum wurde nicht gefunden.");
        }

        return $this->render('diocese/diocese.html.twig', [
            'form' => $form->createView(),
            'diocese' => $diocese,
            'offset' => $offset,
            'hassuccessor' => $hassuccessor,
        ]);

    }

    /**
     * return diocese data
     *
     * @Route("/bistum/data", name="diocese_query_data")
     */
    public function queryData(Request $request,
                              DioceseRepository $repository,
                              DioceseService $service) {

        if ($request->isMethod('POST')) {
            $form = $this->createForm(DioceseFormType::class);
            $form->handleRequest($request);
            $data = $form->getData();
            $format = $request->request->get('format');
        } else {
            $data = $request->query->all();
            $format = $request->query->get('format') ?? 'json';
        }

        $name = $data['name'];
        $result = $repository->dioceseWithBishopricSeatByName($name);

        $format = ucfirst(strtolower($format));
        if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
            throw $this->createNotFoundException('Unbekanntes Format: '.$format);
        }
        $fncResponse='createResponse'.$format; # e.g. 'createResponseRdf'
        return $service->$fncResponse($result);

    }

    /**
     * AJAX
     *
     * @Route("/diocese_name", name="diocese_name")
     */
    public function dioceseName(Request $request) {
        $name = $request->query->get('q');
        $suggestions = $this->getDoctrine()
                            ->getRepository(Diocese::class)
                            ->suggestName($request->query->get('q'),
                                          self::HINT_SIZE);

        return $this->render('bishop/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }


}
