	<?php 

  include_once('paypalDA.php');

if (!defined("WHMCS"))
    die("This file cannot be accessed directly");



//====================== Addon hooks starts ========================//

function hook_paypaldeluxe_cancel_addon($vars) {
	 $return_fields = array();
	 $qry = "SELECT to.invoiceid, tha.addonid FROM tblhostingaddons tha left join tblorders `to` on to.id = tha.orderid where tha.id = $vars[id]";
	 $data = mysql_fetch_assoc(mysql_query($qry));

	 print_r($data);exit;
	 $pda = new paypalDA();
	 $pda->cancel_addon($data['invoiceid'],  $data['addonid']);
	 return $return_fields;
}


function hook_paypaldeluxe_suspend_addon($vars) {
	 $return_fields = array();
	 $qry = "SELECT to.invoiceid, tha.addonid FROM tblhostingaddons tha left join tblorders `to` on to.id = tha.orderid where tha.id = $vars[id]";
	 $data = mysql_fetch_assoc(mysql_query($qry));
	 #	 print_r($data); exit;

	 $pda = new paypalDA();
	 $pda->suspend_addon($data['invoiceid'],  $data['addonid']);
	 return $return_fields;
}

function hook_paypaldeluxe_unsuspend_addon($vars) {
	 $return_fields = array();
	 $qry = "SELECT to.invoiceid, tha.addonid FROM tblhostingaddons tha left join tblorders `to` on to.id = tha.orderid where tha.id = $vars[id]";
	 $data = mysql_fetch_assoc(mysql_query($qry));
	 #print_r($data);exit;
	 $pda = new paypalDA();
	 $pda->reactivate_addon($data['invoiceid'],  $data['addonid']);
	 return $return_fields;
}


add_hook("AddonCancelled", 10, "hook_paypaldeluxe_cancel_addon");
add_hook("AddonSuspended", 10, "hook_paypaldeluxe_suspend_addon");
add_hook("AddonUnsuspended", 10, "hook_paypaldeluxe_unsuspend_addon");
add_hook("AddonTerminated", 10, "hook_paypaldeluxe_cancel_addon");


//====================== Addon hooks ends ========================//




//====================== Service hooks starts ========================//

function hook_paypaldeluxe_cancel_service($vars) {
	 $return_fields = array();
	 $qry = "SELECT to.invoiceid, tha.id FROM tblhosting th left join tblorders `to` on to.id = tha.orderid where th.id = $vars[serviceid]";
	 $res = mysql_query($qry);
	 if($res) {
		$data = mysql_fetch_assoc(mysql_query($qry));
		$pda = new paypalDA();
		$pda->cancel_addon($data['invoiceid'],  $data['id']);
	 }
	 return $return_fields;
}

function hook_paypaldeluxe_suspend_service($vars) {
	 $return_fields = array();
	 $qry = "SELECT to.invoiceid, tha.id FROM tblhosting th left join tblorders `to` on to.id = tha.orderid where th.id = $vars[serviceid]";
	 $res = mysql_query($qry);
	 if($res) {
		$data = mysql_fetch_assoc(mysql_query($qry));
		$pda = new paypalDA();
		$pda->suspend_addon($data['invoiceid'],  $data['id']);
	 }
	 return $return_fields;
}

function hook_paypaldeluxe_unsuspend_service($vars) {
	 $return_fields = array();
	 $qry = "SELECT to.invoiceid, tha.id FROM tblhosting th left join tblorders `to` on to.id = tha.orderid where th.id = $vars[serviceid]";
	 $res = mysql_query($qry);
	 if($res) {
		$data = mysql_fetch_assoc(mysql_query($qry));
		$pda = new paypalDA();
		$pda->reactivate_addon($data['invoiceid'],  $data['id']);
	 }
	 return $return_fields;
}

//add_hook("AfterModuleSuspend", 10, "hook_paypaldeluxe_suspend_service");
//add_hook("AfterModuleTerminate", 10, "hook_paypaldeluxe_cancel_service");
//add_hook("AfterModuleUnsuspend", 10, "hook_paypaldeluxe_unsuspend_service");

//====================== Service hooks ends ========================//


function PD_GetSystemURL() {
		require_once dirname ( __FILE__ ) .  '/../../../configuration.php';
		global $CONFIG;
		if (!empty($CONFIG['SystemSSLURL'])) {
			return trim($CONFIG['SystemSSLURL']);
		}
			return trim($CONFIG['SystemURL']);
		
}	

