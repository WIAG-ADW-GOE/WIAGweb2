<?php
namespace App\Controller;

use App\Entity\Person;
use App\Repository\PersonRepository;
use App\Form\BishopFormType;
use App\Form\Model\BishopFormModel;
use App\Entity\Role;

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
     * @Route("/bischof", name="bishop")
     */
    public function query(Request $request,
                          PersonRepository $repository) {

        // we need to pass an instance of BishopFormModel, because facets depend on it's data
        $model = new BishopFormModel;

        $form = $this->createForm(BishopFormType::class, $model);
        $offset = 0;


        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $singleoffset = $request->request->get('singleoffset');
            if(!is_null($singleoffset)) {
                return $this->bishop($form, $singleoffset);
            }


            $model = $form->getData();
            dump($model);
            $offset = $request->request->get('offset');

            $count = $repository->bishopCountByModel($model);

            $result = $repository->bishopWithOfficeByModel($model, self::PAGE_SIZE, $offset);

            return $this->renderForm('bishop/query_result.html.twig', [
                'menuItem' => 'collections',
                'form' => $form,
                'data' => $result,
                'offset' => $offset,
                'pageSize' => self::PAGE_SIZE,
            ]);

        }

        return $this->renderForm('bishop/query.html.twig', [
            'menuItem' => 'collections',
            'form' => $form,
        ]);

    }

    /**
     * AJAX
     *
     * @Route("/bischof_name", name="bishop_name")
     */
    public function bishopName() {
        return ["name"];
    }

    /**
     * AJAX
     *
     * @Route("/bischof_diocese", name="bishop_diocese")
     */
    public function bishopDiocese() {
        return ["diocese"];
    }

    /**
     * AJAX
     *
     * @Route("/bischof_office", name="bishop_office")
     */
    public function bishopOffice() {
        return ["office"];
    }

    /**
     * Test
     * @Route("/bischof_aemter/{name}", name="bishop_person_roles")
     */
    public function bishopPersonRoles($name) {
        $repository = $this->getDoctrine()
                           ->getRepository(Person::class);

        $persons = $repository->findByRole($name);
        dd($persons);

        return new Response("bishopPersonRoles");
    }


}
