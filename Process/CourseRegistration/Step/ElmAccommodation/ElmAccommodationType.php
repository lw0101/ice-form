<?php
namespace Ice\FormBundle\Process\CourseRegistration\Step\ElmAccommodation;

use Ice\FormBundle\Process\CourseRegistration;
use Ice\JanusClientBundle\Exception\ValidationException;
use Ice\VeritasClientBundle\Entity\Course;
use JMS\Serializer\Tests\Fixtures\Person;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormBuilderInterface;
use Ice\FormBundle\Process\CourseRegistration\Step\AbstractRegistrationStep;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\MinLength;
use Symfony\Component\Validator\Constraints\NotBlank;
use Ice\JanusClientBundle\Entity\User;
use Symfony\Component\Validator\ExecutionContext;

class ElmAccommodationType extends AbstractRegistrationStep
{
    const CATEGORY_ACCOMMODATION = 6;

    /**
     * @var array confirmation value
     */
    protected $childFormOrder;

    public function __construct(CourseRegistration $parentProcess, $reference = null, $version = null)
    {
        $this->childFormOrder = [
            1 => 'accommodation'
        ];

        if ($this->enableSundayAccommodation()) {
            $this->childFormOrder[2] = 'sundayAccommodation';
        }

        parent::__construct($parentProcess, $reference, $version);
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->buildCustomForm($this->getParentProcess()->getCourse(), $builder);

        $courseId = $this->getParentProcess()->getCourseId();

        $builder->addEventListener (FormEvents::PRE_BIND, function(FormEvent $event) use ($courseId) {
            $data = $event->getData();
            if (!isset($data['accommodation']) || !$data['accommodation'] || strpos($data['accommodation'], 'NONE') !== false) {
                unset($data['sundayEnsuite']);
                unset($data['sundayStandard']);
            } else if (strpos($data['accommodation'], 'ENSUITE') !== false) {
                if (!isset($data['sundayEnsuite'])) {
                    unset($data['sundayStandard']);
                } else {
                    $data['sundayStandard'] = $data['sundayEnsuite'];
                }
            } else if (strpos($data['accommodation'], 'STANDARD') !== false) {
                if (!isset($data['sundayStandard'])) {
                    unset($data['sundayEnsuite']);
                } else {
                    $data['sundayEnsuite'] = $data['sundayStandard'];
                }
            }
            $event->setData($data);
        });
        parent::buildForm($builder, $options);
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Accommodation';
    }

    /**
     * @return string
     */
    public function getHtmlTemplate()
    {
        return 'ElmAccommodation.html.twig';
    }

    /**
     * @param array $vars
     * @return string
     */
    public function renderHtml(array $vars = array())
    {
        $vars['course'] = $this->getParentProcess()->getCourse();
        $vars['accommodationFirstNight'] = $this->getParentProcess()->getCourse()->getStartDate();
        $vars['accommodationLastNight'] = $this->getParentProcess()->getCourse()->getEndDate();
        $vars['accommodationSunday'] = clone($this->getParentProcess()->getCourse()->getStartDate());
        $vars['accommodationSunday']->modify('-1 day');

        $vars['numberNights'] = $vars['accommodationFirstNight']->diff($vars['accommodationLastNight'])->days;

        return parent::renderHtml($vars);
    }


    /**
     * @param Request $request
     */
    public function processRequest(Request $request = null)
    {
        $this->getForm()->bind($request);
        /** @var $entity ElmAccommodation */
        $entity = $this->getEntity();

        if ($this->isContinueClicked() && $this->getForm()->isValid()) {
            $course = $this->getParentProcess()->getCourse();

            $booking = $this->getParentProcess()->getBooking();

            $bookingItems = $booking->getBookingItems();

            //Remove all items
            $newBookingItems = [];

            //Add back any items we're not responsible for
            foreach ($bookingItems as $bookingItem) {
                if (
                    substr($bookingItem->getCode(),0,13) !== 'ACCOMMODATION'
                ) {
                    $newBookingItems[] = $bookingItem;
                }
            }

            $booking->setBookingItems($newBookingItems);

            //Add back any items which we are responsible for
            if ($choice = $entity->getAccommodation()) {
                $booking->addBookingItemByCourseBookingItem(
                    $course->getBookingItemByCode($choice)
                );
            }

            //Add back any items which we are responsible for
            if ($choice = $entity->getSundayAccommodation()) {
                $booking->addBookingItemByCourseBookingItem(
                    $course->getBookingItemByCode($choice)
                );
            }

            //Persist the new items
            $this->getParentProcess()->persistBooking();
        }

        $this->getStepProgress()->setFieldValue(
            'accommodation',
            1,
            'Course accommodation',
            $entity->getAccommodation()
        );

        $this->getStepProgress()->setFieldValue(
            'sundayAccommodation',
            2,
            'Additional accommodation',
            $entity->getSundayAccommodation()
        );

        if ($this->getForm()->isValid()) {
            $this->setComplete();
        } else {
            $this->setComplete(false);
        }
        $this->setUpdated();
        $this->save();
    }

