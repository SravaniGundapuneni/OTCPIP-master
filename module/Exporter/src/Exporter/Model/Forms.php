<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Exporter\Model;

/**
 * Description of Forms
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Forms {
    public $entity_id;
    public $description;
    public $valid_from;
    public $valid_to;
    public $status;
    public $items;
    public $created_at;
    
    /**
     * 
     * @param type $data
     */
    public function exchangeArray($data)
    {
        $this->entity_id=(!empty($data['entity_id']))? $data['entity_id']:null;
        $this->description=(!empty($data['description']))? $data['description']:null;
        $this->valid_from=(!empty($data['valid_from']))? $data['valid_from']:null;
        $this->valid_to=(!empty($data['valid_to']))? $data['valid_to']:null;
        $this->status=(!empty($data['status']))? $data['status']:null;
        $this->items=(!empty($data['items']))? $data['items']:null;
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
