<?php

class paypalDA {

public $GATEWAY;
private $addonRecCount = 0;
private	$hostingRecCount = 0;

public function __construct() {

	if(!function_exists('getGatewayVariables')) {
	    require_once("../includes/gatewayfunctions.php");
	}
	$this->GATEWAY = getGatewayVariables("paypaldeluxe");
	$this->environment = ($this->GATEWAY['Testmode'] == 'on') ? 'sandbox' : '';
}

public function is_recurring($invNum) {
	$subsqry = select_query("tbladdonmodules", 'value', array('module' => "paypal_deluxe", 'setting' => "enablesubscription"));
	$subsVal = mysql_fetch_assoc($subsqry);


	$val = $subsVal['value'];

	if(empty($val)) {
		return false;
	}

	$addonData = $this->get_addon_orders($invNum);
	$hostingData = $this->get_hosting_order($invNum);
	$hRec = $this->pd_is_recurring($hostingData); 
	$aRec = $this->pd_is_recurring($addonData, true);
	return $hRec || $aRec;
	 
}

public function get_hosting_order($invNum) {

	 $query = "SELECT DISTINCT ha.billingcycle, it.invoiceid, it.amount as amt,'Hosting' AS type, it.duedate as duedate, ha.id as id from tblinvoiceitems it LEFT JOIN tblorders o on o.invoiceid = it.invoiceid LEFT JOIN tblhosting ha on ha.orderid=o.id where it.type = 'Hosting' and it.invoiceid = $invNum";

	$data = mysql_fetch_assoc(mysql_query($query));

	#$recurring = $this->pd_is_recurring($hostingData);

	return $data;
}

public function get_addon_orders($invNum) {

	 $query = "SELECT DISTINCT ha.setupfee as setup_amt,ha.recurring as recurring_amt, ha.billingcycle, it.invoiceid, it.amount as amt, 'Addon' AS type, it.duedate as duedate, ha.id as id from tblinvoiceitems it LEFT JOIN tblorders o on o.invoiceid = it.invoiceid LEFT JOIN tblhostingaddons ha on ha.orderid=o.id where it.type = 'Addon' and it.invoiceid = $invNum";

	$data = mysql_query($query);

     #$cntQry = "SELECT COUNT(invoiceid) as count from tblinvoiceitems where type='Addon' and invoiceid = $invNum";
	 #$count = mysql_fetch_assoc(mysql_query($cntQry));

	#$recurring = $this->pd_is_recurring($hostingData);
	while($row = mysql_fetch_assoc($data)){
	 $newdata[] = $row; 
	}

	return $newdata;
}




private function pd_is_recurring($data, $addon = false) {

    $recurring = false;
	$this->addonRecCount = 0;
	$this->hostingRecCount = 1;
	if($addon) {
	
		foreach($data AS $d) {
		
			if(!empty($d['billingcycle'])) {
				$recurring = true;
				$this->addonRecCount++;
			}
		}
	} elseif(!empty($data['billingcycle'])) {
			
		$recurring = true;
		
	}

	return $recurring;

}


#invoice id, addon or hosting, paypalrefid - rec prof id
public function save($invId, $paypalPId, $typeid, $type="Hosting") {

	if(empty($invId) || empty($paypalPId) || empty($type)) {
		throw new Exception('Invoice id or Paypal profile id or type should not be empty');
	}

	#typeid is the id of the particular type

	$query = "INSERT INTO `mod_paypaldeluxe` SET `invoiceid` = $invId, paypal_profile_id = '$paypalPId', `type`= '$type', `typeid`= $typeid,  `action`='active'";
	return mysql_query($query);
}



#invoice id, addon or hosting
#need to delete particular addon
public function delete($invId,$type, $id) {

	if(empty($invId) || empty($type)) {
		throw new Exception('Invoice id or type should not be empty');
	}
	#join with tbladdon or tblhosting

	$query = "DELETE FROM `mod_paypaldeluxe` WHERE `invoiceid` = $invId AND `type`= $type AND typeid = $id";
	return mysql_query($query);
}

public function get_paypal_bc($bt){

	$res = array();
	switch(strtolower($bt)) {
		case 'annually': $res = array('BILLINGPERIOD'=>'Year', 'BILLINGFREQUENCY'=>'1'); break;
		case 'daily':  $res = array('BILLINGPERIOD'=>'Day', 'BILLINGFREQUENCY'=>'1'); break;
		case 'monthly':  $res = array('BILLINGPERIOD'=>'Month', 'BILLINGFREQUENCY'=>'1'); break;
		case 'quarterly':  $res = array('BILLINGPERIOD'=>'Month', 'BILLINGFREQUENCY'=>'3'); break;
		case 'biennially':  $res = array('BILLINGPERIOD'=>'Month', 'BILLINGFREQUENCY'=>'6'); break;
		case 'triennially':  $res = array('BILLINGPERIOD'=>'Month', 'BILLINGFREQUENCY'=>'4'); break;
		case 'semiannually':  $res = array('BILLINGPERIOD'=>'Month', 'BILLINGFREQUENCY'=>'6'); break;
		default: $res = array('BILLINGPERIOD'=>'Month', 'BILLINGFREQUENCY'=>'1');
	}

	return http_build_query($res);

}

public function get_rec_billing_descriptions($billingdescription) {

	$str = "L_BILLINGTYPE0=RecurringPayments&L_BILLINGAGREEMENTDESCRIPTION0=$billingdescription";
	for ($i=1; $i<=$this->addonRecCount; $i++) {
		$str .= "&L_BILLINGTYPE{$i}=RecurringPayments&L_BILLINGAGREEMENTDESCRIPTION{$i}=$billingdescription{$i}";
	}

	return $str;
	
}

public function create_profile($httpParsedResponseAr2, $orderItemData, $token, $recProfDesc) {
 #must me same as in SetExp method
 #$recProfDesc = 'ProfileAgreement';
 $paypalBCNvp = $this->get_paypal_bc($orderItemData['billingcycle']);
 $today = gmdate("Y-m-d\TH:i:s\Z");

 $rpNvp="&PROFILESTARTDATE=$today&$paypalBCNvp&AMT=$orderItemData[amt]&DESC=$recProfDesc&EMAIL=$httpParsedResponseAr2[EMAIL]&STREET=$httpParsedResponseAr2[SHIPTOSTREET]&CITY=$httpParsedResponseAr2[SHIPTOCITY]&COUNTRYCODE=$httpParsedResponseAr2[SHIPTOCOUNTRYCODE]&ZIP=$httpParsedResponseAr2[SHIPTOZIP]&PAYERID=$httpParsedResponseAr2[PAYERID]";

	$varss =  urldecode($rpNvp);
	//echo $varss;
	$recProRes = $this->PPHttpPostDeluxe('CreateRecurringPaymentsProfile', "&token=$token".$varss, $this->environment);

	
	if($recProRes['ACK'] == 'Success') {
		//need to save prof id with corresponding hos/addon ids in database $recProRes['PROFILEID'];
        $profId =  urldecode($recProRes['PROFILEID']);
		$this->save($orderItemData['invoiceid'], $profId, $orderItemData['id'], $orderItemData['type']);
	} else {
		throw new Exception($recProRes['L_LONGMESSAGE0']);
	}

	return $profId;
}



#$action must be Cancel/Suspend/Reactivate
public function manage_profile_status($profileId, $token, $action) {

    $rpNvp="&PROFILEID=$profileId&ACTION=$action";

	$varss =  urldecode($rpNvp);

	$recProRes = $this->PPHttpPostDeluxe('ManageRecurringPaymentsProfileStatus', "&token=$token".$varss, $this->environment);

	#print_r($recProRes);
    switch($action) {
		case 'Cancel': $act = 'cancelled';break;
		case 'Suspend': $act = 'suspended';break;
		case 'Reactivate': $act = 'reactivated';break;
		default: $act = 'active';
	
	}
	
	 $this->update_action($act);
	 #do the corresponding action to corresponding product or addon

	//need to save prof id with corresponding hos/addon ids in database $recProRes['PROFILEID'];
    $profId =  urldecode($recProRes['PROFILEID']);
	//need to have status active/canceled/suspended
	#return $this->save($orderItemData['invoiceid'], $profId, $orderItemData['id'], $orderItemData['type']);
	
}


private function update_action($action, $invoiceid,  $addonid) {
	$updateAction = "update mod_paypaldeluxe set action = '$action' where typeid = $addonid and invoiceid = $invoiceid";
	return mysql_query($updateAction);
}

private function exec_action($invoiceid,  $typeid, $action,$type, $profId = 0) {

	$vars['PROFILEID'] = ($profId!=0) ? $profId : $this->get_profile_id($invoiceid,  $typeid);
	$vars['ACTION']  = $action;

	if($action == 'Suspend') {
		$finAction = 'suspended';
	} elseif($action == 'Cancel') {
		$finAction = 'cancelled';
	} else {
		$finAction = 'reactivated';
	}
    #call paypal to cancel
	$res = $this->PPHttpPostDeluxe('ManageRecurringPaymentsProfileStatus', '&'.http_build_query($vars), $this->environment);
	if($res['PROFILEID']) {
		return $this->update_action($finAction, $invoiceid,  $typeid);		
	}
	return false;
}

public function cancel_addon($invoiceid,  $addonid) {
	return $this->exec_action($invoiceid,  $addonid,  'Cancel', 'Addon');
}

public function suspend_addon($invoiceid,  $addonid) {
   	return $this->exec_action($invoiceid,  $addonid,  'Suspend', 'Addon');
}

public function reactivate_addon($invoiceid,  $addonid) {
  	return $this->exec_action($invoiceid,  $addonid,  'Reactivate', 'Addon');
}

public function cancel_product($invoiceid,  $hostingId) {
	return $this->exec_action($invoiceid,  $hostingId,  'Cancel', 'Hosting');
}

public function suspend_product($invoiceid,  $hostingId) {
   	return $this->exec_action($invoiceid,  $hostingId,  'Suspend', 'Hosting');
}

public function reactivate_product($invoiceid,  $hostingId) {
  	return $this->exec_action($invoiceid,  $hostingId,  'Reactivate', 'Hosting');
}

public function get_profile_id($invoiceid,  $addonid) {
    $sql = "select paypal_profile_id from mod_paypaldeluxe where invoiceid = $invoiceid and typeid = $addonid";
    $res = mysql_fetch_assoc(mysql_query($sql));
	return $res['paypal_profile_id'];
}

public function get_all_profiles($userId=0) {
	$where = '1';
	if($userid) {
		$where = "tc.id = $userId";
	}
    $sql = "select mpd.*, tc.firstname, tc.lastname, tc.id as userid from `mod_paypaldeluxe` as mpd inner join `tblinvoices` as tinv on tinv.id = mpd.invoiceid inner join `tblclients` as tc on tc.id = tinv.userid where $where";
    $res = mysql_query($sql);
	$newdata = array();
	while($row = mysql_fetch_assoc($res)){
		$newdata[] = $row; 
	}
	return $newdata;
}

#to get possible actions of a profile
public function get_next_actions($profile) {

	$act = array();
	$url="clientssummary.php?userid=$profile[userid]&type=$profile[type]&typeid=$profile[typeid]&invoiceid=$profile[invoiceid]";
	$suspendUrl="$url&paction=suspend";
	$cancelUrl="$url&paction=cancel";
	$reactivateUrl="$url&paction=reactivate";
	switch($profile['action']) {
		
		case 'active': $act = array('Suspend'=>$suspendUrl, 'Cancel'=>$cancelUrl);break;
		case 'suspended': $act = array('Reactivate'=>$reactivateUrl, 'Cancel'=>$cancelUrl);break;
		case 'reactivated': $act = array('Suspend'=>$suspendUrl, 'Cancel'=>$cancelUrl);break;
		default: $act = 'active';
	}

	return $act;
}

public function PPHttpPostDeluxe($methodName_, $nvpStr_, $environment) {
    $API_UserName = urlencode($this->GATEWAY['API_USERNAME']);
    $API_Password = urlencode($this->GATEWAY['API_PASSWORD']);
    $API_Signature = urlencode($this->GATEWAY['API_SIGNATURE']);
    $API_Endpoint = "https://api-3t.paypal.com/nvp";
    if ("sandbox" === $environment || "beta-sandbox" === $environment) {
        $API_Endpoint = "https://api-3t.$environment.paypal.com/nvp";
    }
    $version = '123.0';
    $nvpreq = "METHOD=$methodName_&VERSION=$version&PWD=$API_Password&USER=$API_UserName&SIGNATURE=$API_Signature&BUTTONSOURCE=MyWorksDesign_SP$nvpStr_";


    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$API_Endpoint?$nvpreq");
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $httpResponse = curl_exec($ch);
    if (!$httpResponse) {
        exit("$methodName_ failed: " . curl_error($ch) . '(' . curl_errno($ch) . ')');
    }
    $httpResponseAr = explode("&", $httpResponse);
    $httpParsedResponseAr = array();
    foreach ($httpResponseAr as $i => $value) {
        $tmpAr = explode("=", $value);
        if (sizeof($tmpAr) > 1) {
            $httpParsedResponseAr[$tmpAr[0]] = $tmpAr[1];
        }
    }

    if ((0 == sizeof($httpParsedResponseAr)) || !array_key_exists('ACK', $httpParsedResponseAr)) {
        exit("Invalid HTTP Response for POST request($nvpreq) to $API_Endpoint.");
    }



    return $httpParsedResponseAr;
}

}

	