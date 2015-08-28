<?php

class wpsc_merchant_twocheckout_hosted extends wpsc_merchant {

    public $name = '2Checkout';

    /**
     * construct value array method, converts the data gathered by the base class code to something acceptable to the gateway
     * @access public
     */
    function construct_value_array() {
        $this->collected_gateway_data = $this->_construct_value_array();
    }

    /**
     * construct value array method, converts the data gathered by the base class code to something acceptable to the gateway
     * @access private
     * @param boolean $aggregate Whether to aggregate the cart data or not. Defaults to false.
     * @return array $twocheckout_vars The 2checkout vars
     */
    function _construct_value_array( $aggregate = false ) {
        global $wpdb;

        $purchase_log = new WPSC_Purchase_Log( $this->cart_data['session_id'], 'sessionid' );

        // Collect the 2Checkout payload vars
        $twocheckout_vars = array(
            'sid'                           => get_option( 'twocheckout_hosted_seller_id' ),
            'purchase_step'                 => 'payment-method',
            'twocheckout_hosted_callback'   => "true",
            'lang'                          => get_option('twocheckout_hosted_language'),
            'x_receipt_link_url'            => get_option('transact_url'),
            'merchant_order_id'             => $this->cart_data['session_id'],
            'currency_code'                 => $this->cart_data['store_currency'],
            'total'                         => $this->cart_data['total_price'],
            'cart_order_id'                 => $purchase_log->get('id'),
            'email'                         => $this->cart_data['email_address'],
            'first_name'                    => $this->cart_data['billing_address']['first_name'],
            'last_name'                     => $this->cart_data['billing_address']['last_name'],
            'street_address'                => $this->cart_data['billing_address']['address'],
            'city'                          => $this->cart_data['billing_address']['city'],
            'country'                       => $this->cart_data['billing_address']['country'],
            'zip'                           => $this->cart_data['billing_address']['post_code'],
            'state'                         => $this->cart_data['billing_address']['state'],
            'phone'                         => $this->cart_data['billing_address']['phone'],
        );

        return apply_filters( 'wpsc_twocheckout_post_data', $twocheckout_vars );
    }

    /**
     * submit method, sends the received data to the payment gateway
     * @access public
     */
    function submit() {
        $mode = get_option( 'twocheckout_hosted_mode' );

        $submit_url = $mode == 'sandbox' ? 'https://sandbox.2checkout.com/checkout/purchase' : 'https://www.2checkout.com/checkout/purchase';

        if(WPSC_GATEWAY_DEBUG == true )
        {
            exit("<pre>".print_r($this->collected_gateway_data,true)."</pre>");
        }

        // Create Form to post to 2Checkout
        $output = '<form id="twocheckout_form" method="post" action="'.$submit_url.'">';
        foreach ($this->collected_gateway_data as $n => $v) {
            $output .= "<input type='hidden' name='$n' value='$v' />";
        }
        $output .= '<p><strong>Redirecting to 2Checkout for secure processing.';
        $output .= '<script>document.getElementById("twocheckout_form").submit();</script>';
        echo $output;
        exit();
    }
}

function twocheckout_hosted_callback()
{
    global $wpdb;

    if(isset($_REQUEST['twocheckout_hosted_callback']) && ($_REQUEST['twocheckout_hosted_callback'] == 'true'))
    {
        $valid = false;
        $sessionid = trim(stripslashes($_REQUEST['merchant_order_id']));
        $purchase_log = new WPSC_Purchase_Log( $sessionid, 'sessionid' );
        $cart_total = $purchase_log->get('totalprice');
        $seller_id = get_option('twocheckout_hosted_seller_id');
        $secret_word = get_option('twocheckout_hosted_secret_word');
        $transaction_id = trim(stripslashes($_REQUEST['order_number']));

        if ($purchase_log->exists()) {
            $compare_string = $secret_word . $seller_id . $transaction_id . $cart_total;
            $compare_hash1 = strtoupper(md5($compare_string));
            $compare_hash2 = $_REQUEST['key'];
            $valid = $compare_hash1 == $compare_hash2;
        }

        if ($valid) {
            wpsc_update_purchase_log_status( $sessionid, 3, 'sessionid' );
            wpsc_update_purchase_log_details( $purchase_log->get('id'), array( 'processed' => 3, 'transactid' => $transaction_id ) );
            transaction_results($sessionid, false, $transaction_id);
            $redirect = add_query_arg('sessionid', $sessionid, get_option('transact_url'));
            wp_redirect( $redirect );
            exit;
        } else {
            $wpdb->update( WPSC_TABLE_PURCHASE_LOGS, array( 'processed' => 2, 'transactid' => $transaction_id, 'date' => time() ), array( 'sessionid' => $sessionid ), array( '%d', '%s' ) );

        }
    }
}

function twocheckout_hosted_results()
{
    if(isset($_REQUEST['merchant_order_id']) && ($_REQUEST['merchant_order_id'] !='') && ($_GET['merchant_order_id'] == ''))
    {
        $_GET['sessionid'] = $_REQUEST['merchant_order_id'];
    }
}

