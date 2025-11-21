<?php

namespace App\Form\Type\Back;

use App\Entity\Question;
use App\Enum\QuestionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuestionTypeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('surveyId', NumberType::class, [
              'disabled' => true,
                'label' => 'Id du questionnaire',
                'attr' => [
                    'class' => 'disabled bg-gray-100 text-gray-600',
                    'style' => 'appearance: textfield; cursor: default;',
                ]
            ])
            ->add('label', TextType::class, [
                'required' => true,
                'label' => 'Libellé',
                'attr' => [
                    'placeholder' => "Texte de la question..."
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de la question',
                'placeholder' => "Sélectionnez un type",
                'choices' => QuestionType::cases(),
                'choice_label' => fn(QuestionType $questionType) => $questionType->value,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Question::class,
            'translation_domain' => 'Question',
        ]);
    }
}
