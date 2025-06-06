<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\Corpus;
use App\Entity\ItemReference;
use App\Entity\ItemProperty;
use App\Entity\ItemPropertyType;
use App\Entity\ItemCorpus;
use App\Entity\UrlExternal;
use App\Entity\Person;
use App\Entity\InputError;
use App\Entity\PersonRole;
use App\Entity\PersonRoleProperty;
use App\Entity\RolePropertyType;
use App\Entity\Authority;
use App\Entity\NameLookup;
use App\Entity\ItemNameRole;
use App\Entity\UserWiag;
use App\Form\EditPersonFormType;
use App\Form\Model\PersonFormModel;
use App\Entity\Role;

use App\Service\EditPersonService;
use App\Service\AutocompleteService;
use App\Service\UtilService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Doctrine\ORM\EntityManagerInterface;

class EditPersonController extends AbstractController {
    /** number of suggestions in autocomplete list */
    const SUGGEST_SIZE = 8;
    const EDIT_FORM_ID = 'edit_person_edit_form';

    private $editService;
    private $entityManager;
    private $autocomplete;

    public function __construct(EditPersonService $editService,
                                AutocompleteService $autocomplete,
                                EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        $this->autocomplete = $autocomplete;
        $this->editService = $editService;
    }

    /**
     * display query form for persons;
     */
    #[Route(path: '/edit/person/query/{corpusId}', name: 'edit_person_query')]
    public function query($corpusId, Request $request) {


        $corpusRepository = $this->entityManager->getRepository(Corpus::class);
        $corpus_id_list = explode(",", $corpusId);
        $corpus_list = $corpusRepository->findBy(['corpusId' => $corpus_id_list]);

        if (!$this->checkAccess($corpus_id_list)) {
            return $this->render('home\message.html.twig', [
                'message' => 'Sie haben nicht die erforderlichen Rechte oder sind nicht angemeldet.'
            ]);
        }

        // $this->denyAccessUnlessGranted('ROLE_EDIT_'.strtoupper($corpus_id_list[0]));
        $title_list = array();
        foreach ($corpus_list as $cps) {
            $title_list[] = $cps->getPageTitle();
        }

        $model = new PersonFormModel;
        // set defaults
        $model->editStatus = ['- alle -' => '- alle -'];
        $model->isOnline = true;
        $model->listSize = 20;
        $model->corpus = $corpusId;
        $model->isEdit = true;

        $status_choices = $this->statusChoices();

        $form = $this->createForm(EditPersonFormType::class, $model, [
            'statusChoices' => $status_choices,
        ]);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        $person_list = array();
        $count = 0;

        if ($form->isSubmitted() && $form->isValid() && $model->isValid() || $model->isEmpty()) {

            $personRepository = $this->entityManager->getRepository(Person::class);

            $limit = 0; $offset = 0; $online_only = false;
            $id_all = $personRepository->findEditPersonIds($model, $limit, $offset);

            $count = count($id_all);

            // $offset is null if form is not sent via a page browse button, then $page_number is relevant
            $offset = $request->request->get('offset');
            $page_number = $request->request->get('pageNumber');

            // set offset to page begin
            $offset = UtilService::offset($offset, $page_number, $count, $model->listSize);

            $id_list = array_slice($id_all, $offset, $model->listSize);

            $deleted_flag = false;
            $ancestor_flag = true;
            $person_list = $personRepository->findList($id_list, $deleted_flag, $ancestor_flag);

            // add empty role, reference and url external if not present
            $authorityRepository = $this->entityManager->getRepository(Authority::class);
            $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
            foreach ($person_list as $person) {
                $person->extractSeeAlso();
                $person->addEmptyDefaultElements($corpusId, $auth_list);
            }

        } else {
            $person_list = [];
            $offset = 0;
        }

        $template_params = [
            'titleList' => $title_list,
            'personList' => $person_list,
            'form' => $form,
            'corpusId' => $corpusId,
            'error_list' => $model->getInputError(),
            'count' => $count,
            'offset' => $offset,
            'pageSize' => $model->listSize,
        ];

        $template = 'edit_person/query.html.twig';
        return $this->renderEditElements($corpusId, $template, $template_params);

    }

