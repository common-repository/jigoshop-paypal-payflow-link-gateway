<?php
/*
  Plugin Name: Jigoshop PayPal Payflow Link Payment Gateway
  Plugin URI: 
  Description: Allows you to use <a href="https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/howto_gateway_payflow_link">PayPal's Payflow Link</a> payment gateway with the Jigoshop ecommerce plugin.
  Version: 0.9
  Author: Mike Osterhout
  Author URI: http://ostedesign.com/
 */


/*

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

 */


/* Add a custom payment class to Jigoshop
  ------------------------------------------------------------ */

add_action('plugins_loaded', 'jigoshop_paypal_payflowlink_payment_gateway', 0);

function jigoshop_paypal_payflowlink_payment_gateway() {

	if (!class_exists('jigoshop_payment_gateway'))
		return; // if the Jigoshop payment gateway class is not available, do nothing

	class paypal_payflowlink extends jigoshop_payment_gateway {

		public function __construct() {

			$this->id = 'paypal_payflowlink';
			$this->icon = jigoshop::plugin_url() . '/assets/images/icons/paypal.png';
			$this->has_fields = false;

			$this->enabled = get_option('jigoshop_paypal_payflowlink_enabled');
			$this->title = get_option('jigoshop_paypal_payflowlink_title');
			$this->description = get_option('jigoshop_paypal_payflowlink_description');

			add_action('init', array(&$this, 'check_paypal_payflowlink_response'));

			add_action('jigoshop_update_options', array(&$this, 'process_admin_options'));
			add_option('jigoshop_paypal_payflowlink_enabled', 'no');
			add_option('jigoshop_paypal_payflowlink_title', __('PayPal Payflow Link', 'jigoshop'));
			add_option('jigoshop_paypal_payflowlink_description', __("Pay via credit card via a secure PayPal payment page", 'jigoshop'));
			
			add_option('jigoshop_paypal_payflowlink_login', '');

			add_action('receipt_paypal_payflowlink', array(&$this, 'receipt_page'));
		}

		/* Construct our function to output and display our gateway
		  ------------------------------------------------------------ */

		public function admin_options() {
			?>
			<thead>
				<tr>
					<th scope="col" width="200px"><?php _e('PayPal Payflow Link', 'jigoshop')?></th>
					<th scope="col" class="desc"><?php  _e('This payment gateway is setup specifically for PayPal Payflow Link. Learn more <a href="https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/howto_gateway_payflow_link">here</a>', 'jigoshop'); ?></th>
				</tr>
			</thead>
			
			<tr>
				<td class="titledesc"><?php _e('Enable PayPal Payflow Link', 'jigoshop') ?>:</td>
				<td class="forminp">
					<select name="jigoshop_paypal_payflowlink_enabled" id="jigoshop_paypal_payflowlink_enabled" style="min-width:100px;">
						<option value="yes" <?php if (get_option('jigoshop_paypal_payflowlink_enabled') == 'yes') echo 'selected="selected"'; ?>><?php _e('Yes', 'jigoshop'); ?></option>
						<option value="no" <?php if (get_option('jigoshop_paypal_payflowlink_enabled') == 'no') echo 'selected="selected"'; ?>><?php _e('No', 'jigoshop'); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="titledesc">
					<a href="#" tip="<?php _e('This controls the title which the user sees during checkout.','jigoshop'); ?>" class="tips" tabindex="99"></a><?php _e('Method Title', 'jigoshop') ?>:
				</td>
				<td class="forminp">
					<input class="input-text wide-input" type="text" name="jigoshop_paypal_payflowlink_title" id="jigoshop_paypal_payflowlink_title" style="min-width:50px;" value="<?php if ($value = get_option('jigoshop_paypal_payflowlink_title')) echo $value; ?>" />
				</td>
			</tr>
			<tr>
				<td class="titledesc">
					<a href="#" tip="<?php _e('This controls the description which the user sees during checkout.','jigoshop') ?>" class="tips" tabindex="99"></a><?php _e('Description', 'jigoshop') ?>:
				</td>
				<td class="forminp">
					<input class="input-text wide-input" type="text" name="jigoshop_paypal_payflowlink_description" id="jigoshop_paypal_payflowlink_description" style="min-width:50px;" value="<?php if ($value = get_option('jigoshop_paypal_payflowlink_description')) echo $value; ?>" />
				</td>
			</tr>
			<tr>
				<td class="titledesc">
					<a href="#" tip="<?php _e('This will be the value of the LOGIN field on the payment form','jigoshop') ?>" class="tips" tabindex="99"></a><?php _e('Login', 'jigoshop') ?>:
				</td>
				<td class="forminp">
					<input class="input-text wide-input" type="text" name="jigoshop_paypal_payflowlink_login" id="jigoshop_paypal_payflowlink_login" style="min-width:50px;" value="<?php if ($value = get_option('jigoshop_paypal_payflowlink_login')) echo $value; ?>" />
				</td>
			</tr>
			<tr>
				<td class="titledesc">
					<a href="#" tip="<?php _e('This will be the value of the PARTNER field on the payment form','jigoshop') ?>" class="tips" tabindex="99"></a><?php _e('Partner', 'jigoshop') ?>:
				</td>
				<td class="forminp">
					<input class="input-text wide-input" type="text" name="jigoshop_paypal_payflowlink_partner" id="jigoshop_paypal_payflowlink_partner" style="min-width:50px;" value="<?php if ($value = get_option('jigoshop_paypal_payflowlink_partner')) echo $value; ?>" />
				</td>
			</tr>

			<?php
		}
		
		/**
		 * There are no payment fields for paypal, but we want to show the description if set.
		 * */
		function payment_fields() {
			if ($jigoshop_paypal_payflowlink_description = get_option('jigoshop_paypal_payflowlink_description'))
				echo wpautop(wptexturize($jigoshop_paypal_payflowlink_description));
		}

		/* Update options in the database upon save
		  ------------------------------------------------------------ */

		public function process_admin_options() {
			
			if (isset($_POST['jigoshop_paypal_payflowlink_enabled']))
				update_option('jigoshop_paypal_payflowlink_enabled', jigowatt_clean($_POST['jigoshop_paypal_payflowlink_enabled'])); 
			else
				@delete_option('jigoshop_paypal_payflowlink_enabled');
			
			if (isset($_POST['jigoshop_paypal_payflowlink_title']))
				update_option('jigoshop_paypal_payflowlink_title', jigowatt_clean($_POST['jigoshop_paypal_payflowlink_title'])); 
			else
				@delete_option('jigoshop_paypal_payflowlink_title');
			
			if (isset($_POST['jigoshop_paypal_payflowlink_description']))
				update_option('jigoshop_paypal_payflowlink_description', jigowatt_clean($_POST['jigoshop_paypal_payflowlink_description'])); 
			else
				@delete_option('jigoshop_paypal_payflowlink_description');
			
			if (isset($_POST['jigoshop_paypal_payflowlink_login']))
				update_option('jigoshop_paypal_payflowlink_login', jigowatt_clean($_POST['jigoshop_paypal_payflowlink_login'])); 
			else
				@delete_option('jigoshop_paypal_payflowlink_login');
			
			if (isset($_POST['jigoshop_paypal_payflowlink_partner']))
				update_option('jigoshop_paypal_payflowlink_partner', jigowatt_clean($_POST['jigoshop_paypal_payflowlink_partner'])); 
			else
				@delete_option('jigoshop_paypal_payflowlink_partner');
		}

		/* Process order 
		  ------------------------------------------------------------ */

		function process_payment($order_id) {

			$order = &new jigoshop_order($order_id);

			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('jigoshop_pay_page_id'))))
			);
		}

		function receipt_page($order) {

			echo '<p>' . __('Thank you for your order, please click the button below to pay with PayPal.', 'jigoshop') . '</p>';

			$order = &new jigoshop_order($order);

			?>
			
			<form method="POST" action="https://payflowlink.paypal.com">
				<input type="hidden" name="USER1" value="<?php echo $order->__get('id'); ?>"></input>
				<input type="hidden" name="USER2" value="<?php echo $order->__get('order_key'); ?>"></input>
				<input type="hidden" name="LOGIN" value="<?php echo get_option('jigoshop_paypal_payflowlink_login'); ?>"></input>
				<input type="hidden" name="PARTNER" value="<?php echo get_option('jigoshop_paypal_payflowlink_partner'); ?>"></input>
				<input type="hidden" name="AMOUNT" value="<?php echo $order->order_total ?>"></input>
				<input type="hidden" name="TYPE" value="S"></input>
				<input type="submit"></input>
			</form>
			
			<?php
			echo "<pre>";
			print_r($order);
			echo "</pre>";
		}

		function check_paypal_payflowlink_response() {

			if (isset($_GET['paypalListener']) && $_GET['paypalListener'] == 'paypal_payflowlink'):

				if ($_POST['RESULT'] === '0') {

					mail('mosterhout@ur.rochester.edu', 'approved', print_r($_POST, true));

					$order = new jigoshop_order((int) $_POST['USER1']);

					if ($order->order_key !== $_POST['USER2'])
						exit;

					$order->add_order_note(__('IPN payment completed', 'jigoshop'));
					$order->payment_complete();
				}else {

					mail('mosterhout@ur.rochester.edu', 'something other than approved', print_r($_POST, true));
				}

			endif;
		}

	}

	/* Add our new payment gateway to the Jigoshop gateways 
	  ------------------------------------------------------------ */

	add_filter('jigoshop_payment_gateways', 'add_paypal_payflowlink_gateway');

	function add_paypal_payflowlink_gateway( $methods ) {
		$methods[] = 'paypal_payflowlink'; 
		return $methods;
	}

}