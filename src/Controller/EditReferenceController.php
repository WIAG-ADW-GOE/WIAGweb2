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

        return $this->renderForm('edit_reference/select.html.twig', [
            'menuItem' => 'edit-menu',
            'form' => $form,
            'editFormId' => $edit_form_id,
            'itemTypeId' => $item_type_id,
            'referenceList' => $reference_list,
        ]);
    }

    /**
     * @Route("/edit/reference/save", name="edit_reference_save")
     */
    public function save(Request $request,
                         EntityManagerInterface $entityManager,
                         UtilService $utilService) {

        $edit_form_id = 'reference_edit_form';
        $form_data = $request->request->get($edit_form_id);

        // validation?
        $error_flag = false;

        $form_display_type = $request->request->get('formType');

        // save data
        if (!$error_flag) {
            $referenceRepository = $entityManager->getRepository(ReferenceVolume::class);
            $reference_list = array();
            foreach($form_data as $data) {
                $id = $data['id'];
                if ($id > 0) {
                    $reference = $referenceRepository->find($id);
                    $reference_list[] = $reference;
                }
                if (isset($data['formIsEdited'])) {
                    if (!$id > 0) {
                        // new entry
                        $reference = new ReferenceVolume();
                        $entityManager->persist($reference);
                        $reference_list[] = $reference;
                    }
                    $utilService->setByKeys(
                        $reference,
                        $data,
                        ReferenceVolume::EDIT_FIELD_LIST);
                }
                $formIsExpanded = isset($data['formIsExpanded']) ? 1 : 0;
                $reference->setFormIsExpanded($formIsExpanded);
            }

            if ($form_display_type == "new_entry") {
                $reference = new ReferenceVolume();
                $reference_list[] = $reference;
            }
        }

        $entityManager->flush();

        $template = "";
        if ($form_display_type == 'list') {
            $template = 'edit_reference/_list.html.twig';
        } else { // useful for debugging: dump output is accessible
            $template = 'edit_reference/select.html.twig';
        }

        return $this->renderForm($template, [
            'menuItem' => 'edit-menu',
            'editFormId' => $edit_form_id,
            'referenceList' => $reference_list,
            'formType' => $form_display_type,
        ]);

    }

    /**
     * @Route("/edit/reference/new", name="edit_reference_new")
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        UtilService $utilService) {

        $edit_form_id = 'reference_edit_form';


        $reference_list = array(new ReferenceVolume());


        return $this->renderForm('edit_reference/new_reference.html.twig', [
            'menuItem' => 'edit-menu',
            'editFormId' => $edit_form_id,
            'referenceList' => $reference_list,
            'formType' => 'new_entry',
            'count' => count($reference_list),
        ]);



    }


}