    /**
     * statusChoices()
     *
     * Returns choice list for status values
     */
    private function statusChoices() {
        $personRepository = $this->entityManager->getRepository(Person::class);

        $q_param = null;
        $suggestions = $this->autocomplete->suggestEditStatus($q_param, 60);
        $status_list = array_column($suggestions, 'suggestion');

        $status_choices = ['- alle -' => '- alle -'];
        $status_choices = array_merge($status_choices, array_combine($status_list, $status_list));
        // $status_choices = array_combine($status_list, $status_list);

        return $status_choices;
    }

    /**
     * map data to objects and save them to the database
     */
    #[Route(path: '/edit/person/save-single/{corpusId}', name: 'edit_person_save_single')]
    public function saveSingle($corpusId, Request $request) {
        $current_user_id = $this->getUser()->getId();

        // use EDIT_FORM_ID as the name attribute of the form in the template
        $form_data = $request->request->all(self::EDIT_FORM_ID);
        $person_index = array_keys($form_data)[0];
        // get first element independent from indexing
        $form_data = array_values($form_data)[0];
        /* map/validate form */
        // fill person_list
        $person_list = $this->editService->mapFormdata([$form_data], $current_user_id);
        $person = $person_list[0];
        $error_flag = $person->getItem()->hasError('error');
        $person_id = $person->getId();

        /* save data */
        $em = $this->entityManager;
        if (!$error_flag) {
            $itemRepository = $em->getRepository(Item::class);
            $personRepository = $em->getRepository(Person::class);
            $nameLookupRepository = $em->getRepository(NameLookup::class);
            $itemNameRoleRepository = $em->getRepository(ItemNameRole::class);
            $urlExternalRepository = $em->getRepository(UrlExternal::class);
            $corpusRepository = $em->getRepository(Corpus::class);
            $itemCorpusRepository = $em->getRepository(ItemCorpus::class);

            // set ID in corpus and public ID if necessary
            $this->editService->initItemCorpusMayBe($person->getItem());

            // this function is only called if form data have changed
            if ($person_id == 0) { // new entry
                // start out with a new object to avoid cascade errors
                $item_new = new Item($current_user_id);
                $this->entityManager->persist($item_new);
                $person_new = new Person($item_new);
                $this->entityManager->persist($person_new);
                $this->entityManager->flush();
                $person_id = $person_new->getItem()->getId();
            }

            // read complete tree to perform deletions if necessary
            $query_result = $personRepository->findList([$person_id]);
            $target = $query_result[0];

            // collect IDs of affected persons
            $affected_id_list = [$person_id];
            // - get value of URL external via object data
            // - person
            $uext_value = $person->getItem()->getGsn();
            if (!is_null($uext_value)) {
                $item_id_list = $urlExternalRepository->findItemId($uext_value, Authority::ID['GSN']);
                $affected_id_list = array_merge($affected_id_list, $item_id_list);
            }
            // - target (ante)
            $uext_value = $target->getItem()->getGsn();
            // $person_id == ID of target is already in $affected_id_list
            if (!is_null($uext_value)) {
                $item_id_list = $urlExternalRepository->findItemId($uext_value, Authority::ID['GSN']);
                $affected_id_list = array_merge($affected_id_list, $item_id_list);
            }
            $affected_id_list = array_unique($affected_id_list);


            // transfer data from $person to $target
            $this->editService->update($target, $person, $current_user_id);

            // merging process?
            $target_item = $target->getItem();
            if ($target_item->getMergeStatus() == 'merging') {
                $parent_list = $this->editService->readParentList($target);

                $target_item->setMergeStatus('child');
                $target_item->clearMergeParent();
                $idPublic = $parent_list[0]->getItem()->getIdPublic();
                $target_id = $target->getId();
                $ancestor_list = array();
                foreach ($parent_list as $parent) {
                    $item_loop = $parent->getItem();
                    $ancestor_list[] = $item_loop;
                    $q_ancestor_list = $itemRepository->findAncestor($item_loop);
                    $ancestor_list = array_merge($ancestor_list, $q_ancestor_list);
                    $this->editService->updateItemAsParent($item_loop, $target_id);
                }

                foreach ($parent_list as $parent) {
                    $affected_id_list[] = $parent->getId();
                    $uext_value = $parent->getItem()->getGsn();
                    if (!is_null($uext_value)) {
                        $item_id_list = $urlExternalRepository->findItemId($uext_value, Authority::ID['GSN']);
                        $affected_id_list = array_merge($affected_id_list, $item_id_list);
                    }
                }
                $affected_id_list = array_unique($affected_id_list);

                $target->getItem()->setAncestor($ancestor_list);
            }

            $nameLookupRepository->update($target);

            // form status
            $expanded = $person->getItem()->getFormIsExpanded();
            $target->getItem()->setFormIsExpanded($expanded);
            $target->getItem()->setFormIsEdited(false);

            $this->entityManager->flush();

            $itemNameRoleRepository->updateByIdList($affected_id_list);

            $person=$target;

        }

        // add empty elements for blank form sections
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
        $person->extractSeeAlso();
        $person->addEmptyDefaultElements($corpusId, $auth_list);
        $person->getItem()->setFormType($form_data['item']['formType']);

        $template = "edit_person/_item.html.twig";

        $debug_submit = $request->request->get('debug');
        if ($debug_submit == 'debug') {
            $template = "edit_person/item_debug.html.twig";
        }

        return $this->renderEditElements($corpusId, $template, [
            'person' => $person,
            'personIndex' => $person_index,
        ]);
    }

