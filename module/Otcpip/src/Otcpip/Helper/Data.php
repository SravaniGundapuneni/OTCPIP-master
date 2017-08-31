<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Helper;

use Zend\ServiceManager\ServiceManager;
use Otcpip\Service\ServiceManagerConfig;

use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;

use Otcpip\Model\Eligibilityprocess;
use Otcpip\Model\EligibilityprocessTable;

use Otcpip\Model\File;
use Otcpip\Model\FileTable;

use Otcpip\Model\Memberstmp;
use Otcpip\Model\MemberstmpTable;
use \Otcpip\Model\CleansedFileTable;

use Otcpip\Model\Benefit;
use Otcpip\Model\BenefitTable;

use Otcpip\Model\Members;
use Otcpip\Model\MembersTable;

use Otcpip\Model\MembersCollection;

use Otcpip\Model\Elprocessmemberrel;
use Otcpip\Model\ElprocessmemberrelTable;
use Otcpip\Model\ElprocessmemberrelCollection;


use Otcpip\Model\Emailerrorlog;
use Otcpip\Model\EmailerrorlogTable;

use DateTime;
use DateTimeZone;


/**
 * Description of Data
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Data 
{
    protected $serviceManager=null;
    protected $eligibilityprocessTable;
    protected $fileTable;
    protected $memberstmpTable;
    protected $benefitTable;
    protected $membersTable;
    protected $membersCollection;
    protected $elprocessmemberrelTable;
    protected $elprocessmemberrelCollection;
    protected $emailerrorlogTable;
    protected $cleansedFileTable;


    /**
     * 
     */
    public function __construct()
    {
        $config=$this->getConfiguration('config');
        if(is_null($config))
        {
            $this->setConfiguration('config');
            $config=$this->getConfiguration('config');
        }
    }
    
    /**
     * 
     * @param type $index
     * @return null|array
     */
    public function getConfiguration($index)
    {
        $serviceManager=$this->serviceManager;
        if(!is_null($serviceManager))
        {
            $tconfig = $serviceManager->get($index);
            if( $this->verify_encrypted($tconfig) )
            {
                $this->treat_secret($tconfig);
            }
            return $tconfig;
        }
        return null;
    }
    
    /**
     * 
     * @param type $index
     * @param array $configuration
     */
    public function setConfiguration($index,array $configuration=array())
    {
        $global=require 'config/autoload/global.php';
        $final=array();
        foreach(glob('config/autoload/*local.php')as $filename)
        {
            
            $local=require $filename;

            $final=array_merge_recursive($global,$local,$configuration,$final);
        }
        $serviceManager=new ServiceManager(new ServiceManagerConfig());
        $serviceManager->setService($index, $final);
        $this->serviceManager=$serviceManager;
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getEligibilityprocessTable($planID) {
        if(!$this->eligibilityprocessTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new Eligibilityprocess());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('eligibility_process', $dbAdapter, null, $resultSetPrototype);
            $this->eligibilityprocessTable=new EligibilityprocessTable($tableGateway);
        }
        return $this->eligibilityprocessTable;
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getFileTable($planID)
    {
        if(!$this->fileTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new File());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('file', $dbAdapter, null, $resultSetPrototype);
            $this->fileTable=new FileTable($tableGateway);
        }
        return $this->fileTable;
    }
    
    //++###
    public function getCleansedFileTable($planID)
    {
        if(!$this->cleansedFileTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new File());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('cleansedFileTable', $dbAdapter, null, $resultSetPrototype);
            $this->cleansedFileTable=new CleansedFileTable($tableGateway);
        }
        return $this->cleansedFileTable;
    }
    
    /**
     * 
     * @param type $planID
     * @param type $process
     * @param type $status
     */
    public function update_process_status($planID,$process,$status)
    {
        $process->status=$status;
        $this->getEligibilityprocessTable($planID)
                ->saveEligibilityprocess($process);
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getMemberstmpTable($planID) {
        if(!$this->memberstmpTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new Memberstmp());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('members_tmp', $dbAdapter, null, $resultSetPrototype);
            $this->memberstmpTable=new MemberstmpTable($tableGateway);
        }
        return $this->memberstmpTable;
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getBenfitTable($planID)
    {
        if(!$this->benefitTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new Benefit());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('benefit', $dbAdapter, null, $resultSetPrototype);
            $this->benefitTable=new BenefitTable($tableGateway);
        }
        return $this->benefitTable;
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getMembersTable($planID) {
        if(!$this->membersTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new Members());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('members', $dbAdapter, null, $resultSetPrototype);
            $this->membersTable=new MembersTable($tableGateway);
        }
        return $this->membersTable;
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getMembersCollection($planID)
    {
        if(!$this->membersCollection)
        {
            $config=$this->getConfiguration('config');
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $this->membersCollection=new MembersCollection($dbAdapter);
        }
        return $this->membersCollection;
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getElprocessmemberrelTable($planID)
    {
        if(!$this->elprocessmemberrelTable)
        {
            $config=$this->getConfiguration('config');
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new Elprocessmemberrel());
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('elprocess_member_rel', $dbAdapter, null, $resultSetPrototype);
            $this->elprocessmemberrelTable=new ElprocessmemberrelTable($tableGateway);
        }
        return $this->elprocessmemberrelTable;
    }
    
    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getElprocessmemberrelCollection($planID)
    {
        if(!$this->elprocessmemberrelCollection)
        {
            $config=$this->getConfiguration('config');
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $this->elprocessmemberrelCollection=new ElprocessmemberrelCollection($dbAdapter);
        }
        return $this->elprocessmemberrelCollection;
    }

    /**
     * 
     * @param type $planID
     * @return type
     */
    public function getEmailerrorlogTable($planID)
    {

        if(!$this->emailerrorlogTable)
        {
            $resultSetPrototype=new ResultSet();
            $resultSetPrototype->setArrayObjectPrototype(new Emailerrorlog());
            $config=$this->getConfiguration('config');
            $dbAdapter=new Adapter($config['servers'][$planID]['db']);
            $tableGateway=new TableGateway('email_error_log', $dbAdapter, null, $resultSetPrototype);
            $this->emailerrorlogTable=new EmailerrorlogTable($tableGateway);
        }
        return $this->emailerrorlogTable;
    }
    
    /**
     * 
     * [nameToProcessFiles method for calculate the files what will be processed]
     * @param  [Array] $config [Configuration for files to download]
     * @return [Array]         [Array with parse for download files]
     */
    public function nameToProcessFiles($config)
    {

        $nameFiles = array();
        $daysToBack = array(
                'Monday' => array( 'Monday'=>'-7 day', 'Tuesday'=>'-6 day', 'Wednesday'=>'-5 day','Thursday'=>'-4 day','Friday'=>'-3 day','Saturday'=>'-2 day','Sunday'=>'-1 day' ),
                'Tuesday' => array( 'Monday'=>'-1 day', 'Tuesday'=>'-7 day', 'Wednesday'=>'-6 day','Thursday'=>'-5 day','Friday'=>'-4 day','Saturday'=>'-3 day','Sunday'=>'-2 day' ),
                'Wednesday' => array( 'Monday'=>'-2 day', 'Tuesday'=>'-1 day', 'Wednesday'=>'-7 day','Thursday'=>'-6 day','Friday'=>'-5 day','Saturday'=>'-4 day','Sunday'=>'-3 day' ),
                'Thursday' => array( 'Monday'=>'-3 day', 'Tuesday'=>'-2 day', 'Wednesday'=>'-1 day','Thursday'=>'-7 day','Friday'=>'-6 day','Saturday'=>'-5 day','Sunday'=>'-4 day' ),
                'Friday' => array( 'Monday'=>'-4 day', 'Tuesday'=>'-3 day', 'Wednesday'=>'-2 day','Thursday'=>'-1 day','Friday'=>'-7 day','Saturday'=>'-6 day','Sunday'=>'-5 day' ),
                'Saturday' => array( 'Monday'=>'-5 day', 'Tuesday'=>'-4 day', 'Wednesday'=>'-3 day','Thursday'=>'-2 day','Friday'=>'-1 day','Saturday'=>'-7 day','Sunday'=>'-6 day' ),
                'Sunday' => array( 'Monday'=>'-6 day', 'Tuesday'=>'-5 day', 'Wednesday'=>'-4 day','Thursday'=>'-3 day','Friday'=>'-2 day','Saturday'=>'-1 day','Sunday'=>'-7 day' ),
            );
        
        $now   = new DateTime('now', new DateTimeZone('America/New_York'));        
        $year4 = $now->format("Y");
        $year2 = $now->format("y");
        $month = $now->format("m");
        $day   = $now->format("d");
        $timeStampDay = strtotime ( $now->format('Ymd') );

        // Calculating file to get
        $files = $config['eligibility']['connection']['in']['files'];
        foreach($files as $configfile)
        {
            $timeStampBackDay = 0;
            // download_day exists in daysToBack means that we will backing down an old file to download
            if( array_key_exists( $configfile['parse']['download_day'], $daysToBack ) ){

                // if today is equal to download_day 
                // no need back days
                if( $now->format("l") == $configfile['parse']['download_day'] )
                {
                    $toMatch = $timeStampDay;
                }
                else //We need back days
                {
                    // Calculating days to back
                    $back =  $daysToBack[ $now->format("l") ][ $configfile['parse']['download_day'] ];

                    // Convert new date in strtotime
                    $toMatch = date( "mdY",strtotime ( $back , $timeStampDay ) );
                    $timeStampBackDay = strtotime ( $back , $timeStampDay );
                }

            }
            else
            {
                $toMatch = $timeStampDay;
            }

            // Apply parse to get file
            if(preg_match($configfile['parse']['from'], $toMatch, $matches))
            {
                // If i need get a previus file
                if( $timeStampBackDay > 0 )
                {
                    $setMonth = date( 'm', $timeStampBackDay );
                    $setDay   = date( 'd', $timeStampBackDay );
                    $setYear  = date( 'Y', $timeStampBackDay );
                    $setYear2 = date( 'y', $timeStampBackDay );
                }
                else
                {
                    $setMonth = date( 'm', $timeStampDay );
                    $setDay   = date( 'd', $timeStampDay );
                    $setYear  = date( 'Y', $timeStampDay );
                    $setYear2 = date( 'y', $timeStampDay );
                }

                if( sizeof( $matches ) == 3 )
                {
                    $fileInTmp  = preg_replace(array('/M{2}/','/D{2}/'), array($setMonth,$setDay), $configfile['format_in']);
                    $fileOutTmp = preg_replace(array('/M{2}/','/D{2}/'), array($setMonth,$setDay), $configfile['format_out']);
                }
                if( sizeof( $matches ) == 4 )
                {
                    $fileInTmp  = preg_replace(array('/M{2}(?!A)/','/D{2}/','/Y{4}/','/Y{2}/'), array($setMonth,$setDay,$setYear,$setYear2), $configfile['format_in']);
                    $fileOutTmp = preg_replace(array('/M{2}(?!A)/','/D{2}/','/Y{4}/','/Y{2}/'), array($setMonth,$setDay,$setYear,$setYear2), $configfile['format_out']);
                }
                
                $arrayTmp = array(
                        'format_in'=>$fileInTmp,
                        'path_out'=>$configfile['path_out'],
                        'format_out'=>$fileOutTmp,
                    );
            }

            array_push( $nameFiles, $arrayTmp );

        }

        return $nameFiles;

    }

    /*
     * [treat_secret decode data encript in configuration file]
     * @param  [Array] &$array [Array of configuration]
     * @return [Array]         [Array of configuration decrypt]
     */
    public function treat_secret(&$array)
    {
      $array_keys=array_keys($array);
      foreach($array_keys as $key)
      {
        
        if( $key==='password' OR $key === 'fingerprint' OR $key === 'passphrase' )
        {
            $array[$key]=base64_decode($array[$key]);
        }
        else if( is_array($array[$key]) )
        {
            $this->treat_secret($array[$key]);
        }
      }
      return $array;
    }

    /**
     * [verify_encrypted Verify if we can decript the configuration file]
     * @param  [Array] $array [Array of configuration]
     * @return [boolean]        [True if we can decrypt or false if we can't]
     */
    public function verify_encrypted($array)
    {
        $result = array_key_exists('config_encrypted', $array);
        if ($result && $array['config_encrypted'])
        {
            return $array['config_encrypted'];
        }
        
        foreach($array as $key => $arrayTmp)
        {
            if(is_array($arrayTmp))
            {
                $result = $this->verify_encrypted($arrayTmp);
            }
            
            if($result)
            {
                return $result;
            }
        }
        return $result;
    }
}
