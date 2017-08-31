<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Model;
use Zend\Db\Adapter\Adapter;

use Otcpip\Helper\Data;
/**
 * Description of MembersCollection
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class MembersCollection {
    const NO_RECORD_FOUND=129;
    
    protected $db;
    
    public function __construct(Adapter $db) {
        $this->db=$db;
    }
    
    /**
     * 
     * @param type $conditions
     * @param type $days_before
     * @return type
     */
    public function apply_disenrolled($conditions,$days_before)
    {
        /**
         * @Todo: Validate conditions.
         */
        $sql="delete from elprocess_member_rel where eligibility_process_id=".$this->db->driver->formatParameterName('eligibility_process_id');
        $statement=$this->db->createStatement($sql,array(
            'eligibility_process_id'=>$conditions['eligibility_process_id'],
        ));
        $statement->execute();
        
        $sql="Insert into elprocess_member_rel (eligibility_process_id,member_entity_id,status)
            Select ".$this->db->driver->formatParameterName('eligibility_process_id')." as eligibility_process_id, members.entity_id,'Disenrolled' as status
from members
left join members_tmp on members_tmp.member_id=members.member_id
where isnull(members_tmp.entity_id)
and members.status=".$this->db->driver->formatParameterName('member_status');
        $statement=$this->db->createStatement($sql,$conditions);
        $result=$statement->execute();
        
        if($result->getAffectedRows())
        {
            $sql="update members
    inner join elprocess_member_rel on members.entity_id=elprocess_member_rel.member_entity_id
    set members.Status='Disenrolled',
    members.disenroll_date=date_sub(curdate(),interval $days_before day)
    where elprocess_member_rel.Status='Disenrolled'
    and members.status=".$this->db->driver->formatParameterName('member_status')."
    and elprocess_member_rel.eligibility_process_id=".$this->db->driver->formatParameterName('eligibility_process_id');
            $statement=$this->db->createStatement($sql,$conditions);
            $result=$statement->execute();
        }
        return $result->getAffectedRows();
    }
    
    /**
     * 
     * @param type $conditions
     * @return type
     */
    public function apply_updated($conditions)
    {
        /**
         * @Todo: Validate conditions.
         */
        //Inserte updated records in elprocess_member_rel
        $sql="Insert into elprocess_member_rel (eligibility_process_id,member_entity_id,status)
Select members_tmp.eligibility_process_id,members.entity_id,'Updated' as status
from members_tmp
inner join members on members_tmp.member_id=members.member_id
where eligibility_process_id=".$this->db->driver->formatParameterName('eligibility_process_id');
        $statement=$this->db->createStatement($sql,$conditions);
        $result=$statement->execute();
        if($result->getAffectedRows())
        {
            //Update updated
            $sql="update members
inner join members_tmp on members_tmp.member_id=members.member_id
set 
members.alternative_id=if(members_tmp.alternative_id!='' and not isnull(members_tmp.alternative_id),members_tmp.alternative_id,members.alternative_id),
members.dob=if(members_tmp.dob!='' and not isnull(members_tmp.dob),members_tmp.dob,members.dob),
members.gender=if(members_tmp.gender!='' and not isnull(members_tmp.gender),members_tmp.gender,members.gender),
members.enroll_date=if(members_tmp.enroll_date!='' and not isnull(members_tmp.enroll_date),members_tmp.enroll_date,members.enroll_date),
members.disenroll_date=if(members_tmp.disenroll_date!='' and not isnull(members_tmp.disenroll_date),members_tmp.disenroll_date,members.disenroll_date),
members.relationship=if(members_tmp.relationship!='' and not isnull(members_tmp.relationship),members_tmp.relationship,members.relationship),
members.address_1=if(members_tmp.address_1!='' and not isnull(members_tmp.address_1),members_tmp.address_1,members.address_1),
members.address_2=if(members_tmp.address_2!='' and not isnull(members_tmp.address_2),members_tmp.address_2,members.address_2),
members.city=if(members_tmp.city!='' and not isnull(members_tmp.city),members_tmp.city,members.city),
members.state_code=if(members_tmp.state_code!='' and not isnull(members_tmp.state_code),members_tmp.state_code,members.state_code),
members.first_name=if(members_tmp.first_name!='' and not isnull(members_tmp.first_name),members_tmp.first_name,members.first_name),
members.middle_name_initial=if(members_tmp.middle_name_initial!='' and not isnull(members_tmp.middle_name_initial),members_tmp.middle_name_initial,members.middle_name_initial),
members.last_name=if(members_tmp.last_name!='' and not isnull(members_tmp.last_name),members_tmp.last_name,members.last_name),
members.zipcode=if(members_tmp.zipcode!='' and not isnull(members_tmp.zipcode),members_tmp.zipcode,members.zipcode),
members.phone_number=if(members_tmp.phone_number!='' and not isnull(members_tmp.phone_number),members_tmp.phone_number,members.phone_number),
members.benefit_amount=if(members_tmp.benefit_amount!='' and not isnull(members_tmp.benefit_amount),members_tmp.benefit_amount,members.benefit_amount),
members.period_factor=if(members_tmp.period_factor!='' and not isnull(members_tmp.period_factor),members_tmp.period_factor,members.period_factor),
members.policy_number=if(members_tmp.policy_number!='' and not isnull(members_tmp.policy_number),members_tmp.policy_number,members.policy_number),
members.health_plan_id=if(members_tmp.health_plan_id!='' and not isnull(members_tmp.health_plan_id),members_tmp.health_plan_id,members.health_plan_id),
members.benefit_effective_date=if(members_tmp.benefit_effective_date!='' and not isnull(members_tmp.benefit_effective_date),members_tmp.benefit_effective_date,members.benefit_effective_date),
members.member_last_update=if(members_tmp.member_last_update!='' and not isnull(members_tmp.member_last_update),members_tmp.member_last_update,members.member_last_update),
members.benefit_id=if(members_tmp.benefit_id!='' and not isnull(members_tmp.benefit_id),members_tmp.benefit_id,members.benefit_id),
members.medicare_id=if(members_tmp.medicare_id!='' and not isnull(members_tmp.medicare_id),members_tmp.medicare_id,members.medicare_id),
members.line_of_business=if(members_tmp.line_of_business!='' and not isnull(members_tmp.line_of_business),members_tmp.line_of_business,members.line_of_business),
members.vendor_code=if(members_tmp.vendor_code!='' and not isnull(members_tmp.vendor_code),members_tmp.vendor_code,members.vendor_code),
members.family_id=if(members_tmp.family_id!='' and not isnull(members_tmp.family_id),members_tmp.family_id,members.family_id),
members.benefit_plan=if(members_tmp.benefit_plan!='' and not isnull(members_tmp.benefit_plan),members_tmp.benefit_plan,members.benefit_plan),
members.plan_description=if(members_tmp.plan_description!='' and not isnull(members_tmp.plan_description),members_tmp.plan_description,members.plan_description),
members.group_number=if(members_tmp.group_number!='' and not isnull(members_tmp.group_number),members_tmp.group_number,members.group_number),
members.group_name=if(members_tmp.group_name!='' and not isnull(members_tmp.group_name),members_tmp.group_name,members.group_name),
members.household=if(members_tmp.household!='' and not isnull(members_tmp.household),members_tmp.household,members.household),
members.lang_code=if(members_tmp.lang_code!='' and not isnull(members_tmp.lang_code),members_tmp.lang_code,members.lang_code),
members.benefit_package=if(members_tmp.benefit_package!='' and not isnull(members_tmp.benefit_package),members_tmp.benefit_package,members.benefit_package),
members.dea_number=if(members_tmp.dea_number!='' and not isnull(members_tmp.dea_number),members_tmp.dea_number,members.dea_number),
members.financial_number=if(members_tmp.financial_number!='' and not isnull(members_tmp.financial_number),members_tmp.financial_number,members.financial_number),
members.marital_status=if(members_tmp.marital_status!='' and not isnull(members_tmp.marital_status),members_tmp.marital_status,members.marital_status),
members.region_code=if(members_tmp.region_code!='' and not isnull(members_tmp.region_code),members_tmp.region_code,members.region_code),
members.form_id=if(members_tmp.form_id!='' and not isnull(members_tmp.form_id),members_tmp.form_id,members.form_id),
members.plan_code=if(members_tmp.plan_code!='' and not isnull(members_tmp.plan_code),members_tmp.plan_code,members.plan_code),
members.status=if(members_tmp.status!='' and not isnull(members_tmp.status),members_tmp.status,members.status)
where eligibility_process_id=".$this->db->driver->formatParameterName('eligibility_process_id');
            $statement=$this->db->createStatement($sql,$conditions);
            $result=$statement->execute();
        }
        return $result->getAffectedRows();
    }
    
    /**
     * 
     * @param type $conditions
     * @return type
     */
    public function apply_to_insert($conditions) {
        $sql="Select members_tmp.*
from members_tmp
left join members on members_tmp.member_id=members.member_id
where isnull(members.entity_id)
and eligibility_process_id=".$this->db->driver->formatParameterName('eligibility_process_id');
        $statement=$this->db->createStatement($sql,$conditions);
        $rowset=$statement->execute();
        return $rowset;
    }
    
    /**
     * 
     * @return type
     */
    public function apply_statuses() {
        $sql="update members
set status=if(curdate() between enroll_date and disenroll_date,'Enabled','Disenrolled')";
        $statement=$this->db->createStatement($sql);
        $result=$statement->execute();
        return $result->getAffectedRows();
    }

    /**
     * 
     * @return type
     */
    public function apply_household_logic($planID){
        $datah=new Data();
        $config         = $datah->getConfiguration('config');
        $applyHousehold = $config['servers'][$planID]['eligibility']['apply_household'];
        $householdtype  = $applyHousehold['householdtype'];

        switch( $householdtype ){
            case 'vistacoventry':
                $conditions = array(
                                        'health_plan_id' => $applyHousehold['health_plan_id'],
                                        'benefit_plan' => $applyHousehold['benefit_plan'],
                                    );

                $sql = "SELECT
                               COUNT(entity_id) AS records,
                               MAX(member_id) AS household_value,
                               GROUP_CONCAT(entity_id) AS entities
                        FROM members
                        WHERE health_plan_id=". $this->db->driver->formatParameterName('health_plan_id') ." 
                        AND (benefit_plan!=". $this->db->driver->formatParameterName('benefit_plan') ." OR isnull(benefit_plan))
                        GROUP BY address_1,address_2,city,state_code,zipcode
                        HAVING records>1";
                
                $statement = $this->db->createStatement($sql,$conditions);
                $rowset = $statement->execute();

                while( $rowset->valid() ){
                    $current_data = $rowset->current();
                    $entities = "";
                    $household_value = "";
                    foreach ($current_data as $key => $value) {
                        
                        if( $key == 'household_value' )
                            $household_value = $value;
                        if( $key == 'entities' )
                        {
                            $sql = "UPDATE members
                                     SET household = '". $household_value ."' 
                                     WHERE entity_id IN (". $value .")";
                            $statement=$this->db->createStatement($sql);
                            $result=$statement->execute();
                        }
                    }
                    
                    $rowset->next();
                }
                return $rowset->getAffectedRows();
             break;
             case 'devpippmp':
                $sql = "SELECT COUNT(*) AS records,
                                MAX(member_id) AS member_id,
                                GROUP_CONCAT(entity_id) AS entities,
                                address_1
                            FROM members 
                            GROUP BY address_1, address_2, city, state_code, zipcode
                            HAVING records > 1";
                $statement = $this->db->createStatement($sql);
                $rowset = $statement->execute();
                
                while( $rowset->valid() ){
                    $current_data = $rowset->current();

                    $entities = "";
                    $member_id = "";
                    foreach ($current_data as $key => $value) {
                        if( $key == 'member_id' )
                            $member_id = $value;
                        if( $key == 'entities' )
                        {
                            $sql = "UPDATE members 
                                        SET group_number = ". $member_id ." 
                                        WHERE entity_id IN (". $value .")";

                            $statement=$this->db->createStatement($sql);
                            $result=$statement->execute();
                        }
                    }
                    $rowset->next();
                }
                return $rowset->getAffectedRows();
             break;
             case 'molina':
                $conditions = array(
                                        'group_name' => $applyHousehold['group_name'],
                                    );
                $sql = "SELECT COUNT(entity_id) AS records,
                               MAX(member_id) AS member_id,
                               GROUP_CONCAT(entity_id) AS entities
                        FROM members
                        WHERE group_name=". $this->db->driver->formatParameterName('group_name') ."
                        GROUP BY address_1,address_2,city,state_code,zipcode
                        HAVING records > 1";
                
                $statement = $this->db->createStatement($sql, $conditions);
                $rowset = $statement->execute();

                while( $rowset->valid() ){
                    $current_data = $rowset->current();

                    $entities = "";
                    $member_id = "";
                    foreach ($current_data as $key => $value) {
                        if( $key == 'member_id' )
                            $member_id = $value;
                        if( $key == 'entities' )
                        {
                            $sql = "UPDATE members 
                                        SET household = ". $member_id ." 
                                        WHERE entity_id IN (". $value .")";
                            $statement=$this->db->createStatement($sql);
                            $result=$statement->execute();
                        }
                    }
                    $rowset->next();
                }
                return $rowset->getAffectedRows();
            break;
        }

    }
}