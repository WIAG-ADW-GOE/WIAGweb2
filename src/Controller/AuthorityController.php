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
     */
    #[Route(path: '/authority-suggest-core/{field}', name: 'authority_suggest_core')]
    public function autocompleteCore(Request $request,
                                 EntityManagerInterface $entityManager,
                                 String $field) {
        $repository = $entityManager->getRepository(Authority::class);
        $name = $request->query->get('q');
        $fnName = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $core_list = Authority::CORE_ID_LIST;

        // get suggestions without core authorities
        $suggestions = $repository->$fnName($name, self::HINT_SIZE, $core_list);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

    /**
     * respond to asynchronous JavaScript request
     */
    #[Route(path: '/authority-suggest/{field}', name: 'authority_suggest')]
    public function autocomplete(Request $request,
                                 EntityManagerInterface $entityManager,
                                 String $field) {
        $repository = $entityManager->getRepository(Authority::class);
        $name = $request->query->get('q');
        $fnName = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $core_list = null;

        // get suggestions without core authorities
        $suggestions = $repository->$fnName($name, self::HINT_SIZE, $core_list);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }



}
