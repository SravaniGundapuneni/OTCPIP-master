<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Processor\Betterhealth;

use Otcpip\Helper\Connector;

use Otcpip\Helper\Data;

use DateTime;
use DateTimeZone;

use Otcpip\Model\File;
use Otcpip\Model\Members;
use Otcpip\Model\Memberstmp;

use Zend\Db\Sql;
use Otcpip\Processor\Processor;

use Otcpip\Helper\Error as ErrorHelper;

/**
 * Description of Aetnail
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Betterhealth extends Processor {
    
    /**
     * [getFile method for getfile from a host]
     * @param  [Array] $config [Configuration for download files]
     * @return [Integer]         [bytes downloaded]
     */
    public function getFile($config,$planID,$process) {
        echo "Connecting...\n";
        try 
        {
            $connector = new Connector();
            $tbytes = 0;

                $datah=new Data();
                $files = $datah->nameToProcessFiles( $config );

                // Downloading files
                foreach ($files as $file)
                {
                    $config['eligibility']['connection']['in']['file']['format_in']  = $file['format_in'];
                    $config['eligibility']['connection']['in']['file']['path_out']   = $file['path_out'];
                    $config['eligibility']['connection']['in']['file']['format_out'] = $file['format_out'];
                    
                    echo "Getting file: ". $file['format_in'] ."\n";
                    $tbytes+=$connector->getRemoteFile( $config );
                    if( $tbytes == 0 )
                    {
                        return 0;
                    }
                }
                
            return $tbytes;
       
        }
        catch (\Exception $ex)
        {
            echo "An error was ocurred when download file: ". $ex->getMessage()."\n";
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('getfile_overplan_001', 1, "getfile OTCPIP:". $ex->getMessage(), $planID, 'gettingFile', $process->entity_id );
            return false;
        }
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
        $origfile=$files->current();

        //================================================================================================
        try 
        {

            $configTmp = $config['servers'][$planID];
            $filesToProcess = $datah->nameToProcessFiles( $configTmp );
            foreach ($filesToProcess as $fileVerify) {
               if(!file_exists( $fileVerify['path_out'].$fileVerify['format_in'] ))
                {
                    /**
                     * @Todo: Handle error. Update file status to failed clean data.
                     */
                    echo "File does not exist: ". $fileVerify['path_out'].$fileVerify['format_in'] ."\n";
                    return 0;
                } 
            }
            
            $writtenBytes = 0;
            foreach ($filesToProcess as $index => $fileClean) {
                
                $orighandle = fopen( $fileClean['path_out'].$fileClean['format_in'], 'r' );

                $now   = new DateTime('now', new DateTimeZone('America/New_York'));
                $year  = $now->format("Y");
                $month = $now->format("m");
                $day   = $now->format("d");

                $fileout_template = $config['servers'][$planID]['eligibility']['file']['name'];
                $fileout_name = preg_replace(array('/Y{4}/','/M{2}(?!A)/','/D{2}/','/X{1}/'), array($year,$month,$day,($index+1)), $fileout_template);

                $filename_dest=$config['servers'][$planID]['eligibility']['file']['path_out'].$fileout_name;
                $desthandle=  fopen($filename_dest, 'w');

                $writtenBytes += stream_copy_to_stream($orighandle, $desthandle);
                fclose($orighandle);
                fclose($desthandle);
                if($writtenBytes > 0)
                {
                    $filesize = filesize( $fileClean['path_out'].$fileClean['format_in'] );
                    //updates old file status.
                    $origfile->status='File Gotten processed';
                    $datah->getFileTable($planID)
                            ->saveFile($origfile);
                    //write to file record.
                    $dataclean_file=new File();
                    $dataclean_file->eligibility_process_id=$process->entity_id;
                    $dataclean_file->filename=$fileout_name;
                    $dataclean_file->file_description="File cleaned and ready to be parsed ($planID).";
                    $dataclean_file->size=$filesize;
                    $dataclean_file->lines=0;
                    $dataclean_file->status='Data cleaned';
                    
                    $datah->getFileTable($planID)
                            ->saveFile($dataclean_file);
                    /**
                     * @Todo: Destroy original file gotten?
                     */
                }

            }
            
            return $writtenBytes;

        } catch (\Exception $ex) {
            echo "An error was ocurred when cleanData". $ex->getMessage()."\n";
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('cleandata_overplan_001', 1, "cleandata OTCPIP:". $ex->getMessage(), $planID, 'file', $process->entity_id );
            return false;
        }
        //================================================================================================
        
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
        
        $lines = 0;
        while( $files->valid() )
        {
            $current_file = $files->current();
            $filename = $config['servers'][$planID]['eligibility']['file']['path_out']. $current_file->filename;
            
            if(!file_exists( $filename ))
            {
                /**
                 * @Todo: Handle error. Update file status to failed clean data.
                 */
                echo "File does not exist: ". $filename ."\n";
                return 0;
            }

            if( $files->key() == 0 )
            {
                echo print_r( "read_format_5010\n", 1 );
                $lines += $this->read_format_5010( $config, $filename, $planID, $process );
            }
            else
            {
                echo print_r( "\nread_format_plain\n", 1 );
                $lines += $this->read_format_plain( $config, $filename, $planID, $process );
            }
            
            $files->next();
            
            try {
                
                if($lines)
                {
                    //updates old file status.
                    $current_file->status='File parsed';
                    $datah->getFileTable($planID)
                            ->saveFile($current_file);
                    /**
                     * @Todo: Destroy original file gotten?
                     */
                }
            } 
            catch (\Exception $ex) 
            {
                $errorhelper=new ErrorHelper();
                $errorhelper->email_error('parseInf_overplan_003', 1, "parseInformation OTCPIP:". $ex->getMessage(), $planID, 'file', $process->entity_id );
                return false;
            }
        }
        
        //Delete data cleaned file?
        return $lines;
    }

    /**
     * [calculate_group_number Calculate group number for a member]
     * @param  Memberstmp $member [Temporary member]
     * @param  [String]     $planID [name of plan]
     * @return [Array]             [member with group number]
     */
    public function calculate_group_number(Memberstmp $member,$planID)
    {

        if( substr( $member->line_of_business, 0, 4) == 'CARE' ){
            $member->group_number = 'CARE_'. trim( $member->benefit_plan );
        }else if( ( ( substr($member->line_of_business, 4, 1 ) == 'C' ) AND ( substr($member->line_of_business, 0, 2) == 'RG' ) ) OR ( substr($member->line_of_business, 0, 3) == 'CHA' ) ){
            $member->group_number = 'CHA';
        }else if( substr( $member->line_of_business,0, 5 ) == 'MD DA' ){
            $member->group_number = 'MCH';
        }else if( ( substr($member->line_of_business,4,1) == 'S' AND substr($member->line_of_business,0,2) == 'RG' ) OR substr($member->line_of_business,0,4) == 'MD-S' OR substr($member->line_of_business,0,3) == 'SH' ){
            $member->group_number = 'SH';
        }else if( substr($member->line_of_business, 0,2) == 'HK' ){
            $member->group_number = 'HK';
        }else if( substr($member->line_of_business,0,3) == 'MD-' OR substr($member->line_of_business,0,3) == 'XMD' OR substr($member->line_of_business,0,2) == 'BR' OR ( substr($member->line_of_business,4,1) == 'B' AND substr($member->line_of_business,0,2) == 'RG' ) ){
            $member->group_number = 'BH';
        }else{
            $member->group_number = 'BH';
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
                ->equalTo('group_number', $member->group_number);
        
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
     * [bValid Validating fields that are not null]
     * @param  Memberstmp $member [Member temporary]
     * @return [Boolean]             [Return true if valid fields and false if I find at least some field must not be null ]
     */
    public function bValid( Memberstmp $member ){
        $notNullFields = [ 
                            'dob',
                            'enroll_date',
                            'city',
                            'state_code',
                            'zipcode',
                            'address_1',
                            'member_id', 
                            'benefit_amount', 
                            'period_factor', 
                            'household', 
                            'form_id' 
                        ];
        $bValid = true;
        foreach ($notNullFields as $field) {
            if( empty($member->$field) ){
                $bValid = false;
            }
        }
        
        return $bValid;
    }

    /**
     * [read_format_5010 read file with format 5010 and extract data]
     * @param  [Array] $config   [Array from configuration]
     * @param  [String] $filename [name of file to read]
     * @param  [String] $planID   [name of plan]
     * @param  [Array] $process  [Status of process]
     * @return [Integer]           [Number of lines was read]
     */
    public function read_format_5010( $config, $filename, $planID, $process )
    {
        try 
        {
            // =========================================================================================================================================================
            $datah=new Data();
            $fileInformation = file_get_contents( $filename );
            // Explode delimiters file to get records
            $data  = explode( 'INS' , $fileInformation );
            $lines = 0;
            $discarded_lines = 0;
            // get Records
            foreach ($data as $key => $record) {
                $fields = explode( '~', $record );
                // Variables
                $vzip   = true;            
                $member = new Memberstmp();
                    foreach ($fields as $key => $field) 
                    {
                        // Get memberID
                        if( substr( trim( $field ) , 0, 7) == 'REF*0F*' )
                        {
                            $member->member_id = trim( substr( trim( $field ) , 7) );
                        }
                        // Name Complete
                        if( substr( trim( $field ) , 0, 9) == 'NM1*IL*1*' )
                        {
                            $name = explode( '*' , substr( trim( $field ) , 9) );
                            if( !isset( $name[ 0 ] ) )
                                $name[ 0 ] = '';
                            if( !isset( $name[ 1 ] ) )
                                $name[ 1 ] = '';

                            $member->last_name = trim( $name[ 0 ] );
                            $member->first_name = trim( $name[ 1 ] );
                            if( sizeof( $name ) == 3 )
                            {
                                $member->middle_name_initial = trim( $name[ 2 ] );
                            }
                            else
                            {
                                $member->middle_name_initial = '';
                            }
                        }
                        // Dob, Gender
                        if( substr( trim( $field ), 0, 7) == 'DMG*D8*' )
                        {
                            $formatDate = new DateTime( substr( trim( $field ) , 7, 8) , new DateTimeZone('America/New_York'));
                            $year_s  = $formatDate->format("Y");
                            $month_s = $formatDate->format("m");
                            $day_s   = $formatDate->format("d");
                            $member->dob    = sprintf("%04d-%02d-%02d", $year_s, $month_s, $day_s );
                            $member->gender = substr( trim( $field ), 16, 1 );
                        }
                        // Enrolldate
                        if( substr( trim( $field ) , 0, 11) == 'DTP*348*D8*' )
                        {
                            $formatDate = new DateTime( substr( trim( $field ) , 11) , new DateTimeZone('America/New_York'));
                            $year_s  = $formatDate->format("Y");
                            $month_s = $formatDate->format("m");
                            $day_s   = $formatDate->format("d");
                            $member->enroll_date = sprintf("%04d-%02d-%02d", $year_s, $month_s, $day_s );
                        }
                        // Disenrolldate
                        if( substr( trim( $field ) , 0, 11) == 'DTP*349*D8*' )
                        {
                            $formatDate = new DateTime( substr( trim( $field ) , 11) , new DateTimeZone('America/New_York'));
                            $year_s  = $formatDate->format("Y");
                            $month_s = $formatDate->format("m");
                            $day_s   = $formatDate->format("d");
                            $member->disenroll_date = sprintf("%04d-%02d-%02d", $year_s, $month_s, $day_s );
                        }
                        // Address_1, Address_2
                        if( substr( trim( $field ) , 0, 3) == 'N3*' )
                        {
                            $addressTmp = explode( "*" , substr( trim( $field ) , 3 ) );
                            
                            if( strlen( $member->address_1 ) == 0 )
                            {
                                if( sizeof( $addressTmp ) == 1 )
                                {
                                   $member->address_1 = trim( $addressTmp[ 0 ] );
                                }
                                else
                                {
                                    $member->address_1 = trim( $addressTmp[ 0 ] )." ". trim( $addressTmp[ 1 ] );
                                }
                            }
                            else
                            {
                                if( sizeof( $addressTmp ) == 1 )
                                {
                                   $member->address_2 = trim( $addressTmp[ 0 ] );
                                }
                                else
                                {
                                    $member->address_2 = trim( $addressTmp[ 0 ] )." ". trim( $addressTmp[ 1 ] );
                                }
                            }
                        }
                        // City, State, ZipCode
                        if( substr( trim( $field ) , 0, 3) == 'N4*' )
                        {
                            if( $vzip )
                            {
                                $items = explode( '*' , substr( trim( $field ) , 3) );
                                $member->city = trim( $items[ 0 ] );
                                $member->state_code = trim( $items[ 1 ] );
                            $member->zipcode = substr( trim( $items[ 2 ] ), 0, 5 );
                                $vzip = false;
                            }
                        }
                        // Phone number
                        if( substr( trim( $field ) , 0, 11) == 'PER*IP**HP*' )
                        {
                            $member->phone_number = trim( substr( trim( $field ) , 11,10) );
                        }
                        
                        // Benefit plan
                        if( substr( trim( $field ) , 0, 8) == 'HD*030**' )
                        {
                            $explode = explode( '*', substr( trim( $field ) , 8) );
                            $member->benefit_plan = trim( substr( $explode[1], 0, 3 ) );
                        }
                    }//foreach fields
            // =========================================================================================================================================================
                    $member->line_of_business = 'CARE';
                    $member->member_id = preg_replace("/\s+/", '', $member->member_id);
                    $member->eligibility_process_id=$process->entity_id;
                    
                    if(empty($member->disenroll_date) or $member->disenroll_date=='0000-00-00' or $member->disenroll_date=='00000000')
                    {
                        $member->disenroll_date='2078-12-31';
                    }
                    $now=new DateTime('now', new DateTimeZone('America/New_York'));
                    $member->member_last_update=$now->format('Y-m-d');
                    $member=$this->calculate_benefit_plan($member,$planID);
                    
                    $member=$this->calculate_group_number($member,$planID);
                    $member=$this->calculate_benefit($member,$planID);
                    $member=$this->calculate_household($member);
                    $member=$this->calculate_status($member);
                    
                    $member->plan_code=$planID;
                    if( $this->bValid( $member ) )
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
                // if( $lines >= 10 )
                // {
                //     break;
                // }
            }//foreach Records

            return $lines;

        } 
        catch (\Exception $ex) 
        {
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('parseInf_overplan_001', 1, "parseInformation OTCPIP:". $ex->getMessage(), $planID, 'format5010', $process->entity_id );
            return false;
        }
    }


    /**
     * [read_format_plain Read data from plain format]
     * @param  [Array] $config   [Array of configuration]
     * @param  [String] $filename [Name of file]
     * @param  [String] $planID   [Name of plan]
     * @param  [Array] $process  [Array of process]
     * @return [Integer]           [Number of lines was read]
     */
    public function read_format_plain( $config, $filename, $planID, $process )
    {
        try 
        {
            
            $datah=new Data();
            $orighandle=  fopen($filename, 'r');
            //Parse
            $lines=0;
            $discarded_lines=0;
            while(!feof($orighandle))
            {
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
                
                $member=$this->calculate_group_number($member,$planID);
                $member=$this->calculate_benefit($member,$planID);
                $member=$this->calculate_household($member);
                $member=$this->calculate_status($member);
                
                $member->plan_code=$planID;
                if( $this->bValid( $member ) )
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
               // if($lines >= 10)
               // {
               //     break;
               // }
            }
            echo "\n";
            fclose($orighandle);

            return $lines;

        } 
        catch (\Exception $ex) 
        {
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('parseInf_overplan_002', 1, "parseInformation OTCPIP:". $ex->getMessage(), $planID, 'plainText', $process->entity_id );
            return false;
        }
    }

}