    private function renderEditElements($corpusId, $template, $param_list) {

        $userWiagRepository = $this->entityManager->getRepository(UserWiag::class);
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $essential_auth_list = $authorityRepository->findList(array_values(Authority::ID));

        $corpusRepository = $this->entityManager->getRepository(Corpus::class);
        $corpus_id_list = explode(',', $corpusId);

        $cc_qr = $corpusRepository->findBy(['corpusId' => $corpus_id_list], ['editChoiceOrder' => 'ASC']);
        $corpus_choice = [];
        foreach ($cc_qr as $cc_loop) {
            $corpus_choice[$cc_loop->getCorpusId()] = $cc_loop->getName();
        }

        $param_list_combined = array_merge($param_list, [
            'menuItem' => 'edit-menu',
            'editFormId' => self::EDIT_FORM_ID,
            'corpusId' => $corpusId,
            'userWiagRepository' => $userWiagRepository,
            'essentialAuthorityList' => $essential_auth_list,
            'itemPropertyTypeList' => $this->getItemPropertyTypeList(),
            'corpusChoice' => $corpus_choice,
        ]);

        return $this->render($template, $param_list_combined);

    }

    #[Route(path: '/edit/person/delete-local', name: 'edit_person_delete_local')]
    public function deleteEntryLocal(Request $request) {
        $personRepository = $this->entityManager->getRepository(Person::class);
        $itemRepository = $this->entityManager->getRepository(Item::class);
        $itemNameRoleRepository = $this->entityManager->getRepository(ItemNameRole::class);
        $urlExternalRepository = $this->entityManager->getRepository(UrlExternal::class);

        $form_data = $request->request->all(self::EDIT_FORM_ID);
        // get first element independent from indexing
        $form_data = array_values($form_data)[0];
        $id = $form_data['id'];

        $item = $itemRepository->find($id);

        $item->setIsDeleted(1);
        $item->setIsOnline(0);
        $item->updateChangedMetaData($this->getUser());

        // clear item_name_role
        // - collect IDs of affected persons
        $affected_id_list = [$id];
        // - get value of URL external via object data
        // - person
        $dreg_auth_id = Authority::ID['GSN'];
        $uext_value = $urlExternalRepository->findValue($id, $dreg_auth_id);
        if (!is_null($uext_value)) {
            $item_id_list = $urlExternalRepository->findItemId($uext_value, $dreg_auth_id);
            $affected_id_list = array_merge($affected_id_list, $item_id_list);
        }

        $itemNameRoleRepository->updateByIdList($affected_id_list);

        $this->entityManager->flush();

        return new Response("delete ID ".$id);
    }

    /**
     * display edit form for new person
     */
    #[Route(path: '/edit/person/new', name: 'edit_person_new')]
    public function newList(Request $request) {
        $corpusId = $request->query->get('corpusId');
        $corpus_id_list = explode(',', $corpusId);

        if (!$this->checkAccess($corpus_id_list)) {
            return $this->render('home\message.html.twig', [
                'message' => 'Sie haben nicht die erforderlichen Rechte oder sind nicht angemeldet.'
            ]);
        }

        $person = $this->makePerson($corpus_id_list[0]);
        $person->getItem()->setFormIsExpanded(true);
        $person->getItem()->setFormType('insert');

        $template = 'edit_person/new_person.html.twig';

        return $this->renderEditElements($corpusId, $template, [
            'personList' => array($person),
            'count' => 1,
        ]);

    }

