<?php

/**
	* WP eCommerce 2Checkout Payment Module
	* This is the file for the 2Checkout purchase routine.
	* @author Craig Christenson
	* @version 0.0.1
 	* @package wp-e-commerce
 	* @subpackage wpsc-merchants
*/

$nzshpcrt_gateways[$num]['name'] = '2Checkout';
$nzshpcrt_gateways[$num]['internalname'] = 'tco';
$nzshpcrt_gateways[$num]['function'] = 'gateway_tco';
$nzshpcrt_gateways[$num]['form'] = "form_tco";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_tco";
$nzshpcrt_gateways[$num]['payment_type'] = "credit_card";
$nzshpcrt_gateways[$num]['display_name'] = 'Credit Card';
$nzshpcrt_gateways[$num]['image'] = WPSC_URL . '/images/cc.gif';

function gateway_tco($separator, $sessionid)
{
	global $wpdb;
	$purchase_log_sql = "SELECT * FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE `sessionid`= ".$sessionid." LIMIT 1";
	$purchase_log = $wpdb->get_results($purchase_log_sql,ARRAY_A) ;

	$cart_sql = "SELECT * FROM `".WPSC_TABLE_CART_CONTENTS."` WHERE `purchaseid`='".$purchase_log[0]['id']."'";
	$cart = $wpdb->get_results($cart_sql,ARRAY_A) ;

	// tco post variables
    $data['sid'] = get_option('tco_seller_id');
    $data['tco_callback'] = "true";
	$data['lang'] = get_option('tco_language');
	$data['x_receipt_link_url'] = get_option('transact_url');
	$data['cart_order_id'] = $sessionid;
	$data['payment_method'] = 'tco';

	// User details
	if($_POST['collected_data'][get_option('tco_form_first_name')] != '')
    {
    	$data['first_name'] = $_POST['collected_data'][get_option('tco_form_first_name')];
    }
	if($_POST['collected_data'][get_option('tco_form_last_name')] != "")
    {
    	$data['last_name'] = $_POST['collected_data'][get_option('tco_form_last_name')]; }
    if($_POST['collected_data'][get_option('tco_form_phone')] != '')
    {
    	$data['phone'] = $_POST['collected_data'][get_option('tco_form_phone')];
    }
  	if($_POST['collected_data'][get_option('tco_form_address')] != '')
    {
    	$data['street_address'] = str_replace("\n",', ', $_POST['collected_data'][get_option('tco_form_address')]);
    }
   	if($_POST['collected_data'][get_option('tco_form_city')] != '')
    {
    	$data['city'] = $_POST['collected_data'][get_option('tco_form_city')];
    }
   	if($_POST['collected_data'][get_option('tco_form_post_code')] != '')
    {
    	$data['zip'] = $_POST['collected_data'][get_option('tco_form_post_code')];
    }
  	if($_POST['collected_data'][get_option('tco_form_country')] != '')
    {
    	$data['country'] =  $_POST['collected_data'][get_option('tco_form_country')][0];
    }
    if ($data['country'] == 'US' || $data['country'] == 'CA') {
    	$data['state'] = get_state($_SESSION['wpsc_selected_region']);
    } else {
    	$data['state'] = 'XX';
    }

  	$email_data = $wpdb->get_results("SELECT `id`,`type` FROM `".WPSC_TABLE_CHECKOUT_FORMS."` WHERE `type` IN ('email') AND `active` = '1'",ARRAY_A);
  	foreach((array)$email_data as $email)
    {
    	$data['email'] = $_POST['collected_data'][$email['id']];
    }
  	if(($_POST['collected_data'][get_option('email_form_field')] != null) && ($data['email'] == null))
    {
    	$data['email'] = $_POST['collected_data'][get_option('email_form_field')];
    }

	// Get Currency details
	$currency_code = $wpdb->get_results("SELECT `code` FROM `".WPSC_TABLE_CURRENCY_LIST."` WHERE `id`='".get_option('currency_type')."' LIMIT 1",ARRAY_A);
	$local_currency_code = $currency_code[0]['code'];

	$curr=new CURRENCYCONVERTER();
	$decimal_places = 2;
	$total_price = $purchase_log[0]['totalprice'];

	$i = 1;

	$all_donations = true;
	$all_no_shipping = true;

	foreach($cart as $item)
	{
		$product_data = $wpdb->get_results("SELECT * FROM `" . $wpdb->posts . "` WHERE `id`='".$item['prodid']."' LIMIT 1",ARRAY_A);
		$product_data = $product_data[0];
		$variation_count = count($product_variations);

		$variation_sql = "SELECT * FROM `".WPSC_TABLE_CART_ITEM_VARIATIONS."` WHERE `cart_id`='".$item['id']."'";
		$variation_data = $wpdb->get_results($variation_sql,ARRAY_A);
		$variation_count = count($variation_data);

		if($variation_count >= 1)
      	{
      		$variation_list = " (";
      		$j = 0;
      		foreach($variation_data as $variation)
        	{
        		if($j > 0)
          		{
          			$variation_list .= ", ";
          		}
        		$value_id = $variation['venue_id'];
        		$value_data = $wpdb->get_results("SELECT * FROM `".WPSC_TABLE_VARIATION_VALUES."` WHERE `id`='".$value_id."' LIMIT 1",ARRAY_A);
        		$variation_list .= $value_data[0]['name'];
        		$j++;
        	}
      		$variation_list .= ")";
      	}
      	else
        {
        	$variation_list = '';
        }

    	$local_currency_productprice = $item['price'];

			$local_currency_shipping = $item['pnp'];


			$tco_currency_productprice = $local_currency_productprice;
			$tco_currency_shipping = $local_currency_shipping;

                        $data['c_name_'.$i] = $product_data['post_name'].$variation_list;
                        $data['c_description_'.$i] = $product_data['post_excerpt'].$variation_list;
                        $data['c_price_'.$i] = number_format(sprintf("%01.2f", $tco_currency_productprice),$decimal_places,'.','');
                        $data['c_prod_'.$i] = $product_data['post_name'] ."," . $item['quantity'];
                        $quantity.$i = $item['quantity'];

    	$i++;
	}

	$data['total'] = $total_price;


	if(WPSC_GATEWAY_DEBUG == true ) {
  	exit("<pre>".print_r($data,true)."</pre>");
	}


	// Create Form to post to 2Checkout
	$output = "
		<form id=\"tco_form\" name=\"tco_form\" method=\"post\" action=\"https://www.2checkout.com/checkout/spurchase\">\n";

	foreach($data as $n=>$v) {
			$output .= "			<input type=\"hidden\" name=\"$n\" value=\"$v\" />\n";
	}

	$output .= "			<input type=\"submit\" value=\"Continue to 2Checkout\" />
		</form>
	";

	// echo form..
	if( get_option('tco_debug') == 1)
	{
		echo ("DEBUG MODE ON!!<br/>");
		echo("The following form is created and would be posted to 2Checkout for processing.  Press submit to continue:<br/>");
		echo("<pre>".htmlspecialchars($output)."</pre>");
	}

	echo($output);

	if(get_option('tco_debug') == 0)
	{
		echo "<script language=\"javascript\" type=\"text/javascript\">document.getElementById('tco_form').submit();</script>";
	}

  	exit();
}

