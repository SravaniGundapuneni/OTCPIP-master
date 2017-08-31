<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Exporter\Model;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Where;

/**
 * Description of FileexportTable
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class FileexportTable {
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
    public function getFileexport($entity_id) {
        $entity_id=(int)$entity_id;
        $rowset=$this->tableGateway->select(array('entity_id'=>$entity_id));
        $row=$rowset->current();
        if(!$row)
        {
            throw new \Exception("getFileexport Couldn't find row $entity_id.");
        }
        return $row;
    }
    
    /**
     * 
     * @param type $conditions
     * @return type
     * @throws \Exception
     */
    public function getFileexportByConditions($conditions)
    {
        if(!is_array($conditions) and !($conditions instanceof Where))
        {
            throw new \Exception("getExportprocessByConditions: Conditions has to be an array or Where.".  get_class($conditions). " received");
        }
        if(empty($conditions))
        {
            throw new \Exception("getFileexportByConditions: Conditions shouldn't be empty.");
        }
        /**
         * @TODO: Validate parameters.
         */
        $rowset=$this->tableGateway->select($conditions);
        return $rowset;
    }
    
    /**
     * 
     * @param \Otcpip\Model\Fileexport $fileexport
     * @return type
     */
    public function saveFileexport(Fileexport $fileexport)
    {
        $data=  get_object_vars($fileexport);
        $entity_id=$fileexport->entity_id;
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
    }
}
