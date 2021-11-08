<?php
namespace App\Form;

use App\Entity\FacetChoice;
use App\Form\Model\BishopFormModel;
use App\Repository\PersonRepository;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;


class DioceseFormType extends AbstractType {

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
        ]);

    }

    public function buildForm(FormBuilderInterface $builder, array $options) {

        $builder
            ->add('name', TextType::class, [
                         'label' => false,
                         'required' => false,
                         'attr' => [
                             'placeholder' => 'Erzbistum/Bistum',
                             'size' => 25,
                         ],
                     ])
            ->add('searchHTML', SubmitType::class, [
                'label' => 'Suche',
                'attr' => [
                    'class' => 'btn btn-secondary btn-light',
                ],
            ]);

    }

}
