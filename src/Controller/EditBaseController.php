<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Entity\ItemPropertyType;
use App\Entity\RolePropertyType;
use App\Entity\InputError;
use App\Entity\Role;
use App\Form\Model\PropertyTypeFormModel;

use App\Service\UtilService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use Doctrine\ORM\EntityManagerInterface;


class EditBaseController extends AbstractController {

    /**
     * @Route("/edit/prop", name="edit_prop")
     */
    public function propertyList(Request $request,
                                 EntityManagerInterface $entityManager): Response {
        $edit_form_id = 'prop_edit_form';

        $item_type_id = Item::ITEM_TYPE_ID['Bischof']['id'];
        $model = ['itemType' => $item_type_id]; // set default

        $itemTypeRepository = $entityManager->getRepository(ItemType::class);
        $itemType_list = $itemTypeRepository->findAll();
        $choices = array();
        foreach($itemType_list as $t_loop) {
            $choices [$t_loop->getName()] = $t_loop->getId();
        }

        $form = $this->createFormBuilder($model)
                     ->add('itemType', ChoiceType::class, [
                         'label' => 'Gegenstandstyp',
                         'choices' => $choices,
                     ])
                     ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            $item_type_id = $data['itemType'];
        }


        $itemPropertyTypeRepository = $entityManager->getRepository(ItemPropertyType::class);
        $item_property_type_list = $itemPropertyTypeRepository->findByItemTypeId($item_type_id);
        $rolePropertyTypeRepository = $entityManager->getRepository(RolePropertyType::class);
        $role_property_type_list = $rolePropertyTypeRepository->findByItemTypeId($item_type_id);

        return $this->renderForm('edit_base/property.html.twig', [
            'menuItem' => 'edit-menu',
            'form' => $form,
            'controller_name' => 'EditBaseController',
            'editFormId' => $edit_form_id,
            'itemTypeId' => $item_type_id,
            'itemPropertyTypeList' => $item_property_type_list,
            'rolePropertyTypeList' => $role_property_type_list,
        ]);
    }

    /**
     * @Route("/edit/prop/save", name="edit_prop_save")
     */
    public function propertySave(Request $request,
                                 EntityManagerInterface $entityManager,
                                 UtilService $utilService) {

        $edit_form_id = 'prop_edit_form';
        $form_data = $request->request->get($edit_form_id);

        $item_type_id = $form_data['itemTypeId'];

        /* map/validate form */
        // the properties are not linked to items yet

        $itemPropertyTypeRepository = $entityManager->getRepository(ItemPropertyType::class);
        $rolePropertyTypeRepository = $entityManager->getRepository(RolePropertyType::class);

        // item properties
        $property_list = array();
        $error_flag = false;
        foreach($form_data['itemProp'] as $data) {
            $prop_id = $data['id'];
            if (isset($data['formIsEdited'])) {
                $property = new PropertyTypeFormModel();
                $property_list[] = $property;
                $utilService->setByKeys($property, $data, ['id', 'name', 'label', 'comment']);
                if (is_null($property->name)) {
                    $msg = "Das Feld 'Name' darf nicht leer sein.";
                    $property->inputError->add(new InputError('global', $msg));
                    $error_flag = true;
                }
                if (is_null($property->label)) {
                    $msg = "Das Feld 'Anzeige' darf nicht leer sein.";
                    $property->inputError->add(new InputError('global', $msg));
                    $error_flag = true;
                }
            } elseif ($prop_id > 0) {
                $property = $itemPropertyTypeRepository->find($prop_id);
                $property_list[] = $property;
            }
        }

        $item_property_type_list = $property_list;

        // role properties
        $property_list = array();
        foreach($form_data['roleProp'] as $data) {
            $prop_id = $data['id'];
            if (isset($data['formIsEdited'])) {
                $property = new PropertyTypeFormModel();
                $property_list[] = $property;
                $utilService->setByKeys($property, $data, ['id', 'name', 'label', 'comment']);
                if (is_null($property->name)) {
                    $msg = "Das Feld 'Name' darf nicht leer sein.";
                    $property->inputError->add(new InputError('global', $msg));
                    $error_flag = true;
                }
                if (is_null($property->label)) {
                    $msg = "Das Feld 'Anzeige' darf nicht leer sein.";
                    $property->inputError->add(new InputError('global', $msg));
                    $error_flag = true;
                }
            } elseif ($prop_id > 0) {
                $property = $rolePropertyTypeRepository->find($prop_id);
                $property_list[] = $property;
            }
        }

        $role_property_type_list = $property_list;

        // save data
        if (!$error_flag) {
            // item properties
            // rebuild property list
            $property_list = array();
            foreach($form_data['itemProp'] as $data) {
                $prop_id = $data['id'];
                if ($prop_id > 0) {
                    $property = $itemPropertyTypeRepository->find($prop_id);
                    $property_list[] = $property;
                }
                if (isset($data['formIsEdited'])) {
                    if (!$prop_id > 0) {
                        // new entry
                        $property = new ItemPropertyType();
                        $property->setItemTypeId($item_type_id);
                        $entityManager->persist($property);
                        $property_list[] = $property;
                    }
                    $utilService->setByKeys($property, $data, ['name', 'label', 'comment']);
                }
            }

            $item_property_type_list = $property_list;
            // role properties
            // rebuild property list
            $property_list = array();
            foreach($form_data['roleProp'] as $data) {
                $prop_id = $data['id'];
                if ($prop_id > 0) {
                    $property = $rolePropertyTypeRepository->find($prop_id);
                    $property_list[] = $property;
                }
                if (isset($data['formIsEdited'])) {
                    if (!$prop_id > 0) {
                        // new entry
                        $property = new RolePropertyType();
                        $property->setItemTypeId($item_type_id);
                        $entityManager->persist($property);
                        $property_list[] = $property;
                    }
                    $utilService->setByKeys($property, $data, ['name', 'label', 'comment']);
                }
            }

            $role_property_type_list = $property_list;

            $entityManager->flush();

        }

        $template = "";
        if ($request->query->get('listOnly')) {
            $template = 'edit_base/_edit_prop_form.html.twig';
        } else { // useful for debugging: dump output is accessible
            $template = 'edit_base/edit_property_list.html.twig';
        }

        return $this->renderForm($template, [
            'menuItem' => 'edit-menu',
            'editFormId' => $edit_form_id,
            'itemTypeId' => $item_type_id,
            'itemPropertyTypeList' => $item_property_type_list,
            'rolePropertyTypeList' => $role_property_type_list,
        ]);

    }

}
