<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Entity\Diocese;
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


class EditDioceseController extends AbstractController {

    /**
     * respond to asynchronous JavaScript request
     *
     * @Route("/diocese-suggest/{field}", name="diocese_suggest")
     */
    public function autocomplete(Request $request,
                                 EntityManagerInterface $entityManager,
                                 String $field): Response {
        $query_param = $request->query->get('q');
        $fn_name = 'suggest'.ucfirst($field); // e.g. suggestInstitution

        $dioceseRepository = $entityManager->getRepository(Diocese::class);
        $suggestions = $dioceseRepository->$fn_name($query_param);

        return $this->render('_autocomplete.html.twig', [
            'suggestions' => array_column($suggestions, 'suggestion'),
        ]);
    }


    /**
     * @Route("/edit/diocese/query", name="edit_diocese_query")
     */
    public function query(Request $request,
                          EntityManagerInterface $entityManager): Response {

        $dioceseRepository = $entityManager->getRepository(Diocese::class);


        $model = [
            'name' => '',
        ];

        $form = $this->createFormBuilder($model)
                     ->setMethod('GET')
                     ->add('name', TextType::class, [
                         'label' => 'Name',
                         'required' => false,
                         'attr' => [
                             'placeholder' => 'Bezeichnung'
                         ],
                     ])
                     ->getForm();

        $form->handleRequest($request);
        $model = $form->getData();

        $diocese_list = array();
        if ($form->isSubmitted() && $form->isValid()) {
        } else {
            $model['name'] = '';
        }

        $diocese_list = $dioceseRepository->findByModel($model);

        // sort null last
        $sort_criteria = ['name', 'id'];
        $diocese_list = UtilService::sortByFieldList($diocese_list, $sort_criteria);

        $template = 'edit_diocese/query.html.twig';
        $edit_form_id = 'diocese_edit_form';

        return $this->renderForm($template, [
            'menuItem' => 'edit-menu',
            'form' => $form,
            'editFormId' => $edit_form_id,
            'dioceseList' => $diocese_list,
        ]);

    }

