<?php
namespace App\Form;

use App\Entity\Item;
use App\Entity\FacetChoice;
use App\Form\Model\PriestUtFormModel;
use App\Repository\PersonRepository;

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


class PriestUtFormType extends AbstractType
{
    private $repository;

    public function __construct(PersonRepository $repository) {
        $this->repository = $repository;
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([
            'data_class' => PriestUtFormModel::class,
            'forceFacets' => false,
        ]);

    }

    public function buildForm(FormBuilderInterface $builder, array $options): void {
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
            ->add('birthplace', TextType::class, [
                'label' => 'Geburtsort',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Geburtsort',
                ],
            ])
            ->add('religiousOrder', TextType::class, [
                'label' => 'Orden',
                'required' => false,
                'attr' => [
                    'placeholder' => 'OSB, OP, OCarm, ...',
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
                    'placeholder' => 'ID',
                    'size' => '25',
                ],
            ])
            ->add('stateFctRo', HiddenType::class, [
                'mapped' => false,
            ]);

        if ($forceFacets) {
            $this->createFacetOrder($builder, $model);
        }


        // add facets with current model data
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function ($event) {
                $data = $event->getData();
                if (!$data) {
                    $model = new PriestUtFormModel();
                } else {
                    $model = PriestUtFormModel::newByArray($data);
                }

                $this->createFacetOrder($event->getForm(), $model);
            });
    }

    public function createFacetOrder($form, $modelIn): void {
        // do not filter by dioceses themselves
        $model = clone $modelIn;
        $model->facetOrder = null;

        $order_list = $this->repository->countPriestUtOrder($model);

        $choices = array();
        foreach($order_list as $order) {
            $choices[] = new FacetChoice($order['name'], $order['n']);
        }

        // add selected fields, that are not contained in $choices
        $choicesIn = $modelIn->facetReligiousOrder;
        FacetChoice::mergeByName($choices, $choicesIn);

        if ($order_list) {
            $form->add('facetReligiousOrder', ChoiceType::class, [
                'label' => 'Filter Orden',
                'expanded' => true,
                'multiple' => true,
                'choices' => $choices,
                'choice_label' => 'label', //TODO insert sensible labels again
                'choice_value' => 'name',
            ]);
        }
    }

    public function createFacetDiocese($form, $modelIn): void {
        // do not filter by dioceses themselves
        $model = clone $modelIn;
        $model->facetDiocese = null;

        $dioceses = $this->repository->countPriestUtDiocese($model);

        $choices = array();
        foreach($dioceses as $diocese) {
            $choices[] = new FacetChoice($diocese['name'], $diocese['n']);
        }

        // add selected fields, that are not contained in $choices
        $choicesIn = $modelIn->facetDiocese;
        FacetChoice::mergeByName($choices, $choicesIn);

        if ($dioceses) {
            $form->add('facetDiocese', ChoiceType::class, [
                'label' => 'Filter Bistum',
                'expanded' => true,
                'multiple' => true,
                'choices' => $choices,
                'choice_label' => 'label', //TODO insert sensible labels again
                'choice_value' => 'name',
            ]);
            // it is not possible to set data for field stateFctDioc
        }
    }

    public function createFacetOffice($form, $modelIn): void {
        // do not filter by office themselves
        $model = clone $modelIn;
        $model->facetOffice = null;

        $offices = $this->repository->countPriestUtOffice($model);

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
                'choice_label' => 'label', //TODO insert sensible labels again
                'choice_value' => 'name',
            ]);

        }
    }

}
