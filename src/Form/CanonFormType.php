<?php
namespace App\Form;

use App\Entity\Item;
use App\Entity\FacetChoice;
use App\Form\Model\PersonFormModel;
use App\Repository\ItemRepository;
use App\Repository\CanonLookupRepository;

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


class CanonFormType extends AbstractType
{
    private $repository;

    public function __construct(CanonLookupRepository $repository) {
        $this->repository = $repository;
    }

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'data_class' => PersonFormModel::class,
            'forceFacets' => false,
        ]);

    }

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $model = $options['data'] ?? null;
        $forceFacets = $options['forceFacets'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Vor- oder Nachname',
                ],
            ])
            ->add('institution', TextType::class, [
                'label' => 'Domstift',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Domstift',
                ],
            ])
            ->add('office', TextType::class, [
                'label' => 'Amt',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Amtsbezeichnung',
                ],
            ])
            ->add('place', TextType::class, [
                'label' => 'Ort',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ort',
                    'size' => '8',
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
            ->add('itemTypeId', HiddenType::class)
            ->add('stateFctInst', HiddenType::class, [
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


        if ($forceFacets) {
            $this->createFacetInstitution($builder, $model);
            $this->createFacetOffice($builder, $model);
            $this->createFacetPlace($builder, $model);
            $this->createFacetUrl($builder, $model);
        }


        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function($event) {
                $data = $event->getData();
                $model = PersonFormModel::newByArray($data);

                $this->createFacetInstitution($event->getForm(), $model);
                $this->createFacetOffice($event->getForm(), $model);
                $this->createFacetPlace($event->getForm(), $model);
                $this->createFacetUrl($event->getForm(), $model);
            });

    }

    public function createFacetInstitution($form, $modelIn) {
        // do not filter by filter domstift itsself
        $model = clone $modelIn;
        $model->facetInstitution = null;

        $domstifte = $this->repository->countCanonDomstift($model);

        $choices = array();
        foreach($domstifte as $domstift) {
            $choices[] = new FacetChoice($domstift['name'], $domstift['n']);
        }

        // add selected fields, that are not contained in $choices
        $choicesIn = $modelIn->facetInstitution;
        FacetChoice::mergeByName($choices, $choicesIn);


        if ($domstifte) {
            $form->add('facetInstitution', ChoiceType::class, [
                'label' => 'Filter Domstift',
                'expanded' => true,
                'multiple' => true,
                'choices' => $choices,
                'choice_label' => ChoiceList::label($this, 'label'),
                'choice_value' => 'name',
            ]);

        }
    }

    public function createFacetPlace($form, $modelIn) {
        // do not filter by facet place itsself
        $model = clone $modelIn;
        $model->facetPlace = null;

        $places = $this->repository->countCanonPlace($model);

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

    public function createFacetOffice($form, $modelIn) {
        // do not filter by facet office itsself
        $model = clone $modelIn;
        $model->facetOffice = null;

        $offices = $this->repository->countCanonOffice($model);

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

    public function createFacetUrl($form, $modelIn) {
        // do not filter by facet URL itsself
        $model = clone $modelIn;
        $model->facetUrl = null;

        $url_list = $this->repository->countCanonUrl($model);

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
