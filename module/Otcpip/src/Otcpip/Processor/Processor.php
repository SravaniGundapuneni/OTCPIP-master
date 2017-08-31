<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Processor;

use Otcpip\Helper\Connector;

use Otcpip\Helper\Data;

use DateTime;
use DateTimeZone;

use Otcpip\Model\File;
use Otcpip\Model\Members;
use Otcpip\Model\Memberstmp;
use Otcpip\Model\CleansedFileTable;

use Otcpip\Model\Elprocessmemberrel;

use Otcpip\Helper\Error as ErrorHelper;

use Zend\Db\Sql;

/**
 * Description of Processor
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Processor {
    /**
     * 
     * @param type $config
     * @return type
     */
    public function getFile($config,$planID,$process) {
        echo "Connecting...\n";
        
        $connector=new Connector();
        return $connector->getRemoteFile($config);
    }
    
    /**
     * 
     * @param type $planID
     * @param type $process
     * @return int
     */
    public function cleanData($planID,$process)
    {
        echo "Cleaning data...\n";
        //Get file
        $datah=new Data();
        $config=$datah->getConfiguration('config');
        $files=$datah->getFileTable($planID)
                ->getFileByConditions(array(
                    'eligibility_process_id'=>$process->entity_id,
                    'status'=>'File Gotten',
                ));
        if(count($files)==0)
        {
            /**
             * @Todo: Handle error.
             */
            echo "No files\n";
            return 0;
        }

        try 
        {
            
            $origfile=$files->current();
            //Open "original" file
            $filename=$config['servers'][$planID]['eligibility']['connection']['in']['file']['path_out'].$origfile->filename;
            if(!file_exists($filename))
            {
                /**
                 * @Todo: Handle error. Update file status to failed clean data.
                 */
                echo "File does not exist: $filename\n";
                return 0;
            }
            //$orighandle=  fopen($filename, 'r'); //--###
            
            $now=new DateTime('now', new DateTimeZone('America/New_York'));
            $year=$now->format("Y");
            $month=$now->format("m");
            $day=$now->format("d");
            
            $fileout_template=$config['servers'][$planID]['eligibility']['file']['name'];
            $fileout_name=  preg_replace(array('/Y{4}/','/M{2}(?!A)/','/D{2}/'), array($year,$month,$day), $fileout_template);
            
            $filename_dest=$config['servers'][$planID]['eligibility']['file']['path_out'].$fileout_name;
            //$desthandle=  fopen($filename_dest, 'w'); //--###
            //Clean Data
            //Nothing to do in clean data
            //Save in cleaned file
            //$writtenBytes=stream_copy_to_stream($orighandle, $desthandle); //--###
            $writtenBytes = filesize($filename); //++### just get file size
            //fclose($orighandle); //--###
            //fclose($desthandle); //--###
            //clean table and them dump file into db
            $cleanedTable = new CleansedFileTable();
            $cleanedTable->delete();
            $cmd = "LOAD DATA LOCAL INFILE='$filename' into table cleansedTable";
            shell_exec($cmd);
            
            if($writtenBytes)
            {
                //updates old file status.
                $origfile->status='File Gotten processed';
                $datah->getFileTable($planID)
                        ->saveFile($origfile);
                //write to file record.
                $dataclean_file=new File();
                $dataclean_file->eligibility_process_id=$process->entity_id;
                $dataclean_file->filename=$fileout_name;
                $dataclean_file->file_description="File cleaned and ready to be parsed ($planID).";
                $dataclean_file->size=$writtenBytes;
                $dataclean_file->lines=0;
                $dataclean_file->status='Data cleaned';
                
                $datah->getFileTable($planID)
                        ->saveFile($dataclean_file);
                /**
                 * @Todo: Destroy original file gotten?
                 */
            }
            return $writtenBytes;

        } catch (\Exception $ex) {

            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('cleandata_proc_001', 1, "cleandata OTCPIP:". $ex->getMessage(), $planID, 'file', $process->entity_id );
            return false;

        }
        
    }
    
    /**
     * 
     * @param type $planID
     * @param type $process
     * @return int
     */
    public function parseInformation($planID,$process) 
    {
        echo "Parsing information...\n";
        //Get file
        $datah=new Data();
        $config=$datah->getConfiguration('config');
        $cleansedFileTable = $datah->getCleansedFileTable($planID)
                  ->getFileByConditions(array(
                    'eligibility_process_id'=>$process->entity_id,
                    'status'=>'Data cleaned',
                ));
        $records = $cleansedFileTable->select();
        /*
        $files=$datah->getFileTable($planID)
                ->getFileByConditions(array(
                    'eligibility_process_id'=>$process->entity_id,
                    'status'=>'Data cleaned',
                ));
        if(count($files)==0)
        {
            
            return 0;
        }
        $origfile=$files->current();
        //Open "original" file
        $filename=$config['servers'][$planID]['eligibility']['file']['path_out'].$origfile->filename;
        if(!file_exists($filename))
        {
            
            
            return 0;
        }
         */
        //
        try 
        {

            //$orighandle=  fopen($filename, 'r'); --###
            //Parse
            $lines=0;
            $discarded_lines=0;
            //while(!feof($orighandle)) //--###
            foreach($records as $row) // ++###
            {
                //$line=fgets($orighandle); //--###
                $lines = $row['columnName'];
                $member=new Memberstmp();
                foreach($config['servers'][$planID]['eligibility']['file']['layout']['fields'] as $fields)
                {
                    if(!empty($fields['equivalent_standard_field']))
                    {
                        $value=trim(substr($line, ($fields['start_pos']-1),$fields['length']));
                    if( $fields['equivalent_standard_field']=='zipcode' )
                    {
                        $value = substr($value,0, 5);
                    }
                        if(!empty($fields['parse']))
                        {
                            if($fields['parse']['type']=='date')
                            {
                                if(preg_match($fields['parse']['from'], $value, $matches))
                                {
                                   $year_s=$matches[$fields['parse']['year_index']];
                                   $month_s=$matches[$fields['parse']['month_index']];
                                   $day_s=$matches[$fields['parse']['day_index']];
                                   if($year_s>=0 and $year_s<=30)
                                   {
                                       $year_s+=2000;
                                   }
                                   $value=sprintf("%04d-%02d-%02d", $year_s,$month_s,$day_s);
                                }
                                else
                                {
                                    $value='0000-00-00';
                                }
                            }
                        }
                        $member->$fields['equivalent_standard_field']=  $value;
                    }
                }
                
                $member->member_id=preg_replace("/\s+/", '', $member->member_id);
                $member->eligibility_process_id=$process->entity_id;
                
                if(empty($member->disenroll_date) or $member->disenroll_date=='0000-00-00' or $member->disenroll_date=='00000000')
                {
                    $member->disenroll_date='2078-12-31';
                }
                
                $now=new DateTime('now', new DateTimeZone('America/New_York'));
                $member->member_last_update=$now->format('Y-m-d');
                
                $member=$this->calculate_benefit_plan($member,$planID);
                
                $member=$this->calculate_benefit($member,$planID);
                $member=$this->calculate_household($member);
                $member=$this->calculate_status($member);
                
                $member->plan_code=$planID;
                if(!empty($member->member_id) and !empty($member->benefit_amount) and !empty($member->period_factor) and !empty($member->household) and !empty($member->form_id))
                {
                    //Save in DB tmp
                    $datah->getMemberstmpTable($planID)
                            ->saveMemberstmp($member);
                    if($this->validate_member($member))
                    {
                        echo ".";
                    }
                    else
                    {
                        echo "x";
                    }
                }
                else
                {
                    echo "d";
                    $discarded_lines++;
                }
                $lines++;
               // if($lines>=10)
               // {
               //     break;
               // }
            }
            echo "\n";
            //fclose($orighandle); //--###
        } catch (\Exception $ex) {
            
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('parseInf_proc_001', 1, "parseInformation OTCPIP:". $ex->getMessage(), $planID, 'memberTemporary', $process->entity_id );
            return false;

        }

        try 
        {
            
            if($lines)
            {
                //updates old file status.
                $origfile->status='File parsed';
                $datah->getFileTable($planID)
                        ->saveFile($origfile);
                /**
                 * @Todo: Destroy original file gotten?
                 */
            }
            //Delete data cleaned file?
            return $lines;
            
        } catch (\Exception $ex) {
            
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('parseInf_proc_002', 1, "parseInformation OTCPIP:". $ex->getMessage(), $planID, 'file', $process->entity_id );
            return false;            

        }
            

        
    }
    
    /**
     * 
     * @param Memberstmp $member
     * @param type $planID
     * @return Memberstmp
     */
    public function calculate_benefit_plan(Memberstmp $member,$planID)
    {
        $datah=new Data();
        $config=$datah->getConfiguration('config');
        if(isset($config['servers'][$planID]['eligibility']['benefit_plan']))
        {
            $member->benefit_plan=$config['servers'][$planID]['eligibility']['benefit_plan'];
            $member->region_code=$member->benefit_plan;
        }
        return $member;
    }
    
    /**
     * 
     * @param Members $member
     * @return Members
     */
    public function calculate_benefit(Memberstmp $member,$planID)
    {
        $now=new DateTime('now', new DateTimeZone('America/New_York'));
        
        $conditions=new Sql\Where();
        $conditions->greaterThan('valid_to',$now->format("Y-m-d"))
                ->lessThanOrEqualTo('valid_from',$now->format("Y-m-d"))
                ->equalTo('group_number', $member->group_name);
        
        $datah=new Data();
        $benefits=$datah->getBenfitTable($planID)
                ->getBenefitByConditions($conditions);
        if($benefits->count()==0)
        {
            /**
             * @todo: handle error
             */
            return $member;
        }
        $benefit=$benefits->current();
        
        $member->form_id=$benefit->form_id;
        $member->period_factor=$benefit->period_factor;
        $member->benefit_amount=$benefit->benefit_amount;
        
        return $member;
    }
    
    /**
     * 
     * @param Memberstmp $member
     * @return Memberstmp
     */
    public function calculate_household(Memberstmp $member)
    {
        /**
        * @Todo: verify if household should be the member_id
        */
        $member->household=$member->member_id;
        return $member;
    }
    
    /**
     * 
     * @param Memberstmp $member
     * @return Memberstmp
     */
    public function calculate_status(Memberstmp $member)
    {
        /**
        * @Todo: Verify how to set form_id
        */
       $member->status='Enabled';
       return $member;
    }
    
    /**
     * 
     * @param Memberstmp $member
     * @return Memberstmp
     */
    public function validate_member(Memberstmp $member)
    {
        /**
         * @Todo: run validations:
         */
        //Validate fields
        $valid=1;
        if($valid)
        {
            $member->status='Enabled';
        }
        else
        {
            $member->status='Invalid';
        }
        return $member;
    }
    
    /**
     * 
     * @param type $planID
     * @param type $process
     * @param type $days_before
     * @return type
     */
    public function processRecords($planID,$process,$days_before)
    {
        echo "Parsing information...\n";
        $datah=new Data();
        //Insert Disenrolled records in elprocess_member_rel
        try 
        {

            $disenrolled=$datah->getMembersCollection($planID)
                    ->apply_disenrolled(array(
                        'member_status'=>'Enabled',
                        'eligibility_process_id'=>$process->entity_id,
                    ),
                    $days_before);
            echo "$disenrolled disenrolled\n";
            //Update disenrolled
            $updated=$datah->getMembersCollection($planID)
                    ->apply_updated(array(
                       'eligibility_process_id'=>$process->entity_id, 
                    ));
            echo "$updated updated\n";
            
            
            //Get records to insert
            $to_insert=$datah->getMembersCollection($planID)
                    ->apply_to_insert(array(
                        'eligibility_process_id'=>$process->entity_id,
                    ));
            
        } catch (\Exception $ex) {
            
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('proccRec_proc_001', 1, "processrecords OTCPIP:". $ex->getMessage(), $planID, 'elProcessMember', $process->entity_id );
            return false;

        }

        
        try 
        {

            $inserted=0;
            $fail_inserted=0;
            
            while($to_insert->valid())
            {
                $currec=$to_insert->current();
                unset($currec['entity_id']);
                unset($currec['eligibility_process_id']);
                $member=new Members();
                $member->exchangeArray($currec);
                if(!empty($member->member_id))
                {
                    //Insert in members
                    $member_ins=$datah->getMembersTable($planID)
                            ->saveMembers($member);
                    $elprocessmemberrel=new Elprocessmemberrel();
                    $elprocessmemberrel->eligibility_process_id=$process->entity_id;
                    if($member_ins)
                    {
                        echo "i";
                        $inserted++;
                        $elprocessmemberrel->member_entity_id=$member_ins;
                        $elprocessmemberrel->status='Inserted';
                    }
                    else
                    {
                        echo "!";
                        $fail_inserted++;
                        $elprocessmemberrel->member_id=$member->member_id;
                        $elprocessmemberrel->status='Failed Insertion';
                    }
                    //Insert in elprocess_member_rel
                    //Consider status Inserted and Failed insertion.
                    
                    //Insert in elprocess_member_rel
                    $datah->getElprocessmemberrelTable($planID)
                            ->saveElprocessmemberrel($elprocessmemberrel);
                }
                
                $to_insert->next();
            }
            
            echo "\n$inserted inserted, $fail_inserted failed insertion\n";
            $processed_records=$datah->getElprocessmemberrelCollection($planID)
                    ->getProcessedRecordsCount(array(
                        'eligibility_process_id'=>$process->entity_id,
                    ));
            
            echo "\n";
            //Return results
            return $processed_records['processed_records'];

        } catch (\Exception $ex) {
            
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('proccRec_proc_002', 1, "processrecords OTCPIP:". $ex->getMessage(), $planID, 'member', $process->entity_id );
            return false;

        }
        
    }
    
    /**
     * 
     * @param type $planID
     */
    public function updateStatus($planID) {
        echo "Updating statuses...\n";
        $datah=new Data();
        $statusesupdated=$datah->getMembersCollection($planID)
                ->apply_statuses();
        echo "\n";
        return $statusesupdated;
    }

    /**
     * 
     * @param type $planID
     */
     public function applyHousehold($planID)
     {
        echo "Apply household logic...\n";
        $datah=new Data();
        $householdupdated=$datah->getMembersCollection($planID)
                ->apply_household_logic($planID);
        echo "\n";
        return $householdupdated;
     }
}
