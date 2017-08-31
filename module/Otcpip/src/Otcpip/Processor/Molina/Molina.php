<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Processor\Molina;

use Otcpip\Helper\Connector;
use Otcpip\Helper\Data;

use DateTime;
use DateTimeZone;

use Otcpip\Model\File;
use Otcpip\Model\Members;
use Otcpip\Model\Memberstmp;

use Otcpip\Model\Elprocessmemberrel;

use Zend\Db\Sql;
use Otcpip\Processor\Processor;

use gnupg;
use Otcpip\Helper\Error as ErrorHelper;

/**
 * Description of Aetnail
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Molina extends Processor {
    
    /**
     * Get the file downloaded in the directory
     * @param type $config
     * @return type
     */
    public function searchInDirectory($config, $type, $planID, $process){
        
        $now   = new DateTime('now', new DateTimeZone('America/New_York'));
        $year  = $now->format("Y");
        $month = $now->format("m");
        $day   = $now->format("d");

        if( $type != 'hardDrive' )
        {
            $host     = $config['eligibility']['connection']['in']['host'];
            $port     = $config['eligibility']['connection']['in']['port'];
            $user     = $config['eligibility']['connection']['in']['user'];
            $password = $config['eligibility']['connection']['in']['password'];
            $pathin   = $config['eligibility']['connection']['in']['file_directory'];
        }
        
        switch ( $type ) 
        {
            case 'sFTP':
            {

                $connection = ssh2_connect($host, $port);
                //authenticate
                ssh2_auth_password($connection, $user, $password);
                $sftp=ssh2_sftp($connection);
                $dirHandle = opendir( "ssh2.sftp://$sftp$pathin" );

                
            } break;
            case 'FTP':
            {
                $connection  = ftp_connect( $host, $port );
                $loginResult = ftp_login( $connection, $user, $password );
                $dirHandle   = ftp_nlist( $connection, $pathin );

            } break;
            case 'hardDrive':
            {
                // Is important put the correct plan
                $dirHandle = opendir( $config['eligibility']['connection']['in']['file']['path_out'] );
            } break;
        }


        try 
        {
            
            $fileout_template      = $config['eligibility']['connection']['in']['file']['format_in'];
            $fileout_name          =  preg_replace(array('/Y{4}/','/M{2}(?!A)/','/D{2}/'), array($year,$month,$day), $fileout_template);
            $file_search_extension = end( explode( '.', $fileout_name ) );
            $file_search           = substr( $fileout_name, 0, 16 );
            $file_search_date      = substr($fileout_name, 21, 8);
            

            // Searching in directory whe using opendir
            if( !is_array( $dirHandle ) )
            {
                
                while (false !== ($file = readdir($dirHandle))) 
                {
                    if ($file != '.' && $file != '..')
                    {
                        
                        $dir_file_extension = end( explode( '.', $file ) );
                        $dir_file           = substr( $file, 0, 16 );
                        $dir_file_date      = substr( $file, 21, 8 );
                        if( $dir_file_extension == $file_search_extension )
                        {
                            if( ( $dir_file == $file_search ) && ( $dir_file_date == $file_search_date ) )
                            {
                                $config['eligibility']['connection']['in']['file']['format_in'] = $file;
                                $config['eligibility']['connection']['in']['file']['format_out'] = $file;
                                return $config;
                            }

                        }
                    }
                }

            }
            else //Search in directory when using ftp_ntlist
            {
                foreach ($dirHandle as $key => $file) {
                    if ($file != '.' && $file != '..')
                    {
                        
                        $dir_file_extension = end( explode( '.', $file ) );
                        $dir_file           = substr( $file, 0, 16 );
                        $dir_file_date      = substr( $file, 21, 8 );
                        if( $dir_file_extension == $file_search_extension )
                        {
                            if( ( $dir_file == $file_search ) && ( $dir_file_date == $file_search_date ) )
                            {
                                $config['eligibility']['connection']['in']['file']['format_in'] = $file;
                                $config['eligibility']['connection']['in']['file']['format_out'] = $file;
                                return $config;
                            }

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
        
        $config = $this->searchInDirectory( $config, 'FTP', $planID, $process );

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
            return 0;
        }

        // Alter config file to search original file
        // This is done because the file name in the database is incorrect
        // only for this plan
        $config = $this->searchInDirectory( $config['servers'][$planID], 'hardDrive', $planID, $process );

        $origfile=$files->current();
        
        
        try {
            
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
            // Decrypt data
            //==========================================================
            $pathOut   = $config['eligibility']['connection']['in']['file']['path_out'];
            $extension = end( explode( '.', $filename ) );
            $nameFile  = basename( $filename, ".".$extension );
            $fileOut   = $pathOut.$nameFile;
            
            $gpg = new gnupg();
            $gpg -> seterrormode(gnupg::ERROR_EXCEPTION); //Set Exception in case of error

              
                $gpg -> adddecryptkey( $config['eligibility']['connection']['in']['file']['fingerprint'],$config['eligibility']['connection']['in']['file']['passphrase'] );

                $encrypted_text = file_get_contents( $filename );
                $plain          = $gpg -> decrypt( $encrypted_text );
                $bytes          = file_put_contents( $fileOut, $plain );
            //==========================================================
              
        } catch (\Exception $ex) {
            echo "An error was ocurred when decrypt this file: $filename". $ex->getMessage()."\n";
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('cleandata_overplan_001', 1, "cleandata OTCPIP:". $ex->getMessage(), $planID, 'decryptingFile', $process->entity_id );
            return false;   
        }
        
        try 
        {

            $orighandle=  fopen($fileOut, 'r');
            $now=new DateTime('now', new DateTimeZone('America/New_York'));
            $year=$now->format("Y");
            $month=$now->format("m");
            $day=$now->format("d");
            
            $fileout_template=$config['eligibility']['file']['name'];
            $fileout_name=  preg_replace(array('/Y{4}/','/M{2}/','/D{2}/'), array($year,$month,$day), $fileout_template);
            
            $filename_dest=$config['eligibility']['file']['path_out'].$fileout_name;
            $desthandle=  fopen($filename_dest, 'w');
            // Clean Data
            // Nothing to do in clean data
            // Save in cleaned file
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
            $errorhelper->email_error('cleandata_overplan_002', 1, "cleandata OTCPIP:". $ex->getMessage(), $planID, 'file', $process->entity_id );
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
                            $member->member_id = substr( trim( $field ) , 7);
                        }

                        // Name Complete
                        if( substr( trim( $field ) , 0, 9) == 'NM1*IL*1*' )
                        {
                            $name = explode( '*' , substr( trim( $field ) , 9) );
                            $member->last_name = trim( $name[ 0 ] );
                            if( isset( $name[1] ) )
                                $member->first_name = trim( $name[ 1 ] );
                            if( sizeof( $name ) == 3 )
                            {
                                if( isset( $name[ 2 ] ) )
                                    $member->middle_name_initial = trim( $name[ 2 ] );
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
                                else if( isset( $addressTmp[ 1 ] ) )
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
                                else if( isset( $addressTmp[ 1 ] ) )
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
                                if( isset( $items[ 1 ] ) )
                                    $member->state_code = trim( $items[ 1 ] );
                                if( isset( $items[ 2 ] ) )
                                $member->zipcode = substr( trim( $items[ 2 ] ), 0, 5 );
                                $vzip = false;
                            }
                        }

                        // Phone number
                        if( substr( trim( $field ) , 0, 11) == 'PER*IP**HP*' )
                        {
                            $member->phone_number = substr( trim( $field ) , 11);
                        }

                        // Policy number
                        if( substr( trim( $field ) , 0, 7) == 'REF*1L*' )
                        {
                            $member->policy_number = substr( trim( $field ) , 7);
                        }

                        // Heatlh plan id
                        if( substr( trim( $field ) , 0, 8) == 'HD*030**' )
                        {
                            $member->health_plan_id = substr( trim( $field ) , 12, 6);
                        }
                        
                        // Benefit plan
                        if( substr( trim( $field ) , 0, 7) == 'REF*23*' )
                        {
                            $member->benefit_plan = substr( trim( $field ) , 7);
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
                    if( $this->bValid($member) )
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
        } 
        catch (\Exception $ex) 
        {
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
     * [calculate_group_name Calculating group_name for member]
     * @param  Memberstmp $member [Object member tmp]
     * @param  [String]     $planID [plan Id]
     * @return Memberstmp             [Return object Memberstmp]
     */
    public function calculate_group_name(Memberstmp $member, $planID)
    {
        if( strlen( $member->benefit_plan ) == 9 )
        {
            $member->group_name = substr( $member->benefit_plan, 5 );
        }
        else
        {
            $member->group_name = substr( $member->health_plan_id, 0, 3 );
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

        $datah  = new Data();

        try 
        {
            
            $config = $datah->getConfiguration('config');
            $config = $this->searchInDirectory( $config['servers'][$planID], 'hardDrive', $planID, $process );

            $filename = $config['eligibility']['connection']['in']['file']['format_in'];
            $typeFile = substr( $filename, 17 , 3) ;    
            
            if( $typeFile == "M_F" ){

                //Insert Disenrolled records in elprocess_member_rel
                $disenrolled=$datah->getMembersCollection($planID)
                        ->apply_disenrolled(array(
                            'member_status'=>'Enabled',
                            'eligibility_process_id'=>$process->entity_id,
                        ),
                        $days_before);
                echo "$disenrolled disenrolled\n";
                
            }

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

        } 
        catch (\Exception $ex) 
        {
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('proccRec_overplan_001', 1, "processrecords OTCPIP:". $ex->getMessage(), $planID, 'elProcessMember', $process->entity_id );
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

        } 
        catch (\Exception $ex)
        {
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('proccRec_overplan_002', 1, "processrecords OTCPIP:". $ex->getMessage(), $planID, 'member', $process->entity_id );
            return false;
        }
    }

    /**
     * [bValid Verify that saving null in not null field]
     * @param  Memberstmp $member [description]
     * @return [type]             [description]
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

}