<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Entity\ReferenceVolume;
use App\Entity\InputError;

use App\Service\UtilService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use Doctrine\ORM\EntityManagerInterface;


class EditReferenceController extends AbstractController {

    /**
     * @Route("/edit/reference", name="edit_reference")
     */
    public function list(Request $request,
                         EntityManagerInterface $entityManager): Response {
        $edit_form_id = 'reference_edit_form';

        // default
        $item_type_id = Item::ITEM_TYPE_ID['Domherr']['id'];
        $model = ['itemType' => $item_type_id]; // set default

        $itemTypeRepository = $entityManager->getRepository(ItemType::class);

        $choices = [
            'Bischof'    => Item::ITEM_TYPE_ID['Bischof']['id'],
            'Domherr'    => Item::ITEM_TYPE_ID['Domherr']['id'],
            'Bistum'     => Item::ITEM_TYPE_ID['Bistum']['id'],
            'Bischof GS' => Item::ITEM_TYPE_ID['Bischof GS']['id'],
            'GS-Bände' => Item::ITEM_TYPE_ID['Domherr GS']['id'],
            'Priester Utrecht' => Item::ITEM_TYPE_ID['Priester Utrecht']['id'],
        ];

        $form = $this->createFormBuilder($model)
                     ->setMethod('GET')
                     ->add('itemType', ChoiceType::class, [
                         'label' => 'Gegenstandstyp',
                         'choices' => $choices,
                     ])
                     ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            $item_type_id = $data['itemType'];
            // dump($request->getUri());
        }

        $referenceRepository = $entityManager->getRepository(ReferenceVolume::class);

        $reference_list = $referenceRepository->findBy(
            ['itemTypeId' => $item_type_id],
        );

        // sort null last
        $reference_list = UtilService::sortByFieldList($reference_list, ['displayOrder', 'id']);

        $emptyReference = new ReferenceVolume();
        $emptyReference->setItemTypeId($item_type_id);

        return $this->renderForm('edit_reference/select.html.twig', [
            'menuItem' => 'edit-menu',
            'form' => $form,
            'editFormId' => $edit_form_id,
            'itemTypeId' => $item_type_id,
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
            $item_type_id = $data['itemTypeId'];
            $formIsExpanded = isset($data['formIsExpanded']) ? 1 : 0;
            if ($id > 0) {
                $reference = $reference_list[$id];
                $reference->setFormIsExpanded($formIsExpanded);
            }
            if (isset($data['formIsEdited'])) {
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
                    $reference->setReferenceId($max_reference_id + 1);
                    $max_reference_id += 1;
                    $entityManager->persist($reference);
                }
            }
            $entityManager->flush();
        }

        // displayOrder may have been edited
        // sort null last
        $reference_list = UtilService::sortByFieldList($reference_list, ['displayOrder', 'id']);

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
