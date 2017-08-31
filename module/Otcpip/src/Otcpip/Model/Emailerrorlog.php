<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;

/**
 * Description of Emailerrorlog
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Emailerrorlog {
    public $entity_id;
    public $code;
    public $priority;
    public $message;
    public $email_sent_at;
    public $consecutive;
    public $entity;
    public $entity_rel_id;
    public $created_at;
    public $updated_at;
    
    /**
     * 
     * @param type $data
     */
    public function exchangeArray($data)
    {
        $this->entity_id=(!empty($data['entity_id']))?$data['entity_id']:null;
        $this->code=(!empty($data['code']))?$data['code']:null;
        $this->priority=(!empty($data['priority']))?$data['priority']:null;
        $this->message=(!empty($data['message']))?$data['message']:null;
        $this->email_sent_at=(!empty($data['email_sent_at']))?$data['email_sent_at']:null;
        $this->consecutive=(!empty($data['consecutive']))?$data['consecutive']:null;
        $this->entity=(!empty($data['entity']))?$data['entity']:null;
        $this->entity_rel_id=(!empty($data['entity_rel_id']))?$data['entity_rel_id']:null;
        $this->created_at=(!empty($data['created_at']))?$data['created_at']:null;
        $this->updated_at=(!empty($data['updated_at']))?$data['updated_at']:null;
    }
}