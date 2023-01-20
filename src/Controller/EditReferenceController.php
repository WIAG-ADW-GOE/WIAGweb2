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
            'Domherr GS' => Item::ITEM_TYPE_ID['Domherr GS']['id'],
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
            ['displayOrder' => 'ASC']
        );


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
        foreach($form_data as $data) {
            $id = $data['id'];
            $item_type_id = $data['itemTypeId'];
            $formIsExpanded = isset($data['formIsExpanded']) ? 1 : 0;
            if ($id > 0) {
                $reference = $referenceRepository->find($id);
                $reference->setFormIsExpanded($formIsExpanded);
                $reference_list[] = $reference;
            }
            if (isset($data['formIsEdited'])) {
                if (!$id > 0) {
                    // new entry
                    $reference = new ReferenceVolume();
                    $reference->setFormIsExpanded($formIsExpanded);
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

        // dd(count($reference_list), $form_data);

        // $form_display_type = $request->request->get('formType');

        $max_reference_id = UtilService::maxInList($reference_list, 'referenceId', 0);

        // 2023-01-20 leave it to the editors to set field display_order
        // $max_display_order = UtilService::maxInList($reference_list, 'displayOrder', 0);

        // save data
        // - default
        $item_type_id = Item::ITEM_TYPE_ID['Domherr']['id'];
        if (!$error_flag) {
            $reference_list = array();
            foreach($form_data as $data) {
                $id = $data['id'];
                $item_type_id = $data['itemTypeId'];
                $formIsExpanded = isset($data['formIsExpanded']) ? 1 : 0;
                if ($id > 0) {
                    $reference = $referenceRepository->find($id);
                    $reference->setFormIsExpanded($formIsExpanded);
                    $reference_list[] = $reference;
                }
                if (isset($data['formIsEdited'])) {
                    if (!$id > 0) {
                        // new entry
                        $reference = new ReferenceVolume();
                        $reference->setItemTypeId(0); // will be overwritten

                        $reference->setReferenceId($max_reference_id + 1);
                        $max_reference_id += 1;

                        $entityManager->persist($reference);
                        $entityManager->flush();
                        $id = $reference->getId();
                        $reference = $referenceRepository->find($id);
                        $reference->setFormIsExpanded($formIsExpanded);
                        $reference_list[] = $reference;
                    }
                    UtilService::setByKeys(
                        $reference,
                        $data,
                        ReferenceVolume::EDIT_FIELD_LIST);
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

        return $this->renderForm($template, [
            'menuItem' => 'edit-menu',
            'editFormId' => $edit_form_id,
            'referenceList' => $reference_list,
            'emptyReference' => $emptyReference,
        ]);

    }

}
