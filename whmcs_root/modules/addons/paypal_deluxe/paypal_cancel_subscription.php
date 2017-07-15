<?php
/*
*************************************************************************
*                                                                       *
*  Start MyWorks Code				         					        *
* Copyright (c) 2007-2014 MyWorks Design. All Rights Reserved,          *
* paypal deluxe Code                                         *
* Last Modified: 08th Mar 2015                                          *
*                                                                       *
*************************************************************************
*/ 


$auto_sub_cancellation_qry = mysql_query("SELECT value FROM `tbladdonmodules` WHERE module = 'paypal_deluxe' AND setting LIKE 'autocancel'");
$auto_cancel = mysql_fetch_assoc($auto_sub_cancellation_qry);
if($auto_cancel['value']=='on') {
	cancelSubscription($userid); 
}

// This function will check if a valid subscription ID exists on cancellation
function cancelSubscription($userid) {
	if(!$userid) {
		return false;
	}
	// Check if we have a subscription ID entered in WHMCS:
	$q = "SELECT id AS relid, subscriptionid FROM tblhosting WHERE userid = {$userid}  AND subscriptionid != '' AND paymentmethod = 'paypal'";
	$r = mysql_query($q) or die("Error in query " . mysql_error());
	// If we do, cancel it in PayPal:
	if (mysql_num_rows($r) > 0) {

		while($row = mysql_fetch_assoc($r)) {
			$subscriptionid = $row['subscriptionid']; 
			$relid = $row['relid'];
			// Do PayPal Cancellation
			cancelSubscriptionAPI($relid);
		}
	}
	else {
		logactivity("MYWORKS DEBUG: No PayPal Subscription ID detected for this service - not attempting to cancel.");
	}
}

// This function is used to call the PayPal function with cancellation parameters
// id in tblhosting is relid here
function cancelSubscriptionAPI($relid) {

		//remove subcription id
		$update_qry = "UPDATE tblhosting SET subscriptionid = '' WHERE id = $relid";

		try {
		   $status = mysql_query($update_qry);
		   if(!$status) {
			   $error = mysql_error();
				throw new Exception('Update subscriptionid to null is not successful :'.$error);
		   }
		} catch (Exception $e) {
		    echo 'Caught exception: ',  $e->getMessage(), "\n";
		}
		// Log success to WHMCS activity log
		logactivity("MYWORKS DEBUG: Successfully terminated Paypal Subscription ID: $subscriptionid attached to Service ID: $relid and User ID: $userid");
	} 


