<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Entity\Diocese;
use App\Entity\PersonRole;
use App\Entity\UrlExternal;
use App\Entity\SkosLabel;
use App\Entity\Authority;
use App\Entity\Place;
use App\Entity\UserWiag;
use App\Entity\InputError;

use App\Service\UtilService;
use App\Service\EditService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use Doctrine\ORM\EntityManagerInterface;


class EditDioceseController extends AbstractController {

    /**
     * @Route("/edit/diocese/query", name="edit_diocese_query")
     */
    public function query(Request $request,
                          EntityManagerInterface $entityManager): Response {

        $dioceseRepository = $entityManager->getRepository(Diocese::class);


        $model = [
            'name' => '',
        ];

        $form = $this->createFormBuilder($model)
                     ->setMethod('GET')
                     ->add('name', TextType::class, [
                         'label' => 'Name',
                         'required' => false,
                         'attr' => [
                             'placeholder' => 'Bezeichnung'
                         ],
                     ])
                     ->getForm();

        $form->handleRequest($request);
        $model = $form->getData();

        $diocese_list = array();
        if ($form->isSubmitted() && $form->isValid()) {
        } else {
            $model['name'] = '';
        }

        $diocese_list = $dioceseRepository->findByModel($model);

        // sort null last
        $sort_criteria = ['name', 'id'];
        $diocese_list = UtilService::sortByFieldList($diocese_list, $sort_criteria);

        $template = 'edit_diocese/query.html.twig';
        $edit_form_id = 'diocese_edit_form';

        return $this->renderForm($template, [
            'menuItem' => 'edit-menu',
            'form' => $form,
            'editFormId' => $edit_form_id,
            'dioceseList' => $diocese_list,
        ]);

    }

    /**
     * @Route("/edit/diocese/save", name="edit_diocese_save")
     */
    public function save(Request $request,
                         EntityManagerInterface $entityManager) {

        $dioceseRepository = $entityManager->getRepository(Diocese::class);
        $itemRepository = $entityManager->getRepository(Item::class);
        $userWiagRepository = $entityManager->getRepository(UserWiag::class);

        $current_user_id = $this->getUser()->getId();
        $current_user = $userWiagRepository->find($current_user_id);

        $edit_form_id = 'diocese_edit_form';
        $form_data = $request->request->get($edit_form_id) ?? array();

        // validation
        $error_flag = false;

        $diocese_list = array();

        $id_list = array_column($form_data, 'id');
        $query_result = $dioceseRepository->findList($id_list);
        foreach ($query_result as $diocese) {
            $diocese_list[$diocese->getId()] = $diocese;
        }

        // map data
        foreach($form_data as $data) {
            $id = $data['id'];
            $form_is_expanded = isset($data['formIsExpanded']) ? 1 : 0;
            if ($id > 0) {
                $diocese = $diocese_list[$id];

            } else {
                $diocese = new Diocese($current_user_id);
            }
            $diocese->getItem()->setFormIsExpanded($form_is_expanded);
            if (isset($data['formIsEdited'])) {
                $item = $diocese->getItem();
                if (!$id > 0) {
                    // new entry
                    $form_is_expanded = 1;
                    $diocese_list[] = $diocese;
                    $data['item']['idInSource'] = '0';
                } else {
                    $diocese->getItem()->setIsNew(false);
                    $diocese_list[$id] = $diocese;
                }
                $item->setFormIsEdited(1);

                // item
                UtilService::setByKeys(
                    $item,
                    $data['item'],
                    ['idInSource']);

                // diocese
                UtilService::setByKeys(
                    $diocese,
                    $data,
                    Diocese::EDIT_FIELD_LIST);

                $this->mapBishopricSeat($diocese, $data['bishopricSeat'], $entityManager);

                EditService::mapUrlExternal($item, $data['urlext'], $entityManager);
                EditService::mapSkosLabel($diocese, $data['skosLabel']);

                // validate input
                if (trim($diocese->getName()) == "") {
                    $msg = "Bitte das Feld 'Bezeichung' ausfüllen.";
                    $diocese->getInputError()->add(new InputError('general', $msg, 'error'));
                }
                $error_flag = ($error_flag or !$item->getInputError()->isEmpty());
            }
        }

        // save
        if (!$error_flag) {
            $item_type_id = ITEM::ITEM_TYPE_ID['Bistum']['id'];
            $max_id = $itemRepository->maxIdInSource($item_type_id);
            $next_id = $max_id + 1;
            foreach ($diocese_list as $diocese) {
                $item = $diocese->getItem();
                if ($item->getFormIsEdited()) {
                    if ($item->getIsNew()) {
                        $item->setIdInSource($next_id);
                        EditService::removeUrlExternalMayBe($item, $entityManager);
                        EditService::removeSkosLabelMayBe($diocese, $entityManager);
                        $entityManager->persist($diocese);
                        // get new item from the database (ID updated)
                        $entityManager->flush();
                        $next_id += 1;

                        // we need to set conceptId manually
                        $diocese->getAltLabels()->map(function($v) use ($diocese) {
                            $v->setConceptId($diocese->getItem()->getId());
                        });

                        $item->setIsNew(false);
                    }
                    // delete flag?
                    EditService::removeUrlExternalMayBe($item, $entityManager);
                    EditService::removeSkosLabelMayBe($diocese, $entityManager);
                    $item->updateChangedMetaData($current_user);
                    $item->setFormIsEdited(false);

                    $label_list = $diocese->getAltLabels();
                    foreach ($label_list as $label) {
                        $label->setDiocese($diocese);
                    }
                }
            }

            $entityManager->flush();
        }

        // provide empty form elements if neccessary
        foreach ($diocese_list as $diocese) {
            $this->setDisplayData($diocese, $entityManager);
        }

        $template = 'edit_diocese/_list.html.twig';

        $debug = $request->request->get('formType');

        return $this->render($template, [
            'editFormId' => $edit_form_id,
            'dioceseList' => $diocese_list,
        ]);

    }

