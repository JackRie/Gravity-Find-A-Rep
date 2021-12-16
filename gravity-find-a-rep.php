<?php
/**
 * Plugin Name:       Gravity Find a Rep
 * Plugin URI:        https://github.com/JackRie/Gravity-Find-A-Rep
 * Description:       Integrate Gravity form with 3rd party API call to display find a rep information.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Fairly Painless
 * Author URI:        https://fairlypainless.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/JackRie/Gravity-Find-A-Rep
 * Text Domain:       gravfr
 * Domain Path:       /languages
 */

if( ! defined('ABSPATH') ) exit; //Exit if accessed directly
// Define File Path Constant
define( 'GRAVFR_DIR', dirname(__FILE__).'/' );

class GravityFindARep {

    function __construct() {
        add_filter('gform_confirmation_3', array($this, 'custom_confirmation'), 10, 4);
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_init', array($this, 'settings_fields') );
    }

    function add_admin_page() {
        add_options_page( 'Find A Rep Settings', __('Find A Rep', 'gravfr'), 'manage_options', 'gravfr-settings-page', array($this, 'add_admin_html') );
    }

    function settings_fields() {
        // API Call Settings
        add_settings_section( 'gravfr_section', "API Settings", null, 'gravfr-settings-page' );
        // Grant Type
        add_settings_field('gravfr-grant-type', 'Grant Type', array($this, 'textInputHtml'), 'gravfr-settings-page', 'gravfr_section', array('name' => 'gravfr-grant-type'));
        register_setting('armfrplugin', 'gravfr-grant-type', array('sanitize_callback' => 'sanitize_text_field', 'default' => NULL));
        // Client ID
        add_settings_field('gravfr-client-id', 'Client ID', array($this, 'textInputHtml'), 'gravfr-settings-page', 'gravfr_section', array('name' => 'gravfr-client-id'));
        register_setting('armfrplugin', 'gravfr-client-id', array('sanitize_callback' => 'sanitize_text_field', 'default' => NULL));
        // Client Secret
        add_settings_field('gravfr-client-secret', 'Client Secret', array($this, 'textInputHtml'), 'gravfr-settings-page', 'gravfr_section', array('name' => 'gravfr-client-secret'));
        register_setting('armfrplugin', 'gravfr-client-secret', array('sanitize_callback' => 'sanitize_text_field', 'default' => NULL));
        // Username
        add_settings_field('gravfr-username', 'Username', array($this, 'usernameHtml'), 'gravfr-settings-page', 'gravfr_section');
        register_setting('armfrplugin', 'gravfr-username', array('sanitize_callback' => 'sanitize_email', 'default' => NULL));
        // Password
        add_settings_field('gravfr-password', 'Password', array($this, 'textInputHtml'), 'gravfr-settings-page', 'gravfr_section', array('name' => 'gravfr-password'));
        register_setting('armfrplugin', 'gravfr-password', array('sanitize_callback' => 'sanitize_text_field', 'default' => NULL));
    }

    function textInputHtml($args) { ?>
        <input type="text" name="<?php echo $args['name']?>" value="<?php echo esc_attr(get_option($args['name']));?>">
    <?php
    }

    function usernameHtml() { ?>
        <input type="email" name="gravfr-username" value="<?php echo esc_attr(get_option('gravfr-username'));?>">
    <?php
    }

    function add_admin_html() { ?>
        <div class="wrap">
            <h2><?php _e("Gravity Find A Rep Settings") ?></h2>
            <h3>Use the fields below to set up creditials for Salesforce API Call.</h3>
            <form action="options.php" method="POST">
                <?php 
                settings_fields('armfrplugin');
                do_settings_sections('gravfr-settings-page'); 
                submit_button();
                ?>
            </form>
        </div>
    <?php
    }

