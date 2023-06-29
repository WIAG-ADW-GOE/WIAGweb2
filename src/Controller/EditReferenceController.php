<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Entity\ReferenceVolume;
use App\Entity\InputError;
use App\Entity\ItemReference;

use App\Service\UtilService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use Doctrine\ORM\EntityManagerInterface;


class EditReferenceController extends AbstractController {

    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/reference-suggest/entry", name="reference_suggest_entry")
     */
    public function autocomplete(Request $request,
                                 EntityManagerInterface $entityManager): Response {
        $query_param = $request->query->get('q');

        $referenceRepository = $entityManager->getRepository(ReferenceVolume::class);
        $suggestions = $referenceRepository->suggestEntry($query_param);

        return $this->render('person/_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }


    /**
     * @Route("/edit/reference/query", name="edit_reference_query")
     */
    public function query(Request $request,
                          EntityManagerInterface $entityManager): Response {

        $item_type_choices = [
            '- alle -' => '',
            'Bischof/Domherr' => '4, 5',
            'GS-Bände' => '6, 9',
            'Bistum' => '1',
            'Priester Utrecht' => '10',
        ];

        $default_item_type = $item_type_choices['- alle -'];

        $sort_by_choices = [
            'ID' => 'referenceId',
            'GS Zitation' => 'gsCitation',
            'Kurztitel' => 'titleShort',
            'Anzeigereihenfolge' => 'displayOrder',
        ];

        $model = [
            'itemType' => '',
            'sortBy' => 'referenceId',
            'searchText' => '',
        ];

        $form = $this->createFormBuilder($model)
                     ->setMethod('GET')
                     ->add('itemType', ChoiceType::class, [
                         'label' => 'Thema',
                         'choices' => $item_type_choices,
                         'required' => false,
                     ])
                     ->add('searchText', TextType::class, [
                         'label' => 'Suchtext',
                         'required' => false,
                         'attr' => [
                             'placeholder' => 'Autor/Titel'
                         ],
                     ])
                     ->add('sortBy', ChoiceType::class, [
                         'label' => 'Sortierung',
                         'choices' => $sort_by_choices,
                     ])
                     ->getForm();

        $form->handleRequest($request);
        $model = $form->getData();

        $reference_list = array();
        if ($form->isSubmitted() && $form->isValid()) {
        } else {
            $model['itemType'] = $default_item_type;
        }

        $referenceRepository = $entityManager->getRepository(ReferenceVolume::class);

        $reference_list = $referenceRepository->findByModel($model);

        // sort null last
        $sort_criteria = array();
        if ($model['sortBy'] != '') {
            $sort_criteria[] = $model['sortBy'];
        }
        $sort_criteria[] = 'id';
        $reference_list = UtilService::sortByFieldList($reference_list, $sort_criteria);

        $item_type_id = '';
        if ($model['itemType'] != '- alle -') {
            $item_type_cand = explode(' ,', $model['itemType']);
            $item_type_id = $item_type_cand[0];
        }

        $template = 'edit_reference/query.html.twig';
        $edit_form_id = 'reference_edit_form';

        return $this->renderForm($template, [
            'menuItem' => 'edit-menu',
            'form' => $form,
            'editFormId' => $edit_form_id,
            'referenceList' => $reference_list,
        ]);

    }

    /**
     * @Route("/edit/reference/save", name="edit_reference_save")
     */
    public function save(Request $request,
                         EntityManagerInterface $entityManager) {

        $edit_form_id = 'reference_edit_form';
        $form_data = $request->request->get($edit_form_id) ?? array();

        $referenceRepository = $entityManager->getRepository(ReferenceVolume::class);
        $itemReferenceRepository = $entityManager->getRepository(ItemReference::class);

        // validation

        $error_flag = false;

        $reference_list = array();

        $id_list = array_column($form_data, 'id');
        $query_result = $referenceRepository->findList($id_list);
        foreach ($query_result as $reference) {
            $reference_list[$reference->getId()] = $reference;
        }

        // - default
        // reference_volume.item_type_id is obsolete 2023-05-05
        $item_type_id = 0;

        foreach($form_data as $data) {
            $id = $data['id'];
            $form_is_expanded = isset($data['formIsExpanded']) ? 1 : 0;
            if ($id > 0) {
                $reference = $reference_list[$id];
                $reference->setFormIsExpanded($form_is_expanded);
            }
            if (isset($data['isEdited'])) {
                if (!$id > 0) {
                    // new entry
                    $form_is_expanded = 1;
                    $reference = new ReferenceVolume();
                    $reference->setFormIsExpanded($form_is_expanded);
                    $reference->setItemTypeId($item_type_id);
                    $reference_list[] = $reference;
                }
                if (UtilService::missingKeyList($data, ReferenceVolume::EDIT_FIELD_LIST) == array()) {
                    $referenceCount = $itemReferenceRepository->referenceCount($reference->getReferenceId());
                    $reference->setReferenceCount($referenceCount);

                    $is_online = isset($data['isOnline']) ? 1 : 0;
                    $reference->setIsOnline($is_online);
                    UtilService::setByKeys(
                        $reference,
                        $data,
                        ReferenceVolume::EDIT_FIELD_LIST);
                    if (trim($reference->getFullCitation()) == "") {
                        $msg = "Bitte das Feld 'Titel' ausfüllen.";
                        $reference->getInputError()->add(new InputError('general', $msg, 'error'));
                        $error_flag = true;
                    }
                    if (trim($reference->getTitleShort()) == "") {
                        $msg = "Bitte das Feld 'Kurztitel' ausfüllen.";
                        $reference->getInputError()->add(new InputError('general', $msg, 'error'));
                        $error_flag = true;
                    }

                    if (trim($reference->getGSCitation()) == "") {
                        $msg = "Bitte das Feld 'GS Zitation' ausfüllen.";
                        $reference->getInputError()->add(new InputError('general', $msg, 'error'));
                        $error_flag = true;
                    }
                }
                else { // only item.display_order is accessible
                    UtilService::setByKeys(
                        $reference,
                        $data,
                        ['displayOrder']);
                }
            }
        }

        // save

        if (!$error_flag) {
            foreach ($reference_list as $reference) {
                $id = $reference->getId();
                if ($id < 1) {
                    $next_id = $referenceRepository->nextId();
                    $reference->setReferenceId($next_id);
                    $entityManager->persist($reference);
                }
            }
            $entityManager->flush();
        }

        $template = 'edit_reference/_list.html.twig';

        $debug = $request->request->get('formType');
        if (!is_null($debug) && $debug == "debug") {
            $template = 'edit_reference/debug_list.html.twig';
        }

        return $this->render($template, [
            'editFormId' => $edit_form_id,
            'referenceList' => $reference_list,
            'itemTypeId' => $item_type_id,
        ]);

    }

    /**
     *
     * @Route("/edit/reference/delete/{q_id}", name="edit_reference_delete")
     */
    public function deleteEntry(Request $request,
                                int $q_id,
                                EntityManagerInterface $entityManager) {
        $edit_form_id = 'reference_edit_form';
        $form_data = $request->request->get($edit_form_id);

        $referenceRepository = $entityManager->getRepository(ReferenceVolume::class);

        // validation
        $error_flag = false;

        $id_list = array_column($form_data, 'id');
        $query_result = $referenceRepository->findList($id_list);
        $reference_list = array();
        foreach ($query_result as $reference) {
            $reference_list[$reference->getId()] = $reference;
        }

        // - default
        // reference_volume.item_type_id is obsolete 2023-05-05
        $item_type_id = 0;

        // deletion takes priority: all other edit data are lost and sub-forms are closed
        foreach ($reference_list as $reference) {
            $id_loop = $reference->getId();
            if ($id_loop == $q_id) {
                $entityManager->remove($reference);
            }
        }

        $entityManager->flush();

        $query_result = $referenceRepository->findList($id_list);
        $reference_list = array();
        foreach ($query_result as $reference) {
            $reference_list[$reference->getId()] = $reference;
        }

        $template = 'edit_reference/_list.html.twig';

        return $this->render($template, [
            'editFormId' => $edit_form_id,
            'referenceList' => $reference_list,
            'itemTypeId' => $item_type_id,
        ]);

    }


    /**
     * get data for item with ID $id and pass $index
     * @Route("/edit/reference/item/{id}/{index}", name="edit_reference_item")
     */
    public function _item(int $id,
                          int $index,
                          EntityManagerInterface $entityManager): Response {
        $referenceRepository = $entityManager->getRepository(ReferenceVolume::class);
        $itemReferenceRepository = $entityManager->getRepository(ItemReference::class);

        $reference = $referenceRepository->find($id);
        $reference->setFormIsExpanded(1);

        $referenceCount = $itemReferenceRepository->referenceCount($reference->getReferenceId());
        $reference->setReferenceCount($referenceCount);

        $edit_form_id = 'reference_edit_form';

        return $this->render('edit_reference/_input_content.html.twig', [
            'reference' => $reference,
            'editFormId' => $edit_form_id,
            'current_idx' => $index,
            'base_id' => $edit_form_id.'_'.$index,
            'base_input_name' => $edit_form_id.'['.$index.']',
        ]);

    }

    /**
     * @return template for new reference
     *
     * @Route("/edit/reference/new-reference", name="edit_reference_new_reference")
     */
    public function newReference(Request $request) {

        $item_type_id = $request->query->get('item_type_id');
        $ref = new ReferenceVolume();
        $ref->setItemTypeId($item_type_id);
        $ref->setIsOnline(0);
        $ref->setFormIsExpanded(true);

        // property types

        return $this->render('edit_reference/_item.html.twig', [
            'editFormId' => $request->query->get('edit_form_id'),
            'current_idx' => $request->query->get('current_idx'),
            'reference' => $ref,
        ]);

    }


}
