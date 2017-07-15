<?php
/*
 *****************************************************************************
 *                                                                       	*
 *  Start MyWorks Code				        					        	*
 * Copyright (c) 2007-2015 MyWorks Design. All Rights Reserved,          	*
 * PayPal Deluxe Module 		                                          	*
 * Last Modified: 24 June 2015                                          	*
 *                                                                       	*
 *                                                                       	*
 *IMPORTANT! THIS MODULE IS FREE FOR INDIVIDUAL USE. IT MAY BE DISTRIBUTED 	*
 *FOR NO CHARGE, BUT ALL CODE IS PROPERTY OF MYWORKS DESIGN. NO PART OF THIS*
 *MODULE MAY BE CHANGED AND DISTRIBUTED, EITHER FREE OR FOR SALE, UNDER A	*
 *DIFFERENT NAME.                                                           *
 *                                                                          *
 *MODIFICATION AND REDISTRIBUTION, EITHER FREE OR FOR SALE IS PROHIBITED.   *
 *                                                                          *
 *****************************************************************************
 */
if (!defined("WHMCS"))
    die("This file cannot be accessed directly");

function get_paypal_deluxe_version_data($modName, $xmlUrl = 'http://myworks.design/moduleversion.xml')
{
    
    $xml = simplexml_load_file($xmlUrl); // Check the latest version
    $res = new StdClass();
    // print_r($xml->children()>module);
    if ($xml) {
        foreach ($xml->children() as $addon) // children hierarchy : xml->module->addon
            {
            if (($modName == $addon->addon->name[0])) {
                $res->name    = $addon->addon->name[0];
                $res->version = $addon->addon->version[0];
            }
        }
    }
    
    return $res;
}

function paypal_deluxe_config()
{
    
    $sql         = mysql_query("select value from tbladdonmodules where module='paypal_deluxe' and setting='isactivated'");
    $local       = mysql_fetch_array($sql);
    $isactivated = $local['value'];
    
    $version = '1.1'; // Enter Version HERE AS WELL!!
    $addon   = get_paypal_deluxe_version_data('MyWorks PayPal Deluxe Module');
    if (isset($addon->version)) {
        if (($version < $addon->version)) {
            $version_check = "<a href='https://clients.myworkshosting.com/clientarea.php?action=products'><span class=\"label active\">New Version Available!</span></a>";
        } else {
            $version_check = "<a href='https://clients.myworkshosting.com/clientarea.php?action=products'><span class=\"label expired\">Up To Date!</span></a>";
        }
        
    }
    
    $configarray = array(
        "name" => "MyWorks PayPal Deluxe Module",
        "description" => "Use our deluxe PayPal Gateway to enhance your client's checkout experience and increase your standard PayPal gateway features!",
        "version" => "$version",
        "author" => "<img src=\"../modules/addons/paypal_deluxe/img/logo.png\"> </br> $version_check $isactivated",
        "language" => "english",
        "fields" => array(
            
            //Enable/Disable Subscriptions
            "enablesubscription" => array(
                "FriendlyName" => "Enable Subscriptions",
                "Type" => "yesno",
                "Description" => "Check to enable subscriptions for customers who check out with recurring payment products. If unchecked, only one time payments will be allowed. <strong>Note: In-Context Checkout will not work when subscriptions are enabled.</strong>"
            ),
            
            //Set Gateway as Default Checkbox
            "autodefault" => array(
                "FriendlyName" => "Automatically Set As Default",
                "Type" => "yesno",
                "Description" => "Check to change client's Default Payment Method to this gateway after paying for the first time using it. Helpful in making sure clients migrating to this form of payment stay using this form of payment."
            ),
            
            //New or Old Checkout Field
            "choosecheckout" => array(
                "FriendlyName" => "Choose PayPal Checkout Style",
                "Type" => "radio",
                "Options" => "In-Context,New Express Checkout",
                "Description" => "Choose your PayPal Checkout Style. <strong>Note: In-Context Checkout will not work when subscriptions are enabled.</strong>"
            ),
            
            //Custom Return URL Field
            "CUSTOM_RETURN_URL" => array(
                "FriendlyName" => "Custom Return URL",
                "Type" => "text",
                "Size" => "70",
                "Description" => "Enter a URL in this box for clients to be redirected to after they successfully check out with this module."
            )
            
            
        )
    );
    return $configarray;
}

