<?php
namespace App\Controller;

use App\Entity\Diocese;
use App\Form\DioceseFormType;
use App\Repository\DioceseRepository;
use App\Service\DioceseService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use Doctrine\ORM\EntityManagerInterface;

class DioceseController extends AbstractController {
    /** number of items per page */
    const PAGE_SIZE = 20;
    /** number of suggestions in autocomplete list */
    const SUGGEST_SIZE = 8;


    #[Route(path: '/bistuemer')]
    #[Route(path: '/api/bistuemer')]
    #[Route(path: '/query/dioc', name: 'diocese_query')]
    public function diocese(Request $request, DioceseRepository $repository) {

        $name = $request->query->get('name');
        $form = $this->createForm(DioceseFormType::class, [
            'name' => $name,
        ]);

        if ($request->isMethod('GET')) {
            $offset = $request->query->get('offset');
            $page_number = $request->query->get('pageNumber');
        } else {
            $form->handleRequest($request);
            $data = $form->getData();
            $name = $data['name'];

            $offset = $request->request->get('offset') ?? 0;
            $offset = floor($offset / self::PAGE_SIZE) * self::PAGE_SIZE;

        }

        // show all dioceses as a default
        if($form->isSubmitted() && !$form->isValid()) {
            $name = null;
        }

        $count = $repository->countByName($name);
        $result = $repository->dioceseWithBishopricSeat($name, self::PAGE_SIZE, $offset);

        return $this->render('diocese/query_result.html.twig', [
            'menuItem' => 'collections',
            'form' => $form->createView(),
            'count' => $count,
            'offset' => $offset,
            'data' => $result,
            'pageSize' => self::PAGE_SIZE,
        ]);
    }


    /**
     * display details
     */
    #[Route(path: '/listelement/dioc', name: 'diocese_list_detail')]
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
            $result = $repository->dioceseWithBishopricSeat($name, 2, $offset);
            if(count($result) == 2) $hassuccessor = true;
        } else {
            $result = $repository->dioceseWithBishopricSeat($name, 3, $offset - 1);
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
     */
    #[Route(path: '/bistum/data')]
    #[Route(path: '/data/dioc', name: 'diocese_query_data')]
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
        $diocese_list = $repository->dioceseWithBishopricSeat($name);


        $format = ucfirst(strtolower($format));
        if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
            throw $this->createNotFoundException('Unbekanntes Format: '.$format);
        }

        $node_list = [];
        foreach ($diocese_list as $diocese) {
            $node_list[] = $service->dioceseData($format, $diocese);
        }

        return $service->createResponse($format, $node_list);

    }


    /**
     * respond to asynchronous JavaScript request
     */
    #[Route(path: '/diocese-suggest/{field}', name: 'diocese_suggest')]
    public function autocomplete(Request $request,
                                 EntityManagerInterface $entityManager,
                                 String $field): Response {
        $query_param = $request->query->get('q');
        $altes_reich_flag = $request->query->get('altes-reich');
        $fn_name = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $dioceseRepository = $entityManager->getRepository(Diocese::class);
        $suggestions = $dioceseRepository->$fn_name($query_param, self::SUGGEST_SIZE, $altes_reich_flag);

        return $this->render('_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

}
