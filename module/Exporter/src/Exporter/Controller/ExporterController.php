<?php

namespace Exporter\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Console\Request as ConsoleRequest;

use Zend\Db\Sql;

use DateTime;
use DateTimeZone;

use Exporter\Model\Exportprocess;

use Exporter\Helper\Data;
use Exporter\Model\Fileexport;


class ExporterController extends AbstractActionController
{

    /**
     * 
     * @return type
     * @throws \RuntimeException
     */
    public function exportAction()
    {
        $request=$this->getRequest();
        
        if(!$request instanceof ConsoleRequest)
        {
            throw new \RuntimeException('You can only use this action from a console!');
        }
        
        $exportAction=$request->getParam('exportAction','all');
        $planID=$request->getParam('planID', 'all');
        
        //plan validation
        $config=$this->getServiceLocator()->get('config');
        if(empty($config['servers'][$planID]) and $planID!='all')
        {
            die("Not existent plan.\n");
        }
        
        $verbose=$request->getParam('verbose') || $request->getParam('v');
        
        $fp=fopen("otcpipexp.lock","w");
        if(!flock($fp, LOCK_EX|LOCK_NB)) { //try to get exclusive lock, non-blocking
            die("Another instance is running\n");
        }
        if($planID=='all')
        {
            $plans=$config['servers'];
            foreach($plans as $planID=>$value)
            {
                $this->$exportAction($planID,$verbose);
            }
        }
        else
        {
            return $this->$exportAction($planID,$verbose);
        }
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @return string
     */
    public function all($planID,$verbose)
    {
        $functions=array(
            'create_export_process_request',
            'export_export_process',
            'put_exported_file',
            /*'create_plans_export_file_request',
            'create_departments_export_file_request',
            'create_items_export_file_request',
            'create_forms_export_file_request',
            'create_forms_items_rel_export_file_request',
            'create_members_export_file_request',*/
            /*'export_plans_file',
            'export_department_file',
            'export_items_file',
            'export_forms_file',
            'export_forms_items_rel_file',
            'export_members_file',
            'put_plans_file',
            'put_department_file',
            'put_items_file',
            'put_forms_file',
            'put_forms_items_rel_file',
            'put_members_file',*/
        );
        echo "Running all functions for $planID plan(s)...\n";
        foreach($functions as $function)
        {
            echo "Running $function for $planID plan(s)...\n";
            echo $this->$function($planID,$verbose).
                    "\n";
        }
        return "Done all\n";
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @return string
     */
    public function create_export_process_request($planID,$verbose)
    {
        $config=$this->getServiceLocator()->get('config');
        if(empty($config['servers'][$planID]) and $planID!='all')
        {
            die("Not existent plan.\n");
        }
        echo "Starting export plans function for this plan(s): $planID...\n";
        $now=new DateTime('now', new DateTimeZone('America/New_York'));
        $current_day=$now->format("l");
        if(!$config['feedexport']['schedule'][$current_day]['run'])
        {
            echo "\033[36mNo file expected today.\n\033[36m";
        }
        else
        {
            $from_valid=new DateTime($now->format("Y-m-d ").$config['feedexport']['schedule'][$current_day]['from_time'], new DateTimeZone('America/New_York'));
            $to_valid=new DateTime($now->format("Y-m-d ").$config['feedexport']['schedule'][$current_day]['to_time'], new DateTimeZone('America/New_York'));
            
            $conditions=new Sql\Where();
            $conditions->between('created_at',
                    $from_valid->format("Y-m-d H:i:s"),
                    $to_valid->format("Y-m-d H:i:s"));
            $datah=new Data();
            $exportprocess=$datah->getExportprocessTable($planID)
                    ->getExportprocessByConditions($conditions);
            
            if(count($exportprocess)==0)
            {
                echo "No records found\n";
                if($now>=$to_valid)
                {
                    //if there is no record, and time is after "to", create record as expired and send error.
                    /**
                     * @Todo: Mark as expired and Send error here.
                     */
                    echo "\033[31mError while creating file request\n\033[0m";
                }
                else
                {
                    //if there is no record, create it.
                    echo "to create\n";
                    
                    $exportprocess_i=new Exportprocess();
                    $exportprocess_i->plan_id=$planID;
                    $exportprocess_i->status='Enabled';
                    
                    $process_id=$datah->getExportprocessTable($planID)
                            ->saveExportProcess($exportprocess_i);
                    /**
                     * @Todo: insert in log too.
                     */
                    if($process_id)
                    {
                        $exportprocess_i->entity_id=$process_id;
                        $createf=$this->create_export_files_records($planID, $verbose,$process_id);
                        
                        if($createf)
                        {
                            //update export_process status
                            $datah->update_export_process_status($planID, $exportprocess_i, 'File requests created');
                            echo "\033[32mExport Process with ID: $process_id was created.\n\033[0m";
                        }
                        else
                        {
                            $datah->update_export_process_status($planID, $exportprocess_i, 'Failed requesting file creation');
                            echo "\033[31mAn error occurred while creating file request.\n\033[31m";
                        }
                    }
                    else
                    {
                        //update export_process status
                        $datah->update_export_process_status($planID, $exportprocess_i, 'Failed requesting file creation');
                        echo "\033[31mAn error occurred while creating file request.\n\033[31m";
                        /**
                         * @Todo: report error.
                         */
                    }
                }
            }
            else
            {
                //otherwise there is nothing to do here.
                $current_expprocess=$exportprocess->current();
                if($current_expprocess->status=='File requests created')
                {
                    echo "\033[36mRecord already created.\n\033[0m";
                }
                else
                {
                    $process_id=$current_expprocess->entity_id;
                    $createf=$this->create_export_files_records($planID, $verbose,$process_id);
                        
                    if($createf)
                    {
                        //update export_process status
                        $datah->update_export_process_status($planID, $current_expprocess, 'File requests created');
                        echo "\033[32mExport Process with ID: $process_id was created.\n\033[0m";
                    }
                    else
                    {
                        $datah->update_export_process_status($planID, $current_expprocess, 'Failed requesting file creation');
                        echo "\033[31mAn error occurred while creating file request.\n\033[31m";
                    }
                }
            }
            //If record is expired, send error.
            /**
             * @Todo: Develop this logic.
             */
        }
        return "Done\n";
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @return string
     */
    public function export_export_process($planID,$verbose)
    {
        //look for records with statuses: 'File requests created', 'Failed exporting feeds'
        //plan validation
        $config=$this->getServiceLocator()->get('config');
        if(empty($config['servers'][$planID]) and $planID!='all')
        {
            die("Not existent plan.\n");
        }
        echo "Starting export plans function for this plan(s): $planID...\n";
        $now=new DateTime('now', new DateTimeZone('America/New_York'));
        $current_day=$now->format("l");
        if(!$config['feedexport']['schedule'][$current_day]['run'])
        {
            echo "\033[36mNo file expected today.\n\033[36m";
        }
        else
        {
            $conditions=new Sql\Where();
            $conditions->in('status', array(
                'File requests created',
                'Failed exporting feeds',
            ));
            $datah=new Data();
            $exportprocess=$datah->getExportprocessTable($planID)
                    ->getExportprocessByConditions($conditions);
            
            if(count($exportprocess)==0)
            {
                echo "\033[36mNothing to do in export.\n\033[0m";
            }
            else
            {
                /**
                 * @Todo: Should old processes be considered?
                 */
                while ($exportprocess->valid())
                {
                    $curexpprocess=$exportprocess->current();
                    echo "Getting file requests from process ID: ".$curexpprocess->entity_id."\n";
                    $conditions=new Sql\Where();
                    $conditions->equalTo('export_process_id', $curexpprocess->entity_id)
                            ->in('status',array(
                                'File requests created',
                                'Failed exporting feeds',
                            ));
                    $expfiles=$datah->getFileexportTable($planID)
                            ->getFileexportByConditions($conditions);
                    if(count($expfiles)==0)
                    {
                        echo "No files to consider\n";
                    }
                    else
                    {
                        while($expfiles->valid())
                        {
                            $curexpfile=$expfiles->current();
                            echo "Exporting $curexpfile->entity into file: $curexpfile->filename\n";
                            $fncname="export_".$curexpfile->entity;
                            $this->$fncname($planID,$verbose,$curexpfile);
                            $expfiles->next();
                        }
                        /**
                         * @Todo: validate update export_process status.
                         */
                        $datah->update_export_process_status($planID, $curexpprocess, 'Feed exported');
                    }
                    $exportprocess->next();
                }
            }
        }
        return "Done\n";
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @param type $export_process_id
     * @return int
     */
    public function create_export_files_records($planID,$verbose,$export_process_id)
    {
        $datah=new Data();
        $config=$this->getServiceLocator()->get('config');
        $now=new DateTime('now', new DateTimeZone('America/New_York'));
        $year4=$now->format("Y");
        $year2=$now->format("y");
        $month=$now->format("m");
        $day=$now->format("d");

        $entities=$config['feedexport']['file']['layout'];

        //Create files records
        foreach($entities as $key=>$value)
        {
            //Look for record first
            //Insert only missing records
            $files=$datah->getFileexportTable($planID)
                    ->getFileexportByConditions(array(
                        'export_process_id'=>$export_process_id,
                        'entity'=>$key,
                        'status'=>'File requests created',
                    ));
            if(count($files)>0)
            {
                echo "·";
            }
            else
            {
                $fileout_template=$config['feedexport']['connection']['out']['files'][$key]['format_out'];
                $fileout_name=  preg_replace(array('/Y{4}/','/Y{2}/','/M{2}/','/D{2}/'), array($year4,$year2,$month,$day), $fileout_template);
                $fileexport=new Fileexport();
                $fileexport->entity=$key;
                $fileexport->export_process_id=$export_process_id;
                $fileexport->file_description="$key export plan";
                $fileexport->filename=$fileout_name;
                $fileexport->size=0;
                $fileexport->lines=0;
                $fileexport->status='File requests created';
                $datah->getFileexportTable($planID)
                        ->saveFileexport($fileexport);
                echo ".";
            }
        }
        /**
         * @Todo: Validate return status
         */
        return 1;
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @param type $file_export
     * @return int
     */
    public function export_plans($planID,$verbose,$file_export)
    {
        $config=$this->getServiceLocator()->get('config');
        //get information
        $fields=$config['feedexport']['file']['layout']['plans']['fields'];
        $line="";
        foreach($fields as $field)
        {
            //Build line.
            $value=$config['servers'][$planID]['export_info'][$field['name']];
            $padded_value=  str_pad($value, 
                    $field['length'], 
                    $field['pad'], 
                    $field['pad_type']);
            $line.=$padded_value;
        }
        $line.="\n";
//        echo "Line: $line";
        //put information in file
        $exporth=  fopen($config['feedexport']['connection']['out']['files']['plans']['path_out'].$file_export->filename, "a");
        fwrite($exporth,$line);
        fclose($exporth);
        //update file_export record
        $file_export->status='Feed exported';
        $file_export->lines='1';
        $datah=new Data();
        $datah->getFileexportTable($planID)
                ->saveFileexport($file_export);
        echo "Plan $planID exported\n";
        return 1;
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @param type $file_export
     * @return int
     */
    public function export_departments($planID,$verbose,$file_export)
    {
        $entity='departments';
        $config=$this->getServiceLocator()->get('config');
        //get information
        $fields=$config['feedexport']['file']['layout'][$entity]['fields'];
        
        //get departments
        $datah=new Data();
        $departments=$datah->getDepartmentsTable($planID)
                ->fetchAll();
        if(count($departments)==0)
        {
            /**
             * @Todo: handle error.
             */
            echo "No department records to export\n";
            return 0;
        }
        $exporth=  fopen($config['feedexport']['connection']['out']['files'][$entity]['path_out'].$file_export->filename, "a");
        $lines=0;
        while($departments->valid())
        {
            $line="";
            $curdepartment=$departments->current();
            foreach ($fields as $field)
            {
                if($field['name']=='plan_code')
                {
                    $value=$planID;
                }
                else
                {
                    $value=$curdepartment->$field['name'];
                }
                $padded_value=str_pad($value, 
                    $field['length'], 
                    $field['pad'], 
                    $field['pad_type']);
                $line.=$padded_value;
            }
            $line.="\n";
            fwrite($exporth, $line);
            $lines++;
            $departments->next();
        }
        fclose($exporth);
        $file_export->status='Feed exported';
        $file_export->lines=$lines;
        $datah->getFileexportTable($planID)
                ->saveFileexport($file_export);
        echo "$entity $planID exported\n";
        return 1;
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @param type $file_export
     * @return int
     */
    public function export_items($planID,$verbose,$file_export)
    {
        $entity='items';
        $config=$this->getServiceLocator()->get('config');
        //get information
        $fields=$config['feedexport']['file']['layout'][$entity]['fields'];
        
        //get items
        $datah=new Data();
        $items=$datah->getItemsTable($planID)
                ->fetchAll();
        if(count($items)==0)
        {
            /**
             * @Todo: handle error.
             */
            echo "No item records to export\n";
            return 0;
        }
        $exporth=  fopen($config['feedexport']['connection']['out']['files'][$entity]['path_out'].$file_export->filename, "a");
        $lines=0;
        while($items->valid())
        {
            $line="";
            $curitem=$items->current();
            foreach ($fields as $field)
            {
                if($field['name']=='plan_code')
                {
                    $value=$planID;
                }
                else
                {
                    $value=$curitem->$field['name'];
                }
                $padded_value=str_pad($value, 
                    $field['length'], 
                    $field['pad'], 
                    $field['pad_type']);
                $line.=$padded_value;
            }
            $line.="\n";
            fwrite($exporth, $line);
//            echo "Line: $line";
            $lines++;
            $items->next();
        }
        fclose($exporth);
        $file_export->status='Feed exported';
        $file_export->lines=$lines;
        $datah->getFileexportTable($planID)
                ->saveFileexport($file_export);
        echo "$entity $planID exported\n";
        return 1;
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @param type $file_export
     * @return int
     */
    public function export_forms($planID,$verbose,$file_export)
    {
        $entity='forms';
        $config=$this->getServiceLocator()->get('config');
        //get information
        $fields=$config['feedexport']['file']['layout'][$entity]['fields'];
        
        //get forms
        $datah=new Data();
        $forms=$datah->getFormsTable($planID)
                ->fetchAll();
        if(count($forms)==0)
        {
            /**
             * @Todo: handle error.
             */
            echo "No item records to export\n";
            return 0;
        }
        $exporth=  fopen($config['feedexport']['connection']['out']['files'][$entity]['path_out'].$file_export->filename, "a");
        $lines=0;
        while($forms->valid())
        {
            $line="";
            $curitem=$forms->current();
            foreach ($fields as $field)
            {
                if($field['name']=='plan_code')
                {
                    $value=$planID;
                }
                else
                {
                    $value=$curitem->$field['name'];
                }
                $padded_value=str_pad($value, 
                    $field['length'], 
                    $field['pad'], 
                    $field['pad_type']);
                $line.=$padded_value;
            }
            $line.="\n";
            fwrite($exporth, $line);
//            echo "Line: $line";
            $lines++;
            $forms->next();
        }
        fclose($exporth);
        $file_export->status='Feed exported';
        $file_export->lines=$lines;
        $datah->getFileexportTable($planID)
                ->saveFileexport($file_export);
        echo "$entity $planID exported\n";
        return 1;
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @param type $file_export
     * @return int
     */
    public function export_forms_items_rel($planID,$verbose,$file_export)
    {
        $entity='forms_items_rel';
        $config=$this->getServiceLocator()->get('config');
        //get information
        $fields=$config['feedexport']['file']['layout'][$entity]['fields'];
        
        //get forms_items_rel
        $datah=new Data();
        $forms_items_rel=$datah->getFormsitemsrelTable($planID)
                ->fetchAll();
        if(count($forms_items_rel)==0)
        {
            /**
             * @Todo: handle error.
             */
            echo "No item records to export\n";
            return 0;
        }
        $exporth=  fopen($config['feedexport']['connection']['out']['files'][$entity]['path_out'].$file_export->filename, "a");
        $lines=0;
        while($forms_items_rel->valid())
        {
            $line="";
            $curitem=$forms_items_rel->current();
            foreach ($fields as $field)
            {
                if($field['name']=='plan_code')
                {
                    $value=$planID;
                }
                else
                {
                    $value=$curitem->$field['name'];
                }
                $padded_value=str_pad($value, 
                    $field['length'], 
                    $field['pad'], 
                    $field['pad_type']);
                $line.=$padded_value;
            }
            $line.="\n";
            fwrite($exporth, $line);
//            echo "Line: $line";
            $lines++;
            $forms_items_rel->next();
        }
        fclose($exporth);
        $file_export->status='Feed exported';
        $file_export->lines=$lines;
        $datah->getFileexportTable($planID)
                ->saveFileexport($file_export);
        echo "$entity $planID exported\n";
        return 1;
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @param type $file_export
     * @return int
     */
    public function export_members($planID,$verbose,$file_export)
    {
        $entity='members';
        $config=$this->getServiceLocator()->get('config');
        //get information
        $fields=$config['feedexport']['file']['layout'][$entity]['fields'];
        
        //get members
        $datah=new Data();
        $members=$datah->getMembersTable($planID)
                ->getMembersByConditions(array(
                    'status'=>'Enabled',
                ));
        if(count($members)==0)
        {
            /**
             * @Todo: handle error.
             */
            echo "No item records to export\n";
            return 0;
        }
        $exporth=  fopen($config['feedexport']['connection']['out']['files'][$entity]['path_out'].$file_export->filename, "a");
        $lines=0;
        while($members->valid())
        {
            $line="";
            $curitem=$members->current();
            foreach ($fields as $field)
            {
                if($field['name']=='plan_code')
                {
                    $value=$planID;
                }
                else
                {
                    $value=$curitem->$field['name'];
                }
                $padded_value=str_pad($value, 
                    $field['length'], 
                    $field['pad'], 
                    $field['pad_type']);
                $line.=$padded_value;
            }
            $line.="\n";
            fwrite($exporth, $line);
//            echo "Line: $line";
            $lines++;
            $members->next();
        }
        fclose($exporth);
        $file_export->status='Feed exported';
        $file_export->lines=$lines;
        $datah->getFileexportTable($planID)
                ->saveFileexport($file_export);
        echo "$entity $planID exported\n";
        return 1;
    }
    
}

