<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Person;
use App\Entity\PersonRole;

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

    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/person-suggest/{itemTypeId}/{field}", name="person_suggest")
     */
    public function autocomplete(Request $request,
                                 EntityManagerInterface $entityManager,
                                 String $field,
                                 String $itemTypeId) {
        $repository = $entityManager->getRepository(Person::class);
        $name = $request->query->get('q');
        $fnName = 'suggest'.ucfirst($field); // e.g. suggestInstitution
        if ($field == "institution") {
            $itemTypeId = explode("-", $itemTypeId);
        }
        $suggestions = $repository->$fnName($itemTypeId, $name, self::HINT_SIZE);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }


}
