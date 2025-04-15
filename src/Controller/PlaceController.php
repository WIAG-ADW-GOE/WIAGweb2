<?php
namespace App\Controller;

use App\Entity\Place;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Doctrine\ORM\EntityManagerInterface;

class PlaceController extends AbstractController {
    /** number of suggestions in autocomplete list */
    const SUGGEST_SIZE = 8;

    /**
     * respond to asynchronous JavaScript request
     */
    #[Route(path: '/place-suggest/{field}', name: 'place_suggest')]
    public function autocomplete(Request $request,
                                 EntityManagerInterface $entityManager,
                                 String $field): Response {
        $query_param = $request->query->get('q');
        $fn_name = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $dioceseRepository = $entityManager->getRepository(Place::class);
        $suggestions = $dioceseRepository->$fn_name($query_param, self::SUGGEST_SIZE);

        return $this->render('_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }


}
