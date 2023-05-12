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


class EditPersonFormType extends AbstractType
{

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'data_class' => PersonFormModel::class,
            'statusChoices' => [
                '- alle -' => null,
            ],
            'sortByChoices' => [
                'Name' => 'name',
            ]
        ]);

    }

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $model = $options['data'] ?? null;
        $itemTypeId = $model->itemTypeId;

        $institution_label_list = [
            '4' => 'Erzbistum/Bistum',
            '5' => 'Domstift/Kloster'
        ];

        $institution_label = $institution_label_list[$itemTypeId];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Vor- oder Nachname',
                ],
            ])
            ->add('institution', TextType::class, [
                'label' => $institution_label,
                'required' => false,
                'attr' => [
                    'placeholder' => $institution_label,
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
            ->add('reference', TextType::class, [
                'label' => 'Referenz',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Band',
                    'size' => '45',
                ],
            ])
            ->add('editStatus', ChoiceType::class, [
                'required' => false,
                'label' => 'Status',
                'multiple' => true,
                'expanded' => false,
                'choices' => $options['statusChoices'],
            ])
            ->add('commentDuplicate', TextType::class, [
                'label' => 'identisch mit',
                'required' => false,
            ])
            ->add('comment', TextType::class, [
                'label' => 'Kommentar (red.)',
                'required' => false,
            ])
            ->add('dateCreated', TextType::class, [
                'label' => 'angelegt',
                'required' => false,
                'attr' => [
                    'placeholder' => 'am/von-bis',
                    'size' => 17,
                ]
            ])
            ->add('dateChanged', TextType::class, [
                'label' => 'geändert',
                'required' => false,
                'attr' => [
                    'placeholder' => 'am/von-bis',
                    'size' => 17,
                ]
            ])
            ->add('listSize', NumberType::class, [
                'label' => 'Anzahl',
                'required' => false,
                'attr' => [
                    'size' => '3',
                ]
            ])
            ->add('sortBy', ChoiceType::class, [
                'required' => true,
                'label' => 'Sortierung',
                'multiple' => false,
                'expanded' => false,
                'choices' => $options['sortByChoices'],
            ])
            ->add('isEdit', HiddenType::class)
            // data set via JavaScript
            ->add('sortOrder', HiddenType::class);

        if ($itemTypeId == 5) { // canons
            $builder->add('place', TextType::class, [
                'label' => 'Ort',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ort',
                ],
            ]);
        } else {
        }
    }
}
