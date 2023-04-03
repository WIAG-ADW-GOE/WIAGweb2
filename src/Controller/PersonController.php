<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Service\AutocompleteService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Doctrine\ORM\EntityManagerInterface;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;


class PersonController extends AbstractController {
    const HINT_SIZE = 12;

    private $autocomplete = null;

    public function __construct(AutocompleteService $service) {
        $this->autocomplete = $service;
    }

    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/person-suggest/{itemTypeId}/{field}", name="person_suggest")
     * response depends on item type
     */
    public function autocomplete(Request $request,
                                 String $field,
                                 String $itemTypeId) {
        $query_param = $request->query->get('q');
        $fnName = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $suggestions = $this->autocomplete->$fnName($itemTypeId, $query_param, self::HINT_SIZE);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/person-suggest-online/{itemTypeId}/{field}", name="person_suggest_online")
     * filter by Item->isOnline
     */
    public function autocompleteOnline(Request $request,
                                       String $field,
                                       String $itemTypeId) {
        $query_param = $request->query->get('q');
        $fnName = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $online_only = true;
        $suggestions = $this->autocomplete->$fnName($itemTypeId, $query_param, self::HINT_SIZE, $online_only);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/person-suggest-base/{field}", name="person_suggest_base")
     * response is independent from item type
     */
    public function autocompleteBase(Request $request,
                                     String $field) {
        $query_param = $request->query->get('q');
        $fnName = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $suggestions = $this->autocomplete->$fnName($query_param, self::HINT_SIZE);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }

}
