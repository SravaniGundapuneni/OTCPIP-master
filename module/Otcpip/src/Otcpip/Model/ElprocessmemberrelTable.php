<?php

/* * 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;
use Zend\Db\TableGateway\TableGateway;

/**
 * Description of Elprocess_member_relTable
 *
 * @author gabriel
 */
class ElprocessmemberrelTable {
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
    public function getElprocessmemberrel($entity_id) {
        $entity_id=(int)$entity_id;
        $rowset=$this->tableGateway->select(array('entity_id'=>$entity_id));
        $row=$rowset->current();
        if(!$row)
        {
            throw new \Exception("getElprocess_member_rel Couldn't find row $entity_id.");
        }
        return $row;
    }
    
    /**
     * 
     * @param type $conditions
     * @return type
     * @throws \Exception
     */
    public function getElprocessmemberrelByConditions($conditions)
    {
        if(!is_array($conditions))
        {
            throw new \Exception("getElprocess_member_relByConditions: Conditions has to be an array.".  get_class($conditions). " received");
        }
        if(empty($conditions))
        {
            throw new \Exception("getElprocess_member_relByConditions: Conditions shouldn't be empty.");
        }
        /**
         * @TODO: Validate parameters.
         */
        $rowset=$this->tableGateway->select($conditions);
        return $rowset;
    }
    
    /**
     * 
     * @param \Otcpip\Model\Elprocess_member_rel $elprocess_member_rel
     */
    public function saveElprocessmemberrel(Elprocessmemberrel $elprocessmemberrel)
    {
        $data=  get_object_vars($elprocessmemberrel);
        $entity_id=$elprocessmemberrel->entity_id;
        if(!$entity_id)
        {
            $this->tableGateway->insert($data);
        }
        else
        {
            try
            {
                $this->tableGateway->update($data,array('entity_id'=>$entity_id));
            } catch (\Exception $ex) {
                $this->tableGateway->insert($data);
            }
        }
    }
}
