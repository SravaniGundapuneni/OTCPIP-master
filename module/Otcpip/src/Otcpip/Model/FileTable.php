<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;
use Zend\Db\TableGateway\TableGateway;

/**
 * Description of FileTable
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class FileTable {
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
    public function getFile($entity_id) {
        $entity_id=(int)$entity_id;
        $rowset=$this->tableGateway->select(array('entity_id'=>$entity_id));
        $row=$rowset->current();
        if(!$row)
        {
            throw new \Exception("getFile Couldn't find row $entity_id.");
        }
        return $row;
    }
    
    /**
     * 
     * @param type $conditions
     * @return type
     * @throws \Exception
     */
    public function getFileByConditions($conditions)
    {
        if(!is_array($conditions))
        {
            throw new \Exception("getFileByConditions: Conditions has to be an array.".  get_class($conditions). " received");
        }
        if(empty($conditions))
        {
            throw new \Exception("getFileByConditions: Conditions shouldn't be empty.");
        }
        /**
         * @TODO: Validate parameters.
         */
        $rowset=$this->tableGateway->select($conditions);
        return $rowset;
    }
    
    /**
     * 
     * @param \Otcpip\Model\File $file
     * @return type
     */
    public function saveFile(File $file)
    {
        $data=  get_object_vars($file);
        $entity_id=$file->entity_id;
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
