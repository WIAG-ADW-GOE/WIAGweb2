<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;


class HomeController extends AbstractController {

    /**
     * display query form for bishops; handle query
     *
     * @Route("/", name="home")
     */
    public function home() {
        return $this->render('home/home.html.twig');
    }

    /**
     * @Route("/about/images", name="about_images")
     */
    public function images() {
        return $this->render('home/images.html.twig');
    }

    /**
     * @Route("/contact", name="contact")
     */
    public function contact() {
        return $this->render('home/contact.html.twig', [
            'menuItem' => 'about',
        ]);
    }



}