function hook_paypal_deluxe_center_trial($params) {
	
	//print_r($params); exit;
	
	require_once (dirname(__FILE__)."/../../../init.php");
	require_once (dirname(__FILE__)."/../../../modules/gateways/paypaldeluxe.php");
	require_once (dirname(__FILE__)."/../../../includes/gatewayfunctions.php");
	
	$GATEWAY = getGatewayVariables("paypaldeluxe");	
	$url = PD_GetSystemURL();
	$userid = $params['clientdetails']['userid'];
	$currencyID = $params['currency']['code'];
	$invId = $params['invoiceid'];
	$amount = $params['amount'];
	$paymentmethod = $params['paymentmethod'];
	$desc = 'ProfileAgreement'; // get inv desc
	$environment = $GATEWAY['Testmode'] == 'on' ? 'sandbox' : '';
	
    $result_gateway = select_query("tblclients", 'gatewayid', array('id' => $userid));
    $client = mysql_fetch_assoc($result_gateway);
	
	
	if($amount == 0.00 && $paymentmethod == 'paypaldeluxe' && empty($client['gatewayid'])) {
		
		$returnURL = urlencode($url . "modules/gateways/callback/paypaldeluxe.php?amount=$amount&currency=$currencyID&clientid=$userid&zerocheckout=true&invoiceid=$invId");
		$cancelURL = urlencode($url ."viewinvoice.php?id=" . $invId);
		
		$clientContactInfoStrDoRef = '&NOSHIPPING=2&ADDROVERRIDE=0';
		$paypalDA = new paypalDA();

//echo $paypalDA->is_recurring($invId); exit;
		if($paypalDA->is_recurring($invId)){
			 $clientContactInfoStrDoRef .=  "&PAYMENTACTION=Authorization&".$paypalDA->get_rec_billing_descriptions($desc);
			 
			 $clientContactInfoStrDoRef .= "&a1=0&p1=1&t1=M";
		
			
			//redirect to paypal for billing agreement creation.
				$result_gateway = select_query("tblclients", 'gatewayid', array('id' => $userid));
			$client = mysql_fetch_assoc($result_gateway);

			 #   $nvpStr = "&MAXAMT=$amount&Amt=$amount&RETURNURL=$returnURL&CANCELURL=$cancelURL&CURRENCYCODE=$currencyID&BUTTONSOURCE=MyWorksDesign_SP&DESC=" . urlencode($params['description']) ."&page_style=" . urlencode($params['Paypal_Custom_Page']);

	 $nvpStr=   "&METHOD=SetExpressCheckout" 
			   . "&VERSION=110.0" 
			   . "&RETURNURL=$returnURL"
			   . "&CANCELURL=$cancelURL"
			   . "&BUTTONSOURCE=MyWorksDesign_SP"
			   . "&PAYMENTREQUEST_0_CURRENCYCODE=$currencyID"
			   . "&PAYMENTREQUEST_0_AMT=$amount"
			   . "&PAYMENTREQUEST_0_ITEMAMT=$amount"
			   . "&PAYMENTREQUEST_0_TAXAMT=0.00"
			   . "&PAYMENTREQUEST_0_PAYMENTACTION=Sale"
			   . "&L_PAYMENTREQUEST_0_NAME0=". urlencode($params['description'])
			   . "&L_PAYMENTREQUEST_0_QTY0=1"
			   . "&L_PAYMENTREQUEST_0_AMT0=$amount"
			   . "&SOLUTIONTYPE=Sole"
				."&ADDROVERRIDE=1"
				."&localecode=". urlencode($params['country'])
				."&hdrbordercolor=ffffff"
				."&hdrbackcolor=cacdb2"
				."&channeltype=Merchant"
				."&reqconfirmshipping=0";
				$httpParsedResponseAr = PPHttpPost12Deluxe('SetExpressCheckout', $nvpStr.$clientContactInfoStrDoRef, $GATEWAY['API_USERNAME'], $GATEWAY['API_PASSWORD'],$GATEWAY['API_SIGNATURE'], $environment);

				if ("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {
					$token = urldecode($httpParsedResponseAr["TOKEN"]);
					

						$paypal_url = "https://www.paypal.com/webscr";
			
					if ("sandbox" === $environment || "beta-sandbox" === $environment) {
						$paypal_url = "https://www.$environment.paypal.com/webscr";
					}
		

						
					$redirectURL=$paypal_url."?cmd=_express-checkout&token=$token&page_style=".$GATEWAY['Paypal_Custom_Page'];
				}

				header("Location: $redirectURL");
			}
	}
}


//add_hook('InvoiceCreation', 1, 'hook_paypal_deluxe_center_trial');
add_hook('ShoppingCartCheckoutCompletePage', 1, 'hook_paypal_deluxe_center_trial');