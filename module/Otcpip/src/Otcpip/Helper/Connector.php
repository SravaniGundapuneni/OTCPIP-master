<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Helper;

use Zend\ServiceManager\ServiceManager;
use Otcpip\Service\ServiceManagerConfig;

use DateTime;
use DateTimeZone;

use GuzzleHttp\Client;

/**
 * Description of Connector
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Connector {
    protected $serviceManager=null;
    
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
            return $serviceManager->get($index);
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
     * @param type $host
     * @param type $port
     * @param type $username
     * @param type $password
     * @param type $pathin
     * @param type $filein
     * @param type $pathout
     * @param type $fileout
     * @return type
     */
    public function getsFTPRemoteFile($host,$port,$username,$password,$pathin,$filein,$pathout,$fileout) 
    {
        //Validate parameters
        /**
         * @Todo: validate all steps in this function, return 0=error and manage error log.
         */
        //connect
        $connection = ssh2_connect($host, $port);
        //authenticate
        ssh2_auth_password($connection, $username, $password);
        
        $sftp=ssh2_sftp($connection);
        //get file
        $stream=  fopen("ssh2.sftp://$sftp$pathin$filein", 'r');
        
        $local=fopen("$pathout$fileout",'w');
        
        $writtenBytes=  stream_copy_to_stream($stream, $local);
        fclose($stream);
        fclose($local);
        return $writtenBytes;
    }
    
    /**
     * 
     * @param type $config
     * @return type
     */
    public function getRemoteFile($config)
    {
        $now=new DateTime('now', new DateTimeZone('America/New_York'));
        $year4=$now->format("Y");
        $year2=$now->format("y");
        $month=$now->format("m");
        $day=$now->format("d");
        
        $host=$config['eligibility']['connection']['in']['host'];
        $port=$config['eligibility']['connection']['in']['port'];
        $user=$config['eligibility']['connection']['in']['user'];
        $password=$config['eligibility']['connection']['in']['password'];
        $pathin=$config['eligibility']['connection']['in']['file_directory'];
        $pathout=$config['eligibility']['connection']['in']['file']['path_out'];
        $connection_type=$config['eligibility']['connection']['in']['type'];
        
        $filein_template=$config['eligibility']['connection']['in']['file']['format_in'];
        $filein_name=  preg_replace(array('/Y{4}/','/Y{2}/','/M{2}(?!A)/','/D{2}/'), array($year4,$year2,$month,$day), $filein_template);

        $fileout_template=$config['eligibility']['connection']['in']['file']['format_out'];
        $fileout_name=  preg_replace(array('/Y{4}/','/Y{2}/','/M{2}(?!A)/','/D{2}/'), array($year4,$year2,$month,$day), $fileout_template);
        
        $bytes=0;
        switch($connection_type)
        {
            case 'sftp':
                $bytes=$this->getsFTPRemoteFile($host, 
                    $port, 
                    $user, 
                    $password, 
                    $pathin, 
                    $filein_name, 
                    $pathout,
                    $fileout_name);
            break;
            case 'https':
                $bytes=$this->getHTTPSRemoteFile($host,
                        $pathin,
                        $user,
                        $password,
                        $filein_name,
                        $pathout,
                        $fileout_name);
            break;
            case 'login':
                $post_url=$config['eligibility']['connection']['in']['post_url'];
                $query=$config['eligibility']['connection']['in']['query'];
                $bytes=$this->getLoginRemoteFile($host, $pathin, $user, $password, $post_url, $filein_name, $pathout, $fileout_name, $query);
            break;
            case 'ftp':
                $bytes=$this->getFTPRemoteFile($host,
                        $port,
                        $user,
                        $password,
                        $pathin,
                        $filein_name,
                        $pathout,
                        $fileout_name);
            break;
            default:
                $bytes=0;
            break;
        }
        return $bytes;
    }
    
    /**
     * 
     * @param type $host
     * @param type $pathin
     * @param type $user
     * @param type $password
     * @param type $filein_name
     * @param type $pathout
     * @param type $fileout_name
     * @return type
     */
    public function getHTTPSRemoteFile($host,$pathin,$user,$password,$filein_name,$pathout,$fileout_name)
    {
        $client=new Client([
            'base_url'=>$host.$pathin,
            'defaults'=>[
                'auth'=>[$user,$password],
            ],
        ]);
        try
        {
            $zipdata=$client->get($filein_name);
            $fp = fopen($pathout.$fileout_name, 'w');
            fwrite($fp, $zipdata->getBody());
            fclose($fp);
            return filesize($pathout.$fileout_name);
        } catch (\Exception $ex) {
            return "Exception: ".$ex->getMessage();
        }
    }
    
    /**
     * 
     * @param type $host
     * @param type $pathin
     * @param type $user
     * @param type $password
     * @param type $post_url
     * @param type $filein_name
     * @param type $pathout
     * @param type $fileout_name
     * @param type $query
     * @return type
     */
    public function getLoginRemoteFile($host,$pathin,$user,$password,$post_url,$filein_name,$pathout,$fileout_name,$query)
    {
        $client=new Client([
            'base_url'=>$host,
            'defaults'=>[
                'auth'=>[$user,$password],
            ],
        ]);
        try
        {
            $login=$client->post($post_url);
            $cookies=$login->getHeaderAsArray('Set-Cookie');
            $stream=$client->get($pathin.'/'.$filein_name, [
                'query'=>$query,
                'cookies'=>$cookies,
            ]);
            $fp = fopen($pathout.$fileout_name, 'w');
            fwrite($fp, $stream->getBody());
            fclose($fp);
            return filesize($pathout.$fileout_name);
        } catch (\Exception $ex) {
            return "Exception: ".$ex->getMessage();
        }
    }

    public function getFTPRemoteFile($host,$port,$user,$password,$pathin,$filein_name,$pathout,$fileout_name){
        try 
        {
            $handle      = fopen( $pathout.$fileout_name, 'w' );
            $conn        = ftp_connect( $host, $port );
            $loginResult = ftp_login( $conn, $user, $password );

            ftp_fget( $conn, $handle, $pathin.$filein_name, FTP_BINARY, 0);
            ftp_close( $conn );
            fclose( $handle );
            return filesize( $pathout.$fileout_name );
        } catch (\Exception $ex) {
            return "Exception: ".$ex->getMessage();   
        }
        
    }
}
