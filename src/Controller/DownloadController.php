<?php
namespace App\Controller;

use App\Entity\Corpus;
use App\Entity\Item;
use App\Entity\ItemNameRole;
use App\Entity\Role;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\Authority;
use App\Entity\UrlExternal;
use App\Form\PersonFormType;
use App\Form\Model\PersonFormModel;


use App\Service\UtilService;
use App\Service\DownloadService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;


use Doctrine\ORM\EntityManagerInterface;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * provide bulk download functions
 */
class DownloadController extends AbstractController {

    private $entityManager = null;

    public function __construct(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
    }


    /**
     * @Route("/download/csv/person/{corpusId}", name="download-csv-person-data")
     *
     * @return streamed response for person data, e.g. name, birthday
     */
    public function csvPersonData(Request $request, $corpusId) {

        $itemNameRoleRepository = $this->entityManager->getRepository(ItemNameRole::class);
        // dev
        $personRepository = $this->entityManager->getRepository(Person::class);


        $model = PersonFormModel::newByArray($request->query->all());
        $model->corpus = $corpusId;
        $form = $this->createForm(PersonFormType::class, $model, [
            'forceFacets' => false,
            'repository' => $itemNameRoleRepository,
        ]);

        $form->handleRequest($request);
        $model = $form->getData();
        $model->corpus = $corpusId;

        if ($form->isSubmitted() && $form->isValid()) {

            $id_all = $itemNameRoleRepository->findPersonIds($model);
            // dev/debug
            $download_debug = false;
            if ($download_debug) {
                $person_list = $personRepository->findSimpleList($id_all);
                $role_list = $itemNameRoleRepository->findSimpleRoleList($id_all);
                $person = $person_list[2];
                $inr_role_list = array_column($person['item']['itemNameRole'], 'itemIdRole');
                $role_list_single = UtilService::findAllArray($role_list, 'personId', $inr_role_list);
                $description_role_list = DownloadService::descriptionRoleList($role_list_single);
                dd($role_list_single, $description_role_list);
            }

            $response = new StreamedResponse();

            $callback = array($this, 'yieldPersonData');
            $response->setCallback(function() use ($callback, $id_all) {
                $callback($id_all);
            });

            $filename = "WIAG-".$corpusId.".csv";

            $response->headers->set('X-Accel-Buffering', 'no');
            $response->headers->set('Content-Type', 'application/force-download');
            $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);

            return $response;

        }

        return new Response("Fehler: Das Formular konnte nicht ausgewertet werden");
    }

    private function yieldPersonData($id_list) {
        $personRepository = $this->entityManager->getRepository(Person::class);
        $itemNameRoleRepository = $this->entityManager->getRepository(ItemNameRole::class);

        $handle = fopen('php://output', 'r+');
        $chunk_size = 200;
        $chunk_pos = 0;
        $count = count($id_list);
        fputcsv($handle, DownloadService::formatPersonDataHeader(), ";");
        while ($chunk_pos < $count) {
            $id_chunk = array_slice($id_list, $chunk_pos, $chunk_size);
            $person_chunk_list = $personRepository->findSimpleList($id_chunk);
            $role_chunk_list = $itemNameRoleRepository->findSimpleRoleList($id_chunk);
            $chunk_pos += $chunk_size;

            foreach ($person_chunk_list as $person) {
                $inr_role_list = array_column($person['item']['itemNameRole'], 'itemIdRole');
                $role_list = UtilService::findAllArray($role_chunk_list, 'personId', $inr_role_list);
                $rec = DownloadService::formatPersonData($person, $role_list);

                fputcsv($handle, $rec, ";");
            }
            // avoid memory overflow
            unset($person_list);
            unset($role_list);

            ob_flush();
            flush();
        }
        fclose($handle);
    }

}
