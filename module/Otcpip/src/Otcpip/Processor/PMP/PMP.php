<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Processor\PMP;

use Otcpip\Helper\Data;
use Otcpip\Helper\Connector;

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
class PMP extends Processor {
    
    /**
     * 
     * @param type $config
     * @return type
     */
    public function searchInDirectory($config, $type,$planID,$process){
        
        $now   = new DateTime('now', new DateTimeZone('America/New_York'));
        $year  = $now->format("Y");
        $month = $now->format("m");
        $day   = $now->format("d");
        
        switch ( $type ) 
        {
            case 'sFTP':
            {
                $host             = $config['eligibility']['connection']['in']['host'];
                $port             = $config['eligibility']['connection']['in']['port'];
                $user             = $config['eligibility']['connection']['in']['user'];
                $password         = $config['eligibility']['connection']['in']['password'];
                $pathin           = $config['eligibility']['connection']['in']['file_directory'];

                $connection = ssh2_connect($host, $port);
                //authenticate
                ssh2_auth_password($connection, $user, $password);
                $sftp=ssh2_sftp($connection);
                $dirHandle = opendir( "ssh2.sftp://$sftp$pathin" );

                
            } break;
            case 'hardDrive':
            {
                $dirHandle = opendir( $config['eligibility']['connection']['in']['file']['path_out'] );
            } break;
        }


        try 
        {
            
            $fileout_template      = $config['eligibility']['connection']['in']['file']['format_in'];
            $fileout_name          =  preg_replace(array('/Y{4}/','/M{2}(?!A)/','/D{2}/'), array($year,$month,$day), $fileout_template);
            $file_search_extension = end( explode( '.', $fileout_name ) );
            $file_search           = substr( $fileout_name, 0, 25 );

            // Searching in directory
            while (false !== ($file = readdir($dirHandle))) {
                if ($file != '.' && $file != '..') {
                    
                    $dir_file_extension = end( explode( '.', $file ) );
                    $dir_file = substr( $file, 0, 25 );
                    
                    if( $dir_file_extension == $file_search_extension )
                    {
                        if( $dir_file == $file_search )
                        {
                            $config['eligibility']['connection']['in']['file']['format_in'] = $file;
                            $config['eligibility']['connection']['in']['file']['format_out'] = $file;
                            return $config;
                        }

                    }
                }
            }
            return false;
        } 
        catch(\Exception $ex) 
        {
            echo "An error was ocurred when search: $fileout_template". $ex->getMessage()."\n";
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('searchfile_overplan_001', 1, "searchfile OTCPIP: ". $type ." ".$ex->getMessage(), $planID, 'searchFile', $process->entity_id );
            return false;
        }

    }

