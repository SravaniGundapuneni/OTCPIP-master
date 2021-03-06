<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Processor\PHP;
use Otcpip\Helper\Data;

use DateTime;
use DateTimeZone;

use Otcpip\Model\File;
use Otcpip\Model\Members;
use Otcpip\Model\Memberstmp;

use Zend\Db\Sql;

use ZipArchive;

use Otcpip\Processor\Processor;

use Otcpip\Helper\Error as ErrorHelper;
/**
 * Description of PCP
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class PHP extends Processor{
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
            return 0;
        }
        $origfile=$files->current();
        //Open "original" file
        $filename=$config['servers'][$planID]['eligibility']['connection']['in']['file']['path_out'].$origfile->filename;
        if(!file_exists($filename))
        {
            /**
             * @Todo: Handle error. Update file status to failed clean data.
             */
            
            return 0;
        }
        // Clean Data Unzip File
        //==========================================================
        $extracTo = $config['servers'][$planID]['eligibility']['connection']['in']['file']['path_out'];
        $extension = end( explode( '.', $origfile->filename ) );
        $unzipName = basename( $origfile->filename, ".".$extension );
        $unzipFile = $extracTo.$unzipName;
        try 
        {
        
            $zip = new ZipArchive();
            // Rename file insde the zip
            if ($zip->open( $filename ) === TRUE) 
            {
                $zip->renameIndex( 0, $unzipName );
                $zip->close();
            } 
            else 
            {
              /**
               * @Todo: handle error.
               */
            }

            // Extract file
            if ($zip->open( $filename ) === TRUE) 
            {
                $zip->extractTo( $extracTo );
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
            echo "An error was ocurred when unzip this file: $filename". $ex->getMessage()."\n";
            $errorhelper=new ErrorHelper();
            $errorhelper->email_error('cleandata_overplan_001', 1, "cleandata OTCPIP:". $ex->getMessage(), $planID, 'unzipFile', $process->entity_id );
            return false;
        }

        //==========================================================
        try 
        {
            
            $orighandle=  fopen($unzipFile, 'r');
            $now=new DateTime('now', new DateTimeZone('America/New_York'));
            $year=$now->format("Y");
            $month=$now->format("m");
            $day=$now->format("d");
            
            $fileout_template=$config['servers'][$planID]['eligibility']['file']['name'];
            $fileout_name=preg_replace(array('/Y{4}/','/M{2}(?!A)/','/D{2}/'), array($year,$month,$day), $fileout_template);
            
            $filename_dest=$config['servers'][$planID]['eligibility']['file']['path_out'].$fileout_name;
            $desthandle=fopen($filename_dest, 'w');
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
     * @param Members $member
     * @return Members
     */
    public function calculate_benefit(Memberstmp $member,$planID)
    {
        $now=new DateTime('now', new DateTimeZone('America/New_York'));
        
        $conditions=new Sql\Where();
        $conditions->greaterThan('valid_to',$now->format("Y-m-d"))
               ->lessThanOrEqualTo('valid_from',$now->format("Y-m-d"))
               ->equalTo('group_number', $member->group_number)
               ->equalTo('benefit_plan', $member->benefit_plan);
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
