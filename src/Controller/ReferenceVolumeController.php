<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ReferenceVolume;
use App\Entity\Corpus;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Doctrine\ORM\EntityManagerInterface;

class ReferenceVolumeController extends AbstractController {
    const HINT_SIZE = 12;

    /**
     * display the list of references
     */
    #[Route(path: '/referenz/liste', name: 'reference_list')]
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

        return $this->render('reference/list.html.twig', [
            'reference_list' => $result,
        ]);
    }

    /**
     * display reference details
     */
    #[Route(path: '/referenz/{id}', name: 'reference')]
    public function referenceDetail (int $id, Request $request, EntityManagerInterface $entityManager) {

        $repository = $entityManager->getRepository(ReferenceVolume::class);

        $result = $repository->find($id);

        return $this->render('reference/reference.html.twig', [
            'reference' => $result,
        ]);
    }


}