function paypal_deluxe_activate()
{
    
    #Create Table
    $query  = "CREATE TABLE `mod_paypaldeluxe` (
			          `id` INT(11) NOT NULL AUTO_INCREMENT,
				      `invoiceid` INT NOT NULL,
			          `paypal_profile_id` VARCHAR(100),
					   `type` VARCHAR(20) NOT NULL,
			          `typeid` INT(1) NOT NULL,
					  `status` enum('active', 'suspended', 'cancelled', 'reactivated')
			          PRIMARY KEY (`id`) )";
    $result = full_query($query);

	 #Activate Gateway  
		    $query1 = "INSERT INTO `tblpaymentgateways` (`gateway`, `setting`, `value`, `order`)
					  VALUES ('paypaldeluxe', 'name', 'MyWorks Paypal Deluxe Module', '0'),
					  ('paypaldeluxe', 'type', 'Invoices', '0'),
					  ('paypaldeluxe', 'visible', 'on', '0');";
		    $result = full_query($query1);
    
    #Activate Module  
    $query2 = "INSERT INTO `tbladdonmodules` (`module`, `setting`, `value`) VALUES ('paypal_deluxe', 'isactivated', '<span class=\"label active\">Activated</span>');";
    $result = full_query($query2);
    
    
    return array(
        'status' => 'success',
        'description' => 'You have successfully installed the MyWorks Paypal Deluxe Module! Go to Setup > Payments > Payment Gateways > MyWorks Paypal Deluxe Module to configure the gateway!'
    );
    
    return array(
        'status' => 'error',
        'description' => 'There was an error activating the module.'
    );
}

function paypal_deluxe_deactivate()
{
    
    return array(
        'status' => 'success',
        'description' => 'You have successfully uninstalled the MyWorks PayPal Deluxe gateway!'
    );
    return array(
        'status' => 'error',
        'description' => 'There was an error de-activating the module.'
    );
    mysql_query("DELETE FROM tblpaymentgateways WHERE gateway='paypaldeluxe' ");
}

function paypal_deluxe_upgrade($vars)
{
    
    $version = $vars['version'];
    # By default, version_compare() returns -1 if the first version is lower than the second, 0 if they are equal, and 1 if the second is lower.   
	
    # Run SQL Updates to upgrade to V1.1
    if ($version < 1.1) {
		$query = "UPDATE `tblpaymentgateways` SET `value` = REPLACE (`value`, 'CC', 'Invoices') WHERE `gateway` LIKE 'paypaldeluxe' AND `setting` LIKE 'type';";
    	$result = mysql_query($query);   	  
    } 
}

function paypal_deluxe_sidebar($vars)
{
    
    $config        = paypal_deluxe_config();
    $modulelink    = $vars['modulelink'];
    $version       = $vars['version'];
    $version_check = '';
    
    $addon = get_paypal_deluxe_version_data('MyWorks PayPal Deluxe Module');
    if (isset($addon->version)) {
        if (($version < $addon->version)) {
            $version_check = "<a href='https://clients.myworkshosting.com/clientarea.php?action=products'><span class=\"label active\">New Version Available!</span></a>";
        } else {
            $version_check = "<a href='https://clients.myworkshosting.com/clientarea.php?action=products'><span class=\"label expired\">Up To Date!</span></a>";
        }
        
    }
    
    
    $sidebar = '<span class="header"><img src="images/icons/income.png" class="absmiddle" width="16" height="16" /> PayPal Deluxe</span>
	<ul class="menu">
		<li><a href="">Documentation</a></li> 
		<li><a href="https://clients.myworkshosting.com/submitticket.php?step=2&deptid=6">Support</a></li> 
		<li><br></li>
        <li><a href="#">My Version: ' . $version . '</a></li>
		<li>' . $version_check . '</li>
    </ul>';
    return $sidebar;
    
}


function getPayPalDGatewayDetails()
{
    
    require_once("../includes/gatewayfunctions.php");
    
    #  include("../includes/invoicefunctions.php");
    $gatewaymodule = "paypaldeluxe"; # Gateway Module Name
    $GATEWAY       = getGatewayVariables($gatewaymodule);
    return $GATEWAY;
}



if (!function_exists('mysql_select')) {
    function mysql_select($query)
    {
        
        $data = array();
        
        $result = mysql_query($query) or die(mysql_error());
        
        while ($row = mysql_fetch_assoc($result)) {
            
            $data[] = $row;
        }
        return $data;
    }
}


function paypal_deluxe_output($vars)
{
?>
    <?php
    if ($status) {
?>
        <div class="<?php
        echo $status;
?>box">
            <strong>
                <span class="title">
                    <?php
        echo strtoupper($status);
?>
                </span>
            </strong><br>
            <?php
        echo $msg;
?>
        </div>
    <?php
    }
    
    if ($_REQUEST['action'] == ''):
?>
   
    <div id="tabs"><ul><li class="tab" id="tab0"><a href="javascript:;">Summary</a></li></div>
    <div class="tabbox" id="tab0box" >
        <div id="tab_content">

           <p>
	There's nothing to show here :)
	</p>

        </div>
    </div>
    <?php
    elseif ($_REQUEST['action'] == 'migrate'):
        require(dirname(__FILE__) . "/migrate.php");
    endif;
}

function paypal_deluxe_clientarea($vars)
{
    
    $modulelink = $vars['modulelink'];
    $version    = $vars['version'];
    $LANG       = $vars['_lang'];
    
    return array(
        'pagetitle' => 'PayPal Deluxe',
        'breadcrumb' => array(
            'index.php?m=paypal_deluxe' => 'PayPal Deluxe'
        ),
        'templatefile' => 'home',
        'requirelogin' => true # or false
    );
    
}
?>