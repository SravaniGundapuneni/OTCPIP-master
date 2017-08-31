<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Exporter\Model;

/**
 * Description of Formsitemsrel
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Formsitemsrel {
    public $entity_id;
    public $item_id;
    public $form_id;
    public $price;
    public $created_at;
    
    /**
     * 
     * @param type $data
     */
    public function exchangeArray($data)
    {
        $this->entity_id=(!empty($data['entity_id']))? $data['entity_id']:null;
        $this->item_id=(!empty($data['item_id']))? $data['item_id']:null;
        $this->form_id=(!empty($data['form_id']))? $data['form_id']:null;
        $this->price=(!empty($data['price']))? $data['price']:null;
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
