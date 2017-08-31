<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Exporter\Model;
use Zend\Db\TableGateway\TableGateway;

/**
 * Description of ItemsTable
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class ItemsTable {
    protected $tableGateway;
  
    public function __construct(TableGateway $tableGateway)
    {
      $this->tableGateway=$tableGateway;
    }

    /**
     * 
     * @return type
     */
    public function fetchAll()
    {
      $resultSet=$this->tableGateway->select();
//      echo "in fetch ".print_r($resultSet->count());
      return $resultSet;
    }
}
