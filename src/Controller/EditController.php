<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EditController extends AbstractController
{
    /**
     * @Route("/edit", name="edit")
     */
    public function index(): Response
    {

        return $this->render('edit/index.html.twig', [
            'controller_name' => 'EditController',
        ]);
    }
}