function wpsc_twocheckout_settings_form() {
    global $wpsc_gateways;

    $select_language[get_option('twocheckout_hosted_language')] = "selected='selected'";
    $mode = get_option( 'twocheckout_hosted_mode' );

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
            <td><input type='text' size='40' value='".get_option('twocheckout_hosted_seller_id')."' name='twocheckout_hosted_seller_id' /></td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td><small>2Checkout Account Number</small></td>
        </tr>
        <tr>
            <td>Secret Word</td>
            <td><input type='text' size='40' value='".get_option('twocheckout_hosted_secret_word')."' name='twocheckout_hosted_secret_word' /></td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td><small>Must me the same value you setup on the 2Checkout Site Management page.</small></td>
        </tr>
        <tr>
            <td>Language</td>
            <td><select name='twocheckout_hosted_language'>
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
        </tr>";


    $store_currency_data = WPSC_Countries::get_currency_data( get_option( 'currency_type' ), true );
    $current_currency = get_option('twocheckout_hosted_curcode');
    $twocheckout_currencies = $wpsc_gateways['wpsc_twocheckout_hosted']['supported_currencies']['currency_list'];
    if ( ( $current_currency == '' ) && in_array( $store_currency_data['code'], $twocheckout_currencies ) ) {
        update_option( 'twocheckout_hosted_curcode', $store_currency_data['code'] );
        $current_currency = $store_currency_data['code'];
    }
;
    if ( $current_currency != $store_currency_data['code'] ) {
        $output .= "
        <tr>
            <td>
            </td>
            <td><strong class='form_group'>" . __( 'Currency Converter', 'wpsc' ) . "</td>
        </tr>
        <tr>
            <td>
            </td>
            <td>
            ".sprintf( __( 'Your website uses <strong>%s</strong>. This currency is not supported by 2Checkout, please select a currency using the drop down menu below.', 'wpsc' ), $store_currency_data['currency'] )."
            </td>
        </tr>

        <tr>
            <td>
                " . __( 'Select Currency:', 'wpsc' ) . "
            </td>
            <td>
                <select name='twocheckout_curcode'>\n";

        $currency_list = WPSC_Countries::get_currencies( true );

        foreach ( $currency_list as $currency_item ) {
            $selected_currency = '';
            if ( $current_currency == $currency_item['code'] ) {
                $selected_currency = "selected='selected'";
            }
            $output .= "<option " . $selected_currency . " value='{$currency_item['code']}'>{$currency_item['name']}</option>";
        }
        $output .= "
                </select>
            </td>
        </tr>";
    }

    $output .= "
        <tr>
            <td>Sandbox or Production?</td>
            <td>
                <select name='twocheckout_hosted_mode'>
                    <option value='sandbox' " . selected('sandbox', $mode, false) . ">Sandbox</option>
                    <option value='production' " . selected('production', $mode, false) . ">Production</option>
                </select>
            </td>
        </tr>
        <tr>
            <td>Return URL</td>
            <td><input type='text' size='40' value='".get_option('transact_url')."' name='twocheckout_hosted_return_url' /></td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td><small>If you are using a sandbox account, enter this URL in the Approved URL field on your 2Checkout Site Management page. This page is the transaction details page that you have configured in Shop Options.  It can not be edited on this page.</small></td>
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
            <td><select name='twocheckout_hosted_form[first_name]'>
                ".nzshpcrt_form_field_list(get_option('twocheckout_hosted_form_first_name'))."
                </select>
            </td>
        </tr>
        <tr>
            <td>Last Name Field</td>
            <td><select name='twocheckout_hosted_form[last_name]'>
                ".nzshpcrt_form_field_list(get_option('twocheckout_hosted_form_last_name'))."
                </select>
            </td>
        </tr>
        <tr>
            <td>Address Field</td>
            <td><select name='twocheckout_hosted_form[address]'>
                ".nzshpcrt_form_field_list(get_option('twocheckout_hosted_form_address'))."
                </select>
            </td>
        </tr>
        <tr>
            <td>City Field</td>
            <td><select name='twocheckout_hosted_form[city]'>
                ".nzshpcrt_form_field_list(get_option('wpsc_twocheckout_hosted_form_city'))."
                </select>
            </td>
        </tr>
        <tr>
            <td>State Field</td>
            <td><select name='twocheckout_hosted_form[state]'>
                ".nzshpcrt_form_field_list(get_option('twocheckout_hosted_form_state'))."
                </select>
            </td>
        </tr>
        <tr>
            <td>Postal code</td>
            <td><select name='twocheckout_hosted_form[post_code]'>
                ".nzshpcrt_form_field_list(get_option('twocheckout_hosted_form_post_code'))."
                </select>
            </td>
        </tr>
        <tr>
            <td>Country Field</td>
            <td><select name='twocheckout_hosted_form[country]'>
                ".nzshpcrt_form_field_list(get_option('twocheckout_hosted_form_country'))."
                </select>
            </td>
        </tr>
        <tr>
            <td>Phone Field</td>
            <td><select name='twocheckout_hosted_form[phone]'>
                ".nzshpcrt_form_field_list(get_option('twocheckout_hosted_form_phone'))."
                </select>
            </td>
        </tr>
        <tr>
           <td colspan='2'>For more help configuring 2Checkout, contact techsupport@2co.com.</a></td>
        </tr>";

    return $output;
}

function wpsc_save_twocheckout_settings() {

    $options = array(
        'twocheckout_hosted_curcode',
        'twocheckout_hosted_language',
        'twocheckout_hosted_seller_id',
        'twocheckout_hosted_secret_word',
        'twocheckout_hosted_mode'


    );

    foreach ( $options as $option ) {
        if ( ! empty( $_POST[ $option ] ) ) {
            update_option( $option, sanitize_text_field( $_POST[ $option ] ) );
        }
    }

    return true;
}