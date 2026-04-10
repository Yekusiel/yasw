<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YASW_Admin_Settings {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_yasw_save_donation_types', array( $this, 'ajax_save_donation_types' ) );
        add_action( 'wp_ajax_yasw_get_donations', array( $this, 'ajax_get_donations' ) );
        add_action( 'wp_ajax_yasw_get_donation_detail', array( $this, 'ajax_get_donation_detail' ) );
    }

    public function add_menu_pages() {
        // Top-level menu (no page)
        add_menu_page(
            'YASW Donations',
            'YASW Donations',
            'manage_options',
            'yasw-donations',
            null,
            'dashicons-money-alt',
            30
        );

        // Donations submenu (first, so it appears on top)
        add_submenu_page(
            'yasw-donations',
            'Donations',
            'Donations',
            'manage_options',
            'yasw-donations',
            array( $this, 'render_donations_page' )
        );

        // Settings submenu
        add_submenu_page(
            'yasw-donations',
            'Settings',
            'Settings',
            'manage_options',
            'yasw-donations-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        // Sandbox mode toggle
        register_setting( 'yasw_donations_settings', 'yasw_sandbox_mode', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'yes',
        ) );

        register_setting( 'yasw_donations_settings', 'yasw_sandbox_email', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default'           => '',
        ) );

        // Donation types
        register_setting( 'yasw_donations_settings', 'yasw_donation_types', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_donation_types' ),
            'default'           => array(),
        ) );

        // Sola sandbox (global)
        register_setting( 'yasw_donations_settings', 'yasw_sola_sandbox_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'yasw_donations_settings', 'yasw_sola_sandbox_ifields', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        // Sola per-type keys stored as serialized array
        register_setting( 'yasw_donations_settings', 'yasw_sola_keys', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_sola_keys' ),
            'default'           => array(),
        ) );

        // The Donors Fund - Sandbox API Key
        register_setting( 'yasw_donations_settings', 'yasw_daf_sandbox_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        // The Donors Fund - Production API Key
        register_setting( 'yasw_donations_settings', 'yasw_daf_production_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        // The Donors Fund - Sandbox
        register_setting( 'yasw_donations_settings', 'yasw_daf_sandbox_validation_token', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'yasw_donations_settings', 'yasw_daf_sandbox_account_number', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'yasw_donations_settings', 'yasw_daf_sandbox_tax_id', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        // The Donors Fund - Production
        register_setting( 'yasw_donations_settings', 'yasw_daf_production_validation_token', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'yasw_donations_settings', 'yasw_daf_production_account_number', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'yasw_donations_settings', 'yasw_daf_production_tax_id', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        // OJC Fund
        register_setting( 'yasw_donations_settings', 'yasw_ojc_auth_token', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'yasw_donations_settings', 'yasw_ojc_sandbox_org_id', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'yasw_donations_settings', 'yasw_ojc_production_org_id', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        // Pledger
        register_setting( 'yasw_donations_settings', 'yasw_pledger_bearer_token', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'yasw_donations_settings', 'yasw_pledger_sandbox_tax_id', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'yasw_donations_settings', 'yasw_pledger_tax_id', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'yasw_donations_settings', 'yasw_pledger_charity_name', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        // Email templates — register fields for both types
        $email_types = array( 'admin_notification', 'donor_receipt' );
        $text_fields = array( 'send_to', 'from_name', 'from_email', 'reply_to', 'cc', 'bcc', 'subject' );

        foreach ( $email_types as $type ) {
            register_setting( 'yasw_donations_settings', "yasw_email_{$type}_enabled", array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'yes',
            ) );

            foreach ( $text_fields as $field ) {
                register_setting( 'yasw_donations_settings', "yasw_email_{$type}_{$field}", array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => '',
                ) );
            }

            register_setting( 'yasw_donations_settings', "yasw_email_{$type}_message", array(
                'type'              => 'string',
                'sanitize_callback' => 'wp_kses_post',
                'default'           => '',
            ) );
        }
    }

    public function sanitize_donation_types( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }
        $clean = array();
        foreach ( $input as $type ) {
            $slug  = sanitize_title( $type['slug'] ?? '' );
            $label = sanitize_text_field( $type['label'] ?? '' );
            if ( $slug && $label ) {
                $clean[] = array( 'slug' => $slug, 'label' => $label );
            }
        }
        return $clean;
    }

    public function sanitize_sola_keys( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }
        $clean = array();
        foreach ( $input as $slug => $keys ) {
            $clean_slug = sanitize_title( $slug );
            $clean[ $clean_slug ] = array(
                'api_key' => sanitize_text_field( $keys['api_key'] ?? '' ),
                'ifields' => sanitize_text_field( $keys['ifields'] ?? '' ),
            );
        }
        return $clean;
    }

    public function enqueue_admin_assets( $hook ) {
        $allowed = array(
            'toplevel_page_yasw-donations',
            'yasw-donations_page_yasw-donations-settings',
        );
        if ( ! in_array( $hook, $allowed, true ) ) {
            return;
        }

        wp_enqueue_style(
            'yasw-admin',
            YASW_DONATIONS_URL . 'assets/css/admin-settings.css',
            array(),
            YASW_DONATIONS_VERSION
        );
        wp_enqueue_script(
            'yasw-admin',
            YASW_DONATIONS_URL . 'assets/js/admin-settings.js',
            array( 'jquery' ),
            YASW_DONATIONS_VERSION,
            true
        );
        wp_localize_script( 'yasw-admin', 'yaswAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'yasw_admin_nonce' ),
        ) );
    }

    /**
     * Get donation types from options, with fallback defaults.
     */
    public static function get_donation_types() {
        $types = get_option( 'yasw_donation_types', array() );
        if ( empty( $types ) ) {
            $types = array(
                array( 'slug' => 'general',     'label' => 'General Donation' ),
                array( 'slug' => 'building',    'label' => 'Building Fund' ),
                array( 'slug' => 'scholarship', 'label' => 'Scholarship Fund' ),
                array( 'slug' => 'torah',       'label' => 'Torah Fund' ),
                array( 'slug' => 'other',       'label' => 'Other' ),
            );
        }
        return $types;
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $donation_types = self::get_donation_types();
        $sola_keys      = get_option( 'yasw_sola_keys', array() );
        $sandbox_mode   = get_option( 'yasw_sandbox_mode', 'yes' );
        ?>
        <div class="wrap yasw-admin-wrap">
            <h1>YASW Donations Settings</h1>

            <form method="post" action="options.php" id="yasw-settings-form">
                <?php settings_fields( 'yasw_donations_settings' ); ?>

                <!-- Tab Navigation -->
                <nav class="nav-tab-wrapper yasw-tabs">
                    <a href="#general" class="nav-tab nav-tab-active" data-tab="general">General Settings</a>
                    <a href="#donation-types" class="nav-tab" data-tab="donation-types">Donation Types</a>
                    <a href="#sola" class="nav-tab" data-tab="sola">Sola (Credit Card)</a>
                    <a href="#donors-fund" class="nav-tab" data-tab="donors-fund">The Donors Fund</a>
                    <a href="#ojc-fund" class="nav-tab" data-tab="ojc-fund">OJC Fund</a>
                    <a href="#pledger" class="nav-tab" data-tab="pledger">Pledger</a>
                    <a href="#email-admin" class="nav-tab" data-tab="email-admin">Admin Notification</a>
                    <a href="#email-receipt" class="nav-tab" data-tab="email-receipt">Donor Receipt</a>
                </nav>

                <!-- ============================================================
                     TAB: General Settings
                     ============================================================ -->
                <div class="yasw-tab-content active" id="tab-general">
                    <h2>General Settings</h2>

                    <div class="yasw-sandbox-toggle <?php echo $sandbox_mode === 'yes' ? 'yasw-sandbox-active' : 'yasw-sandbox-inactive'; ?>">
                        <label class="yasw-toggle-label">
                            <span class="yasw-toggle-switch">
                                <input type="checkbox" name="yasw_sandbox_mode" value="yes" <?php checked( $sandbox_mode, 'yes' ); ?>>
                                <span class="yasw-toggle-slider"></span>
                            </span>
                            <span class="yasw-toggle-text">
                                <strong>Sandbox Mode</strong> — Use sandbox/test credentials for all payment gateways
                            </span>
                        </label>
                        <div class="yasw-sandbox-email-field" style="margin-top:10px;margin-left:52px;<?php echo $sandbox_mode !== 'yes' ? 'display:none;' : ''; ?>">
                            <label for="yasw-sandbox-email">
                                <strong>Sandbox Email Override</strong> — All emails (admin &amp; donor) will be sent to this address instead
                            </label>
                            <input type="email" id="yasw-sandbox-email" name="yasw_sandbox_email" value="<?php echo esc_attr( get_option( 'yasw_sandbox_email', '' ) ); ?>" class="regular-text" placeholder="test@example.com" style="display:block;margin-top:6px;">
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     TAB: Donation Types
                     ============================================================ -->
                <div class="yasw-tab-content" id="tab-donation-types">
                    <h2>Donation Types</h2>
                    <p class="description">Manage the options that appear in the Donation Type dropdown on the frontend form.</p>

                    <table class="widefat yasw-donation-types-table" id="yasw-donation-types-table">
                        <thead>
                            <tr>
                                <th style="width:40px;"></th>
                                <th>Label (displayed to donors)</th>
                                <th>Slug (internal identifier)</th>
                                <th style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $donation_types as $i => $type ) : ?>
                            <tr class="yasw-type-row">
                                <td class="yasw-drag-handle">&#9776;</td>
                                <td>
                                    <input type="text" name="yasw_donation_types[<?php echo $i; ?>][label]" value="<?php echo esc_attr( $type['label'] ); ?>" class="regular-text yasw-type-label">
                                </td>
                                <td>
                                    <input type="text" name="yasw_donation_types[<?php echo $i; ?>][slug]" value="<?php echo esc_attr( $type['slug'] ); ?>" class="regular-text yasw-type-slug" pattern="[a-z0-9\-_]+" title="Lowercase letters, numbers, hyphens, underscores only">
                                </td>
                                <td>
                                    <button type="button" class="button yasw-remove-type" title="Remove">&times;</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p>
                        <button type="button" class="button" id="yasw-add-type">+ Add Donation Type</button>
                    </p>
                </div>

                <!-- ============================================================
                     TAB: Sola (Credit Card)
                     ============================================================ -->
                <div class="yasw-tab-content" id="tab-sola">
                    <h2>Sola — Credit Card Processing</h2>

                    <h3>Sandbox Credentials (Global)</h3>
                    <table class="form-table">
                        <tr>
                            <th><label for="yasw_sola_sandbox_api_key">API Key</label></th>
                            <td><input type="text" id="yasw_sola_sandbox_api_key" name="yasw_sola_sandbox_api_key" value="<?php echo esc_attr( get_option( 'yasw_sola_sandbox_api_key', '' ) ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="yasw_sola_sandbox_ifields">iFields Key</label></th>
                            <td><input type="text" id="yasw_sola_sandbox_ifields" name="yasw_sola_sandbox_ifields" value="<?php echo esc_attr( get_option( 'yasw_sola_sandbox_ifields', '' ) ); ?>" class="regular-text"></td>
                        </tr>
                    </table>

                    <hr>

                    <h3>Production Credentials (Per Donation Type)</h3>
                    <p class="description">Each donation type can have its own Sola API keys. Transactions will use the keys matching the selected donation type.</p>

                    <?php foreach ( $donation_types as $type ) :
                        $slug    = $type['slug'];
                        $label   = $type['label'];
                        $api_key = $sola_keys[ $slug ]['api_key'] ?? '';
                        $ifields = $sola_keys[ $slug ]['ifields'] ?? '';
                    ?>
                    <div class="yasw-sola-type-section">
                        <h4><?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code></h4>
                        <table class="form-table">
                            <tr>
                                <th><label>API Key</label></th>
                                <td><input type="text" name="yasw_sola_keys[<?php echo esc_attr( $slug ); ?>][api_key]" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>iFields Key</label></th>
                                <td><input type="text" name="yasw_sola_keys[<?php echo esc_attr( $slug ); ?>][ifields]" value="<?php echo esc_attr( $ifields ); ?>" class="regular-text"></td>
                            </tr>
                        </table>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- ============================================================
                     TAB: The Donors Fund
                     ============================================================ -->
                <div class="yasw-tab-content" id="tab-donors-fund">
                    <h2>The Donors Fund</h2>

                    <h3>Sandbox Credentials</h3>
                    <table class="form-table">
                        <tr>
                            <th><label for="yasw_daf_sandbox_api_key">API Key</label></th>
                            <td><input type="text" id="yasw_daf_sandbox_api_key" name="yasw_daf_sandbox_api_key" value="<?php echo esc_attr( get_option( 'yasw_daf_sandbox_api_key', '' ) ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="yasw_daf_sandbox_validation_token">Validation Token</label></th>
                            <td><input type="text" id="yasw_daf_sandbox_validation_token" name="yasw_daf_sandbox_validation_token" value="<?php echo esc_attr( get_option( 'yasw_daf_sandbox_validation_token', '' ) ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="yasw_daf_sandbox_account_number">Account Number</label></th>
                            <td>
                                <input type="text" id="yasw_daf_sandbox_account_number" name="yasw_daf_sandbox_account_number" value="<?php echo esc_attr( get_option( 'yasw_daf_sandbox_account_number', '' ) ); ?>" class="regular-text">
                                <p class="description">7-digit account number</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="yasw_daf_sandbox_tax_id">Tax ID</label></th>
                            <td>
                                <input type="text" id="yasw_daf_sandbox_tax_id" name="yasw_daf_sandbox_tax_id" value="<?php echo esc_attr( get_option( 'yasw_daf_sandbox_tax_id', '' ) ); ?>" class="regular-text">
                                <p class="description">9-digit tax ID (e.g. 123456789 or 12-3456789)</p>
                            </td>
                        </tr>
                    </table>

                    <hr>

                    <h3>Production Credentials</h3>
                    <table class="form-table">
                        <tr>
                            <th><label for="yasw_daf_production_api_key">API Key</label></th>
                            <td><input type="text" id="yasw_daf_production_api_key" name="yasw_daf_production_api_key" value="<?php echo esc_attr( get_option( 'yasw_daf_production_api_key', '' ) ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="yasw_daf_production_validation_token">Validation Token</label></th>
                            <td><input type="text" id="yasw_daf_production_validation_token" name="yasw_daf_production_validation_token" value="<?php echo esc_attr( get_option( 'yasw_daf_production_validation_token', '' ) ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="yasw_daf_production_account_number">Account Number</label></th>
                            <td>
                                <input type="text" id="yasw_daf_production_account_number" name="yasw_daf_production_account_number" value="<?php echo esc_attr( get_option( 'yasw_daf_production_account_number', '' ) ); ?>" class="regular-text">
                                <p class="description">7-digit account number</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="yasw_daf_production_tax_id">Tax ID</label></th>
                            <td>
                                <input type="text" id="yasw_daf_production_tax_id" name="yasw_daf_production_tax_id" value="<?php echo esc_attr( get_option( 'yasw_daf_production_tax_id', '' ) ); ?>" class="regular-text">
                                <p class="description">9-digit tax ID (e.g. 123456789 or 12-3456789)</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ============================================================
                     TAB: OJC Fund
                     ============================================================ -->
                <div class="yasw-tab-content" id="tab-ojc-fund">
                    <h2>OJC Fund</h2>

                    <table class="form-table">
                        <tr>
                            <th><label for="yasw_ojc_auth_token">Auth Token</label></th>
                            <td>
                                <input type="text" id="yasw_ojc_auth_token" name="yasw_ojc_auth_token" value="<?php echo esc_attr( get_option( 'yasw_ojc_auth_token', '' ) ); ?>" class="regular-text">
                                <p class="description">Basic Auth token (same for sandbox and production)</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="yasw_ojc_sandbox_org_id">Sandbox Org ID</label></th>
                            <td><input type="text" id="yasw_ojc_sandbox_org_id" name="yasw_ojc_sandbox_org_id" value="<?php echo esc_attr( get_option( 'yasw_ojc_sandbox_org_id', '' ) ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="yasw_ojc_production_org_id">Production Org ID</label></th>
                            <td><input type="text" id="yasw_ojc_production_org_id" name="yasw_ojc_production_org_id" value="<?php echo esc_attr( get_option( 'yasw_ojc_production_org_id', '' ) ); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                </div>

                <!-- ============================================================
                     TAB: Pledger
                     ============================================================ -->
                <div class="yasw-tab-content" id="tab-pledger">
                    <h2>Pledger</h2>

                    <table class="form-table">
                        <tr>
                            <th><label for="yasw_pledger_bearer_token">Bearer Token</label></th>
                            <td>
                                <input type="text" id="yasw_pledger_bearer_token" name="yasw_pledger_bearer_token" value="<?php echo esc_attr( get_option( 'yasw_pledger_bearer_token', '' ) ); ?>" class="regular-text">
                                <p class="description">API bearer token for authentication</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="yasw_pledger_charity_name">Charity Name</label></th>
                            <td>
                                <input type="text" id="yasw_pledger_charity_name" name="yasw_pledger_charity_name" value="<?php echo esc_attr( get_option( 'yasw_pledger_charity_name', '' ) ); ?>" class="regular-text">
                                <p class="description">Organization name sent with grants</p>
                            </td>
                        </tr>
                    </table>

                    <hr>

                    <h3>Sandbox Tax ID</h3>
                    <table class="form-table">
                        <tr>
                            <th><label for="yasw_pledger_sandbox_tax_id">Tax ID</label></th>
                            <td>
                                <input type="text" id="yasw_pledger_sandbox_tax_id" name="yasw_pledger_sandbox_tax_id" value="<?php echo esc_attr( get_option( 'yasw_pledger_sandbox_tax_id', '' ) ); ?>" class="regular-text">
                                <p class="description">Tax ID used for sandbox/testing</p>
                            </td>
                        </tr>
                    </table>

                    <hr>

                    <h3>Production Tax ID</h3>
                    <table class="form-table">
                        <tr>
                            <th><label for="yasw_pledger_tax_id">Tax ID</label></th>
                            <td>
                                <input type="text" id="yasw_pledger_tax_id" name="yasw_pledger_tax_id" value="<?php echo esc_attr( get_option( 'yasw_pledger_tax_id', '' ) ); ?>" class="regular-text">
                                <p class="description">9-digit tax ID (e.g. 123456789 or 12-3456789)</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ============================================================
                     TAB: Admin Notification Email
                     ============================================================ -->
                <div class="yasw-tab-content" id="tab-email-admin">
                    <?php $this->render_email_tab( 'admin_notification', 'Admin Notification Email' ); ?>
                </div>

                <!-- ============================================================
                     TAB: Donor Receipt Email
                     ============================================================ -->
                <div class="yasw-tab-content" id="tab-email-receipt">
                    <?php $this->render_email_tab( 'donor_receipt', 'Donor Receipt Email' ); ?>
                </div>

                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render an email template settings tab.
     */
    private function render_email_tab( $type, $title ) {
        $prefix  = "yasw_email_{$type}";
        $enabled = get_option( "{$prefix}_enabled", 'yes' );

        // Defaults per type
        $defaults = array(
            'admin_notification' => array(
                'send_to'    => '{admin_email}',
                'from_name'  => 'YASW Donations',
                'from_email' => '{admin_email}',
                'reply_to'   => '{donor_email}',
                'cc'         => '',
                'bcc'        => '',
                'subject'    => 'New Donation from {donor_fname} {donor_lname}',
            ),
            'donor_receipt' => array(
                'send_to'    => '{donor_email}',
                'from_name'  => 'YASW Donations',
                'from_email' => '{admin_email}',
                'reply_to'   => '{admin_email}',
                'cc'         => '',
                'bcc'        => '',
                'subject'    => 'Thank you for your donation, {donor_fname}!',
            ),
        );
        $d = $defaults[ $type ] ?? $defaults['admin_notification'];
        ?>
        <h2><?php echo esc_html( $title ); ?></h2>

        <!-- Enable toggle -->
        <div class="yasw-email-toggle">
            <label class="yasw-toggle-label">
                <span class="yasw-toggle-switch">
                    <input type="checkbox" name="<?php echo esc_attr( "{$prefix}_enabled" ); ?>" value="yes" <?php checked( $enabled, 'yes' ); ?>>
                    <span class="yasw-toggle-slider"></span>
                </span>
                <span class="yasw-toggle-text"><strong>Enable this email</strong></span>
            </label>
        </div>

        <table class="form-table">
            <tr>
                <th><label for="<?php echo esc_attr( "{$prefix}_send_to" ); ?>">Send To</label></th>
                <td>
                    <input type="text" id="<?php echo esc_attr( "{$prefix}_send_to" ); ?>" name="<?php echo esc_attr( "{$prefix}_send_to" ); ?>" value="<?php echo esc_attr( get_option( "{$prefix}_send_to", $d['send_to'] ) ); ?>" class="large-text">
                    <p class="description">Comma-separated email addresses. Use <code>{donor_email}</code> or <code>{admin_email}</code>.</p>
                </td>
            </tr>
            <tr>
                <th><label for="<?php echo esc_attr( "{$prefix}_from_name" ); ?>">From Name</label></th>
                <td>
                    <input type="text" id="<?php echo esc_attr( "{$prefix}_from_name" ); ?>" name="<?php echo esc_attr( "{$prefix}_from_name" ); ?>" value="<?php echo esc_attr( get_option( "{$prefix}_from_name", $d['from_name'] ) ); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th><label for="<?php echo esc_attr( "{$prefix}_from_email" ); ?>">From Email</label></th>
                <td>
                    <input type="text" id="<?php echo esc_attr( "{$prefix}_from_email" ); ?>" name="<?php echo esc_attr( "{$prefix}_from_email" ); ?>" value="<?php echo esc_attr( get_option( "{$prefix}_from_email", $d['from_email'] ) ); ?>" class="regular-text">
                    <p class="description">Email address or <code>{admin_email}</code>.</p>
                </td>
            </tr>
            <tr>
                <th><label for="<?php echo esc_attr( "{$prefix}_reply_to" ); ?>">Reply To</label></th>
                <td>
                    <input type="text" id="<?php echo esc_attr( "{$prefix}_reply_to" ); ?>" name="<?php echo esc_attr( "{$prefix}_reply_to" ); ?>" value="<?php echo esc_attr( get_option( "{$prefix}_reply_to", $d['reply_to'] ) ); ?>" class="regular-text">
                    <p class="description">Email address, <code>{donor_email}</code>, or <code>{admin_email}</code>.</p>
                </td>
            </tr>
            <tr>
                <th><label for="<?php echo esc_attr( "{$prefix}_cc" ); ?>">CC</label></th>
                <td>
                    <input type="text" id="<?php echo esc_attr( "{$prefix}_cc" ); ?>" name="<?php echo esc_attr( "{$prefix}_cc" ); ?>" value="<?php echo esc_attr( get_option( "{$prefix}_cc", $d['cc'] ) ); ?>" class="large-text">
                    <p class="description">Comma-separated email addresses (optional).</p>
                </td>
            </tr>
            <tr>
                <th><label for="<?php echo esc_attr( "{$prefix}_bcc" ); ?>">BCC</label></th>
                <td>
                    <input type="text" id="<?php echo esc_attr( "{$prefix}_bcc" ); ?>" name="<?php echo esc_attr( "{$prefix}_bcc" ); ?>" value="<?php echo esc_attr( get_option( "{$prefix}_bcc", $d['bcc'] ) ); ?>" class="large-text">
                    <p class="description">Comma-separated email addresses (optional).</p>
                </td>
            </tr>
            <tr>
                <th><label for="<?php echo esc_attr( "{$prefix}_subject" ); ?>">Subject</label></th>
                <td>
                    <input type="text" id="<?php echo esc_attr( "{$prefix}_subject" ); ?>" name="<?php echo esc_attr( "{$prefix}_subject" ); ?>" value="<?php echo esc_attr( get_option( "{$prefix}_subject", $d['subject'] ) ); ?>" class="large-text">
                    <p class="description">Supports placeholders like <code>{donor_fname}</code>, <code>{donor_lname}</code>, <code>{donation_amount}</code>.</p>
                </td>
            </tr>
            <tr>
                <th><label>Message</label></th>
                <td>
                    <?php
                    $editor_id = str_replace( '-', '_', $prefix ) . '_message';
                    $content   = get_option( "{$prefix}_message", '' );
                    wp_editor( $content, $editor_id, array(
                        'textarea_name' => "{$prefix}_message",
                        'media_buttons' => false,
                        'textarea_rows' => 15,
                        'tinymce'       => array(
                            'toolbar1' => 'formatselect,|,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,undo,redo',
                            'toolbar2' => '',
                        ),
                    ) );
                    ?>
                </td>
            </tr>
        </table>

        <!-- Placeholder reference -->
        <div class="yasw-placeholder-guide">
            <h4>Available Placeholders</h4>
            <div class="yasw-placeholder-grid">
                <div class="yasw-placeholder-item"><code>{donor_fname}</code> <span>Donor first name</span></div>
                <div class="yasw-placeholder-item"><code>{donor_lname}</code> <span>Donor last name</span></div>
                <div class="yasw-placeholder-item"><code>{donor_email}</code> <span>Donor email address</span></div>
                <div class="yasw-placeholder-item"><code>{donor_phone}</code> <span>Donor phone number</span></div>
                <div class="yasw-placeholder-item"><code>{donor_address}</code> <span>Donor street address</span></div>
                <div class="yasw-placeholder-item"><code>{donor_zip}</code> <span>Donor ZIP code</span></div>
                <div class="yasw-placeholder-item"><code>{donation_amount}</code> <span>Total amount charged</span></div>
                <div class="yasw-placeholder-item"><code>{donation_type}</code> <span>Donation type label</span></div>
                <div class="yasw-placeholder-item"><code>{payment_method}</code> <span>Payment method name</span></div>
                <div class="yasw-placeholder-item"><code>{transaction_id}</code> <span>Transaction/confirmation ID</span></div>
                <div class="yasw-placeholder-item"><code>{donation_date}</code> <span>Date of donation</span></div>
                <div class="yasw-placeholder-item"><code>{donation_message}</code> <span>Donor's message</span></div>
                <div class="yasw-placeholder-item"><code>{admin_email}</code> <span>WordPress admin email</span></div>
                <div class="yasw-placeholder-item"><code>{installment_amount}</code> <span>Per-payment amount (charged today)</span></div>
                <div class="yasw-placeholder-item"><code>{installment_months}</code> <span>Number of installment payments</span></div>
                <div class="yasw-placeholder-item"><code>{all_fields}</code> <span>Full summary of all donation details</span></div>
            </div>
        </div>
        <?php
    }

    /* =========================================================================
       Donations List Page
       ========================================================================= */

    public function render_donations_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $donation_types = self::get_donation_types();
        ?>
        <div class="wrap yasw-admin-wrap">
            <div class="yasw-donations-header">
                <h1>Donations</h1>
                <div class="yasw-donations-stats" id="yasw-donations-stats"></div>
            </div>

            <!-- Filters -->
            <div class="yasw-donations-filters">
                <div class="yasw-filter-row">
                    <div class="yasw-filter-search">
                        <input type="text" id="yasw-search" class="yasw-filter-input" placeholder="Search by name, email, or transaction ID...">
                    </div>
                    <div class="yasw-filter-group">
                        <select id="yasw-filter-status" class="yasw-filter-select">
                            <option value="">All Statuses</option>
                            <option value="approved">Approved</option>
                            <option value="declined">Declined</option>
                            <option value="error">Error</option>
                            <option value="pending">Pending</option>
                            <option value="3ds_pending">3DS Pending</option>
                        </select>
                        <select id="yasw-filter-method" class="yasw-filter-select">
                            <option value="">All Methods</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="donors_fund">Donors Fund</option>
                            <option value="ojc_fund">OJC Fund</option>
                            <option value="pledger">Pledger</option>
                        </select>
                        <select id="yasw-filter-type" class="yasw-filter-select">
                            <option value="">All Donation Types</option>
                            <?php foreach ( $donation_types as $type ) : ?>
                            <option value="<?php echo esc_attr( $type['slug'] ); ?>"><?php echo esc_html( $type['label'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="yasw-filter-date-wrap">
                            <label class="yasw-filter-date-label" for="yasw-filter-date-from">Start Date</label>
                            <input type="date" id="yasw-filter-date-from" class="yasw-filter-input yasw-filter-date">
                        </div>
                        <div class="yasw-filter-date-wrap">
                            <label class="yasw-filter-date-label" for="yasw-filter-date-to">End Date</label>
                            <input type="date" id="yasw-filter-date-to" class="yasw-filter-input yasw-filter-date">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="yasw-donations-table-wrap">
                <table class="yasw-donations-table" id="yasw-donations-table">
                    <thead>
                        <tr>
                            <th class="yasw-col-id yasw-sortable" data-sort="id">#</th>
                            <th class="yasw-col-date yasw-sortable" data-sort="created_at">Date</th>
                            <th class="yasw-col-name yasw-sortable" data-sort="full_name">Donor</th>
                            <th class="yasw-col-type">Type</th>
                            <th class="yasw-col-method">Method</th>
                            <th class="yasw-col-amount yasw-sortable" data-sort="total">Amount</th>
                            <th class="yasw-col-status yasw-sortable" data-sort="status">Status</th>
                            <th class="yasw-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="yasw-donations-tbody">
                        <tr><td colspan="8" class="yasw-loading">Loading donations...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="yasw-pagination" id="yasw-pagination"></div>

            <!-- Detail Modal -->
            <div class="yasw-modal-overlay" id="yasw-modal-overlay" style="display:none;">
                <div class="yasw-modal">
                    <div class="yasw-modal-header">
                        <h2>Donation Details</h2>
                        <button type="button" class="yasw-modal-close" id="yasw-modal-close">&times;</button>
                    </div>
                    <div class="yasw-modal-body" id="yasw-modal-body"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /* =========================================================================
       AJAX: Get donations (paginated, filtered)
       ========================================================================= */

    public function ajax_get_donations() {
        check_ajax_referer( 'yasw_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'yasw_donations';

        $page     = max( 1, intval( $_POST['page'] ?? 1 ) );
        $per_page = 20;
        $offset   = ( $page - 1 ) * $per_page;

        // Build WHERE
        $where  = array( '1=1' );
        $params = array();

        $search = sanitize_text_field( $_POST['search'] ?? '' );
        if ( $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '(full_name LIKE %s OR email LIKE %s OR transaction_id LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $status = sanitize_text_field( $_POST['status'] ?? '' );
        if ( $status ) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        $method = sanitize_text_field( $_POST['method'] ?? '' );
        if ( $method ) {
            $where[]  = 'payment_method = %s';
            $params[] = $method;
        }

        $dtype = sanitize_text_field( $_POST['donation_type'] ?? '' );
        if ( $dtype ) {
            $where[]  = 'donation_type = %s';
            $params[] = $dtype;
        }

        $sandbox_filter = $_POST['sandbox'] ?? '';
        if ( $sandbox_filter !== '' ) {
            $where[]  = 'sandbox = %d';
            $params[] = intval( $sandbox_filter );
        } else {
            // Default: show sandbox transactions when sandbox mode is on, real ones when off
            $is_sandbox = get_option( 'yasw_sandbox_mode', 'yes' ) === 'yes' ? 1 : 0;
            $where[]    = 'sandbox = %d';
            $params[]   = $is_sandbox;
        }

        $date_from = sanitize_text_field( $_POST['date_from'] ?? '' );
        if ( $date_from ) {
            $where[]  = 'created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        $date_to = sanitize_text_field( $_POST['date_to'] ?? '' );
        if ( $date_to ) {
            $where[]  = 'created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $where );

        // Sort
        $allowed_sort = array( 'id', 'created_at', 'full_name', 'total', 'status' );
        $sort_col     = in_array( $_POST['sort'] ?? '', $allowed_sort, true ) ? $_POST['sort'] : 'created_at';
        $sort_dir     = strtoupper( $_POST['sort_dir'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

        // Count
        if ( ! empty( $params ) ) {
            $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params ) );
        } else {
            $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
        }

        // Fetch
        $query_params = array_merge( $params, array( $per_page, $offset ) );
        if ( ! empty( $params ) ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, donation_type, payment_method, amount, total, full_name, email, status, sandbox, created_at, transaction_id FROM {$table} WHERE {$where_sql} ORDER BY {$sort_col} {$sort_dir} LIMIT %d OFFSET %d",
                $query_params
            ) );
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, donation_type, payment_method, amount, total, full_name, email, status, sandbox, created_at, transaction_id FROM {$table} WHERE {$where_sql} ORDER BY {$sort_col} {$sort_dir} LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ) );
        }

        // Stats
        $stats = array(
            'total'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
            'approved' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'approved' ) ),
            'declined' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'declined' ) ),
            'sum'      => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(total),0) FROM {$table} WHERE status = %s", 'approved' ) ),
        );

        wp_send_json_success( array(
            'donations'  => $rows,
            'total'      => $count,
            'pages'      => ceil( $count / $per_page ),
            'page'       => $page,
            'per_page'   => $per_page,
            'stats'      => $stats,
        ) );
    }

    /* =========================================================================
       AJAX: Get single donation detail
       ========================================================================= */

    public function ajax_get_donation_detail() {
        check_ajax_referer( 'yasw_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        global $wpdb;
        $id = intval( $_POST['donation_id'] ?? 0 );

        $donation = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}yasw_donations WHERE id = %d",
            $id
        ) );

        if ( ! $donation ) {
            wp_send_json_error( 'Donation not found.' );
        }

        $errors = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}yasw_donation_errors WHERE donation_id = %d ORDER BY created_at DESC",
            $id
        ) );

        wp_send_json_success( array(
            'donation' => $donation,
            'errors'   => $errors,
        ) );
    }
}
