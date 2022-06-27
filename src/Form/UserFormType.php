<?php

namespace App\Form;

use App\Entity\UserWiag;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('givenname', null, [
                'label' => 'Vorname'
            ])
            ->add('familyname', null, [
                'label' => 'Nachname'
            ])
            ->add('email', null, [
                'label' => 'E-Mail'
            ])
            ->add('roles', ChoiceType::class, [
                'mapped' => true,
                'label' => 'Rollen',
                'expanded' => true,
                'multiple' => true,
                'choices' => [
                    'Redaktion' => 'ROLE_EDIT',
                    'Datenbank' => 'ROLE_DB_EDIT',
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'type' => PasswordType::class,
                'invalid_message' => 'Die Passwörter müssen übereinstimmen.',
                'mapped' => false,
                'first_options' => [
                    'label' => 'Passwort',
                    'attr' => ['autocomplete' => 'new-password'],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Please enter a password',
                        ]),
                        new Length([
                            'min' => 6,
                            'minMessage' => 'Your password should be at least {{ limit }} characters',
                            // max length allowed by Symfony for security reasons
                            'max' => 4096,
                        ]),
                    ],
                ],
                'second_options' => [
                    'label' => 'Passwort (Wiederholung)'
                ]

            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserWiag::class,
        ]);
    }
}