    /**
     * display edit form for new person
     */
    #[Route(path: '/edit/person/new-entry', name: 'edit_person_new_entry')]
    public function _newEntry(Request $request) {
        $corpusId = $request->query->get('corpusId');
        $corpus_id_list = explode(',', $corpusId);
        $person = $this->makePerson($corpus_id_list[0]);
        $person->getItem()->setFormIsExpanded(true);
        $person->getItem()->setFormType('insert');
        $template = 'edit_person/_item.html.twig';
        $personIndex = $request->query->get('current_idx');

        return $this->renderEditElements($corpusId, $template, [
            'person' => $person,
            'personIndex' => $personIndex,
        ]);

    }

    /**
     * create new Person with Item and empty default elements
     */
    private function makePerson($corpusId) {
        $current_user_id = intVal($this->getUser()->getId());
        $item = new Item($current_user_id);
        $person = new Person($item);
        // add empty elements for blank form sections
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
        $corpus_id_list = explode(',', $corpusId);

        $person->addEmptyDefaultElements($corpus_id_list[0], $auth_list);

        return $person;
    }

    /**
     * Returns template for new item property
     */
    #[Route(path: '/edit/person/new-property/{personIndex}', name: 'edit_person_new_property')]
    public function newProperty(Request $request,
                                int $personIndex) {

        $prop = new ItemProperty();

        // current_idx is set by JavaScript

        return $this->render('edit_person/_input_property.html.twig', [
            'editFormId' => self::EDIT_FORM_ID,
            'personIndex' => $personIndex,
            'current_idx' => $request->query->get('current_idx'),
            'prop' => $prop,
            'is_last' => true,
            'itemPropertyTypeList' => $this->getItemPropertyTypeList(),
        ]);

    }


    /**
     * Returns template for new role
     */
    #[Route(path: '/edit/person/new-role/{personIndex}', name: 'edit_person_new_role')]
    public function newRole(Request $request,
                            int $personIndex) {

        $role = new PersonRole();
        $role->setId(0);

        return $this->render('edit_person/_input_role.html.twig', [
            'editFormId' => self::EDIT_FORM_ID,
            'personIndex' => $personIndex,
            'current_idx' => $request->query->get('current_idx'),
            'role' => $role,
            'is_last' => true,
            'itemPropertyTypeList' => $this->getItemPropertyTypeList(),
        ]);

    }

    /**
     * Returns template for new role
     */
    #[Route(path: '/edit/person/new-role-property/{personIndex}/{roleIndex}', name: 'edit_person_new_role_property')]
    public function newRoleProperty(Request $request,
                                    int $personIndex,
                                    int $roleIndex) {

        $prop = new PersonRoleProperty();


        return $this->render('edit_person/_input_role_property.html.twig', [
            'editFormId' => self::EDIT_FORM_ID,
            'personIndex' => $personIndex,
            'roleIndex' => $roleIndex,
            'current_idx' => $request->query->get('current_idx'),
            'prop' => $prop,
            'is_last' => true,
            'itemPropertyTypeList' => $this->getItemPropertyTypeList(),
        ]);

    }

    private function getItemPropertyTypeList() {
        $repository = $this->entityManager->getRepository(itemPropertyType::class);
        $type_list = $repository->findAll();
        return UtilService::sortByFieldList($type_list, ['displayOrder', 'name']);
    }


    /**
     * Returns template for new reference
     */
    #[Route(path: '/edit/person/new-reference/{personIndex}', name: 'edit_person_new_reference')]
    public function newReference(Request $request,
                                 int $personIndex) {

        $reference = new ItemReference(0);

        return $this->render('edit_person/_input_reference.html.twig', [
            'editFormId' => self::EDIT_FORM_ID,
            'personIndex' => $personIndex,
            'current_idx' => $request->query->get('current_idx'),
            'ref' => $reference,
        ]);

    }

    /**
     * Returns template for new external ID
     */
    #[Route(path: '/edit/person/new-url-external/{personIndex}', name: 'edit_person_new_urlexternal')]
    public function newUrlExternal(Request $request,
                                 int $personIndex) {

        $urlExternal = new urlExternal();

        return $this->render('edit_person/_input_url_external.html.twig', [
            'editFormId' => self::EDIT_FORM_ID,
            'personIndex' => $personIndex,
            'current_idx' => $request->query->get('current_idx'),
            'urlext' => $urlExternal,
        ]);

    }

