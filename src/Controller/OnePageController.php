<?php
namespace App\Controller;

use App\Entity\Corpus;
use App\Entity\Item;
use App\Entity\ItemNameRole;
use App\Entity\Role;
use App\Entity\Person;
use App\Entity\PersonRole;
use App\Entity\Authority;
use App\Entity\Institution;
use App\Entity\UrlExternal;

use App\Form\PersonFormType;
use App\Form\Model\PersonFormModel;

use App\Service\PersonService;
use App\Service\UtilService;
use App\Service\AutocompleteService;

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


class OnePageController extends AbstractController {

    /**
     * show selected canons (e.g. by domstift) in one page
     * @Route("/person/onepage", name="person_onepage")
     */
    public function onepage(Request $request,
                            EntityManagerInterface $entityManager,
                            UtilService $utilService) {

        $itemNameRoleRepository = $entityManager->getRepository(ItemNameRole::class);
        $personRepository = $entityManager->getRepository(Person::class);
        $corpusRepository = $entityManager->getRepository(Corpus::class);
        $institutionRepository = $entityManager->getRepository(Institution::class);
        $corpusId = 'can';
        $corpus = $corpusRepository->findOneByCorpusId($corpusId);


        $model = new PersonFormModel;
        $model->corpus = $corpusId;

        $form = $this->createForm(PersonFormType::class, $model, [
            'forceFacets' => false,
            'repository' => $itemNameRoleRepository,
        ]);
        $form->handleRequest($request);
        $model = $form->getData();

        // is request related to a Domstift?
        $domstift_id = null;
        if (!is_null($model->domstift)) {
            $cap_q = $institutionRepository->findByCorpusAndName('cap', $model->domstift);
            if (count($cap_q) > 0) {
                $domstift_id = array_values($cap_q)[0]['id'];
            }
        }
        $facetDomstift = $model->facetDomstift;
        if (is_null($domstift_id) and !is_null($facetDomstift)) {
            $fct_cap_list = array_column($facetDomstift, 'name');
            $fct_cap_cand = array_values($fct_cap_list)[0];
            $cap_q = $institutionRepository->findByCorpusAndName('cap', $fct_cap_cand);
            if (count($cap_q) > 0) {
                $domstift_id = array_values($cap_q)[0]['id'];
            }
        }

        $id_all = $itemNameRoleRepository->findPersonIds($model);
        $count = count($id_all);

        // set global limit here (avoid server crash!)
        $global_limit = 4000;
        $id_all = array_slice($id_all, 0, $global_limit - 1);

        $chunk_offset = 0;
        // sort the list by office criteria (see below)
        // if order returned by findPersonIds() is not appropriate, and an extra sorting step is required,
        // then splitting in chunks is not possible.
        $limit = 200; # chunk size
        // split up in chunks
        $id_list = array_slice($id_all, $chunk_offset, $limit);

        $person_node_list = array();

        // loop over chunks
        while (count($id_list) > 0) {
            $item_name_role_list = $itemNameRoleRepository->findByItemIdName($id_list);

            // 2023-09-07 in memory version
            // hildesheim: 15 s, 470 MB, limit on the server is 512 MB 2023-09-07
            // hildesheim, with $limit (chunk size) 200: 15 s, 290 MB
            $person_role_id_list = array();
            foreach($item_name_role_list as $item_name_role) {
                $person_role_id_list[] = $item_name_role->getItemIdRole();
            }

            $person_all_list = $personRepository->findList($person_role_id_list);

            // group by item_id_name in the order of $id_list

            foreach($id_list as $id_loop) {
                // $domstift_id if not null is used for sorting
                $person_node_list[] = $this->extractNode($item_name_role_list, $person_all_list, $domstift_id, $id_loop);
            }

            // 2023-09-07 sequential version
            // hildesheim 21 s, 128 MB
            // foreach ($id_list as $id_node) {
            //     // get office data, e.g. from Digitales Personenregister
            //     $person_role_id_list = $itemNameRoleRepository->findPersonIdRole($id_node);
            //     $person_role_list = $personRepository->findList($person_role_id_list);
            //     // sort office data
            //     foreach ($person_role_list as $person_role) {
            //         $this->sortRole($person_role, $model);
            //     }

            //     $person = array_values($person_role_list)[0];

            //     $node = [
            //         'personName' => $person,
            //         'personRole' => $person_role_list,
            //     ];
            //     $person_node_list[] = $node;

            // }

            $chunk_offset += $limit;
            $id_list = array_slice($id_all, $chunk_offset, $limit);
        }


        // sort elements of $canon_node_list by first relevant office, then by name
        if ($model->domstift) {
            // person order: this seems to be the sorting returned by findPersonIds
            // $this->sortByRelevantOffice($person_node_list);
        }

        $title = $model->domstift;
        if ($title) {
            $part_list = explode(" ", $title);
            if (count($part_list) == 1) {
                $title = 'Domstift '.$title;
            }
            $title = ucwords($title);
        } else {
            $title = "Domherren";
        }

        return $this->render('person/onepage_result.html.twig', [
            'title' => $title,
            'canonNodeList' => $person_node_list,
            'limitReached' => count($person_node_list) >= $global_limit,
        ]);

    }