function get_state($state_id)
{
	switch ($state_id) {
		case '1':
			$state = 'Alberta';
			break;
		case '2':
			$state = 'British Columbia';
			break;
		case '3':
			$state = 'Manitoba';
			break;
		case '4':
			$state = 'New Brunswick';
			break;
		case '5':
			$state = 'Newfoundland';
			break;
		case '6':
			$state = 'Northwest Territories';
			break;
		case '7':
			$state = 'Nova Scotia';
			break;
		case '8':
			$state = 'Nunavut';
			break;
		case '9':
			$state = 'Ontario';
			break;
		case '10':
			$state = 'Prince Edward Island';
			break;
		case '11':
			$state = 'Quebec';
			break;
		case '12':
			$state = 'Saskatchewan';
			break;
		case '13':
			$state = 'Yukon';
			break;
		case '14':
			$state = 'Alabama';
			break;
		case '15':
			$state = 'Alaska';
			break;
		case '16':
			$state = 'Arizona';
			break;
		case '17':
			$state = 'Arkansas';
			break;
		case '18':
			$state = 'California';
			break;
		case '19':
			$state = 'Colorado';
			break;
		case '20':
			$state = 'Connecticut';
			break;
		case '21':
			$state = 'Delaware';
			break;
		case '22':
			$state = 'Florida';
			break;
		case '23':
			$state = 'Georgia';
			break;
		case '24':
			$state = 'Hawaii';
			break;
		case '25':
			$state = 'Idaho';
			break;
		case '26':
			$state = 'Illinois';
			break;
		case '27':
			$state = 'Indiana';
			break;
		case '28':
			$state = 'Iowa';
			break;
		case '29':
			$state = 'Kansas';
			break;
		case '30':
			$state = 'Kentucky';
			break;
		case '31':
			$state = 'Louisiana';
			break;
		case '32':
			$state = 'Maine';
			break;
		case '33':
			$state = 'Maryland';
			break;
		case '34':
			$state = 'Massachusetts';
			break;
		case '35':
			$state = 'Michigan';
			break;
		case '36':
			$state = 'Minnesota';
			break;
		case '37':
			$state = 'Mississippi';
			break;
		case '38':
			$state = 'Missouri';
			break;
		case '39':
			$state = 'Montana';
			break;
		case '40':
			$state = 'Nebraska';
			break;
		case '41':
			$state = 'Nevada';
			break;
		case '42':
			$state = 'New Hampshire';
			break;
		case '43':
			$state = 'New Jersey';
			break;
		case '44':
			$state = 'New Mexico';
			break;
		case '45':
			$state = 'New York';
			break;
		case '46':
			$state = 'North Carolina';
			break;
		case '47':
			$state = 'North Dakota';
			break;
		case '48':
			$state = 'Ohio';
			break;
		case '49':
			$state = 'Oklahoma';
			break;
		case '50':
			$state = 'Oregon';
			break;
		case '51':
			$state = 'Pennsylvania';
			break;
		case '52':
			$state = 'Rhode Island';
			break;
		case '53':
			$state = 'South Carolina';
			break;
		case '54':
			$state = 'South Dakota';
			break;
		case '55':
			$state = 'Tennessee';
			break;
		case '56':
			$state = 'Texas';
			break;
		case '57':
			$state = 'Utah';
			break;
		case '58':
			$state = 'Vermont';
			break;
		case '59':
			$state = 'Virginia';
			break;
		case '60':
			$state = 'Washington';
			break;
		case '61':
			$state = 'Washington DC';
			break;
		case '62':
			$state = 'West Virginia';
			break;
		case '63':
			$state = 'Wisconsin';
			break;
		case '64':
			$state = 'Wyoming';
			break;
		}
	return $state;
}

