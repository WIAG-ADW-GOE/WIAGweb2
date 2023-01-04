<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Authority;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Doctrine\ORM\EntityManagerInterface;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;


class AuthorityController extends AbstractController {
    const HINT_SIZE = 12;

    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/authority-suggest/{field}", name="authority_suggest")
     */
    public function autocomplete(Request $request,
                                 EntityManagerInterface $entityManager,
                                 String $field) {
        $repository = $entityManager->getRepository(Authority::class);
        $name = $request->query->get('q');
        $fnName = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $suggestions = $repository->$fnName($name, self::HINT_SIZE);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }


}
