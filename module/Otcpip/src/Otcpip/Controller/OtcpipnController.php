<?php

namespace Otcpip\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Console\Request as ConsoleRequest;
use Otcpip\Helper\Data;
use Zend\Db\Sql;

use DateTime;
use DateTimeZone;

use Otcpip\Model\Eligibilityprocess;
use Otcpip\Model\File;

use Otcpip\Helper\Error as ErrorHelper;

class OtcpipnController extends AbstractActionController
{
    
    /**
     * 
     * @return type
     * @throws \RuntimeException
     */
    public function doAction()
    {
        $request=$this->getRequest();
        
        if(!$request instanceof ConsoleRequest)
        {
            throw new \RuntimeException('You can only use this action from a console!');
        }
        
        $doAction=$request->getParam('doAction','all');
        $planID=$request->getParam('planID','all');
        
        //plan validation
        $config=$this->getServiceLocator()->get('config');

        $datah=new Data();
        if( $datah->verify_encrypted($config) )
        {
            $datah->treat_secret($config);
        }

        if(empty($config['servers'][$planID]) and $planID!='all')
        {
            die("Not existent plan.\n");
        }
        
        $verbose=$request->getParam('verbose') || $request->getParam('v');
        
        $fp=fopen("otcams.lock","w");
        if(!flock($fp, LOCK_EX|LOCK_NB)) { //try to get exclusive lock, non-blocking
            die("Another instance is running\n");
        }
        if($planID=='all')
        {
            $plans=$config['servers'];
            foreach($plans as $planID=>$value)
            {
                $this->$doAction($planID,$verbose);
            }
        }
        else
        {
            return $this->$doAction($planID,$verbose);
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
            'createfilerequest',
            'getfile',
            'cleanData',
            'parseInformation',
            'processRecords',
            'updateStatuses',
        );
        echo "Running all functions for $planID plan(s)...\n";
        foreach($functions as $function)
        {
            echo "Running $function for $planID plan(s)...\n";
            echo $this->$function($planID,$verbose).
                    "\n";
        }

        $datah=new Data();
        $config=$this->getServiceLocator()->get('config');
        if( array_key_exists( 'apply_household', $config['servers'][$planID]['eligibility']) ){
                echo "Running applyHousehold for $planID plan(s)...\n";
                $this->applyHousehold($planID, $verbose)."\n";
            return "Done all\n";
        }
        return "Done all\n";

    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @return string
     */
    public function createfilerequest($planID,$verbose)
    {

        //Is there a file for the current period (based on frequency)?
        $config=$this->getServiceLocator()->get('config');

        $datah=new Data();
        if( $datah->verify_encrypted($config) )
        {
            $datah->treat_secret($config);
        }

        if(empty($config['servers'][$planID]) and $planID!='all')
        {
            die("Not existent plan.\n");
        }
        echo "Starting create file request function for this plan(s): $planID...\n";
        $now=new DateTime('now', new DateTimeZone('America/New_York'));
        $current_day=$now->format("l");
        if(!$config['servers'][$planID]['eligibility']['schedule'][$current_day]['run'])
        {
            echo "\033[36mNo file expected today.\n\033[36m";
        }
        else
        {
            $from_valid=new DateTime($now->format("Y-m-d ").$config['servers'][$planID]['eligibility']['schedule'][$current_day]['from_time'], new DateTimeZone('America/New_York'));
            $to_valid=new DateTime($now->format("Y-m-d ").$config['servers'][$planID]['eligibility']['schedule'][$current_day]['to_time'], new DateTimeZone('America/New_York'));
            
            $conditions=new Sql\Where();
            $conditions->between('created_at',
                    $from_valid->format("Y-m-d H:i:s"),
                    $to_valid->format("Y-m-d H:i:s"));
            $eligibilityprocess=$datah->getEligibilityprocessTable($planID)
                    ->getEligibilityprocessByConditions($conditions);

            if(count($eligibilityprocess)==0)
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
                    
                    try {
                    
                        $eligibilityprocess_i=new Eligibilityprocess();
                        $eligibilityprocess_i->plan_id=$planID;
                        $eligibilityprocess_i->status='Created';
                        
                        $process_id=$datah->getEligibilityprocessTable($planID)
                                ->saveEligibilityProcess($eligibilityprocess_i);
                        /**
                         * @Todo: insert in log too.
                         */
                        if($process_id)
                        {
                            echo "\033[32mProcess with ID: $process_id was created.\n\033[0m";
                        }
                        else
                        {
                            echo "\033[31mAn error occurred while creating file request.\n\033[31m";
                            /**
                             * @Todo: report error.
                             */
                        }

                    } catch (\Exception $ex) {

                        $errorhelper=new ErrorHelper();
                        $errorhelper->email_error('crefilreq_cont_001', 1, "createfilerequest in controller OTCPIP:". $ex->getMessage(), $planID, 'eligibilityProcess', $process_id );
                        return $ex->getMessage();
                    }

                }
            }
            else
            {
                //otherwise there is nothing to do here.
                echo "\033[36mRecord already created.\n\033[0m";
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
    public function getfile($planID,$verbose)
    {

        //look for records with statuses: Created, Failed getting file
        //plan validation
       
        $config=$this->getServiceLocator()->get('config');

        $datah=new Data();
        if( $datah->verify_encrypted($config) )
        {
            $datah->treat_secret($config);
        }

        if(empty($config['servers'][$planID]) and $planID!='all')
        {
            die("Not existent plan.\n");
        }
        echo "Starting get file function for this plan(s): $planID...\n";
        $now=new DateTime('now', new DateTimeZone('America/New_York'));
        $current_day=$now->format("l");
        if(!$config['servers'][$planID]['eligibility']['schedule'][$current_day]['run'])
        {
            echo "\033[36mNo file expected today.\n\033[0m";
        }
        else
        {
            $conditions=new Sql\Where();
            $conditions->in('status', array(
                'Created',
                'Failed getting file',
            ));
            $eligibilityprocess=$datah->getEligibilityprocessTable($planID)
                    ->getEligibilityprocessByConditions($conditions);
            $process=$eligibilityprocess->current();

            if(count($eligibilityprocess)==0)
            {
                echo "\033[36mNothing to do in get file.\n\033[0m";
            }
            else
            {

                try 
                {
                    /**
                     * @Todo: Should old processes be considered?
                     */
                    $process=$eligibilityprocess->current();
                    $current_processor=new $config['servers'][$planID]['processor']();
                    $getfilestatus=$current_processor->getfile($config['servers'][$planID],$planID,$process);
                    if($getfilestatus)
                    {
                    
                        if( $config['servers'][$planID]['eligibility']['connection']['in']['is_multi_files'] )
                        {
                            $configTmp = $config['servers'][$planID];
                            $files = $datah->nameToProcessFiles( $configTmp );
                            foreach ($files as $fileInsert) 
                            {
                                $filesize = filesize( $fileInsert['path_out'].$fileInsert['format_in'] );
                                $file=new File();
                                $file->eligibility_process_id=$process->entity_id;
                                $file->filename=$fileInsert['format_in'];
                                $file->file_description="Requested file for $planID";
                                $file->size=$filesize;
                                $file->lines=0;
                                $file->status='File Gotten';
                                //insert file record
                                $file_id=$datah->getFileTable($planID)
                                        ->saveFile($file);
                                echo "\033[32mFile ".$file->filename." was saved ($file->size).\n\033[0m";
                            }
                            //update status
                            $datah->update_process_status($planID,$process,'File Gotten');
                        
                        }
                        else
                        {
                            $year4=$now->format("Y");
                            $year2=$now->format("y");
                            $month=$now->format("m");
                            $day=$now->format("d");
                            
                            $filein_template=$config['servers'][$planID]['eligibility']['connection']['in']['file']['format_in'];
                            $filein_name=  preg_replace(array('/Y{4}/','/Y{2}/','/M{2}(?!A)/','/D{2}/'), array($year4,$year2,$month,$day), $filein_template);
                            
                            $file=new File();
                            $file->eligibility_process_id=$process->entity_id;
                            $file->filename=$filein_name;
                            $file->file_description="Requested file for $planID";
                            $file->size=$getfilestatus;
                            $file->lines=0;
                            $file->status='File Gotten';
                            //insert file record
                            $file_id=$datah->getFileTable($planID)
                                    ->saveFile($file);
                            echo "\033[32mFile ".$file->filename." was saved ($getfilestatus).\n\033[0m";
                            //update status
                            $datah->update_process_status($planID,$process,'File Gotten');
                            
                        }
                    
                    }
                    else
                    {
                        /**
                         * @Todo: handle error. Update process id status, send email, log in DB
                         */
                        echo "\033[31mError while getting file\n\033[0m";
                        $datah->update_process_status($planID,$process,'Failed getting file');
                    }

                } catch (\Exception $ex) {
                    
                    $errorhelper=new ErrorHelper();
                    $errorhelper->email_error('getfile_cont_001', 1, "getfile OTCPIP:". $ex->getMessage(), $planID, 'eligibilityProcess', $process->entity_id );
                    return $ex->getMessage();

                }
            }
        }
        return "Done\n";

        
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @return string
     */
    public function cleanData($planID,$verbose)
    {
        
        //look for records with statuses: File Gotten, Failed cleaning data
        $config=$this->getServiceLocator()->get('config');
        
        $datah=new Data();
        if($datah->verify_encrypted($config))
        {
            $datah->treat_secret($config);
        }

        if(empty($config['servers'][$planID]) and $planID!='all')
        {
            die("Not existent plan.\n");
        }
        echo "Starting clean Data function for this plan(s): $planID...\n";
        $now=new DateTime('now', new DateTimeZone('America/New_York'));
        $current_day=$now->format("l");
        if(!$config['servers'][$planID]['eligibility']['schedule'][$current_day]['run'])
        {
            echo "\033[36mNo file expected today.\n\033[0m";
        }
        else
        {

            $conditions=new Sql\Where();
            $conditions->in('status', array(
                'File Gotten',
                'Failed cleaning data',
            ));
            $eligibilityprocess=$datah->getEligibilityprocessTable($planID)
                    ->getEligibilityprocessByConditions($conditions);
            
            if(count($eligibilityprocess)==0)
            {
                echo "\033[36mNothing to do in clean data.\n\033[0m";
            }
            else
            {
                /**
                 * @Todo: Should old processes be considered?
                 */
                $process=$eligibilityprocess->current();
                try 
                {
                    $entity='eligibilityProcess';
                    $current_processor=new $config['servers'][$planID]['processor']();
                    $cleandatastatus=$current_processor->cleandata($planID,$process);
                    if($cleandatastatus)
                    {
                        echo "\033[32m$cleandatastatus bytes were cleaned.\n\033[0m";
                        //update status
                        $datah->update_process_status($planID,$process,'Data cleaned');
                    }
                    else
                    {
                        /**
                         * @Todo: handle error. Update process id status, send email, log in DB
                         */
                        echo "\033[31mError in data cleaning\n\033[0m";
                        $datah->update_process_status($planID,$process,'Failed cleaning data');
                    }
                } catch (\Exception $ex) {
                    
                    $errorhelper=new ErrorHelper();
                    $errorhelper->email_error('cleandata_cont_001', 1, "cleandata OTCPIP:". $ex->getMessage(), $planID, 'eligibilityProcess', $process->entity_id );
                    return $ex->getMessage();

                }
            }
            
        }
        return "Done\n";    
        
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @return string
     */
    public function parseInformation($planID,$verbose)
    {

        //look for records with statuses: Data cleaned, Failed parsing file
        $config=$this->getServiceLocator()->get('config');
        
        $datah=new Data();
        if($datah->verify_encrypted($config))
        {
            $datah->treat_secret($config);
        }
        
        if(empty($config['servers'][$planID]) and $planID!='all')
        {
            die("Not existent plan.\n");
        }
        echo "Starting parse Information function for this plan(s): $planID...\n";
        $now=new DateTime('now', new DateTimeZone('America/New_York'));
        $current_day=$now->format("l");
        if(!$config['servers'][$planID]['eligibility']['schedule'][$current_day]['run'])
        {
            echo "\033[36mNo file expected today.\n\033[0m";
        }
        else
        {
            $conditions=new Sql\Where();
            $conditions->in('status', array(
                'Data cleaned',
                'Failed parsing file',
            ));
            $eligibilityprocess=$datah->getEligibilityprocessTable($planID)
                    ->getEligibilityprocessByConditions($conditions);
            
            if(count($eligibilityprocess)==0)
            {
                echo "\033[36mNothing to do in parse information.\n\033[0m";
            }
            else
            {
                
                    
                /**
                 * @Todo: Should old processes be considered?
                 */
                $process=$eligibilityprocess->current();
                try 
                {

                    $current_processor=new $config['servers'][$planID]['processor']();
                    $parseinformationstatus=$current_processor->parseInformation($planID,$process);
                    if($parseinformationstatus)
                    {
                        echo "\033[32m$parseinformationstatus lines were parsed.\n\033[0m";
                        //update status
                        $datah->update_process_status($planID,$process,'File parsed');
                    }
                    else
                    {
                        /**
                         * @Todo: handle error. Update process id status, send email, log in DB
                         */
                        echo "\033[31mError while parsing information\n\033[0m";
                        $datah->update_process_status($planID,$process,'Failed parsing file');
                    }
                
                } catch (\Exception $ex) {
                    
                    $errorhelper=new ErrorHelper();
                    $errorhelper->email_error('parseInf_cont_001', 1, "parseinformation OTCPIP:". $ex->getMessage(), $planID, 'eligibilityProcess', $process->entity_id );
                    return $ex->getMessage();

                }
            }
        }
        return "Done\n";
        
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @return string
     */
    public function processRecords($planID,$verbose)
    {
        //look for records with statuses: File parsed, Failed processing records
        $config=$this->getServiceLocator()->get('config');

        $datah=new Data();
        if($datah->verify_encrypted($config))
        {
            $datah->treat_secret($config);
        }

        if(empty($config['servers'][$planID]) and $planID!='all')
        {
            die("Not existent plan.\n");
        }
        echo "Starting Process Records function for this plan(s): $planID...\n";
        $now=new DateTime('now', new DateTimeZone('America/New_York'));
        $current_day=$now->format("l");
        if(!$config['servers'][$planID]['eligibility']['schedule'][$current_day]['run'])
        {
            echo "\033[36mNo file expected today.\n\033[0m";
        }
        else
        {
            $conditions=new Sql\Where();
            $conditions->in('status', array(
                'File parsed',
                'Failed processing records',
            ));
            $eligibilityprocess=$datah->getEligibilityprocessTable($planID)
                    ->getEligibilityprocessByConditions($conditions);
            
            if(count($eligibilityprocess)==0)
            {
                echo "\033[36mNothing to do in process records.\n\033[0m";
            }
            else
            {
                /**
                 * @Todo: Should old processes be considered?
                 */
                $process=$eligibilityprocess->current();
                try 
                {

                    $days_before=$config['servers'][$planID]['days_before'];
                    $current_processor=new $config['servers'][$planID]['processor']();
                    $processrecordsstatus=$current_processor->processRecords($planID,$process,$days_before);
                    if($processrecordsstatus)
                    {
                        echo "\033[32m$processrecordsstatus records were processed.\n\033[0m";
                        //update status
                        $datah->update_process_status($planID,$process,'Records processed');
                    }
                    else
                    {
                        /**
                         * @Todo: handle error. Update process id status, send email, log in DB
                         */
                        echo "\033[31mError while processing records.\n\033[0m";
                        $datah->update_process_status($planID,$process,'Failed processing records');
                    }
                
                } 
                catch (\Exception $ex) 
                {
                    
                    $errorhelper=new ErrorHelper();
                    $errorhelper->email_error('proccRec_cont_001', 1, "processrecords OTCPIP:". $ex->getMessage(), $planID, 'eligibilityProcess', $process->entity_id );
                    return $ex->getMessage();

                }
            }
        }
        return "Done\n";    
        
    }
    
    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @return string
     */
    public function updateStatuses($planID,$verbose)
    {
        
        $config=$this->getServiceLocator()->get('config');
        
        $datah=new Data();
        if($datah->verify_encrypted($config))
        {
            $datah->treat_secret($config);
        }

        if(empty($config['servers'][$planID]) and $planID!='all')
        {
            die("Not existent plan.\n");
        }
        echo "Starting Process Records function for this plan(s): $planID...\n";
        $current_processor=new $config['servers'][$planID]['processor']();
        $updatestatus=$current_processor->updateStatus($planID);
        echo "\033[32m$updatestatus statuses were updated.\n\033[0m";
        return "Done\n";
            
    }

    /**
     * 
     * @param type $planID
     * @param type $verbose
     * @return string
     */
    public function applyHousehold($planID,$verbose)
    {
        $config=$this->getServiceLocator()->get('config');
        if(empty($config['servers'][$planID]) and $planID!='all')
        {
            die("Not existent plan.\n");
        }
        echo "Starting apply household function for this plan(s): $planID...\n";
        $current_processor=new $config['servers'][$planID]['processor']();
        $householdLogic=$current_processor->applyHousehold($planID);
        echo "\033[32m$householdLogic statuses were updated.\n\033[0m";
        return "Done\n";
    }
}