    /**
     * extract node data for one person with $id_name out of $person_all_list
     *
     * sort roles by domstift
     */
    private function extractNode($item_name_role_list, $person_all_list, $domstift_id, $id_name) {
        $item_name_role = array_filter(
            $item_name_role_list,
            function($v) use ($id_name) { return ($v->getItemIdName() == $id_name); }
        );
        $person_name = null;
        $person_role = null;
        foreach ($item_name_role as $item) {
            $item_id_role = $item->getItemIdRole();
            $person_role_filter = array_filter(
                $person_all_list,
                function($v) use ($item_id_role) { return ($v->getId() == $item_id_role); }
            );
            $person_role_loop = array_values($person_role_filter)[0];
            if ($person_role_loop->getItem()->hasCorpus('epc') or $person_role_loop->getItem()->hasCorpus('can')) {
                $person_name = $person_role_loop;
            } else {
                $person_role = $person_role_loop;
            }
        }

        // build node
        if (!is_null($person_name)) {
            $node_name = $person_name;
            $node_role = [$person_name];
            if (!is_null($person_role)) {
                $node_role[] = $person_role;
            }
        } else {
            $node_name = $person_role;
            $node_role = [$person_role];
        }
        $node = [
            'personName' => $node_name,
            'personRole' => $node_role
        ];

        foreach ($node_role as $nr_loop) {
            $this->sortRole($nr_loop, $domstift_id);
        }

        return $node;
    }


    private function sortRole($person, $domstift_id) {
        $role = $person->getRole();
        if (is_array($role)) {
            $role_list = $role;
        } else {
            $role_list = $role->toArray();
        }
        $crit_list = ['placeName', 'dateSortKey', 'id'];
        $role_list = UtilService::sortByFieldList($role_list, $crit_list );
        if (!is_null($domstift_id)) {
            $role_list = UtilService::sortByDomstift($role_list, $domstift_id);
        }
        $person->setRole($role_list);
    }

    private function sortByRelevantOffice($person_node_list) {
        uasort($person_node_list, function($a, $b) {
            // first criterion: earliest office
            $a_key = PersonRole::MAX_DATE_SORT_KEY;
            if (count($a["personRole"]) > 0) {
                $a_fpr = $a["personRole"][0];
                $a_key = $a_fpr->getFirstRoleSortKey();
            }

            $b_key = PersonRole::MAX_DATE_SORT_KEY;
            if (count($b["personRole"]) > 0) {
                $b_fpr = $b["personRole"][0];
                $b_key = $b_fpr->getFirstRoleSortKey();
            }

            $result = $a_key < $b_key ? -1 : ($a_key > $b_key ? 1 : 0);

            // second criterion: name
            if ($result == 0) {
                $a_name = $a["personName"]->getDisplayname();
                $b_name = $b["personName"]->getDisplayname();

                $result =  $a_name < $b_name ? -1 : ($a_name > $b_name ? 1 : 0);
            }

            // third criterion: id
            if ($result == 0) {
                $a_id = $a["personName"]->getId();
                $b_id = $b["personName"]->getId();

                $result =  $a_id < $b_id ? -1 : ($a_id > $b_id ? 1 : 0);
            }

            return $result;
        });
    }

     /**
     * show references for selected canons
     * @Route("/domherr/onepage/literatur/{corpusIdRef}", name="person_onepage_references")
     */
    public function references(Request $request,
                               EntityManagerInterface $entityManager,
                               $corpusIdRef) {

        $itemNameRoleRepository = $entityManager->getRepository(ItemNameRole::class);

        $model = new PersonFormModel;
        $model->corpus = 'can'; // query corpus is always 'can'
        $form = $this->createForm(PersonFormType::class, $model, [
            'forceFacets' => false,
            'repository' => $itemNameRoleRepository,
        ]);

        $form->handleRequest($request);
        $model = $form->getData();

        $id_list = $itemNameRoleRepository->findPersonIds($model);
        $reference_list = $itemNameRoleRepository->referenceListByCorpus($id_list, $corpusIdRef);

        $title = 'Literatur andere';
        $criteria_list = ['titleShort', 'displayOrder', 'referenceId'];
        if ($corpusIdRef == 'dreg-can') {
                $title = 'Literatur Germania Sacra';
                $criteria_list = ['displayOrder', 'titleShort', 'referenceId'];
        }

        $reference_list = UtilService::sortByFieldList($reference_list, $criteria_list);

        return $this->render('canon/onepage_references.html.twig', [
            'title' => $title,
            'reference_list' => $reference_list,
        ]);
    }



}
