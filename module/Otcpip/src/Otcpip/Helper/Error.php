<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Helper;
use Otcpip\Model\Emailerrorlog;
use Otcpip\Helper\Data;
use Zend\Mail;

/**
 * Description of Error
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class Error {
    
    /**
     * 
     * @param type $code
     * @param type $priority
     * @param type $message
     * @param type $user_id
     * @return int
     */
    public function email_error($code,$priority,$message,$plan_id=null, $entity, $entity_rel_id=null)
    {


        $data=new Data();
          $emailerrorlog=$data->getEmailerrorlogTable($plan_id)
                ->getEmailerrorlogByConditions(array(
                    'code'=>$code,
                    'priority'=>$priority,
                ));
        if(count($emailerrorlog)>0)
        {
            /**
             * @todo: verify this.
             */
            $emtos=end($emailerrorlog);
            $emtos->consecutive++;
             $data->getEmailerrorlogTable($plan_id)
                    ->saveEmailerrorlog($emtos);
        }
        else
        {
            // if not receiving user_id, asociate user_id = 1
            if( !(isset( $user_id ) > 0) )
                $user_id = 1;
            
            $emailerrorlog=new Emailerrorlog();
            $emailerrorlog->code=$code;
            $emailerrorlog->priority=$priority;
            $emailerrorlog->message=$message;
            $emailerrorlog->entity=$entity;
            $emailerrorlog->entity_rel_id=$entity_rel_id;
            $emailerrorlog->consecutive=1;
            
            $data->getEmailerrorlogTable($plan_id)
                    ->saveEmailerrorlog($emailerrorlog);
        }
        
        if($priority==1)
        {
            try
            {
                $mail=new Mail\Message();
                $mail->setBody($message);
                
                $config=$data->getConfiguration('config');
                $mail->setFrom($config['servers']['email_error']['from']['email'], $config['servers']['email_error']['from']['name']);
                $mail->addTo($config['servers']['email_error']['to']);
                $mail->setSubject($config['servers']['email_error']['subject']);
                
                $transport=new Mail\Transport\Sendmail();
                $transport->send($mail);
            }
            catch(\Exception $ex)
            {
                return $ex->getMessage();
            }
        }
        return 1;
    }
} 
