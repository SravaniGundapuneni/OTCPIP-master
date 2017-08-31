<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;

use Zend\Db\TableGateway\TableGateway;
use Otcpip\Helper\Error as ErrorHelper;

/**
 * Description of EmailerrorlogTable
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class EmailerrorlogTable {
    const NO_RECORD_FOUND=129;
    
    protected $tableGateway;
    
    /**
     * 
     * @param \Order\Model\TableGateway $tableGateway
     */
    public function __construct(TableGateway $tableGateway)
    {
        $this->tableGateway=$tableGateway;
    }
    
    /**
     * 
     * @param type $conditions
     * @return type
     * @throws \Exception
     */
    public function getEmailErrorlogByConditions($conditions)
    {
        if(!is_array($conditions))
        {
            throw new \Exception("getEmailErrorlogByConditions Conditions has to be an array.".  get_class($conditions). " received");
        }
        if(empty($conditions))
        {
            throw new \Exception("getEmailErrorlogByConditions Conditions shouldn't be empty.");
        }
        /**
         * @TODO: validate parameters
         */
        $rowset=$this->tableGateway->select($conditions);
        if($rowset->count()==0)
        {
            return array();
            // throw new \Exception("Couldn't find rows with such conditions.",  self::NO_RECORD_FOUND);
        }
        $records=array();
        while($rowset->valid())
        {
            $records[]=$rowset->current();
            $rowset->next();
        }
        return $records;
    }
    
    /**
     * 
     * @param type $entity_id
     * @return type
     */
    public function getEmailerrorlog($entity_id)
    {
        $rowset=$this->tableGateway->select(array(
            'entity_id'=>$entity_id,
        ));
        $row=$rowset->current();
        return $row;
    }
    
    /**
     * [saveEmailerrorlog description]
     * @param  Emailerrorlog $emailerrorlog [description]
     * @return [type]                       [description]
     */
    public function saveEmailerrorlog(Emailerrorlog $emailerrorlog)
    {

        $data=  get_object_vars($emailerrorlog);
        $entity_id=$emailerrorlog->entity_id;
        if(!$entity_id)
        {
            try
            {
                $this->tableGateway->insert($data);
                return $this->tableGateway->getLastInsertValue();
            }
            catch (\Exception $ex)
            {
                return $ex->getMessage();
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