<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Exporter\Helper;

use Zend\ServiceManager\ServiceManager;
use Exporter\Service\ServiceManagerConfig;

use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;

use Exporter\Model\Exportprocess;
use Exporter\Model\ExportprocessTable;

use Exporter\Model\Fileexport;
use Exporter\Model\FileexportTable;

use Exporter\Model\Departments;
use Exporter\Model\DepartmentsTable;

use Exporter\Model\Items;
use Exporter\Model\ItemsTable;

use Exporter\Model\Forms;
use Exporter\Model\FormsTable;

use Exporter\Model\Formsitemsrel;
use Exporter\Model\FormsitemsrelTable;

use Exporter\Model\Members;
use Exporter\Model\MembersTable;

/**
 * Description of Data
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Data {
    protected $serviceManager=null;
    protected $exportprocessTable;
    protected $fileexportTable;
    protected $departmentsTable;
    protected $itemsTable;
    protected $formsTable;
    protected $formsitemsrelTable;
    protected $membersTable;


    /**
     * 
     */
    public function __construct()
    {
        $config=$this->getConfiguration('config');
        if(is_null($config))
        {
            $this->setConfiguration('config');
            $config=$this->getConfiguration('config');
        }
    }
    
    /**
     * 
     * @param type $index
     * @return null|array
     */
    public function getConfiguration($index)
    {
        $serviceManager=$this->serviceManager;
        if(!is_null($serviceManager))
        {
            return $serviceManager->get($index);
        }
        return null;
    }
    
    /**
     * 
     * @param type $index
     * @param array $configuration
     */
    public function setConfiguration($index,array $configuration=array())
    {
        $global=require 'config/autoload/global.php';
        $final=array();
        foreach(glob('config/autoload/*local.php')as $filename)
        {
            
            $local=require $filename;

            $final=array_merge_recursive($global,$local,$configuration,$final);
        }
        $serviceManager=new ServiceManager(new ServiceManagerConfig());
        $serviceManager->setService($index, $final);
        $this->serviceManager=$serviceManager;
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getExportprocessTable($planID) {
        if(!$this->exportprocessTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new Exportprocess());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('export_process', $dbAdapter, null, $resultSetPrototype);
            $this->exportprocessTable=new ExportprocessTable($tableGateway);
        }
        return $this->exportprocessTable;
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getFileexportTable($planID)
    {
        if(!$this->fileexportTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new Fileexport());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('file_export', $dbAdapter, null, $resultSetPrototype);
            $this->fileexportTable=new FileexportTable($tableGateway);
        }
        return $this->fileexportTable;
    }
    
    /**
     * 
     * @param type $planID
     * @param Exportprocess $export_process
     * @param type $status
     */
    public function update_export_process_status($planID,  Exportprocess $export_process,$status)
    {
        $export_process->status=$status;
        $this->getExportprocessTable($planID)
                ->saveExportprocess($export_process);
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getDepartmentsTable($planID)
    {
        if(!$this->departmentsTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new Departments());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('department', $dbAdapter, null, $resultSetPrototype);
            $this->departmentsTable=new DepartmentsTable($tableGateway);
        }
        return $this->departmentsTable;
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getItemsTable($planID)
    {
        if(!$this->itemsTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new Items());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('items', $dbAdapter, null, $resultSetPrototype);
            $this->itemsTable=new ItemsTable($tableGateway);
        }
        return $this->itemsTable;
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getFormsTable($planID)
    {
        if(!$this->formsTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new Forms());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('forms', $dbAdapter, null, $resultSetPrototype);
            $this->formsTable=new FormsTable($tableGateway);
        }
        return $this->formsTable;
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getFormsitemsrelTable($planID)
    {
        if(!$this->formsitemsrelTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new Formsitemsrel());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('forms_items_rel', $dbAdapter, null, $resultSetPrototype);
            $this->formsitemsrelTable=new FormsitemsrelTable($tableGateway);
        }
        return $this->formsitemsrelTable;
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getMembersTable($planID)
    {
        if(!$this->membersTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new Members());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('members', $dbAdapter, null, $resultSetPrototype);
            $this->membersTable=new MembersTable($tableGateway);
        }
        return $this->membersTable;
    }
}
