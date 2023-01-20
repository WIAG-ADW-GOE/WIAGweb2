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
     * @Route("/about", name="about")
     */
    public function about() {
        return $this->render('home/about.html.twig', ['menuItem' => 'about']);
    }

    /**
     * @Route("/about/images", name="about_images")
     */
    public function images() {
        return $this->render('home/images.html.twig', ['menuItem' => 'about']);
    }

    /**
     * @Route("/contact", name="contact")
     */
    public function contact() {
        return $this->render('home/contact.html.twig', [
            'menuItem' => 'about',
        ]);
    }

    /**
     * @Route("/about/data-service", name="data_service")
     */
    public function dataService() {
        return $this->render('home/data_service.html.twig', ['menuItem' => 'service']);
    }

    /**
     * @Route("/about/phpinfo", name="about_phpinfo")
     */
    public function phpinfo() {
        dd(phpinfo());
    }



}
