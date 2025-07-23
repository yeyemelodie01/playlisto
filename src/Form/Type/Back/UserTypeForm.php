<?php

namespace App\Form\Type\Back;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserTypeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', TextType::class, [
                    'label' => 'email',
                    'attr' => [
                        'placeholder' => 'placeholder.email',
                        'class' => 'form-control',
                    ],
            ])
            ->add('username', TextType::class, [
                'label' => 'username',
                'attr' => [
                    'placeholder' => 'placeholder.username',
                    'class' => 'form-control',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
            ])
            ->add('password', PasswordType::class, [
                'label' => 'password',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'placeholder.password',
                    'class' => 'form-control',
                ],
            ])
        ;
    }

    /**
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return '';
    }

    /**
     * Configure options for the form.
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'User',
        ]);
    }
}
