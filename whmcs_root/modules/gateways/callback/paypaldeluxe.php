<?php 

/*
*****************************************************************************
*                                                                       	*
*  Start MyWorks Code				        					        	*
* Copyright (c) 2007-2015 MyWorks Design. All Rights Reserved,          	*
* PayPal Deluxe Module 		                                            	*
* Last Modified: 10 March 2015                                          	*
*                                                                       	*
*                                                                       	*
*IMPORTANT! THIS MODULE IS FREE FOR INDIVIDUAL USE. IT MAY BE DISTRIBUTED 	*
*FOR NO CHARGE, BUT ALL CODE IS PROPERTY OF MYWORKS DESIGN. NO PART OF THIS	*
*MODULE MAY BE CHANGED AND DISTRIBUTED, EITHER FREE OR FOR SALE, UNDER A	*
*DIFFERENT NAME.                                                            *
*                                                                           *
*MODIFICATION AND REDISTRIBUTION, EITHER FREE OR FOR SALE IS PROHIBITED.    *
*                                                                           *
*****************************************************************************
*/


require_once "../../../init.php";
	include_once('../../addons/paypal_deluxe/paypalDA.php');


$whmcs->load_function("gateway");
$whmcs->load_function("invoice");
$GATEWAY = getGatewayVariables("paypaldeluxe");
$gatewaymodule = "paypaldeluxe";
if (!$GATEWAY["type"])
    die("Module Not Activated");
$paypalgatewayname = $GATEWAY['name'];
$userid = isset($_GET['clientid']) ? $_GET['clientid'] : NULL;
	$amt = $_GET['amount'];
$invoiceid = $_GET['invoiceid'];
$paypalDA = new paypalDA();
//gets custom url set in addon settings
	$customSuccessUrlQry = select_query("tbladdonmodules", 'value', array('module' => "paypal_deluxe", 'setting' => "CUSTOM_RETURN_URL"));
			$cusUrlVal = mysql_fetch_assoc($customSuccessUrlQry);
			$customSuccessUrl = $cusUrlVal['value'];


$clientContactInfoStr = '';
if($userid) {

	 $token = $_GET['token'];
     $nvpStr = "&BUTTONSOURCE=MyWorksDesign_SP";
 	$httpParsedResponseAr2 = $paypalDA->PPHttpPostDeluxe('GetExpressCheckoutDetails', $nvpStr."&token=$token", $environment);

	
	 $clientContactInfoStr =  http_build_query($httpParsedResponseAr2).'&REQCONFIRMSHIPPING=1'; 
	$clientContactInfoStr = '&'.urldecode( $clientContactInfoStr);
	$clientContactInfoStrDoRef = '&NOSHIPPING=2&REQCONFIRMSHIPPING=1';

	#echo  $clientContactInfoStr;


if($paypalDA->is_recurring($invoiceid)) {
$hostingItem = $paypalDA->get_hosting_order($invoiceid);
$addonItems = $paypalDA->get_addon_orders($invoiceid);

 $invoiceid = checkCbInvoiceID($invoiceid, $GATEWAY["name"]);

$totOneTimeAmt = $feeamount =  0;
$addonRecCount = 0;

foreach($addonItems as $item) {
	try {
		if(!empty($item['billingcycle'])) {
			$addonRecCount++;
		
			$totOneTimeAmt += $item['setup_amt'];
			#charge only recurring amount not set up in rec profile
			  $item['amt'] = $item['recurring_amt'];
			  $profId = $paypalDA->create_profile($httpParsedResponseAr2, $item, $token, "ProfileAgreement{$addonRecCount}");
			  
			  checkCbTransID($profId);
			  addInvoicePayment($invoiceid, $profId,  $item['recurring_amt'], $feeamount, $gatewaymodule);
			 logTransaction("$paypalgatewayname", $resultarray_final, "Successful");

		} else {
			//doexpresscheckoutcode
			$totOneTimeAmt += $item['amt'];
		}
	} catch (Exception $e) {
		print $e->getMessage();
	}	
}

if(!empty($hostingItem['billingcycle'])) {

	 $profId = $paypalDA->create_profile($httpParsedResponseAr2, $hostingItem, $token, 'ProfileAgreement');
	 checkCbTransID($profId);
	 addInvoicePayment($invoiceid, $profId,  $hostingItem['amt'], $feeamount, $gatewaymodule);
     logTransaction("$paypalgatewayname", $resultarray_final, "Successful");

} else {
	//doexpresscheckoutcode
	$totOneTimeAmt += $hostingItem['amt'];
}

} else {
$totOneTimeAmt = $amt;
}

}


