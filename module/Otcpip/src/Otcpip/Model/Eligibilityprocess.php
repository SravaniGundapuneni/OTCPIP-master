<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;

/**
 * Description of Eligibilityprocess
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Eligibilityprocess {
    public $entity_id;
    public $plan_id;
    public $status;
    public $created_at;
    public $updated_at;
    
    /**
     * 
     * @param type $data
     */
    public function exchangeArray($data) {
        $this->entity_id=(!empty($data['entity_id']))?$data['entity_id']:null;
        $this->plan_id=(!empty($data['plan_id']))?$data['plan_id']:null;
        $this->status=(!empty($data['status']))?$data['status']:null;
        $this->created_at=(!empty($data['created_at']))?$data['created_at']:null;
        $this->updated_at=(!empty($data['updated_at']))?$data['updated_at']:null;
    }
}
