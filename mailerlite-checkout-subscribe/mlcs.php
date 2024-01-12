<?php
/*
* Plugin Name: MailerLite Checkout Subscribe
* Description: MailerLite WC checkout Subscribe
* Author: Borsányi István
* Author URI: https://github.com/Estalhun
* License: GPLv2 or later
* Version: 1.3 beta
* Requires PHP: 7.3
*/
namespace MailerLiteCheckoutSubscribe;

if (!defined('ABSPATH')) {
    exit;
}
class MailerLiteCheckoutSubscribe
{

    public function __construct()
    {
        add_action('admin_init', array($this, 'mlcs_register_plugin_setting_fields'));
        add_action('admin_notices', array($this, 'mlcs_admin_notices'));
        add_action('admin_menu', array($this, 'addSettingPane'));
        add_action('woocommerce_review_order_before_submit', array($this, 'wc_add_phone_call_subscription_checkbox'), 100);
        //add_action('woocommerce_after_checkout_validation', array($this, 'wc_save_phone_call_subscription_state'));
        add_action('woocommerce_checkout_create_order', array($this, 'wc_save_phone_call_subscription_state'));
        add_action('woocommerce_thankyou', array($this, 'subscribeCustomer'));
    }

    public function addSettingPane()
    {
        add_options_page(esc_html__('MailerLite Checkout Subscribe Settings', 'mailerlite-checkout-subscribe'), 'MailerLite Checkout Subscribe',
        'manage_options', 'mailerlite-checkout-subscribe', array($this, 'displaySettingPane'));
    }