    /**
     * display query form for bishops; handle query
     */
    #[Route(path: '/edit/person/merge-query', name: 'edit_person_merge_query')]
    public function mergeQuery(Request $request,
                               FormFactoryInterface $formFactory) {

        $model = new PersonFormModel;
        // set defaults
        $model->editStatus = ['- alle -' => '- alle -'];
        $model->isOnline = true;
        $model->listSize = 10;
        $model->isEdit = true;

        $corpusId = $request->query->get('corpusId');
        $model->corpus = $corpusId;

        $status_choices = $this->statusChoices();

        $form = $formFactory->createNamed(
            'person_merge_query',
            EditPersonFormType::class,
            $model, [
                'statusChoices' => $status_choices,
            ]);

        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        $template_params = [
            'menuItem' => 'edit-menu',
            'form' => $form,
        ];


        if ($form->isSubmitted() && $form->isValid()) {
            $personRepository = $this->entityManager->getRepository(Person::class);


            $limit = 0; $offset = 0; $online_only = false;
            $id_all = $personRepository->findEditPersonIds($model, $limit, $offset);

            $count = count($id_all);

            $offset = $request->request->get('offset');
            $page_number = $request->request->get('pageNumber');

            // set offset to page begin
            if (!is_null($offset)) {
                $offset = intdiv($offset, $model->listSize) * $model->listSize;
            } elseif (!is_null($page_number) && $page_number > 0) {
                $page_number = min($page_number, intdiv($count, $model->listSize) + 1);
                $offset = ($page_number - 1) * $model->listSize;
            } else {
                $offset = 0;
            }

            $id_list = array_slice($id_all, $offset, $model->listSize);
            $person_list = $personRepository->findList($id_list);

            $template_params = [
                'menuItem' => 'edit-menu',
                'form' => $form,
                'count' => $count,
                'personList' => $person_list,
                'offset' => $offset,
                'pageSize' => $model->listSize,
            ];

        }

        $debug_flag = $request->query->get('debug');
        $template = $debug_flag ? 'merge_query_debug.html.twig' : 'merge_query.html.twig';

        return $this->render('edit_person/'.$template, $template_params);

    }

    #[Route(path: '/edit/person/merge-item-local/{corpusId}', name: 'edit_person_merge_local')] // merge items using form data; show form with combined data
    public function mergeItemLocal($corpusId, Request $request) {
        $itemRepository = $this->entityManager->getRepository(Item::class);
        $personRepository = $this->entityManager->getRepository(Person::class);
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $itemCorpusRepository = $this->entityManager->getRepository(ItemCorpus::class);

        $current_user_id = $this->getUser()->getId();

        // use EDIT_FORM_ID as the name attribute of the form in the template
        $form_data = $request->request->all(self::EDIT_FORM_ID);
        $person_index = array_keys($form_data)[0];
        // get first element independent from indexing
        $form_data = array_values($form_data)[0];

        // see person_edit/_merge_list.html.twig
        // - an item may have IDs for more than one corpus
        $iic_second_text = $request->query->get('selected');
        $iic_second_list = explode(",", $iic_second_text);
        // find merge candidate
        $second = null;
        foreach ($iic_second_list as $iic) {
            $parts = UtilService::splitIdInCorpus($iic);
            $item_id_q  = $itemCorpusRepository->findItemIdByCorpusAndId($parts['corpusId'], $parts['idInCorpus']);
            if (!is_null($item_id_q) and count($item_id_q) > 0) {
                $item_id = array_values(array_column($item_id_q, 'itemId'))[0];
                $second = $itemRepository->find($item_id);
                break;
            }
        }

        $id_list = array($form_data['id']);
        if (is_null($second)) {
            $msg = "Zu {$iic_second_text} (angegegeben im Feld 'identisch mit') wurde keine Person gefunden.";
            $q_person = $personRepository->findList($id_list);
            $person = $q_person[0];
            $person->getItem()->getInputError()->add(new InputError("status", $msg));
        } elseif ($second->getId() == $form_data['id']) {
            $msg = "Eine Person kann nicht mit sich selbst zusammengeführt werden.";
            $q_person = $personRepository->findList($id_list);
            $person = $q_person[0];
            $person->getItem()->getInputError()->add(new InputError("status", $msg));
        } else {
            $id_list[] = $second->getId();

            // get parent data
            $parent_list = $personRepository->findList($id_list);

            // create new person set corpus data
            $item = new Item($current_user_id);
            $person = new Person($item);
            $item->setEditStatus(Item::DEFAULT_STATUS_MERGE);
            $corpus_list_new = $person->getItem()->getItemCorpus();
            $corpus_found_list = array(); // add each corpus_id only once
            foreach ($parent_list as $parent) {
                $corpus_list = $parent->getItem()->getItemCorpus();
                foreach ($corpus_list as $item_corpus) {
                    $corpus_id = $item_corpus->getCorpusId();
                    if (!in_array($corpus_id, $corpus_found_list)) {
                        $corpus_found_list[] = $corpus_id;
                        $item_corpus_new = clone $item_corpus;
                        $item_corpus_new->setItem($item);
                        $item_corpus_new->setItemId($item->getId());
                        $corpus_list_new->add($item_corpus_new);
                    }
                }
            }

            $person->merge($parent_list);
            $item->setMergeStatus('merging');

            $item->setFormIsEdited(true);
        }

        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
        $person->extractSeeAlso();
        $person->addEmptyDefaultElements($corpusId, $auth_list);

        $person->getItem()->setFormIsExpanded(true);
        $person->getItem()->setFormType("edit");

        $template = "edit_person/_item.html.twig";

        return $this->renderEditElements($corpusId, $template, [
            'person' => $person,
            'personIndex' => $person_index,
        ]);

    }