    /**
     * @Route("/edit/diocese/save", name="edit_diocese_save")
     */
    public function save(Request $request,
                         EntityManagerInterface $entityManager) {

        $dioceseRepository = $entityManager->getRepository(Diocese::class);
        $personDioceseRepository = $entityManager->getRepository(PersonDiocese::class);
        $itemRepository = $entityManager->getRepository(Item::class);
        $userWiagRepository = $entityManager->getRepository(UserWiag::class);

        $current_user_id = $this->getUser()->getId();
        $current_user = $userWiagRepository->find($current_user_id);

        $edit_form_id = 'diocese_edit_form';
        $form_data = $request->request->get($edit_form_id) ?? array();

        // validation
        $error_flag = false;

        $diocese_list = array();

        $id_list = array_column($form_data, 'id');
        $query_result = $dioceseRepository->findList($id_list);
        foreach ($query_result as $diocese) {
            $diocese_list[$diocese->getId()] = $diocese;
        }

        // map data
        foreach($form_data as $data) {
            $id = $data['id'];
            $form_is_expanded = isset($data['formIsExpanded']) ? 1 : 0;
            if ($id > 0) {
                $diocese = $diocese_list[$id];
                $diocese->getItem()->setFormIsExpanded($form_is_expanded);
            }
            if (isset($data['formIsEdited'])) {
                $diocese = new Diocese($current_user_id);
                if (!$id > 0) {
                    // new entry
                    $form_is_expanded = 1;
                    $diocese_list[] = $diocese;
                } else {
                    $diocese->getItem()->setIsNew(false);
                    $diocese_list[$id] = $diocese;
                }
                $diocese->getItem()->setFormIsExpanded($form_is_expanded);
                $diocese->getItem()->setFormIsEdited(1);

                // content in item can not be edited in the form
                UtilService::setByKeys(
                    $diocese,
                    $data,
                    Diocese::EDIT_FIELD_LIST);

                foreach ($data['urlext'] as $data_loop) {
                     $this->mapUrlExternal($diocese, $data_loop, $entityManager);
                }

                // validate input
                if (trim($diocese->getName()) == "") {
                    $msg = "Bitte das Feld 'Bezeichung' ausfüllen.";
                    $diocese->getInputError()->add(new InputError('general', $msg, 'error'));
                    $error_flag = true;
                }
                if (trim($diocese->getLang()) == "") {
                    $msg = "Bitte das Feld 'Sprache' ausfüllen.";
                    $diocese->getInputError()->add(new InputError('general', $msg, 'error'));
                    $error_flag = true;
                }
            }
        }

        // save
        if (!$error_flag) {
            $item_type_id = ITEM::ITEM_TYPE_ID['Amt']['id'];
            $max_id = $itemRepository->maxIdInSource($item_type_id);
            $next_id = $max_id + 1;
            foreach ($diocese_list as $key => $diocese) {
                $item = $diocese->getItem();
                if ($item->getFormIsEdited()) {
                    if ($item->getIsNew()) {
                        $item->setIdInSource($next_id);
                        $entityManager->persist($diocese);
                        // get new item from the database (ID updated)
                        $entityManager->flush();
                        $next_id += 1;
                        $item->setIsNew(false);
                        $item->setFormIsEdited(false);
                        $item->updateChangedMetaData($current_user);
                    } else {
                        // get object for saving
                        $q_result = $dioceseRepository->findList([$key]);
                        $target = $q_result[0];
                        $this->copy($target, $diocese, $entityManager);
                        $target->getItem()->updateChangedMetaData($current_user);
                        $diocese_list[$key] = $target;
                    }
                }
            }
            $entityManager->flush();
        }

        // provide empty form elements if neccessary
        foreach ($diocese_list as $diocese) {
            $this->setDisplayData($diocese, $entityManager);
        }

        $template = 'edit_diocese/_list.html.twig';

        $debug = $request->request->get('formType');

        return $this->render($template, [
            'editFormId' => $edit_form_id,
            'dioceseList' => $diocese_list,
        ]);

    }

    private function setDisplayData($diocese, $entityManager) {
        $personRoleRepository = $entityManager->getRepository(PersonRole::class);

        $item = $diocese->getItem();
        if ($item->getFormIsExpanded()) {
            $url_ext_list = $item->getUrlExternal();
            if (count($url_ext_list) < 1) {
                $url_ext_list->add(new UrlExternal());
            }
            $dioceseCount = $personRoleRepository->dioceseReferenceCount($diocese->getId());
            $diocese->setReferenceCount($dioceseCount);
        }
    }

    private function copy(Diocese $target, Diocese $source, $entityManager) {
        // content in item can not be edited in the form

        // url external
        $target_item = $target->getItem();
        $target_uext = $target_item->getUrlExternal();
        $source_uext = $source->getItem()->getUrlExternal();
        EditService::setItemAttributeList($target->getItem(), $target_uext, $source_uext, $entityManager);

        $field_list = Diocese::EDIT_FIELD_LIST;

        foreach ($field_list as $field) {
            $get_fnc = 'get'.ucfirst($field);
            $set_fnc = 'set'.ucfirst($field);
            $target->$set_fnc($source->$get_fnc());
        }

    }

