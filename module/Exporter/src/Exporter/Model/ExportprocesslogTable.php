<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Exporter\Model;
use Zend\Db\TableGateway\TableGateway;

/**
 * Description of ExportprocesslogTable
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class ExportprocesslogTable {
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
    public function getExportprocesslog($entity_id) {
        $entity_id=(int)$entity_id;
        $rowset=$this->tableGateway->select(array('entity_id'=>$entity_id));
        $row=$rowset->current();
        if(!$row)
        {
            throw new \Exception("getExportprocesslog Couldn't find row $entity_id.");
        }
        return $row;
    }
    
    /**
     * 
     * @param type $conditions
     * @return type
     * @throws \Exception
     */
    public function getExportprocesslogByConditions($conditions)
    {
        if(!is_array($conditions))
        {
            throw new \Exception("getExportprocesslogByConditions: Conditions has to be an array.".  get_class($conditions). " received");
        }
        if(empty($conditions))
        {
            throw new \Exception("getExportprocesslogByConditions: Conditions shouldn't be empty.");
        }
        /**
         * @TODO: Validate parameters.
         */
        $rowset=$this->tableGateway->select($conditions);
        if($rowset->count()==0)
        {
//            return array();
            throw new \Exception("Couldn't find rows with such conditions.",  self::NO_RECORD_FOUND);
        }
        return $rowset;
    }
    
    /**
     * 
     * @param \Otcpip\Model\Exportprocesslog $exportprocesslog
     */
    public function saveExportprocesslog(Exportprocesslog $exportprocesslog)
    {
        $data=  get_object_vars($exportprocesslog);
        $entity_id=$exportprocesslog->entity_id;
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
