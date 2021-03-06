<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Exporter\Model;

/**
 * Description of Fileexport
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Fileexport {
    public $entity_id;
    public $entity;
    public $filename;
    public $file_description;
    public $size;
    public $lines;
    public $export_process_id;
    public $status;
    public $created_at;
    public $updated_at;
    
    /**
     * 
     * @param type $data
     */
    public function exchangeArray($data)
    {
        $this->entity_id=(!empty($data['entity_id']))?$data['entity_id']:null;
        $this->entity=(!empty($data['entity']))?$data['entity']:null;
        $this->filename=(!empty($data['filename']))?$data['filename']:null;
        $this->file_description=(!empty($data['file_description']))?$data['file_description']:null;
        $this->size=(!empty($data['size']))?$data['size']:null;
        $this->lines=(!empty($data['lines']))?$data['lines']:null;
        $this->export_process_id=(!empty($data['export_process_id']))?$data['export_process_id']:null;
        $this->status=(!empty($data['status']))?$data['status']:null;
        $this->created_at=(!empty($data['created_at']))?$data['created_at']:null;
        $this->updated_at=(!empty($data['updated_at']))?$data['updated_at']:null;
    }
}
