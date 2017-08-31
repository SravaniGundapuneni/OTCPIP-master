<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Where;

/**
 * Description of EligibilityprocessTable
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class EligibilityprocessTable {
    const NO_RECORD_FOUND=129;
    
    protected $tableGateway;
    
    /**
     * 
     * @param \Zend\Db\TableGateway\TableGateway $tableGateway
     */
    public function __construct(TableGateway $tableGateway)
    {
        $this->tableGateway=$tableGateway;
    }
    
    /**
     * 
     * @param type $entity_id
     * @return type
     * @throws \Exception
     */
    public function getEligibilityprocess($entity_id) {
        $entity_id=(int)$entity_id;
        $rowset=$this->tableGateway->select(array('entity_id'=>$entity_id));
        $row=$rowset->current();
        if(!$row)
        {
            throw new \Exception("getEligibilityprocess Couldn't find row $entity_id.");
        }
        return $row;
    }
    
    /**
     * 
     * @param type $conditions
     * @return type
     * @throws \Exception
     */
    public function getEligibilityprocessByConditions($conditions)
    {
        if(!is_array($conditions) and !($conditions instanceof Where))
        {
            throw new \Exception("getEligibilityprocessByConditions: Conditions has to be an array or Where.".  get_class($conditions). " received");
        }
        if(empty($conditions))
        {
            throw new \Exception("getEligibilityprocessByConditions: Conditions shouldn't be empty.");
        }
        /**
         * @TODO: Validate parameters.
         */
        $rowset=$this->tableGateway->select($conditions);
//        if($rowset->count()==0)
//        {
////            return array();
//            throw new \Exception("Couldn't find rows with such conditions.",  self::NO_RECORD_FOUND);
//        }
        return $rowset;
    }
    
    /**
     * 
     * @param \Otcpip\Model\Eligibilityprocess $eligibilityprocess
     * @return type
     */
    public function saveEligibilityprocess(Eligibilityprocess $eligibilityprocess)
    {
        $data=  get_object_vars($eligibilityprocess);
        $entity_id=$eligibilityprocess->entity_id;
        if(!$entity_id)
        {
            $this->tableGateway->insert($data);
            return $this->tableGateway->getLastInsertValue();
        }
        else
        {
            try
            {
                $this->tableGateway->update($data,array('entity_id'=>$entity_id));
            } catch (\Exception $ex) {
                $this->tableGateway->insert($data);
                return $this->tableGateway->getLastInsertValue();
            }
        }
        return false;
    }
}
