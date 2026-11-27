<?php

namespace App\Form;

use App\Entity\Professor;
use App\Entity\Student;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class ContactProfessorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Student $student */
        $student = $options['student'];

        $builder
            ->add('professor', EntityType::class, [
                'class' => Professor::class,
                'placeholder' => 'Sélectionner un professeur',
                'choice_label' => 'name',
                'multiple' => false,
                'expanded' => false,
                'label' => 'Professeur',
                // on ne propose que les professeurs de la classe de l'étudiant
                'choices' => $student->getClassLevel()?->getProfessors() ?? [],
                'constraints' => new NotNull([
                    'message' => 'Sélectionnez un professeur',
                ]),
            ])
            ->add('subject', TextType::class, [
                'label' => 'Objet',
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => [
                    'rows' => 6,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'student' => null,
        ]);
    }
}