    /**
     * 
     * @param type $config
     * @return type
     */
    public function getFile($config, $planID, $process) {
        echo "Connecting...\n";
        
        $config = $this->searchInDirectory( $config, 'sFTP',$planID, $process );

        try 
        {
            
            if( $config )
            {
                $connector=new Connector();
                return $connector->getRemoteFile($config);
            }
            else
            {
                echo "The file was not found\n";
            }

        } 
        catch (\Exception $ex) 
        {
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

        // Alter config file to search original file
        // This is done because the file name in the database is incorrect
        // only for this plan
        $config = $this->searchInDirectory( $config['servers'][$planID], 'hardDrive', $planID, $process );

        $origfile=$files->current();

        try 
        {
            
            //Open "original" file
            $filename=$config['eligibility']['connection']['in']['file']['path_out'].$config['eligibility']['connection']['in']['file']['format_in'];
            if(!file_exists($filename))
            {
                /**
                 * @Todo: Handle error. Update file status to failed clean data.
                 */
                echo "File does not exist: $filename\n";
                return 0;
            }
            $orighandle=  fopen($filename, 'r');
            
            $now=new DateTime('now', new DateTimeZone('America/New_York'));
            $year=$now->format("Y");
            $month=$now->format("m");
            $day=$now->format("d");
            
            $fileout_template=$config['eligibility']['file']['name'];
            $fileout_name=  preg_replace(array('/Y{4}/','/M{2}(?!A)/','/D{2}/'), array($year,$month,$day), $fileout_template);
            
            $filename_dest=$config['eligibility']['file']['path_out'].$fileout_name;
            $desthandle=  fopen($filename_dest, 'w');
            //Clean Data
            //Nothing to do in clean data
            //Save in cleaned file
            $writtenBytes=stream_copy_to_stream($orighandle, $desthandle);
            fclose($orighandle);
            fclose($desthandle);
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

        } 
        catch (\Exception $ex) 
        {
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('cleandata_overplan_001', 1, "cleandata OTCPIP:". $ex->getMessage(), $planID, 'file', $process->entity_id );
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
        $origfile = $files->current();
        //Open "original" file
        $filename = $config['servers'][$planID]['eligibility']['file']['path_out'].$origfile->filename;
        if(!file_exists($filename))
        {
            /**
             * @Todo: Handle error. Update file status to failed clean data.
             */
            
            return 0;
        }
        
        try 
        {
                                                    
            // =========================================================================================================================================================
            $fileInformation = file( $filename );
            
            // Explode delimiters file to get records
            $data  = explode( '~INS' , $fileInformation[ 0 ] );
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
                        if( trim( substr( $field , 0, 7) ) == 'REF*0F*' )
                        {
                            $member->member_id = trim( substr( $field , 7) );
                        }

                        // Name Complete
                        if( trim( substr( $field , 0, 9) ) == 'NM1*IL*1*' )
                        {
                            $name = explode( '*' , trim( substr( $field , 9) ) );
                            $member->last_name = $name[ 0 ];
                            if( isset( $name[ 1 ] ) )
                                $member->first_name = $name[ 1 ];
                            if( sizeof( $name ) == 3 )
                            {
                                if( isset( $name[ 2 ] ) )
                                    $member->middle_name_initial = $name[ 2 ];
                            }
                            
                        }

                        // Dob, Gender
                        if( trim( substr( $field , 0, 7) ) == 'DMG*D8*' )
                        {
                            $formatDate = new DateTime( substr( $field , 7, 8) , new DateTimeZone('America/New_York'));
                            $year_s  = $formatDate->format("Y");
                            $month_s = $formatDate->format("m");
                            $day_s   = $formatDate->format("d");
                            $member->dob    = sprintf("%04d-%02d-%02d", $year_s, $month_s, $day_s );
                            $member->gender = substr( $field, 16, 1 );
                        }

                        // Enrolldate
                        if( trim( substr( $field , 0, 11) ) == 'DTP*474*D8*' )
                        {
                            $formatDate = new DateTime( substr( $field , 11) , new DateTimeZone('America/New_York'));
                            $year_s  = $formatDate->format("Y");
                            $month_s = $formatDate->format("m");
                            $day_s   = $formatDate->format("d");
                            $member->enroll_date = sprintf("%04d-%02d-%02d", $year_s, $month_s, $day_s );
                        }

                        // Address_1, Address_2
                        if( trim( substr( $field , 0, 3) ) == 'N3*' )
                        {
                            $addressTmp = explode( "*" , substr( $field , 3 ) );
                            
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
                                    $member->address_2 = trim( $addressTmp[ 0 ] ) ." ". trim( $addressTmp[ 1 ] );
                                }
                            }
                        }

                        // City, State, ZipCode
                        if( trim( substr( $field , 0, 3) ) == 'N4*' )
                        {
                            if( $vzip )
                            {
                                $items = explode( '*' , substr( $field , 3) );
                                $member->city = $items[ 0 ];
                                if( isset( $items[ 1 ] ) )
                                    $member->state_code = $items[ 1 ];
                                if( isset( $items[ 2 ] ) )
                                $member->zipcode = substr( $items[ 2 ], 0, 5 );
                                $vzip = false;
                            }
                        }

                        // Phone number
                        if( trim( substr( $field , 0, 11) ) == 'PER*IP**TE*' )
                        {
                            $member->phone_number = trim( substr( $field , 11, 10) );
                        }

                        // Benefit plan
                        if( trim( substr( $field , 0, 7) ) == 'REF*1L*' )
                        {
                            $member->benefit_plan = trim( substr( $field , 7) );
                        }

                        // Plan Code
                        if( trim( substr( $field , 0, 12) ) == 'HD*021**HMO*' )
                        {
                            $member->plan_description = trim( substr( $field , 13, 5) );
                        }

                    }//foreach fields

            // =========================================================================================================================================================

                    $member->member_id = preg_replace("/\s+/", '', $member->member_id);
                    $member->eligibility_process_id=$process->entity_id;
                    
                    if(empty($member->disenroll_date) or $member->disenroll_date=='0000-00-00' or $member->disenroll_date=='00000000')
                    {
                        $member->disenroll_date='2078-12-31';
                    }

                    $now=new DateTime('now', new DateTimeZone('America/New_York'));
                    $member->member_last_update=$now->format('Y-m-d');
                    $member=$this->calculate_benefit_plan($member,$planID);
                    
                    $member=$this->calculate_group_name($member,$planID);
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
                // if( $lines >= 10 )
                // {
                //     break;
                // }

            }//foreach Records

        } 
        catch (\Exception $ex) 
        {
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('parseInf_overplan_001', 1, "parseInformation OTCPIP:". $ex->getMessage(), $planID, 'format5010', $process->entity_id );
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

        } catch (Exception $e) {
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('parseInf_overplan_002', 1, "parseInformation OTCPIP:". $ex->getMessage(), $planID, 'file', $process->entity_id );
            return false;
        }

    }

    public function calculate_group_name(Memberstmp $member, $planID)
    {
        if( preg_match('/MMA-LTC/', $member->benefit_plan) )
        {
            $member->group_name = 'LTC';
        }
        else
        {
            $member->group_name = 'MMA';
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
}