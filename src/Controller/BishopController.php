<?php
namespace App\Controller;

use App\Entity\Person;
use App\Repository\PersonRepository;
use App\Form\BishopFormType;
use App\Form\Model\BishopFormModel;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;


class BishopController extends AbstractController {
    /** number of items per page */
    const PAGE_SIZE = 20;

    /**
     * display query form for bishops; handle query
     *
     * @Route("/bischof", name="bishop_query")
     */
    public function query(Request $request,
                          PersonRepository $repository) {

        // we need to pass an instance of BishopFormModel, because facets depend on it's data
        $bishopModel = new BishopFormModel;

        $form = $this->createForm(BishopFormType::class, $bishopModel);
        $offset = 0;


        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $bishopModel = $form->getData();

            $count = $repository->countByModel($bishopModel);
            dump($count);
            # $resultset = $repository->findByModel($bishopModel, self::PAGE_SIZE, $offset);

            return $this->renderForm('bishop/query.html.twig', [
                'form' => $form,
            ]);

        }

        return $this->renderForm('bishop/query.html.twig', [
                'form' => $form,
        ]);


    }

    /**
     * AJAX
     *
     * @Route("/bischof_name", name="bishop_name")     *
     */
    public function bishopName() {
        return ["name"];
    }

    /**
     * AJAX
     *
     * @Route("/bischof_diocese", name="bishop_diocese")     *
     */
    public function bishopDiocese() {
        return ["diocese"];
    }

    /**
     * AJAX
     *
     * @Route("/bischof_office", name="bishop_office")     *
     */
    public function bishopOffice() {
        return ["office"];
    }

}
