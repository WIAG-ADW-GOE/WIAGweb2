<?php
namespace App\Controller;

use App\Entity\Person;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class DioceseController extends AbstractController {

    /**
     *
     * @Route("/Bistum", name="diocese")
     */
    public function diocese(Request $request) {
        $model = new Diocese();

        $route_name = $this->generateUrl('diocese_name');

        $form = $this->createFormBuilder($diocesequery)
                     ->add('diocese', TextType::class, [
                         'label' => false,
                         'required' => false,
                         'attr' => [
                             'placeholder' => 'Erzbistum/Bistum',
                             'class' => 'js-name-autocomplete',
                             'data-autocomplete-url' => $route_utility_names,
                             'size' => 25,
                         ],
                     ])
                     ->add('searchHTML', SubmitType::class, [
                         'label' => 'Suche',
                         'attr' => [
                             'class' => 'btn btn-secondary btn-light',
                         ],
                     ])
                     ->getForm();

        $form->handlerequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $diocesequery = $form->getData();

            # strip 'bistum' or 'erzbistum' from search field diocese
            $diocesequery->normDiocese();

            $offset = $request->request->get('offset') ?? 0;
            $format = $request->request->get('format') ?? null;

            $offset = floor($offset / self::LIST_LIMIT) * self::LIST_LIMIT;

            $singleoffset = $request->request->get('singleoffset');

            $name = $diocesequery->getDiocese();

            $repository = $this->getDoctrine()
                               ->getRepository(Diocese::class);

            return new Response("Query Dioceses");
        }

    }

    /**
     * AJAX
     *
     * @Route("/diocese_name", name="diocese_name")
     */
    public function DioceseName() {
        return ["name"];
    }


}
