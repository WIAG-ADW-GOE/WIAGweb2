<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Entity\Person;
use App\Entity\Diocese;

use App\Service\BishopService;
use App\Controller\BishopController;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class IdController extends AbstractController {
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

            return $this->$typeName($itemId, $format);

        } else {
            # TODO catch error
            return new Response("Id ist nicht gültig");
        }


     }

    public function bishop($id, $format) {
        $repository = $this->getDoctrine()
                           ->getRepository(Person::class);

        $result = $repository->find($id);

        switch($format) {
        case 'html':
            return $this->render('bishop/person.html.twig', [
                'person' => $result,
            ]);
        }

        # TODO
        # throw error unknown format
        return new Response("Unbekanntes Format: '".$format);
    }

    public function diocese($id, $format) {
        $repository = $this->getDoctrine()
                           ->getRepository(Diocese::class);

        $result = $repository->find($id);

        switch($format) {
        case 'html':
            return $this->render('diocese/diocese.html.twig', [
                'diocese' => $result,
            ]);
        }

        throw $this->createNotFoundException("Unbekanntes Format: '".$format);

    }


}
