<?php
defined('ABSPATH') or die("you do not have access to this page!");

register_deactivation_hook(__FILE__, array('rsssl_premium_options','deactivate') );

class rsssl_premium_options {
    private static $_this;
    //enter previous version
    private $required_version = "2.5.12";
    public $has_http_redirect=false;

    function __construct() {
        if ( isset( self::$_this ) )
            wp_die( sprintf( __( '%s is a singleton class and you cannot create a second instance.','really-simple-ssl-pro' ), get_class( $this ) ) );

        self::$_this = $this;

        add_action('plugins_loaded', array(&$this, 'load_translation'), 20);

        add_action("update_option_rlrsssl_options", array($this, "update_hsts_no_apache"), 10,3);

        add_action("update_option", array($this, "insert_security_headers_in_htaccess"), 20, 3);

        add_action("update_option_rlrsssl_options", array($this, "maybe_clear_certificate_check_schedule"), 30,3);

        //Action for the NGINX notice
        add_action("update_option_rlrsssl_options", array($this, "maybe_update_nginx_notice_option_hsts"), 20, 3);
        add_action("update_option_rsssl_hsts_preload", array($this, "maybe_update_nginx_notice_option_hsts_preload"), 20, 3);
        add_action("update_option_rlrsssl_options", array($this, "maybe_update_pro_multisite_notice_option"), 20, 3);
        add_action("update_option_rsssl_enable_csp_reporting", array($this, "maybe_update_csp_activation_time"), 20, 3);

        //add_action('admin_init', array($this, 'add_hsts_option'),50);
        add_action('wp_loaded', array($this, 'admin_mixed_content_fixer'), 1);

        add_action('admin_init', array($this, 'check_http_redirect'), 1);
        add_action('admin_init', array($this, 'change_notices_free'), 5);

        add_action('admin_init', array($this, 'add_pro_settings'),60);

        add_action('admin_init' , array($this, 'add_security_headers_settings'), 60);

        add_action('admin_init', array($this, 'insert_secure_cookie_settings'), 70);

        add_action("admin_notices", array($this, 'show_notice_wpconfig_not_writable'));
        add_action("admin_notices", array($this, 'show_notice_csp_enabled_next_steps'));
        add_action("admin_notices", array($this, 'show_notice_upgrade_pro_multisite'));
        add_action("admin_notices", array($this, 'show_nginx_headers_notice'), 20);
        add_action("admin_notices", array($this, 'show_notice_redirect_to_http'), 30);

        //Necessary to dismiss the nginx and pro multisite notices
        add_action('admin_print_footer_scripts', array($this, 'insert_nginx_dismiss_success'));
        add_action('wp_ajax_dismiss_success_message_nginx', array($this,'dismiss_nginx_message_callback') );
        add_action('admin_print_footer_scripts', array($this, 'insert_pro_multisite_notice_success'));
        add_action('admin_print_footer_scripts', array($this, 'insert_csp_next_steps_dismiss'));

        add_action('wp_ajax_dismiss_success_pro_multisite_notice', array($this,'dismiss_pro_multisite_notice_callback') );
        add_action('wp_ajax_dismiss_csp_next_steps_notice', array($this,'dismiss_csp_next_steps_notice_callback') );

        $plugin = rsssl_pro_plugin;
        add_filter("plugin_action_links_$plugin", array($this,'plugin_settings_link'));

        add_filter('rsssl_tabs', array($this,'add_security_headers_tab'),14,3 );
        add_filter('rsssl_notices', array($this,'get_notices_list'),20,1 );
        add_action('show_tab_security_headers', array($this, 'add_security_headers_page'));

	    add_filter( 'rsssl_activate_notice_class', array($this, 'activation_notice_color'), 10, 3 );


    }

    static function this() {
        return self::$_this;
    }

    public function add_security_headers_tab($tabs)
    {
        $tabs['security_headers'] = __("Security Headers","really-simple-ssl-pro");
        return $tabs;
    }

    public function check_http_redirect(){
        if (!RSSSL()->really_simple_ssl->ssl_enabled) {
            $this->has_http_redirect = $this->has_redirect_to_http();
        } else {
            $this->has_http_redirect = false;
        }
    }

    public function deactivate(){
        $this->remove_security_headers();
        $this->remove_secure_cookie_settings();
    }

    public function load_translation() {
        $success = load_plugin_textdomain('really-simple-ssl-pro', FALSE, dirname(plugin_basename(__FILE__)).'/languages/');
    }

    public function change_notices_free(){

        remove_action('rsssl_activation_notice_inner', array(RSSSL()->really_simple_ssl, 'show_pro'), 40);

        if (!RSSSL()->really_simple_ssl->ssl_enabled && $this->has_http_redirect){
            remove_action('rsssl_activation_notice_inner', array(RSSSL()->really_simple_ssl, 'show_enable_ssl_button'), 50);
        }
        add_action('rsssl_activation_notice_inner' , array($this, 'show_scan_buttons_before_activation'), 40);
    }

    /*
        Activate the mixed content fixer on the admin when enabled.
    */

    public function admin_mixed_content_fixer(){

        $admin_mixed_content_fixer = get_option("rsssl_admin_mixed_content_fixer");
        if (is_multisite() && RSSSL()->rsssl_multisite->mixed_content_admin) {
            $admin_mixed_content_fixer = true;
        }

        if (is_admin() && is_ssl() && $admin_mixed_content_fixer) {
            RSSSL()->rsssl_mixed_content_fixer->fix_mixed_content();
        }

    }

    public function options_validate($input){
        if ($input==1){
            $validated_input = 1;
        }else{
            $validated_input = "";
        }
        return $validated_input;

    }

    public function options_validate_text($input)
    {
        if (!current_user_can('manage_options')) return '';

        $validated_input = sanitize_text_field($input);
        return $validated_input;
    }


    /**
     *
     * Checks if a redirect to http:// is active to prevent redirect loop issues
     * Since 2.0.20
     * @access public
     *
     */

    public function has_redirect_to_http()
    {
        //run this function only once
        $detected_redirect = get_option('rsssl_redirect_to_http_check');
        $force_check = false;

        //but if the user explicitly rechecks, run it again.
        if (isset($_POST['rsssl-check-redirect'])) $force_check = true;

        if ($force_check || !$detected_redirect){
            //make sure this redirect check only happens once by immediately setting a value
            update_option('rsssl_redirect_to_http_check', 'https');
            $url = site_url();
            if (!function_exists('curl_init')) {
                return false;
            }

            //CURLOPT_FOLLOWLOCATION might cause issues on php<5.4
            if (version_compare(PHP_VERSION, '5.4') < 0) {
                return false;
            }

            //Change the http:// domain to https:// to test for a possible redirect back to http://.
            $url = str_replace("http://", "https://", $url);

            //Follow the entire redirect chain.
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // follow redirects
            curl_setopt($ch, CURLOPT_AUTOREFERER, 1); // set referer on redirect
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
            curl_exec($ch);
            //$target is the endpoint of the redirect chain
            $target = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);

            //Check for http:// needle in target
            $http_needle = 'http://';

            $pos = strpos($target, $http_needle);

            if ($pos !== false) {
                //There is a redirect back to HTTP.
                $detected_redirect = 'http';
            } else {
                $detected_redirect = 'https';
            }
            update_option('rsssl_redirect_to_http_check', $detected_redirect);
        }

