<?php
namespace App\Controller;

use App\Entity\Person;
use App\Repository\PersonRepository;

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

        $form = $this->createFormBuilder()
                     ->add('name', TextType::class, [
                         'label' => 'Name',
                     ])
                     ->add('diocese', TextType::class, [
                         'label' => 'Bistum',
                     ])
                     ->getForm();
        $data = null;

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            # dd($data);

            return $this->renderForm('bishop/query.html.twig', [
                'form' => $form,
                'data' => $data,
            ]);

        }

        return $this->renderForm('bishop/query.html.twig', [
                'form' => $form,
                'data' => $data,
        ]);


    }


}
