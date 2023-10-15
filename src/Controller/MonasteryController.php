<?php
namespace App\Controller;

use App\Entity\Item;
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

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class MonasteryController extends AbstractController {
    // route '/edit' is globally protected (see security.yaml)


    /**
     * update monastery data
     *
     * display page with update options
     *
     * @Route("/edit/monastery/update", name="edit_monastery_update")
     */
    public function update(Request $request,
                           EntityManagerInterface $entityManager,
                           MonasteryService $service) {


        return $this->renderForm('monastery/update.html.twig');
    }

    /**
     * update monastery data
     *
     * @Route("/edit/monastery/update-step", name="edit_monastery_update_step")
     */
    function updateStep(Request $request,
                        MonasteryService $service,
                        EntityManagerInterface $entityManager) {
        $debug_flag = false;
        $chunk_size = $request->query->get('chunkSize');
        $offset = $request->query->get('offset');

        $list = [];
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

        if ($status == 200) {

            $updated_n = 0;
            $created_n = 0;
            foreach ($list as $gsn) {
                $repository = $entityManager->getRepository(Institution::class);
                $monastery_list = $repository->findByIdGsn($gsn);

                if (!is_null($monastery_list) && count($monastery_list) > 0) {
                    $service->update($monastery_list[0]);
                    $updated_n += 1;
                } else {
                    $user_id = intval($this->getUser()->getId());
                    $item_type_id = Item::ITEM_TYPE_ID['Kloster']['id'];
                    $monastery_new = new Institution($item_type_id, $user_id);
                    $monastery_new->setIdGsn($gsn);
                    $service->update($monastery_new);
                    $entityManager->persist($monastery_new->getItem());
                    $entityManager->persist($monastery_new);
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