//this module cancels the subscription if auto subscription cancellation is enabled in addon settings
//todo check the enabled condition in subscrioption cancellation hook
include('../../addons/paypal_deluxe/paypal_cancel_subscription.php');


if($totOneTimeAmt == 0) {

	  (!empty($customSuccessUrl)) ? header("location: $customSuccessUrl") :
		header("location: $GATEWAY[systemurl]/viewinvoice.php?id=$invoiceid");
}

if (isset($_REQUEST['token']) && $totOneTimeAmt > 0 ) {
    $token1 = $_GET['token'];
	$currency_code = urlencode($_REQUEST['currency']);
    $nvpStr = "&TOKEN=$token1&AMT=$totOneTimeAmt&CURRENCYCODE=$currency_code&BUTTONSOURCE=MyWorksDesign_SP";
    $environment = $GATEWAY['Testmode'] == 'on' ? 'sandbox' : '';
	

	   $resultarray_final = $httpParsedResponseAr = $paypalDA->PPHttpPostDeluxe('DoExpressCheckoutPayment', $nvpStr.$clientContactInfoStr.$clientContactInfoStrDoRef, $environment); 
	


    foreach ($httpParsedResponseAr as $key => $value) {
        $resultarray[$key] = urldecode($value);
    }
    if ("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {
		// Code to update default payment method if so chosen in admin configuration

			$auto_default_qry = select_query("tbladdonmodules", 'value', array('module' => "paypal_deluxe", 'setting' => "autodefault"));
			$auto_default = mysql_fetch_assoc($auto_default_qry);
			$set_default = $auto_default['value']=='on' ? true : false;

			if($set_default) {
				update_query("tblclients", array('defaultgateway' => 'paypaldeluxe'), array('id' => $userid));
			}

    
        $invoiceid = checkCbInvoiceID($invoiceid, $GATEWAY["name"]);
   
        $desc = urlencode($GATEWAY['companyname'] . " - Invoice #$invoiceid");
		$currency_code = urlencode($_REQUEST['currency']);

        if ($resultarray_final['ACK'] == 'Success') {
            $transid = $resultarray['PAYMENTINFO_0_TRANSACTIONID'];
            $feeamount = $resultarray['PAYMENTINFO_0_FEEAMT'];

	
            checkCbTransID($transid);

            addInvoicePayment($invoiceid, $transid, $amt, $feeamount, $gatewaymodule);
            logTransaction("$paypalgatewayname", $resultarray_final, "Successful");

            $admin = mysql_fetch_assoc(mysql_query("SELECT id FROM tbladmins"));
            $command = "updateclient";
            $adminuser = $admin['id'];
            $values["clientid"] = $_GET['clientid'];
            $results = localAPI($command, $values, $adminuser);

		      (!empty($customSuccessUrl)) ? header("location: $customSuccessUrl") :
				header("location: $GATEWAY[systemurl]/viewinvoice.php?id=$invoiceid");
		 
        } else {
            logTransaction("$paypalgatewayname", $resultarray_final, "Unsuccessful");
            header("location: $GATEWAY[systemurl]/paypal_error.php?CORRELATIONID=$httpParsedResponseAr[CORRELATIONID]&invoiceid=$_GET[invoiceid]");
        }
    
    } else {
        logTransaction("$paypalgatewayname", $resultarray, "Unsuccessful");
       header("location: $GATEWAY[systemurl]/paypal_error.php?CORRELATIONID=$httpParsedResponseAr[CORRELATIONID]&invoiceid=$_GET[invoiceid]");
    }
}

?> 
                            