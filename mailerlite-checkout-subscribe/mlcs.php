<?php
/*
* Plugin Name: MailerLite Checkout Subscribe
* Description: Use this plugin together with MailerLite - WooCommerce integration. Functions: Sign up at registration, follow-up email subscription, custom fields.
* Author: Borsányi István
* Author URI: https://github.com/Estalhun
* License: GPLv2 or later
* Version: 1.6.10
* Requires PHP: 7.4
* Requires at least: 5.6
* Text Domain: mailerlite-checkout-subscribe
* WC tested up to: 8.5.1
* WC requires at least: 5.6
*/

namespace MailerLiteCheckoutSubscribe;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MLCS_PLUGIN')) {
    define( 'MLCS_PLUGIN', __FILE__ );
}

if (!defined('MLCS_PLUGIN_PATH')) {
    define('MLCS_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

class MailerLiteCheckoutSubscribe
{
    public $options;
    public function __construct()
    {
        add_action('plugin_loaded', array($this, 'initialize'));
        add_action('plugins_loaded', array($this, 'mlcs_load_textdomain')); // or init hook?
        add_action('admin_init', array($this, 'mlcs_register_plugin_setting_fields'));
        add_action('admin_notices', array($this, 'mlcs_admin_notices'));
        add_action('admin_menu', array($this, 'mlcs_addSettingPane'));
        add_action('woocommerce_review_order_before_submit', array($this, 'mlcs_wc_add_phone_call_subscription_checkbox_checkout'), 100);
        add_action('woocommerce_checkout_create_order', array($this, 'mlcs_wc_save_phone_call_subscription_state'));
        add_action('woocommerce_after_save_address_validation', array($this, 'mlcs_validate_phone')); // /my-account/edit-address/ page
        add_action('woocommerce_customer_save_address', array($this, 'mlcs_validate_phone'));
        add_action('woocommerce_register_form', array($this, 'mlcs_wc_add_newsletter_checkbox_registration'), 5);
        add_action('woocommerce_register_form', array($this, 'mlcs_wc_add_phone_call_checkbox_registration'), 6);
        add_action('woocommerce_created_customer', array($this, 'mlcs_subscribe_user_wc_registration'));
        add_action('woocommerce_before_checkout_form', array($this, 'mlcs_check_customer_already_subscribed_checkout')); //wp_enqueue_scripts
        add_action('wp_ajax_mlcs_email_check', array($this, 'mlcs_email_check')); //AJAX hook
        add_action('wp_ajax_mlcs_phone_check', array($this, 'mlcs_phone_check')); //AJAX hook
        add_action('woocommerce_thankyou', array($this, 'mlcs_subscribe_customer_after_order'), 10); // $order->get_id() 
        add_action('woocommerce_thankyou', array($this, 'mlcs_dequeue_scripts'), 11); //woocommerce_checkout_update_user_meta $user_id
        add_action('woocommerce_checkout_update_user_meta', array($this, 'mlcs_update_user_meta'));
        //@todo: add settings link to plugin
        //add_filter('plugin_action_links', array($this, 'mlcs_plugin_settings_link', ), 10, 2 );
        //@todo: unsubscribe button to my-account
    }

    public function my_log($mytxt) //@TODO: debug
    {
        if (defined('WC_VERSION')) {
            $logger = wc_get_logger();
            $logger->info('MYLOG:' . json_encode($mytxt) );
        }
    }

    public function initialize()
    {   
        $this->options = get_option('mlcs_settings');
    }

    public function mlcs_load_textdomain()
    {
        $lang_dir = MLCS_PLUGIN_PATH . '/languages/';
        $locale = apply_filters('plugin_locale', get_locale(), 'mailerlite-checkout-subscribe');
        $mofile = sprintf('%1$s-%2$s.mo', 'mailerlite-checkout-subscribe', $locale);
        $mofile_local = $lang_dir . $mofile;
        $mofile_global = WP_LANG_DIR . '/mailerlite-checkout-subscribe/' . $mofile;

        if (file_exists($mofile_global)) {
            load_textdomain('mailerlite-checkout-subscribe', $mofile_global);
        } elseif (file_exists($mofile_local)) {
            load_textdomain('mailerlite-checkout-subscribe', $mofile_local);
        } else {
            load_plugin_textdomain('mailerlite-checkout-subscribe', false, $lang_dir);
        }
    }

    /*
    public function mlcs_plugin_settings_link( $plugin_actions, $plugin_file ) {

        $new_actions = array();
        if ( basename( plugin_dir_path( __FILE__ ) ) . 'mlcs.php' === $plugin_file ) {
            $new_actions['mlcs_settings'] = sprintf( '<a href="%s">' . __('Settings', 'mailerlite-checkout-subscribe') . '</a>', esc_url( admin_url( 'options-general.php?page=mailerlite-checkout-subscribe' ) ) );
        }
        return array_merge( $new_actions, $plugin_actions );
    }
    */
    public function mlcs_addSettingPane()
    {
        add_options_page(esc_html__('MailerLite Checkout Subscribe Settings', 'mailerlite-checkout-subscribe'), 'MailerLite Checkout Subscribe',
        'manage_options', 'mailerlite-checkout-subscribe', array($this, 'mlcs_displaySettingPane'));
    }

    public function mlcs_displaySettingPane()
    {
        ?>
        <div class="wrap">
            <h2>MailerLite Checkout Subscribe Settings</h2>
            <form action="options.php" method="post">
                <?php
                settings_fields('mlcs_settings');
                do_settings_sections(__FILE__); ?>
                <table class="form-table">
                   <tr>
                        <th scope="row">
                            <?php _e('MailerLite API Token:', 'mailerlite-checkout-subscribe'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <input name="mlcs_settings[mlcs_token]" type="text" id="mlcs_token"
                                    value="<?php echo (isset($this->options['mlcs_token']) && $this->options['mlcs_token'] != '') ?
                                        $this->options['mlcs_token'] : ''; ?>" /><br/>
                                <label for="mlcs_token"><?php _e('Please enter a valid MailerLite API Token', 'mailerlite-checkout-subscribe'); ?></label>
                            </fieldset>
                        </td>
                    </tr>
                    <!-- newsletter -->
                    <tr>
                        <th scope="row">
                            <?php _e('MailerLite Newsletter Group Settings:', 'mailerlite-checkout-subscribe'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <input name="mlcs_settings[mlcs_newsletter_list]" type="text" id="mlcs_newsletter_list"
                                    value="<?php echo (isset($this->options['mlcs_newsletter_list']) && $this->options['mlcs_newsletter_list'] != '') ?
                                        $this->options['mlcs_newsletter_list'] : ''; ?>" />
                                <input name="mlcs_settings[mlcs_newsletter_list_active]" type="checkbox" value="1" id="mlcs_newsletter_list_active"
                                    <?php checked(1, isset($this->options['mlcs_newsletter_list_active']) ? $this->options['mlcs_newsletter_list_active'] : '', true); ?> />&nbsp;
                                    <?php _e('Enabled', 'mailerlite-checkout-subscribe'); ?><br />
                                <label for="mlcs_newsletter_list">
                                    <?php _e('Please enter a valid MailerLite subscriber list ID for subscribers on registration form.', 'mailerlite-checkout-subscribe'); ?><br/>
                                    <?php _e('This should be the default MailerLite newsletter group.', 'mailerlite-checkout-subscribe'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <!-- telemarketing -->
                    <tr>
                        <th scope="row">
                            <?php _e('MailerLite Telemarketing Group Settings:', 'mailerlite-checkout-subscribe'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <input name="mlcs_settings[mlcs_telemarketing_list]" type="text" id="mlcs_telemarketing_list"
                                    value="<?php echo (isset($this->options['mlcs_telemarketing_list']) && $this->options['mlcs_telemarketing_list'] != '') ?
                                        $this->options['mlcs_telemarketing_list'] : ''; ?>" />
                                <input name="mlcs_settings[mlcs_telemarketing_list_active]" type="checkbox" value="1" id="mlcs_telemarketing_list_active"
                                    <?php checked(1, isset($this->options['mlcs_telemarketing_list_active']) ? $this->options['mlcs_telemarketing_list_active'] : '', true); ?> />&nbsp;
                                    <?php _e('Enabled', 'mailerlite-checkout-subscribe'); ?><br />
                                <label for="mlcs_telemarketing_list">
                                    <?php _e('Please enter a valid MailerLite subscriber list ID, which will be used for subscribers on the registration form or at checkout.',
                                            'mailerlite-checkout-subscribe'); ?><br/>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Additional field #1:', 'mailerlite-checkout-subscribe'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <input name="mlcs_settings[mlcs_key1]" type="text" id="mlcs_key1"
                                    value="<?php echo (isset($this->options['mlcs_key1']) && $this->options['mlcs_key1'] != '') ?
                                        $this->options['mlcs_key1'] : ''; ?>" />
                                <input name="mlcs_settings[mlcs_val1]" type="text" id="mlcs_val1"
                                    value="<?php echo (isset($this->options['mlcs_val1']) && $this->options['mlcs_val1'] != '') ?
                                        $this->options['mlcs_val1'] : ''; ?>" /><br />
                                <label for="mlcs_key1"><?php _e('Please enter a valid MailerLite field ID and a field value', 'mailerlite-checkout-subscribe'); ?></label>
                            </fieldset>
                        </td>
                    </tr>
                    <!-- subscribe to follow-up email -->
                    <tr>
                        <th scope="row">
                            <?php _e('MailerLite Follow-Up Email Group:', 'mailerlite-checkout-subscribe'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <input name="mlcs_settings[mlcs_follow_up_list]" type="text" id="mlcs_follow_up_list"
                                    value="<?php echo (isset($this->options['mlcs_follow_up_list']) && $this->options['mlcs_follow_up_list'] != '') ?
                                        $this->options['mlcs_follow_up_list'] : ''; ?>" />
                                <input name="mlcs_settings[mlcs_follow_up_list_active]" type="checkbox" value="1" id="mlcs_follow_up_list_active"
                                    <?php checked(1, isset($this->options['mlcs_follow_up_list_active']) ?
                                        $this->options['mlcs_follow_up_list_active'] : '', true); ?> />&nbsp;
                                    <?php _e('Enabled', 'mailerlite-checkout-subscribe'); ?><br />
                                <label for="mlcs_follow_up_list"><?php _e('Please enter a valid MailerLite subscriber list ID', 'mailerlite-checkout-subscribe'); ?></label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                <input class="button-primary woocommerce-save-button" type="submit" value="<?php _e('Save Changes', 'mailerlite-checkout-subscribe'); ?>" />
            </form>
        </div>
        <?php
        $this->options = get_option('mlcs_settings');
    }

    public function mlcs_register_plugin_setting_fields()
    {
        register_setting('mlcs_settings', 'mlcs_settings', ['mlcs_settings_validate']);
    }

    public function mlcs_settings_validate($args)
    {
        $err = true;
        if ( !isset($args['mlcs_token']) || empty(trim($args['mlcs_token'])) ) {
            $args['mlcs_token'] = '';
            add_settings_error('mlcs_settings', 'mlcs_invalid_token', esc_html__('Please enter a valid token!', 'mailerlite-checkout-subscribe'), $type = 'error');
        }
        if (isset($args['mlcs_newsletter_list_active']) && (!isset($args['mlcs_newsletter_list']) || empty(trim($args)['mlcs_newsletter_list']))) {
            $args['mlcs_newsletter_list'] = '';
            $args['mlcs_newsletter_list_active'] = '';
            add_settings_error('mlcs_settings', 'mlcs_invalid_list', esc_html__('Please enter a valid subscriber list!', 'mailerlite-checkout-subscribe'), $type = 'error');
        }
        if (isset($args['mlcs_telemarketing_list_active']) && (!isset($args['mlcs_telemarketing_list']) || empty(trim($args)['mlcs_telemarketing_list']) ) ) {
            $args['mlcs_telemarketing_list'] = '';
            $args['mlcs_telemarketing_list_active'] = '';
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
        return $args;
    }

    /*
    * Admin notices
    */
    public function mlcs_admin_notices()
    {
        settings_errors(); //Display the validation errors and update messages
    }
    public function mlcs_email_check() //if the customer subscribed on registration page
    {
        check_ajax_referer( 'mlcs-email-nonce', 'nonce' );  // Check the nonce, stop early when the nonce invalid.
        if (is_user_logged_in() ) { //is changed the customer's billing_email? The customer has the choice to subscribe the new email addrress or not.
            //setcookie('mlcs_billing_email', wp_get_current_user()->user_email, time() + 5 * MINUTE_IN_SECONDS, '/'); //@TODO: debug
            wp_send_json_success( array(
                'subscribed' => get_user_meta(wp_get_current_user()->ID, 'mlcs_newsletter_subscibed', true),
                'email' => wp_get_current_user()->user_email,
            ));
        }
        wp_die(); //terminate the AJAX request !!
    }
    public function mlcs_phone_check() //if the customer subscribed on registration page
    {
        check_ajax_referer( 'mlcs-phone-nonce', 'nonce' );  // Check the nonce, stop early when the nonce invalid.
        if (is_user_logged_in()) { //is changed the customer's billing_phone? The customer has the choice to subscribe the new phone number or not.
            $mlcs_subscribed = get_user_meta(get_current_user_id(), 'mlcs_telemarketing_subscibed', true);
            if ($mlcs_subscribed) {
                //setcookie('mlcs_billing_phone', 'SUBSCRIBED', time() + 5 * MINUTE_IN_SECONDS, '/'); //@TODO: debug
                echo 'SUBSCRIBED';
            }
        }
        wp_die(); //terminate the AJAX request !!
    }
    public function mlcs_check_customer_already_subscribed_checkout() {
        $list_active = array_key_exists('mlcs_newsletter_list_active', $this->options) ? $this->options['mlcs_newsletter_list_active'] : false; //if the subscription is enabled on register page
        if ($list_active) {
            $woo_mailerlite_checkout = get_option('woocommerce_mailerlite_settings');

            if ( class_exists('Woo_Mailerlite') && isset($woo_mailerlite_checkout['checkout']) &&
                strtolower($woo_mailerlite_checkout['checkout']) == 'yes' ) { //MailerLite - WooCommerce integration plugin checkbox ON
                wp_enqueue_script('mlcs_mailerlite_checkout_billing_email_script', plugins_url('js/mailerlite_checkout_billing_email.js', MLCS_PLUGIN), array('jquery'), '1.9');
                wp_localize_script('mlcs_mailerlite_checkout_billing_email_script', 'mlcs_mailerlite_checkout_billing_email_script',
                    array(
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'language' => get_locale(),
                        'nonce' => wp_create_nonce('mlcs-email-nonce'),
                    )
                );
            }
        }        

        $list_active = array_key_exists('mlcs_telemarketing_list_active', $this->options) ? $this->options['mlcs_telemarketing_list_active'] : false; //if the subscription is enabled on register page
        if ($list_active) {
            wp_enqueue_script('mlcs_checkout_billing_phone_script', plugins_url('js/checkout_billing_phone.js', MLCS_PLUGIN), array('jquery'), '1.9');
            wp_localize_script('mlcs_checkout_billing_phone_script', 'mlcs_checkout_billing_phone_script', 
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'language' => get_locale(),
                    'nonce' => wp_create_nonce('mlcs-phone-nonce'),
                )
            );
        }
    }
    public function mlcs_update_user_meta($user_id) { //to prevent the subscriber to re-subscribe 
        if (is_user_logged_in() ) {
            if (get_user_meta($user_id, 'mlcs_telemarketing_subscibed', true) ) {
                update_user_meta($user_id, 'mlcs_telemarketing_subscibed', 0);
            }
            if (get_user_meta($user_id, 'mlcs_newsletter_subscibed', true) ) {
                update_user_meta($user_id, 'mlcs_newsletter_subscibed', 0);
            }
        }
    }  
    public function mlcs_dequeue_scripts()
    {
        wp_dequeue_script('mlcs_mailerlite_checkout_billing_email_script');
        wp_dequeue_script('mlcs_checkout_billing_phone_script');
    }
    public function mlcs_subscribe_customer_after_order($order_id) //order-received page
    {
        $order = wc_get_order( $order_id );
        $customer_email = $order->get_billing_email();
        $customer_name = $order->get_billing_first_name();
        $customer_last_name = $order->get_billing_last_name();
        $customer_phone = $order->get_billing_phone();
        //$options = get_options('mlcs_settings');
        //$list_active = get_option('mlcs_settings')['mlcs_telemarketing_list_active'];
        $mlcs_subscribed = get_post_meta($order_id, 'mlcs_subscibed', true);
        $list_id = array_key_exists('mlcs_telemarketing_list', $this->options) ? $this->options['mlcs_telemarketing_list'] : false;
        //subscibe customer to the telemarketing list
        if (isset($mlcs_subscribed) && $mlcs_subscribed == 1) {
            // Set the request body
            $body = array(
                'email' => $customer_email,
                'fields' => array(
                    'first_name' => $customer_name,
                    'last_name' => $customer_last_name,
                    'subscriber_language' => determine_locale(),
                    'phone' => $customer_phone,
                ),
            );

            $key1 = array_key_exists('mlcs_key1', $this->options) ? $this->options['mlcs_key1'] : false;
            $val1 = array_key_exists('mlcs_val1', $this->options) ? $this->options['mlcs_val1'] : false;

            if ($key1 && $val1) {
                $body[$key1] = $val1; //'e.g. marketing_preferences' => 'Telemarketing',
            }
            print($this->mlcs_subscribe_user_to_list($body, $list_id)); //Show the error message returned
        }

        //subscibe customer to the follow-up email list
        $follow_up_list_id = array_key_exists('mlcs_follow_up_list', $this->options) ? $this->options['mlcs_follow_up_list'] : false;
        $follow_up_list_active = array_key_exists('mlcs_follow_up_list_active', $this->options) ? $this->options['mlcs_follow_up_list_active'] : false;

        if ($follow_up_list_active && $follow_up_list_id) {
            // Set the request body
            $body = array(
                'email' => $customer_email,
                'fields' => array(
                    'first_name' => $customer_name,
                    'last_name' => $customer_last_name,
                    'subscriber_language' => determine_locale(),
                ),
            );
            print( $this->mlcs_subscribe_user_to_list($body, $follow_up_list_id) ); //Show the error message returned
        }
    }

    public function mlcs_wc_add_phone_call_subscription_checkbox_checkout()
    {
        $list_active = array_key_exists('mlcs_telemarketing_list_active', $this->options) ? $this->options['mlcs_telemarketing_list_active'] : false;
        if($list_active) {
            ?>
                <p id="mlcs_telemarketing" class="form-row">
                    <span class="woocommerce-input-wrapper">
                        <label class="checkbox">
                            <input type="checkbox" id="mlcs_phone_call_subscription" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="mlcs_phone_call_subscription"/>
                            <?php _e('Would you like to receive a phone call from our customer support team?', 'mailerlite-checkout-subscribe') ?>
                        </label>
                    </span>
                </p>
            <?php
        }
    }

    public function mlcs_wc_save_phone_call_subscription_state($order) //save state for card payments and Paypal when a GUEST customer leaves the site
    {
        $checkbox = isset($_POST['mlcs_phone_call_subscription']) ? 1 : 0;
        if (is_object($order)) {
            $order->update_meta_data('mlcs_subscibed', $checkbox);
            $order->save();
        }
    }

    public function mlcs_wc_add_newsletter_checkbox_registration() 
    {
        $list_active = array_key_exists('mlcs_newsletter_list_active', $this->options) ? $this->options['mlcs_newsletter_list_active'] : false;
        if (!$list_active) {
            return;
        }
        ?>
            <span class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                    <input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" type="checkbox" name="mlcs-newsletter-checkbox" id="mlcs-newsletter-checkbox" value="1"
                        <?php if (!empty($_POST['mlcs-newsletter-checkbox'])) checked($_POST['mlcs-newsletter-checkbox'], 1); ?>/>
                    <span><?php _e('I subscribe to newsletters.', 'mailerlite-checkout-subscribe'); ?></span>
                </label><br/>
                <?php _e('You may withdraw your consent at any time, without giving any reason, in accordance with the Privacy Policy.', 'mailerlite-checkout-subscribe') ?>
            </span>
        <?php
    }

    public function mlcs_wc_add_phone_call_checkbox_registration()
    {
        $list_active = array_key_exists('mlcs_telemarketing_list_active', $this->options) ? $this->options['mlcs_telemarketing_list_active'] : false;
        if (!$list_active) {
            return;
        }
        ?>
            <span class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                    <input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" type="checkbox" name="mlcs-telemarketing-checkbox" id="mlcs-telemarketing-checkbox" value="1"
                        <?php if (!empty($_POST['mlcs-telemarketing-checkbox'])) checked($_POST['mlcs-telemarketing-checkbox'], 1); ?>/>
                    <span><?php _e('I subscribe to telemarketing calls.', 'mailerlite-checkout-subscribe'); ?></span>
                </label><br/>
                <?php _e('You may withdraw your consent at any time, without giving any reason, in accordance with the Privacy Policy.', 'mailerlite-checkout-subscribe') ?>
            </span>
        <?php
    }

    public function mlcs_subscribe_user_wc_registration( $customer_id ) {

        if ( isset( $_POST['mlcs-newsletter-checkbox'] ) ) { //save state
            update_user_meta( $customer_id, 'mlcs_newsletter_subscibed', 1 ); //true
        } else {
            update_user_meta( $customer_id, 'mlcs_newsletter_subscibed', 0 ); //false
        }

        if (isset($_POST['mlcs-telemarketing-checkbox'])) { //save it for later
            update_user_meta($customer_id, 'mlcs_telemarketing_subscibed', 1); //true
        } else {
            update_user_meta($customer_id, 'mlcs_telemarketing_subscibed', 0); //false
        }
        
        $user = get_userdata($customer_id);
        $customer_email = $user->user_email;
        $mlcs_subscribed = get_user_meta($customer_id, 'mlcs_newsletter_subscibed', true);
        $list_id = array_key_exists('mlcs_newsletter_list', $this->options) ? $this->options['mlcs_newsletter_list'] : false;

        //subscibe customer to the newsletter list
        if ($mlcs_subscribed && $customer_email && $list_id) {
            // Set the request body
            $body = array(
                'email' => $customer_email,
                'fields' => array(
                    'subscriber_language' => determine_locale(),
                ),
            );
            print($this->mlcs_subscribe_user_to_list($body, $list_id)); //Show the error message returned
        }
    }
    public function mlcs_validate_phone()
    {
        $billing_phone = preg_replace('/[^0-9+]/', '', $_POST['billing_phone']); //remove bad chars
        if (!(preg_match('/^\+[1-9][0-9]{9,}$/', $billing_phone))) { //international format
            $billing_phone = $_POST['billing_phone'];
            wc_add_notice(__('The mobile phone number you entered is incorrect:', 'mailerlite-checkout-subscribe') . ' <span data-no-translation style="color:red; font-weight:bold;">' . $billing_phone .
                '</span><br /><b>' . __('Please enter the mobile number in the following format: +36701234567', 'mailerlite-checkout-subscribe') . '</b>', 'error');
            wc_add_notice( __('This may be due to the following reasons:', 'mailerlite-checkout-subscribe') . '<br />' .
                '<ul><li>' . __('the data entered is incorrect,', 'mailerlite-checkout-subscribe') . '</li>' .
                '<li>' . __('the browser you are using is not up to date and therefore does not work with the shop,', 'mailerlite-checkout-subscribe') . '</li>' .
                '<li>' . __('You are using an ad-blocking plugin that may cause errors in the operation of the shop, so please disable it!', 'mailerlite-checkout-subscribe') . '</li></ul>'
                , 'error');
        }
    }
    public function mlcs_subscribe_user_to_list($body, $list_id)
    {
        $err = false;
        $api_key = array_key_exists('mlcs_token', $this->options) ? $this->options['mlcs_token'] : false;

        if (!isset($body) || !is_array($body) || !isset($list_id) || empty($list_id) || !$api_key) {
            $err = true;
        }

        if (!$err) {
            // Construct the URL
            $url = 'https://api.mailerlite.com/api/v2/groups/' . $list_id . '/subscribers';

            // Set the request headers
            $headers = array(
                'Content-Type' => 'application/json',
                'X-MailerLite-ApiKey' => $api_key
            );

            // Send the request
            $response = wp_remote_post(
                $url,
                array(
                    'headers' => $headers,
                    'body' => json_encode($body)
                )
            );

            // Check the response status code
            if (wp_remote_retrieve_response_code($response) !== 200) {
                $err = true;
            }
        }

        if ($err) {
            return __('Error subscribing customer: ', 'mailerlite-checkout-subscribe') . wp_remote_retrieve_response_message($response);
        }
        return;
    }
}

$MailerLiteCheckoutSubscribe = new MailerLiteCheckoutSubscribe();

?>