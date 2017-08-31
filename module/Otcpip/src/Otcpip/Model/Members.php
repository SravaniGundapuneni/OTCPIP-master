<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;

/**
 * Description of Members
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Members {
    
    public $entity_id;
    public $alternative_id;
    public $dob;
    public $gender;
    public $enroll_date;
    public $disenroll_date;
    public $relationship;
    public $address_1;
    public $address_2;
    public $city;
    public $state_code;
    public $member_id;
    public $first_name;
    public $middle_name_initial;
    public $last_name;
    public $zipcode;
    public $phone_number;
    public $benefit_amount;
    public $period_factor;
    public $policy_number;
    public $health_plan_id;
    public $benefit_effective_date;
    public $member_last_update;
    public $benefit_id;
    public $medicare_id;
    public $line_of_business;
    public $vendor_code;
    public $family_id;
    public $benefit_plan;
    public $plan_description;
    public $group_number;
    public $group_name;
    public $household;
    public $lang_code;
    public $benefit_package;
    public $dea_number;
    public $financial_number;
    public $marital_status;
    public $region_code;
    public $form_id;
    public $plan_code;
    public $status;
    public $created_at;


    
    /**
     * 
     * @param type $data
     */
    public function exchangeArray($data)
    {
        $this->entity_id=(!empty($data['entity_id']))?$data['entity_id']:null;
        $this->alternative_id=(!empty($data['alternative_id']))?$data['alternative_id']:null;
        $this->dob=(!empty($data['dob']))?$data['dob']:null;
        $this->gender=(!empty($data['gender']))?$data['gender']:null;
        $this->enroll_date=(!empty($data['enroll_date']))?$data['enroll_date']:null;
        $this->disenroll_date=(!empty($data['disenroll_date']))?$data['disenroll_date']:null;
        $this->relationship=(!empty($data['relationship']))?$data['relationship']:null;
        $this->address_1=(!empty($data['address_1']))?$data['address_1']:null;
        $this->address_2=(!empty($data['address_2']))?$data['address_2']:null;
        $this->city=(!empty($data['city']))?$data['city']:null;
        $this->state_code=(!empty($data['state_code']))?$data['state_code']:null;
        $this->member_id=(!empty($data['member_id']))?$data['member_id']:null;
        $this->first_name=(!empty($data['first_name']))?$data['first_name']:null;
        $this->middle_name_initial=(!empty($data['middle_name_initial']))?$data['middle_name_initial']:null;
        $this->last_name=(!empty($data['last_name']))?$data['last_name']:null;
        $this->zipcode=(!empty($data['zipcode']))?$data['zipcode']:null;
        $this->phone_number=(!empty($data['phone_number']))?$data['phone_number']:null;
        $this->benefit_amount=(!empty($data['benefit_amount']))?$data['benefit_amount']:null;
        $this->period_factor=(!empty($data['period_factor']))?$data['period_factor']:null;
        $this->policy_number=(!empty($data['policy_number']))?$data['policy_number']:null;
        $this->health_plan_id=(!empty($data['health_plan_id']))?$data['health_plan_id']:null;
        $this->benefit_effective_date=(!empty($data['benefit_effective_date']))?$data['benefit_effective_date']:null;
        $this->member_last_update=(!empty($data['member_last_update']))?$data['member_last_update']:null;
        $this->benefit_id=(!empty($data['benefit_id']))?$data['benefit_id']:null;
        $this->medicare_id=(!empty($data['medicare_id']))?$data['medicare_id']:null;
        $this->line_of_business=(!empty($data['line_of_business']))?$data['line_of_business']:null;
        $this->vendor_code=(!empty($data['vendor_code']))?$data['vendor_code']:null;
        $this->family_id=(!empty($data['family_id']))?$data['family_id']:null;
        $this->benefit_plan=(!empty($data['benefit_plan']))?$data['benefit_plan']:null;
        $this->plan_description=(!empty($data['plan_description']))?$data['plan_description']:null;
        $this->group_number=(!empty($data['group_number']))?$data['group_number']:null;
        $this->group_name=(!empty($data['group_name']))?$data['group_name']:null;
        $this->household=(!empty($data['household']))?$data['household']:null;
        $this->lang_code=(!empty($data['lang_code']))?$data['lang_code']:null;
        $this->benefit_package=(!empty($data['benefit_package']))?$data['benefit_package']:null;
        $this->dea_number=(!empty($data['dea_number']))?$data['dea_number']:null;
        $this->financial_number=(!empty($data['financial_number']))?$data['financial_number']:null;
        $this->marital_status=(!empty($data['marital_status']))?$data['marital_status']:null;
        $this->region_code=(!empty($data['region_code']))?$data['region_code']:null;
        $this->form_id=(!empty($data['form_id']))?$data['form_id']:null;
        $this->plan_code=(!empty($data['plan_code']))?$data['plan_code']:null;
        $this->status=(!empty($data['status']))?$data['status']:null;
        $this->created_at=(!empty($data['created_at']))?$data['created_at']:null;
    }

}