    public function displaySettingPane()
    {
        ?>
        <div class="wrap">
            <h2>MailerLite Checkout Subscribe Settings</h2>
            <form action="options.php" method="post">
                <?php
                settings_fields('mlcs_settings');
                do_settings_sections(__FILE__);
                $options = get_option('mlcs_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('MailerLite API Token:', 'mailerlite-checkout-subscribe'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input name="mlcs_settings[mlcs_token]" type="text" id="mlcs_token"
                                            value="<?php echo (isset($options['mlcs_token']) && $options['mlcs_token'] != '') ? $options['mlcs_token'] : ''; ?>" /><br />
                                            <span class="description">
                                                <?php echo esc_html__('Please enter a valid MailerLite API Token', 'mailerlite-checkout-subscribe'); ?>
                                            </span>
                                    </label>
                                </fieldset>
                            </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('MailerLite Group Settings:', 'mailerlite-checkout-subscribe'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input name="mlcs_settings[mlcs_list]" type="text" id="mlcs_list"
                                            value="<?php echo (isset($options['mlcs_list']) && $options['mlcs_list'] != '') ? $options['mlcs_list'] : ''; ?>" />
                                        <input name="mlcs_settings[mlcs_list_active]" type="checkbox" value="1" id="mlcs_list_active"
                                            <?php echo checked(1, isset($options['mlcs_list_active']) ? $options['mlcs_list_active'] : '', true); ?> />&nbsp;
                                            <?php echo esc_html__('Enabled', 'mailerlite-checkout-subscribe'); ?><br />
                                        <span class="description">
                                            <?php echo esc_html__('Please enter a valid MailerLite subscriber list ID', 'mailerlite-checkout-subscribe'); ?>
                                        </span>
                                    </label>
                                </fieldset>
                            </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Additional field #1:', 'mailerlite-checkout-subscribe'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input name="mlcs_settings[mlcs_key1]" type="text" id="mlcs_key1"
                                            value="<?php echo (isset($options['mlcs_key1']) && $options['mlcs_key1'] != '') ? $options['mlcs_key1'] : ''; ?>" />
                                            <input name="mlcs_settings[mlcs_val1]" type="text" id="mlcs_val1"
                                            value="<?php echo (isset($options['mlcs_val1']) && $options['mlcs_val1'] != '') ? $options['mlcs_val1'] : ''; ?>" /><br />
                                        <span class="description">
                                            <?php echo esc_html__('Please enter a valid MailerLite field ID and a field value', 'mailerlite-checkout-subscribe'); ?>
                                        </span>
                                    </label>
                                </fieldset>
                            </td>
                    </tr>
                    <!-- subscribe to follow-up email -->
                    <tr>
                        <th scope="row"><?php echo esc_html__('MailerLite Follow-Up Email Group:', 'mailerlite-checkout-subscribe'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input name="mlcs_settings[mlcs_follow_up_list]" type="text" id="mlcs_follow_up_list"
                                        value="<?php echo (isset($options['mlcs_follow_up_list']) && $options['mlcs_follow_up_list'] != '') ? $options['mlcs_follow_up_list'] : ''; ?>" />
                                    <input name="mlcs_settings[mlcs_follow_up_list_active]" type="checkbox" value="1" id="mlcs_follow_up_list_active"
                                        <?php echo checked(1, isset($options['mlcs_follow_up_list_active']) ? $options['mlcs_follow_up_list_active'] : '', true); ?> />&nbsp;
                                        <?php echo esc_html__('Enabled', 'mailerlite-checkout-subscribe'); ?><br />
                                    <span class="description">
                                        <?php echo esc_html__('Please enter a valid MailerLite subscriber list ID', 'mailerlite-checkout-subscribe'); ?>
                                    </span>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                        <input type="submit" value="<?php echo esc_html__('Save Changes', 'mailerlite-checkout-subscribe'); ?>" />
            </form>
        </div>
        <?php
    }

    public function mlcs_register_plugin_setting_fields()
    {
        register_setting('mlcs_settings', 'mlcs_settings', 'mlcs_settings_validate');
    }

    public function mlcs_settings_validate($args)
    {
        $err = true;
        if ( !isset($args['mlcs_token']) || empty(trim($args['mlcs_token'])) ) {
            $args['mlcs_token'] = '';
            add_settings_error('mlcs_settings', 'mlcs_invalid_token', esc_html__('Please enter a valid token!', 'mailerlite-checkout-subscribe'), $type = 'error');
        }
        if (isset($args['mlcs_list_active']) && (!isset($args['mlcs_list']) || empty(trim($args)['mlcs_list']) ) ) {
            $args['mlcs_list'] = '';
            $args['mlcs_list_active'] = '';
            add_settings_error('mlcs_settings', 'mlcs_invalid_list', esc_html__('Please enter a valid subscriber list!', 'mailerlite-checkout-subscribe'), $type = 'error');
        }
        if ((!isset($args['mlcs_key1']) || empty(trim($args)['mlcs_key1'])) && (!isset($args['mlcs_val1']) || empty(trim($args)['mlcs_val1'])) ) {
            $args['mlcs_key1'] = '';
            $args['mlcs_val1'] = '';
            $err = false;
        }
        if ((isset($args['mlcs_key1']) || !empty(trim($args)['mlcs_key1'])) && (isset($args['mlcs_val1']) || !empty(trim($args)['mlcs_val1']))) {
            $err = false;
        }

        if ($err) {
            add_settings_error('mlcs_settings', 'mlcs_key_value_pair', esc_html__('Please enter valid Key & Value pair!', 'mailerlite-checkout-subscribe'), $type = 'error');
        }

        if ((!isset($args['mlcs_key1']) || empty(trim($args)['mlcs_key1'])) && (!isset($args['mlcs_val1']) || empty(trim($args)['mlcs_val1']))) {
            $args['mlcs_key1'] = '';
            $args['mlcs_val1'] = '';
            $err = false;
        }
        if ((isset($args['mlcs_key1']) || !empty(trim($args)['mlcs_key1'])) && (isset($args['mlcs_val1']) || !empty(trim($args)['mlcs_val1']))) {
            $err = false;
        }

        if ($err) {
            add_settings_error('mlcs_settings', 'mlcs_key_value_pair', esc_html__('Please enter valid Key & Value pair!', 'mailerlite-checkout-subscribe'), $type = 'error');
        }
        //error_log('ARGS: ' . json_encode($args)); //debug
        return $args;
    }

    /*
    * Admin notices
    */
    public function mlcs_admin_notices()
    {
        settings_errors(); //Display the validation errors and update messages
    }
    public function subscribeCustomer($order_id)
    {
        $mlcs_subscribed = get_post_meta( $order_id, 'mlcs_subscibed', true );

        /*
        if (!current_user_can('administrator')) { //DEBUG
            return;
        }
        error_log('ML1: ' . $mlcs_subscribed);
        */

        $err = false;
        $order = wc_get_order( $order_id );
        $customer_email = $order->get_billing_email();
        $customer_name = $order->get_billing_first_name();
        $customer_last_name = $order->get_billing_last_name();
        $customer_phone = $order->get_billing_phone();

        $api_key = get_option('mlcs_settings')['mlcs_token'];
        $list_id = get_option('mlcs_settings')['mlcs_list'];
        $list_active = get_option('mlcs_settings')['mlcs_list_active'];
        $key1 = get_option('mlcs_settings')['mlcs_key1'];
        $val1 = get_option('mlcs_settings')['mlcs_val1'];
        $follow_up_list_id = get_option('mlcs_settings')['mlcs_follow_up_list'];
        $follow_up_list_active = get_option('mlcs_settings')['mlcs_follow_up_list_active'];

        if (isset($mlcs_subscribed) && $mlcs_subscribed == 1) {
            // Set the request body
            $body = array(
                'email' => $customer_email,
                'fields' => array(
                    'first_name' => $customer_name,
                    'last_name' => $customer_last_name,
                    'subscriber_language' => determine_locale(),
                    'phone' => $customer_phone,
                    $key1 => $val1,                 //'marketing_preferences' => 'Telemarketing',
                ),
            );

            // Construct the URL
            $url = 'https://api.mailerlite.com/api/v2/groups/' . $list_id . '/subscribers';

            // Set the request headers
            $headers = array(
                'Content-Type' => 'application/json',
                'X-MailerLite-ApiKey' => $api_key
            );

            // Send the request
            $response = wp_remote_post($url, array(
                'headers' => $headers,
                'body' => json_encode($body)
            )
            );

            if (wp_remote_retrieve_response_code($response) !== 200) {
                $err = true;
            }
        }
        
        //follow-up email
        if ($follow_up_list_active && !$err) {
            // Set the request body
            if (isset($mlcs_subscribed) && $mlcs_subscribed == 1){ // already subscribed to telemarketing
                $body = array(
                    'email' => $customer_email,
                    'fields' => array(
                        'subscriber_language' => determine_locale(),
                    ),
                );    
            } else {
                $body = array(
                    'email' => $customer_email,
                    'fields' => array(
                        'first_name' => $customer_name,
                        'last_name' => $customer_last_name,
                        'subscriber_language' => determine_locale(),
                        'phone' => $customer_phone,
                        $key1 => $val1,                 //'marketing_preferences' => 'Telemarketing',
                    ),
                );
            }
            // Construct the URL
            $url = 'https://api.mailerlite.com/api/v2/groups/' . $follow_up_list_id . '/subscribers';

            // Set the request headers
            $headers = array(
                'Content-Type' => 'application/json',
                'X-MailerLite-ApiKey' => $api_key
            );

            // Send the request
            $response = wp_remote_post($url, array(
                'headers' => $headers,
                'body' => json_encode($body)
            ));

            if (wp_remote_retrieve_response_code($response) !== 200) {
                $err = true;
            }   
        }

        // Check the response status code
        if (!$err) {
            echo esc_html__('Customer subscribed successfully.', 'mailerlite-checkout-subscribe');
            //error_log('SUCCESS: ' . json_encode($body)); //debug
        } else {
            echo esc_html__('Error subscribing customer: ', 'mailerlite-checkout-subscribe') . wp_remote_retrieve_response_message($response);
            //error_log('FALSE: ' . json_encode($body)); //debug
        }
    }
    public function wc_add_phone_call_subscription_checkbox()
    {
        /*
        if (!current_user_can('administrator')) { //debug
            return;
        }
        */
        $list_active = get_option('mlcs_settings')['mlcs_list_active'];
        if(isset($list_active) && $list_active)
        echo '<p><span class="woocommerce-input-wrapper"><label class="checkbox"><input type="checkbox" id="phone_call_subscription"
         class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="phone_call_subscription">' .
            esc_html__('Would you like to receive a phone call from our customer support team?', 'mailerlite-checkout-subscribe') . '</label></span></p>';
    }
    public function wc_save_phone_call_subscription_state($order)
    {
        $checkbox = isset($_POST['phone_call_subscription']) ? 1 : 0;
        if (is_object($order)) {
            $order->update_meta_data('mlcs_subscibed', $checkbox);
            $order->save();
        }
    }
}

$MailerLiteCheckoutSubscribe = new MailerLiteCheckoutSubscribe();

?>