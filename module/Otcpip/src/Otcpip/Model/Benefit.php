<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;

/**
 * Description of Benefit
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Benefit {
    public $entity_id;
    public $benefit_amount;
    public $period_factor;
    public $multiple_orders_per_period;
    public $group_number;
    public $benefit_plan;
    public $form_id;
    public $created_at;
    
    /**
     * 
     * @param type $data
     */
    public function exchangeArray($data)
    {
        $this->entity_id=(!empty($data['entity_id']))? $data['entity_id']:null;
        $this->benefit_amount=(!empty($data['benefit_amount']))? $data['benefit_amount']:null;
        $this->period_factor=(!empty($data['period_factor']))? $data['period_factor']:null;
        $this->multiple_orders_per_period=(!empty($data['multiple_orders_per_period']))? $data['multiple_orders_per_period']:null;
        $this->group_number=(!empty($data['group_number']))? $data['group_number']:null;
        $this->benefit_plan=(!empty($data['benefit_plan']))? $data['benefit_plan']:null;
        $this->form_id=(!empty($data['form_id']))? $data['form_id']:null;
        $this->created_at=(!empty($data['created_at']))? $data['created_at']:null;
    }
}
