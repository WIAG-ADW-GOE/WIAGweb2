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

    // approximate total number of entries in Klosterdatenbank
    const COUNT_MAX = 6000;


    /**
     * update monastery data
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
                        MonasteryService $service) {
        $debug_flag = true;
        $chunk_size = $request->query->get('chunkSize');
        $offset = $request->query->get('offset');

        $list = [];
        $count = 0;
        $status = 200;
        try {
            $list = $service->queryGSList($chunk_size, $offset);
        } catch (Exception $e) {
            $status = $e->getMessage();
        }

        // dd($status, $list);

        if ($status == 200) {

            $updated_n = 0;
            $created_n = 0;
            foreach ($list as $gsn) {
                $update_flag = $service->update($gsn);
                if ($update_flag) {
                    $updated_n += 1;
                } else {
                    // TODO new entry
                    // $service->create($gsn);
                    $created_n += 1;
                }
            }
            $this->getEntityManager()->flush();

            $count = count($list);
            if ($count < $chunk_size) {
                $status = 240;
            }
        }

        if ($debug_flag) {
            $response = $this->render('monastery/debug_status.html.twig', [
                'status' => $status,
                'count' => $count,
                'total' => $offset + $count,
                'countMax' => self::COUNT_MAX,
            ]);

            return $response;
        }


        $response = $this->render('monastery/_status.html.twig', [
            'status' => $status,
            'count' => $count,
            'total' => $offset + $count,
            'countMax' => self::COUNT_MAX,
        ]);



        $response->setStatusCode($status);

        return $response;
    }

}
