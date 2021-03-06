<?php
namespace App\Form;

use App\Entity\Item;
use App\Entity\FacetChoice;
use App\Form\Model\BishopFormModel;
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


class BishopFormType extends AbstractType
{
    private $repository;

    public function __construct(ItemRepository $repository) {
        $this->repository = $repository;
    }

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'data_class' => BishopFormModel::class,
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
            ->add('diocese', TextType::class, [
                'label' => 'Erzbistum/Bistum',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Erzbistum/Bistum',
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
            ->add('stateFctDioc', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('stateFctOfc', HiddenType::class, [
                'mapped' => false,
            ]);

        if ($forceFacets) {
            $this->createFacetDiocese($builder, $model);
            $this->createFacetOffice($builder, $model);
        }

        // add facets with current model data
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function ($event) {
                $data = $event->getData();
                if (!$data) {
                    $model = new BishopFormModel();
                } else {
                    $model = BishopFormModel::newByArray($data);
                }

                $this->createFacetDiocese($event->getForm(), $model);
                $this->createFacetOffice($event->getForm(), $model);
            });
    }

    public function createFacetDiocese($form, $modelIn) {
        // do not filter by dioceses themselves
        $model = clone $modelIn;
        $model->facetDiocese = null;

        $dioceses = $this->repository->countBishopDiocese($model);

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
                'choice_label' => ChoiceList::label($this, 'label'),
                'choice_value' => 'name',
            ]);
            // it is not possible to set data for field stateFctDioc
        }
    }

    public function createFacetOffice($form, $modelIn) {
        // do not filter by office themselves
        $model = clone $modelIn;
        $model->facetOffice = null;

        $itemTypeId = Item::ITEM_TYPE_ID['Bischof'];
        $offices = $this->repository->countBishopOffice($model);

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
