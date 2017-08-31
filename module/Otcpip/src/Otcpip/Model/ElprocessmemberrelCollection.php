<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;
use Zend\Db\Adapter\Adapter;

/**
 * Description of MembersCollection
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class ElprocessmemberrelCollection {
    const NO_RECORD_FOUND=129;
    
    protected $db;
    
    public function __construct(Adapter $db) {
        $this->db=$db;
    }
    
    /**
     * 
     * @param type $conditions
     * @return type
     */
    public function getProcessedRecordsCount($conditions)
    {
        $sql="select count(*) as processed_records"
                . " from elprocess_member_rel"
                . " where eligibility_process_id=".$this->db->driver->formatParameterName('eligibility_process_id');
        $statement=$this->db->createStatement($sql,$conditions);
        $rowset=$statement->execute();
        return $rowset->current();
    }
}
