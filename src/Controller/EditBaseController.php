<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemProperty;
use App\Entity\ItemPropertyType;
use App\Entity\InputError;
use App\Entity\Role;
use App\Form\Model\PropertyTypeFormModel;

use App\Service\UtilService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use Doctrine\ORM\EntityManagerInterface;


class EditBaseController extends AbstractController {

    #[Route(path: '/edit/prop', name: 'edit_prop')]
    public function propertyList(Request $request,
                                 EntityManagerInterface $entityManager): Response {
        $this->denyAccessUnlessGranted('ROLE_EDIT_BASE');

        $edit_form_id = 'prop_edit_form';

        $model = ['sortBy' => 'name']; // set default

        $itemPropertyTypeRepository = $entityManager->getRepository(ItemPropertyType::class);
        $itemPropertyRepository = $entityManager->getRepository(ItemProperty::class);

        $choices = [
            'ID' => 'id',
            'Name' => 'name',
            'Reihenfolge' => 'displayOrder',
        ];

        $form = $this->createFormBuilder($model)
                     ->add('sortBy', ChoiceType::class, [
                         'label' => 'Sortierung',
                         'choices' => $choices,
                     ])
                     ->getForm();

        $form->handleRequest($request);
        $model = $form->getData();

        $item_property_type_list = $itemPropertyTypeRepository->findAll();
        $this->setReferenceCount($item_property_type_list, $itemPropertyRepository);

        $sortBy = $model['sortBy'];
        $item_property_type_list = UtilService::sortByFieldList($item_property_type_list, [$sortBy]);

        return $this->render('edit_base/property.html.twig', [
            'menuItem' => 'edit-menu',
            'form' => $form,
            'editFormId' => $edit_form_id,
            'itemPropertyTypeList' => $item_property_type_list,
            'nextId' => $itemPropertyTypeRepository->nextId(),
        ]);
    }

    #[Route(path: '/edit/prop/save', name: 'edit_prop_save')]
    public function propertySave(Request $request,
                                 EntityManagerInterface $entityManager,
                                 UtilService $utilService) {

        $edit_form_id = 'prop_edit_form';
        $form_data = $request->request->all($edit_form_id);

        /* map/validate form */
        // the properties are not linked to items yet

        $itemPropertyTypeRepository = $entityManager->getRepository(ItemPropertyType::class);
        $itemPropertyRepository = $entityManager->getRepository(ItemProperty::class);

        // item properties
        $property_list = array();
        $error_flag = false;
        foreach($form_data['itemProp'] as $data) {
            $is_edited = array_key_exists('formIsEdited', $data);
            $is_new = array_key_exists('isNew', $data);
            $do_delete = (array_key_exists('deleteFlag', $data) and ($data['deleteFlag'] == 'delete'));

            $prop_id = $data['id'];
            if ($is_edited and $is_new) {
                // new entry
                $property = new ItemPropertyType();
                $property->setId($prop_id);
                $property->setIsNew($is_new);
                $property_list[] = $property;
            }

            if (!$is_new) {
                $prop_id = $data['id'];
                $property = $itemPropertyTypeRepository->find($prop_id);
                $property_list[] = $property;
            }

            if ($is_edited) {
                $utilService->setByKeys($property, $data, ['name', 'displayOrder', 'comment']);
                $property->setDeleteFlag($do_delete ? "delete" : "");
                if (!$do_delete) {
                    if (is_null($property->getName())) {
                        $msg = "Das Feld 'Name' darf nicht leer sein.";
                        $property->getInputError()->add(new InputError('global', $msg));
                        $error_flag = true;
                    }
                    $display_order = $property->getDisplayOrder();
                    if (!is_null($display_order) and (!intval($display_order) > 0)) {
                        $msg = "Das Feld 'Reihenfolge' muss eine positive Zahl enthalten.";
                        $property->getInputError()->add(new InputError('global', $msg));
                        $error_flag = true;
                    }
                }
                $property->setIsEdited($is_edited);
            }
        }

        // save data
        if (!$error_flag) {
            // item properties
            // rebuild property list
            $property_list_2 = array();
            foreach($property_list as $property) {
                if ($property->getIsNew()) { // a new entry can not be deleted
                    $entityManager->persist($property);
                    $property->setIsNew(false);
                }
                if ($property->getDeleteFlag() == "delete") {
                    $entityManager->remove($property);
                } else {
                    $property_list_2[] = $property;
                }

                $property->setIsEdited(false);
            }
            $entityManager->flush();
        } else {
            $property_list_2 = $property_list;
        }


        $this->setReferenceCount($property_list_2, $itemPropertyRepository);

        $template = 'edit_base/_edit_prop_form.html.twig';

        return $this->render($template, [
            'menuItem' => 'edit-menu',
            'editFormId' => $edit_form_id,
            'itemPropertyTypeList' => $property_list_2,
            'nextId' => $itemPropertyTypeRepository->nextId(),
        ]);

    }

    private function setReferenceCount($type_list, $repository) {
        foreach($type_list as $type_loop) {
            $q_ref_count = $repository->referenceCount($type_loop->getId());
            if (!is_null($q_ref_count)) {
                $type_loop->setReferenceCount($q_ref_count);
            }
        }
        return $type_list;
    }

}
