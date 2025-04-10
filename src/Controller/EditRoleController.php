<?php

namespace App\Controller;

use App\Entity\ItemCorpus;
use App\Entity\Role;
use App\Entity\RoleGroup;
use App\Entity\PersonRole;
use App\Entity\UrlExternal;
use App\Entity\Authority;
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


class EditRoleController extends AbstractController {
    const SUGGEST_SIZE = 8;
    const PAGE_SIZE = 30;

    /**
     * respond to asynchronous JavaScript request
     */
    #[Route(path: '/role-suggest/{field}', name: 'role_suggest')]
    public function autocomplete(Request $request,
                                 EntityManagerInterface $entityManager,
                                 String $field): Response {
        $query_param = $request->query->get('q');
        $fn_name = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $roleRepository = $entityManager->getRepository(Role::class);
        $suggestions = $roleRepository->$fn_name($query_param, self::SUGGEST_SIZE);

        return $this->render('_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }


    #[Route(path: '/edit/role/query', name: 'edit_role_query')]
    public function query(Request $request,
                          EntityManagerInterface $entityManager): Response {

        $this->denyAccessUnlessGranted('ROLE_EDIT_BASE');

        $roleRepository = $entityManager->getRepository(Role::class);
        $roleGroupRepository = $entityManager->getRepository(RoleGroup::class);

        $group_choice_list = ['- alle -' => ''];
        $group_list = $roleGroupRepository->findAll();
        $group_list = UtilService::collectionColumn($group_list, 'name');
        $group_list = array_combine($group_list, $group_list);
        $group_choice_list = array_merge($group_choice_list, $group_list);

        $sort_by_choices = [
            'ID' => 'idInSource',
            'Gruppe' => 'roleGroup',
            'Name' => 'name'
        ];

        $model = [
            'roleGroup' => '',
            'name' => '',
            'sortBy' => 'name',
        ];

        $form = $this->createFormBuilder($model)
                     ->setMethod('GET')
                     ->add('roleGroup', ChoiceType::class, [
                         'label' => 'Gruppe',
                         'choices' => $group_choice_list,
                         'required' => false,
                     ])
                     ->add('name', TextType::class, [
                         'label' => 'Name',
                         'required' => false,
                         'attr' => [
                             'placeholder' => 'Bezeichnung'
                         ],
                     ])
                     ->add('sortBy', ChoiceType::class, [
                         'label' => 'Sortierung',
                         'choices' => $sort_by_choices
                     ])
                     ->getForm();

        $form->handleRequest($request);
        $model = $form->getData();

        $role_list = array();
        if ($form->isSubmitted() && $form->isValid()) {
        } else {
            $model['roleGroup'] = '';
        }

        $role_list = $roleRepository->findByModel($model);
        $count = count($role_list);

        // sort null last
        $sort_criteria = array();
        if ($model['sortBy'] != '') {
            $sort_criteria[] = $model['sortBy'];
        }
        $sort_criteria[] = 'id';
        $role_list = UtilService::sortByFieldList($role_list, $sort_criteria);

        // $offset is null if form is not sent via a page browse button, then $page_number is relevant
        $offset = $request->query->get('offset');
        $page_number = $request->query->get('pageNumber');
        // set offset to page begin
        $offset = UtilService::offset($offset, $page_number, $count, self::PAGE_SIZE);

        $role_list = array_slice($role_list, $offset, self::PAGE_SIZE);

        $template = 'edit_role/query.html.twig';
        $edit_form_id = 'role_edit_form';

        return $this->render($template, [
            'menuItem' => 'edit-menu',
            'form' => $form,
            'editFormId' => $edit_form_id,
            'roleList' => $role_list,
            'count' => $count,
            'offset' => $offset,
            'pageSize' => self::PAGE_SIZE,
        ]);

    }

    #[Route(path: '/edit/role/save', name: 'edit_role_save')]
    public function save(Request $request,
                         EntityManagerInterface $entityManager) {

        $roleRepository = $entityManager->getRepository(Role::class);
        $roleGroupRepository = $entityManager->getRepository(RoleGroup::class);
        $personRoleRepository = $entityManager->getRepository(PersonRole::class);
        $itemCorpusRepository = $entityManager->getRepository(ItemCorpus::class);
        $userWiagRepository = $entityManager->getRepository(UserWiag::class);

        $current_user_id = $this->getUser()->getId();
        $current_user = $userWiagRepository->find($current_user_id);

        $edit_form_id = 'role_edit_form';
        $form_data = $request->request->all($edit_form_id) ?? array();

        // validation
        $error_flag = false;

        $role_list = array();

        $id_list = array_column($form_data, 'id');
        $query_result = $roleRepository->findList($id_list);
        foreach ($query_result as $role) {
            $role_list[$role->getId()] = $role;
        }

        // map data
        foreach($form_data as $data) {
            $id = $data['id'];
            $form_is_expanded = isset($data['formIsExpanded']) ? 1 : 0;
            if ($id > 0) {
                $role = $role_list[$id];
            } else {
                $role = new Role($current_user_id);
                EditService::setNewItemCorpus($role->getItem(), Role::CORPUS_ID, $entityManager);
            }
            $role->getItem()->setFormIsExpanded($form_is_expanded);

            if (isset($data['formIsEdited'])) {
                $item = $role->getItem();
                if (!$id > 0) {
                    // new entry
                    $form_is_expanded = 1;
                    $role_list[] = $role;
                    $data['item']['idInSource'] = '0';
                } else {
                    $role->getItem()->setIsNew(false);
                    $role_list[$id] = $role;
                }
                $item->setFormIsEdited(1);

                // item
                UtilService::setByKeys(
                    $item,
                    $data['item'],
                    ['idInSource']);

                // role
                UtilService::setByKeys(
                    $role,
                    $data,
                    Role::EDIT_FIELD_LIST);

                EditService::mapUrlExternal($item, $data['urlext'], $entityManager);

                // roleGroup
                if (array_key_exists('roleGroup', $data) and $data['roleGroup'] > 0) {
                    $role->setRoleGroupId($data['roleGroup']);
                    $role->setRoleGroup($roleGroupRepository->find($data['roleGroup']));
                } else {
                    $role->setRoleGroupId(null);
                    $role->setRoleGroup(null);
                }

                // validate input
                $gs_reg_id = intval($role->getGsRegId());
                if (strlen($role->getGsRegId()) > 0 and $gs_reg_id == 0) {
                    $msg = "Der Wert für 'GS Reg ID' muss eine Zahl sein.";
                    $item->getInputError()->add(new InputError('general', $msg, 'error'));
                }
                if (trim($role->getName()) == "") {
                    $msg = "Bitte das Feld 'Bezeichung' ausfüllen.";
                    $item->getInputError()->add(new InputError('general', $msg, 'error'));
                }
                if (trim($role->getLang()) == "") {
                    $msg = "Bitte das Feld 'Sprache' ausfüllen.";
                    $item->getInputError()->add(new InputError('general', $msg, 'error'));
                }
                $error_flag = ($error_flag or !$item->getInputError()->isEmpty());
            }
        }

        // save
        if (!$error_flag) {
            $max_id = $itemCorpusRepository->findMaxIdInCorpus('ofcm');
            $next_id = $max_id + 1;
            foreach ($role_list as $key => $role) {
                $item = $role->getItem();
                if ($item->getFormIsEdited()) {
                    if ($item->getIsNew()) {
                        $item->setIdInSource($next_id);
                        EditService::removeUrlExternalMayBe($item, $entityManager);
                        $entityManager->persist($role);
                        foreach ($item->getItemCorpus() as $ic) {
                            $entityManager->persist($ic);
                        }

                        // get new item from the database (ID updated)
                        $entityManager->flush();
                        $next_id += 1;
                    }
                    // delete flag?
                    EditService::removeUrlExternalMayBe($item, $entityManager);
                    $item->setIsNew(false);
                    $item->updateChangedMetaData($current_user);
                    $item->setFormIsEdited(false);
                }
            }

            $entityManager->flush();
        }

        // provide empty form elements if neccessary
        foreach ($role_list as $role) {
            $this->setDisplayData($role, $entityManager);
        }

        $template = 'edit_role/_list.html.twig';

        return $this->render($template, [
            'editFormId' => $edit_form_id,
            'roleList' => $role_list,
            'roleGroupList' => $roleGroupRepository->findAll(),
        ]);

    }

    /**
     * set default elements for the form
     */
    private function setDisplayData($role, $entityManager) {
        $personRoleRepository = $entityManager->getRepository(PersonRole::class);

        $item = $role->getItem();
        if ($item->getFormIsExpanded()) {
            $url_ext_list = $item->getUrlExternal();
            if (count($url_ext_list) < 1) {
                $url_ext_list->add(new UrlExternal());
            }
            $roleCount = $personRoleRepository->referenceCount($role->getId());
            $role->setReferenceCount($roleCount);
        }
    }

    #[Route(path: '/edit/role/delete/{q_id}', name: 'edit_role_delete')]
    public function deleteEntry(Request $request,
                                int $q_id,
                                EntityManagerInterface $entityManager) {
        $edit_form_id = 'role_edit_form';
        $form_data = $request->request->all($edit_form_id);


        $roleRepository = $entityManager->getRepository(Role::class);

        $id_list = array_column($form_data, 'id');
        $query_result = $roleRepository->findList($id_list);
        $role_list = array();

        foreach ($query_result as $role) {
            $role_list[$role->getId()] = $role;
        }

        // deletion takes priority: all other edit data are lost and sub-forms are closed
        foreach ($role_list as $role) {
            $id_loop = $role->getId();
            if ($id_loop == $q_id) {
                $item = $role->getItem();
                $entityManager->remove($role);
                foreach($item->getUrlExternal() as $uext) {
                    $entityManager->remove($uext);
                }
                foreach($item->getItemCorpus() as $item_corpus) {
                    $entityManager->remove($item_corpus);
                }
                $entityManager->remove($item);
            }
        }

        $entityManager->flush();

        $query_result = $roleRepository->findList($id_list);
        $role_list = array();
        foreach ($query_result as $role) {
            $role_list[$role->getId()] = $role;
        }

        $template = 'edit_role/_list.html.twig';

        return $this->render($template, [
            'editFormId' => $edit_form_id,
            'roleList' => $role_list,
        ]);

    }


    /**
     * get data for item with ID $id and pass $index
     */
    #[Route(path: '/edit/role/item/{id}/{index}', name: 'edit_role_item')]
    public function _item(int $id,
                          int $index,
                          EntityManagerInterface $entityManager): Response {

        $roleRepository = $entityManager->getRepository(Role::class);
        $roleGroupRepository = $entityManager->getRepository(RoleGroup::class);
        $userWiagRepository = $entityManager->getRepository(UserWiag::class);

        $role = $roleRepository->find($id);
        $role->getItem()->setFormIsExpanded(1);

        $item = $role->getItem();
        $user = $userWiagRepository->find($item->getChangedBy());
        $item->setChangedByUser($user);

        $this->setDisplayData($role, $entityManager);

        $edit_form_id = 'role_edit_form';

        return $this->render('edit_role/_input_content.html.twig', [
            'role' => $role,
            'roleGroupList' => $roleGroupRepository->findAll(),
            'editFormId' => $edit_form_id,
            'itemIndex' => $index,
            'base_id' => $edit_form_id.'_'.$index,
            'base_input_name' => $edit_form_id.'['.$index.']',
        ]);

    }

    /**
     * Returns template for new role
     */
    #[Route(path: '/edit/role/new-role', name: 'edit_role_new_role')]
    public function newRole(Request $request,
                            EntityManagerInterface $entityManager) {
        $this->denyAccessUnlessGranted('ROLE_EDIT_BASE');
        $roleGroupRepository = $entityManager->getRepository(RoleGroup::class);

        $current_user_id = $this->getUser()->getId();
        $obj = new Role($current_user_id);
        $obj->getItem()->setFormIsExpanded(true);
        // default
        $obj->setGender('männlich');

        $this->setDisplayData($obj, $entityManager);

        return $this->render('edit_role/_item.html.twig', [
            'editFormId' => $request->query->get('edit_form_id'),
            'roleGroupList' => $roleGroupRepository->findAll(),
            'current_idx' => $request->query->get('current_idx'),
            'role' => $obj,
        ]);

    }

        /**
     * Returns template for new external ID
     */
    #[Route(path: '/edit/role/new-url-external/{itemIndex}', name: 'edit_role_new_urlexternal')]
    public function newUrlExternal(Request $request,
                                   int $itemIndex) {

        $edit_form_id = 'role_edit_form';

        $urlExternal = new urlExternal();

        return $this->render('edit_role/_input_url_external.html.twig', [
            'editFormId' => $edit_form_id,
            'itemIndex' => $itemIndex,
            'currentIndex' => $request->query->get('current_idx'),
            'urlext' => $urlExternal,
            'isLast' => true,
        ]);

    }


    /**
     * clear $target_list; copy collection $source_list to $target_list;
     */
    private function setItemAttributeList($target, $target_list, $source_list, $entityManager) {

        // - remove entries
        // $target_ref = $target->getItem()->getReference();
        foreach ($target_list as $t) {
            $target_list->removeElement($t);
            $t->setItem(null);
            $entityManager->remove($t);
        }

        // - set new entries
        foreach ($source_list as $i) {
            if (!$i->getDeleteFlag()) {
                $target_list->add($i);
                $i->setItem($target);
                $entityManager->persist($i);
            }
        }
    }

    private function updateUrlExternal($role, $entityManager) {
        $uext_list = $role->getItem()->getUrlExternal();

        foreach ($uext_list as $uext) {
            if ($uext->getDeleteFlag()) {
                $uext_list->removeElement($uext);
            }
            // what about new elements?
        }

    }

}
