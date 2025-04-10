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
use App\Entity\ItemReference;
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


    #[Route(path: '/download/csv/person/{corpusId}', name: 'download-csv-person-data')] // Returns streamed response for person data, e.g. name, birthday
    public function csvPersonData(Request $request, $corpusId) {

        ini_set('max_execution_time', 300);

        $corpusRepository = $this->entityManager->getRepository(Corpus::class);
        // dev
        $personRepository = $this->entityManager->getRepository(Person::class);
        $personRoleRepository = $this->entityManager->getRepository(PersonRole::class);

        $corpus = $corpusRepository->findOneByCorpusId($corpusId);

        $model = PersonFormModel::newByArray($request->query->all());
        $model->corpus = $corpusId;
        $form = $this->createForm(PersonFormType::class, $model, [
            'forceFacets' => false,
            'repository' => $personRepository,
        ]);

        $form->handleRequest($request);
        $model = $form->getData();
        $model->corpus = $corpusId;

        if ($form->isSubmitted() && $form->isValid()) {

            $id_all = $personRepository->findPersonIds($model);
            // dev/debug
            $download_debug = false;
            if ($download_debug) {
                $person_list = $personRepository->findArray($id_all);
                $role_list = $personRoleRepository->findRoleArray($id_all);
                $person = $person_list[3];
                $inr_role_list = array_column($person['item']['itemNameRole'], 'itemIdRole');
                $role_list_single = UtilService::findAllArray($role_list, 'personId', $inr_role_list);
                DownloadService::describeRoleList($role_list_single);
                dd($role_list_single);
            }

            $response = new StreamedResponse();

            $callback = array($this, 'yieldPersonData');
            $response->setCallback(function() use ($callback, $id_all) {
                $callback($id_all);
            });

            $corpus_txt = str_replace(' ', '-', $corpus->getName());
            $filename = "WIAG-".$corpus_txt."-Lebensdaten.csv";

            $response->headers->set('X-Accel-Buffering', 'no');
            $response->headers->set('Content-Type', 'application/force-download');
            $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);

            // return $this->render("base.html.twig");

            return $response;

        }

        return new Response("Fehler: Das Formular konnte nicht ausgewertet werden");
    }

    /**
     * callback for streaming of person data
     */
    private function yieldPersonData($id_list) {
        $personRepository = $this->entityManager->getRepository(Person::class);
        $personRoleRepository = $this->entityManager->getRepository(PersonRole::class);

        $handle = fopen('php://output', 'r+');
        $chunk_size = 200;
        $chunk_offset = 0;
        $count = count($id_list);
        fputcsv($handle, DownloadService::formatPersonDataHeader(), ";");
        while ($chunk_offset < $count) {
            $id_chunk = array_slice($id_list, $chunk_offset, $chunk_size);
            $chunk_offset += $chunk_size;
            $person_chunk = $personRepository->findArray($id_chunk);
            $role_chunk = $personRoleRepository->findArray($id_chunk);


            foreach ($person_chunk as $person) {
                $inr_role_list = array_column($person['item']['itemNameRole'], 'itemIdRole');
                $role_list = UtilService::findAllArray($role_chunk, 'personId', $inr_role_list);
                $rec = DownloadService::formatPersonData($person, $role_list);

                fputcsv($handle, $rec, ";");
            }

            ob_flush();
            flush();
            // avoid memory overflow
            unset($inr_role_list);
            unset($role_list);
            unset($person_chunk);
            unset($role_chunk);

        }
        fclose($handle);
    }

    #[Route(path: '/download/csv/person-role/{corpusId}', name: 'download-csv-person-role-data')] // Returns streamed response for person role data
    public function csvPersonRoleData(Request $request, $corpusId) {

        ini_set('max_execution_time', 300);

        $corpusRepository = $this->entityManager->getRepository(Corpus::class);
        $personRepository = $this->entityManager->getRepository(Person::class);

        $corpus = $corpusRepository->findOneByCorpusId($corpusId);

        $model = PersonFormModel::newByArray($request->query->all());
        $model->corpus = $corpusId;
        $form = $this->createForm(PersonFormType::class, $model, [
            'forceFacets' => false,
            'repository' => $personRepository,
        ]);

        $form->handleRequest($request);
        $model = $form->getData();
        $model->corpus = $corpusId;

        if ($form->isSubmitted() && $form->isValid()) {

            $id_all = $personRepository->findPersonIds($model);
            // dev/debug
            $download_debug = false;
            if ($download_debug) {
                $itemNameRoleRepository = $this->entityManager->getRepository(ItemNameRole::class);
                $person_list = $personRepository->findArray($id_all);
                $role_list = $itemNameRoleRepository->findRoleArray($id_all);
                $person = $person_list[0];
                $inr_role_list = array_column($person['item']['itemNameRole'], 'itemIdRole');
                $role_list_single = UtilService::findAllArray($role_list, 'personId', $inr_role_list);
                foreach($role_list_single as $person_role) {
                    $rec = DownloadService::formatPersonRoleData($person, $person_role);
                }
                // dd($person, $role_list_single);
            }

            $response = new StreamedResponse();

            $callback = array($this, 'yieldPersonRoleData');
            $response->setCallback(function() use ($callback, $id_all) {
                $callback($id_all);
            });

            $corpus_txt = str_replace(' ', '-', $corpus->getName());
            $filename = "WIAG-".$corpus_txt."-Ämter.csv";

            $response->headers->set('X-Accel-Buffering', 'no');
            $response->headers->set('Content-Type', 'application/force-download');
            $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);

            return $response;

        }

        return new Response("Fehler: Das Formular konnte nicht ausgewertet werden");
    }

    /**
     * callback for streaming of person role data
     */
    private function yieldPersonRoleData($id_list) {
        $personRepository = $this->entityManager->getRepository(Person::class);
        $itemNameRoleRepository = $this->entityManager->getRepository(ItemNameRole::class);

        $handle = fopen('php://output', 'r+');
        $chunk_size = 200;
        $chunk_offset = 0;
        $count = count($id_list);
        fputcsv($handle, DownloadService::formatPersonRoleDataHeader(), ";");
        while ($chunk_offset < $count) {
            $id_chunk = array_slice($id_list, $chunk_offset, $chunk_size);
            $person_chunk = $personRepository->findArray($id_chunk);
            $role_chunk = $itemNameRoleRepository->findRoleArray($id_chunk);
            $chunk_offset += $chunk_size;

            foreach ($person_chunk as $person) {
                $inr_role_list = array_column($person['item']['itemNameRole'], 'itemIdRole');
                $role_list = UtilService::findAllArray($role_chunk, 'personId', $inr_role_list);
                foreach ($role_list as $role) {
                    $rec = DownloadService::formatPersonRoleData($person, $role);
                    fputcsv($handle, $rec, ";");
                }
            }

            ob_flush();
            flush();
            // avoid memory overflow
            unset($inr_role_list);
            unset($role_list);
            unset($person_chunk);
            unset($role_chunk);
        }
        fclose($handle);
    }

    #[Route(path: '/download/csv/person-reference/{corpusId}', name: 'download-csv-person-reference')] // Returns streamed response for person reference data
    public function csvPersonReference(Request $request, $corpusId) {

        ini_set('max_execution_time', 300);

        $corpusRepository = $this->entityManager->getRepository(Corpus::class);
        $personRepository = $this->entityManager->getRepository(Person::class);

        $corpus = $corpusRepository->findOneByCorpusId($corpusId);

        $model = PersonFormModel::newByArray($request->query->all());
        $model->corpus = $corpusId;
        $form = $this->createForm(PersonFormType::class, $model, [
            'forceFacets' => false,
            'repository' => $personRepository,
        ]);

        $form->handleRequest($request);
        $model = $form->getData();
        $model->corpus = $corpusId;

        if ($form->isSubmitted() && $form->isValid()) {

            $id_all = $personRepository->findPersonIds($model);
            // dev/debug
            $download_debug = false;
            if ($download_debug) {
                $itemNameRoleRepository = $this->entityManager->getRepository(ItemNameRole::class);
                $person_list = $personRepository->findArray($id_all);
                $vol_list = $itemNameRoleRepository->findSimpleReferenceList($id_all);
                $person = $person_list[1];
                $inr_role_list = array_column($person['item']['itemNameRole'], 'itemIdRole');
                $vol_list_single = UtilService::findAllArray($vol_list, 'itemId', $inr_role_list);
                // dd($person, $vol_list_single);
            }

            $response = new StreamedResponse();

            $callback = array($this, 'yieldPersonReference');
            $response->setCallback(function() use ($callback, $id_all) {
                $callback($id_all);
            });

            $corpus_txt = str_replace(' ', '-', $corpus->getName());
            $filename = "WIAG-".$corpus_txt."-Literatur.csv";

            $response->headers->set('X-Accel-Buffering', 'no');
            $response->headers->set('Content-Type', 'application/force-download');
            $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);

            return $response;

        }

        return new Response("Fehler: Das Formular konnte nicht ausgewertet werden");
    }

    /**
     * Callback for streaming of person references
     */
    private function yieldPersonReference($id_list) {
        $personRepository = $this->entityManager->getRepository(Person::class);
        $itemNameRoleRepository = $this->entityManager->getRepository(ItemNameRole::class);

        $handle = fopen('php://output', 'r+');
        $chunk_size = 200;
        $chunk_offset = 0;
        $count = count($id_list);
        fputcsv($handle, DownloadService::formatPersonReferenceHeader(), ";");
        while ($chunk_offset < $count) {
            $id_chunk = array_slice($id_list, $chunk_offset, $chunk_size);
            $person_chunk = $personRepository->findArray($id_chunk);
            $ref_chunk = $itemNameRoleRepository->findSimpleReferenceList($id_chunk);
            $chunk_offset += $chunk_size;

            foreach ($person_chunk as $person) {
                $inr_role_list = array_column($person['item']['itemNameRole'], 'itemIdRole');
                $ref_list = UtilService::findAllArray($ref_chunk, 'itemId', $inr_role_list);
                foreach ($ref_list as $ref) {
                    $rec = DownloadService::formatPersonReference($person, $ref);
                    fputcsv($handle, $rec, ";");
                }
            }

            ob_flush();
            flush();
            // avoid memory overflow
            unset($inr_role_list);
            unset($ref_list);
            unset($person_chunk);
            unset($ref_chunk);
        }
        fclose($handle);
    }


    #[Route(path: '/download/csv/person-url-external/{corpusId}', name: 'download-csv-person-url-external')] // Returns streamed response for person reference data
    public function csvPersonUrlExternal(Request $request, $corpusId) {

        ini_set('max_execution_time', 300);

        $corpusRepository = $this->entityManager->getRepository(Corpus::class);
        $itemNameRoleRepository = $this->entityManager->getRepository(ItemNameRole::class);
        // dev
        // $itemReferenceRepository = $this->entityManager->getRepository(ItemReference::class);
        $personRepository = $this->entityManager->getRepository(Person::class);

        $corpus = $corpusRepository->findOneByCorpusId($corpusId);

        $model = PersonFormModel::newByArray($request->query->all());
        $model->corpus = $corpusId;
        $form = $this->createForm(PersonFormType::class, $model, [
            'forceFacets' => false,
            'repository' => $personRepository,
        ]);

        $form->handleRequest($request);
        $model = $form->getData();
        $model->corpus = $corpusId;

        if ($form->isSubmitted() && $form->isValid()) {

            $id_all = $personRepository->findPersonIds($model);
            // dev/debug
            $download_debug = false;
            if ($download_debug) {
                $person_list = $personRepository->findArray($id_all);
                $person = $person_list[7];
                $uext_list = $person['item']['urlExternal'];
                // dd(s$person, $uext_list);
            }

            $response = new StreamedResponse();

            $callback = array($this, 'yieldPersonUrlExternal');
            $response->setCallback(function() use ($callback, $id_all) {
                $callback($id_all);
            });

            $corpus_txt = str_replace(' ', '-', $corpus->getName());
            $filename = "WIAG-".$corpus_txt."-externe-IDs.csv";

            $response->headers->set('X-Accel-Buffering', 'no');
            $response->headers->set('Content-Type', 'application/force-download');
            $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);

            return $response;

        }

        return new Response("Fehler: Das Formular konnte nicht ausgewertet werden");
    }

    /**
     * Callback for streaming of person references
     */
    private function yieldPersonUrlExternal($id_list) {
        $personRepository = $this->entityManager->getRepository(Person::class);
        $itemNameRoleRepository = $this->entityManager->getRepository(ItemNameRole::class);

        $handle = fopen('php://output', 'r+');
        $chunk_size = 200;
        $chunk_offset = 0;
        $count = count($id_list);
        fputcsv($handle, DownloadService::formatPersonUrlExternalHeader(), ";");
        while ($chunk_offset < $count) {
            $id_chunk = array_slice($id_list, $chunk_offset, $chunk_size);
            $person_chunk = $personRepository->findArray($id_chunk);
            $chunk_offset += $chunk_size;

            foreach ($person_chunk as $person) {
                foreach ($person['item']['urlExternal'] as $uext) {
                    $rec = DownloadService::formatPersonUrlExternal($person, $uext);
                    fputcsv($handle, $rec, ";");
                }
            }
            ob_flush();
            flush();

            // avoid memory overflow
            unset($person_chunk);
        }
        fclose($handle);
    }

}
