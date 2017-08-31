<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Exporter\Model;

/**
 * Description of Items
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Items {
    public $entity_id;
    public $benefit_upc;
    public $upc;
    public $name;
    public $short_description;
    public $description;
    public $department_id;
    public $brand_name;
    public $unit;
    public $order_index;
    public $weight;
    public $rank;
    public $max_items;
    public $bin_location;
    public $min_qty;
    public $max_qty;
    public $reorder_point;
    public $replenish;
    public $tax_class;
    public $stock_qty;
    public $stock_availability;
    public $year;
    public $status;
    public $created_at;
    
    /**
     * 
     * @param type $data
     */
    public function exchangeArray($data)
    {
        $this->entity_id=(!empty($data['entity_id']))? $data['entity_id']:null;
        $this->benefit_upc=(!empty($data['benefit_upc']))? $data['benefit_upc']:null;
        $this->upc=(!empty($data['upc']))? $data['upc']:null;
        $this->name=(!empty($data['name']))? $data['name']:null;
        $this->short_description=(!empty($data['short_description']))? $data['short_description']:null;
        $this->description=(!empty($data['description']))? $data['description']:null;
        $this->department_id=(!empty($data['department_id']))? $data['department_id']:null;
        $this->brand_name=(!empty($data['brand_name']))? $data['brand_name']:null;
        $this->unit=(!empty($data['unit']))? $data['unit']:null;
        $this->order_index=(!empty($data['order_index']))? $data['order_index']:null;
        $this->weight=(!empty($data['weight']))? $data['weight']:null;
        $this->rank=(!empty($data['rank']))? $data['rank']:null;
        $this->max_items=(!empty($data['max_items']))? $data['max_items']:null;
        $this->bin_location=(!empty($data['bin_location']))? $data['bin_location']:null;
        $this->min_qty=(!empty($data['min_qty']))? $data['min_qty']:null;
        $this->max_qty=(!empty($data['max_qty']))? $data['max_qty']:null;
        $this->reorder_point=(!empty($data['reorder_point']))? $data['reorder_point']:null;
        $this->replenish=(!empty($data['replenish']))? $data['replenish']:null;
        $this->tax_class=(!empty($data['tax_class']))? $data['tax_class']:null;
        $this->stock_qty=(!empty($data['stock_qty']))? $data['stock_qty']:null;
        $this->stock_availability=(!empty($data['stock_availability']))? $data['stock_availability']:null;
        $this->year=(!empty($data['year']))? $data['year']:null;
        $this->status=(!empty($data['status']))? $data['status']:null;
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
