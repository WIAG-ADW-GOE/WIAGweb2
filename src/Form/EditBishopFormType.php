<?php
namespace App\Form;

use App\Entity\Item;
use App\Entity\FacetChoice;

use App\Form\Model\BishopFormModel;

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


class EditBishopFormType extends AbstractType
{

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'data_class' => BishopFormModel::class,
            'status_choices' => 'online',
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
            // 2022-09-21 delete via button?
            // ->add('isDeleted', ChoiceType::class, [
            //     'label' => 'Anzeige',
            //     'choices' => [
            //         'aktiv' => 0,
            //         'inaktiv (gelöscht)' => 1,
            //     ],
            //     'expanded' => true,
            //     'multiple' => false,
            // ])
            ->add('editStatus', ChoiceType::class, [
                'required' => false,
                'label' => 'Status',
                'multiple' => true,
                'expanded' => false,
                'choices' => $options['status_choices'],
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
            ]);

    }
}
