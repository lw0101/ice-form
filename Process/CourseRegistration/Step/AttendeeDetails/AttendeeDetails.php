<?php
namespace Ice\FormBundle\Process\CourseRegistration\Step\AttendeeDetails;

use Ice\JanusClientBundle\Entity\User;
use Ice\MinervaClientBundle\Entity\StepProgress;
use Ice\MinervaClientBundle\Exception\NotFoundException;

class AttendeeDetails{
    /** @var string */
    private $address1;

    /** @var string */
    private $address2;

    /** @var string */
    private $address3;

    /** @var string */
    private $town;

    /** @var string */
    private $county;

    /** @var string */
    private $country;

    /** @var string */
    private $postCode;

    /** @var string */
    private $telephone;

    /**
     * @param string $address1
     * @return AttendeeDetails
     */
    public function setAddress1($address1)
    {
        $this->address1 = $address1;
        return $this;
    }

    /**
     * @return string
     */
    public function getAddress1()
    {
        return $this->address1;
    }

    /**
     * @param string $address2
     * @return AttendeeDetails
     */
    public function setAddress2($address2)
    {
        $this->address2 = $address2;
        return $this;
    }

    /**
     * @return string
     */
    public function getAddress2()
    {
        return $this->address2;
    }

    /**
     * @param string $address3
     * @return AttendeeDetails
     */
    public function setAddress3($address3)
    {
        $this->address3 = $address3;
        return $this;
    }

    /**
     * @return string
     */
    public function getAddress3()
    {
        return $this->address3;
    }

    /**
     * @param string $county
     * @return AttendeeDetails
     */
    public function setCounty($county)
    {
        $this->county = $county;
        return $this;
    }

    /**
     * @return string
     */
    public function getCounty()
    {
        return $this->county;
    }

    /**
     * @param string $town
     * @return AttendeeDetails
     */
    public function setTown($town)
    {
        $this->town = $town;
        return $this;
    }

    /**
     * @return string
     */
    public function getTown()
    {
        return $this->town;
    }

    /**
     * @param string $country
     * @return AttendeeDetails
     */
    public function setCountry($country)
    {
        $this->country = $country;
        return $this;
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @param string $postCode
     * @return AttendeeDetails
     */
    public function setPostCode($postCode)
    {
        $this->postCode = $postCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getPostCode()
    {
        return $this->postCode;
    }

    /**
     * @param string $telephone
     * @return AttendeeDetails
     */
    public function setTelephone($telephone)
    {
        $this->telephone = $telephone;
        return $this;
    }

    /**
     * @return string
     */
    public function getTelephone()
    {
        return $this->telephone;
    }

    /**
     * @param StepProgress $stepProgress
     * @return AttendeeDetails
     */
    public static function fromStepProgress(StepProgress $stepProgress){
        $instance = new self();
        $instance->setAddress1($instance->getDeserializedValueByFieldName($stepProgress, 'address1'));
        $instance->setAddress2($instance->getDeserializedValueByFieldName($stepProgress, 'address2'));
        $instance->setAddress3($instance->getDeserializedValueByFieldName($stepProgress, 'address3'));
        $instance->setTown($instance->getDeserializedValueByFieldName($stepProgress, 'town'));
        $instance->setCounty($instance->getDeserializedValueByFieldName($stepProgress, 'county'));
        $instance->setPostCode($instance->getDeserializedValueByFieldName($stepProgress, 'postCode'));
        $instance->setCountry($instance->getDeserializedValueByFieldName($stepProgress, 'country'));
        $instance->setTelephone($instance->getDeserializedValueByFieldName($stepProgress, 'telephone'));
        return $instance;
    }

    /**
     * @param User $user
     * @return AttendeeDetails
     */
    public static function fromUser(User $user){
        $instance = new self();
        $instance->setAddress1($user->getAttributeValueByName('address1'));
        $instance->setAddress2($user->getAttributeValueByName('address2'));
        $instance->setAddress3($user->getAttributeValueByName('address3'));
        $instance->setTown($user->getAttributeValueByName('town'));
        $instance->setCounty($user->getAttributeValueByName('county'));
        $instance->setPostCode($user->getAttributeValueByName('postCode'));
        $instance->setCountry($user->getAttributeValueByName('country'));
        $instance->setTelephone($user->getAttributeValueByName('telephone'));
        return $instance;
    }

    /**
     * @param StepProgress $stepProgress
     * @param $fieldName
     * @return mixed|null
     */
    private function getDeserializedValueByFieldName(StepProgress $stepProgress, $fieldName){
        try{
            return $stepProgress->getFieldValueByName($fieldName)->getValue();
        }
        catch(NotFoundException $e){
            return null;
        }
    }
}
