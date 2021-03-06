<?php

namespace Ice\FormBundle\Process\CourseRegistration\EventSubscriber;

use Ice\FormBundle\Process\CourseRegistration\Step\WeekendAccommodation\AccommodationRequirementsType;
use Ice\FormBundle\Process\CourseRegistration\Step\WeekendAccommodation\DietaryRequirementsType;
use Ice\FormBundle\Process\CourseRegistration\Step\WeekendAccommodation\WeekendAccommodation;
use Ice\VeritasClientBundle\Entity\Course;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class WeekendAccommodationSubscriber implements EventSubscriberInterface
{
    const CATEGORY_ACCOMMODATION = 6;
    const CATEGORY_BED_AND_BREAKFAST_ACCOMMODATION = 7;
    const CATEGORY_PLATTER = 8;

    /**
     * @var \Symfony\Component\Form\FormFactoryInterface
     */
    private $factory;

    /**
     * @var \Ice\VeritasClientBundle\Entity\Course
     */
    private $course;

    /**
     * @var bool
     */
    private $platterFieldsEnabled;

    /**
     * @param FormFactoryInterface $factory
     * @param Course $course
     * @param $platterFieldsEnabled
     */
    public function __construct(FormFactoryInterface $factory, Course $course, $platterFieldsEnabled)
    {
        $this->factory = $factory;
        $this->course = $course;
        $this->platterFieldsEnabled = $platterFieldsEnabled;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_BIND => 'preBind',
            FormEvents::POST_BIND => 'postBind',
            FormEvents::PRE_SET_DATA => 'preSetData'
        );
    }

    /**
     * Event triggered on PRE_BIND.
     *
     * It adds a new widget to show additional widgets, based on earlier selections:
     * * Available bed and breakfast accommodation displayed if standard accommodation chosen
     * * Platter displayed if bed and breakfast is chosen
     *
     * Validation is added to ensure that only acceptable combinations can be chosen.
     *
     * @param FormEvent $event
     */
    public function preBind(FormEvent $event)
    {
        $data = $event->getData();
        $form = $event->getForm();
        $this->resetForm($form);
        $this->buildCustomForm($data, $form);
        foreach ($data as $key => $value) {
            if (!$form->has($key)) {
                unset($data[$key]);
            }
        }
        $event->setData($data);
    }

    /**
     * Event triggered on POST_BIND.
     *
     * It makes sure that incompatible choices are not set on the entity
     *
     * @param FormEvent $event
     */
    public function postBind(FormEvent $event)
    {
        /** @var WeekendAccommodation $entity */
        $entity = $event->getData();

        if (
            null === $entity->getAccommodation() ||
            preg_match('/^([a-z]+)-NONE-(\d+)$/i', $entity->getAccommodation(), $matches)
        )
        {
            $entity->setAdaptedBedroomRequired('N');
            $entity->setAccommodationRequirements(null);
            $entity->setBedAndBreakfastAccommodation(null);
        }

        if (
            null === $entity->getBedAndBreakfastAccommodation() ||
            preg_match('/^([a-z]+)-NONE-(\d+)$/i', $entity->getBedAndBreakfastAccommodation(), $matches)
        ) {
            $entity->setPlatter(null);
        }

        if (
            null === $entity->getBedAndBreakfastAccommodation() ||
            preg_match('/^([a-z]+)-NONE-(\d+)$/i', $entity->getPlatter(), $matches)
        ) {
            $entity->setPlatterOption(null);
        }

        $event->setData($entity);
    }

    /**
     * Event triggered on PRE_SET_DATA.
     *
     * It adds a new widget to show additional widgets, based on earlier selections:
     * * Available bed and breakfast accommodation displayed if standard accommodation chosen
     * * Platter displayed if bed and breakfast is chosen
     *
     * Validation is added to ensure that only acceptable combinations can be chosen.
     *
     * @param FormEvent $event
     */
    public function preSetData(FormEvent $event)
    {
        /** @var WeekendAccommodation $data */
        $data = $event->getData();
        $form = $event->getForm();
        $this->buildCustomForm($data->toDataArray(), $form);
    }

    /**
     * Reset the form prior to calling buildCustomForm. (NB: Removing all fields would be a bad thing, eg stepReference)
     *
     * @param FormInterface $form
     */
    protected function resetForm(FormInterface $form)
    {
        $form->remove('accommodation');
        $form->remove('bedAndBreakfastAccommodation');
        $form->remove('accommodationRequirementsGroup');
        $form->remove('accommodationSharingWith');
        if($this->platterFieldsEnabled) {
            $form->remove('platter');
            $form->remove('platterOption');
        }
    }

    /**
     * Add fields to the form based on the values in $data, which may or may not be from a request.
     *
     * @param array $data
     * @param FormInterface $form
     */
    protected function buildCustomForm(array $data, FormInterface $form)
    {

        $choices = $this->getChoicesForCategory(self::CATEGORY_ACCOMMODATION);
        $form->add(
            $this->createFormForItemType(
                'accommodation',
                $choices,
                'Course accommodation'
            )
        );


        $bAndBChoices = [];

        $accommodationChoice = isset($data['accommodation']) ? $data['accommodation'] : null;
        if (($accommodationChoice !== null && strpos($accommodationChoice, '-NONE-') === false)) {
            $form->add(
                $this->factory->create(new AccommodationRequirementsType(),
                [
                    'adaptedBedroomRequired'=>isset($data['adaptedBedroomRequired'])?$data['adaptedBedroomRequired']:null,
                    'accommodationRequirements'=>isset($data['accommodationRequirements'])?$data['accommodationRequirements']:null
                ])
            );

            $bAndBChoices = $this->getBedAndBreakfastAccommodationChoices($accommodationChoice);
            if (count($bAndBChoices)) {
                $form->add(
                    $this->createFormForItemType(
                        'bedAndBreakfastAccommodation',
                        $bAndBChoices,
                        'Sunday night B&B accommodation',
                        $this->platterFieldsEnabled // This only needs to be an AJAX field if there's a platter option
                    )
                );
            }

            // Double or twin selected so we need to find out who they are sharing the room with
            if (false !== strpos($accommodationChoice, 'DOUBLE') || false !== strpos($accommodationChoice, 'TWIN')) {
                $form->add(
                    $this->factory->createNamed('accommodationSharingWith', 'textarea', null, array(
                        'label' => 'Please provide the name of the person with whom you will be sharing, and the course which they are attending.',
                        'constraints' => array(
                            new NotBlank(array(
                                'message' => 'Twin and double rooms must be shared with another attendee. Please provide this information.',
                            )),
                        ),
                    ))
                );
            }
        }

        if ($this->platterFieldsEnabled) {
            $this->addPlatterFields($accommodationChoice, $bAndBChoices, $form, $data);
        }
    }

    private function addPlatterFields($accommodationChoice, $bAndBChoices, $form, $data)
    {
        $bedAndBreakfastAccommodationChoice =
            isset($data['bedAndBreakfastAccommodation']) ? $data['bedAndBreakfastAccommodation'] : null;

        if (
            ($accommodationChoice !== null && strpos($accommodationChoice, '-NONE-') === false) &&
            ($bedAndBreakfastAccommodationChoice !== null && strpos($bedAndBreakfastAccommodationChoice, '-NONE-') === false &&
                count($bAndBChoices)
            )
        ) {
            $choices = $this->getChoicesForCategory(self::CATEGORY_PLATTER);

            $form->add(
                $this->createFormForItemType(
                    'platter',
                    $choices,
                    'Sunday night platter'
                )
            );
        }

        $platterChoice = isset($data['platter']) ? $data['platter'] : null;
        if (($platterChoice !== null && strpos($platterChoice, '-NONE-') === false) &&
            ($bedAndBreakfastAccommodationChoice !== null && strpos($bedAndBreakfastAccommodationChoice, '-NONE-') === false) &&
            ($accommodationChoice !== null && strpos($accommodationChoice, '-NONE-') === false) &&
            count($bAndBChoices)
        ) {
            $form->add(
                $this->factory->createNamed('platterOption', 'choice', null, array(
                    'label' => 'Please indicate the option you would prefer.',
                    'choices' => array(
                        'Meat' => 'Meat',
                        'Fish' => 'Fish',
                        'Vegetarian' => 'Vegetarian',
                    ),
                    'expanded' => false,
                    'multiple' => false,
                    'empty_value' => 'Please select',
                    'constraints' => array(
                        new NotBlank(array(
                            'message' => 'Please select an option.',
                        ))
                    ),
                ))
            );
        }
    }

    /**
     * Get available booking item choices for the specified category.
     *
     * @param $category
     *
     * @return array
     */
    private function getChoicesForCategory($category)
    {
        $options = array();

        foreach ($this->course->getBookingItems() as $item) {
            if ($category == $item->getCategory()) {
                $options[$item->getCode()] = [
                    'label'=>$item->getTitle(),
                    'enabled'=>$item->isInStock()
                ];
            }
        }

        return $options;
    }

    /**
     * Get the available bed and breakfast accommodation options based on the chosen accommodation option.
     *
     * @param $accommodationChoiceCode
     *
     * @return array
     */
    private function getBedAndBreakfastAccommodationChoices($accommodationChoiceCode)
    {
        $options = array();

        if (preg_match('/^([a-z]+)-([a-z]+)-(\d+)$/i', $accommodationChoiceCode, $matches)) {
            list($all, $itemType, $roomType, $id) = $matches;
        } else {
            // Item code format doesn't match what is expected so we can't provide any valid options.
            return array();
        }

        foreach ($this->course->getBookingItems() as $item) {
            if (
                // Allow bed and breakfast accommodation of the same type as the selected accommodation. Also allow no B&B
                self::CATEGORY_BED_AND_BREAKFAST_ACCOMMODATION == $item->getCategory() &&
                (
                    false !== strpos($item->getCode(), $roomType) ||
                    false !== strpos($item->getCode(), '-NONE-')
                )
            ) {
                $options[$item->getCode()] = [
                    'label' => $item->getTitle(),
                    'enabled' => true
                ];
            }
        }

        return $options;
    }


    /**
     * @param string $name        Name of the field
     * @param array $choices
     * @param string $label
     *
     * @param bool $ajax
     * @return \Symfony\Component\Form\FormInterface
     */
    private function createFormForItemType($name, array $choices, $label, $ajax = true)
    {
        $enabledChoiceKeys = [];
        $choiceKeysLabels = [];

        foreach ($choices as $key => $choice) {
            if ($choice['enabled']) {
                $enabledChoiceKeys[] = $key;
            }
            $choiceKeysLabels[$key] = $choice['label'];
        }

        $constraints = array(
            new Choice(array(
                'choices' => $enabledChoiceKeys,
            )),
            new NotNull(array(
                'message' => 'Please choose an option.'
            ))
        );

        $options = array(
            'label' => $label,
            'choices' => $choiceKeysLabels,
            'constraints' => isset($constraints) ? $constraints : array(),
            'required' => false,
            'expanded' => true,
            'multiple' => false,
            'invalid_message' => 'Please choose a valid option. Some choices are only valid in combination with others so you may need to re-select multiple options.'
        );

        if ($ajax) {
            $options['attr'] = array('class' => 'ajax');
        }

        $form = $this->factory->createNamed($name, 'choice', null, $options);

        return $form;
    }
}
