<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Exporter\Model;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Where;

/**
 * Description of ExportprocessTable
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class ExportprocessTable {
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
    public function getExportprocess($entity_id) {
        $entity_id=(int)$entity_id;
        $rowset=$this->tableGateway->select(array('entity_id'=>$entity_id));
        $row=$rowset->current();
        if(!$row)
        {
            throw new \Exception("getExportprocess Couldn't find row $entity_id.");
        }
        return $row;
    }
    
    /**
     * 
     * @param type $conditions
     * @return type
     * @throws \Exception
     */
    public function getExportprocessByConditions($conditions)
    {
        if(!is_array($conditions) and !($conditions instanceof Where))
        {
            throw new \Exception("getExportprocessByConditions: Conditions has to be an array or Where.".  get_class($conditions). " received");
        }
        if(empty($conditions))
        {
            throw new \Exception("getExportprocessByConditions: Conditions shouldn't be empty.");
        }
        /**
         * @TODO: Validate parameters.
         */
        $rowset=$this->tableGateway->select($conditions);
        return $rowset;
    }
    
    /**
     * 
     * @param \Otcpip\Model\Exportprocess $exportprocess
     * @return type
     */
    public function saveExportprocess(Exportprocess $exportprocess)
    {
        $data=  get_object_vars($exportprocess);
        $entity_id=$exportprocess->entity_id;
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
                return $entity_id;
            } catch (\Exception $ex) {
                $this->tableGateway->insert($data);
                return $this->tableGateway->getLastInsertValue();
            }
        }
        return false;
    }
}
