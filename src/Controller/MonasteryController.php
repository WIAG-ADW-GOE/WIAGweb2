<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemCorpus;
use App\Entity\Institution;
use App\Service\MonasteryService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Config\Definition\Exception\Exception;

use Doctrine\ORM\EntityManagerInterface;


class MonasteryController extends AbstractController {
    // route '/edit' is globally protected (see security.yaml)
    /**
     * update monastery data
     *
     * display page with update options
     */
    #[Route(path: '/edit/monastery/update', name: 'edit_monastery_update')]
    public function update(Request $request,
                           EntityManagerInterface $entityManager,
                           MonasteryService $service) {


        return $this->renderForm('monastery/update.html.twig', [
            'menuItem' => 'edit-menu',
        ]);
    }

    /**
     * update monastery data
     */
    #[Route(path: '/edit/monastery/update-step', name: 'edit_monastery_update_step')]
    function updateStep(Request $request,
                        MonasteryService $service,
                        EntityManagerInterface $entityManager) {

        $debug_flag = false;
        $chunk_size = $request->query->get('chunkSize');
        $offset = $request->query->get('offset');

        $list = [];
        $set_size = 0;
        $count = 0;
        $status = 200;
        try {
            $set_size_n_list = $service->queryGSList($chunk_size, $offset);
            $list = $set_size_n_list["list"];
            $set_size = $set_size_n_list["set_size"];
        } catch (Exception $e) {
            $status = $e->getMessage();
        }

        // dd($status, $list);
        $updated_n = 0;
        $created_n = 0;

        if ($status == 200) {

            foreach ($list as $gsn) {
                $repository = $entityManager->getRepository(Institution::class);
                $monastery_q_list = $repository->findByIdGsn($gsn);

                if (!is_null($monastery_q_list) && count($monastery_q_list) > 0) {
                    $monastery = array_values($monastery_q_list)[0];
                    $service->update($monastery);
                    $updated_n += 1;
                } else {
                    $user_id = intval($this->getUser()->getId());
                    $monastery_new = new Institution($user_id);
                    $monastery_new->setIdGsn($gsn);
                    // $monastery_new->setCorpusId(Institution::CORPUS_ID); 2023-11-16
                    $service->update($monastery_new);
                    $entityManager->persist($monastery_new->getItem());
                    $entityManager->persist($monastery_new);

                    $item_corpus = new ItemCorpus();
                    $item_corpus->setItem($monastery_new->getItem());
                    $item_corpus->setCorpusId(Institution::CORPUS_ID);
                    $item_corpus->setIdInCorpus($gsn);
                    $entityManager->persist($item_corpus);

                    $created_n += 1;
                }
            }
            $entityManager->flush();

            $count = count($list);
            // done ?
            if ($count < $chunk_size) {
                $status = 240;
            }

            //debug
            // $max_offset = 500;
            // if ($offset > $max_offset) {
            //     $status = 240;
            // }
        }


        if ($debug_flag) {
            // access dump data
            $template = 'monastery/debug_status.html.twig';
        } else {
            $template = 'monastery/_status.html.twig';
        }

        $response = $this->render($template, [
            'menuItem' => 'edit-menu',
            'status' => $status,
            'count' => $count,
            'totalCount' => $offset + $updated_n + $created_n,
            'maxCount' => $set_size,
            'createdCount' => $created_n,
        ]);

        $response->setStatusCode($status);

        return $response;
    }


}