function nzshpcrt_tco_callback()
{
	global $wpdb;

	if(isset($_REQUEST['tco_callback']) && ($_REQUEST['tco_callback'] == 'true') && ($_REQUEST['payment_method'] == 'tco'))
	{
		$seller_id = get_option('tco_seller_id');
		$secret_word = get_option('tco_secret_word');
		$sessionid = trim(stripslashes($_REQUEST['cart_order_id']));
	    $transaction_id = trim(stripslashes($_REQUEST['order_number']));
		if ($_REQUEST['demo'] == 'Y') {
		$transaction_id = 1;
		}
		$compare_string = $secret_word . $seller_id . $transaction_id . $_REQUEST['total'];
		$compare_hash1 = strtoupper(md5($compare_string));
		$compare_hash2 = $_REQUEST['key'];
		if ($compare_hash1 != $compare_hash2) {
		$wpdb->update( WPSC_TABLE_PURCHASE_LOGS, array( 'processed' => 2, 'transactid' => $transaction_id, 'date' => time() ), array( 'sessionid' => $sessionid ), array( '%d', '%s' ) );
		} else {
					$data = array(
						'processed'  => 3,
						'transactid' => $transaction_id,
						'date'       => time(),
					);
					$where = array( 'sessionid' => $sessionid );
					$format = array( '%d', '%s', '%s' );
					$wpdb->update( WPSC_TABLE_PURCHASE_LOGS, $data, $where, $format );
					transaction_results($sessionid, false, $transaction_id);
		}

		// If in debug, email details
		if(get_option('tco_debug') == 1)
		{
			$message = "This is a debugging message sent because it appears that you are in debug mode.\n\rEnsure 2Checkout debug is turned off once you are happy with the function.\n\r\n\r";
			$message .= "OUR_POST:\n\r".print_r($header . $req,true)."\n\r\n\r";
			$message .= "THEIR_POST:\n\r".print_r($_POST,true)."\n\r\n\r";
			$message .= "GET:\n\r".print_r($_GET,true)."\n\r\n\r";
			$message .= "SERVER:\n\r".print_r($_SERVER,true)."\n\r\n\r";
			mail(get_option('purch_log_email'), "2Checkout Data", $message);
		}
	}
}