    private function mapBishopricSeat($diocese, $data, $entityManager) {
        $placeRepository = $entityManager->getRepository(Place::class);

        $match_list = array();
        preg_match("/[[:alpha:]]+ \(([0-9]+)\)/", $data, $match_list);

        $diocese->setFormBishopricSeat($data);

        $error_flag = true;
        if (count($match_list) > 0) {
            $q_result = $placeRepository->findByGeonamesId($match_list[1]);
            if (!is_null($q_result) and count($q_result) > 0) {
                $diocese->setBishopricSeatId($q_result[0]->getId());
                $diocese->setBishopricSeat($q_result[0]);
                $error_flag = false;
            }
        }

        if ($error_flag) {
            $msg = "Es wurde kein Ort für den Bischofssitz gefunden.";
            $item = $diocese->getItem();
            $item->getInputError()->add(new InputError('general', $msg, 'error'));
        }

        return $diocese;

    }

    /**
     * set default elements for the form
     */
    private function setDisplayData($diocese, $entityManager) {
        $personRoleRepository = $entityManager->getRepository(PersonRole::class);

        $item = $diocese->getItem();
        if ($item->getFormIsExpanded()) {
            $uext_list = $item->getUrlExternal();
            if (count($uext_list) < 1) {
                $uext_list->add(new UrlExternal());
            }
            $skos_label_list = $diocese->getAltLabels();
            if (count($skos_label_list) < 1) {
                $skos_label_list->add(new SkosLabel(Diocese::SKOS_SCHEME_ID));
            }
            $dioceseCount = $personRoleRepository->dioceseReferenceCount($diocese->getId());
            $diocese->setReferenceCount($dioceseCount);
        }
    }

