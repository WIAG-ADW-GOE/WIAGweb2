<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Entity\Role;
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

    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/role-suggest/{field}", name="role_suggest")
     */
    public function autocomplete(Request $request,
                                 EntityManagerInterface $entityManager,
                                 String $field): Response {
        $query_param = $request->query->get('q');
        $fn_name = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $roleRepository = $entityManager->getRepository(Role::class);
        $suggestions = $roleRepository->$fn_name($query_param);

        return $this->render('_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }


    /**
     * @Route("/edit/role/query", name="edit_role_query")
     */
    public function query(Request $request,
                          EntityManagerInterface $entityManager): Response {

        $roleRepository = $entityManager->getRepository(Role::class);

        $group_choice_list = ['- alle -' => ''];
        $group_list = $roleRepository->roleGroupList();
        $group_list = array_column($group_list, 'roleGroup');
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
            'sortBy' => 'idInSource',
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

        // sort null last
        $sort_criteria = array();
        if ($model['sortBy'] != '') {
            $sort_criteria[] = $model['sortBy'];
        }
        $sort_criteria[] = 'id';
        $role_list = UtilService::sortByFieldList($role_list, $sort_criteria);

        $template = 'edit_role/query.html.twig';
        $edit_form_id = 'role_edit_form';

        return $this->renderForm($template, [
            'menuItem' => 'edit-menu',
            'form' => $form,
            'editFormId' => $edit_form_id,
            'roleList' => $role_list,
        ]);

    }

    /**
     * @Route("/edit/role/save", name="edit_role_save")
     */
    public function save(Request $request,
                         EntityManagerInterface $entityManager) {

        $roleRepository = $entityManager->getRepository(Role::class);
        $personRoleRepository = $entityManager->getRepository(PersonRole::class);
        $itemRepository = $entityManager->getRepository(Item::class);
        $userWiagRepository = $entityManager->getRepository(UserWiag::class);

        $current_user_id = $this->getUser()->getId();

        $edit_form_id = 'role_edit_form';
        $form_data = $request->request->get($edit_form_id) ?? array();

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
                $role->getItem()->setFormIsExpanded($form_is_expanded);
            }
            if (isset($data['formIsEdited'])) {
                $role = new Role($current_user_id);
                if (!$id > 0) {
                    // new entry
                    $form_is_expanded = 1;
                    $role_list[] = $role;
                } else {
                    $role->getItem()->setIsNew(false);
                    $role_list[$id] = $role;
                }
                $role->getItem()->setFormIsExpanded($form_is_expanded);
                $role->getItem()->setFormIsEdited(1);

                // content in item can not be edited in the form
                $role->getItem()->updateChangedMetaData($current_user_id);
                UtilService::setByKeys(
                    $role,
                    $data,
                    Role::EDIT_FIELD_LIST);

                foreach ($data['urlext'] as $data_loop) {
                     $this->mapUrlExternal($role, $data_loop, $entityManager);
                }

                // validate input
                if (trim($role->getName()) == "") {
                    $msg = "Bitte das Feld 'Bezeichung' ausfüllen.";
                    $role->getInputError()->add(new InputError('general', $msg, 'error'));
                    $error_flag = true;
                }
                if (trim($role->getLang()) == "") {
                    $msg = "Bitte das Feld 'Sprache' ausfüllen.";
                    $role->getInputError()->add(new InputError('general', $msg, 'error'));
                    $error_flag = true;
                }
            }
        }

        // save
        if (!$error_flag) {
            $item_type_id = ITEM::ITEM_TYPE_ID['Amt']['id'];
            $max_id = $itemRepository->maxIdInSource($item_type_id);
            $next_id = $max_id + 1;
            foreach ($role_list as $key => $role) {
                $item = $role->getItem();
                if ($item->getFormIsEdited()) {
                    if ($item->getIsNew()) {
                        $item->setIdInSource($next_id);
                        $entityManager->persist($role);
                        // get new item from the database (ID updated)
                        $entityManager->flush();
                        $next_id += 1;
                        $item->setIsNew(false);
                        $item->setFormIsEdited(false);
                    } else {
                        // get object for saving
                        $q_result = $roleRepository->findList([$key]);
                        $target = $q_result[0];
                        $this->copy($target, $role, $entityManager);
                        $role_list[$key] = $target;
                    }
                }
            }
            $entityManager->flush();
        }

        // provide empty form elements if neccessary
        foreach ($role_list as $role) {
            $this->setDisplayData($role, $entityManager);
        }

        $template = 'edit_role/_list.html.twig';

        $debug = $request->request->get('formType');

        return $this->render($template, [
            'editFormId' => $edit_form_id,
            'roleList' => $role_list,
        ]);

    }

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

    private function copy(Role $target, Role $source, $entityManager) {
        // content in item can not be edited in the form

        // url external
        $target_item = $target->getItem();
        $target_uext = $target_item->getUrlExternal();
        $source_uext = $source->getItem()->getUrlExternal();
        EditService::setItemAttributeList($target->getItem(), $target_uext, $source_uext, $entityManager);

        $field_list = Role::EDIT_FIELD_LIST;

        foreach ($field_list as $field) {
            $get_fnc = 'get'.ucfirst($field);
            $set_fnc = 'set'.ucfirst($field);
            $target->$set_fnc($source->$get_fnc());
        }

    }

    /**
     *
     * @Route("/edit/role/delete/{q_id}", name="edit_role_delete")
     */
    public function deleteEntry(Request $request,
                                int $q_id,
                                EntityManagerInterface $entityManager) {
        $edit_form_id = 'role_edit_form';
        $form_data = $request->request->get($edit_form_id);

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
     * @Route("/edit/role/item/{id}/{index}", name="edit_role_item")
     */
    public function _item(int $id,
                          int $index,
                          EntityManagerInterface $entityManager): Response {

        $roleRepository = $entityManager->getRepository(Role::class);
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
            'editFormId' => $edit_form_id,
            'itemIndex' => $index,
            'base_id' => $edit_form_id.'_'.$index,
            'base_input_name' => $edit_form_id.'['.$index.']',
        ]);

    }

    /**
     * @return template for new role
     *
     * @Route("/edit/role/new-role", name="edit_role_new_role")
     */
    public function newRole(Request $request,
                            EntityManagerInterface $entityManager) {

        $current_user_id = $this->getUser()->getId();
        $obj = new Role($current_user_id);
        $obj->getItem()->setFormIsExpanded(true);
        // default
        $obj->setGender('männlich');

        $this->setDisplayData($obj, $entityManager);

        return $this->render('edit_role/_item.html.twig', [
            'editFormId' => $request->query->get('edit_form_id'),
            'current_idx' => $request->query->get('current_idx'),
            'role' => $obj,
        ]);

    }

        /**
     * @return template for new external ID
     *
     * @Route("/edit/role/new-url-external/{itemIndex}", name="edit_role_new_urlexternal")
     */
    public function newUrlExternal(Request $request,
                                   int $itemIndex) {

        $edit_form_id = 'role_edit_form';

        $urlExternal = new urlExternal();

        return $this->render('edit_role/_input_url_external.html.twig', [
            'editFormId' => $edit_form_id,
            'itemIndex' => $itemIndex,
            'currentIndex' => $request->query->get('current_idx'),
            'urlext' => $urlExternal,
        ]);

    }

    /**
     * fill url external with $data
     */
    private function mapUrlExternal($role, $data, $entityManager) {

        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class);
        $authorityRepository = $entityManager->getRepository(Authority::class);

        $item = $role->getItem();
        $url_external_list = $item->getUrlExternal();
        $url_external = null;
        $value = is_null($data['value']) ? null : trim($data['value']);
        if (is_null($value) || $value == "") {
            return $url_external;
            } else {
            $authority_name = $data["urlName"];
            $auth_query = $authorityRepository->findByUrlNameFormatter($authority_name);
            if (!is_null($auth_query) && count($auth_query) > 0) {
                $authority = $auth_query[0];
                // drop base URL if present
                if ($authority_name == 'Wikipedia-Artikel') {
                    $val_list = explode('/', $value);
                    $value = array_slice($val_list, -1)[0];
                }

                $url_external = $this->makeUrlExternal($item, $authority);
                $key_list = ['deleteFlag', 'value', 'note'];
                UtilService::setByKeys($url_external, $data, $key_list);

                $url_external_list->add($url_external);

                // validate: avoid merge separator
                $separator = "|";
                if (str_contains($value, $separator)) {
                    $msg = "Eine externe ID enthält '".$separator."'.";
                    $role->getInputError()->add(new InputError('external id', $msg));
                }
            } else {
                $msg = "Keine exindeutige Institution für '".$authority_name."' gefunden.";
                $role->getInputError()->add(new InputError('external id', $msg));
            }
        }

        return $url_external;
    }

    /**
     * create UrlExternal object
     * TODO 2023-05-25 replace this by UrlExternal($item);
     */
    public function makeUrlExternal($item, $authority) {
        $url_external = new UrlExternal();
        if (!is_null($item->getId())) {
            $url_external->setItemId($item->getId());
        }
        $url_external->setItem($item);
        $url_external->setAuthorityId($authority->getId());
        $url_external->setAuthority($authority);
        return $url_external;
    }



}