    function custom_confirmation($confirmation, $form, $entry, $ajax) {
        // Setup Call For Token
        $params['grant_type'] = get_option('gravfr-grant-type');
        $params['client_id'] = get_option('gravfr-client-id');
        $params['client_secret'] = get_option('gravfr-client-secret');
        $params['username'] = get_option('gravfr-username');
        $params['password'] = get_option('gravfr-password');
        // Build HTTP Query With Above Params
        $paramaters = http_build_query($params);
        // URL Decode So Special Characters Don't Appear
        $query = urldecode($paramaters);
        // Get Token
        $getToken = wp_remote_post( 'https://login.salesforce.com/services/oauth2/token?', 
        array(
            'headers' => array(
                'Cookie'       => 'BrowserId=unsAxTdPEeyddq_3TSkKyg; CookieConsentPolicy=0:0; LSKey-c$CookieConsentPolicy=0:0',
                'Content-Type' => 'application/x-www-form-urlencoded'
            ), 
            'body' => $query,
            'data' => ''
        ));
        $token = json_decode(wp_remote_retrieve_body( $getToken ), true);
        // Define Country Field As Variable
        $country = rgar( $entry, '10' );
        // Create Switch Case Function for Conditional Logic
        function state($entry, $country) {
            switch($country) {
                case "CO": 
                    return rgar( $entry, '11' );
                break;
                case "RU":
                    return rgar( $entry, '12' );
                break;
                case "ES":
                    return rgar( $entry, '13' );
                break;
                default: return rgar( $entry, '9' );
            }
        }

        $productLine = rgar( $entry, '4' );

        function series($entry, $productLine) {
            switch($productLine) {
                case "Hot Water": 
                    return rgar( $entry, '15' );
                break;
                case "Pressure Temperature":
                    return rgar( $entry, '14' );
                break;
            }
        }

        // Use Token In Actual Call
        $post_url = $token['instance_url'] . '/services/apexrest/FindAccountRepService/V1';
        // Set Up Request Body With Form Fields
        $args = array(
            'acctInfo' => array(
                'name' => rgar( $entry, '1' ),
                'country' => $country,
                'postalCode' => rgar( $entry, '7' ),
                'state' => state($entry, $country),
                'city' => rgar( $entry, '8' ),
                'productLine' => $productLine,
                'productSeries' => series($entry, $productLine),
                'industryType' => rgar( $entry, '5' )
            )
        );
        // Structure Request Body As JSON
        $body = json_encode($args);

        GFCommon::log_debug( 'gform_confirmation: body => ' . print_r( $body, true ) );
        // Send Request
        $request  = new WP_Http();
        $response = $request->post( $post_url, array( 
            'headers' => array(
                'Authorization' => $token['token_type'] . ' ' . $token['access_token'],
                'Cookie'        => 'BrowserId=unsAxTdPEeyddq_3TSkKyg; CookieConsentPolicy=0:0; LSKey-c$CookieConsentPolicy=0:0',
                'Content-Type'  => 'application/json'
            ),
            'body' => $body 
        ) );

        GFCommon::log_debug( 'gform_confirmation: response => ' . print_r( $response, true ) );
        // Decode Response for PHP
        $res = json_decode( $response['body'] );
        // Define Variable for Error Responses
        $exists = isset($res[0]->errorCode);
        
        // Build Custom Confirmation
        $confirmation.= '<style>.find-a-rep p { margin: 1rem 0; } .find-a-rep h3 { margin-bottom: 0; } .find-a-rep a { margin: 1rem 0; display: block; } .find-a-rep h4 { margin-bottom: 0; }</style>';
        $confirmation .= '<div class="find-a-rep">';
        // If Error Return No Rep Template
        if($exists) {
            include 'templates/error.php';
        } else {
            // If Response is Empty or if Name or Email is null And Country is null Return No Rep Template
            if( empty($res) || ($res[0]->Name === null || $res[0]->Email === null) && $res[0]->Company === null ) {
                include 'templates/error.php';
            } else {
                // Else Loop over the Response Array and Build HTML
                foreach( $res as $rep ) {
                    $confirmation .= '<div class="rep">';
                    $confirmation .= $rep->Company ? '<h3>' . $rep->Company . '</h3>' : '<h3>Company Name Not Available</h3>';
                    $confirmation .= $rep->Name ? '<p>' . $rep->Name . '</p>' : "";
                    $confirmation .= $rep->Email ? '<a href="mailto:' . $rep->Email . '">' . $rep->Email . '</a>' : "";
                    $confirmation .= $rep->Phone ? '<a href="tel:' . $rep->Phone . '">' . $rep->Phone . '</a>' : "";
                    if ($rep->Street || $rep->City || $rep->State || $rep->PostalCode || $rep->Country) {
                        $confirmation .= '<h4>Address: </h4><p>'; 
                        $confirmation .= $rep->Street . '<br>';
                        $confirmation .= $rep->City . ', ';
                        $confirmation .= $rep->State . ' ';
                        $confirmation .= $rep->PostalCode . '<br>';
                        $confirmation .= $rep->Country . '</p>';
                    }
                    $confirmation .= '<div>';
                }
            }
        }
        // End find-a-rep Container
        $confirmation .= '</div>';
        $confirmation .= "<script id='scroll-top' type='text/javascript'>window.top.jQuery(document).on('gform_confirmation_loaded', function () { document.documentElement.scrollTo({ top: 0, behavior: 'smooth' }) } );</script>";
        // Return Filtered Confirmation
        return $confirmation;
    }

}

$gravityFindARep = new GravityFindARep();