    /**
     * @return string
     */
    public function getJavaScriptTemplate()
    {
        return 'ElmAccommodation.js.twig';
    }


    /**
     * Add fields to the form based on the values in $data, which may or may not be from a request.
     *
     * @param Course $course
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     */
    protected function buildCustomForm($course, FormBuilderInterface $builder)
    {
        $choices = $this->getAccommodation($course);

        $sundayCodes = [
            'ACCOMMODATION-SUNDAY-ENSUITE-'.$course->getId(),
            'ACCOMMODATION-SUNDAY-STANDARD-'.$course->getId()
        ];

        $enabledChoiceKeys = [];
        $choiceKeysLabels = [];

        foreach ($choices as $key => $choice) {
            if (in_array($key, $sundayCodes)) {
                continue;
            }
            if ($choice['enabled']) {
                $enabledChoiceKeys[] = $key;
            }
            $choiceKeysLabels[$key] = $choice['label'];
        }

        $constraints = array(
            new Choice(array(
                'choices' => $enabledChoiceKeys
            ))
        );

        $options = array(
            'label' => 'Your accommodation selection',
            'choices' => $choiceKeysLabels,
            'constraints' => isset($constraints) ? $constraints : array(),
            'required' => false,
            'expanded' => true,
            'multiple' => false,
            'invalid_message' => 'Please choose a valid option. Some choices are only valid in combination with others so you may need to re-select multiple options.',
            'attr' => array(
                'class' => 'ajax',
            ),
        );

        $builder->add('accommodation', 'choice', $options);

        if ($this->enableSundayAccommodation()) {
            $choice = $choices['ACCOMMODATION-SUNDAY-ENSUITE-' . $course->getId()];
            $builder->add('sundayEnsuite', 'checkbox', [
                'required' => false,
                'property_path' => 'sundayAccommodationAsBool',
                'label' => sprintf('I wish to book %s (£%0.2f)', lcfirst($choice['label']), $choice['price'] / 100)
            ]);

            $choice = $choices['ACCOMMODATION-SUNDAY-STANDARD-' . $course->getId()];
            $builder->add('sundayStandard', 'checkbox', [
                'required' => false,
                'property_path' => 'sundayAccommodationAsBool',
                'label' => sprintf('I wish to book %s (£%0.2f)', lcfirst($choice['label']), $choice['price'] / 100)
            ]);
        }
    }


    /**
     * Sets up entities, pre-populates fields
     *
     * @return mixed
     */
    public function prepare()
    {
        $courseId = $this->getParentProcess()->getCourseId();
        if ($this->getStepProgress()->getUpdated()) {
            $contact = ElmAccommodation::fromStepProgress($this->getStepProgress(), $courseId);
        } else {
            $contact = new ElmAccommodation($courseId);
        }
        $this->setEntity($contact);
        $this->setPrepared();
    }


    /**
     * Get available accommodation choices
     *
     * @param Course $course
     * @return array
     */
    private function getAccommodation($course)
    {
        $options = [];

        foreach ($course->getBookingItems() as $item) {
            if ($item->getCategory() === self::CATEGORY_ACCOMMODATION && substr($item->getCode(), 0, 13) === 'ACCOMMODATION') {
                $options[$item->getCode()] = [
                    'label'=>$item->getTitle(),
                    'enabled'=>$item->isInStock(),
                    'price'=>$item->getPrice()
                ];
            }

        }

        return $options;
    }

    /**
     * Disable radio buttons for items which are out of stock
     *
     * @param FormView $view
     * @param FormInterface $form
     * @param array $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);

        $course = $this->getParentProcess()->getCourse();

        foreach (
            [
                'accommodation' => ['unavailableMessage' => 'Unavailable']
            ] as $fieldName => $options) {
            if (!isset($view->children[$fieldName])) {
                continue;
            }
            foreach ($view->children[$fieldName]->children as $child) {
                $code = $child->vars['value'];
                if ($courseItem = $course->getBookingItemByCode($code)) {
                    if ($courseItem->getPrice()) {
                        $child->vars['label'].= sprintf(" (£%.02f)", $courseItem->getPrice()/100);
                    }
                    if (!$course->getBookingItemByCode($code)->isInStock()) {
                        $child->vars['label'] = $options['unavailableMessage'] . ' - ' . $child->vars['label'];
                        $child->vars['attr']['disabled'] = 'disabled';
                    }
                }
            }
        }

        if ($this->enableSundayAccommodation()) {
            foreach (
                [
                    'sundayEnsuite' => 'ACCOMMODATION-SUNDAY-ENSUITE-' . $course->getId(),
                    'sundayStandard' => 'ACCOMMODATION-SUNDAY-STANDARD-' . $course->getId()
                ] as $fieldName => $code) {

                $bookingItem = $course->getBookingItemByCode($code);
                $field = $view->children[$fieldName];

                if (!$bookingItem->isInStock()) {
                    $field->vars['label'] = $options['unavailableMessage'] . ' - ' . $field->vars['label'];
                    $field->vars['attr']['disabled'] = 'disabled';
                }
            }
        }
    }

    public function enableSundayAccommodation()
    {
        return $this->version == 1;
    }
}