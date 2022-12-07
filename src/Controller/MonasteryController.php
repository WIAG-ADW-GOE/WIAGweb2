<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Institution;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Doctrine\ORM\EntityManagerInterface;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class MonasteryController extends AbstractController {

    /**
     * checkServer
     *
     * @Route("/monastery/check", name="monastery_check")
     */
    public function checkServer(Request $request,
                                EntityManagerInterface $entityManager,
                                RouterInterface $router) {

        $form = $this->createFormBuilder()
                     ->getForm();

        $test_result = null;
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ... perform some action, such as saving the task to the database

            $test_result = "Server antwortet.";
        }

        return $this->renderForm('monastery/update.html.twig', [
            'form' => $form,
            'testResult' => $test_result,
        ]);

    }

}
