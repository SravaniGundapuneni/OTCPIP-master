<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Processor\Coventry;

use Otcpip\Helper\Data;

use DateTime;
use DateTimeZone;

use Otcpip\Model\Members;
use Otcpip\Model\Memberstmp;

use Zend\Db\Sql;
use Otcpip\Processor\Processor;
use Otcpip\Helper\Connector;

use Otcpip\Helper\Error as ErrorHelper;

/**
 * Description of Aetnail
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Coventry extends Processor {
    
    public function getFile($config,$planID,$process) {
        echo "Connecting...\n";
        try 
        {
            $connector               = new Connector();
            $tbytes                  = 0;

            $now                     = new DateTime('now', new DateTimeZone('America/New_York'));       
            $year4                   = $now->format("Y");
            $year2                   = $now->format("y");
            $month                   = $now->format("m");
            $day                     = $now->format("d");
            $tmpBytes                = 0;

            $originalPath            = $config['eligibility']['connection']['in']['file']['path_out'];
            $originalFileInTemplate  = $config['eligibility']['connection']['in']['file']['format_in']; 
            $nameFileTmp             = preg_replace(array('/Y{4}/','/Y{2}/','/M{2}(?!A)/','/D{2}/'), array($year4,$year2,$month,$day), $originalFileInTemplate);
            $originalFileOutTemplate = $config['eligibility']['connection']['in']['file']['format_out'];
            $mergeFile               = $originalPath.$nameFileTmp;
            $fileTmp                 = fopen( $mergeFile, 'w' );
            $sizeTorray              = sizeof( $config['eligibility']['connection']['in']['files'] );
            $position                = 0;

           foreach( $config['eligibility']['connection']['in']['files'] as $configfile )
           {
                $position++;
                // Apply parsing for download files DDMMYYYY
                $fileInTmp = preg_replace(array('/D{2}/','/M{2}(?!A)/','/Y{4}/','/Y{2}/'), array($day,$month,$year4,$year2), $configfile['format_in']);
                $config['eligibility']['connection']['in']['file']['format_in']  = $fileInTmp;

                $config['eligibility']['connection']['in']['file']['path_out']   = $configfile['path_out'];

                $fileOutTmp = preg_replace(array('/D{2}/','/M{2}(?!A)/','/Y{4}/','/Y{2}/'), array($day,$month,$year4,$year2), $configfile['format_out']);
                $config['eligibility']['connection']['in']['file']['format_out'] = $fileOutTmp;

                echo "Getting file: ". $fileInTmp ."\n";
                $tbytes+=$connector->getRemoteFile( $config );

                if( $tbytes == 0 )
                {
                    return 0;
                }
                //Merging files
                // ========================================================================================
         
                $obtainedFile = $configfile['path_out'].$fileInTmp;

                echo "Getting file: ". $fileInTmp ."\n";
                $tbytes+=$connector->getRemoteFile( $config );

                //Merging files
                // ========================================================================================
                    
                    $obtainedFile = $configfile['path_out'].$fileInTmp;

                    if( file_exists( $mergeFile ) )
                    {
                      $openFile = fopen( $obtainedFile, 'r' );
                      $tmpBytes += stream_copy_to_stream( $openFile, $fileTmp );
                      fclose( $openFile );
                    }
                    else
                    {
                      echo "File not exist\n";
                    }

                //========================================================================================
                //When merge all files, we restore original settings
                if( $sizeTorray == $position )
                {
                    $config['eligibility']['connection']['in']['file']['format_in']  = $originalFileInTemplate;
                    $config['eligibility']['connection']['in']['file']['path_out']   = $originalPath;
                    $config['eligibility']['connection']['in']['file']['format_out'] = $originalFileOutTemplate;
                }
           }
           fclose( $fileTmp );
           
           return $tbytes;
        } 
        catch (\Exception $ex) 
        {
            echo "An error was ocurred when create this file: $mergeFile". $ex->getMessage()."\n";
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('getfile_overplan_001', 1, "getfile OTCPIP:". $ex->getMessage(), $planID, 'eligibilityProcess', $process->entity_id );
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
        $files=$datah->getFileTable($planID)
                ->getFileByConditions(array(
                    'eligibility_process_id'=>$process->entity_id,
                    'status'=>'Data cleaned',
                ));
        if(count($files)==0)
        {
            /**
             * @Todo: Handle error.
             */
            return 0;
        }
        $origfile=$files->current();
        //Open "original" file
        $filename=$config['servers'][$planID]['eligibility']['file']['path_out'].$origfile->filename;
        if(!file_exists($filename))
        {
            /**
             * @Todo: Handle error. Update file status to failed clean data.
             */
            
            return 0;
        }

        try 
        {
                                                      
            $orighandle=  fopen($filename, 'r');
            //Parse
            $lines=0;
            $discarded_lines=0;
            while(!feof($orighandle))
            {
                $column60 = "";
                $column33 = "";
                $line=fgets($orighandle);
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
                                    $value=sprintf("%04d-%02d-%02d", $matches[$fields['parse']['year_index']],$matches[$fields['parse']['month_index']],$matches[$fields['parse']['day_index']]);
                                }
                                else
                                {
                                    $value='0000-00-00';
                                }
                            }
                        }
                        
                        if( $fields['equivalent_standard_field'] != 'financial_number' )
                        {
                            $member->$fields['equivalent_standard_field']=  $value;
                        }
                        else
                        {
                            $member->$fields['equivalent_standard_field'] = $column60.$column33;
                        }
                    }
                    else
                    {
                        $value = trim(substr($line, ($fields['start_pos']-1),$fields['length']));
                        ( $fields['name'] == 'IDX_plataform' )?( $column60 = $value):($column60);
                        ( $fields['name'] == 'enrollment_loc' )?( $column33 = $value):($column33);
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
            fclose($orighandle);
            
        } 
        catch (\Exception $ex) 
        {
            
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('parseInf_overplan_001', 1, "parseInformation OTCPIP:". $ex->getMessage(), $planID, 'memberTemporary', $process->entity_id );
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
            $errorhelper->email_error('parseInf_overplan_002', 1, "parseInformation OTCPIP:". $ex->getMessage(), $planID, 'file', $process->entity_id );
            return false;

        }

        
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
        $conditions->nest()
               ->equalTo('group_number', $member->line_of_business)
               ->or
               ->equalTo('benefit_plan', $member->benefit_plan)
               ->unnest()
               ->and
               ->greaterThan('valid_to',$now->format("Y-m-d"))
               ->lessThanOrEqualTo('valid_from',$now->format("Y-m-d"));

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
}