    /**
     *
     * @Route("/edit/diocese/delete/{q_id}", name="edit_diocese_delete")
     */
    public function deleteEntry(Request $request,
                                int $q_id,
                                EntityManagerInterface $entityManager) {
        $edit_form_id = 'diocese_edit_form';
        $form_data = $request->request->get($edit_form_id);

        $dioceseRepository = $entityManager->getRepository(Diocese::class);

        $id_list = array_column($form_data, 'id');
        $query_result = $dioceseRepository->findList($id_list);
        $diocese_list = array();
        foreach ($query_result as $diocese) {
            $diocese_list[$diocese->getId()] = $diocese;
        }

        // deletion takes priority: all other edit data are lost and sub-forms are closed
        foreach ($diocese_list as $diocese) {
            $id_loop = $diocese->getId();
            if ($id_loop == $q_id) {
                $item = $diocese->getItem();
                foreach($diocese->getAltLabels() as $label) {
                    $entityManager->remove($label);
                }
                foreach($item->getUrlExternal() as $uext) {
                    $entityManager->remove($uext);
                }
                $entityManager->remove($diocese);
                $entityManager->remove($item);
            }
        }

        $entityManager->flush();

        $query_result = $dioceseRepository->findList($id_list);
        $diocese_list = array();
        foreach ($query_result as $diocese) {
            $diocese_list[$diocese->getId()] = $diocese;
        }

        $template = 'edit_diocese/_list.html.twig';

        return $this->render($template, [
            'editFormId' => $edit_form_id,
            'dioceseList' => $diocese_list,
        ]);

    }


    /**
     * get data for item with ID $id and pass $index
     * @Route("/edit/diocese/item/{id}/{index}", name="edit_diocese_item")
     */
    public function _item(int $id,
                          int $index,
                          EntityManagerInterface $entityManager): Response {

        $dioceseRepository = $entityManager->getRepository(Diocese::class);
        $userWiagRepository = $entityManager->getRepository(UserWiag::class);

        $diocese = $dioceseRepository->find($id);
        $diocese->getItem()->setFormIsExpanded(1);

        $item = $diocese->getItem();
        $user = $userWiagRepository->find($item->getChangedBy());
        $item->setChangedByUser($user);

        $this->setDisplayData($diocese, $entityManager);

        $edit_form_id = 'diocese_edit_form';

        return $this->render('edit_diocese/_input_content.html.twig', [
            'diocese' => $diocese,
            'editFormId' => $edit_form_id,
            'itemIndex' => $index,
            'base_id' => $edit_form_id.'_'.$index,
            'base_input_name' => $edit_form_id.'['.$index.']',
        ]);

    }

    /**
     * @return template for new diocese
     *
     * @Route("/edit/diocese/new-diocese", name="edit_diocese_new_diocese")
     */
    public function newDiocese(Request $request,
                            EntityManagerInterface $entityManager) {

        $current_user_id = $this->getUser()->getId();
        $obj = new Diocese($current_user_id);
        $obj->getItem()->setFormIsExpanded(true);

        $this->setDisplayData($obj, $entityManager);

        return $this->render('edit_diocese/_item.html.twig', [
            'editFormId' => $request->query->get('edit_form_id'),
            'current_idx' => $request->query->get('current_idx'),
            'diocese' => $obj,
        ]);

    }

    /**
     * @return template for new external ID
     *
     * @Route("/edit/diocese/new-url-external/{itemIndex}", name="edit_diocese_new_urlexternal")
     */
    public function newUrlExternal(Request $request,
                                   int $itemIndex) {

        $edit_form_id = 'diocese_edit_form';

        $urlExternal = new UrlExternal();

        return $this->render('edit_diocese/_input_url_external.html.twig', [
            'editFormId' => $edit_form_id,
            'itemIndex' => $itemIndex,
            'currentIndex' => $request->query->get('current_idx'),
            'urlext' => $urlExternal,
            'isLast' => true,
        ]);

    }

    /**
     * @return template for new skos label
     *
     * @Route("/edit/diocese/new-skos-label/{itemIndex}", name="edit_diocese_new_skos_label")
     */
    public function newSkosLabel(Request $request,
                                   int $itemIndex) {

        $edit_form_id = 'diocese_edit_form';

        $skos_label = new SkosLabel(Diocese::SKOS_SCHEME_ID);

        return $this->render('edit_diocese/_input_skos_label.html.twig', [
            'editFormId' => $edit_form_id,
            'itemIndex' => $itemIndex,
            'currentIndex' => $request->query->get('current_idx'),
            'skosLabel' => $skos_label,
            'isLast' => true,
        ]);

    }

}