function nzshpcrt_tco_results()
{
	if(isset($_REQUEST['cart_order_id']) && ($_REQUEST['cart_order_id'] !='') && ($_GET['sessionid'] == ''))
	{
		$_GET['sessionid'] = $_REQUEST['cart_order_id'];
	}
}

function submit_tco()
{
	if(isset($_POST['tco_seller_id']))
    {
    	update_option('tco_seller_id', $_POST['tco_seller_id']);
    }

  	if(isset($_POST['tco_secret_word']))
    {
    	update_option('tco_secret_word', $_POST['tco_secret_word']);
    }

  	if(isset($_POST['tco_language']))
    {
    	update_option('tco_language', $_POST['tco_language']);
    }

  	if(isset($_POST['tco_debug']))
    {
    	update_option('tco_debug', $_POST['tco_debug']);
    }

    if (!isset($_POST['tco_form'])) $_POST['tco_form'] = array();
	foreach((array)$_POST['tco_form'] as $form => $value)
    {
    	update_option(('tco_form_'.$form), $value);
    }
	return true;
}

function form_tco()
{
	$select_currency[get_option('tco_curcode')] = "selected='selected'";
	$select_language[get_option('tco_language')] = "selected='selected'";

	$tco_debug = get_option('tco_debug');
	$tco_debug1 = "";
	$tco_debug2 = "";
	switch($tco_debug)
	{
		case 0:
			$tco_debug2 = "checked ='checked'";
			break;
		case 1:
			$tco_debug1 = "checked ='checked'";
			break;
	}

	if (!isset($select_currency['USD'])) $select_currency['USD'] = '';
	if (!isset($select_currency['EUR'])) $select_currency['EUR'] = '';


	if (!isset($select_language['en'])) $select_language['en'] = '';
	if (!isset($select_language['es_la'])) $select_language['es_la'] = '';
	if (!isset($select_language['nl'])) $select_language['nl'] = '';
	if (!isset($select_language['zh'])) $select_language['zh'] = '';
	if (!isset($select_language['da'])) $select_language['da'] = '';
	if (!isset($select_language['fr'])) $select_language['fr'] = '';
	if (!isset($select_language['gr'])) $select_language['gr'] = '';
	if (!isset($select_language['el'])) $select_language['el'] = '';
	if (!isset($select_language['it'])) $select_language['it'] = '';
	if (!isset($select_language['jp'])) $select_language['jp'] = '';
	if (!isset($select_language['no'])) $select_language['no'] = '';
	if (!isset($select_language['pt'])) $select_language['pt'] = '';
	if (!isset($select_language['es_ib'])) $select_language['es_ib'] = '';
	if (!isset($select_language['sv'])) $select_language['sv'] = '';

	$output = "
		<tr>
			<td>2Checkout Account Number</td>
			<td><input type='text' size='40' value='".get_option('tco_seller_id')."' name='tco_seller_id' /></td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td><small>2Checkout Account Number</small></td>
		</tr>
		<tr>
			<td>Secret Word</td>
			<td><input type='text' size='40' value='".get_option('tco_secret_word')."' name='tco_secret_word' /></td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td><small>Must me the same value you setup on the 2Checkout Site Management page.</small></td>
		</tr>
		<tr>
			<td>Language</td>
			<td><select name='tco_language'>
					<option ".$select_language['en']." value='en'>Engish</option>
					<option ".$select_language['es_la']." value='es_la'>Spanish</option>
					<option ".$select_language['nl']." value='nl'>Dutch</option>
					<option ".$select_language['zh']." value='zh'>Chinese</option> 
					<option ".$select_language['da']." value='da'>Danish</option> 
					<option ".$select_language['fr']." value='fr'>French</option> 
					<option ".$select_language['gr']." value='gr'>German</option> 
					<option ".$select_language['el']." value='el'>Greek</option> 
					<option ".$select_language['it']." value='it'>Italian</option> 
					<option ".$select_language['jp']." value='jp'>Japanese</option>
					<option ".$select_language['no']." value='no'>Norwegian</option>
					<option ".$select_language['pt']." value='pt'>Portuguese</option>
					<option ".$select_language['es_ib']." value='es_ib'>Spanish(Europe)</option>
					<option ".$select_language['sv']." value='sv'>Swedish</option>																																																																
				</select>
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td><small>The language that the 2Checkout purchase routine will be displayed in.</small></td>
		</tr>
		<tr>
			<td>Return URL</td>
			<td><input type='text' size='40' value='".get_option('transact_url')."' name='tco_return_url' /></td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td><small>If you are using a demo account, enter this URL in the Approved URL field on your 2Checkout Site Management page and append &tco_callback=true. (Example: http://yoursite.com?page=6&tco_callback=true) This page is the  transaction details page that you have configured in Shop Options.  It can not be edited on this page.</small></td>
		</tr>
		<tr>
			<td>Debug Mode</td>
			<td>
				<input type='radio' value='1' name='tco_debug' id='tco_debug1' ".$tco_debug1." /> <label for='tco_debug1'>".__('Yes', 'wpsc')."</label> &nbsp;
				<input type='radio' value='0' name='tco_debug' id='tco_debug2' ".$tco_debug2." /> <label for='tco_debug2'>".__('No', 'wpsc')."</label>
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td><small>Debug mode is used to write HTTP communications between the 2Checkout server and your host to a log file.  This should only be activated for testing!</small></td>
		</tr>


	<tr class='update_gateway' >
		<td colspan='2'>
			<div class='submit'>
			<input type='submit' value='".__('Update &raquo;', 'wpsc')."' name='updateoption'/>
		</div>
		</td>
	</tr>

	<tr class='firstrowth'>
		<td style='border-bottom: medium none;' colspan='2'>
			<strong class='form_group'>Forms Sent to Gateway</strong>
		</td>
	</tr>

		<tr>
			<td>First Name Field</td>
			<td><select name='tco_form[first_name]'>
				".nzshpcrt_form_field_list(get_option('tco_form_first_name'))."
				</select>
			</td>
		</tr>
		<tr>
			<td>Last Name Field</td>
			<td><select name='tco_form[last_name]'>
				".nzshpcrt_form_field_list(get_option('tco_form_last_name'))."
				</select>
			</td>
		</tr>
		<tr>
			<td>Address Field</td>
			<td><select name='tco_form[address]'>
				".nzshpcrt_form_field_list(get_option('tco_form_address'))."
				</select>
			</td>
		</tr>
		<tr>
			<td>City Field</td>
			<td><select name='tco_form[city]'>
				".nzshpcrt_form_field_list(get_option('tco_form_city'))."
				</select>
			</td>
		</tr>
		<tr>
			<td>State Field</td>
			<td><select name='tco_form[state]'>
				".nzshpcrt_form_field_list(get_option('tco_form_state'))."
				</select>
			</td>
		</tr>
		<tr>
			<td>Postal code</td>
			<td><select name='tco_form[post_code]'>
				".nzshpcrt_form_field_list(get_option('tco_form_post_code'))."
				</select>
			</td>
		</tr>
		<tr>
			<td>Country Field</td>
			<td><select name='tco_form[country]'>
				".nzshpcrt_form_field_list(get_option('tco_form_country'))."
				</select>
			</td>
		</tr>
		<tr>
			<td>Phone Field</td>
			<td><select name='tco_form[phone]'>
				".nzshpcrt_form_field_list(get_option('tco_form_phone'))."
				</select>
			</td>
		</tr>
		   <tr>
           <td colspan='2'>For more help configuring 2Checkout, contact techsupport@2co.com.</a></td>
       </tr>";

	return $output;
}


add_action('init', 'nzshpcrt_tco_callback');
add_action('init', 'nzshpcrt_tco_results');

?>
