<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;
use Zend\Db\TableGateway\TableGateway;

/**
 * Description of MemberstmpTable
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class MemberstmpTable {
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
    public function getMemberstmp($entity_id) {
        $entity_id=(int)$entity_id;
        $rowset=$this->tableGateway->select(array('entity_id'=>$entity_id));
        $row=$rowset->current();
        if(!$row)
        {
            throw new \Exception("getMemberstmp Couldn't find row $entity_id.");
        }
        return $row;
    }
    
    /**
     * 
     * @param type $conditions
     * @return type
     * @throws \Exception
     */
    public function getMemberstmpByConditions($conditions)
    {
        if(!is_array($conditions))
        {
            throw new \Exception("getMemberstmpByConditions: Conditions has to be an array.".  get_class($conditions). " received");
        }
        if(empty($conditions))
        {
            throw new \Exception("getMemberstmpByConditions: Conditions shouldn't be empty.");
        }
        /**
         * @TODO: Validate parameters.
         */
        $rowset=$this->tableGateway->select($conditions);
        return $rowset;
    }
    
    /**
     * 
     * @param \Otcpip\Model\Memberstmp $memberstmp
     */
    public function saveMemberstmp(Memberstmp $memberstmp)
    {
        $data=  get_object_vars($memberstmp);
        $entity_id=$memberstmp->entity_id;
        $member_id=$memberstmp->member_id;
        
        $old_members=$this->getMemberstmpByConditions(array(
            'member_id'=>$member_id,
        ));
        
        if($old_members->count()!=0)
        {
            
            $old_member=$old_members->current();
            $data['entity_id']=$old_member->entity_id;
            $this->tableGateway->update($data,array('entity_id'=>$old_member->entity_id));
        }
        else if(!$entity_id)
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