        if ($detected_redirect === 'http') {
            return true;
        } else {
            return false;
        }

    }


    public function show_notice_redirect_to_http()
    {
        //prevent showing the review on edit screen, as gutenberg removes the class which makes it editable.
        $screen = get_current_screen();
        if ( $screen->parent_base === 'edit' ) return;

        if (!RSSSL()->really_simple_ssl->ssl_enabled && $this->has_http_redirect && !defined('rsssl_pp_version')) {

            $link_open = '<a target="_blank" href="https://really-simple-ssl.com/knowledge-base/my-website-is-in-a-redirect-loop/">';
            $link_close = '</a>';

            ?>
            <div id="warning" class="notice notice-error">
                <p>
                    <?php printf(__("Really Simple SSL has detected a redirect to HTTP. This can result in a redirect loop when activating SSL. See %sour article on redirect loops%s for the most common causes of a redirect back to http://. We strongly recommend to locate and disable this redirect before activating SSL.", "really-simple-ssl-pro"), $link_open, $link_close);

                    ?>
                <form action="" method="POST">
                    <input type="submit" class="button" name="rsssl-check-redirect" value="<?php _e("Re-check the redirect","complianz")?>">
                </form>
                </p>

            </div>
            <?php
        }
    }


    /*
        if the server is not apache, we set the HSTS in another way.
    */

    public function update_hsts_no_apache($oldvalue, $newvalue, $option){

        if (!is_admin()) return;
        if (!current_user_can("activate_plugins")) return;
        if (!function_exists('RSSSL')) return;

        $options = $newvalue;
        $hsts = isset($options['hsts']) ? $options['hsts'] : FALSE;

        $hsts_no_apache = false;
        $not_using_htaccess = (!is_writable($this->htaccess_file()) || RSSSL()->really_simple_ssl->do_not_edit_htaccess) ? true : false;

        if (class_exists("rsssl_server")) {
            $apache = (RSSSL()->rsssl_server->get_server()=="apache");
            $contains_hsts = RSSSL()->really_simple_ssl->contains_hsts();
            if ($hsts && (!$apache || ($apache && $not_using_htaccess && !$contains_hsts ))) {
                $hsts_no_apache = true;
            } else {
                $hsts_no_apache = false;
            }
        }

        $hsts_no_apache = apply_filters("rsssl_hsts_no_apache", $hsts_no_apache);

        update_option("rsssl_hsts_no_apache", $hsts_no_apache);
    }

    /*
    * Maybe clear the option value for the NGINX notice when the option value has changed
    *
    */

    public function maybe_update_nginx_notice_option_hsts($oldvalue, $newvalue, $option) {

        $hsts_new = isset($newvalue['hsts']) ? $newvalue['hsts'] : FALSE;
        $hsts_old = isset($oldvalue['hsts']) ? $oldvalue['hsts'] : FALSE;

        if ($hsts_new!=$hsts_old) update_site_option("rsssl_nginx_message_shown", false);
    }

    public function maybe_update_nginx_notice_option_hsts_preload($oldvalue, $newvalue, $option) {

        $hsts_new = isset($newvalue['rsssl_hsts_preload']) ? $newvalue['rsssl_hsts_preload'] : FALSE;
        $hsts_old = isset($oldvalue['rsssl_hsts_preload']) ? $oldvalue['rsssl_hsts_preload'] : FALSE;

        if ($hsts_new!=$hsts_old) update_site_option("rsssl_nginx_message_shown", false);
    }

    public function maybe_update_pro_multisite_notice_option($oldvalue, $newvalue, $option) {

        $pro_ms_message_new = isset($newvalue['ms_notice']) ? $newvalue['ms_notice'] : FALSE;
        $pro_ms_message_old = isset($oldvalue['ms_notice']) ? $oldvalue['ms_notice'] : FALSE;

        if ($pro_ms_message_new!=$pro_ms_message_old) update_site_option("rsssl_pro_multisite_message_shown", false);
    }


    public function maybe_update_csp_activation_time($oldvalue, $newvalue, $option) {

        if (get_option("rsssl_csp_reporting_activation_time") ) return;

        if ($oldvalue!=$newvalue) {
            update_option("rsssl_csp_reporting_activation_time", time());
        }

    }

    /*
    *     Check if PHP headers are used to set HSTS
    *      @param void
    *      @return boolean
    *
    **/

    public function uses_php_header_for_hsts(){
        return get_option("rsssl_hsts_no_apache");
    }

    public function add_pro_settings(){
        if (!class_exists('REALLY_SIMPLE_SSL')) return;

        if (!current_user_can('manage_options')) return;

        //for pro users who do not have the multisite plugin but use multisite, we hide preload, as testing for subdomains might be tricky
        if( !is_multisite() || (is_multisite() && RSSSL()->rsssl_multisite->ssl_enabled_networkwide) ) {
            add_settings_field('id_hsts', __("Turn HTTP Strict Transport Security on","really-simple-ssl-pro"), array($this,'get_option_hsts'), 'rlrsssl', 'rlrsssl_settings');

//            if(RSSSL()->really_simple_ssl->hsts) {
                register_setting( 'rlrsssl_options', 'rsssl_hsts_preload', array($this,'options_validate') );
                add_settings_field('id_hsts_preload', __("Configure your site for the HSTS preload list","really-simple-ssl-pro"), array($this,'get_option_hsts_preload'), 'rlrsssl', 'rlrsssl_settings');
//            }
        }

        add_settings_field('id_cert_expiration_warning', __("Receive an email when your certificate is about to expire","really-simple-ssl-pro"), array($this,'get_option_cert_expiration_warning'), 'rlrsssl', 'rlrsssl_settings');
        add_settings_field('id_admin_mixed_content_fixer', __("Enable the mixed content fixer on the WordPress back-end","really-simple-ssl-pro"), array($this,'get_option_admin_mixed_content_fixer'), 'rlrsssl', 'rlrsssl_settings');

        //add_settings_section('section_rssslpp', __("Pro", "really-simple-ssl-pro"), array($this, "section_text"), 'rlrsssl');
        register_setting( 'rlrsssl_options', 'rsssl_admin_mixed_content_fixer', array($this,'options_validate') );
        register_setting( 'rlrsssl_options', 'rsssl_cert_expiration_warning', array($this,'options_validate') );

    }

    public function add_security_headers_settings()
    {
        if (!class_exists('REALLY_SIMPLE_SSL') && (!class_exists('REALLY_SIMPLE_SSL_PP'))) return;

        if (!current_user_can('manage_options')) return;

        add_settings_section('rlrsssl_security_headers_section', __("Settings", "really-simple-ssl-pro"), array($this, 'security_headers_text'), 'rlrsssl_security_headers_page');

        //Security headers
        add_settings_field('id_content_security_policy', __("Set Content-Security-Policy upgrade-insecure-request header", "really-simple-ssl-pro"), array($this, 'get_option_content_security_policy'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
        register_setting('rlrsssl_security_headers', 'rsssl_content_security_policy', array($this, 'options_validate'));
        add_settings_field('id_x_xss_protection', __("Set Cross-site scripting (X-XSS) protection header", "really-simple-ssl-pro"), array($this, 'get_option_x_xss_protection'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
        register_setting('rlrsssl_security_headers', 'rsssl_x_xss_protection', array($this, 'options_validate'));
        add_settings_field('id_x_content_type_options', __("Set X-Content-Type-Options nosniff header", "really-simple-ssl-pro"), array($this, 'get_option_x_content_type_options'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
        register_setting('rlrsssl_security_headers', 'rsssl_x_content_type_options', array($this, 'options_validate'));
        add_settings_field('id_no_referrer_when_downgrade', __("Set No Referrer When Downgrade header", "really-simple-ssl-pro"), array($this, 'get_option_no_referrer_when_downgrade'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
        register_setting('rlrsssl_security_headers', 'rsssl_no_referrer_when_downgrade', array($this, 'options_validate'));
        add_settings_field('id_expect_ct', __("Set Expect-CT enforce header", "really-simple-ssl-pro"), array($this, 'get_option_expect_ct'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
        register_setting('rlrsssl_security_headers', 'rsssl_expect_ct', array($this, 'options_validate'));
        add_settings_field('id_x-frame-options', __("Set X-Frame-Options sameorigin header", "really-simple-ssl-pro"), array($this, 'get_option_x_frame_options'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
        register_setting('rlrsssl_security_headers', 'rsssl_x_frame_options', array($this, 'options_validate'));

        //CSP Reporting
            add_settings_field('id_csp_reporting', __("Enable Content Security Policy reporting", "really-simple-ssl-pro"), array($this, 'get_option_enable_csp_reporting'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
            register_setting('rlrsssl_security_headers', 'rsssl_enable_csp_reporting', array($this, 'options_validate'));

            if (get_option('rsssl_enable_csp_reporting')) {
                add_settings_field('id_rsssl_add_csp_rules_to_htaccess', __("Enforce Content Security Policy", "really-simple-ssl-pro"), array($this, 'get_option_rsssl_add_csp_rules_to_htaccess'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
                register_setting('rlrsssl_security_headers', 'rsssl_add_csp_rules_to_htaccess', array($this, 'options_validate'));
            }
    }

    /**
     *
     * Add the security headers options page
     *
     * @since 2.5
     *
     */

    public function add_security_headers_page()
    {
        if (!current_user_can('manage_options')) return;

        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields('rlrsssl_security_headers');
            do_settings_sections('rlrsssl_security_headers_page');
            ?>

            <input class="button button-primary" name="Submit" type="submit"
                   value="<?php echo __("Save", "really-simple-ssl"); ?>"/>
        </form>
        <?php
    }

    public function security_headers_text()
    {
        echo __("Security headers provide additional security for your website. Hover over the tooltip behind each option to see an explanation.", "really-simple-ssl-pro");
    }


    //Notice arrays
    public function get_notices_list($notices)
    {

        $nice_date = rsssl_pro_expiration_date_nice();

        $notices['certificate_renewal'] = array(
            'condition' => array('rsssl_ssl_enabled', 'rsssl_pro_renewal_notice_enabled'),
            'callback' => 'rsssl_pro_certificate_renewal',
            'output' => array(
                'expiring' => array(
                    'msg' => __("Your certificate needs to be renewed soon, it is valid to: ", "really-simple-ssl-pro") . $nice_date,
                    'icon' => 'success'
                ),
                'not-expiring' => array(
                    'msg' => __("Your certificate is valid to: ", "really-simple-ssl-pro") . $nice_date,
                    'icon' => 'warning'
                ),
            ),
        );

        $notices['hsts_enabled'] = array(
            'condition' => array('rsssl_ssl_enabled', 'rsssl_pro_no_multisite'),
            'callback' => 'rsssl_pro_hsts_enabled',
            'output' => array(
                'hsts-set' => array(
                    'msg' =>__("HTTP Strict Transport Security was set.", "really-simple-ssl-pro"),
                    'icon' => 'success'
                ),
                'hsts-set-php' => array(
                    'msg' => sprintf(__("HTTP Strict Transport Security was set, but with PHP headers, %swhich might cause issues in combination in combination with caching.%s ", "really-simple-ssl-pro"),'<a href="https://really-simple-ssl.com/knowledge-base/inserting-hsts-header-using-php/" target="_blank">', '</a>' ),
                    'icon' => 'warning'
                ),
	            $enable_link = RSSSL()->really_simple_ssl->generate_enable_link($setting_name = 'hsts-enabled'),
                'hsts-not-set' => array(
                    'msg' => sprintf(__("%sHTTP Strict Transport Security%s is not enabled. ", "really-simple-ssl-pro"),'<a href="https://en.wikipedia.org/wiki/HTTP_Strict_Transport_Security" target="_blank">', '</a>' )
                                         . "<span><a href=$enable_link>enable</a></span>" . " "
                                  . __("or", "really-simple-ssl")
                                  . "<span class='rsssl-dashboard-dismiss' data-dismiss_type='check_redirect'><a href='#' class='rsssl-dismiss-text rsssl-close-warning'>dismiss</a></span>",
                    'icon' => 'warning'
                ),
            ),
        );

        $notices['hsts_preload'] = array(
            'condition' => array('rsssl_ssl_enabled', 'rsssl_pro_no_multisite'),
            'callback' => 'rsssl_pro_hsts_preload',
            'output' => array(
                'hsts-preload-set' => array(
                    'msg' => sprintf(__("Your site has been configured for the HSTS preload list. If you have submitted your site, it will be preloaded. Click %shere%s to submit.", "really-simple-ssl-pro"),'<a target="_blank" href="https://hstspreload.org/?domain='.$this->non_www_domain().'">', '</a>' ),
                    'icon' => 'success'
                ),
                'hsts-preload-not-set' => array(
                    'msg' => sprintf(__("Your site is not yet configured for the %sHSTS preload list.%s Read the documentation carefully before you do! ", "really-simple-ssl-pro"),'<a target="_blank" href="https://hstspreload.appspot.com/?domain='.$this->non_www_domain().'">', '</a>' ),
                    'icon' => 'warning'
                ),
            ),
        );

        $notices['secure_cookies_set'] = array(
            'condition' => array('rsssl_ssl_enabled'),
            'callback' => 'rsssl_pro_secure_cookies_set',
            'output' => array(
                'set' => array(
                    'msg' => __("Secure cookies set","really-simple-ssl"),
                    'icon' => 'success'
                ),
                'not-set' => array(
                    'msg' => __('Secure cookie settings not enabled.',"really-simple-ssl"),
                    'icon' => 'warning'
                ),
            ),
        );

        if (get_option('rsssl_enable_csp_reporting') ) {

            if ( (get_option('rsssl_csp_reporting_activation_time') && get_option('rsssl_csp_reporting_activation_time') < strtotime("-1 week")  && (!get_option("rsssl_add_csp_rules_to_htaccess"))) ) {
                 $plusone = 'true';
                 $dismissible = 'true';
             } else {
                 $plusone = 'false';
                 $dismissible = 'false';
             }


            $notices['new_csp_entries'] = array(
                'condition' => array('rsssl_ssl_enabled'),
                'callback' => 'rsssl_pro_check_for_new_csp_entries',
                'output' => array(
                    'new-csp-rules' => array(
                        'msg' => __("You have new rules that can be added to your Content Security Policy", "really-simple-ssl-pro"),
                        'icon' => 'warning',
                        'plusone' => $plusone,
                        'dismissible' => $dismissible

                    ),
                    'no-new-csp-rules' => array(
                        'msg' => __("No Content Security Policy violations found", "really-simple-ssl-pro"),
                        'icon' => 'success'
                    ),
                ),
            );
        }

        $notices['mixed_content_scan'] = array(
        'callback' => 'rsssl_pro_scan_notice',
        'output' => array(
            'has-ssl-no-scan-errors' => array(
                'msg' => __("Great! Your scan last completed without errors.", "really-simple-ssl-pro"),
                'icon' => 'success'
            ),
            'has-ssl-scan-has-errors' => array(
                'msg' => __("The last scan was completed with errors. Only migrate if you are sure the found errors are not a problem for your site.", "really-simple-ssl-pro"),
                'icon' => 'warning',
                'dismissible' => true
            ),
            'has-ssl-no-scan-done' => array(
                'msg' => __("You haven't scanned the site yet, you should scan your site to check for possible issues before migrating to ssl.", "really-simple-ssl-pro"),
                'icon' => 'warning'
            ),
            'no-ssl-no-scan-errors' => array(
                'msg' => __("Great! Your scan last completed without errors.", "really-simple-ssl-pro"),
                'icon' => 'success'
            ),
            'no-ssl-scan-has-errors' => array(
                'msg' => __("The last scan was completed with errors. Are you sure these issues don't impact your site?.", "really-simple-ssl-pro"),
                'icon' => 'warning',
                'dismissible' => true
            ),
            'no-ssl-no-scan-done' => array(
                'msg' => __("You haven't scanned the site yet, you should scan your site to check for possible issues.", "really-simple-ssl-pro"),
                'icon' => 'warning'
            ),
        ),
    );

        $license_data = RSSSL_PRO()->rsssl_licensing->get_license_status();

        if ($license_data && ($license_data == 'expired' || $license_data == 'site_inactive') ) {

            $notices['rsssl_pro_license_valid'] = array(
                'callback' => 'rsssl_pro_is_license_expired',
                'output' => array(
                    'expired' => array(
                        'msg' => __("Your license key has expired. Please renew your license to continue receiving updates and premium support.", "really-simple-ssl-pro"),
                        'icon' => 'error',
                        'plusone' => true,
                        'dismissible' => true
                    ),
                    'not-activated' => array(
                        'msg' => __("Your license key hasn't been activated yet. Activate your license key in the license tab", "really-simple-ssl-pro"),
                        'icon' => 'warning'
                    ),
                ),
            );
        }

        return $notices;

    }

    /**
     * Insert option into settings form
     * @since  1.0.3
     *
     * @access public
     *
     */

    public function get_option_hsts() {

        ?>
        <label class="rsssl-switch" id="rsssl-maybe-highlight-hsts-enabled">
            <input id="rlrsssl_options" name="rlrsssl_options[hsts]" size="40" value="1"
                   type="checkbox" <?php checked(1, RSSSL()->really_simple_ssl->hsts, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
        <?php
        RSSSL()->rsssl_help->get_help_tip(__("HSTS, HTTP Strict Transport Security improves your security by forcing all your visitors to go to the SSL version of your website for at least a year.", "really-simple-ssl-pro")." ".__("It is recommended to enable this feature as soon as your site is running smoothly on SSL, as it improves your security.", "really-simple-ssl"));
    }

    public function get_option_content_security_policy() {

        $content_security_policy = get_option('rsssl_content_security_policy');

        ?>
        <label class="rsssl-switch">
            <input id="rlrsssl_security_headers" name="rsssl_content_security_policy" size="40" value="1"
                   type="checkbox" <?php checked(1, $content_security_policy, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
        <?php
        RSSSL()->rsssl_help->get_help_tip(__("Set the Content-Security-Policy upgrade insecure requests, which is an additional feature to force all incoming http:// requests to https://.", "really-simple-ssl-pro") );
    }

    public function get_option_x_xss_protection() {

        $x_xss_protection = get_option('rsssl_x_xss_protection');

        ?>
        <label class="rsssl-switch">
            <input id="rlrsssl_security_headers" name="rsssl_x_xss_protection" size="40" value="1"
                   type="checkbox" <?php checked(1, $x_xss_protection, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
        <?php
        RSSSL()->rsssl_help->get_help_tip(__("X-XSS-Protection protects your site from cross-site scripting attacks. If a cross-site scripting attack is detected, the browser will automatically sanitize (remove) unsafe parts (scripts) when this header is enabled.", "really-simple-ssl-pro") );
    }

    public function get_option_x_content_type_options() {

        $x_content_type_options = get_option('rsssl_x_content_type_options');

        ?>
        <label class="rsssl-switch">
            <input id="rlrsssl_security_headers" name="rsssl_x_content_type_options" size="40" value="1"
                   type="checkbox" <?php checked(1, $x_content_type_options, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
        <?php
        RSSSL()->rsssl_help->get_help_tip(__("X-Content-Type-Options prevents browsers from doing MIME-type sniffing. MIME-type sniffing is the practice of inspecting content to deduce the file format of the data within. For example, a PDF file with .jpg extension.", "really-simple-ssl-pro") );
    }

    public function get_option_no_referrer_when_downgrade() {

        $no_referrer_when_downgrade = get_option('rsssl_no_referrer_when_downgrade');

        ?>
        <label class="rsssl-switch">
            <input id="rlrsssl_security_headers" name="rsssl_no_referrer_when_downgrade" size="40" value="1"
                   type="checkbox" <?php checked(1, $no_referrer_when_downgrade, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
        <?php
        RSSSL()->rsssl_help->get_help_tip(__("No referrer when downgrade only sets a referrer when going from the same protocol (HTTPS->HTTPS) and not when downgrading (HTTPS->HTTP).", "really-simple-ssl-pro") );
    }

    public function get_option_expect_ct() {

        $expect_ct = get_option('rsssl_expect_ct');

        ?>
        <label class="rsssl-switch">
            <input id="rlrsssl_security_headers" name="rsssl_expect_ct" size="40" value="1"
                   type="checkbox" <?php checked(1, $expect_ct, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
        <?php
        RSSSL()->rsssl_help->get_help_tip(__("The Expect-CT header enforces certificate transparency. This is done by expecting valid Signed Certificate Timestamps (SCTs).", "really-simple-ssl-pro") );
    }

    public function get_option_x_frame_options() {

        $x_frame_options = get_option('rsssl_x_frame_options');

        ?>
        <label class="rsssl-switch">
            <input id="rlrsssl_security_headers" name="rsssl_x_frame_options" size="40" value="1"
                   type="checkbox" <?php checked(1, $x_frame_options, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
        <?php
        RSSSL()->rsssl_help->get_help_tip(__("The X-Frame-Options header prevents your site from being loaded in an iFrame on other domains. This is used to prevent clickjacking attacks.", "really-simple-ssl-pro") );
    }

    public function get_option_enable_csp_reporting() {

        $enable_csp_reporting = get_option('rsssl_enable_csp_reporting');
        $link_open = '<a target="_blank" href="https://really-simple-ssl.com/knowledge-base/how-to-use-the-content-security-policy-generator/">';
        $link_close = '</a>';

        if (RSSSL()->rsssl_server->uses_htaccess() && !RSSSL()->really_simple_ssl->do_not_edit_htaccess ) {
            $disabled = '';
        } else {
            $disabled = 'disabled="disabled"';
        }

        ?>
        <label class="rsssl-switch">
            <input id="rlrsssl_security_headers" name="rsssl_enable_csp_reporting" size="40" value="1"
                   type="checkbox" <?php echo $disabled ?> <?php checked(1, $enable_csp_reporting, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
        <?php
        RSSSL()->rsssl_help->get_help_tip(__("A Content Security Policy is an added layer of security that can mitigate and detect various security threats. This will add an additional tab to the settings where you can select which rules should be added to your Content Security Policy", "really-simple-ssl-pro") );

        if ($disabled === '') {
            printf(__("This is an advanced feature, only enable this when you know what you are doing. %sMore information%s", "really-simple-ssl-pro"), $link_open, $link_close);
        } elseif (RSSSL()->really_simple_ssl->do_not_edit_htaccess) {
            _e("You have checked the option 'Stop editing the .htaccess file'. This option requires access to your .htaccess file. Disabling the 'Stop editing the .htaccess file' option allows you to activate Content Security Policy reporting." , "really-simple-ssl-pro");
        } else {
            _e("This feature requires an .htaccess file. Your site doesn't seem to be using one, therefore this option cannot be enabled." , "really-simple-ssl-pro");

        }
    }

    public function get_option_rsssl_add_csp_rules_to_htaccess() {

        $add_csp_to_htaccess = get_option('rsssl_add_csp_rules_to_htaccess');

        ?>
        <label class="rsssl-switch">
            <input id="rlrsssl_security_headers" name="rsssl_add_csp_rules_to_htaccess" size="40" value="1"
                   type="checkbox" <?php checked(1, $add_csp_to_htaccess, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
        <?php
        RSSSL()->rsssl_help->get_help_tip(__("Add Content Security Policy rules to htaccess", "really-simple-ssl") );
    }

    public function get_option_cert_expiration_warning() {

        $cert_expiration_warning = get_option('rsssl_cert_expiration_warning');
        $disabled = "";
        $comment = "";
        if (is_multisite() && RSSSL()->rsssl_multisite->cert_expiration_warning) {
            $disabled = "disabled";
            $cert_expiration_warning = TRUE;
            $comment = __( "This option is enabled on the network menu.", "really-simple-ssl" );
        }

        ?>
        <label class="rsssl-switch">
            <input id="rlrsssl_options" name="rsssl_cert_expiration_warning" size="40" value="1"
                   type="checkbox" <?php checked(1, $cert_expiration_warning, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
        <?php

        RSSSL()->rsssl_help->get_help_tip(
            __("If your hosting company renews the certificate for you, you probably don't need to enable this setting.", "really-simple-ssl-pro")." ".
            __("If your certificate expires, your site goes offline. Uptime robots don't alert you when this happens.", "really-simple-ssl-pro")." ".
            __("If you enable this option you will receive an email when your certificate is about to expire within 2 weeks.", "really-simple-ssl-pro")
        );
        echo $comment;
    }

    public function get_option_admin_mixed_content_fixer() {
        $admin_mixed_content_fixer = get_option('rsssl_admin_mixed_content_fixer');
        $disabled = "";
        $comment = "";

        if (is_multisite() && RSSSL()->rsssl_multisite->mixed_content_admin) {
            $disabled = "disabled";
            $admin_mixed_content_fixer = TRUE;
            $comment = __( "This option is enabled on the network menu.", "really-simple-ssl" );
        }

        ?>
        <label class="rsssl-switch">
            <input id="rlrsssl_options" name="rsssl_admin_mixed_content_fixer" size="40" value="1"
                   type="checkbox" <?php checked(1, $admin_mixed_content_fixer, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
        <?php
        RSSSL()->rsssl_help->get_help_tip(__("Use this option if you do not have the green lock in the WordPress admin.", "really-simple-ssl-pro"));
        echo $comment;
    }


    public function get_option_hsts_preload() {
        $enabled = get_option('rsssl_hsts_preload');

        ?>
        <label class="rsssl-switch">
            <input id="rlrsssl_options" name="rsssl_hsts_preload" size="40" value="1"
                   type="checkbox" <?php checked(1, $enabled, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
        <?php
        RSSSL()->rsssl_help->get_help_tip(
            __("The preload list offers even more security, as browsers already will know to load your site over SSL before a user ever visits it. This is very hard to undo!", "really-simple-ssl-pro")." ".
            __("Please note that all subdomains, and both www and non-www domain need to be https!", "really-simple-ssl-pro")." ".
            __('Before submitting, please read the information on hstspreload.appspot.com', "really-simple-ssl-pro")
        );
        $link_start ='<a target="_blank" href="https://hstspreload.appspot.com/?domain='.$this->non_www_domain().'">';
        $link_close = "</a> ";
        echo sprintf(__("After enabling this option, you have to %ssubmit%s your site", "really-simple-ssl-pro"),$link_start, $link_close );

    }

    /*

      Get the non www domain.

    */

    public function non_www_domain(){
        $domain = get_home_url();
        $domain = str_replace(array("https://", "http://", "https://www.", "http://www.", "www."), "", $domain);
        return $domain;
    }


    /**
     * Add settings link on plugins overview page
     *
     * @since  1.0.27
     *
     * @access public
     *
     */

    public function plugin_settings_link($links) {

        $settings_link = '<a href="options-general.php?page=rlrsssl_really_simple_ssl">'.__("Settings","really-simple-ssl").'</a>';
        array_unshift($links, $settings_link);
        return $links;

    }

    public function insert_security_headers_in_htaccess($option, $oldvalue, $newvalue){

        //if (!RSSSL()->test_htaccess_redirect) return;
        //Do not update if this is not the RSSSL settings page
        if (!$this->is_settings_page()) return;

        if (!current_user_can("activate_plugins")) return;

        if (defined('rsssl_pp_version') ) return;

        //does it exist?
        if (!file_exists($this->htaccess_file())) return;

        //check if editing is blocked.
        if (RSSSL()->really_simple_ssl->do_not_edit_htaccess) return;

        //Get values for each security header
        $hsts = RSSSL()->really_simple_ssl->hsts;
        $content_security_policy = get_option('rsssl_content_security_policy');
        $x_xss_protection = get_option('rsssl_x_xss_protection');
        $x_content_type_options = get_option('rsssl_x_content_type_options');
        $no_referrer_when_downgrade = get_option('rsssl_no_referrer_when_downgrade');
        $expect_ct = get_option('rsssl_expect_ct');
        $x_frame_options = get_option('rsssl_x_frame_options');

        //on multisite, always use the network setting.
        if (is_multisite()) {
            $hsts = RSSSL()->rsssl_multisite->hsts;

            //but, if ONE of the sites has HSTS enabled, we assume we want it enabled.
            if (!$hsts) {
                $sites = RSSSL_PRO()->rsssl_licensing->get_sites_bw_compatible();
                foreach ( $sites as $site ) {
                    RSSSL()->really_simple_ssl->switch_to_blog_bw_compatible($site);
                    if (RSSSL()->really_simple_ssl->hsts) {
                        $hsts = true;
                        restore_current_blog();
                        break;
                    }
                    restore_current_blog(); //switches back to previous blog, not current, so we have to do it each loop
                }
            }
        }

        $htaccess = file_get_contents($this->htaccess_file());
        if (!is_writable($this->htaccess_file())) return;

        //remove current rules from file, if any.
        $htaccess = preg_replace("/#\s?BEGIN\s?Really_Simple_SSL_SECURITY_HEADERS.*?#\s?END\s?Really_Simple_SSL_SECURITY_HEADERS/s", "", $htaccess);
        $htaccess = preg_replace("/\n+/","\n", $htaccess);
        $rule = "";

        if ($hsts) {
            $hsts_preload = get_option("rsssl_hsts_preload");
            if ($hsts_preload){
                $rule .= 'Header always set Strict-Transport-Security: "max-age=63072000; includeSubDomains; preload" env=HTTPS'."\n";
            } else {
                $rule .= 'Header always set Strict-Transport-Security: "max-age=31536000" env=HTTPS'."\n";
            }
        }

        if ($content_security_policy) {
            if(is_multisite() && !RSSSL()->rsssl_multisite->ssl_enabled_networkwide) {
                //Don't enter rule
                $rule .= "";
            } else {
                $rule .= 'Header always set Content-Security-Policy "upgrade-insecure-requests"' . "\n";
            }
        }

        if ($x_xss_protection) {
            $rule .='Header always set X-XSS-Protection "1; mode=block"' ."\n";
        }

        if ($x_content_type_options) {
            $rule .='Header always set X-Content-Type-Options "nosniff"' ."\n";
        }

        if ($no_referrer_when_downgrade) {
            $rule .='Header always set Referrer-Policy: "no-referrer-when-downgrade"' ."\n";
        }

        if ($expect_ct) {
            $rule .= 'Header always set Expect-CT "max-age=7776000, enforce"' ."\n";
        }
        if ($x_frame_options) {
            $rule .= 'Header always set X-Frame-Options "sameorigin"' ."\n";
        }

        //wrap rules
        if (strlen($rule)>0){
            $rules = "\n"."# BEGIN Really_Simple_SSL_SECURITY_HEADERS"."\n";
            $rules .= "<IfModule mod_headers.c>"."\n";
            $rules .= $rule;
            $rules .= "</IfModule>"."\n";
            $rules .= "# END Really_Simple_SSL_SECURITY_HEADERS"."\n";
            $rules = preg_replace("/\n+/","\n", $rules);
        }

        if (strpos($htaccess, 'Really_Simple_SSL_SECURITY_HEADERS')!==false){
            //replace existing set
            $htaccess = preg_replace("/#\s?BEGIN\s?Really_Simple_SSL_SECURITY_HEADERS.*?#\s?END\s?Really_Simple_SSL_SECURITY_HEADERS/s", $rules, $htaccess);
        } else {
            //Only add if there are rules to add
            if (!empty($rules)) {
            //nothing yet, insert fresh set
            $wptag = "# BEGIN WordPress";
                if (strpos($htaccess, $wptag) !== false) {
                    $htaccess = str_replace($wptag, $rules . $wptag, $htaccess);
                } else {
                    $htaccess = $htaccess . $rules;
                }
            }
        }

        file_put_contents($this->htaccess_file(), $htaccess);

    }

    public function activation_notice_color($class){
	    $result = RSSSL_PRO()->rsssl_scan->scan_completed_no_errors();
	    if ($result == "COMPLETED") {
	        $class = 'rsssl-scan-completed';
	    }
	    return $class;
    }

    public function show_scan_buttons_before_activation()
    {
        //prevent showing the review on edit screen, as gutenberg removes the class which makes it editable.
        $screen = get_current_screen();
        if ( $screen->parent_base === 'edit' ) return;

        $result = RSSSL_PRO()->rsssl_scan->scan_completed_no_errors();

        if ($result == "COMPLETED") {
            ?>
            <div class="rsssl-scan-text-in-activate-notice"><?php _e("You finished a scan without errors.", "really-simple-ssl-pro") ?></div>
        <?php } elseif ($result == "NEVER") { ?>
            <div class="rsssl-scan-text-in-activate-notice">
                <p>
                    <?php
                    $link_start = '<a href="options-general.php?page=rlrsssl_really_simple_ssl&tab=scan">';
                    $link_close = "</a> ";
                    echo sprintf(__("No scan completed yet. Before migrating to SSL, you should do a %sscan%s", "really-simple-ssl-pro"), $link_start, $link_close);
                    ?>
                </p>
            </div>
        <?php } else { ?>
            <div class="rsssl-scan-text-in-activate-notice">
                <p><?php _e("Previous scan completed with issues", "really-simple-ssl-pro"); ?></p>
            </div>
        <?php } ?>

        <div class="rsssl-scan-button">
            <form action="" method="post">
                <?php

                if ($result != "NEVER") {
                    $link_start = '<a href="options-general.php?page=rlrsssl_really_simple_ssl&tab=scan" class="button button-primary">';
                    $link_close = "</a> ";
                    echo sprintf(__("%sScan again%s", "really-simple-ssl-pro"), $link_start, $link_close);

                } else {
                    $link_start = '<a href="options-general.php?page=rlrsssl_really_simple_ssl&tab=scan" class="button button-primary">';
                    $link_close = "</a> ";
                    echo sprintf(__("%sScan for issues%s", "really-simple-ssl-pro"), $link_start, $link_close);
                    wp_nonce_field('rsssl_nonce', 'rsssl_nonce');
                }
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * removes the added redirect to https rules to the .htaccess file.
     *
     * @since  2.0
     *
     * @access public
     *
     **/

    public function remove_security_headers() {
        if (!current_user_can("activate_plugins")) return;
        if(file_exists($this->htaccess_file()) && is_writable($this->htaccess_file())){
            $htaccess = file_get_contents($this->htaccess_file());

            $htaccess = preg_replace("/#\s?BEGIN\s?Really_Simple_SSL_SECURITY_HEADERS.*?#\s?END\s?Really_Simple_SSL_SECURITY_HEADERS/s", "", $htaccess);
            $htaccess = preg_replace("/\n+/","\n", $htaccess);

            file_put_contents($this->htaccess_file(), $htaccess);
        }
    }


    public function insert_secure_cookie_settings(){
        if (!current_user_can("activate_plugins")) return;

        //only if this site has SSL activated.
        if (!RSSSL()->really_simple_ssl->ssl_enabled) return;

        //do not set on per page installations
        if (defined('rsssl_pp_version')) return;

        //only if cookie settings were not inserted yet
        if (!$this->contains_secure_cookie_settings() ) {
            $wpconfig_path = RSSSL()->really_simple_ssl->find_wp_config_path();
            $wpconfig = file_get_contents($wpconfig_path);
            if ((strlen($wpconfig)!=0) && is_writable($wpconfig_path)) {
                $rule  = "\n"."//Begin Really Simple SSL session cookie settings"."\n";
                $rule .= "@ini_set('session.cookie_httponly', true);"."\n";
                $rule .= "@ini_set('session.cookie_secure', true);"."\n";
                $rule .= "@ini_set('session.use_only_cookies', true);"."\n";
                $rule .= "//END Really Simple SSL"."\n";

                $insert_after = "<?php";
                $pos = strpos($wpconfig, $insert_after);
                if ($pos !== false) {
                    $wpconfig = substr_replace($wpconfig,$rule,$pos+1+strlen($insert_after),0);
                }

                file_put_contents($wpconfig_path, $wpconfig);
            }
        }

    }

    /**
     * remove secure cookie settings
     *
     * @since  2.1
     *
     * @access public
     *
     */

    public function remove_secure_cookie_settings() {
        if (!current_user_can("activate_plugins")) return;

        $wpconfig_path = RSSSL()->really_simple_ssl->find_wp_config_path();
        if (!empty($wpconfig_path)) {
            $wpconfig = file_get_contents($wpconfig_path);
            $wpconfig = preg_replace("/\/\/Begin\s?Really\s?Simple\s?SSL\s?session\s?cookie\s?settings.*?\/\/END\s?Really\s?Simple\s?SSL/s", "", $wpconfig);
            $wpconfig = preg_replace("/\n+/","\n", $wpconfig);
            file_put_contents($wpconfig_path, $wpconfig);
        }
    }

    //Show notice for the cookie settings
    public function show_notice_wpconfig_not_writable(){
        //prevent showing the review on edit screen, as gutenberg removes the class which makes it editable.
        $screen = get_current_screen();
        if ( $screen->parent_base === 'edit' ) return;

        if (!current_user_can("activate_plugins")) return;

        //only if this site has SSL activated.
        if (!RSSSL()->really_simple_ssl->ssl_enabled) return;

        //do not set on per page installations
        if (defined('rsssl_pp_version')) return;

        if (!$this->contains_secure_cookie_settings()) {

            ?>
            <div id="message" class="error notice">
                <h1><?php echo __("Could not insert httponly secure cookie settings.","really-simple-ssl-pro");?></h1>

                <p><?php echo __("To set the httponly secure cookie settings, your wp-config.php has to be edited, but the file is not writable.","really-simple-ssl-pro");?></p>
                <p><?php echo __("Add the following lines of code to your wp-config.php.","really-simple-ssl-pro");?>

                    <br><br><code>
                        //Begin Really Simple SSL session cookie settings <br>
                        &nbsp;&nbsp;@ini_set('session.cookie_httponly', true); <br>
                        &nbsp;&nbsp;@ini_set('session.cookie_secure', true); <br>
                        &nbsp;&nbsp;@ini_set('session.use_only_cookies', true); <br>
                        //END Really Simple SSL cookie settings <br>
                    </code><br>
                </p>
                <p><?php echo __("Or set your wp-config.php to writable and reload this page.", "really-simple-ssl-pro");?></p>
            </div>
            <?php
        }
    }

    public function show_notice_upgrade_pro_multisite()
    {
        if (is_multisite()) {

            if (!get_site_option("rsssl_pro_multisite_message_shown")) {

                //First determine the license. If we cannot detect the license, don't show the message at all because we can't show the correct discount code.

                $discount_code = "";

                if (RSSSL_PRO()->rsssl_licensing->get_license_activation_limit() == '1') {
                    $discount_code = "sg9uk5WH2JhT";
                } elseif (RSSSL_PRO()->rsssl_licensing->get_license_activation_limit() == '5') {
                    $discount_code = "Zhh2BfX7JJmQ";
                } elseif (RSSSL_PRO()->rsssl_licensing->get_license_activation_limit() == '0') {
                    $discount_code = "ff5qdtKfaDbV";
                }

                if (!$discount_code) return;

                $link_open = '<a target="_blank" href="https://really-simple-ssl.com/downloads/really-simple-ssl-pro-multisite/?discount=' . $discount_code . '">';
                $link_close = '</a>';
                ?>

                <div id="message" class="notice updated is-dismissible">
                    <h1><?php _e("Multisite detected", "really-simple-ssl-pro"); ?></h1>
                    <p><?php _e("You seem to be using a WordPress multisite installation. Did you know Really Simple SSL has a dedicated multisite plugin?", "really-simple-ssl-pro"); ?></p>
                    <p><?php printf(__("You can upgrade to the pro multisite plugin with a discount code equal to your Really Simple SSL pro purchase by visiting this %slink%s. The discount will be applied automatically on checkout.", "really-simple-ssl-soc"), $link_open, $link_close); ?></p>
                </div>
                <?php
            }
        }
    }

    public function show_notice_csp_enabled_next_steps()
    {

        if (get_option("rsssl_pro_csp_notice_next_steps_notice_dismissed") ) return;

            if (get_option('rsssl_enable_csp_reporting') ) {

                $link_open = '<a target="_blank" href="https://really-simple-ssl.com/knowledge-base/how-to-use-the-content-security-policy-generator/">';
                $link_close = '</a>';
                ?>

                <div id="message" class="notice updated is-dismissible">
                    <h1><?php _e("Content Security Policy reporting enabled", "really-simple-ssl-pro"); ?></h1>
                    <p><?php _e("Follow these steps to complete the setup:", "really-simple-ssl-pro"); ?></p>
                    <div><p><?php _e("- Let it gather data from the website for a couple of days", "really-simple-ssl-pro"); ?></p></div>
                    <div><p><?php _e("- Newly found rules can be found in the Content Security Policy tab.", "really-simple-ssl-pro"); ?></p></div>
                    <div><p><?php _e("- When no new exceptions have been found, you can enfore the Content Security Policy rules by enabling the 'Enfore' option.", "really-simple-ssl-pro"); ?></p></div>
                    <div><p><?php printf(__("- For a detailed explanation of the Content Security Policy, see this %slink%s.", "really-simple-ssl-soc"), $link_open, $link_close); ?></p></div>
                </div>
                <?php
            }
    }

    /*

        @TODO remove function reference in favor of this same function in core plugin.
        Next version

    */

    public function contains_secure_cookie_settings() {
        $wpconfig_path = RSSSL()->really_simple_ssl->find_wp_config_path();

        if (!$wpconfig_path) return false;

        $wpconfig = file_get_contents($wpconfig_path);
        if ( (strpos($wpconfig, "//Begin Really Simple SSL session cookie settings")===FALSE) && (strpos($wpconfig, "cookie_httponly")===FALSE) ) {
            return false;
        }

        return true;
    }

    /*
    * Dissmiss Pro multisite notice callback
    */

    public function dismiss_pro_multisite_notice_callback() {
        if (!current_user_can('manage_options')) return;
        check_ajax_referer('really-simple-ssl-dismiss', 'security');

        update_site_option("rsssl_pro_multisite_message_shown", true);
        wp_die();
    }

    public function dismiss_csp_next_steps_notice_callback() {
        if (!current_user_can('manage_options')) return;
        check_ajax_referer('really-simple-ssl-dismiss', 'security');

        update_option("rsssl_pro_csp_notice_next_steps_notice_dismissed", true);
        wp_die();
    }

    /*
    * Show a notice on security headers when NGINX is used as a webserver
    */

    public function show_nginx_headers_notice() {
        //prevent showing the review on edit screen, as gutenberg removes the class which makes it editable.
        $screen = get_current_screen();
        if ( $screen->parent_base === 'edit' ) return;

        if( !is_multisite() ) {
            if (RSSSL()->rsssl_server->get_server() === 'nginx' && !get_site_option("rsssl_nginx_message_shown")) {
                ?>
                <div id="message" class="notice updated is-dismissible">
                    <p>
                        <?php _e("Really Simple SSL has detected NGINX as webserver. The security headers are currently set using PHP which can cause issues with caching. To enable the headers directly in NGINX add the following line(s) to the NGINX server block within your NGINX configuration:"); ?>
                        <br> <br>

                        <?php if ((RSSSL()->really_simple_ssl->hsts) && (!get_option('rsssl_hsts_preload'))) { ?>
                            <code>add_header Strict-Transport-Security: max-age=31536000</code> <br> <br>
                            <?php
                            if (get_option('rsssl_hsts_preload')) { ?>
                                <code>add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;</code> <br>
                                <br>
                            <?php }
                            $link_start = '<a target="_blank" href="https://www.nginx.com/blog/http-strict-transport-security-hsts-and-nginx">';
                            $link_close = "</a> ";
                            echo sprintf(__("For more information about NGINX and HSTS see %sHTTP Strict Transport Security and NGINX%s", "really-simple-ssl-pro"), $link_start, $link_close);
                        }

                        if (get_option('rsssl_x_xss_protection') ) { ?>
                            <code>add_header x-xss-protection "1; mode=block" always;</code> <br> <br>
                            <?php
                        }

                        if (get_option('rsssl_x_content_type_options') ) { ?>
                            <code>add_header X-Content-Type-Options "nosniff";</code> <br> <br>
                            <?php
                        }

                        if (get_option('rsssl_no_referrer_when_downgrade')) { ?>
                            <code>add_header Referrer-Policy: "no-referrer-when-downgrade";</code> <br> <br>
                        <?php }

                        if (get_option('rsssl_expect_ct')) { ?>
                            <code>add_header Expect-CT "max-age=7776000, enforce";</code> <br> <br>
                         <?php }
                        if (get_option('rsssl_x_frame_options')) { ?>
                            <code>add_header X-Frame-Options "sameorigin";</code>
                         <?php }

                        ?>
                    </p>
                </div>
                <?php
            }
        }
    }

    /*
    * Ajax call for the NGINX notice
    */

    public function insert_pro_multisite_notice_success() {
        if (!get_site_option("rsssl_pro_multisite_message_shown")) {
            $ajax_nonce = wp_create_nonce( "really-simple-ssl-dismiss" );
            ?>
            <script type='text/javascript'>
                jQuery(document).ready(function($) {
                    $(".notice.updated.is-dismissible").on("click", ".notice-dismiss", function(event){
                        var data = {
                            'action': 'dismiss_success_pro_multisite_notice',
                            'security': '<?php echo $ajax_nonce; ?>'
                        };

                        $.post(ajaxurl, data, function(response) {

                        });
                    });
                });
            </script>
            <?php
        }
    }

    public function insert_csp_next_steps_dismiss() {
        if (!get_option("rsssl_pro_csp_notice_next_steps_notice_dismissed") ) {
            $ajax_nonce = wp_create_nonce( "really-simple-ssl-dismiss" );
            ?>
            <script type='text/javascript'>
                jQuery(document).ready(function($) {
                    $(".notice.updated.is-dismissible").on("click", ".notice-dismiss", function(event){
                        var data = {
                            'action': 'dismiss_csp_next_steps_notice',
                            'security': '<?php echo $ajax_nonce; ?>'
                        };

                        $.post(ajaxurl, data, function(response) {

                        });
                    });
                });
            </script>
            <?php
        }
    }

    public function dismiss_nginx_message_callback() {
        //nonce check fails if url is changed to ssl.
        //check_ajax_referer( 'really-simple-ssl-dismiss', 'security' );
        update_site_option("rsssl_nginx_message_shown", true);
        wp_die();
    }

    /*
    * Ajax call for the NGINX notice
    */

    public function insert_nginx_dismiss_success() {
        if (!get_site_option("rsssl_nginx_message_shown")) {
            $ajax_nonce = wp_create_nonce( "really-simple-ssl-dismiss" );
            ?>
            <script type='text/javascript'>
                jQuery(document).ready(function($) {
                    $(".notice.updated.is-dismissible").on("click", ".notice-dismiss", function(event){
                        var data = {
                            'action': 'dismiss_success_message_nginx',
                            'security': '<?php echo $ajax_nonce; ?>'
                        };

                        $.post(ajaxurl, data, function(response) {

                        });
                    });
                });
            </script>
            <?php
        }
    }

    public function maybe_clear_certificate_check_schedule($oldvalue, $newvalue, $option){

        if (!get_option('rsssl_cert_expiration_warning')){
            wp_clear_scheduled_hook('rsssl_pro_daily_hook');
        }
    }

    /**
     * @Since 2.0
     *
     * Check if site uses an htaccess.conf file, used in bitnami installations
     *
     */

    public function uses_htaccess_conf() {
        $htaccess_conf_file = dirname(ABSPATH) . "/conf/htaccess.conf";
        //conf/htaccess.conf can be outside of open basedir, return false if so
        $open_basedir = ini_get("open_basedir");

        if (!empty($open_basedir)) return false;

        if (is_file($htaccess_conf_file)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return string
     *
     * since 2.0
     *
     * Determine the htaccess file. This can be either the regular .htaccess file, or an htaccess.conf file on bitnami installations.
     *
     *
     */

    public function htaccess_file() {

        if ($this->uses_htaccess_conf()) {
            $htaccess_file = dirname(RSSSL()->really_simple_ssl->ABSpath) . "/conf/htaccess.conf";
        } else {
            $htaccess_file = RSSSL()->really_simple_ssl->ABSpath . ".htaccess";
        }

        return $htaccess_file;
    }

    /**
     * Returns a success, error or warning image for the settings page
     *
     * @since  2.0
     *
     * @access public
     *
     * @param string $type the type of image
     *
     * @return html string
     */

    public function img($type) {
        if ($type=='success') {
            return "<img class='rsssl-icons' src='".rsssl_pro_url."img/check-icon.png' alt='success'>";
        } elseif ($type=="error") {
            return "<img class='rsssl-icons' src='".rsssl_pro_url."img/cross-icon.png' alt='error'>";
        } else {
            return "<img class='rsssl-icons' src='".rsssl_pro_url."img/warning-icon.png' alt='warning'>";
        }
    }

    /**
     * Check to see if we are on the settings page, action hook independent
     *
     * @since  2.5
     *
     * @access public
     *
     */

    public function is_settings_page()
    {
        if (!isset($_SERVER['QUERY_STRING'])) return false;

        parse_str($_SERVER['QUERY_STRING'], $params);
        if (array_key_exists("page", $params) && ($params["page"] == "rlrsssl_really_simple_ssl")) {
            return true;
        }
        return false;
    }

}//class closure

function rsssl_pro_certificate_renewal()
{
    if (is_ssl() && get_option('rsssl_cert_expiration_warning') || (is_multisite() && RSSSL()->rsssl_multisite->cert_expiration_warning)) {

        $expiring  = rsssl_pro_almost_expired();

        if ($expiring) {
            return 'expiring';
        }
    }
    return 'not-expiring';
}

function rsssl_pro_renewal_notice_enabled()
{
    if (get_option('rsssl_cert_expiration_warning')) {
        return true;
    } else {
        return false;
    }
}

function rsssl_pro_hsts_enabled()
{
    if (RSSSL()->really_simple_ssl->contains_hsts()) {
        return 'hsts-set';
    } elseif (RSSSL_PRO()->rsssl_premium_options->uses_php_header_for_hsts()) {
        return 'hsts-set-php';
    } else {
        return 'hsts-not-set';
    }
}

function rsssl_pro_hsts_preload()
{
    $preload_enabled = get_option('rsssl_hsts_preload');
    if (RSSSL()->really_simple_ssl->hsts && $preload_enabled) {
         return 'hsts-preload-set';
    }
    return 'hsts-preload-not-set';
}

function rsssl_pro_secure_cookies_set()
{
    if (!is_multisite() || (is_multisite() && RSSSL()->rsssl_multisite->ssl_enabled_networkwide) ) {

        if (RSSSL_PRO()->rsssl_premium_options->contains_secure_cookie_settings()) {
            return 'set';
        }
    }
    return 'not-set';
}

function rsssl_pro_check_for_new_csp_entries()
{

    global $wpdb;

    $table_name = $wpdb->prefix . "rsssl_csp_log";
    //Check if there are any inpolicy values that are not true. If so, new rules can be added to the Content Security Policy. Show a warning in dashboard when new rules can be added, if all rules have been added show a checkmark
    $count = $wpdb->get_var("SELECT count(*) FROM $table_name where inpolicy != 'true'");

    if ($count>0) {
        return 'new-csp-rules';
    }
    return 'no-new-csp-rules';
}

function rsssl_pro_scan_notice()
{
    if (!RSSSL()->really_simple_ssl->site_has_ssl) {
        if (RSSSL_PRO()->rsssl_scan->scan_completed_no_errors() == "COMPLETED") {
            return 'has-ssl-no-scan-errors';
        } elseif (RSSSL_PRO()->rsssl_scan->scan_completed_no_errors() == "ERRORS") {
            return 'has-ssl-scan-has-errors';
        } else {
            return 'has-ssl-no-scan-done';
        }
    } else {
        if (RSSSL_PRO()->rsssl_scan->scan_completed_no_errors() == "COMPLETED") {
            return 'no-ssl-no-scan-errors';
        } elseif (RSSSL_PRO()->rsssl_scan->scan_completed_no_errors() == "ERRORS") {
            return 'no-ssl-scan-has-errors';
        } else {
            return 'no-ssl-no-scan-done';
        }
    }
}

function rsssl_pro_is_license_expired()
{
    $status = RSSSL_PRO()->rsssl_licensing->get_license_status();
    if (!$status) return 'not-activated';
    if($status == 'expired') {
        return 'expired';
    } elseif ($status == 'site_inactive') {
        return 'not-activated';
    }
}

function rsssl_pro_no_multisite() {
	if (!is_multisite()) {
		return true;
	} else {
		return false;
	}
}