    /**
     *
     * @Route("/edit/diocese/delete/{q_id}", name="edit_diocese_delete")
     */
    public function deleteEntry(Request $request,
                                int $q_id,
                                EntityManagerInterface $entityManager) {
        $edit_form_id = 'diocese_edit_form';
        $form_data = $request->request->get($edit_form_id);

        $dioceseRepository = $entityManager->getRepository(Diocese::class);

        $id_list = array_column($form_data, 'id');
        $query_result = $dioceseRepository->findList($id_list);
        $diocese_list = array();
        foreach ($query_result as $diocese) {
            $diocese_list[$diocese->getId()] = $diocese;
        }

        // deletion takes priority: all other edit data are lost and sub-forms are closed
        foreach ($diocese_list as $diocese) {
            $id_loop = $diocese->getId();
            if ($id_loop == $q_id) {
                $item = $diocese->getItem();
                $entityManager->remove($diocese);
                foreach($item->getUrlExternal() as $uext) {
                    $entityManager->remove($uext);
                }
                $entityManager->remove($item);
            }
        }

        $entityManager->flush();

        $query_result = $dioceseRepository->findList($id_list);
        $diocese_list = array();
        foreach ($query_result as $diocese) {
            $diocese_list[$diocese->getId()] = $diocese;
        }

        $template = 'edit_diocese/_list.html.twig';

        return $this->render($template, [
            'editFormId' => $edit_form_id,
            'dioceseList' => $diocese_list,
        ]);

    }


    /**
     * get data for item with ID $id and pass $index
     * @Route("/edit/diocese/item/{id}/{index}", name="edit_diocese_item")
     */
    public function _item(int $id,
                          int $index,
                          EntityManagerInterface $entityManager): Response {

        $dioceseRepository = $entityManager->getRepository(Diocese::class);
        $userWiagRepository = $entityManager->getRepository(UserWiag::class);

        $diocese = $dioceseRepository->find($id);
        $diocese->getItem()->setFormIsExpanded(1);

        $item = $diocese->getItem();
        $user = $userWiagRepository->find($item->getChangedBy());
        $item->setChangedByUser($user);

        $this->setDisplayData($diocese, $entityManager);

        $edit_form_id = 'diocese_edit_form';

        return $this->render('edit_diocese/_input_content.html.twig', [
            'diocese' => $diocese,
            'editFormId' => $edit_form_id,
            'itemIndex' => $index,
            'base_id' => $edit_form_id.'_'.$index,
            'base_input_name' => $edit_form_id.'['.$index.']',
        ]);

    }

    /**
     * @return template for new diocese
     *
     * @Route("/edit/diocese/new-diocese", name="edit_diocese_new_diocese")
     */
    public function newDiocese(Request $request,
                            EntityManagerInterface $entityManager) {

        $current_user_id = $this->getUser()->getId();
        $obj = new Diocese($current_user_id);
        $obj->getItem()->setFormIsExpanded(true);
        // default
        $obj->setGender('männlich');

        $this->setDisplayData($obj, $entityManager);

        return $this->render('edit_diocese/_item.html.twig', [
            'editFormId' => $request->query->get('edit_form_id'),
            'current_idx' => $request->query->get('current_idx'),
            'diocese' => $obj,
        ]);

    }

        /**
     * @return template for new external ID
     *
     * @Route("/edit/diocese/new-url-external/{itemIndex}", name="edit_diocese_new_urlexternal")
     */
    public function newUrlExternal(Request $request,
                                   int $itemIndex) {

        $edit_form_id = 'diocese_edit_form';

        $urlExternal = new urlExternal();

        return $this->render('edit_diocese/_input_url_external.html.twig', [
            'editFormId' => $edit_form_id,
            'itemIndex' => $itemIndex,
            'currentIndex' => $request->query->get('current_idx'),
            'urlext' => $urlExternal,
        ]);

    }

    /**
     * fill url external with $data
     */
    private function mapUrlExternal($diocese, $data, $entityManager) {

        $urlExternalRepository = $entityManager->getRepository(UrlExternal::class);
        $authorityRepository = $entityManager->getRepository(Authority::class);

        $item = $diocese->getItem();
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
                    $diocese->getInputError()->add(new InputError('external id', $msg));
                }
            } else {
                $msg = "Keine exindeutige Institution für '".$authority_name."' gefunden.";
                $diocese->getInputError()->add(new InputError('external id', $msg));
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
