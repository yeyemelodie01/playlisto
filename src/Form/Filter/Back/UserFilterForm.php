<?php

namespace App\Form\Filter\Back;

use Spiriit\Bundle\FormFilterBundle\Filter\Condition\ConditionInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type\DateRangeFilterType;

class UserFilterForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('createdAt', DateRangeFilterType::class, [
                'label' => 'Période',
                'left_date_options' => [
                    'label' => 'Du',
                    'widget' => 'single_text',
                    'attr' => ['class' => 'form-control'],
                ],
                'right_date_options' => [
                    'label' => 'Au',
                    'widget' => 'single_text',
                    'attr' => ['class' => 'form-control'],
                ],
                'required' => false,
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'User',
            'csrf_protection' => false,
        ]);
    }

    /**
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'user_filter';
    }
}
