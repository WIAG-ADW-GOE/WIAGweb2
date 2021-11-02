<?php
namespace App\Controller;

use App\Entity\Item;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class ReferenceController extends AbstractController {

    /**
     * display a list of references
     *
     * @Route("/referenz/liste/{itemTypeId}", name="reference_list")
     */
    public function list(int $itemTypeId, Request $request) {

        $repository = $this->getDoctrine()
                           ->getRepository(Item::class);

        $result = $repository->referenceByItemType($itemTypeId);

        return $this->renderForm('reference/list.html.twig', [
            'references' => $result,
        ]);
    }

    /**
     * display reference details
     *
     * @Route("/referenz/{id}", name="reference")
     */
    public function referenceDetail (int $id, Request $request) {

        $repository = $this->getDoctrine()
                           ->getRepository(ReferenceVolumne::class);

        $result = $repository->find($id);

        return $this->renderForm('reference/reference.html.twig', [
            'reference' => $result,
        ]);
    }


}
