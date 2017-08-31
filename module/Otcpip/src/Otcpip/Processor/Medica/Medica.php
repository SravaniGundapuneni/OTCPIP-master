<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Processor\Medica;

use Otcpip\Helper\Data;

use DateTime;
use DateTimeZone;

use Otcpip\Model\Members;
use Otcpip\Model\Memberstmp;

use Zend\Db\Sql;
use Otcpip\Processor\Processor;

use Otcpip\Helper\Error as ErrorHelper;

/**
 * Description of Medica
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Medica extends Processor {
    
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
        
        // =========================================================================================================================================================
        
        try 
        {

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
                $ref_1l = true;         
                $member = new Memberstmp();
                    foreach ($fields as $key => $field) 
                    {
                        // Get memberID
                        if( substr( trim( $field ) , 0, 7) == 'REF*23*' )
                        {
                            $member->member_id = substr( trim( $field ) , 7);
                        }
                        // Name Complete
                        if( substr( trim( $field ) , 0, 9) == 'NM1*IL*1*' )
                        {
                            $name = explode( '*' , substr( trim( $field ) , 9) );
                            $member->last_name = trim( $name[ 0 ] );
                            
                            if( isset( $name[ 1 ] ) )
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
                            $member->phone_number = substr( trim( $field ) , 11, 10);
                        }
                        
                        // Policy number or BenefitPlan
                        if( substr( trim( $field ) , 0, 7) == 'REF*1L*' )
                        {
                            if( $ref_1l )
                            {
                                $member->policy_number = substr( trim( $field ) , 7);
                                $ref_1l = false;
                            }
                            else
                            {
                                $member->benefit_plan = substr( trim( $field ) , 7);
                            }
                        }

                        // Benefit id
                        if( substr( trim( $field ) , 0, 7) == 'REF*0F*' )
                        {
                            $member->benefit_id = substr( trim( $field ) , 7);
                        }
                        
                        // Lang Code
                        if( substr( trim( $field ) , 0, 7) == 'LUI*LD*' )
                        {
                            $member->lang_code = substr( trim( $field ) , 7,2);
                        }

                        // Martial Status
                        if( substr( trim( $field ) , 0, 7) == 'DMG*D8*' )
                        {
                            $explode = explode('*', substr( trim( $field ) , 7) );
                            if( isset( $explode[ 2 ] ) )
                                $member->marital_status = $explode[ 2 ];
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
                ->equalTo('group_number', $member->benefit_plan);
        
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
