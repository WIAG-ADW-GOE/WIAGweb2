<?php
namespace App\Form;

use App\Entity\Item;
use App\Entity\FacetChoice;
use App\Form\Model\PersonFormModel;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Routing\RouterInterface;


class PersonFormType extends AbstractType
{
    const FILTER_MAP = [
        'can' => ['cap', 'ofc', 'plc', 'url'],
        'epc' => ['dioc', 'ofc'],
        'ibe' => ['dioc', 'ofc'],
    ];

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'data_class' => PersonFormModel::class,
            'forceFacets' => false,
            'repository' => null,
            'action' => "",
        ]);

    }

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $model = $options['data'] ?? null;
        $forceFacets = $options['forceFacets'];
        $repository = $options['repository'];
        $action = $options['action'];
        $corpusId = $model->corpus;

        $filter_map = self::FILTER_MAP[$corpusId];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Vor- oder Nachname',
                ],
            ])
            ->add('office', TextType::class, [
                'label' => 'Amt',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Amtsbezeichnung',
                ],
            ])
            ->add('year', NumberType::class, [
                'label' => 'Jahr',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Jahreszahl',
                    'size' => '8',
                ],
            ])
            ->add('someid', TextType::class, [
                'label' => 'Nummer',
                'required' => false,
                'attr' => [
                    'placeholder' => 'GSN, GND, Wikidata, VIAF',
                    'size' => '25',
                ],
            ])
            ->add('corpus', HiddenType::class, [
            ])
            ->add('stateFctDioc', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('stateFctCap', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('stateFctOfc', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('stateFctPlc', HiddenType::class, [
                 'mapped' => false,
            ])
            ->add('stateFctUrl', HiddenType::class, [
                 'mapped' => false,
            ]);

        if (in_array('dioc', $filter_map)) {
            $builder->add('diocese', TextType::class, [
                'label' => 'Erzbistum/Bistum',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Erzbistum/Bistum',
                ],
            ]);
        }

        if (in_array('cap', $filter_map)) {
            $builder
                ->add('domstift', TextType::class, [
                    'label' => 'Domstift',
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Domstift',
                    ],
                ]);
        }
        if (in_array('plc', $filter_map)) {
            $builder
                ->add('place', TextType::class, [
                    'label' => 'Ort',
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Ort',
                        'size' => '8',
                    ],
                ]);
        }

        if ($forceFacets) {
            if (in_array('ofc', $filter_map)) {
                $this->createFacetOffice($builder, $model, $repository);
            }
            if (in_array('cap', $filter_map)) {
                $this->createFacetDomstift($builder, $model, $repository);
            }
            if (in_array('plc', $filter_map)) {
                $this->createFacetPlace($builder, $model, $repository);
            }
            if (in_array('url', $filter_map)) {
                $this->createFacetUrl($builder, $model, $repository);
            }
            if (in_array('dioc', $filter_map)) {
                $this->createFacetDiocese($builder, $model, $repository);
            }
        }

        if ($action != "") {
            $builder->setAction($action);
        }

        // add facets with current model data
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function ($event) use ($repository, $filter_map) {
                $data = $event->getData();
                if (!$data) {
                    $model = new PersonFormModel();
                } else {
                    $model = PersonFormModel::newByArray($data);
                }

                $form = $event->getForm();
                if (in_array('ofc', $filter_map)) {
                    $this->createFacetOffice($form, $model, $repository);
                }
                if (in_array('cap', $filter_map)) {
                    $this->createFacetDomstift($form, $model, $repository);
                }
                if (in_array('plc', $filter_map)) {
                    $this->createFacetPlace($form, $model, $repository);
                }
                if (in_array('url', $filter_map)) {
                    $this->createFacetUrl($form, $model, $repository);
                }
                if (in_array('dioc', $filter_map)) {
                    $this->createFacetDiocese($form, $model, $repository);
                }
            });
    }

    public function createFacetDiocese($form, $modelIn, $repository) {
        // do not filter by dioceses themselves
        $model = clone $modelIn;
        $model->facetDiocese = null;

        $dioceses = $repository->countDiocese($model);

        $choices = array();
        foreach($dioceses as $diocese) {
            $choices[] = new FacetChoice($diocese['name'], $diocese['n']);
        }

        // add selected fields, that are not contained in $choices
        $choicesIn = $modelIn->facetDomstift;
        FacetChoice::mergeByName($choices, $choicesIn);

        if ($dioceses) {
            $form->add('facetDiocese', ChoiceType::class, [
                'label' => 'Filter Bistum',
                'expanded' => true,
                'multiple' => true,
                'choices' => $choices,
                'choice_label' => ChoiceList::label($this, 'label'),
                'choice_value' => 'name',
            ]);
            // it is not possible to set data for field stateFctDioc
        }
    }

    public function createFacetOffice($form, $modelIn, $repository) {
        // do not filter by office themselves
        $model = clone $modelIn;
        $model->facetOffice = null;

        $offices = $repository->countOffice($model);

        $choices = array();

        foreach($offices as $office) {
            $choices[] = new FacetChoice($office['name'], $office['n']);
        }

        // add selected fields, that are not contained in $choices
        $choicesIn = $modelIn->facetOffice;
        FacetChoice::mergeByName($choices, $choicesIn);

        if ($offices) {
            $form->add('facetOffice', ChoiceType::class, [
                'label' => 'Filter Amt',
                'expanded' => true,
                'multiple' => true,
                'choices' => $choices,
                'choice_label' => ChoiceList::label($this, 'label'),
                'choice_value' => 'name',
            ]);

        }
    }

    public function createFacetDomstift($form, $modelIn, $repository) {
        // do not filter by filter domstift itsself
        $model = clone $modelIn;
        $model->facetDomstift = null;

        $domstifte = $repository->countDomstift($model);

        $choices = array();
        foreach($domstifte as $domstift) {
            $choices[] = new FacetChoice($domstift['name'], $domstift['n']);
        }

        // add selected fields, that are not contained in $choices
        $choicesIn = $modelIn->facetDomstift;
        FacetChoice::mergeByName($choices, $choicesIn);


        if ($domstifte) {
            $form->add('facetDomstift', ChoiceType::class, [
                'label' => 'Filter Domstift',
                'expanded' => true,
                'multiple' => true,
                'choices' => $choices,
                'choice_label' => ChoiceList::label($this, 'label'),
                'choice_value' => 'name',
            ]);

        }
    }

    public function createFacetPlace($form, $modelIn, $repository) {
        // do not filter by facet place itsself
        $model = clone $modelIn;
        $model->facetPlace = null;

        $places = $repository->countPlace($model);

        $choices = array();
        foreach($places as $place) {
            $choices[] = new FacetChoice($place['name'], $place['n']);
        }

        // add selected fields, that are not contained in $choices
        $choicesIn = $modelIn->facetPlace;
        FacetChoice::mergeByName($choices, $choicesIn);


        if ($places) {
            $form->add('facetPlace', ChoiceType::class, [
                'label' => 'Filter Ort',
                'expanded' => true,
                'multiple' => true,
                'choices' => $choices,
                'choice_label' => ChoiceList::label($this, 'label'),
                'choice_value' => 'name',
            ]);

        }
    }

    public function createFacetUrl($form, $modelIn, $repository) {
        // do not filter by facet URL itsself
        $model = clone $modelIn;
        $model->facetUrl = null;

        $url_list = $repository->countUrl($model);

        $choices = array();

        foreach($url_list as $url) {
            $choices[] = new FacetChoice($url['name'], $url['n']);
        }

        // add selected fields, that are not contained in $choices
        $choicesIn = $modelIn->facetUrl;
        FacetChoice::mergeByName($choices, $choicesIn);

        if ($url_list) {
            $form->add('facetUrl', ChoiceType::class, [
                'label' => 'Filter Externe URL',
                'expanded' => true,
                'multiple' => true,
                'choices' => $choices,
                'choice_label' => ChoiceList::label($this, 'label'),
                'choice_value' => 'name',
            ]);

        }
    }

}