    /**
     * edit a single entry
     */
    #[Route(path: '/edit/person/edit-single/{someid}', name: 'edit_person_edit_single')]
    public function editSingle(Request $request,
                               $someid) {

        $personRepository = $this->entityManager->getRepository(Person::class);

        $model = new PersonFormModel;
        $model->someid = $someid;

        $limit = null;
        $offset = null;
        $id_all = $personRepository->findEditPersonIds($model, $limit, $offset);

        $person_list = $personRepository->findList($id_all);

        // add empty role, reference and id external if not present
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
        foreach ($person_list as $person) {
            $person->addEmptyDefaultElements($auth_list);
        }

        if (count($person_list) == 1) {
            $person_list[0]->getItem()->setFormIsExpanded(1);
        }

        $template = 'edit_person/new_person.html.twig';

        return $this->renderEditElements('',$template, [
            'personList' => $person_list,
            'form' => null,
            'title' => null,
            'count' => count($person_list)
        ]);

    }

    /**
     * split merged item, show parents in edit forms
     *
     *
     */
    #[Route(path: '/edit/person/split-item/{corpusId}/{id}', name: 'edit_person_split_item')]
    public function splitItem($corpusId, int $id) {
        $itemRepository = $this->entityManager->getRepository(Item::class);
        $personRepository = $this->entityManager->getRepository(Person::class);
        $itemNameRoleRepository = $this->entityManager->getRepository(ItemNameRole::class);
        $urlExternalRepository = $this->entityManager->getRepository(UrlExternal::class);

        $item = $itemRepository->find($id);

        // set status values for parents and child
        $person_list = array();
        if (!is_null($item)) {
            $parent_list = $itemRepository->findParents($item);
            // dd ($parent_list);

            $item->setIsDeleted(1);
            $item->setMergeStatus('orphan');
            $item->setIsOnline(0);
            $orphan_person = $personRepository->find($id);

            $affected_id_list = array($id);
            $uext_value = $orphan_person->getItem()->getGsn();
            if (!is_null($uext_value)) {
                $item_id_list = $urlExternalRepository->findItemId($uext_value, Authority::ID['GSN']);
                $affected_id_list = array_merge($affected_id_list, $item_id_list);
            }

            $id_list = array();
            foreach($parent_list as $parent_item) {
                $this->editService->updateIsOnline($parent_item);

                // merge_status
                $parent_parent_list = $itemRepository->findParents($parent_item);
                $merge_status = count($parent_parent_list) > 0 ? 'child' : 'original';
                $parent_item->setMergeStatus($merge_status);

                $parent_item->setMergedIntoId(null);

                $id_list[] = $parent_item->getId();
                $parent_item->setFormIsExpanded(1);
            }

            $with_deleted = false;
            $with_ancestor = true;
            $parent_list = $personRepository->findList($id_list, $with_deleted, $with_ancestor);

            $this->entityManager->flush();
            // set up affected_id_list to update item_name_role
            foreach ($parent_list as $parent) {
                $affected_id_list[] = $parent->getId();
                $uext_value = $parent->getItem()->getGsn();
                if (!is_null($uext_value)) {
                    $item_id_list = $urlExternalRepository->findItemId($uext_value, Authority::ID['GSN']);
                    $affected_id_list = array_merge($affected_id_list, $item_id_list);
                }
            }
            $affected_id_list = array_unique($affected_id_list);
            $itemNameRoleRepository->updateByIdList($affected_id_list);

        } else {
            throw $this->createNotFoundException('ID is nicht gültig: '.$id);
        }

        // add empty elements for blank form sections (after flush)
        $authorityRepository = $this->entityManager->getRepository(Authority::class);
        $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
        foreach ($parent_list as $parent) {
            $parent->extractSeeAlso();
            $parent->addEmptyDefaultElements($corpusId, $auth_list);

            $parent->getItem()->setFormType('insert');
        }

        $template = 'edit_person/new_person.html.twig';

        return $this->renderEditElements($corpusId, $template, [
            'personList' => $parent_list,
            'form' => null,
            'count' => count($parent_list),
        ]);

    }

