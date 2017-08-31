<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;
use Zend\Db\TableGateway\TableGateway;

/**
 * Description of MembersTable
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class MembersTable {
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
    public function getMembers($entity_id) {
        $entity_id=(int)$entity_id;
        $rowset=$this->tableGateway->select(array('entity_id'=>$entity_id));
        $row=$rowset->current();
        if(!$row)
        {
            throw new \Exception("getMembers Couldn't find row $entity_id.");
        }
        return $row;
    }
    
    /**
     * 
     * @param type $conditions
     * @return type
     * @throws \Exception
     */
    public function getMembersByConditions($conditions)
    {
        if(!is_array($conditions))
        {
            throw new \Exception("getMembersByConditions: Conditions has to be an array.".  get_class($conditions). " received");
        }
        if(empty($conditions))
        {
            throw new \Exception("getMembersByConditions: Conditions shouldn't be empty.");
        }
        /**
         * @TODO: Validate parameters.
         */
        $rowset=$this->tableGateway->select($conditions);
        return $rowset;
    }
    
    /**
     * 
     * @param \Otcpip\Model\Members $members
     */
    public function saveMembers(Members $members)
    {
        $data=  get_object_vars($members);
        $entity_id=$members->entity_id;
        if(!$entity_id)
        {
            try
            {
                $this->tableGateway->insert($data);
                return $this->tableGateway->getLastInsertValue();
            } catch (\Exception $ex) {
                /**
                 * @Todo: handle error?
                 */
                return 0;
            }
        }
        else
        {
            try
            {
                $this->tableGateway->update($data,array('entity_id'=>$entity_id));
                return $entity_id;
            } catch (\Exception $ex) {
                $this->tableGateway->insert($data);
                return $this->tableGateway->getLastInsertValue();
            }
        }
    }
}
