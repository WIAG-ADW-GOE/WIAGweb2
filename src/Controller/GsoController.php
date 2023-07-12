<?php
namespace App\Controller;

use App\Entity\Gso\GsoItems;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class GsoController extends AbstractController {
    /** number of items per page */
    const PAGE_SIZE = 20;
    /** number of suggestions in autocomplete list */
    const SUGGEST_SIZE = 8;


    /**
     *
     * @Route("/edit/gso-update", name="gso_update")
     */
    public function gsoUpdate(Request $request, ManagerRegistry $doctrine) {

        $em = $doctrine->getManager('gso');
        $itemsRepository = $em->getRepository(GsoItems::class, 'gso');

        $items = $itemsRepository->find('278795');

        return new Response("items Objekt mit Status ".$items->getStatus());
    }

}