    /**
     * display query form for doublets
     */
    #[Route(path: '/edit/person/query-doublet/{corpusId}', name: 'edit_person_query_doublet')]
    public function queryDoublet(string $corpusId, Request $request) {
        // parameters
        $list_size = 20;

        $urlExternalRepository = $this->entityManager->getRepository(UrlExternal::class);

        // set defaults
        $status_choices = $this->statusChoices();

        $authority_choices = [
            'GSN' => Authority::ID['GSN'],
            'GND' => Authority::ID['GND'],
            'Wikidata' => Authority::ID['Wikidata']
        ];

        $model = [
            'editStatus' => ['online'],
            'authority' => Authority::ID['GSN'],
        ];

        $form = $this->createFormBuilder($model)
                     ->setMethod('GET')
                     ->add('editStatus', ChoiceType::class, [
                         'required' => false,
                         'label' => 'Status',
                         'multiple' => true,
                         'expanded' => false,
                         'choices' => $status_choices,
                     ])
                     ->add('authority', ChoiceType::class, [
                         'label' => 'Normdaten',
                         'choices' => $authority_choices,
                     ])
                     ->getForm();


        $offset = 0;

        $form->handleRequest($request);
        $model = $form->getData();

        $person_list = array();
        $personRepository = $this->entityManager->getRepository(Person::class);
        $itemRepository = $this->entityManager->getRepository(Item::class);


        $id_all = $urlExternalRepository->personDoubletIds($model);
        $count = count($id_all);

        //
        $offset = $request->query->get('offset');
        $page_number = $request->query->get('pageNumber');

            // set offset to page begin
            if (!is_null($offset)) {
                $offset = intdiv($offset, $list_size) * $list_size;
            } elseif (!is_null($page_number) && $page_number > 0) {
                $page_number = min($page_number, intdiv($count, $list_size) + 1);
                $offset = ($page_number - 1) * $list_size;
                // this may happen if elements were deleted
                while ($offset >= $count) {
                    $offset -= $list_size;
                }
            } else {
                $offset = 0;
            }

            $id_list = array_slice($id_all, $offset, $list_size);

            $person_list = $personRepository->findList($id_list);

            // add empty role, reference and url external if not present
            $authorityRepository = $this->entityManager->getRepository(Authority::class);
            $auth_list = $authorityRepository->findList(Authority::ESSENTIAL_ID_LIST);
            foreach ($person_list as $person) {
                $person->extractSeeAlso();
                $cid = $person->getItem()->getCorpusId();
                $person->addEmptyDefaultElements($cid, $auth_list);
            }

            $template_params = [
                'personList' => $person_list,
                'form' => $form,
                'error_list' => null,
                'count' => $count,
                'offset' => $offset,
                'pageSize' => $list_size,
            ];

        $template = 'edit_person/query_doublet.html.twig';
        return $this->renderEditElements($corpusId, $template, $template_params);

    }

    private function checkAccess($corpus_id_list) {
        foreach($corpus_id_list as $cid) {
            if ($this->isGranted('ROLE_EDIT_'.strtoupper($cid))) {
                     return true;
            }
        }
        return false;
    }

}
