<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Entity\Person;
use App\Entity\Diocese;

use App\Service\PersonService;
use App\Service\DioceseService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class IdController extends AbstractController {
    private $personServcice;
    private $dioceseServcice;

    public function __construct(PersonService $personService, DioceseService $dioceseService) {
        $this->personService = $personService;
        $this->dioceseService = $dioceseService;
    }

    /**
     * find item by ID; show details or deliver data as JSON, CSV or XML
     *
     * decide which format should be delivered
     *
     * @Route("/id/{id}", name="id")
     */
    public function id(string $id, Request $request) {

        $format = $request->request->get('format') ?? 'html';

        $itemRepository = $this->getDoctrine()
                               ->getRepository(Item::class);

        $itemTypeRepository = $this->getDoctrine()
                                   ->getRepository(ItemType::class);

        $itemResult = $itemRepository->findByIdPublic($id);

        if ($itemResult) {
            $item = $itemResult[0];
            $itemTypeId = $item->getItemTypeId();
            $itemId = $item->getId();

            $itemTypeResult = $itemTypeRepository->find($itemTypeId);

            $typeName = $itemTypeResult->getNameApp();

            # e.g. bishop($itemId, $format)
            return $this->$typeName($itemId, $format);

        } else {
            throw $this->createNotFoundException('Id is nicht gültig: '.$itemId);
        }


     }

    public function bishop($id, $format) {
        $repository = $this->getDoctrine()
                           ->getRepository(Person::class);

        $result = $repository->find($id);

        if ($format == 'html') {
            return $this->render('bishop/person.html.twig', [
                'person' => $result,
            ]);
        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }
            $fncResponse='createResponse'.$format; # e.g. 'createResponseRdf'
            return $this->personService->$fncResponse([$result]);
        }
    }

    public function diocese($id, $format) {
        $repository = $this->getDoctrine()
                           ->getRepository(Diocese::class);

        $result = $repository->find($id);

        if ($format == 'html') {
            return $this->render('diocese/diocese.html.twig', [
                'diocese' => $result,
            ]);
        } else {
            if (!in_array($format, ['Json', 'Csv', 'Rdf', 'Jsonld'])) {
                throw $this->createNotFoundException('Unbekanntes Format: '.$format);
            }
            $fncResponse='createResponse'.$format; # e.g. 'createResponseRdf'
            return $this->dioceseService->$fncResponse([$result]);
        }

        switch($format) {
        case 'html':
            return $this->render('diocese/diocese.html.twig', [
                'diocese' => $result,
            ]);
        }

    }


}
