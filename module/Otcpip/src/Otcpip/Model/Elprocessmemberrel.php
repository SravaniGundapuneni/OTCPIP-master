<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;

/**
 * Description of elprocess_member_rel
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Elprocessmemberrel {
    public $entity_id;
    public $eligibility_process_id;
    public $member_entity_id;
    public $member_id;
    public $status;
    public $created_at;
    public $updated_at;
    
    /**
     * 
     * @param type $data
     */
    public function exchangeArray($data) {
        $this->entity_id=(!empty($data['entity_id']))?$data['entity_id']:null;
        $this->eligibility_process_id=(!empty($data['eligibility_process_id']))?$data['eligibility_process_id']:null;
        $this->member_entity_id=(!empty($data['member_entity_id']))?$data['member_entity_id']:null;
        $this->member_id=(!empty($data['member_id']))?$data['member_id']:null;
        $this->status=(!empty($data['status']))?$data['status']:null;
        $this->created_at=(!empty($data['created_at']))?$data['created_at']:null;
        $this->updated_at=(!empty($data['updated_at']))?$data['updated_at']:null;
    }
}
