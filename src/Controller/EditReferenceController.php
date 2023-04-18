<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Entity\ReferenceVolume;
use App\Entity\InputError;
use App\Form\Model\ReferenceQueryFormModel;

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

        $sort_by_choices = [
            'ID' => 'referenceId',
            'Titel' => 'fullCitation',
            'Autorenschaft' => 'authorEditor',
            'Kurztitel' => 'titleShort',
            'Anzeigereihenfolge' => 'displayOrder',
        ];

        $model = [
            'itemType' => '4, 5',
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
            $model['itemType'] = '4, 5';
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
        $emptyReference = new ReferenceVolume();
        $emptyReference->setItemTypeId($item_type_id);

        $template = 'edit_reference/query.html.twig';
        $edit_form_id = 'reference_edit_form';

        return $this->renderForm($template, [
            'menuItem' => 'edit-menu',
            'form' => $form,
            'editFormId' => $edit_form_id,
            'referenceList' => $reference_list,
            'emptyReference' => $emptyReference,
        ]);

    }

    /**
     * @Route("/edit/reference/save", name="edit_reference_save")
     */
    public function save(Request $request,
                         EntityManagerInterface $entityManager) {

        $edit_form_id = 'reference_edit_form';
        $form_data = $request->request->get($edit_form_id);

        $referenceRepository = $entityManager->getRepository(ReferenceVolume::class);

        // validation

        $error_flag = false;

        $reference_list = array();

        $id_list = array_column($form_data, 'id');
        $query_result = $referenceRepository->findList($id_list);
        foreach ($query_result as $reference) {
            $reference_list[$reference->getId()] = $reference;
        }

        // - default
        $item_type_id = Item::ITEM_TYPE_ID['Domherr']['id'];

        foreach($form_data as $data) {
            $id = $data['id'];
            $formIsExpanded = isset($data['formIsExpanded']) ? 1 : 0;
            if ($id > 0) {
                $reference = $reference_list[$id];
                $reference->setFormIsExpanded($formIsExpanded);
            }
            if (isset($data['formIsEdited'])) {
                $item_type_id = $data['itemTypeId'];
                if (!$id > 0) {
                    // new entry
                    $reference = new ReferenceVolume();
                    $reference->setFormIsExpanded($formIsExpanded);
                    $reference->setItemTypeId($item_type_id);
                    $reference_list[] = $reference;
                }
                UtilService::setByKeys(
                    $reference,
                    $data,
                    ReferenceVolume::EDIT_FIELD_LIST);
                if (trim($reference->getFullCitation()) == "") {
                    $msg = "Bitte das Feld 'Titel' ausfüllen.";
                    $reference->getInputError()->add(new InputError('general', $msg, 'error'));
                    $error_flag = true;
                }
            }
        }

        // save
        $max_reference_id = UtilService::maxInList($reference_list, 'referenceId', 0);

        if (!$error_flag) {
            foreach ($reference_list as $reference) {
                $id = $reference->getId();
                if ($id < 1) {
                    $new_item_type_id = $reference->getItemTypeId();
                    $next_id = $referenceRepository->nextId($new_item_type_id);
                    $reference->setReferenceId($next_id);
                    $max_reference_id += 1;
                    $entityManager->persist($reference);
                }
            }
            $entityManager->flush();
        }


        // create empty template for new entries
        $emptyReference = new ReferenceVolume();
        $emptyReference->setItemTypeId($item_type_id);


        $template = 'edit_reference/_list.html.twig';

        $debug = $request->request->get('formType');
        if (!is_null($debug) && $debug == "debug") {
            $template = 'edit_reference/debug_list.html.twig';
        }

        return $this->render($template, [
            'menuItem' => 'edit-menu',
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

        $reference = $referenceRepository->find($id);
        $reference->setFormIsExpanded(1);
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
        $ref->setFormIsExpanded(true);

        // property types

        return $this->render('edit_reference/_item.html.twig', [
            'editFormId' => $request->query->get('edit_form_id'),
            'current_idx' => $request->query->get('current_idx'),
            'reference' => $ref,
        ]);

    }


}
