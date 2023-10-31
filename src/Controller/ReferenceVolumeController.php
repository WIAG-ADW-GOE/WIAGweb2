<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ReferenceVolume;
use App\Entity\Corpus;

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
     * display the list of references
     *
     * @Route("/referenz/liste", name="reference_list")
     */
    public function list(Request $request,
                         EntityManagerInterface $entityManager) {
        $corpusRepository = $entityManager->getRepository(Corpus::class);
        $referenceVolumeRepository = $entityManager->getRepository(ReferenceVolume::class);

        $result = array();
        foreach (['dioc', 'epc', 'can', 'utp'] as $corpus_id) {
            // $ref_list = $referenceVolumeRepository->findByCorpusId($corpus_id);
            $q_corpus = $corpusRepository->findByCorpusId($corpus_id);
            $corpus = array_values($q_corpus)[0];
            $ref_list = $referenceVolumeRepository->findByCorpusId($corpus_id);
            $result[$corpus_id] = [
                'title' => $corpus->getComment(),
                'list' => $ref_list,
            ];
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
