<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\Authority;
use App\Entity\InputError;
use App\Entity\UrlExternal;

use App\Service\UtilService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use Doctrine\ORM\EntityManagerInterface;


class EditAuthorityController extends AbstractController {
    const HINT_SIZE = 12;

    /**
     * @Route("/edit/authority", name="edit_authority")
     */
    public function list(Request $request,
                         EntityManagerInterface $entityManager): Response {
        $edit_form_id = 'authority_edit_form';

        $authorityRepository = $entityManager->getRepository(Authority::class);

        $authority_list = $authorityRepository->findAll();

        // sort null last
        $authority_list = UtilService::sortByFieldList($authority_list, ['id']);

        $emptyAuthority = new Authority();

        return $this->renderForm('edit_authority/home.html.twig', [
            'menuItem' => 'edit-menu',
            'editFormId' => $edit_form_id,
            'authorityList' => $authority_list,
            'emptyAuthority' => $emptyAuthority,
        ]);
    }

    /**
     * @Route("/edit/authority/save", name="edit_authority_save")
     */
    public function save(Request $request,
                         EntityManagerInterface $entityManager) {

        $edit_form_id = 'authority_edit_form';
        $form_data = $request->request->get($edit_form_id);

        $authorityRepository = $entityManager->getRepository(Authority::class);
        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class);

        // validation

        $error_flag = false;

        $authority_list = array();

        $id_list = array_column($form_data, 'id');
        $query_result = $authorityRepository->findList($id_list);
        foreach ($query_result as $authority) {
            $authority_list[$authority->getId()] = $authority;
        }

        foreach($form_data as $data) {
            $id = $data['id'];
            $form_is_expanded = isset($data['formIsExpanded']) ? 1 : 0;
            if ($id > 0) {
                $authority = $authority_list[$id];
                $authority->setFormIsExpanded($form_is_expanded);
            }
            if (isset($data['formIsEdited'])) {
                if (!$id > 0) {
                    // new entry
                    $form_is_expanded = 1;
                    $authority = new Authority();
                    $authority->setFormIsExpanded($form_is_expanded);
                    $authority_list[] = $authority;
                }
                if ($form_is_expanded) {
                    $referenceCount = $urlExternalRepository->referenceCount($authority->getId());
                    $authority->setReferenceCount($referenceCount);

                    UtilService::setByKeys(
                        $authority,
                        $data,
                        Authority::EDIT_FIELD_LIST);
                    if (trim($authority->getUrlNameFormatter()) == "") {
                        $msg = "Bitte das Feld 'Name' ausfüllen.";
                        $authority->getInputError()->add(new InputError('general', $msg, 'error'));
                        $error_flag = true;
                    }
                    if (trim($authority->getUrlType()) == "") {
                        $msg = "Bitte das Feld 'Typ' ausfüllen.";
                        $authority->getInputError()->add(new InputError('general', $msg, 'error'));
                        $error_flag = true;
                    }
                    if (trim($authority->getUrlFormatter()) == "") {
                        $msg = "Bitte das Feld 'Basis-URL' ausfüllen.";
                        $authority->getInputError()->add(new InputError('general', $msg, 'error'));
                        $error_flag = true;
                    }
                } else { // only item.display_order is accessible
                    UtilService::setByKeys(
                        $authority,
                        $data,
                        ['displayOrder']);
                }
            }
        }

        // save
        // 2023-03-28 this is not relevant here, DB sets the ID automatically
        $max_authority_id = UtilService::maxInList($authority_list, 'id', 0);

        if (!$error_flag) {
            foreach ($authority_list as $authority) {
                $id = $authority->getId();
                if (!$id > 0) {
                    $max_authority_id += 1;
                    $entityManager->persist($authority);
                }
            }
            $entityManager->flush();
        }


        $authority_list = UtilService::sortByFieldList($authority_list, ['id']);

        // create empty template for new entries
        $emptyAuthority = new Authority();

        $template = 'edit_authority/_list.html.twig';

        $debug = $request->request->get('formType');
        if (!is_null($debug) && $debug == "debug") {
            $template = 'edit_authority/debug_list.html.twig';
        }

        return $this->render($template, [
            'menuItem' => 'edit-menu',
            'editFormId' => $edit_form_id,
            'authorityList' => $authority_list,
        ]);

    }

    /**
     *
     * @Route("/edit/authority/delete/{q_id}", name="edit_authority_delete")
     */
    public function deleteEntry(Request $request,
                                int $q_id,
                                EntityManagerInterface $entityManager) {
        $edit_form_id = 'authority_edit_form';
        $form_data = $request->request->get($edit_form_id);

        $authorityRepository = $entityManager->getRepository(Authority::class);

        // validation
        $error_flag = false;

        $id_list = array_column($form_data, 'id');
        $query_result = $authorityRepository->findList($id_list);
        $authority_list = array();
        foreach ($query_result as $authority) {
            $authority_list[$authority->getId()] = $authority;
        }


        // deletion takes priority: all other edit data are lost and sub-forms are closed
        foreach ($authority_list as $authority) {
            $id_loop = $authority->getId();
            if ($id_loop == $q_id) {
                $entityManager->remove($authority);
            }
        }

        $entityManager->flush();

        $query_result = $authorityRepository->findList($id_list);
        $authority_list = array();
        foreach ($query_result as $authority) {
            $authority_list[$authority->getId()] = $authority;
        }

        $template = 'edit_authority/_list.html.twig';

        return $this->render($template, [
            'editFormId' => $edit_form_id,
            'authorityList' => $authority_list,
        ]);

    }


    /**
     * get data for item with ID $id and pass $index
     * @Route("/edit/authority/item/{id}/{index}", name="edit_authority_item")
     */
    public function _item(int $id,
                          int $index,
                          EntityManagerInterface $entityManager): Response {
        $authorityRepository = $entityManager->getRepository(Authority::class);
        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class);

        $authority = $authorityRepository->find($id);
        $referenceCount = $urlExternalRepository->referenceCount($authority->getId());
        $authority->setReferenceCount($referenceCount);

        $authority->setFormIsExpanded(1);
        $edit_form_id = 'authority_edit_form';

        return $this->render('edit_authority/_input_content.html.twig', [
            'authority' => $authority,
            'editFormId' => $edit_form_id,
            'current_idx' => $index,
            'base_id' => $edit_form_id.'_'.$index,
            'base_input_name' => $edit_form_id.'['.$index.']',
        ]);

    }

    /**
     * @return template for new authority
     *
     * @Route("/edit/authority/new-authority", name="edit_authority_new_authority")
     */
    public function newAuthority(Request $request) {

        $auth = new Authority();
        $auth->setFormIsExpanded(true);

        // property types

        return $this->render('edit_authority/_item.html.twig', [
            'editFormId' => $request->query->get('edit_form_id'),
            'current_idx' => $request->query->get('current_idx'),
            'authority' => $auth,
        ]);

    }

}
