<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Exporter\Model;

/**
 * Description of Departments
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Departments {
    public $entity_id;
    public $code;
    public $default_description;
    public $alter_description;
    public $created_at;
    
    /**
     * 
     * @param type $data
     */
    public function exchangeArray($data)
    {
        $this->entity_id=(!empty($data['entity_id']))? $data['entity_id']:null;
        $this->code=(!empty($data['code']))? $data['code']:null;
        $this->default_description=(!empty($data['default_description']))? $data['default_description']:null;
        $this->alter_description=(!empty($data['alter_description']))? $data['alter_description']:null;
        $this->created_at=(!empty($data['created_at']))? $data['created_at']:null;
    }
    
    /**
     * 
     * @return type
     */
    public function getArrayCopy()
    {
        return get_object_vars($this);
    }
}
