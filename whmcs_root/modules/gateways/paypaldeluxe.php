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
ini_set('display_errors', 'on');
require_once(__DIR__.'/../addons/paypal_deluxe/paypalDA.php');


	function paypaldeluxe_config() {
	    $configarray = array(
	        "FriendlyName" => array("Type" => "System", "Value" => "MyWorks PayPal Deluxe Module"),
	        "API_USERNAME" => array("FriendlyName" => "API Username", "Type" => "text", "Size" => "30",),
	        "API_PASSWORD" => array("FriendlyName" => "API Password", "Type" => "text", "Size" => "30",),
	        "API_SIGNATURE" => array("FriendlyName" => "API Signature", "Type" => "text", "Size" => "70",),
			"API_MERCHANTID" => array("FriendlyName" => "Merchant ID", "Type" => "text", "Size" => "70",),
			"Paypal_Custom_Page" => array("FriendlyName" => "Paypal Checkout Page Variable", "Type" => "text","Description" => "(Optional) This field can be used if you've created a custom Checkout Page in your PayPal account under Settings.", "Size" => "30",), 
	        "Testmode" => array("FriendlyName" => "Test Mode", "Type" => "yesno", "Description" => "Check To Enter Test Mode",),
	    );
	    return $configarray;
	}  

	function paypaldeluxe_link($params) {
		
		
//		print_r($params);
		$invId = $params['invoiceid'];
		$params['description'] = $params['companyname'] . " - " . "Invoice #" . $invId;
		$params['billingdescription'] = 'ProfileAgreement';
		 
	    $userid = $params['clientdetails']['userid'];
	    $environment = $params['Testmode'] == 'on' ? 'sandbox' : '';
	    $amount = $params['amount'];
		$mercId = $params['API_MERCHANTID'];
		$clientContactInfoStrDoRef = '&NOSHIPPING=2&ADDROVERRIDE=0';
		$paypalDA = new paypalDA();

//echo $paypalDA->is_recurring($invId); exit;
		if($paypalDA->is_recurring($invId)){
			 $clientContactInfoStrDoRef .=  "&PAYMENTACTION=Authorization&".$paypalDA->get_rec_billing_descriptions($params['billingdescription']);
			 if($amount == 0) {
				$clientContactInfoStrDoRef .= "&a1=0&p1=1&t1=M";
			 }
		} else {
		  #enable script to paypal in-context style checkout
		
		}

	    if ($params['amount'] == "0.00" && !$paypalDA->is_recurring($invId)) {
	        logActivity("paypal deluxe Error : Amount Found Zero");
	        return;
	    }
	    if (empty($userid)) {
	        logActivity("paypal deluxe Error : User ID Not Found");
	        return;
	    }
	    $currencyID = urlencode($params['currency']);
	    $returnURL = urlencode($params['systemurl'] . "/modules/gateways/callback/paypaldeluxe.php?invoiceid=" . $params['invoiceid'] . "&clientid=$userid&amount=" . $params['amount'] . "&currency=$currencyID");
	   # $cancelURL = urlencode($params['systemurl'] . "/modules/gateways/callback/paypaldeluxe.php?invoiceid=" . $params['invoiceid'] . "&clientid=$userid&amount=" . $params['amount'] . "&currency=$currencyID");

	   $cancelURL = urlencode($params['systemurl'] . "/viewinvoice.php?id=" . $params['invoiceid']);
	   
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
	        $httpParsedResponseAr = PPHttpPost12Deluxe('SetExpressCheckout', $nvpStr.$clientContactInfoStrDoRef, $params['API_USERNAME'], $params['API_PASSWORD'],$params['API_SIGNATURE'], $environment);
			//print_r($httpParsedResponseAr);

	        if ("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {

	            $token = urldecode($httpParsedResponseAr["TOKEN"]); 
				$script = '
						 
							<script>

	  							  window.paypalCheckoutReady = function () { 
									paypal.checkout.setup("'.$mercId.'", {
										container: "payfrm",
									  }); 
								  };
								  document.onready = function(){
								  if($("#submitfrm").length) {
								   $("#submitfrm").attr("id", "myworkssubmitfrm");
								   setTimeout("autoClick()", 3000);
									function autoClick() {
										$("#submitfrm").find("button:first").click();
									}
								  }
								 };
							</script>
							  <script src="//www.paypalobjects.com/api/checkout.js"></script>
							  ';
	//Choose New or Old Checkout Style
                $sql = mysql_query("select value from tbladdonmodules where module='paypal_deluxe' and setting='choosecheckout'");
				$local=mysql_fetch_array($sql);
				$key=mysql_num_rows($sql);
				$checkoutStyle = $local['value'];

			 	if ($checkoutStyle != 'In-Context') {
	            	$paypal_url = "https://www.paypal.com/webscr&cmd=_express-checkout";
				}else{ 
					$paypal_url = "https://www.paypal.com/webscr";
		 			#$paypal_url = "https://www.paypal.com/cgi-bin/webscr";

				}       
		
	            if ("sandbox" === $environment || "beta-sandbox" === $environment) {
	                $paypal_url = "https://www.$environment.paypal.com/webscr";
	            }
	            //Choose New or in-context Checkout Style
			             
						 	if ($checkoutStyle != 'In-Context') {
				            	$code = ' <form id="payfrm" action="' . $paypal_url . '" method="post" >
				  							<input type="hidden" name="page_style" value="' . $params['Paypal_Custom_Page'] . '" />
					                      	<input type="hidden" name="token" value="' . $token . '" />
											<input type="hidden" name="cmd" value="_express-checkout" />
											<button class="paypal-button" type="submit" style="background: rgba(0, 0, 0, 0) none repeat scroll 0 0; border: 0 none; cursor: pointer">
											<img src="https://www.paypalobjects.com/en_US/i/btn/btn_xpressCheckout.gif?akam_redir=1">
											</button>
					                       </form>';
					            return $code;
							}else{ 
					 			$code = '
								<form action="https://www.paypal.com/checkoutnow?useraction=commit&token='.$token.'" id="payfrm" class="standard">
								<input id="type" type="hidden" name="expType" value="light">
								<input id="token" type="hidden" name="token" value="'.$token.'">
								</form>';
							
								return $code.$script;
							}
	        } else {
	            logTransaction("MyWorks paypal deluxe", $httpParsedResponseAr, "Error");
	            return "<p>PayPal Checkout Error. It seems the PayPal API Credentials are incorrect.</p>";
	        }
	   
	}

	function PPHttpPost12Deluxe($methodName_, $nvpStr_, $API_UserName, $API_Password, $API_Signature, $environment) {
	    //  $environment = 'sandbox';
	    // Set up your API credentials, PayPal end point, and API version.
	    $API_UserName = urlencode($API_UserName);
	    $API_Password = urlencode($API_Password);
	    $API_Signature = urlencode($API_Signature);
	    $API_Endpoint = "https://api-3t.paypal.com/nvp";
	    if ("sandbox" === $environment || "beta-sandbox" === $environment) {
	        $API_Endpoint = "https://api-3t.$environment.paypal.com/nvp";
	    }

		if(isset($nvpStr_['version'])) {
	    $version = $nvpStr_['version']; //latest version
		} else {
		$version = '119.0';
		}
	    // Set the curl parameters.
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $API_Endpoint);
	    curl_setopt($ch, CURLOPT_VERBOSE, 0);

	    // Turn off the server and peer verification (TrustManager Concept).
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_POST, 1);

	    // Set the API operation, version, and API signature in the request.
	    $nvpreq = "METHOD=$methodName_&VERSION=$version&BUTTONSOURCE=MyWorksDesign_SP&PWD=$API_Password&USER=$API_UserName&SIGNATURE=$API_Signature$nvpStr_";

	    // Set the request as a POST FIELD for curl.
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

	    // Get response from the server.
	    $httpResponse = curl_exec($ch);



	    if (!$httpResponse) {
	        exit("$methodName_ failed: " . curl_error($ch) . '(' . curl_errno($ch) . ')');
	    }

	    // Extract the response details.
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
		//echo $methodName_.'<br><pre>';
		//print_r($httpParsedResponseAr);
	    return $httpParsedResponseAr;
	}

	function paypaldeluxe_refund($params) {
	 $environment = $params['Testmode'] == 'on' ? 'sandbox' : '';
	    $query = mysql_query("select `total` from `tblinvoices` where id='" . $params['invoiceid'] . "'");
	    $row = mysql_fetch_assoc($query);
	    if ($params['amount'] < $row['total']) {
	        $refundType = urlencode('Partial');
	    } elseif ($row['total'] == $params['amount']) {
	        $refundType = urlencode('Full');
	    }
	    $invoiceid = $params['invoiceid'];
	    $amount = $params['amount'];
	    $refundType;
	    //die();
	    $transactionID = urlencode($params['transid']);
	    // $refundType = urlencode('Full');                  // required if Partial.
	    $currencyID = urlencode($params['currency']);       // or other currency ('GBP', 'EUR', 'JPY', 'CAD', 'AUD')
	// Add request-specific fields to the request string.
	    $nvpStr = "&TRANSACTIONID=$transactionID&REFUNDTYPE=$refundType&AMT=$amount&CURRENCYCODE=$currencyID&INVOICEID=$invoiceid&NOTE=$invoiceid";

	    if (isset($memo)) {
	        $nvpStr .= "&NOTE=$memo";
	    }

	    if (strcasecmp($refundType, 'Partial') == 0) {
	        if (!isset($amount)) {
	            exit('Partial Refund Amount is not specified.');
	        } else {
	            $nvpStr = $nvpStr . "&AMT=$amount";
	        }

	        //if (!isset($memo)) {
	        //     exit('Partial Refund Memo is not specified.');
	        // }
	    }

	// Execute the API operation; see the PPHttpPost function above.
	    $httpParsedResponseAr = PPHttpPost12Deluxe('RefundTransaction', $nvpStr, $params['API_USERNAME'], $params['API_PASSWORD'], $params['API_SIGNATURE'],$environment);

	    foreach ($httpParsedResponseAr as $key => $value) {
	        $resultarray_final109[$key] = urldecode($value);
	    }

	    if ("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {
	        return array("status" => "success", "transid" => $resultarray_final109['REFUNDTRANSACTIONID'], "rawdata" => $resultarray_final109);
	    } else {

	        return array("status" => "error", "rawdata" => $resultarray_final109);
	    }
	}

?>