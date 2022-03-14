<?php
namespace App\Form;

use App\Entity\Item;
use App\Entity\FacetChoice;
use App\Form\Model\CanonFormModel;
use App\Repository\ItemRepository;

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

    public function __construct(ItemRepository $repository) {
        $this->repository = $repository;
    }

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'data_class' => CanonFormModel::class,
            'forceFacets' => false,
        ]);

    }

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $model = $options['data'] ?? null;

        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Vor- oder Nachname',
                ],
            ])
            ->add('domstift', TextType::class, [
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
            ->add('stateFctDmt', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('stateFctOfc', HiddenType::class, [
                'mapped' => false,
            ]);
        // TODO 2022-01-26
            // ->add('stateFctPlc', HiddenType::class, [
            //     'mapped' => false,
            // ]);

        // TODO 2022-02-25
        // Facetten
        // if ($options['forceFacets']) {
        //     $this->createFacets($builder, $model);
        //     // $this->createFacetOffice($builder, $model);
        //     // $this->createFacetPlace($builder, $model);
        // }


        // $builder->addEventListener(
        //     FormEvents::PRE_SUBMIT,
        //     function($event) {
        //         $data = $event->getData();
        //         $model = CanonFormModel::newByArray($data);
        //         $this->createFacets($event->getForm(), $model);
        //         // $this->createFacetOffice($event->getForm(), $model);
        //     });

    }

    public function createFacets($form, $modelIn) {
        // do not filter by domstift themselves
        $model = clone $modelIn;
        $model->facetDomstift = null;

        $domstifte = $this->repository->countCanonDomstift($model);

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

    public function createFacetOffice($form, $modelIn) {
        // do not filter by office themselves
        $model = clone $modelIn;
        $model->facetOffice = null;

        $itemTypeId = Item::ITEM_TYPE_ID['Domherr'];
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

}
