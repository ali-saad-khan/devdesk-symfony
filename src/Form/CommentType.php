<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Comment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Comment> */
final class CommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('body', TextareaType::class, ['label' => 'Reply', 'attr' => ['rows' => 4]]);
        if ($options['allow_internal']) {
            $builder->add('internal', CheckboxType::class, ['required' => false, 'label' => 'Internal note (agents only)', 'mapped' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'allow_internal' => false]);
        $resolver->setAllowedTypes('allow_internal', 'bool');
    }
}
