<?php

namespace Ice\FormBundle\Process\CourseRegistration\Step;

use Ice\FormBundle\Process\CourseRegistration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Exception\RuntimeException;

abstract class AbstractRegistrationStep extends AbstractType{
    protected $title;

    /** @var string */
    protected $reference;

    /** @var \Ice\FormBundle\Process\CourseRegistration */
    private $parentProcess;

    /** @var \Ice\MinervaClientBundle\Entity\StepProgress */
    private $stepProgress;

    /** @var int */
    private $indexCache;

    /** @var Form */
    private $form;

    /** @var object */
    private $entity;

    /** @var bool */
    private $prepared = false;

    public function buildForm(FormBuilderInterface $builder, array $options){
        $builder->add('stepReference', 'hidden', array(
            'data'=>$this->getReference(),
            'mapped'=>false
        ));
    }

    /**
     * @param CourseRegistration $parentProcess
     * @param string|null $reference
     */
    public function __construct(CourseRegistration $parentProcess, $reference = null){
        $this->parentProcess = $parentProcess;
        $this->reference = $reference;
    }

    public function getTitle(){
        //The step reference, camelCase to Title case
        return ucfirst(strtolower(preg_replace('/([a-z])([A-Z])/', '$1 $2', $this->getReference())));
    }

    public function render(array $vars = array()){
        $vars['form'] = $this->getForm()->createView();
        $vars['url'] = $this->getParentProcess()->getUrl();
        return $this->getParentProcess()->getTemplating()->render('Registration/Step/'.$this->getTemplate(), $vars);
    }

    /**
     * @return Form|\Symfony\Component\Form\FormInterface
     */
    protected function getForm(){
        if(null === $this->form){
            $this->form = $this->getParentProcess()->getFormFactory()->create($this, $this->getEntity());
        }
        return $this->form;
    }

    public function getIndex(){
        if(null === $this->indexCache){
            foreach($this->getParentProcess()->getSteps() as $index=>$step){
                if($this === $step){
                    $this->indexCache = $index;
                    break;
                }
            }
        }
        return $this->indexCache;
    }

    /**
     * @return string
     */
    public function getTemplate(){
        return 'default.html.twig';
    }

    /**
     * @param \Ice\FormBundle\Process\CourseRegistration $parentProcess
     * @return AbstractRegistrationStep
     */
    public function setParentProcess($parentProcess)
    {
        $this->parentProcess = $parentProcess;
        return $this;
    }

    /**
     * @return \Ice\FormBundle\Process\CourseRegistration
     */
    public function getParentProcess()
    {
        return $this->parentProcess;
    }

    public function processRequest(Request $request){}

    public function getName(){
        return '';
    }

    public function getReference()
    {
        return $this->reference;
    }

    public function isCurrent(){
        return $this->getReference() === $this->getParentProcess()->getCurrentStep()->getReference();
    }

    public function setComplete($complete = true){
        if($complete && !$this->isComplete()){ //Change to complete
            $this->getStepProgress()->setCompleted(new \DateTime());
        }
        else if($this->isComplete() && !$complete){ //Change to incomplete
            $this->getStepProgress()->setCompleted(null);
        }
    }

    public function isComplete(){
        if(null === $this->getStepProgress()){
            return false;
        }
        return null !== $this->getStepProgress()->getCompleted();
    }

    /**
     * The entity that the form will be bound to.
     *
     * @throws \RuntimeException if the entity has not yet been prepared
     * @return \stdClass
     */
    public function getEntity(){
        if(null === $this->entity){
            throw new \RuntimeException('Entity has not been prepared');
        }
        return $this->entity;
    }

    /**
     * @param $entity
     * @return AbstractRegistrationStep
     */
    protected function setEntity($entity){
        $this->entity = $entity;
        return $this;
    }

    /**
     * @param boolean $prepared
     * @return AbstractRegistrationStep
     */
    protected function setPrepared($prepared = true)
    {
        $this->prepared = $prepared;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isPrepared()
    {
        return $this->prepared;
    }

    /**
     * Sets up entities, pre-populates fields
     *
     * @return mixed
     */
    abstract public function prepare();

    /**
     * @param \DateTime|null $dateOrNull
     * @return null
     */
    private function dateToString($dateOrNull)
    {
        if(null === $dateOrNull){
            return null;
        }
        return $dateOrNull->format('Y-m-d H:i:s');
    }

    public function save(){
        $values =             array(
            'stepName'=>$this->getStepProgress()->getStepName(),
            'order'=>$this->getStepProgress()->getOrder(),
            'description'=>$this->getStepProgress()->getDescription(),
            'began'=>$this->dateToString($this->getStepProgress()->getBegan()),
            'updated'=>$this->dateToString($this->getStepProgress()->getUpdated()),
            'completed'=>$this->dateToString($this->getStepProgress()->getCompleted()),
            'registrationFieldValues'=>array()
        );
        foreach($this->getStepProgress()->getFieldValues() as $fieldValue){
            $values['registrationFieldValues'][] = array(
                'fieldName'=>$fieldValue->getFieldName(),
                'value'=>$fieldValue->getValueSerialized(),
                'order'=>$fieldValue->getOrder(),
                'description'=>$fieldValue->getDescription()
            );
        }

        $this->getParentProcess()->getMinervaClient()->setRegistrationStep(
            $this->getParentProcess()->getRegistrantId(),
            $this->getParentProcess()->getCourseId(),
            $values
        );
    }

    /**
     * @param $stepProgress
     * @return $this
     */
    public function setStepProgress($stepProgress)
    {
        $this->stepProgress = $stepProgress;
        return $this;
    }

    /**
     * @return \Ice\MinervaClientBundle\Entity\StepProgress
     */
    public function getStepProgress()
    {
        return $this->stepProgress;
    }

    /**
     * @return bool
     */
    public function isAvailable(){
        return $this->getParentProcess()->getRegistrantId() && $this->getParentProcess()->getCourseId();
    }
}