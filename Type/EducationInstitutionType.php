<?php
namespace Ice\FormBundle\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class EducationInstitutionType extends AbstractType
{
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'expanded' => false,
            'multiple' => false,
            'choices' => array(
                '4901' => 'UK state school',
                '4911' => 'UK independent school',
                '4921' => 'UK independent school',
                '4931' => 'Any non-UK institution',
                '4941' => 'UK Higher Education Institute',
            )
        ));
    }

    public function getParent()
    {
        return 'choice';
    }

    public function getName()
    {
        return 'educationInstitution';
    }
}