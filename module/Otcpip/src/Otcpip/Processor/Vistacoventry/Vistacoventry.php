<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Processor\Vistacoventry;

use Otcpip\Helper\Data;

use DateTime;
use DateTimeZone;

use Otcpip\Model\Members;
use Otcpip\Model\Memberstmp;

use Zend\Db\Sql;
use Otcpip\Processor\Processor;
use Otcpip\Helper\Connector;

use ZipArchive;

use Otcpip\Helper\Error as ErrorHelper;

/**
 * Description of Aetnail
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Vistacoventry extends Processor {
    
    public function getFile($config, $planID, $process) {
        echo "Connecting...\n";
        $connector = new Connector();
        $tbytes = 0;
       
        $now = new DateTime('now', new DateTimeZone('America/New_York'));
        $year4 = $now->format("Y");
        $year2 = $now->format("y");
        $month = $now->format("m");
        $day   = $now->format("d");
      
        // get Year, month and day separately
        // these variables are for getfile
        $timeStampDay = strtotime ( $now->format('Ymd') );
        $monthDate    = date( 'm', $timeStampDay );
        $dayDate      = date( 'd', $timeStampDay );
        $yearDate     = date( 'Y', $timeStampDay );

        // these variables are for merging files
        $originalPath            = $config['eligibility']['connection']['in']['file']['path_out'];
        $originalFileInTemplate  = $config['eligibility']['connection']['in']['file']['format_in']; 
        $nameFileTmp             = preg_replace(array('/Y{4}/','/Y{2}/','/M{2}(?!A)/','/D{2}/'), array($year4,$year2,$month,$day), $originalFileInTemplate);
        $originalFileOutTemplate = $config['eligibility']['connection']['in']['file']['format_out'];
        $mergeFile               = $originalPath.$nameFileTmp;
        $fileTmp                 = fopen( $mergeFile, 'w' );
        $sizeTorray              = sizeof( $config['eligibility']['connection']['in']['files'] );
        $position                = 0;

        try {
            
            //====================================================================================================
            foreach( $config['eligibility']['connection']['in']['files'] as $key => $configfile )
            {
                $timeStampBackDay = 0;
                if( $configfile['parse']['type'] == 'date')
                {
                    switch( $now->format('l') )
                    {
                        case 'Tuesday': //When today is Tuesday
                        {
                            // if today is Tuesday and file download on Tuesday
                            if( $configfile['parse']['download_day'] == 'Tuesday' ){
                                $toMatch = $timeStampDay;
                            }
                            // if today is Tuesday and file download on Thursday
                            // we need back 5 days
                            else if( $configfile['parse']['download_day'] == 'Thursday' )
                            {
                                $toMatch = date( "mdY",strtotime ( '-5 day' , $timeStampDay ) );
                                $timeStampBackDay = strtotime ( '-5 day' , $timeStampDay );
                            }

                        } break;
                        case 'Thursday': //When today es Thursday
                        {
                            // if today is Thursday and file download on Tuesday
                            // we need back 2 days
                            if( $configfile['parse']['download_day'] == 'Tuesday' ){
                                $toMatch = date( "mdY",strtotime ( '-2 day' , $timeStampDay ) );
                                $timeStampBackDay = strtotime ( '-2 day' , $timeStampDay );
                            }
                            // if today is Thursday and file download on Thursday
                            else if( $configfile['parse']['download_day'] == 'Thursday' )
                            {
                                $toMatch = $timeStampDay;
                            }
                          
                        } break;

                    }

                    // Reorder parsing
                    if(preg_match($configfile['parse']['from'], $toMatch, $matches))
                    {
                        // If i need get a previus file
                        if( $timeStampBackDay > 0 )
                        {
                            $monthBack = date( 'm', $timeStampBackDay );
                            $dayBack   = date( 'd', $timeStampBackDay );
                            $yearBack  = date( 'Y', $timeStampBackDay );
                        }
                        else
                        {
                            $monthBack = date( 'm', $timeStampDay );
                            $dayBack   = date( 'd', $timeStampDay );
                            $yearBack  = date( 'Y', $timeStampDay );
                        }

                        // Apply parsing for download files MMDDYYYY
                        if( sizeof( $matches ) == 3 )
                        {
                            $fileInTmp  = preg_replace(array('/M{2}/','/D{2}/'), array($monthBack,$dayBack), $configfile['format_in']);
                            $fileOutTmp = preg_replace(array('/M{2}/','/D{2}/'), array($monthBack,$dayBack), $configfile['format_out']);
                        }
                        else if( sizeof( $matches ) == 4 )
                        {
                            $fileInTmp  = preg_replace(array('/M{2}/','/D{2}/','/Y{4}/'), array($monthBack,$dayBack,$yearBack), $configfile['format_in']);
                            $fileOutTmp = preg_replace(array('/M{2}/','/D{2}/','/Y{4}/'), array($monthBack,$dayBack,$yearBack), $configfile['format_out']);
                        }
                    }
                    
                    // Downloading file
                    if( isset($fileInTmp) && $fileOutTmp ){
                        $config['eligibility']['connection']['in']['file']['format_in']  = $fileInTmp;
                        $config['eligibility']['connection']['in']['file']['path_out']   = $configfile['path_out'];
                        $config['eligibility']['connection']['in']['file']['format_out'] = $fileOutTmp;
                        
                        echo "Getting file: ". $fileInTmp ."\n";
                        $tbytes+=$connector->getRemoteFile( $config );
                        if( $tbytes == 0 )
                        {
                            return 0;
                        }
                    }

                    // Unzip File
                    //==========================================================
                    $extension     = end( explode( '.', $fileInTmp ) );
                    if( $extension == 'zip' )
                    {
                        $unzipFile = $this->unzipFile( $configfile['path_out'], $fileInTmp, $planID, $process );
                        // Overwrite the variable momentarily to make the merge
                        $config['eligibility']['connection']['in']['file']['format_out'] = $configfile['namefile_unzip'];
                        if( $unzipFile )
                        {
                            echo "The file was unzip correctly in ". $configfile['path_out'].$configfile['format_out'] ."\n";
                        }
                    }
                    //==========================================================
                    
                    // Mergin Files
                    //==========================================================
                    $position++;
                    $this->mergeFiles( $config, $mergeFile, $fileTmp, $planID, $process );
                    if( $sizeTorray == $position )
                    {
                        $config['eligibility']['connection']['in']['file']['format_in']  = $originalFileInTemplate;
                        $config['eligibility']['connection']['in']['file']['path_out']   = $originalPath;
                        $config['eligibility']['connection']['in']['file']['format_out'] = $originalFileOutTemplate;
                    }
                    //==========================================================
                }
                
            }
            //====================================================================================================          
            
        }
        catch (\Exception $ex)
        {
            echo "An error was ocurred when download file: ". $ex->getMessage()."\n";
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('getfile_overplan_003', 1, "getfile OTCPIP:". $ex->getMessage(), $planID, 'gettingFile', $process->entity_id );
            return false;   
        }
       
        return $tbytes;
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
                
                $member=$this->calculate_family_id($member,$planID);
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
               // if($lines>=5)
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
            $errorhelper->email_error('parseInf_overplan_001', 1, "parseInformation OTCPIP:". $ex->getMessage(), $planID, 'formatPlain', $process->entity_id );
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
                    ->equalTo('group_number', $member->family_id)
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

    /**
     * [calculate_family_id Calculate family id]
     * @param  Memberstmp $member [Object Members]
     * @param  [String]     $planID [Identification of plan]
     * @return [type]             [description]
     */
    public function calculate_family_id(Memberstmp $member,$planID)
    {
        if( $member->benefit_plan == 70104 )
        {
            $member->family_id = "MMAL";
        }
        else
        {
            switch( substr($member->benefit_plan, 0, 2) )
            {
                case 18: { $member->family_id = "RIDERL"; } break;
                case 13: case 17: case 30: { $member->family_id = "COMM"; } break;
                case 70: { $member->family_id = "LTC"; } break;
            }
        }

       return $member;
    }

    /**
     * [unzipFile Method for unzip file]
     * @param  [String] $pathOut [Path to unzip file]
     * @param  [String] $fileInTmp [File to unzipe]
     * @return [Boolean]         [true or false]
     */
    public function unzipFile( $pathOut, $fileInTmp, $planID, $process ){
        try 
        {
          
            $zip = new ZipArchive();
            // Extract file
            if ($zip->open( $pathOut.$fileInTmp ) === TRUE) 
            {
                $zip->extractTo( $pathOut );
                $zip->close();
            } 
            else 
            {
              /**
               * @Todo: handle error.
               */
            }
          
        }
        catch(\Exception $ex)
        {
            echo "An error was ocurred when unzip this file: $pathOut.$fileInTmp". $ex->getMessage()."\n";
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('getfile_overplan_002', 1, "getfile OTCPIP:". $ex->getMessage(), $planID, 'unzipfile', $process->entity_id );
            return false;
        }
        
        return true;
    }

    /**
     * [mergeFiles Merging files]
     * @param  [Array] $config    [Configuration local]
     * @param  [String] $mergeFile [Path of merge file]
     * @param  [String] $fileTmp   [Name of temporally file]
     */
    public function mergeFiles( $config, $mergeFile, $fileTmp, $planID, $process ){
        try {

            $obtainedFile = $config['eligibility']['connection']['in']['file']['path_out'].$config['eligibility']['connection']['in']['file']['format_out'];
            if( file_exists( $mergeFile ) )
            {
              $openFile = fopen( $obtainedFile, 'r' );
              stream_copy_to_stream( $openFile, $fileTmp );
              fclose( $openFile );
            }
            else
            {
              echo "File not exist\n";
            }

        } 
        catch (\Exception $ex) 
        {
            echo "An error was ocurred when create this file: $mergeFile". $ex->getMessage()."\n";
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('getfile_overplan_003', 1, "getfile OTCPIP:". $ex->getMessage(), $planID, 'mergefile', $process->entity_id );
            return false;
        }       
    }
}