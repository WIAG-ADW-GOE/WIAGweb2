<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ReferenceVolume;
use App\Repository\ReferenceVolumeRepository;
use App\Repository\ItemTypeRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Doctrine\ORM\EntityManagerInterface;

class ReferenceVolumeController extends AbstractController {
    const HINT_SIZE = 12;

    /**
     * display a list of references by item type
     * 2022-06-17 obsolete?
     *
     * @Route("/referenz/liste-type/{itemTypeId}", name="reference_list_type")
     */
    // public function listByType(int $itemTypeId, Request $request) {

    //     $repository = $this->getDoctrine()
    //                        ->getRepository(Item::class);

    //     $result = $repository->referenceByItemType($itemTypeId);

    //     return $this->renderForm('reference/list.html.twig', [
    //         'references' => $result,
    //     ]);
    // }

    /**
     * display the list of references
     *
     * @Route("/referenz/liste", name="reference_list")
     */
    public function list(Request $request,
                         ItemTypeRepository $itemTypeRepository,
                         ReferenceVolumeRepository $repository) {

        $type_list = $itemTypeRepository->findBy([], ['id' => 'ASC']);

        $result = array();
        foreach($type_list as $type) {
            $ref_list = $repository->findBy(
                ['itemTypeId' => $type->getId()],
                ['displayOrder' => 'ASC']
            );
            $result[$type->getName()] = $ref_list;
        }

        return $this->renderForm('reference/list.html.twig', [
            'reference_list' => $result,
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
