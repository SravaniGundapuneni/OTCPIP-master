<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql;

/**
 * Description of BenefitTable
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class BenefitTable {
    const NO_RECORD_FOUND=129;
    
  
    protected $tableGateway;

    /**
     * class construct
     *
     * @param TableGateway $tableGateway
     */
    public function __construct(TableGateway $tableGateway) 
    {
      $this->tableGateway=$tableGateway;
    }
    
    /**
     * 
     * @param \Zend\Db\Sql\Where $conditions
     * @return type
     * @throws \Exception
     */
    public function getBenefitByConditions($conditions)
    {
        if(!is_array($conditions) and !($conditions instanceof Sql\Where))
        {
            throw new \Exception("Conditions has to be an array or Where.");
        }
        if(empty($conditions))
        {
            throw new \Exception("Conditions shouldn't be empty.");
        }
        /**
         * @TODO: validate parameters
         */
        $rowset=$this->tableGateway->select($conditions);
        return $rowset;
    }
}
