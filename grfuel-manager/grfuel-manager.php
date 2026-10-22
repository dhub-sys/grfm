<?php
/**
 * Plugin Name: GRFuel Manager
 * Description: Fuel order forms, daily product prices, submissions, exports and order notifications for YOOtheme.
 * Version: 4.5.7
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Dazzlink
 * Author URI: https://www.dazzlink.gr
 * License: GPL-2.0-or-later
 * Text Domain: secure-form-vault
 */

defined( 'ABSPATH' ) || exit;

final class SFV_Plugin {
    private const VERSION = '4.5.7';
    private const PRODUCT_NAME = 'GRFuel Manager';
    private const TAGLINE = 'Fuel pricing and orders, under control.';
    private const CPT = 'sfv_form';
    private const OPTION = 'sfv_settings';
    private const PRICE_HISTORY_OPTION = 'grfuel_price_history';
    private const DB_VERSION = '1.1';
    private const DB_OPTION = 'sfv_db_version';
    private const DATA_SNAPSHOT_OPTION = 'grfuel_persistent_catalog_snapshot';
    private const HISTORY_VERSION_OPTION = 'grfuel_price_history_version';
    private const CAPABILITY = 'grfuel_access_manager';
    private const ADMIN_CAPABILITY = 'grfuel_access_manager';
    private const USER_ADMIN_CAPABILITY = 'manage_options';
    private const OPERATOR_ROLE = 'grfuel_operator';
    private const OTP_TTL = 300;
    private const OTP_MAX_ATTEMPTS = 5;
    private const ROLE_VERSION_OPTION = 'grfuel_role_version';
    private const OTP_ENABLED_OPTION = 'grfuel_otp_enabled';
    private const OTP_SESSION_COOKIE = 'grfuel_otp_session';

    public static function boot(): void {
        $plugin = new self();
        register_activation_hook( __FILE__, array( $plugin, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $plugin, 'deactivate' ) );
        add_action( 'plugins_loaded', array( $plugin, 'maybe_upgrade' ) );
        add_action( 'init', array( $plugin, 'register_form_type' ) );
        add_action( 'init', array( $plugin, 'authenticate_manager_session' ), 0 );
        add_action( 'template_redirect', array( $plugin, 'manager_portal_page' ), -1 );
        add_action( 'template_redirect', array( $plugin, 'manager_logout_page' ), -2 );
        add_action( 'template_redirect', array( $plugin, 'manager_login_page' ), 0 );
        add_action( 'wp_login', array( $plugin, 'require_manager_otp_after_standard_login' ), 10, 2 );
        add_filter( 'logout_redirect', array( $plugin, 'manager_logout_redirect' ), 10, 3 );
        add_filter( 'editable_roles', array( $plugin, 'protect_operator_role_assignment' ) );
        add_action( 'admin_post_grfuel_set_operator_approval', array( $plugin, 'set_operator_approval' ) );
        add_action( 'admin_post_grfuel_set_otp_enabled', array( $plugin, 'set_otp_enabled' ) );
        add_action( 'add_meta_boxes', array( $plugin, 'add_form_meta_box' ) );
        add_action( 'save_post_' . self::CPT, array( $plugin, 'save_form' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $plugin, 'admin_assets' ) );
        add_action( 'admin_notices', array( $plugin, 'forms_screen_nav' ) );
        add_filter( 'admin_body_class', array( $plugin, 'admin_body_class' ) );
        add_action( 'wp_enqueue_scripts', array( $plugin, 'register_front_assets' ) );
        add_shortcode( 'secure_contact_form', array( $plugin, 'shortcode' ) );
        add_shortcode( 'beyond_form', array( $plugin, 'shortcode' ) );
        add_shortcode( 'grfuel_form', array( $plugin, 'shortcode' ) );
        add_shortcode( 'sfv_price', array( $plugin, 'price_shortcode' ) );
        add_shortcode( 'beyond_price', array( $plugin, 'price_shortcode' ) );
        add_shortcode( 'grfuel_price', array( $plugin, 'daily_price_shortcode' ) );
        add_shortcode( 'grfuel_price_chart', array( $plugin, 'daily_price_chart_shortcode' ) );
        add_shortcode( 'grfuel_price_chart_mobile', array( $plugin, 'daily_price_chart_mobile_shortcode' ) );
        add_action( 'wp_ajax_grfuel_check_orders', array( $plugin, 'ajax_check_orders' ) );
        add_action( 'wp_ajax_grfuel_push_test', array( $plugin, 'ajax_push_test' ) );
        add_action( 'wp_ajax_grfuel_refresh_manager_session', array( $plugin, 'ajax_refresh_manager_session' ) );
        add_action( 'wp_ajax_nopriv_grfuel_refresh_manager_session', array( $plugin, 'ajax_refresh_manager_session' ) );
        add_action( 'admin_footer', array( $plugin, 'manager_session_button' ) );
        add_action( 'wp_ajax_grfuel_price_chart', array( $plugin, 'ajax_price_chart' ) );
        add_action( 'wp_ajax_nopriv_grfuel_price_chart', array( $plugin, 'ajax_price_chart' ) );
        add_action( 'admin_post_grfuel_add_price_entry', array( $plugin, 'add_price_history_entry' ) );
        add_action( 'admin_post_grfuel_delete_price_entry', array( $plugin, 'delete_price_history_entry' ) );
        add_action( 'admin_post_grfuel_save_daily_prices', array( $plugin, 'save_daily_prices' ) );
        add_action( 'admin_post_grfuel_create_form', array( $plugin, 'create_form_from_manager' ) );
        add_action( 'admin_post_grfuel_delete_form', array( $plugin, 'delete_form_from_manager' ) );
        add_action( 'wp_ajax_sfv_price', array( $plugin, 'ajax_price' ) );
        add_action( 'wp_ajax_nopriv_sfv_price', array( $plugin, 'ajax_price' ) );
        add_action( 'admin_post_nopriv_sfv_submit', array( $plugin, 'handle_submission' ) );
        add_action( 'admin_post_sfv_submit', array( $plugin, 'handle_submission' ) );
        add_action( 'admin_menu', array( $plugin, 'admin_menu' ) );
        add_action( 'admin_init', array( $plugin, 'register_settings' ) );
        add_filter( 'option_page_capability_sfv_settings_group', array( $plugin, 'manager_settings_capability' ) );
        add_action( 'admin_init', array( $plugin, 'enforce_manager_otp_session' ), 0 );
        add_action( 'admin_init', array( $plugin, 'restrict_operator_admin' ), 1 );
        add_action( 'admin_post_sfv_export', array( $plugin, 'export_submissions' ) );
        add_action( 'admin_post_sfv_export_submission_pdf', array( $plugin, 'export_submission_pdf' ) );
        add_action( 'admin_post_sfv_export_selected_pdf', array( $plugin, 'export_selected_pdf' ) );
        add_action( 'admin_post_sfv_delete_submission', array( $plugin, 'delete_submission' ) );
        add_action( 'admin_post_sfv_clear_submissions', array( $plugin, 'clear_submissions' ) );
        add_action( 'admin_post_sfv_reset_submission_id', array( $plugin, 'reset_submission_id' ) );
        add_action( 'admin_post_grfuel_export_backup', array( $plugin, 'export_backup' ) );
        add_action( 'admin_post_grfuel_export_form', array( $plugin, 'export_form' ) );
        add_action( 'admin_post_grfuel_import_form', array( $plugin, 'import_form' ) );
        add_action( 'admin_post_grfuel_import_backup', array( $plugin, 'import_backup' ) );
        add_action( 'admin_post_grfuel_create_admin_order', array( $plugin, 'create_admin_order' ) );
        add_action( 'sfv_daily_retention', array( $plugin, 'run_retention' ) );
        add_filter( 'wp_privacy_personal_data_exporters', array( $plugin, 'register_privacy_exporter' ) );
        add_filter( 'wp_privacy_personal_data_erasers', array( $plugin, 'register_privacy_eraser' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $plugin, 'plugin_links' ) );
        add_filter( 'manage_' . self::CPT . '_posts_columns', array( $plugin, 'form_columns' ) );
        add_filter( 'enter_title_here', array( $plugin, 'form_title_placeholder' ), 10, 2 );
        add_filter( 'redirect_post_location', array( $plugin, 'redirect_form_after_save' ), 10, 2 );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', array( $plugin, 'form_column_content' ), 10, 2 );
    }

    public function activate(): void {
        // Activation and updates are intentionally non-destructive. dbDelta only adds or
        // adjusts schema and never truncates the submissions table or resets options.
        $this->create_table();
        $this->sync_manager_roles( true );
        $this->restore_catalog_snapshot_if_needed();
        update_option( self::DB_OPTION, self::DB_VERSION, false );
        if ( ! wp_next_scheduled( 'sfv_daily_retention' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sfv_daily_retention' );
        }
    }

    public function deactivate(): void {
        wp_clear_scheduled_hook( 'sfv_daily_retention' );
    }

    public function maybe_upgrade(): void {
        $this->sync_manager_roles();
        $installed = (string) get_option( self::DB_OPTION, '' );
        if ( self::DB_VERSION !== $installed ) {
            $this->create_table();
            $this->restore_catalog_snapshot_if_needed();
            update_option( self::DB_OPTION, self::DB_VERSION, false );
        }
        if ( ! wp_next_scheduled( 'sfv_daily_retention' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sfv_daily_retention' );
        }
        if ( '1' !== (string) get_option( self::HISTORY_VERSION_OPTION, '' ) ) {
            $this->normalize_price_history();
            update_option( self::HISTORY_VERSION_OPTION, '1', false );
        }
        $this->snapshot_catalog();
    }

    // Earlier versions could store several entries per day and zero prices; keep the
    // last price of each day and drop non-positive prices.
    private function normalize_price_history(): void {
        $history = $this->price_history();
        if ( ! $history ) return;
        foreach ( $history as $slug => $entries ) {
            $by_day = array();
            foreach ( (array) $entries as $entry ) {
                if ( ! is_array( $entry ) || (float) ( $entry['price'] ?? 0 ) <= 0 ) continue;
                $day = substr( (string) ( $entry['recorded_at'] ?? '' ), 0, 10 );
                if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) continue;
                if ( ! isset( $by_day[ $day ] ) || strcmp( (string) $entry['recorded_at'], (string) $by_day[ $day ]['recorded_at'] ) >= 0 ) $by_day[ $day ] = $entry;
            }
            ksort( $by_day );
            $history[ $slug ] = array_values( array_map( static fn( $entry ) => array( 'price' => (float) $entry['price'], 'recorded_at' => substr( (string) $entry['recorded_at'], 0, 10 ) . ' 12:00:00' ), $by_day ) );
        }
        update_option( self::PRICE_HISTORY_OPTION, $history, false );
    }

    private function snapshot_catalog(): void {
        $settings = (array) get_option( self::OPTION, array() );
        $prices = isset( $settings['daily_prices'] ) && is_array( $settings['daily_prices'] ) ? $settings['daily_prices'] : array();
        $history = (array) get_option( self::PRICE_HISTORY_OPTION, array() );
        if ( $prices || $history ) {
            update_option( self::DATA_SNAPSHOT_OPTION, array( 'daily_prices' => $prices, 'history' => $history ), false );
        }
    }

    private function restore_catalog_snapshot_if_needed(): void {
        $snapshot = (array) get_option( self::DATA_SNAPSHOT_OPTION, array() );
        if ( ! $snapshot ) return;
        $settings = (array) get_option( self::OPTION, array() );
        if ( empty( $settings['daily_prices'] ) && ! empty( $snapshot['daily_prices'] ) ) {
            $settings['daily_prices'] = (array) $snapshot['daily_prices'];
            update_option( self::OPTION, $settings, false );
        }
        if ( ! get_option( self::PRICE_HISTORY_OPTION, array() ) && ! empty( $snapshot['history'] ) ) {
            update_option( self::PRICE_HISTORY_OPTION, (array) $snapshot['history'], false );
        }
    }

    private function manager_form_capabilities(): array {
        return array(
            'edit_grfuel_form', 'read_grfuel_form', 'delete_grfuel_form',
            'edit_grfuel_forms', 'edit_others_grfuel_forms', 'publish_grfuel_forms',
            'read_private_grfuel_forms', 'delete_grfuel_forms',
            'delete_private_grfuel_forms', 'delete_published_grfuel_forms',
            'delete_others_grfuel_forms', 'edit_private_grfuel_forms',
            'edit_published_grfuel_forms',
        );
    }

    private function sync_manager_roles( bool $force = false ): void {
        if ( ! $force && '2' === (string) get_option( self::ROLE_VERSION_OPTION, '' ) ) return;
        $caps = array_fill_keys( array_merge( array( 'read', 'upload_files', self::CAPABILITY ), $this->manager_form_capabilities() ), true );
        $operator = get_role( self::OPERATOR_ROLE );
        if ( ! $operator ) {
            add_role( self::OPERATOR_ROLE, 'GRFuel Operator', $caps );
            $operator = get_role( self::OPERATOR_ROLE );
        }
        if ( $operator ) {
            foreach ( $caps as $cap => $grant ) $operator->add_cap( $cap, $grant );
            foreach ( array( 'manage_options', 'create_users', 'edit_users', 'delete_users', 'promote_users', 'install_plugins', 'activate_plugins', 'edit_plugins' ) as $forbidden ) {
                $operator->remove_cap( $forbidden );
            }
        }
        $administrator = get_role( 'administrator' );
        if ( $administrator ) {
            $administrator->add_cap( self::CAPABILITY );
            foreach ( $this->manager_form_capabilities() as $cap ) $administrator->add_cap( $cap );
        }
        update_option( self::ROLE_VERSION_OPTION, '2', false );
    }

    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sfv_submissions';
    }

    private function manager_login_url( array $args = array() ): string {
        return add_query_arg( $args, home_url( '/grfuel-login/' ) );
    }

    private function manager_portal_url(): string {
        return home_url( '/grfuel-manager/' );
    }

    private function manager_logout_url(): string {
        return wp_nonce_url( home_url( '/grfuel-manager-logout/' ), 'grfuel_manager_logout' );
    }

    private function manager_user_can_login( WP_User $user ): bool {
        return in_array( 'administrator', (array) $user->roles, true )
            || in_array( self::OPERATOR_ROLE, (array) $user->roles, true );
    }

    private function manager_user_requires_otp( WP_User $user ): bool {
        return in_array( self::OPERATOR_ROLE, (array) $user->roles, true );
    }

    private function otp_enabled(): bool {
        return '1' === (string) get_option( self::OTP_ENABLED_OPTION, '0' );
    }

    private function operator_is_approved( WP_User $user ): bool {
        if ( in_array( 'administrator', (array) $user->roles, true ) ) return true;
        return in_array( self::OPERATOR_ROLE, (array) $user->roles, true )
            && '1' === (string) get_user_meta( $user->ID, 'grfuel_operator_approved', true );
    }

    private function otp_state_key( string $token ): string {
        return 'grfuel_otp_' . hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
    }

    private function otp_browser_hash(): string {
        return hash_hmac( 'sha256', sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), wp_salt( 'nonce' ) );
    }

    private function otp_session_key( string $token ): string {
        return 'grfuel_otp_session_' . hash_hmac( 'sha256', $token, wp_salt( 'secure_auth' ) );
    }

    private function otp_session_valid( WP_User $user ): bool {
        $token = sanitize_text_field( wp_unslash( $_COOKIE[ self::OTP_SESSION_COOKIE ] ?? '' ) );
        if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) return false;
        $state = get_transient( $this->otp_session_key( $token ) );
        return is_array( $state )
            && (int) ( $state['user_id'] ?? 0 ) === (int) $user->ID
            && time() < (int) ( $state['expires'] ?? 0 )
            && hash_equals( (string) ( $state['browser'] ?? '' ), $this->otp_browser_hash() )
            && $this->wordpress_session_matches( $user );
    }

    // The portal frame is wp-admin, which needs the WordPress login cookie. A Manager
    // session that outlives it (e.g. after the browser was closed) would send the user to
    // the WordPress login screen inside the portal, so treat it as expired instead.
    private function wordpress_session_matches( WP_User $user ): bool {
        return (int) wp_validate_auth_cookie( '', 'logged_in' ) === (int) $user->ID;
    }

    private function set_otp_session_cookie( string $token, int $ttl, bool $remember ): void {
        setcookie( self::OTP_SESSION_COOKIE, $token, array(
            // Without "remember me" the WordPress cookies are session cookies; match them.
            'expires' => $remember ? time() + $ttl : 0,
            'path' => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
            'domain' => defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ) );
    }

    private function manager_session_seconds(): int {
        $minutes = absint( $this->settings()['manager_session_minutes'] ?? 480 );
        return max( 15, min( 10080, $minutes ) ) * MINUTE_IN_SECONDS;
    }

    private function create_otp_session( WP_User $user, bool $remember ): bool {
        try {
            $token = bin2hex( random_bytes( 32 ) );
        } catch ( Throwable $error ) {
            return false;
        }
        $ttl = $this->manager_session_seconds();
        set_transient( $this->otp_session_key( $token ), array( 'user_id' => (int) $user->ID, 'browser' => $this->otp_browser_hash(), 'expires' => time() + $ttl, 'remember' => $remember ), $ttl );
        $this->set_otp_session_cookie( $token, $ttl, $remember );
        return true;
    }

    public function authenticate_manager_session(): void {
        if ( is_user_logged_in() ) return;
        $token = sanitize_text_field( wp_unslash( $_COOKIE[ self::OTP_SESSION_COOKIE ] ?? '' ) );
        if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) return;
        $state = get_transient( $this->otp_session_key( $token ) );
        if ( ! is_array( $state ) || time() >= (int) ( $state['expires'] ?? 0 ) || ! hash_equals( (string) ( $state['browser'] ?? '' ), $this->otp_browser_hash() ) ) return;
        $user = get_user_by( 'id', absint( $state['user_id'] ?? 0 ) );
        if ( $user instanceof WP_User && $this->manager_user_can_login( $user ) && $this->operator_is_approved( $user ) && user_can( $user, self::CAPABILITY ) && $this->wordpress_session_matches( $user ) ) wp_set_current_user( $user->ID );
    }

    public function manager_portal_page(): void {
        $portal_path = untrailingslashit( (string) wp_parse_url( $this->manager_portal_url(), PHP_URL_PATH ) );
        $request_path = untrailingslashit( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
        if ( $portal_path !== $request_path ) return;
        $this->redirect_manager_to_https();
        $user = wp_get_current_user();
        if ( ! $user->exists() || ! $this->otp_session_valid( $user ) ) {
            wp_safe_redirect( $this->manager_login_url() );
            exit;
        }
        $this->send_private_response_headers();
        $src = admin_url( 'admin.php?page=grfuel-manager&grfuel_portal=1' );
        status_header( 200 );
        ?><!doctype html><html lang="el"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>GRFuel Manager</title><style>html,body{width:100%;height:100%;margin:0;overflow:hidden;background:#f3f6fb}iframe{display:block;width:100%;height:100%;border:0}</style></head><body><iframe title="GRFuel Manager" src="<?php echo esc_url( $src ); ?>"></iframe></body></html><?php
        exit;
    }

    public function manager_logout_page(): void {
        $logout_path = untrailingslashit( (string) wp_parse_url( home_url( '/grfuel-manager-logout/' ), PHP_URL_PATH ) );
        $request_path = untrailingslashit( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
        if ( $logout_path !== $request_path ) return;
        $this->redirect_manager_to_https();
        check_admin_referer( 'grfuel_manager_logout' );
        $this->clear_otp_session();
        wp_safe_redirect( $this->manager_login_url( array( 'logged_out' => '1' ) ) );
        exit;
    }

    // Over plain http the login sets non-secure cookies that an https wp-admin rejects, and
    // the https wp-admin frame is refused inside an http portal page (X-Frame-Options:
    // SAMEORIGIN). Keep the Manager pages on https whenever wp-admin runs on https.
    private function redirect_manager_to_https(): void {
        if ( is_ssl() ) return;
        // Behind a TLS-terminating proxy (e.g. Cloudflare Flexible SSL) PHP sees http while the
        // visitor is already on https; redirecting there would loop.
        $forwarded = strtolower( (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) );
        $cf_visitor = (string) wp_unslash( $_SERVER['HTTP_CF_VISITOR'] ?? '' );
        if ( str_contains( $forwarded, 'https' ) || str_contains( $cf_visitor, '"https"' ) ) return;
        if ( isset( $_GET['grfuel_https'] ) ) return; // Already redirected once: never loop.
        if ( 'https' !== wp_parse_url( admin_url(), PHP_URL_SCHEME ) && ! force_ssl_admin() ) return;
        $host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
        if ( '' === $host ) return;
        $uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' );
        wp_safe_redirect( add_query_arg( 'grfuel_https', '1', 'https://' . $host . ( '' !== $uri && '/' === $uri[0] ? $uri : '/' ) ), 302 );
        exit;
    }

    private function clear_otp_session(): void {
        $token = sanitize_text_field( wp_unslash( $_COOKIE[ self::OTP_SESSION_COOKIE ] ?? '' ) );
        if ( preg_match( '/^[a-f0-9]{64}$/', $token ) ) delete_transient( $this->otp_session_key( $token ) );
        setcookie( self::OTP_SESSION_COOKIE, '', array(
            'expires' => time() - HOUR_IN_SECONDS,
            'path' => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
            'domain' => defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ) );
    }

    public function ajax_refresh_manager_session(): void {
        check_ajax_referer( 'grfuel_refresh_manager_session', 'nonce' );
        $user = wp_get_current_user();
        $token = sanitize_text_field( wp_unslash( $_COOKIE[ self::OTP_SESSION_COOKIE ] ?? '' ) );
        if ( ! $user->exists() || ! preg_match( '/^[a-f0-9]{64}$/', $token ) || ! $this->otp_session_valid( $user ) ) {
            wp_send_json_error( array( 'message' => 'Η συνεδρία έχει λήξει. Συνδεθείτε ξανά.' ), 403 );
        }
        $ttl = $this->manager_session_seconds();
        $remember = ! empty( get_transient( $this->otp_session_key( $token ) )['remember'] );
        set_transient( $this->otp_session_key( $token ), array( 'user_id' => (int) $user->ID, 'browser' => $this->otp_browser_hash(), 'expires' => time() + $ttl, 'remember' => $remember ), $ttl );
        $this->set_otp_session_cookie( $token, $ttl, $remember );
        wp_send_json_success( array( 'message' => sprintf( 'Η συνεδρία ανανεώθηκε για %d λεπτά.', (int) ( $ttl / MINUTE_IN_SECONDS ) ), 'expires' => time() + $ttl ) );
    }

    public function manager_session_button(): void {
        $user = wp_get_current_user();
        if ( ! $user->exists() || ! current_user_can( self::CAPABILITY ) || ! $this->otp_session_valid( $user ) ) return;
        $token = sanitize_text_field( wp_unslash( $_COOKIE[ self::OTP_SESSION_COOKIE ] ?? '' ) );
        $state = get_transient( $this->otp_session_key( $token ) );
        $expires = is_array( $state ) ? absint( $state['expires'] ?? 0 ) : 0;
        ?><button type="button" class="grfuel-session-refresh" data-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'grfuel_refresh_manager_session' ) ); ?>" data-expires="<?php echo esc_attr( $expires ); ?>" data-login-url="<?php echo esc_url( $this->manager_login_url( array( 'notice' => 'otp_required' ) ) ); ?>" title="Ανανέωση διάρκειας συνεδρίας" aria-label="Ανανέωση διάρκειας συνεδρίας"><span class="dashicons dashicons-update"></span><span class="grfuel-session-refresh-label">Ανανέωση</span><time class="grfuel-session-countdown" aria-label="Χρόνος συνεδρίας">--:--:--</time></button><div class="grfuel-session-toast" role="status" aria-live="polite"></div><?php
    }

    private function otp_rate_key( string $login ): string {
        return 'grfuel_otp_rate_' . hash_hmac( 'sha256', $this->client_ip() . '|' . strtolower( $login ), wp_salt( 'auth' ) );
    }

    private function otp_rate_limited( string $login ): bool {
        return (int) get_transient( $this->otp_rate_key( $login ) ) >= 5;
    }

    private function otp_rate_hit( string $login ): void {
        $key = $this->otp_rate_key( $login );
        set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
    }

    // A completed login clears the counter for every identifier the user can log in with.
    private function otp_rate_reset( WP_User $user, string $login = '' ): void {
        foreach ( array_unique( array_filter( array( $login, $user->user_login, $user->user_email ) ) ) as $identifier ) {
            delete_transient( $this->otp_rate_key( (string) $identifier ) );
        }
    }

    private function send_otp_email( WP_User $user, string $otp ): bool {
        $display_name = trim( (string) $user->display_name );
        $greeting = '' !== $display_name ? 'Γεια σου ' . $display_name . ',' : 'Γεια σου,';
        $digits = implode( '&nbsp;', array_map( 'esc_html', str_split( $otp ) ) );
        $login_url = esc_url( $this->manager_login_url() );
        $message = '<!doctype html><html lang="el"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Κωδικός σύνδεσης GRFuel Manager</title></head>'
            . '<body style="margin:0;padding:0;background:#f3f6fb;color:#172033;font-family:Arial,Helvetica,sans-serif;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f3f6fb;"><tr><td align="center" style="padding:32px 12px;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px;background:#ffffff;border:1px solid #dfe5ee;border-radius:18px;">'
            . '<tr><td style="height:7px;background:#0e3e5f;border-radius:18px 18px 0 0;font-size:0;line-height:0;">&nbsp;</td></tr>'
            . '<tr><td style="padding:34px 34px 12px;text-align:center;"><div style="color:#0e3e5f;font-size:24px;line-height:30px;font-weight:700;">GRFuel Manager</div><div style="margin-top:7px;color:#738098;font-size:13px;line-height:20px;">Ασφαλής επαλήθευση σύνδεσης</div></td></tr>'
            . '<tr><td style="padding:16px 34px 8px;color:#26324d;font-size:15px;line-height:24px;">' . esc_html( $greeting ) . '<br><br>Χρησιμοποίησε τον παρακάτω κωδικό για να ολοκληρώσεις τη σύνδεσή σου:</td></tr>'
            . '<tr><td align="center" style="padding:18px 24px;"><div style="display:inline-block;padding:17px 22px;border:1px solid #cfdbee;border-radius:12px;background:#f5f8ff;color:#0e3e5f;font-family:Arial,Helvetica,sans-serif;font-size:30px;line-height:36px;font-weight:700;letter-spacing:8px;">' . $digits . '</div></td></tr>'
            . '<tr><td style="padding:8px 34px 28px;color:#5d6a80;font-size:13px;line-height:21px;text-align:center;">Ο κωδικός λήγει σε <strong>πέντε λεπτά</strong> και χρησιμοποιείται μόνο μία φορά.<br><a href="' . $login_url . '" style="display:inline-block;margin-top:18px;padding:12px 20px;border-radius:9px;background:#0e3e5f;color:#ffffff;text-decoration:none;font-weight:700;">Μετάβαση στη σελίδα σύνδεσης</a></td></tr>'
            . '<tr><td style="padding:20px 34px;border-top:1px solid #e7ebf2;color:#8791a3;font-size:11px;line-height:18px;text-align:center;">Αν δεν ζήτησες εσύ τον κωδικό, αγνόησε το μήνυμα. Μην κοινοποιείς ποτέ κωδικούς OTP.</td></tr>'
            . '</table></td></tr></table></body></html>';
        return wp_mail(
            $user->user_email,
            'Ο κωδικός σύνδεσής σου στο GRFuel Manager',
            $message,
            array(
                'Content-Type: text/html; charset=UTF-8',
                'From: GRFuel Manager <no-reply@grfuel.gr>',
                'Reply-To: GRFuel Manager <no-reply@grfuel.gr>',
            )
        );
    }

    public function require_manager_otp_after_standard_login( string $user_login, WP_User $user ): void {
        unset( $user_login );
        if ( ! $this->manager_user_requires_otp( $user ) ) return;
        if ( ! $this->operator_is_approved( $user ) ) {
            wp_logout();
            wp_safe_redirect( $this->manager_login_url( array( 'notice' => 'approval_required' ) ) );
            exit;
        }
        if ( ! $this->otp_enabled() ) return;
        if ( defined( 'GRFUEL_DISABLE_OTP' ) && GRFUEL_DISABLE_OTP ) return;
        wp_logout();
        wp_safe_redirect( $this->manager_login_url( array( 'notice' => 'otp_required' ) ) );
        exit;
    }

    public function manager_logout_redirect( string $redirect_to, string $requested_redirect_to, WP_User $user ): string {
        unset( $requested_redirect_to );
        if ( $user->exists() && $this->manager_user_requires_otp( $user ) ) {
            $this->clear_otp_session();
            return $this->manager_login_url( array( 'logged_out' => '1' ) );
        }
        return $redirect_to;
    }

    public function enforce_manager_otp_session(): void {
        if ( ! is_user_logged_in() ) return;
        $user = wp_get_current_user();
        if ( in_array( 'administrator', (array) $user->roles, true ) ) {
            global $pagenow;
            $page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
            $manager_pages = array( 'grfuel-manager', 'sfv-submissions', 'grfuel-new-order', 'grfuel-forms', 'grfuel-prices', 'grfuel-users', 'sfv-settings', 'grfuel-backup', 'sfv-documentation', 'grfuel-changelog' );
            $post_type = sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) );
            if ( 'post.php' === $pagenow ) $post_type = get_post_type( $this->requested_post_id() ) ?: '';
            $is_manager = ( 'admin.php' === $pagenow && in_array( $page, $manager_pages, true ) ) || ( in_array( $pagenow, array( 'post.php', 'post-new.php', 'edit.php' ), true ) && self::CPT === $post_type );
            if ( ! $is_manager ) return;
            if ( $this->otp_session_valid( $user ) ) return;
            wp_safe_redirect( $this->manager_login_url( array( 'notice' => 'otp_required' ) ) );
            exit;
        }
        if ( ! $this->manager_user_requires_otp( $user ) ) return;
        if ( ! $this->operator_is_approved( $user ) ) {
            wp_logout();
            wp_safe_redirect( $this->manager_login_url( array( 'notice' => 'approval_required' ) ) );
            exit;
        }
        if ( ! $this->otp_enabled() ) return;
        if ( ( defined( 'GRFUEL_DISABLE_OTP' ) && GRFUEL_DISABLE_OTP ) || $this->otp_session_valid( $user ) ) return;
        wp_logout();
        wp_safe_redirect( $this->manager_login_url( array( 'notice' => 'otp_required' ) ) );
        exit;
    }

    public function restrict_operator_admin(): void {
        if ( ! is_user_logged_in() || current_user_can( self::USER_ADMIN_CAPABILITY ) || ! current_user_can( self::CAPABILITY ) ) return;
        global $pagenow;
        if ( in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php' ), true ) ) return;
        if ( 'options.php' === $pagenow && 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) && 'sfv_settings_group' === sanitize_key( wp_unslash( $_POST['option_page'] ?? '' ) ) ) return;
        $allowed_pages = array( 'grfuel-manager', 'sfv-submissions', 'grfuel-new-order', 'grfuel-forms', 'grfuel-prices', 'sfv-settings', 'grfuel-backup', 'sfv-documentation', 'grfuel-changelog' );
        $page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
        if ( 'admin.php' === $pagenow && in_array( $page, $allowed_pages, true ) ) return;
        if ( in_array( $pagenow, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
            $post_type = sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) );
            if ( 'post.php' === $pagenow ) $post_type = get_post_type( $this->requested_post_id() ) ?: '';
            if ( self::CPT === $post_type ) return;
        }
        wp_safe_redirect( admin_url( 'admin.php?page=grfuel-manager' ) );
        exit;
    }

    private function requested_post_id(): int {
        return absint( $_GET['post'] ?? $_POST['post_ID'] ?? $_POST['post'] ?? 0 );
    }

    public function protect_operator_role_assignment( array $roles ): array {
        if ( ! current_user_can( self::USER_ADMIN_CAPABILITY ) ) unset( $roles[ self::OPERATOR_ROLE ] );
        return $roles;
    }

    public function manager_login_page(): void {
        $login_path = untrailingslashit( (string) wp_parse_url( $this->manager_login_url(), PHP_URL_PATH ) );
        $request_path = untrailingslashit( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
        if ( $login_path !== $request_path ) return;
        $this->redirect_manager_to_https();

        $this->send_private_response_headers();
        header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'" );
        if ( is_user_logged_in() ) {
            $current = wp_get_current_user();
            if ( $this->manager_user_can_login( $current ) && $this->operator_is_approved( $current ) && $this->otp_session_valid( $current ) ) {
                wp_safe_redirect( $this->manager_portal_url() );
                exit;
            }
        }

        $step = 'credentials';
        $message = '';
        $token = '';
        if ( isset( $_GET['notice'] ) && 'otp_required' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) {
            $message = 'Για την ασφάλεια του λογαριασμού, συνδεθείτε μέσω της επαλήθευσης OTP.';
        } elseif ( isset( $_GET['notice'] ) && 'approval_required' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) {
            $message = 'Ο λογαριασμός GRFuel Operator αναμένει έγκριση από Administrator.';
        } elseif ( ! empty( $_GET['logged_out'] ) ) {
            $message = 'Αποσυνδεθήκατε με επιτυχία.';
        }

        if ( 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
            $action = sanitize_key( wp_unslash( $_POST['grfuel_login_action'] ?? '' ) );
            if ( 'credentials' === $action ) {
                if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'grfuel_login_credentials' ) ) {
                    $message = 'Η σύνδεση δεν ολοκληρώθηκε. Ανανεώστε τη σελίδα και δοκιμάστε ξανά.';
                } else {
                    $login = mb_substr( sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ), 0, 100 );
                    $password = mb_substr( (string) wp_unslash( $_POST['pwd'] ?? '' ), 0, 4096 );
                    $remember = ! empty( $_POST['rememberme'] );
                    if ( '' === $login || '' === $password || $this->otp_rate_limited( $login ) ) {
                        $message = 'Τα στοιχεία σύνδεσης δεν είναι έγκυρα ή έγιναν πολλές προσπάθειες.';
                    } else {
                        $user = wp_authenticate( $login, $password );
                        if ( is_wp_error( $user ) || ! $user instanceof WP_User || ! $this->manager_user_can_login( $user ) || ! $this->operator_is_approved( $user ) || ! user_can( $user, self::CAPABILITY ) ) {
                            $this->otp_rate_hit( $login );
                            $message = 'Τα στοιχεία σύνδεσης δεν είναι έγκυρα ή ο λογαριασμός δεν έχει πρόσβαση.';
                        } else {
                            if ( ! $this->otp_enabled() ) {
                                $this->otp_rate_reset( $user, $login );
                                wp_set_current_user( $user->ID );
                                $this->create_otp_session( $user, $remember );
                                // WordPress admin.php performs its own auth-cookie validation
                                // before rendering inside the Manager portal frame.
                                wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
                                wp_safe_redirect( $this->manager_portal_url() );
                                exit;
                            }
                            try {
                                $otp = (string) random_int( 100000, 999999 );
                                $token = bin2hex( random_bytes( 24 ) );
                            } catch ( Throwable $error ) {
                                $otp = '';
                                $token = '';
                            }
                            if ( '' === $otp || '' === $token ) {
                                $message = 'Δεν ήταν δυνατή η δημιουργία κωδικού ασφαλείας.';
                            } else {
                                $state = array(
                                    'user_id' => (int) $user->ID,
                                    'otp_hash' => hash_hmac( 'sha256', $otp, wp_salt( 'auth' ) . $token ),
                                    'attempts' => 0,
                                    'remember' => $remember,
                                    'browser' => $this->otp_browser_hash(),
                                    'expires' => time() + self::OTP_TTL,
                                );
                                set_transient( $this->otp_state_key( $token ), $state, self::OTP_TTL );
                                // Each code request counts, so a known password cannot trigger unlimited OTP emails.
                                $this->otp_rate_hit( $login );
                                $sent = $this->send_otp_email( $user, $otp );
                                if ( $sent ) {
                                    $step = 'otp';
                                    $message = 'Στείλαμε έναν εξαψήφιο κωδικό στο email του λογαριασμού.';
                                } else {
                                    delete_transient( $this->otp_state_key( $token ) );
                                    $token = '';
                                    $message = 'Δεν ήταν δυνατή η αποστολή του κωδικού. Επικοινωνήστε με τον διαχειριστή.';
                                }
                            }
                        }
                    }
                }
            } elseif ( 'otp' === $action ) {
                $step = 'otp';
                $token = sanitize_text_field( wp_unslash( $_POST['otp_token'] ?? '' ) );
                $otp = preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['otp'] ?? '' ) ) ?? '';
                if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'grfuel_login_otp' ) || ! preg_match( '/^[a-f0-9]{48}$/', $token ) || ! preg_match( '/^\d{6}$/', $otp ) ) {
                    $message = 'Ο κωδικός δεν είναι έγκυρος ή έχει λήξει.';
                } else {
                    $state_key = $this->otp_state_key( $token );
                    $state = get_transient( $state_key );
                    if ( ! is_array( $state ) || time() > absint( $state['expires'] ?? 0 ) || ! hash_equals( (string) ( $state['browser'] ?? '' ), $this->otp_browser_hash() ) || (int) ( $state['attempts'] ?? 0 ) >= self::OTP_MAX_ATTEMPTS ) {
                        delete_transient( $state_key );
                        $message = 'Ο κωδικός δεν είναι έγκυρος ή έχει λήξει.';
                    } else {
                        $state['attempts'] = (int) $state['attempts'] + 1;
                        set_transient( $state_key, $state, max( 1, absint( $state['expires'] ) - time() ) );
                        $expected = hash_hmac( 'sha256', $otp, wp_salt( 'auth' ) . $token );
                        $user = get_user_by( 'id', absint( $state['user_id'] ?? 0 ) );
                        if ( ! hash_equals( (string) ( $state['otp_hash'] ?? '' ), $expected ) || ! $user instanceof WP_User || ! $this->manager_user_can_login( $user ) || ! $this->operator_is_approved( $user ) || ! user_can( $user, self::CAPABILITY ) ) {
                            if ( (int) $state['attempts'] >= self::OTP_MAX_ATTEMPTS ) delete_transient( $state_key );
                            $message = 'Ο κωδικός δεν είναι έγκυρος ή έχει λήξει.';
                        } else {
                            delete_transient( $state_key );
                            wp_set_current_user( $user->ID );
                            $remember = ! empty( $state['remember'] );
                            if ( ! $this->create_otp_session( $user, $remember ) ) {
                                wp_set_current_user( 0 );
                                $message = 'Δεν ήταν δυνατή η δημιουργία ασφαλούς συνεδρίας.';
                            } else {
                                // Keep the dedicated Manager session as the access gate, while
                                // also satisfying WordPress' internal admin auth_redirect().
                                wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
                                update_user_meta( $user->ID, 'grfuel_last_otp_login', time() );
                                $this->otp_rate_reset( $user );
                                wp_safe_redirect( $this->manager_portal_url() );
                                exit;
                            }
                        }
                    }
                }
            }
        }

        status_header( 200 );
        ?><!doctype html><html lang="el"><head><meta charset="<?php echo esc_attr( get_option( 'blog_charset', 'UTF-8' ) ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Σύνδεση · GRFuel Manager</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Geom:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet"><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:22px;background:#f3f6fb;color:#172033;font-family:"Geom",Arial,sans-serif}.card{width:min(440px,100%);padding:32px;border:1px solid #dfe5ee;border-radius:20px;background:#fff;box-shadow:0 22px 70px rgba(15,23,42,.12)}.brand{margin-bottom:24px;color:#0e3e5f;font-size:22px;font-weight:800}.brand span{display:block;margin-top:5px;color:#738098;font-size:12px;font-weight:500}.notice{margin-bottom:18px;padding:12px 14px;border-radius:10px;background:#f0f5ff;color:#334e7d;font-size:13px;line-height:1.5}label{display:grid;gap:7px;margin-bottom:15px;font-size:13px;font-weight:700}input[type=text],input[type=password]{width:100%;min-height:48px;padding:10px 13px;border:1px solid #cfd7e4;border-radius:10px;font-size:16px}input:focus{border-color:#2563eb;outline:3px solid rgba(37,99,235,.13)}.remember{display:flex;align-items:center;gap:8px;font-weight:500}.otp-fieldset{margin:0 0 20px;padding:0;border:0}.otp-fieldset legend{margin-bottom:10px;font-size:13px;font-weight:700}.otp-boxes{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:9px}.otp-digit{width:100%;min-width:0;height:56px;padding:0!important;border:1px solid #cfd7e4;border-radius:10px;text-align:center;font-family:"Geom",Arial,sans-serif;font-size:24px!important;font-weight:700}.otp-digit:focus{border-color:#2563eb;outline:3px solid rgba(37,99,235,.13)}(max-width:420px){.card{padding:24px 18px}.otp-boxes{gap:6px}.otp-digit{height:50px}}.button{width:100%;min-height:48px;border:0;border-radius:10px;background:#0e3e5f;color:#fff;font-size:14px;font-weight:800;cursor:pointer}.help{margin:18px 0 0;text-align:center}.help a{color:#52637d;font-size:12px}</style></head><body><main class="card"><div class="brand">GRFuel Manager<span>Ασφαλής σύνδεση με email OTP</span></div><?php if ( '' !== $message ) : ?><div class="notice" role="status"><?php echo esc_html( $message ); ?></div><?php endif; ?><?php if ( 'otp' === $step && '' !== $token ) : ?><form method="post" autocomplete="one-time-code"><input type="hidden" name="grfuel_login_action" value="otp"><input type="hidden" name="otp_token" value="<?php echo esc_attr( $token ); ?>"><?php wp_nonce_field( 'grfuel_login_otp' ); ?><fieldset class="otp-fieldset"><legend>Κωδικός μίας χρήσης</legend><div class="otp-boxes" data-otp-boxes><input class="otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="1ο ψηφίο" required autofocus><input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="2ο ψηφίο" required><input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="3ο ψηφίο" required><input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="4ο ψηφίο" required><input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="5ο ψηφίο" required><input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="6ο ψηφίο" required></div><input type="hidden" name="otp" data-otp-value></fieldset><button class="button" type="submit">Επιβεβαίωση και σύνδεση</button></form><p class="help"><a href="<?php echo esc_url( $this->manager_login_url() ); ?>">Νέα αποστολή κωδικού</a></p><?php else : ?><form method="post"><input type="hidden" name="grfuel_login_action" value="credentials"><?php wp_nonce_field( 'grfuel_login_credentials' ); ?><label>Email ή όνομα χρήστη<input type="text" name="log" autocomplete="username" maxlength="100" required autofocus></label><label>Κωδικός πρόσβασης<input type="password" name="pwd" autocomplete="current-password" maxlength="4096" required></label><label class="remember"><input type="checkbox" name="rememberme" value="1"> Να παραμείνω συνδεδεμένος</label><button class="button" type="submit">Συνέχεια με OTP</button></form><p class="help"><a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Ξέχασα τον κωδικό μου</a></p><?php endif; ?></main><script>(function(){var wrap=document.querySelector("[data-otp-boxes]"),hidden=document.querySelector("[data-otp-value]");if(!wrap||!hidden)return;var boxes=Array.prototype.slice.call(wrap.querySelectorAll(".otp-digit"));function sync(){hidden.value=boxes.map(function(box){return box.value;}).join("");}function fill(value,start){var digits=String(value).replace(/\D/g,"").slice(0,6-start).split("");digits.forEach(function(digit,index){boxes[start+index].value=digit;});sync();var next=Math.min(start+digits.length,5);boxes[next].focus();boxes[next].select();}boxes.forEach(function(box,index){box.addEventListener("input",function(){var value=box.value.replace(/\D/g,"");box.value="";if(value)fill(value,index);else sync();});box.addEventListener("keydown",function(event){if(event.key==="Backspace"&&!box.value&&index>0){boxes[index-1].value="";boxes[index-1].focus();sync();}if(event.key==="ArrowLeft"&&index>0){event.preventDefault();boxes[index-1].focus();}if(event.key==="ArrowRight"&&index<5){event.preventDefault();boxes[index+1].focus();}});box.addEventListener("paste",function(event){event.preventDefault();fill((event.clipboardData||window.clipboardData).getData("text"),index);});});var form=wrap.closest("form");if(form)form.addEventListener("submit",sync);})();</script></body></html><?php
        exit;
    }

    private function require_post_request(): void {
        $method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) );
        if ( 'POST' !== $method ) {
            status_header( 405 );
            header( 'Allow: POST' );
            wp_die( esc_html__( 'This action requires a POST request.', 'secure-form-vault' ) );
        }
    }

    private function send_private_response_headers(): void {
        nocache_headers();
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Referrer-Policy: no-referrer' );
        header( 'X-Frame-Options: SAMEORIGIN' );
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
    }

    private function admin_icon(): string {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 62 45" aria-hidden="true" focusable="false"><path fill="#fff" fill-opacity=".65" fill-rule="evenodd" d="M40.72 31.28c-7.13 3.74-13.34 8.17-18.27 12.82h7c5.13-5.69 12.25-11.19 20.74-15.64 3.66-1.92 7.33-3.51 10.94-4.78-6.48 1.43-13.46 3.97-20.41 7.6Z"/><path fill="#fff" fill-rule="evenodd" d="M30.38 30.41C18.9 34.46 8.7 39.28.88 44.1h7.27c8.31-5.44 19.81-11.02 32.94-15.66 6.67-2.35 13.16-4.26 19.23-5.69-8.78 1.26-19.13 3.84-29.94 7.66Zm-9.67 9.91c-1.98 1.24-3.77 2.53-5.28 3.78h1.78c1.36-1.23 3.01-2.51 4.88-3.78h-1.38Zm38.11-11.61c-.02-.77-.09-1.54-.22-2.31-.03-.2-.07-.39-.11-.58-4.14 1.79-8.46 4.14-12.75 7.01-4.34 2.9-8.2 6.04-11.45 9.22 3.04 2.15 6.8 3.18 10.93 2.59 7.9-1.13 12.78-6.97 13.5-13.68.04-.37.07-.74.08-1.12v-.17c0-.32.01-.63.02-.95Zm-3.34-9.26c-.31-.44-.59-.79-.87-1.1-4.31-5.14-10.33-8.36-7.35-18.14-12.45 3.61-21.65 16.71-20.44 28.17.03.33.08.66.13.99 7.15-2.84 15.66-5.16 24.92-6.61 1.78-.28 3.55-.52 5.29-.72-.49-.94-1.06-1.79-1.68-2.59Zm-22.78-1.62c-.59 2.22-.97 4.37-1.15 6.37v.08c-.03.27-.23.51-.52.58-.35.08-.7-.13-.8-.46-.42-1.35.33-4.64 1.95-8.21.95-2.08 2.03-3.88 3.02-5.13-.97 1.98-1.83 4.28-2.5 6.79Z"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    private function create_table(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $table = $this->table();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            submitted_at datetime NOT NULL,
            payload longtext NOT NULL,
            ip_hash char(64) NOT NULL DEFAULT '',
            user_agent varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY submitted_at (submitted_at)
        ) {$charset};";
        dbDelta( $sql );
    }

    public function register_form_type(): void {
        register_post_type(
            self::CPT,
            array(
                'labels' => array(
                    'name' => __( 'GRFuel Manager', 'secure-form-vault' ),
                    'singular_name' => __( 'GRFuel Form', 'secure-form-vault' ),
                    'add_new_item' => __( 'Νέα φόρμα', 'secure-form-vault' ),
                    'edit_item' => __( 'Επεξεργασία φόρμας', 'secure-form-vault' ),
                ),
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => false,
                'menu_icon' => $this->admin_icon(),
                'supports' => array( 'title' ),
                'map_meta_cap' => true,
                'capability_type' => array( 'grfuel_form', 'grfuel_forms' ),
                'capabilities' => array(
                    'edit_post' => 'edit_grfuel_form', 'read_post' => 'read_grfuel_form', 'delete_post' => 'delete_grfuel_form',
                    'edit_posts' => 'edit_grfuel_forms', 'edit_others_posts' => 'edit_others_grfuel_forms',
                    'publish_posts' => 'publish_grfuel_forms', 'read_private_posts' => 'read_private_grfuel_forms',
                    'delete_posts' => 'delete_grfuel_forms', 'delete_private_posts' => 'delete_private_grfuel_forms',
                    'delete_published_posts' => 'delete_published_grfuel_forms', 'delete_others_posts' => 'delete_others_grfuel_forms',
                    'edit_private_posts' => 'edit_private_grfuel_forms', 'edit_published_posts' => 'edit_published_grfuel_forms',
                    'create_posts' => 'edit_grfuel_forms',
                ),
            )
        );
    }


    public function form_title_placeholder( string $title, WP_Post $post ): string {
        return self::CPT === $post->post_type ? __( 'Όνομα φόρμας', 'secure-form-vault' ) : $title;
    }

    public function add_form_meta_box(): void {
        add_meta_box( 'sfv_fields', __( 'Πεδία φόρμας', 'secure-form-vault' ), array( $this, 'render_form_meta_box' ), self::CPT, 'normal', 'high' );
    }

    public function render_form_meta_box( WP_Post $post ): void {
        wp_nonce_field( 'sfv_save_form', 'sfv_form_nonce' );
        $fields = get_post_meta( $post->ID, '_sfv_fields', true );
        if ( ! is_array( $fields ) ) {
            $fields = array();
        }
        $confirmation = wp_parse_args(
            get_post_meta( $post->ID, '_sfv_confirmation', true ),
            array(
                'success_message' => __( 'Ευχαριστούμε. Το αίτημα υποβλήθηκε με επιτυχία.', 'secure-form-vault' ),
                'email_field'     => '',
                'email_subject'   => __( 'Επιβεβαίωση παραλαβής αιτήματος', 'secure-form-vault' ),
                'email_message'   => __( "Ευχαριστούμε για την επικοινωνία. Το αίτημά μας παραλήφθηκε με επιτυχία και θα εξεταστεί το συντομότερο δυνατό.", 'secure-form-vault' ),
            )
        );
        ?>
        <div class="sfv-form-hero">
            <div><span class="sfv-eyebrow"><?php esc_html_e( 'Dazzlink · Οπτικός σχεδιαστής φόρμας', 'secure-form-vault' ); ?></span><h2><?php esc_html_e( 'Σχεδιάστε τη φόρμα σας', 'secure-form-vault' ); ?></h2><p><?php esc_html_e( 'Σύρετε στοιχεία στον καμβά, πατήστε μια κάρτα για επεξεργασία και αλλάξτε τη σειρά εμφάνισης με τη λαβή μετακίνησης.', 'secure-form-vault' ); ?></p></div>
            <div class="sfv-shortcode-card"><span><?php esc_html_e( 'Σύντομος κώδικας', 'secure-form-vault' ); ?></span><code>[grfuel_form id="<?php echo absint( $post->ID ); ?>"]</code></div>
        </div>
        <div id="sfv-builder" data-fields="<?php echo esc_attr( wp_json_encode( $fields ) ); ?>"></div>
        <input type="hidden" id="sfv_fields_json" name="sfv_fields_json" value="<?php echo esc_attr( wp_json_encode( $fields ) ); ?>">
        <input type="hidden" id="sfv_return_to_forms" name="sfv_return_to_forms" value="0">

        <section class="sfv-form-confirmation-panel">
            <div class="sfv-form-confirmation-heading">
                <div><span class="sfv-eyebrow"><?php esc_html_e( 'Απαντήσεις μετά την υποβολή', 'secure-form-vault' ); ?></span><h3><?php esc_html_e( 'Επιβεβαίωση χρήστη', 'secure-form-vault' ); ?></h3><p><?php esc_html_e( 'Ορίζουμε ξεχωριστά το μήνυμα που εμφανίζεται στη σελίδα και το email που αποστέλλεται στον χρήστη.', 'secure-form-vault' ); ?></p></div>
            </div>
            <div class="sfv-confirmation-grid">
                <label class="sfv-confirmation-control sfv-confirmation-wide"><span><?php esc_html_e( 'Μήνυμα επιτυχίας στη σελίδα', 'secure-form-vault' ); ?></span><textarea name="sfv_success_message" rows="3" maxlength="2000"><?php echo esc_textarea( $confirmation['success_message'] ); ?></textarea></label>
                <label class="sfv-confirmation-control"><span><?php esc_html_e( 'Email πεδίο παραλήπτη', 'secure-form-vault' ); ?></span><select id="sfv_confirmation_email_field" name="sfv_confirmation_email_field" data-selected="<?php echo esc_attr( $confirmation['email_field'] ); ?>"><option value=""><?php esc_html_e( 'Χωρίς αποστολή email', 'secure-form-vault' ); ?></option></select><small><?php esc_html_e( 'Επιλέγουμε ποιο Email field της φόρμας περιέχει τη διεύθυνση του χρήστη.', 'secure-form-vault' ); ?></small></label>
                <label class="sfv-confirmation-control"><span><?php esc_html_e( 'Θέμα email', 'secure-form-vault' ); ?></span><input type="text" name="sfv_confirmation_email_subject" maxlength="200" value="<?php echo esc_attr( $confirmation['email_subject'] ); ?>"></label>
                <label class="sfv-confirmation-control sfv-confirmation-wide"><span><?php esc_html_e( 'Μήνυμα email', 'secure-form-vault' ); ?></span><textarea name="sfv_confirmation_email_message" rows="6" maxlength="10000"><?php echo esc_textarea( $confirmation['email_message'] ); ?></textarea><small><?php esc_html_e( 'Διαθέσιμες μεταβλητές: {form_title}, {submission_id} και {field_name}, όπου field_name είναι το editable name ενός πεδίου.', 'secure-form-vault' ); ?></small></label>
            </div>
        </section>
        <div class="sfv-form-save-bar"><button type="button" class="sfv-btn sfv-btn-primary" id="sfv-save-form"><span class="dashicons dashicons-saved"></span><?php esc_html_e( 'Αποθήκευση φόρμας', 'secure-form-vault' ); ?></button></div>
        <?php
    }


    public function forms_screen_nav(): void {
        $screen = get_current_screen();
        if ( ! $screen || self::CPT !== $screen->post_type || ! empty( $_GET['page'] ) ) {
            return;
        }
        $is_editor = in_array( $screen->base, array( 'post', 'post-new' ), true );
        echo '<div class="sfv-inline-nav">';
        $this->admin_nav( 'forms' );
        $this->section_intro(
            $is_editor ? 'Δημιουργία και επεξεργασία φόρμας' : 'Διαχείριση φορμών',
            $is_editor ? 'Σχεδιάζουμε τη δομή, τα πεδία, το responsive grid και τους κανόνες εμφάνισης της φόρμας.' : 'Οργανώνουμε όλες τις φόρμες, ελέγχουμε την κατάστασή τους και ανοίγουμε γρήγορα τον builder ή τις υποβολές.'
        );
        echo '</div>';
    }

    public function admin_assets( string $hook ): void {
        $screen = get_current_screen();
        if ( current_user_can( self::CAPABILITY ) ) {
            $settings = $this->settings();
            if ( ! empty( $settings['notification_enabled'] ) ) {
                wp_enqueue_script( 'grfuel-notifications', plugins_url( 'assets/notifications.js', __FILE__ ), array(), self::VERSION, true );
                wp_localize_script( 'grfuel-notifications', 'grfuelNotify', array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'grfuel_check_orders' ),
                    'interval' => max( 15, absint( $settings['notification_interval'] ) ) * 1000,
                    'sound' => ! empty( $settings['notification_sound'] ),
                    'soundChoice' => sanitize_key( $settings['notification_sound_choice'] ?? 'bell' ),
                    'desktop' => ! empty( $settings['notification_desktop'] ),
                    'ordersUrl' => admin_url( 'admin.php?page=sfv-submissions' ),
                    'appName' => 'GRFuel Manager',
                    'inApp' => true,
                    'requireInteraction' => true,
                ) );
            }
        }
        if ( ! $screen || ( self::CPT !== $screen->post_type && ! str_contains( (string) $screen->id, 'grfuel-manager' ) ) ) return;
        $push_settings = $this->settings();
        if ( current_user_can( self::CAPABILITY ) && ! empty( $push_settings['push_enabled'] ) && ! empty( $push_settings['onesignal_app_id'] ) ) {
            $alias = $this->push_alias_for_user( get_current_user_id() );
            $worker_url = plugins_url( 'assets/OneSignalSDKWorker.js', __FILE__ );
            $worker_path = (string) wp_parse_url( $worker_url, PHP_URL_PATH );
            $worker_scope = trailingslashit( dirname( $worker_path ) );
            wp_enqueue_script( 'grfuel-onesignal-sdk', 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js', array(), null, false );
            wp_script_add_data( 'grfuel-onesignal-sdk', 'defer', true );
            wp_enqueue_script( 'grfuel-push-admin', plugins_url( 'assets/push-admin.js', __FILE__ ), array( 'grfuel-onesignal-sdk' ), self::VERSION, true );
            wp_localize_script( 'grfuel-push-admin', 'grfuelPush', array(
                'appId' => sanitize_text_field( $push_settings['onesignal_app_id'] ),
                'externalId' => $alias,
                'workerPath' => $worker_path,
                'workerScope' => $worker_scope,
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'grfuel_push_test' ),
                'isHttps' => is_ssl(),
            ) );
        }
        wp_enqueue_style( 'grfuel-geom-font', 'https://fonts.googleapis.com/css2?family=Geom:ital,wght@0,300..900;1,300..900&family=Libertinus+Sans:ital,wght@0,400;0,700;1,400&display=swap', array(), null );
        wp_enqueue_style( 'sfv-admin', plugins_url( 'assets/admin.css', __FILE__ ), array( 'grfuel-geom-font' ), self::VERSION );
        wp_enqueue_script( 'sfv-admin', plugins_url( 'assets/admin.js', __FILE__ ), array(), self::VERSION, true );
        if ( isset( $_GET['page'] ) && 'grfuel-new-order' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
            wp_enqueue_style( 'sfv-front', plugins_url( 'assets/front.css', __FILE__ ), array(), self::VERSION );
            wp_enqueue_script( 'sfv-front', plugins_url( 'assets/front.js', __FILE__ ), array( 'jquery', 'jquery-ui-datepicker' ), self::VERSION, true );
            wp_localize_script( 'sfv-front', 'sfvFront', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'deliveryLocations' => $this->delivery_locations(),
            ) );
        }
        if ( str_contains( $hook, 'sfv-settings' ) ) wp_enqueue_media();
        if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) wp_localize_script( 'sfv-admin', 'sfvBuilder', array(
            'fieldsLabel' => __( 'Πεδία', 'secure-form-vault' ),
            'dailyPrices' => array_values( array_map( static fn( $product ) => array(
                'slug' => sanitize_title( (string) ( $product['slug'] ?? '' ) ),
                'name' => (string) ( $product['name'] ?? '' ),
                'price' => number_format( (float) ( $product['price'] ?? 0 ), 3, '.', '' ),
            ), (array) ( $this->settings()['daily_prices'] ?? array() ) ) ),
        ) );
    }

    public function create_form_from_manager(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'Δεν έχετε δικαίωμα για αυτή την ενέργεια.', 'secure-form-vault' ) );
        }
        check_admin_referer( 'grfuel_create_form' );
        $title = sanitize_text_field( wp_unslash( $_POST['form_name'] ?? '' ) );
        if ( '' === $title ) {
            wp_safe_redirect( add_query_arg( 'grfuel_form_error', 'missing_name', admin_url( 'admin.php?page=grfuel-forms' ) ) );
            exit;
        }
        $post_id = wp_insert_post( array(
            'post_type'   => self::CPT,
            'post_title'  => $title,
            'post_status' => 'draft',
        ), true );
        if ( is_wp_error( $post_id ) ) {
            wp_safe_redirect( add_query_arg( 'grfuel_form_error', 'create_failed', admin_url( 'admin.php?page=grfuel-forms' ) ) );
            exit;
        }
        wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
        exit;
    }

    public function delete_form_from_manager(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'Δεν έχετε δικαίωμα για αυτή την ενέργεια.', 'secure-form-vault' ) );
        }
        $form_id = absint( $_POST['form_id'] ?? 0 );
        check_admin_referer( 'grfuel_delete_form_' . $form_id );
        if ( ! $form_id || self::CPT !== get_post_type( $form_id ) ) {
            wp_safe_redirect( add_query_arg( 'grfuel_form_error', 'delete_failed', admin_url( 'admin.php?page=grfuel-forms' ) ) );
            exit;
        }
        wp_delete_post( $form_id, true );
        wp_safe_redirect( add_query_arg( 'grfuel_form_deleted', '1', admin_url( 'admin.php?page=grfuel-forms' ) ) );
        exit;
    }

    public function redirect_form_after_save( string $location, int $post_id ): string {
        if ( self::CPT === get_post_type( $post_id ) && isset( $_POST['sfv_return_to_forms'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['sfv_return_to_forms'] ) ) ) {
            return add_query_arg( 'grfuel_form_saved', '1', admin_url( 'admin.php?page=grfuel-forms' ) );
        }
        return $location;
    }

    public function register_front_assets(): void {
        wp_register_style( 'sfv-front', plugins_url( 'assets/front.css', __FILE__ ), array(), self::VERSION );
        wp_register_script( 'sfv-front', plugins_url( 'assets/front.js', __FILE__ ), array( 'jquery', 'jquery-ui-datepicker' ), self::VERSION, true );
        // Register shared runtime data before a shortcode or theme can enqueue
        // and print the frontend bundle. Late localization from inside the
        // shortcode is unreliable with builders that render content in stages.
        wp_localize_script( 'sfv-front', 'sfvFront', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'deliveryLocations' => $this->delivery_locations(),
        ) );
        wp_localize_script( 'sfv-front', 'grfuelChart', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
        wp_register_script( 'sfv-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
    }

    public function save_form( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST['sfv_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sfv_form_nonce'] ) ), 'sfv_save_form' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        $raw = isset( $_POST['sfv_fields_json'] ) ? wp_unslash( $_POST['sfv_fields_json'] ) : '[]';
        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return;
        }
        $allowed_types = array( 'text', 'email', 'phone', 'number', 'date', 'dropdown', 'radio', 'delivery_region', 'delivery_city', 'checkbox', 'textarea', 'heading', 'content_text', 'divider', 'price_calculator', 'single_price_calculator', 'submit', 'turnstile' );
        $clean = array();
        $used_keys = array();
        $key_map = array();
        $unique_types = array( 'submit' => false, 'turnstile' => false );
        foreach ( array_slice( $decoded, 0, 50 ) as $index => $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }
            $type = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
            if ( ! in_array( $type, $allowed_types, true ) ) {
                continue;
            }
            if ( isset( $unique_types[ $type ] ) ) {
                if ( $unique_types[ $type ] ) {
                    continue;
                }
                $unique_types[ $type ] = true;
            }
            $original_key = isset( $field['key'] ) ? sanitize_key( $field['key'] ) : '';
            $key = $original_key;
            if ( '' === $key ) {
                $key = 'field_' . ( $index + 1 );
            }
            $base_key = mb_substr( sanitize_key( $key ), 0, 64 );
            if ( '' === $base_key ) {
                $base_key = 'field_' . ( $index + 1 );
            }
            $key = $base_key;
            $suffix = 2;
            while ( isset( $used_keys[ $key ] ) ) {
                $suffix_text = '_' . $suffix++;
                $key = mb_substr( $base_key, 0, 64 - strlen( $suffix_text ) ) . $suffix_text;
            }
            $used_keys[ $key ] = true;
            if ( '' !== $original_key && ! isset( $key_map[ $original_key ] ) ) {
                $key_map[ $original_key ] = $key;
            }
            $options = array();
            if ( in_array( $type, array( 'dropdown', 'radio' ), true ) && isset( $field['options'] ) && is_array( $field['options'] ) ) {
                foreach ( array_slice( $field['options'], 0, 100 ) as $option ) {
                    $option = sanitize_text_field( (string) $option );
                    if ( '' !== $option ) {
                        $options[] = $option;
                    }
                }
            }
            $heading_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
            $heading_styles = array( 'default', 'small', 'medium', 'large', 'xlarge', '2xlarge', '3xlarge' );
            $divider_styles = array( 'default', 'small', 'icon' );
            $heading_tag = sanitize_key( $field['heading_tag'] ?? 'h2' );
            $heading_style = sanitize_key( $field['heading_style'] ?? 'default' );
            $divider_style = sanitize_key( $field['divider_style'] ?? 'default' );
            $pricing_rows = array();
            if ( in_array( $type, array( 'price_calculator', 'single_price_calculator' ), true ) ) {
                $pricing_rows = $this->sanitize_pricing_rows( $field['pricing_rows'] ?? array() );
            }
            $clean[] = array(
                'key' => $key,
                'type' => $type,
                'label' => 'checkbox' === $type
                    ? $this->sanitize_checkbox_label( (string) ( $field['label'] ?? '' ) )
                    : sanitize_text_field( $field['label'] ?? '' ),
                'checkbox_value' => mb_substr( sanitize_text_field( $field['checkbox_value'] ?? __( 'Συμφωνία με την Πολιτική Απορρήτου', 'secure-form-vault' ) ), 0, 300 ),
                'content' => 'content_text' === $type ? mb_substr( sanitize_textarea_field( $field['content'] ?? '' ), 0, 10000 ) : sanitize_text_field( $field['content'] ?? '' ),
                'heading_tag' => in_array( $heading_tag, $heading_tags, true ) ? $heading_tag : 'h2',
                'heading_style' => in_array( $heading_style, $heading_styles, true ) ? $heading_style : 'default',
                'divider_style' => in_array( $divider_style, $divider_styles, true ) ? $divider_style : 'default',
                'product_field' => sanitize_key( $field['product_field'] ?? '' ),
                'product_label' => mb_substr( sanitize_text_field( $field['product_label'] ?? 'Πετρέλαιο θέρμανσης' ), 0, 200 ),
                'daily_price_product' => 'single_price_calculator' === $type ? sanitize_title( (string) ( $field['daily_price_product'] ?? '' ) ) : '',
                'quantity_field' => sanitize_key( $field['quantity_field'] ?? '' ),
                'pricing_rows' => $pricing_rows,
                'currency' => mb_substr( sanitize_text_field( $field['currency'] ?? '€' ), 0, 8 ),
                'unit_label' => mb_substr( sanitize_text_field( $field['unit_label'] ?? 'λίτρο' ), 0, 40 ),
                'total_label' => mb_substr( sanitize_text_field( $field['total_label'] ?? 'Σύνολο' ), 0, 80 ),
                'calculator_placeholder' => mb_substr( sanitize_text_field( $field['calculator_placeholder'] ?? 'Αυτόματος υπολογισμός κόστους παραγγελίας' ), 0, 160 ),
                'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ),
                'suffix' => 'number' === $type ? mb_substr( sanitize_text_field( $field['suffix'] ?? 'lt' ), 0, 20 ) : '',
                'required' => ! empty( $field['required'] ),
                'hide_label' => ! empty( $field['hide_label'] ),
                'options' => $options,
                'message_only_enabled' => 'radio' === $type && ! empty( $field['message_only_enabled'] ),
                'message_only_option' => 'radio' === $type && in_array( sanitize_text_field( $field['message_only_option'] ?? '' ), $options, true ) ? sanitize_text_field( $field['message_only_option'] ?? '' ) : '',
                'message_only_text' => 'radio' === $type ? mb_substr( sanitize_textarea_field( $field['message_only_text'] ?? 'Οι παραδόσεις σε οικιακούς πελάτες αφορούν αποκλειστικά πετρέλαιο θέρμανσης, το οποίο δεν είναι διαθέσιμο αυτή την περίοδο.' ), 0, 1000 ) : '',
                'location_region_field' => sanitize_key( $field['location_region_field'] ?? '' ),
                'layout' => $this->sanitize_layout( $field['layout'] ?? array(), $field['widths'] ?? array() ),
                'dependency' => $this->sanitize_dependency( $field['dependency'] ?? array() ),
            );
        }
        foreach ( $clean as &$clean_field ) {
            $dependency_field = $clean_field['dependency']['field'] ?? '';
            if ( '' !== $dependency_field && isset( $key_map[ $dependency_field ] ) ) {
                $clean_field['dependency']['field'] = $key_map[ $dependency_field ];
            } elseif ( '' !== $dependency_field && ! isset( $used_keys[ $dependency_field ] ) ) {
                $clean_field['dependency']['field'] = '';
            }

            // Keep calculator references aligned when field keys are sanitized or de-duplicated.
            foreach ( array( 'product_field', 'quantity_field', 'location_region_field' ) as $reference_key ) {
                $reference = $clean_field[ $reference_key ] ?? '';
                if ( '' !== $reference && isset( $key_map[ $reference ] ) ) {
                    $clean_field[ $reference_key ] = $key_map[ $reference ];
                } elseif ( '' !== $reference && ! isset( $used_keys[ $reference ] ) ) {
                    $clean_field[ $reference_key ] = '';
                }
            }
        }
        unset( $clean_field );
        update_post_meta( $post_id, '_sfv_fields', $clean );

        $email_keys = array();
        foreach ( $clean as $clean_field ) {
            if ( 'email' === ( $clean_field['type'] ?? '' ) ) {
                $email_keys[] = (string) $clean_field['key'];
            }
        }
        $email_field = isset( $_POST['sfv_confirmation_email_field'] ) ? sanitize_key( wp_unslash( $_POST['sfv_confirmation_email_field'] ) ) : '';
        if ( '' !== $email_field && ! in_array( $email_field, $email_keys, true ) ) {
            $email_field = '';
        }
        update_post_meta(
            $post_id,
            '_sfv_confirmation',
            array(
                'success_message' => mb_substr( sanitize_textarea_field( wp_unslash( $_POST['sfv_success_message'] ?? '' ) ), 0, 2000 ),
                'email_field'     => $email_field,
                'email_subject'   => mb_substr( sanitize_text_field( wp_unslash( $_POST['sfv_confirmation_email_subject'] ?? '' ) ), 0, 200 ),
                'email_message'   => mb_substr( sanitize_textarea_field( wp_unslash( $_POST['sfv_confirmation_email_message'] ?? '' ) ), 0, 10000 ),
            )
        );
    }

    public function shortcode( array $atts ): string {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'secure_contact_form' );
        $form_id = absint( $atts['id'] );
        if ( ! $form_id || self::CPT !== get_post_type( $form_id ) || 'publish' !== get_post_status( $form_id ) ) {
            return '';
        }
        $fields = get_post_meta( $form_id, '_sfv_fields', true );
        if ( ! is_array( $fields ) || empty( $fields ) ) {
            return '';
        }
        wp_enqueue_style( 'sfv-front' );
        wp_enqueue_script( 'sfv-front' );
        $settings = $this->settings();
        wp_add_inline_style( 'sfv-front', $this->appearance_css( $settings ) );
        $has_turnstile = $this->has_field_type( $fields, 'turnstile' );
        $has_submit = $this->has_field_type( $fields, 'submit' );
        if ( $has_turnstile && ! empty( $settings['site_key'] ) && ! empty( $settings['secret_key'] ) ) {
            wp_enqueue_script( 'sfv-turnstile' );
        }
        $status = isset( $_GET['sfv_status'] ) ? sanitize_key( wp_unslash( $_GET['sfv_status'] ) ) : '';
        $status_form = isset( $_GET['sfv_form'] ) ? absint( $_GET['sfv_form'] ) : 0;
        ob_start();
        if ( $status && $status_form === $form_id ) {
            $confirmation = wp_parse_args( get_post_meta( $form_id, '_sfv_confirmation', true ), array( 'success_message' => __( 'Ευχαριστούμε. Το αίτημα υποβλήθηκε με επιτυχία.', 'secure-form-vault' ) ) );
            $message = 'success' === $status ? (string) $confirmation['success_message'] : __( 'The form could not be submitted. Please check the fields and try again.', 'secure-form-vault' );
            if ( 'error' === $status && defined( 'WP_DEBUG' ) && WP_DEBUG && isset( $_GET['sfv_error'] ) ) {
                $message .= ' [' . sanitize_key( wp_unslash( $_GET['sfv_error'] ) ) . ']';
            }
            printf( '<div class="sfv-notice sfv-%1$s" role="status">%2$s</div>', esc_attr( $status ), esc_html( $message ) );
        }
        ?>
        <form class="sfv-form uk-form-stacked" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" accept-charset="UTF-8">
            <input type="hidden" name="action" value="sfv_submit">
            <input type="hidden" name="form_id" value="<?php echo absint( $form_id ); ?>">
            <input type="hidden" name="sfv_started" value="<?php echo esc_attr( (string) time() ); ?>">
            <?php wp_nonce_field( 'sfv_submit_' . $form_id, 'sfv_nonce' ); ?>
            <div class="sfv-hp" aria-hidden="true"><label>Website<input type="text" name="sfv_website" tabindex="-1" autocomplete="off"></label></div>
            <div class="sfv-grid uk-grid-small" uk-grid>
            <?php foreach ( $fields as $field ) : $this->render_field( $field, $form_id, $settings ); endforeach; ?>
            <?php if ( ! $has_submit ) : ?>
                <div class="sfv-field uk-width-1-1"><button type="submit" class="uk-button uk-button-primary"><?php esc_html_e( 'Submit', 'secure-form-vault' ); ?></button></div>
            <?php endif; ?>
            </div>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    private function render_field( array $field, int $form_id, array $settings, bool $admin_context = false ): void {
        $key = sanitize_key( $field['key'] ?? '' );
        $type = sanitize_key( $field['type'] ?? 'text' );
        $label = (string) ( $field['label'] ?? '' );
        $required = ! empty( $field['required'] );
        $hide_label = ( $admin_context || ! empty( $field['hide_label'] ) ) && in_array( $type, array( 'text', 'email', 'phone', 'date', 'number', 'dropdown', 'delivery_region', 'delivery_city', 'textarea' ), true );
        $required_attr = $required ? ' required' : '';
        $name = 'sfv_fields[' . $key . ']';
        $layout = $this->sanitize_layout( $field['layout'] ?? array(), $field['widths'] ?? array() );
        $classes = $admin_context
            ? 'grfuel-admin-field grfuel-admin-width-' . sanitize_html_class( $layout['width'] )
            : implode( ' ', $this->layout_classes( $field['layout'] ?? array(), $field['widths'] ?? array() ) );
        $dep = $this->sanitize_dependency( $field['dependency'] ?? array() );
        $dep_attrs = '';
        if ( 'heading' === $type ) {
            $tag = sanitize_key( $field['heading_tag'] ?? 'h2' );
            $tag = in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $tag : 'h2';
            $style = sanitize_key( $field['heading_style'] ?? 'default' );
            $styles = array( 'small', 'medium', 'large', 'xlarge', '2xlarge', '3xlarge' );
            $heading_class = in_array( $style, $styles, true ) ? ' uk-heading-' . $style : '';
            printf( '<div class="sfv-field sfv-content-element %1$s"><%2$s class="sfv-content-heading%3$s">%4$s</%2$s></div>', esc_attr( $classes ), tag_escape( $tag ), esc_attr( $heading_class ), esc_html( $field['content'] ?? $label ) );
            return;
        }
        if ( 'content_text' === $type ) {
            $content = (string) ( $field['content'] ?? '' );
            printf( '<div class="sfv-field sfv-content-element %1$s"><div class="sfv-content-text">%2$s</div></div>', esc_attr( $classes ), nl2br( esc_html( $content ) ) );
            return;
        }
        if ( 'divider' === $type ) {
            $style = sanitize_key( $field['divider_style'] ?? 'default' );
            $divider_class = 'small' === $style ? 'uk-divider-small' : ( 'icon' === $style ? 'uk-divider-icon' : '' );
            printf( '<div class="sfv-field sfv-content-element %1$s"><hr class="sfv-content-divider %2$s"></div>', esc_attr( $classes ), esc_attr( $divider_class ) );
            return;
        }
        if ( ! empty( $dep['field'] ) ) {
            $dep_attrs = sprintf( ' data-sfv-dependency="%s" data-sfv-operator="%s" data-sfv-value="%s"', esc_attr( $dep['field'] ), esc_attr( $dep['operator'] ), esc_attr( $dep['value'] ) );
            if ( $admin_context && ! $this->dependency_matches( $dep, array() ) ) {
                $dep_attrs .= ' hidden aria-hidden="true"';
            }
        }
        if ( in_array( $type, array( 'price_calculator', 'single_price_calculator' ), true ) ) {
            $rows = $this->field_pricing_rows( $field );
            $quantity_field = sanitize_key( $field['quantity_field'] ?? '' );

            // Backward-compatible recovery: older alpha forms could lose the stored reference.
            // When there is exactly one Number field, the single calculator can safely use it.
            if ( 'single_price_calculator' === $type ) {
                $form_fields = (array) get_post_meta( $form_id, '_sfv_fields', true );
                $number_keys = array_values( array_filter( array_map(
                    static fn( $candidate ) => is_array( $candidate ) && 'number' === ( $candidate['type'] ?? '' ) ? sanitize_key( $candidate['key'] ?? '' ) : '',
                    $form_fields
                ) ) );
                $known_keys = array_values( array_filter( array_map(
                    static fn( $candidate ) => is_array( $candidate ) ? sanitize_key( $candidate['key'] ?? '' ) : '',
                    $form_fields
                ) ) );
                if ( ( '' === $quantity_field || ! in_array( $quantity_field, $known_keys, true ) ) && 1 === count( $number_keys ) ) {
                    $quantity_field = $number_keys[0];
                }
            }

            $config = array(
                'mode' => 'single_price_calculator' === $type ? 'single' : 'product',
                'productField' => sanitize_key( $field['product_field'] ?? '' ),
                'productLabel' => (string) ( $field['product_label'] ?? 'Πετρέλαιο θέρμανσης' ),
                'quantityField' => $quantity_field,
                'rows' => $rows,
                'currency' => (string) ( $field['currency'] ?? '€' ),
                'unitLabel' => (string) ( $field['unit_label'] ?? 'λίτρο' ),
                'placeholder' => (string) ( $field['calculator_placeholder'] ?? 'Αυτόματος υπολογισμός κόστους παραγγελίας' ),
            );
            $price_hide_label = ! empty( $field['hide_label'] );
            $price_label = (string) ( $field['total_label'] ?? __( 'Σύνολο', 'secure-form-vault' ) );
            printf(
                '<div class="sfv-field sfv-price-calculator-cell %1$s"%3$s><div class="sfv-price-calculator" data-sfv-pricing="%2$s"><span class="sfv-visually-hidden">%6$s</span><div class="sfv-price-line" aria-live="polite"><span class="sfv-price-unit">—</span><strong class="sfv-price-total">—</strong></div></div></div>',
                esc_attr( $classes ),
                esc_attr( wp_json_encode( $config, JSON_UNESCAPED_UNICODE ) ),
                $dep_attrs,
                $price_hide_label ? ' sfv-price-line-no-label' : '',
                $price_hide_label ? ' sfv-visually-hidden' : '',
                esc_html( $price_label )
            );
            return;
        }
        if ( 'submit' === $type ) {
            printf( '<div class="sfv-field %1$s"><button type="submit" class="uk-button uk-button-primary">%2$s</button></div>', esc_attr( $classes ), esc_html( $label ?: __( 'Submit', 'secure-form-vault' ) ) );
            return;
        }
        if ( 'turnstile' === $type ) {
            if ( ! empty( $settings['site_key'] ) && ! empty( $settings['secret_key'] ) ) {
                printf( '<div class="sfv-field %1$s"><div class="cf-turnstile" data-sitekey="%2$s" data-action="sfv_form_%3$d"></div></div>', esc_attr( $classes ), esc_attr( $settings['site_key'] ), absint( $form_id ) );
            } elseif ( current_user_can( 'manage_options' ) ) {
                printf( '<div class="sfv-field %1$s"><div class="uk-alert-warning" uk-alert>%2$s</div></div>', esc_attr( $classes ), esc_html__( 'Turnstile keys are not configured.', 'secure-form-vault' ) );
            }
            return;
        }
        $placeholder = (string) ( $field['placeholder'] ?? '' );
        if ( $hide_label ) {
            $placeholder = '' !== trim( $placeholder ) ? $placeholder : $label;
            if ( $required && '' !== trim( $placeholder ) && ! preg_match( '/\*\s*$/u', $placeholder ) ) {
                $placeholder .= ' *';
            }
        }
        $label_class = $hide_label ? 'uk-form-label sfv-visually-hidden' : 'uk-form-label';
        $label_for = 'date' === $type ? 'sfv-date-display-' . $form_id . '-' . $key : 'sfv-' . $key;
        $message_only_attrs = '';
        if ( 'radio' === $type && ! empty( $field['message_only_enabled'] ) && '' !== trim( (string) ( $field['message_only_option'] ?? '' ) ) ) {
            $message_only_attrs = sprintf(
                ' data-sfv-message-only-option="%1$s" data-sfv-message-only-text="%2$s"',
                esc_attr( (string) $field['message_only_option'] ),
                esc_attr( (string) ( $field['message_only_text'] ?? '' ) )
            );
        }
        printf( '<div class="sfv-field %1$s" data-sfv-field="%2$s"%3$s%4$s>', esc_attr( $classes ), esc_attr( $key ), $dep_attrs, $message_only_attrs );
        if ( 'radio' === $type ) {
            echo '<fieldset class="sfv-radio-group"><legend class="uk-form-label">' . esc_html( $label ) . ( $required ? ' *' : '' ) . '</legend><div class="sfv-radio-options">';
            foreach ( (array) ( $field['options'] ?? array() ) as $option_index => $option ) {
                printf( '<label><input class="uk-radio" type="radio" name="%1$s" value="%2$s"%3$s> %4$s</label>', esc_attr( $name ), esc_attr( $option ), 0 === $option_index ? $required_attr : '', esc_html( $option ) );
            }
            echo '</div></fieldset>';
            if ( ! empty( $field['message_only_enabled'] ) && '' !== trim( (string) ( $field['message_only_option'] ?? '' ) ) ) {
                $message_text = trim( (string) ( $field['message_only_text'] ?? '' ) );
                if ( '' === $message_text ) {
                    $message_text = 'Οι παραδόσεις σε οικιακούς πελάτες αφορούν αποκλειστικά πετρέλαιο θέρμανσης, το οποίο δεν είναι διαθέσιμο αυτή την περίοδο.';
                }
                printf( '<div class="sfv-message-only-notice" role="status" aria-live="polite" hidden><span class="sfv-message-only-icon" aria-hidden="true">!</span><p>%s</p></div>', esc_html( $message_text ) );
            }
        } elseif ( 'checkbox' === $type ) {
            $checkbox_label = $this->sanitize_checkbox_label( (string) $label );
            printf( '<label class="uk-form-label"><input class="uk-checkbox" id="sfv-%1$s" type="checkbox" name="%2$s" value="1"%3$s> <span>%4$s%5$s</span></label>', esc_attr( $key ), esc_attr( $name ), $required_attr, $checkbox_label, $required ? ' *' : '' );
        } else {
            printf( '<label class="%1$s" for="%2$s">%3$s%4$s</label><div class="uk-form-controls">', esc_attr( $label_class ), esc_attr( $label_for ), esc_html( $label ), $required && ! $hide_label ? ' *' : '' );
            if ( 'textarea' === $type ) {
                printf( '<textarea class="uk-textarea" id="sfv-%1$s" name="%2$s" placeholder="%3$s"%4$s></textarea>', esc_attr( $key ), esc_attr( $name ), esc_attr( $placeholder ), $required_attr );
            } elseif ( 'delivery_region' === $type ) {
                $select_prompt = '' !== trim( $placeholder ) ? $placeholder : __( 'Επιλέξτε νομό…', 'secure-form-vault' );
                printf( '<select class="uk-select sfv-delivery-region" id="sfv-%1$s" name="%2$s" data-sfv-location-region="%1$s"%3$s><option value="">%4$s</option>', esc_attr( $key ), esc_attr( $name ), $required_attr, esc_html( $select_prompt ) );
                foreach ( array_keys( $this->delivery_locations() ) as $region ) {
                    printf( '<option value="%1$s">%2$s</option>', esc_attr( $region ), esc_html( $region ) );
                }
                echo '</select>';
            } elseif ( 'delivery_city' === $type ) {
                $region_field = sanitize_key( $field['location_region_field'] ?? '' );
                $select_prompt = '' !== trim( $placeholder ) ? $placeholder : __( 'Επιλέξτε πόλη…', 'secure-form-vault' );
                printf( '<select class="uk-select sfv-delivery-city" id="sfv-%1$s" name="%2$s" data-sfv-location-city="1" data-sfv-region-field="%3$s" data-sfv-placeholder="%4$s"%5$s disabled><option value="">%4$s</option></select>', esc_attr( $key ), esc_attr( $name ), esc_attr( $region_field ), esc_attr( $select_prompt ), $required_attr );
            } elseif ( 'dropdown' === $type ) {
                $select_prompt = $hide_label ? $placeholder : ( '' !== trim( $placeholder ) ? $placeholder : __( 'Select…', 'secure-form-vault' ) );
                printf( '<select class="uk-select" id="sfv-%1$s" name="%2$s"%3$s><option value="">%4$s</option>', esc_attr( $key ), esc_attr( $name ), $required_attr, esc_html( $select_prompt ) );
                foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
                    printf( '<option value="%1$s">%2$s</option>', esc_attr( $option ), esc_html( $option ) );
                }
                echo '</select>';
            } elseif ( 'date' === $type ) {
                $display_id = 'sfv-date-display-' . $form_id . '-' . $key;
                $hidden_id = 'sfv-' . $form_id . '-' . $key;
                $date_placeholder = '' !== trim( $placeholder ) ? $placeholder : __( 'Επιθυμητή ημερομηνία παράδοσης', 'secure-form-vault' );
                if ( $required && $hide_label && ! preg_match( '/\*\s*$/u', $date_placeholder ) ) {
                    $date_placeholder .= ' *';
                }
                printf(
                    '<div class="sfv-date-wrap"><input class="uk-input sfv-datepicker" id="%1$s" type="text" placeholder="%2$s" autocomplete="off" inputmode="none" readonly%3$s data-sfv-date-target="%4$s"><span class="sfv-date-icon" aria-hidden="true"></span><input id="%4$s" type="hidden" name="%5$s"></div>',
                    esc_attr( $display_id ),
                    esc_attr( $date_placeholder ),
                    $required_attr,
                    esc_attr( $hidden_id ),
                    esc_attr( $name )
                );
            } else {
                $html_type = 'phone' === $type ? 'tel' : $type;
                if ( 'number' === $type ) {
                    $suffix = $admin_context ? '' : trim( (string) ( $field['suffix'] ?? 'lt' ) );
                    if ( '' === $suffix ) {
                        printf( '<input class="uk-input" id="sfv-%1$s" type="number" name="%2$s" placeholder="%3$s"%4$s>', esc_attr( $key ), esc_attr( $name ), esc_attr( $placeholder ), $required_attr );
                    } else {
                        printf(
                            '<div class="sfv-input-affix"><input class="uk-input" id="sfv-%1$s" type="number" name="%2$s" placeholder="%3$s"%4$s><span class="sfv-input-suffix" aria-hidden="true">%5$s</span></div>',
                            esc_attr( $key ),
                            esc_attr( $name ),
                            esc_attr( $placeholder ),
                            $required_attr,
                            esc_html( $suffix )
                        );
                    }
                } else {
                    $native_admin_class = $admin_context && in_array( $html_type, array( 'email', 'tel' ), true ) ? ' grfuel-admin-native-contact' : '';
                    $native_admin_style = $admin_context && in_array( $html_type, array( 'email', 'tel' ), true )
                        ? ' style="display:block!important;box-sizing:border-box!important;width:100%!important;min-width:0!important;height:44px!important;min-height:44px!important;max-height:44px!important;margin:0!important;padding:0 13px!important;border:1px solid #d8e0eb!important;border-radius:10px!important;background:#fff!important;background-image:none!important;color:#26324d!important;font-family:inherit!important;font-size:14px!important;font-weight:400!important;line-height:42px!important;box-shadow:none!important;outline:0!important;appearance:none!important;-webkit-appearance:none!important;background-clip:padding-box!important;"'
                        : '';
                    printf( '<input class="uk-input%6$s" id="sfv-%1$s" type="%2$s" name="%3$s" placeholder="%4$s"%5$s%7$s>', esc_attr( $key ), esc_attr( $html_type ), esc_attr( $name ), esc_attr( $placeholder ), $required_attr, esc_attr( $native_admin_class ), $native_admin_style );
                }
            }
            echo '</div>';
        }
        echo '</div>';
    }

    private function delivery_locations(): array {
        return array(
            'Αιτωλοακαρνανία' => array('Αγρίνιο','Μεσολόγγι','Ναύπακτος','Αμφιλοχία','Βόνιτσα','Θέρμο'),
            'Αργολίδα' => array('Άργος','Ναύπλιο','Κρανίδι','Επίδαυρος','Ερμιόνη'),
            'Αρκαδία' => array('Τρίπολη','Μεγαλόπολη','Άστρος','Λεωνίδιο','Βυτίνα'),
            'Άρτα' => array('Άρτα','Πέτα','Κομπότι','Βουργαρέλι'),
            'Αττική' => array('Αθήνα','Πειραιάς','Περιστέρι','Καλλιθέα','Γλυφάδα','Μαρούσι','Κηφισιά','Χαλάνδρι','Αχαρνές','Ελευσίνα','Μέγαρα','Λαύριο','Ραφήνα','Σαλαμίνα','Αίγινα'),
            'Αχαΐα' => array('Πάτρα','Αίγιο','Κάτω Αχαΐα','Καλάβρυτα','Ακράτα'),
            'Βοιωτία' => array('Λιβαδειά','Θήβα','Ορχομενός','Αλίαρτος','Δίστομο'),
            'Γρεβενά' => array('Γρεβενά','Δεσκάτη'),
            'Δράμα' => array('Δράμα','Δοξάτο','Προσοτσάνη','Νευροκόπι'),
            'Δωδεκάνησα' => array('Ρόδος','Κως','Κάλυμνος','Κάρπαθος','Λέρος','Πάτμος','Σύμη','Αστυπάλαια','Νίσυρος','Τήλος','Χάλκη','Κάσος','Λειψοί'),
            'Έβρος' => array('Αλεξανδρούπολη','Ορεστιάδα','Διδυμότειχο','Σουφλί','Φέρες','Σαμοθράκη'),
            'Εύβοια' => array('Χαλκίδα','Αλιβέρι','Κάρυστος','Ιστιαία','Αιδηψός','Κύμη','Ερέτρια','Σκύρος'),
            'Ευρυτανία' => array('Καρπενήσι','Φουρνά','Προυσός'),
            'Ζάκυνθος' => array('Ζάκυνθος','Λαγανάς','Αλυκές','Βολίμες'),
            'Ηλεία' => array('Πύργος','Αμαλιάδα','Αρχαία Ολυμπία','Γαστούνη','Κρέστενα','Ζαχάρω'),
            'Ημαθία' => array('Βέροια','Νάουσα','Αλεξάνδρεια','Μελίκη'),
            'Ηράκλειο' => array('Ηράκλειο','Μοίρες','Τυμπάκι','Χερσόνησος','Μάλια','Αρκαλοχώρι'),
            'Θεσπρωτία' => array('Ηγουμενίτσα','Παραμυθιά','Φιλιάτες','Σύβοτα'),
            'Θεσσαλονίκη' => array('Θεσσαλονίκη','Καλαμαριά','Εύοσμος','Ωραιόκαστρο','Θέρμη','Περαία','Λαγκαδάς','Χαλκηδόνα'),
            'Ιωάννινα' => array('Ιωάννινα','Μέτσοβο','Κόνιτσα','Ζίτσα','Δελβινάκι'),
            'Καβάλα' => array('Καβάλα','Χρυσούπολη','Ελευθερούπολη','Θάσος'),
            'Καρδίτσα' => array('Καρδίτσα','Σοφάδες','Παλαμάς','Μουζάκι'),
            'Καστοριά' => array('Καστοριά','Άργος Ορεστικό','Νεστόριο'),
            'Κέρκυρα' => array('Κέρκυρα','Λευκίμμη','Παλαιοκαστρίτσα','Κασσιόπη','Παξοί'),
            'Κεφαλονιά' => array('Αργοστόλι','Ληξούρι','Σάμη','Πόρος','Ιθάκη'),
            'Κιλκίς' => array('Κιλκίς','Πολύκαστρο','Γουμένισσα'),
            'Κοζάνη' => array('Κοζάνη','Πτολεμαΐδα','Σιάτιστα','Σέρβια','Βελβεντό'),
            'Κορινθία' => array('Κόρινθος','Λουτράκι','Κιάτο','Ξυλόκαστρο','Νεμέα'),
            'Κυκλάδες' => array('Σύρος','Μύκονος','Πάρος','Νάξος','Σαντορίνη','Ίος','Τήνος','Άνδρος','Μήλος','Σίφνος','Σέριφος','Κέα','Κύθνος','Αμοργός','Ανάφη','Φολέγανδρος','Σίκινος','Αντίπαρος','Κίμωλος'),
            'Λακωνία' => array('Σπάρτη','Γύθειο','Μολάοι','Νεάπολη','Μονεμβασιά','Σκάλα'),
            'Λάρισα' => array('Λάρισα','Τύρναβος','Φάρσαλα','Ελασσόνα','Αγιά'),
            'Λασίθι' => array('Άγιος Νικόλαος','Ιεράπετρα','Σητεία','Νεάπολη'),
            'Λέσβος' => array('Μυτιλήνη','Καλλονή','Πλωμάρι','Μήθυμνα','Άγιος Ευστράτιος','Λήμνος'),
            'Λευκάδα' => array('Λευκάδα','Νυδρί','Βασιλική'),
            'Μαγνησία' => array('Βόλος','Νέα Ιωνία','Αλμυρός','Βελεστίνο','Σκιάθος','Σκόπελος','Αλόννησος'),
            'Μεσσηνία' => array('Καλαμάτα','Μεσσήνη','Κυπαρισσία','Πύλος','Γαργαλιάνοι','Φιλιατρά'),
            'Ξάνθη' => array('Ξάνθη','Σταυρούπολη','Άβδηρα'),
            'Πέλλα' => array('Έδεσσα','Γιαννιτσά','Αριδαία','Σκύδρα'),
            'Πιερία' => array('Κατερίνη','Λιτόχωρο','Αιγίνιο','Λεπτοκαρυά'),
            'Πρέβεζα' => array('Πρέβεζα','Πάργα','Καναλλάκι','Φιλιππιάδα'),
            'Ρέθυμνο' => array('Ρέθυμνο','Ανώγεια','Πέραμα','Σπήλι'),
            'Ροδόπη' => array('Κομοτηνή','Σάπες','Ίασμος'),
            'Σάμος' => array('Σάμος','Καρλόβασι','Πυθαγόρειο','Ικαρία','Φούρνοι'),
            'Σέρρες' => array('Σέρρες','Σιδηρόκαστρο','Νιγρίτα','Ηράκλεια','Νέα Ζίχνη'),
            'Τρίκαλα' => array('Τρίκαλα','Καλαμπάκα','Πύλη','Φαρκαδόνα'),
            'Φθιώτιδα' => array('Λαμία','Στυλίδα','Αταλάντη','Καμένα Βούρλα','Δομοκός'),
            'Φλώρινα' => array('Φλώρινα','Αμύνταιο','Πρέσπες'),
            'Φωκίδα' => array('Άμφισσα','Ιτέα','Γαλαξίδι','Δελφοί','Λιδωρίκι'),
            'Χαλκιδική' => array('Πολύγυρος','Νέα Μουδανιά','Κασσάνδρεια','Νικήτη','Ιερισσός','Αρναία'),
            'Χανιά' => array('Χανιά','Κίσσαμος','Παλαιόχωρα','Σούδα','Βάμος','Χώρα Σφακίων'),
            'Χίος' => array('Χίος','Βροντάδος','Καρδάμυλα','Μεστά','Οινούσσες','Ψαρά'),
        );
    }

    private function sanitize_layout( $layout, $legacy_widths = array() ): array {
        $layout = is_array( $layout ) ? $layout : array();
        $allowed_widths = array( '1-1', '1-2', '1-3', '2-3' );
        $allowed_breakpoints = array( 's', 'm' );

        $width = sanitize_key( $layout['width'] ?? '' );
        $breakpoint = sanitize_key( $layout['breakpoint'] ?? '' );

        // Backward compatibility for forms created with the previous responsive-width model.
        if ( ! in_array( $width, $allowed_widths, true ) && is_array( $legacy_widths ) ) {
            foreach ( array( 's', 'm' ) as $legacy_breakpoint ) {
                $legacy_width = sanitize_key( $legacy_widths[ $legacy_breakpoint ] ?? '' );
                if ( in_array( $legacy_width, $allowed_widths, true ) ) {
                    $width = $legacy_width;
                    $breakpoint = $legacy_breakpoint;
                    break;
                }
            }
            if ( ! in_array( $width, $allowed_widths, true ) ) {
                $legacy_base = sanitize_key( $legacy_widths['base'] ?? '' );
                $width = in_array( $legacy_base, $allowed_widths, true ) ? $legacy_base : '1-1';
            }
        }

        if ( ! in_array( $width, $allowed_widths, true ) ) {
            $width = '1-1';
        }
        if ( ! in_array( $breakpoint, $allowed_breakpoints, true ) ) {
            $breakpoint = 's';
        }

        return array( 'width' => $width, 'breakpoint' => $breakpoint );
    }

    /**
     * Preserve the limited HTML allowed in checkbox labels and protect links
     * opening a new tab without relying on the deprecated WordPress helper.
     */
    private function sanitize_checkbox_label( string $label ): string {
        $label = wp_kses(
            $label,
            array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) )
        );

        return (string) preg_replace_callback(
            '/<a\b[^>]*>/i',
            static function ( array $match ): string {
                $tag = $match[0];
                if ( ! preg_match( '/\btarget\s*=\s*(["\'])_blank\1/i', $tag ) ) {
                    return $tag;
                }
                if ( preg_match( '/\brel\s*=\s*(["\'])(.*?)\1/i', $tag, $rel_match ) ) {
                    $tokens = preg_split( '/\s+/', strtolower( trim( $rel_match[2] ) ) ) ?: array();
                    $tokens = array_values( array_unique( array_filter( array_merge( $tokens, array( 'noopener', 'noreferrer' ) ) ) ) );
                    $replacement = 'rel=' . $rel_match[1] . implode( ' ', $tokens ) . $rel_match[1];
                    return (string) preg_replace( '/\brel\s*=\s*(["\'])(.*?)\1/i', $replacement, $tag, 1 );
                }
                return substr( $tag, 0, -1 ) . ' rel="noopener noreferrer">';
            },
            $label
        );
    }

    private function layout_classes( $layout, $legacy_widths = array() ): array {
        $layout = $this->sanitize_layout( $layout, $legacy_widths );
        return array( 'uk-width-1-1', 'uk-width-' . $layout['width'] . '@' . $layout['breakpoint'] );
    }

    private function has_field_type( array $fields, string $type ): bool {
        foreach ( $fields as $field ) {
            if ( is_array( $field ) && $type === sanitize_key( $field['type'] ?? '' ) ) {
                return true;
            }
        }
        return false;
    }

    private function sanitize_dependency( $dep ): array {
        $dep = is_array( $dep ) ? $dep : array();
        $operator = sanitize_key( $dep['operator'] ?? 'equals' );
        if ( ! in_array( $operator, array( 'equals','not_equals','contains','checked','not_checked','empty','not_empty' ), true ) ) $operator = 'equals';
        return array( 'field' => sanitize_key( $dep['field'] ?? '' ), 'operator' => $operator, 'value' => mb_substr( sanitize_text_field( $dep['value'] ?? '' ), 0, 200 ) );
    }

    public function handle_submission(): void {
        $this->require_post_request();
        $content_length = absint( $_SERVER['CONTENT_LENGTH'] ?? 0 );
        if ( $content_length > 256 * 1024 ) {
            status_header( 413 );
            wp_die( esc_html__( 'The submitted request is too large.', 'secure-form-vault' ) );
        }
        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        $redirect = wp_get_referer() ?: home_url( '/' );
        $fail = function ( string $reason = 'validation' ) use ( $redirect, $form_id ): void {
            $args = array( 'sfv_status' => 'error', 'sfv_form' => $form_id );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $args['sfv_error'] = sanitize_key( $reason );
            }
            wp_safe_redirect( add_query_arg( $args, $redirect ) );
            exit;
        };
        if ( ! $form_id || self::CPT !== get_post_type( $form_id ) || 'publish' !== get_post_status( $form_id ) ) {
            $fail( 'invalid_form' );
        }
        $nonce = isset( $_POST['sfv_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sfv_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'sfv_submit_' . $form_id ) ) {
            $fail( 'nonce' );
        }
        if ( ! empty( $_POST['sfv_website'] ) ) {
            $fail( 'honeypot' );
        }
        $started = isset( $_POST['sfv_started'] ) ? absint( $_POST['sfv_started'] ) : 0;
        if ( ! $started || time() - $started < 2 || time() - $started > DAY_IN_SECONDS ) {
            $fail( 'timing' );
        }
        $fields = get_post_meta( $form_id, '_sfv_fields', true );
        $fields = is_array( $fields ) ? $fields : array();
        $posted = isset( $_POST['sfv_fields'] ) && is_array( $_POST['sfv_fields'] ) ? wp_unslash( $_POST['sfv_fields'] ) : array();
        if ( count( $fields ) > 50 || count( $posted ) > 60 ) {
            $fail( 'field_limits' );
        }
        foreach ( $posted as $posted_key => $posted_value ) {
            if ( sanitize_key( (string) $posted_key ) !== (string) $posted_key || is_array( $posted_value ) || is_object( $posted_value ) ) {
                $fail( 'posted_shape' );
            }
        }
        if ( $this->has_field_type( (array) $fields, 'turnstile' ) && ! $this->verify_turnstile( $form_id ) ) {
            $fail( 'turnstile' );
        }
        $payload = array();
        foreach ( (array) $fields as $field ) {
            $key = sanitize_key( $field['key'] ?? '' );
            $type = sanitize_key( $field['type'] ?? 'text' );
            if ( in_array( $type, array( 'submit', 'turnstile', 'heading', 'content_text', 'divider', 'price_calculator', 'single_price_calculator' ), true ) ) {
                continue;
            }
            $raw = $posted[ $key ] ?? '';
            if ( is_array( $raw ) ) {
                $fail( 'array_' . $key );
            }
            $raw = (string) $raw;
            switch ( $type ) {
                case 'email':
                    $value = sanitize_email( $raw );
                    if ( '' !== $raw && ! is_email( $value ) ) { $fail( 'email_' . $key ); }
                    break;
                case 'phone':
                    $value = preg_replace( '/[^0-9+().\-\s]/', '', $raw );
                    $value = substr( (string) $value, 0, 60 );
                    break;
                case 'number':
                    $value = str_replace( ',', '.', sanitize_text_field( $raw ) );
                    if ( '' !== $value && ! is_numeric( $value ) ) { $fail( 'number_' . $key ); }
                    $value = '' === $value ? '' : (string) max( 0, (float) $value );
                    break;
                case 'date':
                    $value = sanitize_text_field( $raw );
                    if ( '' !== $value ) {
                        $date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
                        $errors = DateTimeImmutable::getLastErrors();
                        if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) || $date->format( 'Y-m-d' ) !== $value ) { $fail( 'date_' . $key ); }
                    }
                    break;
                case 'textarea':
                    $value = sanitize_textarea_field( $raw );
                    $value = mb_substr( $value, 0, 5000 );
                    break;
                case 'delivery_region':
                    $value = sanitize_text_field( $raw );
                    if ( '' !== $value && ! array_key_exists( $value, $this->delivery_locations() ) ) { $fail( 'region_' . $key ); }
                    break;
                case 'delivery_city':
                    $value = sanitize_text_field( $raw );
                    $region_key = sanitize_key( $field['location_region_field'] ?? '' );
                    if ( '' === $region_key ) {
                        foreach ( $fields as $candidate_field ) {
                            if ( is_array( $candidate_field ) && 'delivery_region' === sanitize_key( $candidate_field['type'] ?? '' ) ) {
                                $region_key = sanitize_key( $candidate_field['key'] ?? '' );
                                break;
                            }
                        }
                    }
                    $region_value = sanitize_text_field( (string) ( $posted[ $region_key ] ?? '' ) );
                    $allowed_cities = $this->delivery_locations()[ $region_value ] ?? array();
                    if ( '' !== $value && ! in_array( $value, $allowed_cities, true ) ) { $fail( 'city_' . $key ); }
                    break;
                case 'dropdown':
                case 'radio':
                    $value = sanitize_text_field( $raw );
                    if ( '' !== $value && ! in_array( $value, (array) ( $field['options'] ?? array() ), true ) ) { $fail( 'choice_' . $key ); }
                    if ( 'radio' === $type && ! empty( $field['message_only_enabled'] ) && $value === (string) ( $field['message_only_option'] ?? '' ) ) { $fail( 'unavailable_' . $key ); }
                    break;
                case 'checkbox':
                    $value = '1' === $raw ? mb_substr( sanitize_text_field( $field['checkbox_value'] ?? __( 'Συμφωνία με την Πολιτική Απορρήτου', 'secure-form-vault' ) ), 0, 300 ) : '';
                    break;
                default:
                    $value = mb_substr( sanitize_text_field( $raw ), 0, 500 );
            }
            $visible = $this->dependency_matches( $field['dependency'] ?? array(), $posted );
            if ( $visible && ! empty( $field['required'] ) && '' === $value ) {
                $fail( 'required_' . $key );
            }
            $payload[ $key ] = array( 'label' => sanitize_text_field( wp_strip_all_tags( (string) ( $field['label'] ?? $key ) ) ), 'value' => $value );
        }
        // Store the calculated order total at submission time. This guarantees
        // that future exports keep the price shown to the customer, even if the
        // form pricing changes later.
        $summary = $this->submission_summary( $payload, $form_id );
        $quantity_for_price = max( 0, (float) str_replace( ',', '.', (string) ( '—' === $summary['quantity'] ? 0 : $summary['quantity'] ) ) );
        $price_details = $this->order_price_details( $form_id, (string) ( '—' === $summary['product'] ? '' : $summary['product'] ), $quantity_for_price );
        if ( null !== $price_details ) {
            $payload['_sfv_order_total'] = array(
                'label' => 'Τιμή παραγγελίας',
                'value' => number_format( (float) $price_details['total'], 2, '.', '' ) . ' €',
            );
        }

        // Consume the legitimate submission quota only after the request has
        // passed Turnstile and the complete server-side form schema validation.
        if ( $this->is_rate_limited( $form_id ) ) {
            $fail( 'rate_limit' );
        }

        global $wpdb;
        $inserted = $wpdb->insert(
            $this->table(),
            array(
                'form_id' => $form_id,
                'submitted_at' => current_time( 'mysql', true ),
                'payload' => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ),
                'ip_hash' => $this->ip_hash(),
                'user_agent' => mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ),
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );
        if ( false === $inserted ) {
            $fail( 'database' );
        }
        $submission_id = (int) $wpdb->insert_id;
        $this->send_confirmation_email( $form_id, $submission_id, $payload );
        $this->send_admin_order_email( $form_id, $submission_id, $payload );
        $this->send_admin_push_notification( $submission_id );
        wp_safe_redirect( add_query_arg( array( 'sfv_status' => 'success', 'sfv_form' => $form_id ), $redirect ) );
        exit;
    }

    private function push_alias_for_user( int $user_id ): string {
        $alias = (string) get_user_meta( $user_id, 'grfuel_push_alias', true );
        if ( '' === $alias ) {
            $alias = 'grfuel-' . wp_generate_uuid4();
            update_user_meta( $user_id, 'grfuel_push_alias', $alias );
        }
        return $alias;
    }

    private function push_admin_aliases(): array {
        $aliases = array();
        foreach ( get_users( array( 'fields' => array( 'ID' ) ) ) as $user ) {
            if ( user_can( (int) $user->ID, self::CAPABILITY ) ) {
                $aliases[] = $this->push_alias_for_user( (int) $user->ID );
            }
        }
        return array_values( array_unique( array_filter( $aliases ) ) );
    }

    private function send_onesignal_push( array $aliases, string $heading, string $content, string $url, string $name = '' ) {
        $settings = $this->settings();
        if ( empty( $settings['push_enabled'] ) || empty( $settings['onesignal_app_id'] ) || empty( $settings['onesignal_api_key'] ) || ! $aliases ) {
            return new WP_Error( 'grfuel_push_not_configured', 'Το OneSignal Push δεν είναι πλήρως ρυθμισμένο.' );
        }
        $payload = array(
            'app_id' => sanitize_text_field( $settings['onesignal_app_id'] ),
            'target_channel' => 'push',
            'include_aliases' => array( 'external_id' => array_values( $aliases ) ),
            'headings' => array( 'en' => $heading, 'el' => $heading ),
            'contents' => array( 'en' => $content, 'el' => $content ),
            'url' => esc_url_raw( $url ),
            'name' => $name ?: $heading,
            'idempotency_key' => wp_generate_uuid4(),
        );
        $response = wp_remote_post( 'https://api.onesignal.com/notifications', array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Key ' . sanitize_text_field( $settings['onesignal_api_key'] ),
                'Content-Type' => 'application/json; charset=utf-8',
            ),
            'body' => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
        ) );
        if ( is_wp_error( $response ) ) return $response;
        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( $code < 200 || $code >= 300 ) {
            $message = is_array( $body ) ? wp_json_encode( $body, JSON_UNESCAPED_UNICODE ) : 'OneSignal HTTP ' . $code;
            return new WP_Error( 'grfuel_push_failed', $message );
        }
        return is_array( $body ) ? $body : array();
    }

    private function send_admin_push_notification( int $submission_id ): void {
        $settings = $this->settings();
        if ( empty( $settings['push_enabled'] ) ) return;
        $this->send_onesignal_push(
            $this->push_admin_aliases(),
            'Νέα παραγγελία #' . $submission_id,
            'Καταχωρίστηκε νέα παραγγελία. Πατήστε για προβολή.',
            admin_url( 'admin.php?page=sfv-submissions&submission=' . $submission_id ),
            'GRFuel order ' . $submission_id
        );
    }

    public function ajax_push_test(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) wp_send_json_error( array( 'message' => 'Δεν επιτρέπεται η πρόσβαση.' ), 403 );
        check_ajax_referer( 'grfuel_push_test', 'nonce' );
        $result = $this->send_onesignal_push(
            array( $this->push_alias_for_user( get_current_user_id() ) ),
            'GRFuel Manager',
            'Η δοκιμαστική push ειδοποίηση λειτουργεί σωστά.',
            admin_url( 'admin.php?page=sfv-settings' ),
            'GRFuel push test'
        );
        if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
        wp_send_json_success( array( 'message' => 'Η δοκιμαστική ειδοποίηση στάλθηκε στη συγκεκριμένη συσκευή.' ) );
    }

    private function send_admin_order_email( int $form_id, int $submission_id, array $payload ): void {
        $settings = $this->settings();
        if ( empty( $settings['notification_email'] ) ) return;
        $recipient = sanitize_email( $settings['notification_email_address'] ?? get_option( 'admin_email' ) );
        if ( ! is_email( $recipient ) ) return;
        $lines = array( 'Νέα παραγγελία #' . $submission_id, 'Φόρμα: ' . get_the_title( $form_id ), '' );
        foreach ( $payload as $item ) {
            $value = trim( (string) ( $item['value'] ?? '' ) );
            if ( '' !== $value ) $lines[] = sanitize_text_field( $item['label'] ?? '' ) . ': ' . $value;
        }
        $lines[] = '';
        $lines[] = admin_url( 'admin.php?page=sfv-submissions' );
        wp_mail( $recipient, 'Νέα παραγγελία #' . $submission_id . ' · ' . get_bloginfo( 'name' ), implode( "
", $lines ), array( 'Content-Type: text/plain; charset=UTF-8' ) );
    }

    private function send_confirmation_email( int $form_id, int $submission_id, array $payload ): void {
        $confirmation = wp_parse_args(
            get_post_meta( $form_id, '_sfv_confirmation', true ),
            array( 'email_field' => '', 'email_subject' => '', 'email_message' => '' )
        );
        $email_key = sanitize_key( $confirmation['email_field'] ?? '' );
        $recipient = isset( $payload[ $email_key ]['value'] ) ? sanitize_email( (string) $payload[ $email_key ]['value'] ) : '';
        if ( '' === $email_key || ! is_email( $recipient ) ) {
            return;
        }
        $form_title = get_the_title( $form_id );
        $replacements = array(
            '{form_title}'    => $form_title,
            '{submission_id}' => (string) $submission_id,
        );
        foreach ( $payload as $key => $entry ) {
            $replacements[ '{' . sanitize_key( (string) $key ) . '}' ] = (string) ( $entry['value'] ?? '' );
        }
        $subject = strtr( (string) $confirmation['email_subject'], $replacements );
        $message = strtr( (string) $confirmation['email_message'], $replacements );
        if ( '' === trim( $subject ) || '' === trim( $message ) ) {
            return;
        }
        wp_mail(
            $recipient,
            wp_strip_all_tags( $subject ),
            $message,
            array( 'Content-Type: text/plain; charset=UTF-8' )
        );
    }

    private function sanitize_pricing_rows( $rows ): array {
        $clean = array();
        if ( is_string( $rows ) ) {
            $decoded = json_decode( $rows, true );
            $rows = is_array( $decoded ) ? $decoded : array();
        }
        foreach ( array_slice( is_array( $rows ) ? $rows : array(), 0, 100 ) as $row ) {
            if ( ! is_array( $row ) ) continue;
            $product = mb_substr( sanitize_text_field( $row['product'] ?? '' ), 0, 200 );
            $base = isset( $row['base'] ) ? (float) str_replace( ',', '.', (string) $row['base'] ) : 0.0;
            if ( '' === $product || $base < 0 ) continue;
            $tiers = array();
            foreach ( array_slice( (array) ( $row['tiers'] ?? array() ), 0, 50 ) as $tier ) {
                if ( ! is_array( $tier ) ) continue;
                $min = max( 0, (float) str_replace( ',', '.', (string) ( $tier['min'] ?? 0 ) ) );
                $price = max( 0, (float) str_replace( ',', '.', (string) ( $tier['price'] ?? 0 ) ) );
                if ( $min > 0 && $price > 0 ) $tiers[] = array( 'min' => $min, 'price' => $price );
            }
            usort( $tiers, static fn( $a, $b ) => $a['min'] <=> $b['min'] );
            $clean[] = array( 'product' => $product, 'base' => $base, 'tiers' => $tiers );
        }
        return $clean;
    }

    private function daily_price_for( string $slug ): ?float {
        $slug = sanitize_title( $slug );
        if ( '' === $slug ) return null;
        foreach ( (array) ( $this->settings()['daily_prices'] ?? array() ) as $product ) {
            if ( $slug !== sanitize_title( (string) ( $product['slug'] ?? '' ) ) ) continue;
            $price = (float) ( $product['price'] ?? 0 );
            return $price > 0 ? $price : null;
        }
        return null;
    }

    // Pricing rows as used for display and order totals. A single-product calculator linked
    // to a daily price takes its base price from Τιμές Ημέρας; quantity tiers keep their
    // configured difference from the base (base 1.10 / tier 1.08 → daily 1.15 / tier 1.13).
    private function field_pricing_rows( array $field ): array {
        $rows = $this->sanitize_pricing_rows( $field['pricing_rows'] ?? array() );
        if ( 'single_price_calculator' !== sanitize_key( (string) ( $field['type'] ?? '' ) ) ) return $rows;
        $daily = $this->daily_price_for( (string) ( $field['daily_price_product'] ?? '' ) );
        if ( null === $daily ) return $rows;
        if ( ! $rows ) {
            return array( array( 'product' => trim( (string) ( $field['product_label'] ?? '' ) ) ?: 'Πετρέλαιο θέρμανσης', 'base' => $daily, 'tiers' => array() ) );
        }
        $stored_base = (float) $rows[0]['base'];
        foreach ( $rows[0]['tiers'] as &$tier ) {
            if ( $stored_base > 0 ) $tier['price'] = max( 0.001, round( $daily + ( (float) $tier['price'] - $stored_base ), 3 ) );
        }
        unset( $tier );
        $rows[0]['base'] = $daily;
        return $rows;
    }

    private function order_price_details( int $form_id, string $product, float $quantity = 0 ): ?array {
        if ( $quantity <= 0 ) return null;
        $fields = get_post_meta( $form_id, '_sfv_fields', true );
        foreach ( (array) $fields as $field ) {
            $type = sanitize_key( (string) ( $field['type'] ?? '' ) );
            if ( ! in_array( $type, array( 'price_calculator', 'single_price_calculator' ), true ) ) continue;
            $rows = $this->field_pricing_rows( (array) $field );
            if ( 'single_price_calculator' === $type ) {
                $fixed_product = trim( (string) ( $field['product_label'] ?? '' ) );
                $row = null;
                foreach ( $rows as $candidate ) {
                    if ( '' === $fixed_product || $candidate['product'] === $fixed_product ) { $row = $candidate; break; }
                }
                if ( ! $row && $rows ) $row = $rows[0];
            } else {
                $row = null;
                foreach ( $rows as $candidate ) {
                    if ( trim( (string) $candidate['product'] ) === trim( $product ) ) { $row = $candidate; break; }
                }
            }
            if ( ! $row ) continue;
            $unit = (float) $row['base'];
            foreach ( (array) $row['tiers'] as $tier ) {
                if ( $quantity >= (float) $tier['min'] ) $unit = (float) $tier['price'];
            }
            return array( 'unit' => $unit, 'total' => $quantity * $unit );
        }
        return null;
    }

    private function product_price( int $form_id, string $product, float $quantity = 0 ): ?float {
        $fields = get_post_meta( $form_id, '_sfv_fields', true );
        foreach ( (array) $fields as $field ) {
            if ( 'price_calculator' !== ( $field['type'] ?? '' ) ) continue;
            foreach ( $this->sanitize_pricing_rows( $field['pricing_rows'] ?? array() ) as $row ) {
                if ( $row['product'] !== $product ) continue;
                $price = (float) $row['base'];
                foreach ( $row['tiers'] as $tier ) if ( $quantity >= (float) $tier['min'] ) $price = (float) $tier['price'];
                return $price;
            }
        }
        return null;
    }

    public function price_shortcode( array $atts ): string {
        $atts = shortcode_atts( array( 'form' => 0, 'product' => '', 'quantity' => 0, 'currency' => '€' ), $atts, 'sfv_price' );
        $form_id = absint( $atts['form'] );
        $product = sanitize_text_field( (string) $atts['product'] );
        if ( ! $form_id || '' === $product ) return '';
        wp_enqueue_script( 'sfv-front' );
        wp_localize_script( 'sfv-front', 'sfvFront', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'deliveryLocations' => $this->delivery_locations() ) );
        return sprintf(
            '<span class="sfv-dynamic-price" data-form="%1$d" data-product="%2$s" data-quantity="%3$s" data-currency="%4$s">…</span>',
            $form_id, esc_attr( $product ), esc_attr( (string) max( 0, (float) $atts['quantity'] ) ), esc_attr( (string) $atts['currency'] )
        );
    }

    public function ajax_price(): void {
        nocache_headers();
        $form_id = absint( $_GET['form'] ?? 0 );
        $product = sanitize_text_field( wp_unslash( $_GET['product'] ?? '' ) );
        $quantity = max( 0, (float) str_replace( ',', '.', (string) ( $_GET['quantity'] ?? 0 ) ) );
        if ( ! $form_id || self::CPT !== get_post_type( $form_id ) || 'publish' !== get_post_status( $form_id ) ) wp_send_json_error( null, 404 );
        $price = $this->product_price( $form_id, $product, $quantity );
        if ( null === $price ) wp_send_json_error( null, 404 );
        wp_send_json_success( array( 'price' => number_format( $price, 3, '.', '' ), 'product' => $product, 'quantity' => $quantity ) );
    }

    public function daily_price_shortcode( array $atts ): string {
        $atts = shortcode_atts( array( 'product' => '', 'show_name' => 'no', 'decimals' => '3' ), $atts, 'grfuel_price' );
        $slug = sanitize_title( (string) $atts['product'] );
        $decimals = max( 0, min( 4, absint( $atts['decimals'] ) ) );
        foreach ( (array) $this->settings()['daily_prices'] as $product ) {
            if ( $slug !== sanitize_title( (string) ( $product['slug'] ?? '' ) ) ) continue;
            $name = sanitize_text_field( $product['name'] ?? '' );
            $unit = sanitize_text_field( $product['unit'] ?? 'λίτρο' );
            $price = (float) ( $product['price'] ?? 0 );
            $formatted = number_format_i18n( $price, $decimals ) . ' € / ' . $unit;
            if ( 'yes' === strtolower( (string) $atts['show_name'] ) ) $formatted = $name . ': ' . $formatted;
            return '<span class="grfuel-daily-price" data-product="' . esc_attr( $slug ) . '">' . esc_html( $formatted ) . '</span>';
        }
        return '<span class="grfuel-daily-price is-unavailable">—</span>';
    }

    private function price_history(): array {
        $history = get_option( self::PRICE_HISTORY_OPTION, array() );
        return is_array( $history ) ? $history : array();
    }

    private function record_daily_price_history( array $old_prices, array $new_prices ): void {
        $old = array();
        foreach ( $old_prices as $row ) $old[ sanitize_title( (string) ( $row['slug'] ?? '' ) ) ] = (float) ( $row['price'] ?? 0 );
        $history = $this->price_history();
        $changed = false;
        foreach ( $new_prices as $row ) {
            $slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
            if ( '' === $slug ) continue;
            $price = (float) ( $row['price'] ?? 0 );
            if ( $price <= 0 ) continue;
            if ( isset( $old[ $slug ] ) && abs( $old[ $slug ] - $price ) < 0.00001 ) continue;
            // Same rule as the per-product entry form: one price per day, the latest wins.
            $today = current_time( 'Y-m-d' );
            $entries = array_values( array_filter( (array) ( $history[ $slug ] ?? array() ), static fn( $entry ) => substr( (string) ( $entry['recorded_at'] ?? '' ), 0, 10 ) !== $today ) );
            $entries[] = array( 'price' => $price, 'recorded_at' => $today . ' 12:00:00' );
            usort( $entries, static fn( $a, $b ) => strcmp( (string) $a['recorded_at'], (string) $b['recorded_at'] ) );
            $history[ $slug ] = array_slice( $entries, -3650 );
            $changed = true;
        }
        if ( $changed ) update_option( self::PRICE_HISTORY_OPTION, $history, false );
    }

    private function product_history( string $slug, int $days = 30 ): array {
        $slug = sanitize_title( $slug );
        $cutoff = current_time( 'timestamp' ) - max( 1, $days ) * DAY_IN_SECONDS;
        return array_values( array_filter( (array) ( $this->price_history()[ $slug ] ?? array() ), static function( $row ) use ( $cutoff ) {
            return is_array( $row ) && isset( $row['price'], $row['recorded_at'] ) && strtotime( (string) $row['recorded_at'] ) >= $cutoff;
        } ) );
    }

    private function price_chart_svg( array $points, int $height = 220 ): string {
        if ( count( $points ) < 2 ) return '<div class="grfuel-chart-empty"><span class="dashicons dashicons-chart-line"></span><strong>Δεν υπάρχουν ακόμη αρκετά δεδομένα</strong><small>Χρειάζονται τουλάχιστον δύο καταχωρίσεις για να σχηματιστεί το γράφημα.</small></div>';
        usort( $points, static fn( $a, $b ) => strcmp( (string) ( $a['recorded_at'] ?? '' ), (string) ( $b['recorded_at'] ?? '' ) ) );
        $values = array_map( static fn( $p ) => (float) $p['price'], $points );
        $min_value = min( $values ); $max_value = max( $values );
        $visual_pad = max( 0.005, ( $max_value - $min_value ) * .16 );
        $min = max( 0, $min_value - $visual_pad ); $max = $max_value + $visual_pad; $range = max( 0.001, $max - $min );
        $w = 900; $h = max( 180, min( 420, $height ) ); $left = 64; $right = 22; $top = 24; $bottom = 42;
        $plot_w = $w - $left - $right; $plot_h = $h - $top - $bottom; $last = count( $values ) - 1; $coords = array();
        foreach ( $values as $i => $value ) {
            $x = $left + $plot_w * ( $i / $last );
            $y = $top + $plot_h * ( 1 - ( $value - $min ) / $range );
            $coords[] = array( 'x' => round($x,1), 'y' => round($y,1), 'value' => $value, 'date' => (string) ( $points[$i]['recorded_at'] ?? '' ) );
        }
        $trend = end($values) <=> reset($values); $class = $trend > 0 ? 'is-up' : ( $trend < 0 ? 'is-down' : 'is-flat' );
        $id = 'grfuel-chart-' . substr( md5( wp_json_encode( $points ) . $height ), 0, 10 );
        $line_points = implode( ' ', array_map( static fn( $p ) => $p['x'] . ',' . $p['y'], $coords ) );
        $area_points = $left . ',' . ( $top + $plot_h ) . ' ' . $line_points . ' ' . ( $left + $plot_w ) . ',' . ( $top + $plot_h );
        $grid = ''; $y_labels = '';
        for ( $i = 0; $i <= 4; $i++ ) {
            $y = $top + $plot_h * ( $i / 4 ); $label = $max - $range * ( $i / 4 );
            $grid .= '<line x1="'.$left.'" y1="'.round($y,1).'" x2="'.($left+$plot_w).'" y2="'.round($y,1).'" />';
            $y_labels .= '<text x="'.($left-10).'" y="'.round($y+4,1).'" text-anchor="end">'.esc_html(number_format_i18n($label,3)).' €</text>';
        }
        $point_nodes = '';
        foreach ( $coords as $point ) {
            $date_label = wp_date( 'd/m/Y', strtotime( $point['date'] ) );
            $tooltip_label = $date_label . ' — ' . number_format_i18n( $point['value'], 3 ) . ' €';
            $point_nodes .= '<g class="grfuel-chart-point"><circle cx="'.$point['x'].'" cy="'.$point['y'].'" r="5" tabindex="0" role="button" aria-label="'.esc_attr($tooltip_label).'" data-grfuel-point-label="'.esc_attr($tooltip_label).'"><title>'.esc_html($tooltip_label).'</title></circle></g>';
        }
        $indexes = array_values( array_unique( array( 0, (int) floor($last/2), $last ) ) ); $x_labels='';
        foreach ( $indexes as $index ) {
            $anchor = 0 === $index ? 'start' : ( $last === $index ? 'end' : 'middle' );
            $x_labels .= '<text x="'.$coords[$index]['x'].'" y="'.($h-12).'" text-anchor="'.$anchor.'">'.esc_html(wp_date('d/m/Y',strtotime($coords[$index]['date']))).'</text>';
        }
        $change = reset($values) > 0 ? ( ( end($values) - reset($values) ) / reset($values) ) * 100 : 0;
        // On narrow screens the chart keeps a readable plotting scale instead of being compressed.
        // Extra points receive additional horizontal space, with a sensible cap for long histories.
        $mobile_chart_width = max( 900, min( 1800, 760 + count( $points ) * 54 ) );
        return '<div class="grfuel-price-chart ' . esc_attr($class) . '"><div class="grfuel-chart-summary"><span><small>Χαμηλότερη</small><strong>'.esc_html(number_format_i18n($min_value,3)).'<em>€</em></strong></span><span><small>Υψηλότερη</small><strong>'.esc_html(number_format_i18n($max_value,3)).'<em>€</em></strong></span><span><small>Μεταβολή περιόδου</small><strong>'.esc_html(($change>0?'+':'').number_format_i18n($change,2)).'<em>%</em></strong></span></div><div class="grfuel-chart-scroll" tabindex="0" aria-label="Οριζόντια κύλιση γραφήματος" style="--grfuel-mobile-chart-width:'.absint($mobile_chart_width).'px"><svg viewBox="0 0 '.$w.' '.$h.'" role="img" aria-label="Γράφημα ιστορικού τιμής"><defs><linearGradient id="'.$id.'" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".30"/><stop offset="1" stop-color="currentColor" stop-opacity=".02"/></linearGradient><filter id="'.$id.'-shadow" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="currentColor" flood-opacity=".16"/></filter></defs><g class="grfuel-chart-grid">'.$grid.'</g><g class="grfuel-chart-axis-labels">'.$y_labels.$x_labels.'</g><polygon class="grfuel-chart-area" style="fill:url(#'.$id.')" points="'.esc_attr($area_points).'"/><polyline class="grfuel-chart-line" style="filter:url(#'.$id.'-shadow)" points="'.esc_attr($line_points).'"/>'.$point_nodes.'</svg></div><div class="grfuel-chart-tooltip" role="tooltip" aria-hidden="true"></div></div>';
    }

    public function daily_price_chart_shortcode( array $atts ): string {
        $settings = $this->settings();
        $atts = shortcode_atts(
            array(
                'product' => '',
                'days' => (int) $settings['chart_default_days'],
                'height' => 220,
                'show_current' => 'yes',
                'controls' => ! empty( $settings['chart_frontend_controls'] ) ? 'yes' : 'no',
                'max_days' => (int) $settings['chart_max_days'],
            ),
            $atts,
            'grfuel_price_chart'
        );
        $slug = sanitize_title( (string) $atts['product'] );
        if ( '' === $slug ) return '';
        $product = null;
        foreach ( (array) $settings['daily_prices'] as $row ) {
            if ( $slug === sanitize_title( (string) ( $row['slug'] ?? '' ) ) ) { $product = $row; break; }
        }
        if ( ! $product ) return '';
        $max_days = max( 7, min( 3650, absint( $atts['max_days'] ) ) );
        $days = max( 7, min( $max_days, absint( $atts['days'] ) ) );
        $height = max( 140, min( 420, absint( $atts['height'] ) ) );
        $points = $this->product_history( $slug, $days );
        wp_enqueue_style( 'sfv-front' );
        wp_enqueue_script( 'sfv-front' );
        $chart_theme = 'light' === ( $settings['chart_background'] ?? 'transparent' ) ? ' is-light' : ' is-transparent';
        $html = '<section class="grfuel-public-chart' . $chart_theme . '" data-grfuel-chart data-product="' . esc_attr( $slug ) . '" data-height="' . esc_attr( (string) $height ) . '" data-max-days="' . esc_attr( (string) $max_days ) . '">';
        $html .= '<div class="grfuel-public-chart-head"><div><small>Ιστορικό τιμής</small><strong>' . esc_html( $product['name'] ) . '</strong></div>';
        if ( 'yes' === strtolower( (string) $atts['show_current'] ) ) {
            $html .= '<span>' . esc_html( number_format_i18n( (float) $product['price'], 3 ) . ' € / ' . ( $product['unit'] ?: 'λίτρο' ) ) . '</span>';
        }
        $html .= '</div>';
        if ( 'yes' === strtolower( (string) $atts['controls'] ) ) {
            $html .= '<div class="grfuel-chart-controls"><label><span>Περίοδος σύγκρισης</span><div><input type="number" min="7" max="' . esc_attr( (string) $max_days ) . '" value="' . esc_attr( (string) $days ) . '" data-grfuel-chart-days><span>ημέρες</span></div></label><button type="button" data-grfuel-chart-update>Ενημέρωση γραφήματος</button></div>';
        }
        return $html . '<div class="grfuel-chart-stage" aria-live="polite">' . $this->price_chart_svg( $points, $height ) . '</div></section>';
    }

    private function price_chart_mobile_html( array $points ): string {
        if ( count( $points ) < 2 ) {
            return '<div class="grfuel-mobile-chart-empty"><strong>Δεν υπάρχουν ακόμη αρκετά δεδομένα</strong><small>Χρειάζονται τουλάχιστον δύο καταχωρίσεις.</small></div>';
        }

        usort( $points, static fn( $a, $b ) => strcmp( (string) ( $a['recorded_at'] ?? '' ), (string) ( $b['recorded_at'] ?? '' ) ) );
        $values = array_map( static fn( $point ) => (float) ( $point['price'] ?? 0 ), $points );
        $first = (float) reset( $values );
        $last  = (float) end( $values );
        $change = $first > 0 ? ( ( $last - $first ) / $first ) * 100 : 0;
        $trend_class = $last > $first ? 'is-up' : ( $last < $first ? 'is-down' : 'is-flat' );

        // Eight points per view keeps labels and touch targets readable on narrow screens.
        $page_size = 8;
        $pages = array_chunk( $points, $page_size );
        $page_count = count( $pages );
        $w = 360;
        $h = 230;
        $pad_l = 45;
        $pad_r = 14;
        $pad_t = 18;
        $pad_b = 42;
        $plot_w = $w - $pad_l - $pad_r;
        $plot_h = $h - $pad_t - $pad_b;

        $html = '<div class="grfuel-mobile-price-chart ' . esc_attr( $trend_class ) . '" data-grfuel-mobile-pages data-page-count="' . absint( $page_count ) . '">';
        $html .= '<div class="grfuel-mobile-chart-summary">';
        $html .= '<span><small>Τελευταία τιμή</small><strong>' . esc_html( number_format_i18n( $last, 3 ) ) . ' <em>€</em></strong></span>';
        $html .= '<span><small>Μεταβολή</small><strong>' . esc_html( ( $change > 0 ? '+' : '' ) . number_format_i18n( $change, 2 ) ) . ' <em>%</em></strong></span>';
        $html .= '</div>';
        $html .= '<div class="grfuel-mobile-chart-pages">';

        foreach ( $pages as $page_index => $page_points ) {
            $page_values = array_map( static fn( $point ) => (float) ( $point['price'] ?? 0 ), $page_points );
            $page_min = min( $page_values );
            $page_max = max( $page_values );
            $padding = max( 0.003, ( $page_max - $page_min ) * 0.18 );
            $axis_min = $page_min - $padding;
            $axis_max = $page_max + $padding;
            $range = max( 0.001, $axis_max - $axis_min );
            $count = count( $page_points );
            $coords = array();
            $point_nodes = '';
            $x_labels = '';
            $grid = '';
            $y_labels = '';
            $gradient_id = 'grfuel-mobile-gradient-' . wp_unique_id();

            for ( $g = 0; $g < 4; $g++ ) {
                $ratio = $g / 3;
                $y = $pad_t + $plot_h * $ratio;
                $value = $axis_max - $range * $ratio;
                $grid .= '<line x1="' . $pad_l . '" y1="' . esc_attr( (string) round( $y, 2 ) ) . '" x2="' . ( $w - $pad_r ) . '" y2="' . esc_attr( (string) round( $y, 2 ) ) . '"></line>';
                $y_labels .= '<text x="' . ( $pad_l - 7 ) . '" y="' . esc_attr( (string) round( $y + 3, 2 ) ) . '" text-anchor="end">' . esc_html( number_format_i18n( $value, 3 ) ) . '</text>';
            }

            foreach ( $page_points as $i => $point ) {
                $value = (float) $point['price'];
                $x = $pad_l + ( $count > 1 ? ( $plot_w * $i / ( $count - 1 ) ) : $plot_w / 2 );
                $y = $pad_t + ( ( $axis_max - $value ) / $range ) * $plot_h;
                $coords[] = round( $x, 2 ) . ',' . round( $y, 2 );
                $date_full = wp_date( 'd/m/Y', strtotime( (string) $point['recorded_at'] ) );
                $date_short = wp_date( 'd/m', strtotime( (string) $point['recorded_at'] ) );
                $label = $date_full . ' — ' . number_format_i18n( $value, 3 ) . ' €';
                $point_nodes .= '<g class="grfuel-chart-point"><circle cx="' . esc_attr( (string) round( $x, 2 ) ) . '" cy="' . esc_attr( (string) round( $y, 2 ) ) . '" r="6" tabindex="0" role="button" aria-label="' . esc_attr( $label ) . '" data-grfuel-point-label="' . esc_attr( $label ) . '"></circle></g>';
                $x_labels .= '<text x="' . esc_attr( (string) round( $x, 2 ) ) . '" y="' . ( $h - 15 ) . '" text-anchor="middle">' . esc_html( $date_short ) . '</text>';
            }

            $line_points = implode( ' ', $coords );
            $area_points = $pad_l . ',' . ( $pad_t + $plot_h ) . ' ' . $line_points . ' ' . ( $w - $pad_r ) . ',' . ( $pad_t + $plot_h );
            $from = wp_date( 'd/m/Y', strtotime( (string) $page_points[0]['recorded_at'] ) );
            $to = wp_date( 'd/m/Y', strtotime( (string) $page_points[ $count - 1 ]['recorded_at'] ) );

            $html .= '<div class="grfuel-mobile-chart-page grfuel-price-chart" data-grfuel-mobile-page="' . absint( $page_index ) . '"' . ( 0 === $page_index ? '' : ' hidden' ) . '>';
            $html .= '<div class="grfuel-mobile-chart-period">' . esc_html( $from . ' – ' . $to ) . '</div>';
            $html .= '<svg viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Γράφημα ιστορικού τιμής από ' . esc_attr( $from ) . ' έως ' . esc_attr( $to ) . '"><defs><linearGradient id="' . esc_attr( $gradient_id ) . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".28"/><stop offset="1" stop-color="currentColor" stop-opacity=".02"/></linearGradient></defs><g class="grfuel-chart-grid">' . $grid . '</g><g class="grfuel-chart-axis-labels">' . $y_labels . $x_labels . '</g><polygon class="grfuel-chart-area" style="fill:url(#' . esc_attr( $gradient_id ) . ')" points="' . esc_attr( $area_points ) . '"/><polyline class="grfuel-chart-line" points="' . esc_attr( $line_points ) . '"/>' . $point_nodes . '</svg><div class="grfuel-chart-tooltip" role="tooltip" aria-hidden="true"></div></div>';
        }
        $html .= '</div>';

        if ( $page_count > 1 ) {
            $html .= '<div class="grfuel-mobile-chart-nav"><button type="button" data-grfuel-mobile-prev disabled aria-label="Προηγούμενες ημέρες">‹</button><span><b data-grfuel-mobile-current>1</b> / ' . absint( $page_count ) . '</span><button type="button" data-grfuel-mobile-next aria-label="Επόμενες ημέρες">›</button></div>';
            $html .= '<small class="grfuel-mobile-chart-hint">Μετακινήσου ανά οκτώ ημέρες και πάτησε σε σημείο για ημερομηνία και τιμή.</small>';
        } else {
            $html .= '<small class="grfuel-mobile-chart-hint">Πάτησε σε σημείο για ημερομηνία και τιμή.</small>';
        }
        $html .= '</div>';
        return $html;
    }

    public function daily_price_chart_mobile_shortcode( array $atts ): string {
        $settings = $this->settings();
        $atts = shortcode_atts(
            array(
                'product' => '',
                'days' => (int) $settings['chart_default_days'],
                'show_current' => 'yes',
            ),
            $atts,
            'grfuel_price_chart_mobile'
        );
        $slug = sanitize_title( (string) $atts['product'] );
        if ( '' === $slug ) return '';
        $product = null;
        foreach ( (array) $settings['daily_prices'] as $row ) {
            if ( $slug === sanitize_title( (string) ( $row['slug'] ?? '' ) ) ) { $product = $row; break; }
        }
        if ( ! $product ) return '';
        $days = max( 7, min( 3650, absint( $atts['days'] ) ) );
        $points = $this->product_history( $slug, $days );
        wp_enqueue_style( 'sfv-front' );
        wp_enqueue_script( 'sfv-front' );
        $chart_theme = 'light' === ( $settings['chart_background'] ?? 'transparent' ) ? ' is-light' : ' is-transparent';
        $html = '<section class="grfuel-public-mobile-chart' . $chart_theme . '" data-grfuel-mobile-chart data-product="' . esc_attr( $slug ) . '">';
        $html .= '<div class="grfuel-public-mobile-chart-head"><div><small>Ιστορικό τιμής</small><strong>' . esc_html( $product['name'] ) . '</strong></div>';
        if ( 'yes' === strtolower( (string) $atts['show_current'] ) ) {
            $html .= '<span>' . esc_html( number_format_i18n( (float) $product['price'], 3 ) . ' € / ' . ( $product['unit'] ?: 'λίτρο' ) ) . '</span>';
        }
        $html .= '</div>' . $this->price_chart_mobile_html( $points ) . '</section>';
        return $html;
    }

    public function ajax_price_chart(): void {
        $slug = sanitize_title( wp_unslash( $_REQUEST['product'] ?? '' ) );
        $days = max( 7, min( 365, absint( $_REQUEST['days'] ?? 30 ) ) );
        $height = max( 140, min( 420, absint( $_REQUEST['height'] ?? 220 ) ) );
        if ( '' === $slug ) wp_send_json_error( array( 'message' => 'Missing product.' ), 400 );
        $known = false;
        foreach ( (array) $this->settings()['daily_prices'] as $product ) {
            if ( $slug === sanitize_title( (string) ( $product['slug'] ?? '' ) ) ) { $known = true; break; }
        }
        if ( ! $known ) wp_send_json_error( array( 'message' => 'Unknown product.' ), 404 );
        $cache_key = 'grfuel_chart_' . md5( $slug . '|' . $days . '|' . $height );
        $html = get_transient( $cache_key );
        if ( ! is_string( $html ) || '' === $html ) {
            $html = $this->price_chart_svg( $this->product_history( $slug, $days ), $height );
            set_transient( $cache_key, $html, 15 * MINUTE_IN_SECONDS );
        }
        wp_send_json_success( array( 'html' => $html, 'days' => $days ) );
    }

    public function add_price_history_entry(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) );
        check_admin_referer( 'grfuel_add_price_entry' );
        $slug = sanitize_title( wp_unslash( $_POST['product'] ?? '' ) );
        $dates = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['price_date'] ?? array() ) );
        $prices = (array) wp_unslash( $_POST['price_value'] ?? array() );
        if ( '' === $slug || ! $dates || count( $dates ) !== count( $prices ) ) {
            wp_safe_redirect( add_query_arg( 'grfuel_price_error', '1', admin_url( 'admin.php?page=grfuel-prices' ) ) ); exit;
        }
        $history = $this->price_history();
        $entries = (array) ( $history[ $slug ] ?? array() );
        $saved = array();
        foreach ( $dates as $index => $date ) {
            $price = max( 0, (float) str_replace( ',', '.', (string) ( $prices[ $index ] ?? '0' ) ) );
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || $price <= 0 ) continue;
            $entries = array_values( array_filter( $entries, static function( $row ) use ( $date ) { return substr( (string) ( $row['recorded_at'] ?? '' ), 0, 10 ) !== $date; } ) );
            $entries[] = array( 'price' => $price, 'recorded_at' => $date . ' 12:00:00' );
            $saved[] = array( 'date' => $date, 'price' => $price );
        }
        if ( ! $saved ) { wp_safe_redirect( add_query_arg( 'grfuel_price_error', '1', admin_url( 'admin.php?page=grfuel-prices' ) ) ); exit; }
        usort( $entries, static fn( $a, $b ) => strcmp( (string) $a['recorded_at'], (string) $b['recorded_at'] ) );
        $history[ $slug ] = array_slice( $entries, -3650 );
        update_option( self::PRICE_HISTORY_OPTION, $history, false );
        $latest = end( $history[ $slug ] );
        $settings = $this->settings();
        foreach ( $settings['daily_prices'] as &$product ) {
            if ( $slug === sanitize_title( (string) ( $product['slug'] ?? '' ) ) ) { $product['price'] = number_format( (float) ( $latest['price'] ?? 0 ), 3, '.', '' ); break; }
        }
        unset( $product );
        update_option( self::OPTION, $settings, false );
        wp_safe_redirect( add_query_arg( 'grfuel_price_added', count( $saved ), admin_url( 'admin.php?page=grfuel-prices' ) ) );
        exit;
    }

    public function save_daily_prices(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) );
        check_admin_referer( 'grfuel_save_daily_prices' );
        $input = isset( $_POST[ self::OPTION ] ) && is_array( $_POST[ self::OPTION ] ) ? wp_unslash( $_POST[ self::OPTION ] ) : array();
        $settings = $this->settings();
        $daily_prices = array();
        foreach ( array_slice( (array) ( $input['daily_prices'] ?? array() ), 0, 20 ) as $row ) {
            if ( ! is_array( $row ) ) continue;
            $name = mb_substr( sanitize_text_field( $row['name'] ?? '' ), 0, 100 );
            $slug = sanitize_title( $row['slug'] ?? $name );
            $price = max( 0, (float) str_replace( ',', '.', (string) ( $row['price'] ?? 0 ) ) );
            $unit = mb_substr( sanitize_text_field( $row['unit'] ?? 'λίτρο' ), 0, 40 );
            if ( '' !== $name && '' !== $slug ) $daily_prices[] = array( 'name' => $name, 'slug' => $slug, 'price' => number_format( $price, 3, '.', '' ), 'unit' => $unit ?: 'λίτρο' );
        }
        $this->record_daily_price_history( (array) ( $settings['daily_prices'] ?? array() ), $daily_prices );
        $settings['daily_prices'] = $daily_prices;
        $settings['chart_default_days'] = max( 7, min( 3650, absint( $input['chart_default_days'] ?? 30 ) ) );
        $settings['chart_max_days'] = max( $settings['chart_default_days'], min( 3650, absint( $input['chart_max_days'] ?? 365 ) ) );
        $settings['chart_frontend_controls'] = empty( $input['chart_frontend_controls'] ) ? 0 : 1;
        $settings['chart_background'] = 'light' === sanitize_key( $input['chart_background'] ?? 'transparent' ) ? 'light' : 'transparent';
        update_option( self::OPTION, $settings, false );
        wp_safe_redirect( add_query_arg( 'saved', '1', admin_url( 'admin.php?page=grfuel-prices' ) ) );
        exit;
    }

    public function delete_price_history_entry(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) );
        $slug = sanitize_title( wp_unslash( $_POST['product'] ?? '' ) );
        $recorded_at = sanitize_text_field( wp_unslash( $_POST['recorded_at'] ?? '' ) );
        check_admin_referer( 'grfuel_delete_price_entry_' . $slug . '_' . md5( $recorded_at ) );
        if ( '' !== $slug && '' !== $recorded_at ) {
            $history = $this->price_history();
            $history[ $slug ] = array_values( array_filter( (array) ( $history[ $slug ] ?? array() ), static fn( $row ) => (string) ( $row['recorded_at'] ?? '' ) !== $recorded_at ) );
            update_option( self::PRICE_HISTORY_OPTION, $history, false );
            if ( ! empty( $history[ $slug ] ) ) {
                usort( $history[ $slug ], static fn( $a, $b ) => strcmp( (string) $a['recorded_at'], (string) $b['recorded_at'] ) );
                $latest = end( $history[ $slug ] ); $settings = $this->settings();
                foreach ( $settings['daily_prices'] as &$product ) if ( $slug === sanitize_title( (string) ( $product['slug'] ?? '' ) ) ) { $product['price'] = number_format( (float) $latest['price'], 3, '.', '' ); break; }
                unset( $product ); update_option( self::OPTION, $settings, false );
            }
        }
        wp_safe_redirect( add_query_arg( 'grfuel_price_deleted', '1', admin_url( 'admin.php?page=grfuel-prices' ) ) ); exit;
    }

    public function ajax_check_orders(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) wp_send_json_error( null, 403 );
        check_ajax_referer( 'grfuel_check_orders', 'nonce' );
        global $wpdb;
        $user_id = get_current_user_id();
        $latest = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$this->table()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $last_notified = (int) get_user_meta( $user_id, 'grfuel_last_notified_order_id', true );
        if ( 0 === $last_notified && $latest > 0 ) {
            $last_notified = max( 0, $latest - 1 );
        }
        $count = 0;
        $order = null;
        if ( $latest > $last_notified ) {
            $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE id > %d", $last_notified ) );
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, form_id, payload, submitted_at FROM {$this->table()} WHERE id = %d", $latest ) );
            if ( $row ) {
                $payload = json_decode( (string) $row->payload, true );
                $summary = $this->submission_summary( is_array( $payload ) ? $payload : array(), (int) $row->form_id );
                $order = array(
                    'id' => (int) $row->id, 'customer' => (string) $summary['customer'],
                    'product' => (string) $summary['product'], 'quantity' => (string) $summary['quantity'],
                    'submittedAt' => get_date_from_gmt( (string) $row->submitted_at, 'd/m/Y H:i' ),
                );
            }
            update_user_meta( $user_id, 'grfuel_last_notified_order_id', $latest );
        }
        wp_send_json_success( array(
            'latestId' => $latest, 'newCount' => $count, 'unreadCount' => $this->unread_orders_count(), 'order' => $order,
        ) );
    }

    private function dependency_matches( $dependency, array $posted ): bool {
        $d = $this->sanitize_dependency( $dependency );
        if ( '' === $d['field'] ) return true;
        $actual = $posted[ $d['field'] ] ?? '';
        if ( is_array( $actual ) ) return false;
        $actual = (string) $actual;
        return match ( $d['operator'] ) {
            'not_equals' => $actual !== $d['value'], 'contains' => false !== mb_stripos( $actual, $d['value'] ),
            'checked' => '1' === $actual, 'not_checked' => '1' !== $actual, 'empty' => '' === $actual,
            'not_empty' => '' !== $actual, default => $actual === $d['value'],
        };
    }

    private function settings(): array {
        return wp_parse_args(
            (array) get_option( self::OPTION, array() ),
            array(
                'site_key' => '', 'secret_key' => '', 'rate_limit' => 5, 'retention_days' => 0, 'manager_session_minutes' => 480,
                'label_color' => '#202a40', 'label_font' => 'inherit', 'label_size' => 14, 'label_weight' => '600',
                'placeholder_color' => '#8a93a6', 'placeholder_font' => 'inherit', 'placeholder_size' => 14, 'placeholder_weight' => '400',
                'field_text_color' => '#202a40', 'field_background' => '#ffffff', 'field_border_color' => '#dfe4ed',
                'field_hover_text_color' => '#202a40', 'field_hover_background' => '#ffffff', 'field_hover_border_color' => '#aeb7c8',
                'field_focus_text_color' => '#202a40', 'field_focus_background' => '#ffffff', 'field_focus_color' => '#635bff', 'field_focus_outline_color' => '#c7c3ff',
                'field_active_text_color' => '#202a40', 'field_active_background' => '#f8f8ff', 'field_active_border_color' => '#5148ed', 'field_font' => 'inherit', 'field_size' => 14, 'field_weight' => '400',
                'field_height' => 44, 'field_radius' => 8,
                'submit_text_color' => '#ffffff', 'submit_background' => '#635bff', 'submit_hover_background' => '#5148ed',
                'submit_font' => 'inherit', 'submit_size' => 14, 'submit_weight' => '700', 'submit_height' => 44, 'submit_radius' => 8,
                'pdf_brand_name' => get_bloginfo( 'name' ), 'pdf_logo_url' => '',
                'pdf_watermark_url' => '', 'pdf_watermark_opacity' => 12,
                'pdf_primary' => '#111827', 'pdf_accent' => '#2563eb', 'pdf_text' => '#162033', 'pdf_muted' => '#69758a', 'pdf_surface' => '#ffffff',
                'daily_prices' => array( array( 'name' => 'Πετρέλαιο θέρμανσης', 'slug' => 'petrelaio-thermansis', 'price' => '1.090', 'unit' => 'λίτρο' ) ),
                'notification_enabled' => 1, 'notification_sound' => 1, 'notification_desktop' => 1, 'notification_email' => 1,
                'notification_email_address' => get_option( 'admin_email' ), 'notification_interval' => 30, 'notification_sound_choice' => 'bell',
                'push_enabled' => 0, 'onesignal_app_id' => '', 'onesignal_api_key' => '',
                'preview_theme' => 'light',
                'chart_default_days' => 30, 'chart_max_days' => 365, 'chart_frontend_controls' => 1, 'chart_background' => 'transparent',
            )
        );
    }

    private function appearance_css( array $s ): string {
        $fonts = array(
            'inherit' => 'inherit',
            'system' => '-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif',
            'arial' => 'Arial,Helvetica,sans-serif',
            'georgia' => 'Georgia,Times New Roman,serif',
            'monospace' => 'ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace',
        );
        $font = static fn( $key ) => $fonts[ $s[ $key ] ?? 'inherit' ] ?? 'inherit';
        return sprintf(
            '.sfv-form{--sfv-label-color:%1$s;--sfv-placeholder-color:%2$s;--sfv-field-text:%3$s;--sfv-field-bg:%4$s;--sfv-field-border:%5$s;--sfv-field-hover-text:%6$s;--sfv-field-hover-bg:%7$s;--sfv-field-hover-border:%8$s;--sfv-field-focus-text:%9$s;--sfv-field-focus-bg:%10$s;--sfv-field-focus:%11$s;--sfv-field-focus-outline:%12$s;--sfv-field-active-text:%13$s;--sfv-field-active-bg:%14$s;--sfv-field-active-border:%15$s;--sfv-submit-text:%16$s;--sfv-submit-bg:%17$s;--sfv-submit-hover:%18$s;}' .
            '.sfv-form .uk-form-label,.sfv-form .sfv-radio-group legend{color:var(--sfv-label-color);font-family:%19$s;font-size:%20$dpx;font-weight:%21$s;}' .
            '.sfv-form .uk-input,.sfv-form .uk-select,.sfv-form .uk-textarea{color:var(--sfv-field-text)!important;background-color:var(--sfv-field-bg)!important;border:1px solid var(--sfv-field-border)!important;box-shadow:none!important;font-family:%22$s;font-size:%23$dpx;font-weight:%24$s;border-radius:%25$dpx;transition:border-color .18s ease,background-color .18s ease,color .18s ease,box-shadow .18s ease;}' .
            '.sfv-form .uk-input,.sfv-form .uk-select{min-height:%26$dpx;height:%26$dpx;}' .
            '.sfv-form .uk-input:hover,.sfv-form .uk-select:hover,.sfv-form .uk-textarea:hover{color:var(--sfv-field-hover-text)!important;background-color:var(--sfv-field-hover-bg)!important;border-color:var(--sfv-field-hover-border)!important;}' .
            '.sfv-form .uk-input:focus,.sfv-form .uk-select:focus,.sfv-form .uk-textarea:focus{color:var(--sfv-field-focus-text)!important;background-color:var(--sfv-field-focus-bg)!important;border-color:var(--sfv-field-focus)!important;box-shadow:0 0 0 2px var(--sfv-field-focus-outline)!important;outline:0!important;}' .
            '.sfv-form .uk-input:active,.sfv-form .uk-select:active,.sfv-form .uk-textarea:active{color:var(--sfv-field-active-text)!important;background-color:var(--sfv-field-active-bg)!important;border-color:var(--sfv-field-active-border)!important;}' .
            '.sfv-form .uk-input::placeholder,.sfv-form .uk-textarea::placeholder{color:var(--sfv-placeholder-color);opacity:1;font-family:%27$s;font-size:%28$dpx;font-weight:%29$s;}' .
            '.sfv-form .uk-select:invalid{color:var(--sfv-placeholder-color);font-family:%27$s;font-size:%28$dpx;font-weight:%29$s;}' .
            '.sfv-form .uk-button-primary{min-height:%30$dpx;color:var(--sfv-submit-text);background:var(--sfv-submit-bg);font-family:%31$s;font-size:%32$dpx;font-weight:%33$s;border-radius:%34$dpx;}' .
            '.sfv-form .uk-button-primary:hover,.sfv-form .uk-button-primary:focus{background:var(--sfv-submit-hover);color:var(--sfv-submit-text);}',
            esc_attr( $s['label_color'] ), esc_attr( $s['placeholder_color'] ), esc_attr( $s['field_text_color'] ), esc_attr( $s['field_background'] ), esc_attr( $s['field_border_color'] ),
            esc_attr( $s['field_hover_text_color'] ), esc_attr( $s['field_hover_background'] ), esc_attr( $s['field_hover_border_color'] ),
            esc_attr( $s['field_focus_text_color'] ), esc_attr( $s['field_focus_background'] ), esc_attr( $s['field_focus_color'] ), esc_attr( $s['field_focus_outline_color'] ),
            esc_attr( $s['field_active_text_color'] ), esc_attr( $s['field_active_background'] ), esc_attr( $s['field_active_border_color'] ),
            esc_attr( $s['submit_text_color'] ), esc_attr( $s['submit_background'] ), esc_attr( $s['submit_hover_background'] ),
            $font( 'label_font' ), absint( $s['label_size'] ), esc_attr( $s['label_weight'] ),
            $font( 'field_font' ), absint( $s['field_size'] ), esc_attr( $s['field_weight'] ), absint( $s['field_radius'] ), absint( $s['field_height'] ),
            $font( 'placeholder_font' ), absint( $s['placeholder_size'] ), esc_attr( $s['placeholder_weight'] ), absint( $s['submit_height'] ),
            $font( 'submit_font' ), absint( $s['submit_size'] ), esc_attr( $s['submit_weight'] ), absint( $s['submit_radius'] )
        );
    }

    private function verify_turnstile( int $form_id ): bool {
        $settings = $this->settings();
        if ( empty( $settings['site_key'] ) || empty( $settings['secret_key'] ) ) {
            return false;
        }
        $token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
        if ( '' === $token ) {
            return false;
        }
        $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'timeout' => 10,
            'body' => array(
                'secret' => $settings['secret_key'],
                'response' => $token,
                'remoteip' => $this->client_ip(),
                'idempotency_key' => wp_generate_uuid4(),
            ),
        ) );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $expected_host = strtolower( rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ), '.' ) );
        $actual_host = is_array( $body ) ? strtolower( rtrim( sanitize_text_field( (string) ( $body['hostname'] ?? '' ) ), '.' ) ) : '';
        return is_array( $body )
            && ! empty( $body['success'] )
            && hash_equals( 'sfv_form_' . $form_id, (string) ( $body['action'] ?? '' ) )
            && '' !== $expected_host
            && hash_equals( $expected_host, $actual_host );
    }

    private function client_ip(): string {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
    }

    private function ip_hash(): string {
        return hash_hmac( 'sha256', $this->client_ip(), wp_salt( 'auth' ) );
    }

    private function is_rate_limited( int $form_id ): bool {
        $limit = max( 1, min( 100, absint( $this->settings()['rate_limit'] ) ) );
        $key = 'sfv_rl_' . md5( $form_id . '|' . $this->ip_hash() );
        $count = (int) get_transient( $key );
        if ( $count >= $limit ) {
            return true;
        }
        set_transient( $key, $count + 1, HOUR_IN_SECONDS );
        return false;
    }

    private function payload_contains_email( array $payload, string $email ): bool {
        foreach ( $payload as $item ) {
            if ( ! is_array( $item ) ) continue;
            $candidate = sanitize_email( (string) ( $item['value'] ?? '' ) );
            if ( '' !== $candidate && hash_equals( strtolower( $email ), strtolower( $candidate ) ) ) return true;
        }
        return false;
    }

    public function run_retention(): void {
        $days = absint( $this->settings()['retention_days'] ?? 0 );
        if ( $days < 1 ) return;
        global $wpdb;
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
        do {
            $ids = array_map( 'absint', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$this->table()} WHERE submitted_at < %s ORDER BY id ASC LIMIT 500", $cutoff ) ) );
            if ( ! $ids ) break;
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table()} WHERE id IN ({$placeholders})", $ids ) );
        } while ( 500 === count( $ids ) );
    }

    public function register_privacy_exporter( array $exporters ): array {
        $exporters['grfuel-manager'] = array(
            'exporter_friendly_name' => __( 'GRFuel Manager submissions', 'secure-form-vault' ),
            'callback' => array( $this, 'privacy_exporter' ),
        );
        return $exporters;
    }

    public function privacy_exporter( string $email_address, int $page = 1 ): array {
        $email = sanitize_email( $email_address );
        if ( ! is_email( $email ) ) return array( 'data' => array(), 'done' => true );
        global $wpdb;
        $limit = 100; $offset = max( 0, $page - 1 ) * $limit;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,form_id,submitted_at,payload FROM {$this->table()} WHERE payload LIKE %s ORDER BY id ASC LIMIT %d OFFSET %d", '%' . $wpdb->esc_like( $email ) . '%', $limit, $offset ) );
        $data = array();
        foreach ( $rows as $row ) {
            $payload = json_decode( (string) $row->payload, true );
            if ( ! is_array( $payload ) || ! $this->payload_contains_email( $payload, $email ) ) continue;
            $items = array(
                array( 'name' => __( 'Submission ID', 'secure-form-vault' ), 'value' => (string) $row->id ),
                array( 'name' => __( 'Form', 'secure-form-vault' ), 'value' => get_the_title( (int) $row->form_id ) ),
                array( 'name' => __( 'Submitted at (UTC)', 'secure-form-vault' ), 'value' => (string) $row->submitted_at ),
            );
            foreach ( $payload as $item ) if ( is_array( $item ) ) $items[] = array( 'name' => sanitize_text_field( (string) ( $item['label'] ?? '' ) ), 'value' => (string) ( $item['value'] ?? '' ) );
            $data[] = array( 'group_id' => 'grfuel-manager', 'group_label' => __( 'GRFuel Manager submissions', 'secure-form-vault' ), 'item_id' => 'submission-' . absint( $row->id ), 'data' => $items );
        }
        return array( 'data' => $data, 'done' => count( $rows ) < $limit );
    }

    public function register_privacy_eraser( array $erasers ): array {
        $erasers['grfuel-manager'] = array(
            'eraser_friendly_name' => __( 'GRFuel Manager submissions', 'secure-form-vault' ),
            'callback' => array( $this, 'privacy_eraser' ),
        );
        return $erasers;
    }

    public function privacy_eraser( string $email_address, int $page = 1 ): array {
        $email = sanitize_email( $email_address );
        if ( ! is_email( $email ) ) return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,payload FROM {$this->table()} WHERE payload LIKE %s ORDER BY id ASC LIMIT 100", '%' . $wpdb->esc_like( $email ) . '%' ) );
        $removed = 0;
        foreach ( $rows as $row ) {
            $payload = json_decode( (string) $row->payload, true );
            if ( is_array( $payload ) && $this->payload_contains_email( $payload, $email ) ) $removed += (int) $wpdb->delete( $this->table(), array( 'id' => absint( $row->id ) ), array( '%d' ) );
        }
        return array( 'items_removed' => $removed > 0, 'items_retained' => false, 'messages' => array(), 'done' => count( $rows ) < 100 );
    }

    public function admin_menu(): void {
        add_menu_page( 'GRFuel Manager', 'GRFuel Manager', self::CAPABILITY, 'grfuel-manager', array( $this, 'dashboard_page' ), $this->admin_icon(), 3 );
        add_submenu_page( 'grfuel-manager', 'Πίνακας Ελέγχου', 'Πίνακας Ελέγχου', self::CAPABILITY, 'grfuel-manager', array( $this, 'dashboard_page' ) );
        add_submenu_page( 'grfuel-manager', __( 'Submissions', 'secure-form-vault' ), __( 'Παραγγελίες', 'secure-form-vault' ), self::CAPABILITY, 'sfv-submissions', array( $this, 'submissions_page' ) );
        add_submenu_page( 'grfuel-manager', 'Νέα Παραγγελία', 'Νέα Παραγγελία', self::CAPABILITY, 'grfuel-new-order', array( $this, 'new_order_page' ) );
        add_submenu_page( 'grfuel-manager', 'Φόρμες', 'Φόρμες', self::CAPABILITY, 'grfuel-forms', array( $this, 'forms_page' ) );
        add_submenu_page( 'grfuel-manager', 'Τιμές Ημέρας', 'Τιμές Ημέρας', self::CAPABILITY, 'grfuel-prices', array( $this, 'daily_prices_page' ) );
        add_submenu_page( 'grfuel-manager', 'Χρήστες Manager', 'Χρήστες Manager', self::USER_ADMIN_CAPABILITY, 'grfuel-users', array( $this, 'manager_users_page' ) );
        add_submenu_page( 'grfuel-manager', __( 'Settings', 'secure-form-vault' ), __( 'Ρυθμίσεις', 'secure-form-vault' ), self::CAPABILITY, 'sfv-settings', array( $this, 'settings_page' ) );
        add_submenu_page( 'grfuel-manager', 'Backup / Restore', 'Backup / Restore', self::CAPABILITY, 'grfuel-backup', array( $this, 'backup_page' ) );
        add_submenu_page( 'grfuel-manager', __( 'Documentation', 'secure-form-vault' ), __( 'Τεκμηρίωση', 'secure-form-vault' ), self::CAPABILITY, 'sfv-documentation', array( $this, 'documentation_page' ) );
        add_submenu_page( 'grfuel-manager', 'Changelog', 'Changelog', self::CAPABILITY, 'grfuel-changelog', array( $this, 'changelog_page' ) );
    }

    public function admin_body_class( string $classes ): string {
        $screen = get_current_screen();
        if ( $screen && ( self::CPT === $screen->post_type || str_contains( (string) $screen->id, 'grfuel-manager' ) || str_contains( (string) $screen->id, 'sfv-' ) || str_contains( (string) $screen->id, 'grfuel-prices' ) || str_contains( (string) $screen->id, 'grfuel-forms' ) ) ) {
            $classes .= ' grfuel-app-mode';
        }
        return $classes;
    }

    public function dashboard_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) ); }
        global $wpdb;
        $table = $this->table();
        $today_start = current_time( 'Y-m-d 00:00:00' );
        $month_start = current_time( 'Y-m-01 00:00:00' );
        $today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE submitted_at >= %s", get_gmt_from_date( $today_start ) ) );
        $month = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE submitted_at >= %s", get_gmt_from_date( $month_start ) ) );
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $recent = $wpdb->get_results( "SELECT id, form_id, submitted_at, payload FROM {$table} ORDER BY id DESC LIMIT 6" );
        ?>
        <div class="wrap sfv-admin-page grfuel-dashboard-page">
            <?php $this->admin_nav( 'dashboard' ); ?>
            <header class="grfuel-app-topbar"><div><span>Πίνακας λειτουργίας</span><h1>Καλημέρα, <?php echo esc_html( wp_get_current_user()->display_name ); ?></h1></div><div class="grfuel-topbar-actions"><a class="sfv-btn sfv-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=grfuel-prices' ) ); ?>">Τιμές ημέρας</a><a class="sfv-btn sfv-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=grfuel-forms#grfuel-new-form-dialog' ) ); ?>">Νέα φόρμα</a></div></header>
            <main class="grfuel-dashboard-main">
                <section class="grfuel-stat-grid">
                    <article><span>Παραγγελίες σήμερα</span><strong><?php echo number_format_i18n( $today ); ?></strong><small>Νέες εγγραφές από 00:00</small></article>
                    <article><span>Παραγγελίες μήνα</span><strong><?php echo number_format_i18n( $month ); ?></strong><small>Τρέχων μήνας</small></article>
                    <article><span>Σύνολο εγγραφών</span><strong><?php echo number_format_i18n( $total ); ?></strong><small>Αποθηκευμένες υποβολές</small></article>
                    <article class="grfuel-stat-live"><span>Ζωντανή παρακολούθηση</span><strong><i></i> Ενεργό</strong><small>Έλεγχος νέων παραγγελιών</small></article>
                </section>
                <section class="grfuel-dashboard-grid">
                    <article class="grfuel-dashboard-card grfuel-recent-card"><div class="grfuel-card-head"><div><span>Ροή εργασίας</span><h2>Πρόσφατες παραγγελίες</h2></div><a href="<?php echo esc_url( admin_url( 'admin.php?page=sfv-submissions' ) ); ?>">Προβολή όλων →</a></div>
                    <div class="grfuel-recent-list">
                    <?php if ( ! $recent ) : ?><div class="grfuel-empty-state">Δεν υπάρχουν ακόμη παραγγελίες.</div><?php endif; ?>
                    <?php foreach ( $recent as $row ) : $payload=json_decode($row->payload,true); $payload=is_array($payload)?$payload:array(); $summary=$this->submission_summary($payload,(int)$row->form_id); ?>
                        <div class="grfuel-recent-order"><div class="grfuel-order-icon">#<?php echo absint($row->id); ?></div><div><strong><?php echo esc_html($summary['customer']); ?></strong><small><?php echo esc_html($summary['product']); ?></small></div><time><?php echo esc_html( human_time_diff( strtotime($row->submitted_at . ' UTC'), current_time('timestamp', true) ) ); ?> πριν</time></div>
                    <?php endforeach; ?>
                    </div></article>
                    <aside class="grfuel-dashboard-card grfuel-quick-card"><div class="grfuel-card-head"><div><span>Συντομεύσεις</span><h2>Γρήγορες ενέργειες</h2></div></div><a href="<?php echo esc_url(admin_url('admin.php?page=grfuel-forms')); ?>"><span class="dashicons dashicons-feedback"></span><div><strong>Φόρμες</strong><small>Δημιουργία και σύντομοι κώδικες</small></div></a><a href="<?php echo esc_url(admin_url('admin.php?page=sfv-settings')); ?>"><span class="dashicons dashicons-admin-generic"></span><div><strong>Ρυθμίσεις</strong><small>PDF, εμφάνιση και ειδοποιήσεις</small></div></a><a href="<?php echo esc_url(admin_url('admin.php?page=sfv-documentation')); ?>"><span class="dashicons dashicons-book-alt"></span><div><strong>Τεκμηρίωση</strong><small>Οδηγίες λειτουργίας</small></div></a></aside>
                </section>
            </main>
        </div>
        <?php
    }

    public function forms_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) );
        }
        $forms = get_posts( array(
            'post_type'      => self::CPT,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => -1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ) );
        ?>
        <div class="wrap sfv-admin-page grfuel-forms-page">
            <?php $this->admin_nav( 'forms' ); ?>
            <?php $this->section_intro(
                'Διαχείριση φορμών',
                'Οργανώνουμε όλες τις φόρμες, ελέγχουμε την κατάστασή τους και ανοίγουμε γρήγορα τον builder ή τις υποβολές.',
                array( array( 'label' => 'Import φόρμας', 'url' => '#grfuel-import-form-dialog', 'class' => 'sfv-btn-secondary grfuel-open-import-dialog', 'icon' => 'dashicons-upload' ), array( 'label' => 'Νέα φόρμα', 'url' => '#grfuel-new-form-dialog', 'class' => 'sfv-btn-primary grfuel-open-form-dialog', 'icon' => 'dashicons-plus-alt2' ) )
            ); ?>
            <?php if ( isset( $_GET['grfuel_form_saved'] ) ) : ?><div class="grfuel-inline-success">Η φόρμα αποθηκεύτηκε με επιτυχία.</div><?php endif; ?>
            <?php if ( isset( $_GET['grfuel_form_deleted'] ) ) : ?><div class="grfuel-inline-success">Η φόρμα διαγράφηκε οριστικά.</div><?php endif; ?>
            <?php if ( isset( $_GET['grfuel_form_error'] ) ) : ?><div class="grfuel-inline-error">Συμπληρώνουμε έγκυρο όνομα φόρμας και δοκιμάζουμε ξανά.</div><?php endif; ?>
            <?php if ( isset( $_GET['grfuel_form_imported'] ) ) : ?><div class="grfuel-inline-success">Η φόρμα εισήχθη με επιτυχία.</div><?php endif; ?>
            <?php if ( isset( $_GET['grfuel_form_import_error'] ) ) : ?><div class="grfuel-inline-error">Το αρχείο δεν είναι έγκυρο export φόρμας του GRFuel Manager.</div><?php endif; ?>
            <dialog id="grfuel-import-form-dialog" class="grfuel-form-dialog">
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="grfuel_import_form">
                    <?php wp_nonce_field( 'grfuel_import_form' ); ?>
                    <div class="grfuel-dialog-head"><div><span class="sfv-eyebrow">Import</span><h2>Εισαγωγή φόρμας</h2><p>Επιλέγουμε αρχείο JSON που δημιουργήθηκε από το Export μιας φόρμας.</p></div><button type="button" class="grfuel-dialog-close" aria-label="Κλείσιμο">×</button></div>
                    <label><span>Αρχείο φόρμας</span><input type="file" name="grfuel_form_file" accept="application/json,.json" required></label>
                    <div class="grfuel-dialog-actions"><button type="button" class="sfv-btn sfv-btn-secondary grfuel-dialog-cancel">Ακύρωση</button><button type="submit" class="sfv-btn sfv-btn-primary"><span class="dashicons dashicons-upload"></span>Import φόρμας</button></div>
                </form>
            </dialog>
            <dialog id="grfuel-new-form-dialog" class="grfuel-form-dialog">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="grfuel_create_form">
                    <?php wp_nonce_field( 'grfuel_create_form' ); ?>
                    <div class="grfuel-dialog-head"><div><span class="sfv-eyebrow">Νέα φόρμα</span><h2>Δημιουργία φόρμας</h2><p>Δίνουμε ένα όνομα και συνεχίζουμε απευθείας στον builder.</p></div><button type="button" class="grfuel-dialog-close" aria-label="Κλείσιμο">×</button></div>
                    <label><span>Όνομα φόρμας</span><input type="text" name="form_name" placeholder="π.χ. Παραγγελία πετρελαίου" required autofocus></label>
                    <div class="grfuel-dialog-actions"><button type="button" class="sfv-btn sfv-btn-secondary grfuel-dialog-cancel">Ακύρωση</button><button type="submit" class="sfv-btn sfv-btn-primary">Αποθήκευση</button></div>
                </form>
            </dialog>
            <main class="grfuel-forms-main">
                <?php if ( ! $forms ) : ?>
                    <section class="grfuel-forms-empty">
                        <span class="dashicons dashicons-feedback"></span>
                        <h2>Δεν υπάρχουν ακόμη φόρμες</h2>
                        <p>Δημιουργούμε την πρώτη φόρμα παραγγελίας και τη χρησιμοποιούμε στο YOOtheme μέσω shortcode.</p>
                        <button type="button" class="sfv-btn sfv-btn-primary grfuel-open-form-dialog">Δημιουργία φόρμας</button>
                    </section>
                <?php else : ?>
                    <section class="grfuel-forms-list" aria-label="Λίστα φορμών">
                        <div class="grfuel-forms-list-head"><span>Φόρμα</span><span>Shortcode</span><span>Κατάσταση</span><span>Ενημέρωση</span><span></span></div>
                        <?php foreach ( $forms as $form ) :
                            $status = 'publish' === $form->post_status ? 'Ενεργή' : ( 'draft' === $form->post_status ? 'Πρόχειρη' : 'Ιδιωτική' );
                            $status_class = 'publish' === $form->post_status ? 'is-active' : 'is-draft';
                            $edit_url = get_edit_post_link( $form->ID, 'raw' );
                            $submissions_url = add_query_arg( 'form_id', $form->ID, admin_url( 'admin.php?page=sfv-submissions' ) );
                            ?>
                            <article class="grfuel-form-row">
                                <div class="grfuel-form-title"><span class="dashicons dashicons-feedback"></span><div><strong><?php echo esc_html( get_the_title( $form ) ?: 'Χωρίς τίτλο' ); ?></strong><small>ID <?php echo absint( $form->ID ); ?></small></div></div>
                                <code>[grfuel_form id=&quot;<?php echo absint( $form->ID ); ?>&quot;]</code>
                                <span class="grfuel-form-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status ); ?></span>
                                <time datetime="<?php echo esc_attr( get_post_modified_time( DATE_W3C, false, $form ) ); ?>"><?php echo esc_html( get_the_modified_date( 'd/m/Y H:i', $form ) ); ?></time>
                                <div class="grfuel-form-actions"><a class="sfv-btn sfv-btn-secondary" href="<?php echo esc_url( $submissions_url ); ?>">Παραγγελίες</a><a class="sfv-btn sfv-btn-primary" href="<?php echo esc_url( $edit_url ); ?>">Builder</a><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="grfuel-export-form"><input type="hidden" name="action" value="grfuel_export_form"><input type="hidden" name="form_id" value="<?php echo absint( $form->ID ); ?>"><?php wp_nonce_field( 'grfuel_export_form_' . $form->ID ); ?><button type="submit" class="sfv-btn sfv-btn-secondary"><span class="dashicons dashicons-download"></span>Export</button></form><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="grfuel-delete-form" onsubmit="return confirm('Η φόρμα και η διαμόρφωσή της θα διαγραφούν οριστικά. Συνέχεια;');"><input type="hidden" name="action" value="grfuel_delete_form"><input type="hidden" name="form_id" value="<?php echo absint( $form->ID ); ?>"><?php wp_nonce_field( 'grfuel_delete_form_' . $form->ID ); ?><button type="submit" class="sfv-btn sfv-btn-danger">Διαγραφή</button></form></div>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>
            </main>
        </div>
        <?php
    }

    public function daily_prices_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) );
        $s = $this->settings();
        ?>
        <div class="wrap sfv-admin-page grfuel-prices-page">
            <?php $this->admin_nav( 'prices' ); ?>
            <?php $this->section_intro( 'Τιμές Ημέρας', 'Καταχωρούμε ημερομηνία και τιμή ανά προϊόν και δημιουργούμε συγκρίσεις και γραφήματα από το πραγματικό ιστορικό.' ); ?>
            <?php if ( isset( $_GET['grfuel_price_added'] ) ) : ?><div class="grfuel-inline-success">Η τιμή καταχωρήθηκε και το ιστορικό ενημερώθηκε.</div><?php endif; ?>
            <?php if ( isset( $_GET['grfuel_price_error'] ) ) : ?><div class="grfuel-inline-error">Ελέγχουμε την ημερομηνία και την τιμή και δοκιμάζουμε ξανά.</div><?php endif; ?>
            <?php if ( isset( $_GET['grfuel_price_deleted'] ) ) : ?><div class="grfuel-inline-success">Η καταχώριση διαγράφηκε από το αρχείο τιμών.</div><?php endif; ?>

            <section class="grfuel-chart-tool">
                <div><span class="sfv-eyebrow">Ρυθμίσεις γραφήματος</span><h2>Γράφημα ιστορικού τιμών</h2><p>Ορίζουμε την περίοδο του γραφήματος και αντιγράφουμε το κατάλληλο shortcode για desktop ή mobile.</p></div>
                <div class="grfuel-chart-shortcodes">
                    <?php foreach ( (array) $s['daily_prices'] as $chart_product ) : $chart_slug = sanitize_title( (string) ( $chart_product['slug'] ?? '' ) ); if ( '' === $chart_slug ) continue; ?>
                        <article class="grfuel-chart-shortcode-card">
                            <strong><?php echo esc_html( (string) ( $chart_product['name'] ?? $chart_slug ) ); ?></strong>
                            <label><span>Desktop γράφημα</span><code>[grfuel_price_chart product=&quot;<?php echo esc_attr( $chart_slug ); ?>&quot;]</code></label>
                            <label><span>Mobile γράφημα</span><code>[grfuel_price_chart_mobile product=&quot;<?php echo esc_attr( $chart_slug ); ?>&quot;]</code></label>
                        </article>
                    <?php endforeach; ?>
                </div>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="grfuel-chart-tool-form">
                    <input type="hidden" name="action" value="grfuel_save_daily_prices"><?php wp_nonce_field( 'grfuel_save_daily_prices' ); ?>
                    <div class="grfuel-price-manager" id="grfuel-price-manager">
                        <div class="grfuel-price-head"><span>Προϊόν</span><span>Slug / Σύντομος κώδικας</span><span>Τιμή</span><span>Μονάδα</span><span></span></div>
                        <div class="grfuel-price-rows">
                        <?php foreach ( (array) $s['daily_prices'] as $i => $product ) : ?>
                            <div class="grfuel-price-row">
                                <input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[daily_prices][<?php echo absint($i); ?>][name]" value="<?php echo esc_attr($product['name']); ?>" placeholder="Πετρέλαιο θέρμανσης" data-grfuel-name>
                                <div><input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[daily_prices][<?php echo absint($i); ?>][slug]" value="<?php echo esc_attr($product['slug']); ?>" placeholder="petrelaio-thermansis" data-grfuel-slug><code>[grfuel_price product=&quot;<?php echo esc_attr($product['slug']); ?>&quot;]</code></div>
                                <div class="grfuel-price-input"><input type="number" min="0" step="0.001" name="<?php echo esc_attr( self::OPTION ); ?>[daily_prices][<?php echo absint($i); ?>][price]" value="<?php echo esc_attr(number_format((float)$product['price'],3,'.','')); ?>"><span>€</span></div>
                                <input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[daily_prices][<?php echo absint($i); ?>][unit]" value="<?php echo esc_attr($product['unit']); ?>" placeholder="λίτρο">
                                <button type="button" class="grfuel-remove-price" aria-label="Αφαίρεση προϊόντος">×</button>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <button type="button" class="sfv-btn sfv-btn-secondary" id="grfuel-add-price"><span class="dashicons dashicons-plus-alt2"></span>Προσθήκη προϊόντος</button>
                        <template id="grfuel-price-template"><div class="grfuel-price-row"><input type="text" data-name="name" placeholder="Νέο προϊόν" data-grfuel-name><div><input type="text" data-name="slug" placeholder="neo-proion" data-grfuel-slug><code>[grfuel_price product=&quot;neo-proion&quot;]</code></div><div class="grfuel-price-input"><input type="number" min="0" step="0.001" data-name="price" value="0.000"><span>€</span></div><input type="text" data-name="unit" value="λίτρο"><button type="button" class="grfuel-remove-price" aria-label="Αφαίρεση προϊόντος">×</button></div></template>
                    </div>
                    <label><span>Αρχική περίοδος</span><div><input type="number" min="7" max="3650" name="<?php echo esc_attr(self::OPTION); ?>[chart_default_days]" value="<?php echo absint($s['chart_default_days']); ?>"><span>ημέρες</span></div></label>
                    <label><span>Μέγιστη περίοδος επισκέπτη</span><div><input type="number" min="7" max="3650" name="<?php echo esc_attr(self::OPTION); ?>[chart_max_days]" value="<?php echo absint($s['chart_max_days']); ?>"><span>ημέρες</span></div></label>
                    <label><span>Φόντο γραφημάτων</span><select name="<?php echo esc_attr(self::OPTION); ?>[chart_background]"><option value="transparent" <?php selected($s['chart_background'],'transparent'); ?>>Διαφανές / inherit</option><option value="light" <?php selected($s['chart_background'],'light'); ?>>Ανοιχτό</option></select></label><label class="grfuel-tool-toggle"><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[chart_frontend_controls]" value="1" <?php checked(!empty($s['chart_frontend_controls'])); ?>><span>Να εμφανίζεται επιλογή ημερών στον επισκέπτη</span></label>
                    <?php submit_button( 'Αποθήκευση προϊόντων και γραφήματος', 'primary', 'submit', false ); ?>
                </form>
            </section>

            <div class="grfuel-prices-grid">
            <?php foreach ( (array) $s['daily_prices'] as $product ) :
                $slug=sanitize_title((string)$product['slug']); $points=$this->product_history($slug,(int)$s['chart_default_days']); $values=array_map(static fn($p)=>(float)$p['price'],$points); $previous=count($values)>1?$values[count($values)-2]:(float)$product['price']; $current=(float)$product['price']; $delta=$previous>0?(($current-$previous)/$previous)*100:0; ?>
                <article class="grfuel-price-history-card">
                    <div class="grfuel-price-history-head"><div><small><?php echo esc_html($product['unit']); ?></small><h2><?php echo esc_html($product['name']); ?></h2></div><strong><?php echo esc_html(number_format_i18n($current,3)); ?> €</strong></div>
                    <div class="grfuel-price-trend <?php echo $delta>0?'is-up':($delta<0?'is-down':'is-flat'); ?>"><span class="dashicons <?php echo $delta>0?'dashicons-arrow-up-alt':($delta<0?'dashicons-arrow-down-alt':'dashicons-minus'); ?>"></span><?php echo esc_html(number_format_i18n(abs($delta),2)); ?>% από την προηγούμενη τιμή</div>
                    <?php echo $this->price_chart_svg($points,190); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <form class="grfuel-price-entry-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-grfuel-price-entry-form>
                        <input type="hidden" name="action" value="grfuel_add_price_entry">
                        <input type="hidden" name="product" value="<?php echo esc_attr($slug); ?>">
                        <?php wp_nonce_field( 'grfuel_add_price_entry' ); ?>
                        <div class="grfuel-history-entry-rows" data-grfuel-history-rows>
                            <div class="grfuel-history-entry-row">
                                <label><span>Ημερομηνία</span><input type="date" name="price_date[]" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required></label>
                                <label><span>Τιμή ημέρας</span><div><input type="number" min="0.001" step="0.001" name="price_value[]" value="<?php echo esc_attr(number_format((float)$product['price'],3,'.','')); ?>" required><span>€</span></div></label>
                                <button type="button" class="grfuel-history-remove" data-grfuel-history-remove aria-label="Αφαίρεση σειράς">×</button>
                            </div>
                        </div>
                        <div class="grfuel-history-actions"><button type="button" class="sfv-btn sfv-btn-secondary" data-grfuel-add-history><span class="dashicons dashicons-plus-alt2"></span>Προσθήκη</button><button type="submit" class="sfv-btn sfv-btn-primary">Αποθήκευση τιμών</button></div>
                        <template data-grfuel-history-template><div class="grfuel-history-entry-row"><label><span>Ημερομηνία</span><input type="date" name="price_date[]" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required></label><label><span>Τιμή ημέρας</span><div><input type="number" min="0.001" step="0.001" name="price_value[]" value="" placeholder="1.090" required><span>€</span></div></label><button type="button" class="grfuel-history-remove" data-grfuel-history-remove aria-label="Αφαίρεση σειράς">×</button></div></template>
                    </form>
                    <?php $archive_points = array_reverse( $this->product_history( $slug, 36500 ) ); ?>
                    <section class="grfuel-price-archive" aria-label="Αρχείο τιμών">
                        <div class="grfuel-price-archive-head"><div><span class="dashicons dashicons-archive"></span><strong>Αρχείο τιμών</strong></div><small><?php echo count($archive_points); ?> καταχωρίσεις</small></div>
                        <?php if ( $archive_points ) : ?><div class="grfuel-price-archive-list">
                            <?php foreach ( $archive_points as $entry ) : $recorded_at=(string)$entry['recorded_at']; ?>
                                <div class="grfuel-price-archive-row"><time datetime="<?php echo esc_attr($recorded_at); ?>"><?php echo esc_html(wp_date('d/m/Y',strtotime($recorded_at))); ?></time><strong><?php echo esc_html(number_format_i18n((float)$entry['price'],3)); ?> €</strong><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Να διαγραφεί οριστικά αυτή η τιμή από το ιστορικό;');"><input type="hidden" name="action" value="grfuel_delete_price_entry"><input type="hidden" name="product" value="<?php echo esc_attr($slug); ?>"><input type="hidden" name="recorded_at" value="<?php echo esc_attr($recorded_at); ?>"><?php wp_nonce_field('grfuel_delete_price_entry_'.$slug.'_'.md5($recorded_at)); ?><button type="submit" class="grfuel-archive-delete" aria-label="Διαγραφή τιμής"><span class="dashicons dashicons-trash"></span></button></form></div>
                            <?php endforeach; ?>
                        </div><?php else : ?><p class="grfuel-price-archive-empty">Δεν υπάρχουν αποθηκευμένες τιμές.</p><?php endif; ?>
                    </section>
                    <div class="grfuel-price-shortcodes">
                        <code>[grfuel_price product="<?php echo esc_attr($slug); ?>"]</code>
                        <code>[grfuel_price_chart product="<?php echo esc_attr($slug); ?>" days="<?php echo absint($s['chart_default_days']); ?>" max_days="<?php echo absint($s['chart_max_days']); ?>" controls="yes"]</code>
                        <code>[grfuel_price_chart_mobile product="<?php echo esc_attr($slug); ?>" days="<?php echo absint($s['chart_default_days']); ?>"]</code>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
            
        </div><?php
    }

    public function manager_users_page(): void {
        if ( ! current_user_can( self::USER_ADMIN_CAPABILITY ) ) wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) );
        $operators = get_users( array( 'role' => self::OPERATOR_ROLE, 'orderby' => 'display_name', 'order' => 'ASC' ) );
        ?>
        <div class="wrap sfv-admin-page grfuel-users-page">
            <?php $this->admin_nav( 'users' ); ?>
            <?php $this->section_intro(
                'Χρήστες Manager',
                'Μόνο ο Administrator δημιουργεί λογαριασμούς και αναθέτει τον ρόλο GRFuel Operator.',
                array( array( 'label' => 'Προσθήκη χρήστη', 'url' => admin_url( 'user-new.php' ), 'class' => 'sfv-btn-primary', 'icon' => 'dashicons-plus-alt2' ) )
            ); ?>
            <section class="sfv-panel"><div class="sfv-panel-heading"><div><h2>Email OTP</h2><p>Η ενεργοποίηση γίνεται αφού επιβεβαιωθεί ότι το WordPress στέλνει email αξιόπιστα. URL σύνδεσης: <code><?php echo esc_html( $this->manager_login_url() ); ?></code></p></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="grfuel_set_otp_enabled"><input type="hidden" name="enabled" value="<?php echo $this->otp_enabled() ? '0' : '1'; ?>"><?php wp_nonce_field( 'grfuel_set_otp_enabled' ); ?><button class="sfv-btn <?php echo esc_attr( $this->otp_enabled() ? 'sfv-btn-danger' : 'sfv-btn-primary' ); ?>" type="submit"><?php echo esc_html( $this->otp_enabled() ? 'Απενεργοποίηση OTP' : 'Ενεργοποίηση OTP' ); ?></button></form></div></section>
            <section class="sfv-panel"><div class="sfv-panel-heading"><div><h2>GRFuel Operators</h2><p><?php echo esc_html( sprintf( '%d λογαριασμοί Operator. Η πρόσβαση απαιτεί ρητή έγκριση Administrator.', count( $operators ) ) ); ?></p></div></div>
                <div class="sfv-submission-table" role="table">
                    <div class="sfv-submission-row sfv-submission-head" role="row"><span></span><span>Χρήστης</span><span>Email</span><span>Κατάσταση</span><span>Τελευταίο OTP login</span><span>Ενέργειες</span></div>
                    <?php foreach ( $operators as $operator ) : $last_otp = absint( get_user_meta( $operator->ID, 'grfuel_last_otp_login', true ) ); $approved = $this->operator_is_approved( $operator ); ?>
                        <div class="sfv-submission-row" role="row"><span class="dashicons dashicons-admin-users"></span><div class="sfv-row-primary"><strong><?php echo esc_html( $operator->display_name ); ?></strong><small><?php echo esc_html( $operator->user_login ); ?></small></div><div><?php echo esc_html( $operator->user_email ); ?></div><div><strong><?php echo esc_html( $approved ? 'Εγκεκριμένος' : 'Αναμένει έγκριση' ); ?></strong></div><div><?php echo esc_html( $last_otp ? wp_date( 'd/m/Y H:i', $last_otp ) : '—' ); ?></div><div class="sfv-row-actions"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="grfuel_set_operator_approval"><input type="hidden" name="user_id" value="<?php echo absint( $operator->ID ); ?>"><input type="hidden" name="approved" value="<?php echo $approved ? '0' : '1'; ?>"><?php wp_nonce_field( 'grfuel_set_operator_approval_' . $operator->ID ); ?><button class="sfv-btn <?php echo esc_attr( $approved ? 'sfv-btn-danger' : 'sfv-btn-primary' ); ?>" type="submit"><?php echo esc_html( $approved ? 'Ανάκληση' : 'Έγκριση' ); ?></button></form><a class="sfv-icon-action" href="<?php echo esc_url( get_edit_user_link( $operator->ID ) ); ?>" aria-label="Επεξεργασία χρήστη"><span class="dashicons dashicons-edit"></span></a></div></div>
                    <?php endforeach; if ( ! $operators ) : ?><div class="sfv-empty-submissions"><span class="dashicons dashicons-groups"></span><h3>Δεν υπάρχουν Operators</h3><p>Δημιουργήστε χρήστη και επιλέξτε τον ρόλο «GRFuel Operator».</p></div><?php endif; ?>
                </div>
            </section>
        </div><?php
    }

    public function set_operator_approval(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::USER_ADMIN_CAPABILITY ) ) wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) );
        $user_id = absint( $_POST['user_id'] ?? 0 );
        check_admin_referer( 'grfuel_set_operator_approval_' . $user_id );
        $user = get_user_by( 'id', $user_id );
        if ( ! $user instanceof WP_User || ! in_array( self::OPERATOR_ROLE, (array) $user->roles, true ) ) {
            wp_die( esc_html__( 'Ο λογαριασμός Operator δεν βρέθηκε.', 'secure-form-vault' ) );
        }
        if ( ! empty( $_POST['approved'] ) ) update_user_meta( $user_id, 'grfuel_operator_approved', '1' );
        else delete_user_meta( $user_id, 'grfuel_operator_approved' );
        wp_safe_redirect( admin_url( 'admin.php?page=grfuel-users' ) );
        exit;
    }

    public function set_otp_enabled(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::USER_ADMIN_CAPABILITY ) ) wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) );
        check_admin_referer( 'grfuel_set_otp_enabled' );
        update_option( self::OTP_ENABLED_OPTION, ! empty( $_POST['enabled'] ) ? '1' : '0', false );
        wp_safe_redirect( admin_url( 'admin.php?page=grfuel-users' ) );
        exit;
    }

    public function plugin_links( array $links ): array {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=sfv-documentation' ) ) . '">' . esc_html__( 'Τεκμηρίωση', 'secure-form-vault' ) . '</a>' );
        return $links;
    }

    private function unread_orders_count(): int {
        global $wpdb;
        $latest = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$this->table()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $last_read = (int) get_user_meta( get_current_user_id(), 'grfuel_last_read_order_id', true );
        if ( $latest <= $last_read ) return 0;
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE id > %d", $last_read ) );
    }

    private function mark_orders_read(): void {
        global $wpdb;
        $latest = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$this->table()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        update_user_meta( get_current_user_id(), 'grfuel_last_read_order_id', $latest );
        update_user_meta( get_current_user_id(), 'grfuel_last_notified_order_id', $latest );
    }

    private function admin_nav( string $active ): void {
        $items = array(
            'dashboard' => array( 'label' => 'Πίνακας Ελέγχου', 'url' => admin_url( 'admin.php?page=grfuel-manager' ), 'icon' => 'dashicons-dashboard' ),
            'submissions' => array( 'label' => 'Παραγγελίες', 'url' => admin_url( 'admin.php?page=sfv-submissions' ), 'icon' => 'dashicons-cart' ),
            'new-order' => array( 'label' => 'Νέα Παραγγελία', 'url' => admin_url( 'admin.php?page=grfuel-new-order' ), 'icon' => 'dashicons-plus-alt2' ),
            'forms' => array( 'label' => 'Φόρμες', 'url' => admin_url( 'admin.php?page=grfuel-forms' ), 'icon' => 'dashicons-feedback' ),
            'prices' => array( 'label' => 'Τιμές Ημέρας', 'url' => admin_url( 'admin.php?page=grfuel-prices' ), 'icon' => 'dashicons-chart-line' ),
            'users' => array( 'label' => 'Χρήστες Manager', 'url' => admin_url( 'admin.php?page=grfuel-users' ), 'icon' => 'dashicons-groups' ),
            'settings' => array( 'label' => 'Ρυθμίσεις', 'url' => admin_url( 'admin.php?page=sfv-settings' ), 'icon' => 'dashicons-admin-generic' ),
            'backup' => array( 'label' => 'Backup / Restore', 'url' => admin_url( 'admin.php?page=grfuel-backup' ), 'icon' => 'dashicons-database-export' ),
            'documentation' => array( 'label' => 'Τεκμηρίωση', 'url' => admin_url( 'admin.php?page=sfv-documentation' ), 'icon' => 'dashicons-book-alt' ),
            'changelog' => array( 'label' => 'Changelog', 'url' => admin_url( 'admin.php?page=grfuel-changelog' ), 'icon' => 'dashicons-list-view' ),
        );
        if ( ! current_user_can( self::USER_ADMIN_CAPABILITY ) ) unset( $items['users'] );
        echo '<nav class="sfv-compact-nav grfuel-app-sidebar" aria-label="GRFuel Manager"><button type="button" class="grfuel-mobile-menu-close" aria-label="Κλείσιμο μενού"><span class="dashicons dashicons-no-alt"></span></button><div class="grfuel-sidebar-brand">' . $this->admin_icon_markup() . '<div><strong>GRFuel</strong><span>Manager</span></div></div><div class="grfuel-sidebar-links">';
        foreach ( $items as $key => $item ) {
            $unread = 'submissions' === $key ? $this->unread_orders_count() : 0;
            $badge = ( 'submissions' === $key && $unread > 0 ) ? '<span class="grfuel-order-badge" data-grfuel-order-badge>' . esc_html( $unread > 99 ? '99+' : (string) $unread ) . '</span>' : ( 'submissions' === $key ? '<span class="grfuel-order-badge" data-grfuel-order-badge hidden></span>' : '' );
            printf( '<a class="%1$s" href="%2$s"><span class="grfuel-nav-icon dashicons %3$s"></span><span class="grfuel-nav-label">%4$s</span>%5$s</a>', esc_attr( $active === $key ? 'is-active' : '' ), esc_url( $item['url'] ), esc_attr( $item['icon'] ), esc_html( $item['label'] ), $badge ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '</div><button type="button" class="grfuel-sidebar-toggle" aria-label="Σύμπτυξη μενού" aria-expanded="true" title="Σύμπτυξη μενού"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14.5 6.5 9 12l5.5 5.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Σύμπτυξη</span></button><div class="grfuel-sidebar-foot"><span>GRFuel Manager v' . esc_html( self::VERSION ) . '</span><span class="grfuel-developer-credit">Developed by <a class="grfuel-developer-link" href="https://dazzlink.gr" target="_blank" rel="noopener noreferrer">Dazzlink</a></span><a class="grfuel-logout-link" href="' . esc_url( $this->manager_logout_url() ) . '" target="_top">← Έξοδος Manager</a></div></nav><header class="grfuel-mobile-header"><div class="grfuel-mobile-brand">' . $this->admin_icon_markup() . '<strong>GRFuel Manager</strong></div><button type="button" class="grfuel-mobile-burger" aria-label="Άνοιγμα μενού" aria-expanded="false"><span></span><span></span><span></span></button></header><button type="button" class="grfuel-mobile-overlay" aria-label="Κλείσιμο μενού"></button>';
    }

    private function admin_icon_markup(): string {
        return '<span class="grfuel-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 62 45" aria-hidden="true" focusable="false"><defs><linearGradient id="grfuel-red" x1=".87" y1="33.42" x2="60.32" y2="33.42" gradientUnits="userSpaceOnUse"><stop offset=".02" stop-color="#c82127"/><stop offset=".18" stop-color="#c22026"/><stop offset=".37" stop-color="#b31e23"/><stop offset=".58" stop-color="#9a1b1e"/><stop offset=".61" stop-color="#961b1e"/></linearGradient><linearGradient id="grfuel-red-top" x1="26.71" y1="14.78" x2="57.15" y2="14.78" gradientUnits="userSpaceOnUse"><stop offset=".02" stop-color="#c82127"/><stop offset=".18" stop-color="#c22026"/><stop offset=".37" stop-color="#b31e23"/><stop offset=".58" stop-color="#9a1b1e"/><stop offset=".61" stop-color="#961b1e"/></linearGradient><linearGradient id="grfuel-red-drop" x1="34.29" y1="35.31" x2="58.82" y2="35.31" gradientUnits="userSpaceOnUse"><stop offset=".02" stop-color="#c82127"/><stop offset=".18" stop-color="#c22026"/><stop offset=".37" stop-color="#b31e23"/><stop offset=".58" stop-color="#9a1b1e"/><stop offset=".61" stop-color="#961b1e"/></linearGradient></defs><path fill="#0e3e5f" fill-rule="evenodd" d="M40.72 31.28c-7.13 3.74-13.34 8.17-18.27 12.82h7c5.13-5.69 12.25-11.19 20.74-15.64 3.66-1.92 7.33-3.51 10.94-4.78-6.48 1.43-13.46 3.97-20.41 7.6Z"/><path fill="url(#grfuel-red)" fill-rule="evenodd" d="M30.38 30.41C18.9 34.46 8.7 39.28.88 44.1h7.27c8.31-5.44 19.81-11.02 32.94-15.66 6.67-2.35 13.16-4.26 19.23-5.69-8.78 1.26-19.13 3.84-29.94 7.66Z"/><path fill="#0e3e5f" fill-rule="evenodd" d="M20.71 40.32c-1.98 1.24-3.77 2.53-5.28 3.78h1.78c1.36-1.23 3.01-2.51 4.88-3.78h-1.38Z"/><path fill="url(#grfuel-red-top)" fill-rule="evenodd" d="M57.15 22.04c-.49-.94-1.06-1.79-1.68-2.59-.31-.44-.59-.79-.87-1.1-4.31-5.14-10.33-8.36-7.35-18.14-12.45 3.61-21.65 16.71-20.44 28.17.03.33.08.66.13.99 7.15-2.84 15.66-5.16 24.92-6.61 1.78-.28 3.55-.52 5.29-.72Z"/><path fill="url(#grfuel-red-drop)" fill-rule="evenodd" d="M58.82 28.71c-.02-.77-.09-1.54-.22-2.31-.03-.2-.07-.39-.11-.58-4.14 1.79-8.46 4.14-12.75 7.01-4.34 2.9-8.2 6.04-11.45 9.22 3.04 2.15 6.8 3.18 10.93 2.59 7.9-1.13 12.78-6.97 13.5-13.68.04-.37.07-.74.08-1.12v-.17c0-.32.01-.63.02-.95Z"/><path fill="#fff" fill-rule="evenodd" d="M35.2 11.04c-.99 1.25-2.07 3.04-3.02 5.13-1.63 3.57-2.37 6.86-1.95 8.21.09.34.44.54.8.46.29-.07.49-.3.52-.58v-.08c.18-2 .56-4.15 1.15-6.37.67-2.5 1.53-4.8 2.5-6.79Z"/></svg></span>';
    }

    private function section_intro( string $title, string $description, array $actions = array() ): void {
        ?>
        <section class="sfv-section-intro<?php echo $actions ? ' has-actions' : ''; ?>">
            <div class="sfv-section-main">
                <div class="sfv-section-copy"><span class="sfv-section-kicker"><?php echo esc_html( self::PRODUCT_NAME ); ?></span><h1><?php echo esc_html( $title ); ?></h1><p><?php echo esc_html( $description ); ?></p></div>
                <?php if ( $actions ) : ?>
                    <div class="sfv-section-actions">
                        <?php foreach ( $actions as $action ) : ?>
                            <a class="sfv-btn <?php echo esc_attr( $action['class'] ?? 'sfv-btn-secondary' ); ?>" href="<?php echo esc_url( $action['url'] ?? '#' ); ?>">
                                <?php if ( ! empty( $action['icon'] ) ) : ?><span class="dashicons <?php echo esc_attr( $action['icon'] ); ?>"></span><?php endif; ?>
                                <?php echo esc_html( $action['label'] ?? '' ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <a class="sfv-dazzlink-brand" href="https://www.dazzlink.gr" target="_blank" rel="noopener noreferrer"><img src="https://www.dazzlink.gr/images/logo-2025c.svg" alt="Dazzlink" loading="lazy"><span>Σχεδιασμός &amp; ανάπτυξη</span></a>
        </section>
        <?php
    }

    private function backup_data( bool $include_secrets = false ): array {
        global $wpdb;
        $settings = (array) get_option( self::OPTION, array() );
        if ( ! $include_secrets ) {
            unset( $settings['secret_key'], $settings['onesignal_api_key'] );
        }
        $forms = array();
        $posts = get_posts( array(
            'post_type' => self::CPT,
            'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ) );
        foreach ( $posts as $post ) {
            $forms[] = array(
                'id' => (int) $post->ID,
                'title' => $post->post_title,
                'status' => $post->post_status,
                'date' => $post->post_date,
                'date_gmt' => $post->post_date_gmt,
                'modified' => $post->post_modified,
                'modified_gmt' => $post->post_modified_gmt,
                'menu_order' => (int) $post->menu_order,
                'fields' => get_post_meta( $post->ID, '_sfv_fields', true ),
                'confirmation' => get_post_meta( $post->ID, '_sfv_confirmation', true ),
            );
        }

        $user_state = array();
        foreach ( get_users( array( 'fields' => array( 'ID', 'user_login' ) ) ) as $user ) {
            $last_read = get_user_meta( $user->ID, 'grfuel_last_read_order_id', true );
            $last_notified = get_user_meta( $user->ID, 'grfuel_last_notified_order_id', true );
            if ( '' !== $last_read || '' !== $last_notified ) {
                $user_state[] = array(
                    'user_login' => $user->user_login,
                    'last_read_order_id' => (int) $last_read,
                    'last_notified_order_id' => (int) $last_notified,
                );
            }
        }

        return array(
            'settings' => $settings,
            'price_history' => get_option( self::PRICE_HISTORY_OPTION, array() ),
            'forms' => $forms,
            'submissions' => $wpdb->get_results( 'SELECT id, form_id, submitted_at, payload, ip_hash, user_agent FROM ' . $this->table() . ' ORDER BY id ASC', ARRAY_A ),
            'user_state' => $user_state,
        );
    }

    public function backup_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) ); }
        $status = sanitize_key( (string) ( $_GET['grfuel_backup_status'] ?? '' ) );
        $messages = array(
            'restored' => array( 'success', 'Το backup επαναφέρθηκε επιτυχώς.' ),
            'invalid' => array( 'error', 'Το αρχείο δεν είναι έγκυρο backup του GRFuel Manager.' ),
            'upload_error' => array( 'error', 'Η μεταφόρτωση του αρχείου απέτυχε.' ),
            'too_large' => array( 'error', 'Το αρχείο backup υπερβαίνει το επιτρεπόμενο μέγεθος των 20 MB.' ),
            'checksum_error' => array( 'error', 'Ο έλεγχος ακεραιότητας απέτυχε. Το αρχείο ενδέχεται να έχει τροποποιηθεί ή καταστραφεί.' ),
            'restore_error' => array( 'error', 'Η επαναφορά δεν ολοκληρώθηκε. Τα δεδομένα του αρχείου δεν ήταν συμβατά.' ),
        );
        ?>
        <div class="wrap sfv-admin-page grfuel-backup-page">
            <?php $this->admin_nav( 'backup' ); ?>
            <?php $this->section_intro( 'Backup / Restore', 'Κατεβάζουμε τοπικό αντίγραφο όλων των ρυθμίσεων και δεδομένων του GRFuel Manager και το επαναφέρουμε όταν χρειαστεί.' ); ?>
            <?php if ( isset( $messages[ $status ] ) ) : $message = $messages[ $status ]; ?>
                <div class="notice notice-<?php echo esc_attr( $message[0] ); ?> is-dismissible"><p><?php echo esc_html( $message[1] ); ?></p></div>
            <?php endif; ?>
            <div class="grfuel-backup-grid">
                <section class="sfv-panel grfuel-backup-card">
                    <div class="grfuel-backup-icon"><span class="dashicons dashicons-download"></span></div>
                    <div><span class="sfv-settings-kicker">Export</span><h2>Δημιουργία backup</h2><p>Το αρχείο περιλαμβάνει φόρμες και τις ρυθμίσεις τους, γενικές ρυθμίσεις, προϊόντα και ιστορικό τιμών, παραγγελίες και την κατάσταση ειδοποιήσεων.</p></div>
                    <div class="sfv-settings-note"><span class="dashicons dashicons-shield"></span><div><strong>Τοπική αποθήκευση</strong><p>Το backup δημιουργείται τη στιγμή της λήψης και αποθηκεύεται μόνο στον υπολογιστή σας. Δεν παραμένει αντίγραφο στον server.</p></div></div>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="grfuel_export_backup">
                        <?php wp_nonce_field( 'grfuel_export_backup' ); ?>
                        <label class="grfuel-backup-confirm"><input type="checkbox" name="include_secrets" value="1"><span>Συμπερίληψη Turnstile Secret και OneSignal API Key (μόνο για κρυπτογραφημένη αποθήκευση).</span></label>
                        <button type="submit" class="sfv-btn sfv-btn-primary"><span class="dashicons dashicons-download"></span>Λήψη πλήρους backup</button>
                    </form>
                </section>
                <section class="sfv-panel grfuel-backup-card is-restore">
                    <div class="grfuel-backup-icon"><span class="dashicons dashicons-upload"></span></div>
                    <div><span class="sfv-settings-kicker">Import</span><h2>Επαναφορά backup</h2><p>Επιλέγουμε αρχείο <code>.json</code> που δημιουργήθηκε από το GRFuel Manager. Η επαναφορά αντικαθιστά τα τρέχοντα δεδομένα του plugin.</p></div>
                    <div class="sfv-doc-callout"><strong>Προσοχή:</strong> Οι υπάρχουσες φόρμες, παραγγελίες, τιμές και ρυθμίσεις θα αντικατασταθούν. Συνιστάται να κατεβάσετε πρώτα νέο backup της τρέχουσας κατάστασης.</div>
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Η επαναφορά θα αντικαταστήσει όλα τα τρέχοντα δεδομένα του GRFuel Manager. Συνέχεια;');">
                        <input type="hidden" name="action" value="grfuel_import_backup">
                        <?php wp_nonce_field( 'grfuel_import_backup' ); ?>
                        <label class="grfuel-backup-file"><span>Αρχείο backup</span><input type="file" name="grfuel_backup_file" accept="application/json,.json" required><small>Μέγιστο μέγεθος: 20 MB.</small></label>
                        <label class="grfuel-backup-confirm"><input type="checkbox" name="confirm_replace" value="1" required><span>Κατανοώ ότι τα τρέχοντα δεδομένα του plugin θα αντικατασταθούν.</span></label>
                        <button type="submit" class="sfv-btn sfv-btn-danger"><span class="dashicons dashicons-backup"></span>Επαναφορά δεδομένων</button>
                    </form>
                </section>
            </div>
        </div>
        <?php
    }

    private function normalize_imported_form( array $form ): ?array {
        $raw_fields = $form['fields'] ?? null;
        if ( ! is_array( $raw_fields ) || count( $raw_fields ) > 50 ) {
            return null;
        }
        $allowed_types = array( 'text', 'email', 'phone', 'number', 'date', 'dropdown', 'radio', 'delivery_region', 'delivery_city', 'checkbox', 'textarea', 'heading', 'content_text', 'divider', 'price_calculator', 'single_price_calculator', 'submit', 'turnstile' );
        $allowed_heading_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
        $allowed_heading_styles = array( 'default', 'small', 'medium', 'large', 'xlarge', '2xlarge', '3xlarge' );
        $allowed_divider_styles = array( 'default', 'small', 'icon' );
        $used_keys = array();
        $unique_types = array( 'submit' => false, 'turnstile' => false );
        $fields = array();
        foreach ( $raw_fields as $index => $field ) {
            if ( ! is_array( $field ) ) return null;
            $type = sanitize_key( (string) ( $field['type'] ?? 'text' ) );
            if ( ! in_array( $type, $allowed_types, true ) ) return null;
            if ( isset( $unique_types[ $type ] ) ) {
                if ( $unique_types[ $type ] ) return null;
                $unique_types[ $type ] = true;
            }
            $key = mb_substr( sanitize_key( (string) ( $field['key'] ?? '' ) ), 0, 64 );
            if ( '' === $key ) $key = 'field_' . ( $index + 1 );
            $base_key = $key; $suffix = 2;
            while ( isset( $used_keys[ $key ] ) ) {
                $tail = '_' . $suffix++;
                $key = mb_substr( $base_key, 0, 64 - strlen( $tail ) ) . $tail;
            }
            $used_keys[ $key ] = true;
            $options = array();
            if ( in_array( $type, array( 'dropdown', 'radio' ), true ) ) {
                if ( isset( $field['options'] ) && ! is_array( $field['options'] ) ) return null;
                foreach ( array_slice( (array) ( $field['options'] ?? array() ), 0, 100 ) as $option ) {
                    if ( ! is_scalar( $option ) ) return null;
                    $option = mb_substr( sanitize_text_field( (string) $option ), 0, 300 );
                    if ( '' !== $option ) $options[] = $option;
                }
            }
            $heading_tag = sanitize_key( (string) ( $field['heading_tag'] ?? 'h2' ) );
            $heading_style = sanitize_key( (string) ( $field['heading_style'] ?? 'default' ) );
            $divider_style = sanitize_key( (string) ( $field['divider_style'] ?? 'default' ) );
            $message_only_option = sanitize_text_field( (string) ( $field['message_only_option'] ?? '' ) );
            $fields[] = array(
                'key' => $key,
                'type' => $type,
                'label' => 'checkbox' === $type ? $this->sanitize_checkbox_label( (string) ( $field['label'] ?? '' ) ) : mb_substr( sanitize_text_field( (string) ( $field['label'] ?? '' ) ), 0, 300 ),
                'checkbox_value' => mb_substr( sanitize_text_field( (string) ( $field['checkbox_value'] ?? '' ) ), 0, 300 ),
                'content' => 'content_text' === $type ? mb_substr( sanitize_textarea_field( (string) ( $field['content'] ?? '' ) ), 0, 10000 ) : mb_substr( sanitize_text_field( (string) ( $field['content'] ?? '' ) ), 0, 500 ),
                'heading_tag' => in_array( $heading_tag, $allowed_heading_tags, true ) ? $heading_tag : 'h2',
                'heading_style' => in_array( $heading_style, $allowed_heading_styles, true ) ? $heading_style : 'default',
                'divider_style' => in_array( $divider_style, $allowed_divider_styles, true ) ? $divider_style : 'default',
                'product_field' => sanitize_key( (string) ( $field['product_field'] ?? '' ) ),
                'product_label' => mb_substr( sanitize_text_field( (string) ( $field['product_label'] ?? '' ) ), 0, 200 ),
                'daily_price_product' => 'single_price_calculator' === $type ? sanitize_title( (string) ( $field['daily_price_product'] ?? '' ) ) : '',
                'quantity_field' => sanitize_key( (string) ( $field['quantity_field'] ?? '' ) ),
                'pricing_rows' => $this->sanitize_pricing_rows( $field['pricing_rows'] ?? array() ),
                'currency' => mb_substr( sanitize_text_field( (string) ( $field['currency'] ?? '€' ) ), 0, 8 ),
                'unit_label' => mb_substr( sanitize_text_field( (string) ( $field['unit_label'] ?? 'λίτρο' ) ), 0, 40 ),
                'total_label' => mb_substr( sanitize_text_field( (string) ( $field['total_label'] ?? 'Σύνολο' ) ), 0, 80 ),
                'calculator_placeholder' => mb_substr( sanitize_text_field( (string) ( $field['calculator_placeholder'] ?? '' ) ), 0, 160 ),
                'placeholder' => mb_substr( sanitize_text_field( (string) ( $field['placeholder'] ?? '' ) ), 0, 300 ),
                'suffix' => 'number' === $type ? mb_substr( sanitize_text_field( (string) ( $field['suffix'] ?? 'lt' ) ), 0, 20 ) : '',
                'required' => ! empty( $field['required'] ),
                'hide_label' => ! empty( $field['hide_label'] ),
                'options' => $options,
                'message_only_enabled' => 'radio' === $type && ! empty( $field['message_only_enabled'] ),
                'message_only_option' => 'radio' === $type && in_array( $message_only_option, $options, true ) ? $message_only_option : '',
                'message_only_text' => 'radio' === $type ? mb_substr( sanitize_textarea_field( (string) ( $field['message_only_text'] ?? '' ) ), 0, 1000 ) : '',
                'location_region_field' => sanitize_key( (string) ( $field['location_region_field'] ?? '' ) ),
                'layout' => $this->sanitize_layout( $field['layout'] ?? array(), $field['widths'] ?? array() ),
                'dependency' => $this->sanitize_dependency( $field['dependency'] ?? array() ),
            );
        }
        $region_keys = array_column(
            array_values( array_filter( $fields, static fn( $field ) => 'delivery_region' === ( $field['type'] ?? '' ) ) ),
            'key'
        );
        if ( 1 === count( $region_keys ) ) {
            foreach ( $fields as &$normalized_field ) {
                if ( 'delivery_city' === ( $normalized_field['type'] ?? '' ) && '' === ( $normalized_field['location_region_field'] ?? '' ) ) {
                    $normalized_field['location_region_field'] = (string) $region_keys[0];
                }
            }
            unset( $normalized_field );
        }
        $confirmation = is_array( $form['confirmation'] ?? null ) ? $form['confirmation'] : array();
        $email_keys = array_column( array_values( array_filter( $fields, static fn( $field ) => 'email' === $field['type'] ) ), 'key' );
        $email_field = sanitize_key( (string) ( $confirmation['email_field'] ?? '' ) );
        if ( ! in_array( $email_field, $email_keys, true ) ) $email_field = '';
        return array(
            'title' => mb_substr( sanitize_text_field( (string) ( $form['title'] ?? 'Εισαγόμενη φόρμα' ) ), 0, 200 ) ?: 'Εισαγόμενη φόρμα',
            'menu_order' => min( 100000, absint( $form['menu_order'] ?? 0 ) ),
            'fields' => $fields,
            'confirmation' => array(
                'success_message' => mb_substr( sanitize_textarea_field( (string) ( $confirmation['success_message'] ?? '' ) ), 0, 2000 ),
                'email_field' => $email_field,
                'email_subject' => mb_substr( sanitize_text_field( (string) ( $confirmation['email_subject'] ?? '' ) ), 0, 200 ),
                'email_message' => mb_substr( sanitize_textarea_field( (string) ( $confirmation['email_message'] ?? '' ) ), 0, 10000 ),
            ),
        );
    }

    private function normalize_imported_submission_payload( $payload ): ?string {
        $decoded = is_string( $payload ) ? json_decode( $payload, true ) : $payload;
        if ( ! is_array( $decoded ) || count( $decoded ) > 60 ) return null;
        $clean = array();
        foreach ( $decoded as $key => $item ) {
            $clean_key = sanitize_key( (string) $key );
            if ( '' === $clean_key || ! is_array( $item ) || ! is_scalar( $item['label'] ?? '' ) || ! is_scalar( $item['value'] ?? '' ) ) return null;
            $clean[ $clean_key ] = array(
                'label' => mb_substr( sanitize_text_field( (string) ( $item['label'] ?? '' ) ), 0, 300 ),
                'value' => mb_substr( sanitize_textarea_field( (string) ( $item['value'] ?? '' ) ), 0, 5000 ),
            );
        }
        return wp_json_encode( $clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    private function normalize_imported_price_history( $history ): ?array {
        if ( ! is_array( $history ) || count( $history ) > 20 ) return null;
        $clean = array();
        foreach ( $history as $slug => $entries ) {
            $clean_slug = sanitize_title( (string) $slug );
            if ( '' === $clean_slug || ! is_array( $entries ) || count( $entries ) > 3650 ) return null;
            foreach ( $entries as $entry ) {
                if ( ! is_array( $entry ) ) return null;
                $recorded_at = sanitize_text_field( (string) ( $entry['recorded_at'] ?? '' ) );
                $date_part = substr( $recorded_at, 0, 10 );
                $date = DateTimeImmutable::createFromFormat( '!Y-m-d', $date_part );
                $price = (float) ( $entry['price'] ?? 0 );
                if ( ! $date || $date->format( 'Y-m-d' ) !== $date_part || $price < 0 || $price > 1000000 ) return null;
                $clean[ $clean_slug ][] = array( 'price' => $price, 'recorded_at' => $date_part . ' 12:00:00' );
            }
        }
        return $clean;
    }

    public function export_form(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) );
        }

        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        check_admin_referer( 'grfuel_export_form_' . $form_id );
        $form = get_post( $form_id );
        if ( ! $form || self::CPT !== $form->post_type ) {
            wp_die( esc_html__( 'Η φόρμα δεν βρέθηκε.', 'secure-form-vault' ) );
        }

        $data = array(
            'id'           => (int) $form->ID,
            'title'        => $form->post_title,
            'status'       => $form->post_status,
            'date'         => $form->post_date,
            'date_gmt'     => $form->post_date_gmt,
            'modified'     => $form->post_modified,
            'modified_gmt' => $form->post_modified_gmt,
            'menu_order'   => (int) $form->menu_order,
            'fields'       => get_post_meta( $form->ID, '_sfv_fields', true ),
            'confirmation' => get_post_meta( $form->ID, '_sfv_confirmation', true ),
        );
        $checksum_payload = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $export = array(
            'format'         => 'grfuel-manager-form',
            'format_version' => 1,
            'plugin_version' => self::VERSION,
            'exported_at'    => current_time( 'mysql', true ),
            'checksum'       => hash( 'sha256', (string) $checksum_payload ),
            'form'           => $data,
        );

        $site = sanitize_title( get_bloginfo( 'name' ) );
        if ( '' === $site ) $site = 'site';
        $title = sanitize_title( $form->post_title );
        if ( '' === $title ) $title = 'form-' . $form->ID;
        $filename = $site . '-GRFuel-form-' . $title . '-' . wp_date( 'dmY-His' ) . '.json';

        $this->send_private_response_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'X-Content-Type-Options: nosniff' );
        echo wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        exit;
    }

    public function import_form(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) );
        }
        check_admin_referer( 'grfuel_import_form' );
        $redirect = admin_url( 'admin.php?page=grfuel-forms' );
        if ( empty( $_FILES['grfuel_form_file']['tmp_name'] ) || UPLOAD_ERR_OK !== (int) ( $_FILES['grfuel_form_file']['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
            wp_safe_redirect( add_query_arg( 'grfuel_form_import_error', '1', $redirect ) );
            exit;
        }
        if ( (int) ( $_FILES['grfuel_form_file']['size'] ?? 0 ) > 2 * MB_IN_BYTES ) {
            wp_safe_redirect( add_query_arg( 'grfuel_form_import_error', '1', $redirect ) );
            exit;
        }
        $raw = file_get_contents( (string) $_FILES['grfuel_form_file']['tmp_name'] );
        $decoded = json_decode( (string) $raw, true );
        if ( ! is_array( $decoded ) || 'grfuel-manager-form' !== ( $decoded['format'] ?? '' ) || empty( $decoded['form'] ) || ! is_array( $decoded['form'] ) ) {
            wp_safe_redirect( add_query_arg( 'grfuel_form_import_error', '1', $redirect ) );
            exit;
        }
        $form = $decoded['form'];
        $payload = wp_json_encode( $form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $stored_checksum = strtolower( sanitize_text_field( (string) ( $decoded['checksum'] ?? '' ) ) );
        $calculated_checksum = hash( 'sha256', (string) $payload );
        $export_version = sanitize_text_field( (string) ( $decoded['plugin_version'] ?? '' ) );
        $is_legacy_export = '' !== $export_version && version_compare( $export_version, '4.3.18', '<' );
        $has_well_formed_checksum = 1 === preg_match( '/^[a-f0-9]{64}$/', $stored_checksum );

        // Releases before 4.3.18 could serialize some form metadata differently
        // between checksum generation and the final JSON document. Their checksum
        // is therefore retained as a structural marker, while the strict schema
        // normalizer below remains the actual trust boundary. Current exports
        // must always reproduce the exact SHA-256 value.
        if ( ! $has_well_formed_checksum || ( ! $is_legacy_export && ! hash_equals( $stored_checksum, $calculated_checksum ) ) ) {
            wp_safe_redirect( add_query_arg( 'grfuel_form_import_error', '1', $redirect ) );
            exit;
        }
        $normalized_form = $this->normalize_imported_form( $form );
        if ( null === $normalized_form ) {
            wp_safe_redirect( add_query_arg( 'grfuel_form_import_error', '1', $redirect ) );
            exit;
        }
        $post_id = wp_insert_post( array(
            'post_type'   => self::CPT,
            'post_title'  => $normalized_form['title'],
            'post_status' => 'draft',
            'menu_order'  => $normalized_form['menu_order'],
        ), true );
        if ( is_wp_error( $post_id ) ) {
            wp_safe_redirect( add_query_arg( 'grfuel_form_import_error', '1', $redirect ) );
            exit;
        }
        update_post_meta( $post_id, '_sfv_fields', $normalized_form['fields'] );
        update_post_meta( $post_id, '_sfv_confirmation', $normalized_form['confirmation'] );
        wp_safe_redirect( add_query_arg( 'grfuel_form_imported', '1', $redirect ) );
        exit;
    }

    public function export_backup(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) ); }
        check_admin_referer( 'grfuel_export_backup' );
        $data = $this->backup_data( ! empty( $_POST['include_secrets'] ) );
        $backup = array(
            'format' => 'grfuel-manager-backup',
            'format_version' => 1,
            'plugin_version' => self::VERSION,
            'created_at' => gmdate( 'c' ),
            'site' => array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
            'data' => $data,
            'checksum' => hash( 'sha256', wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
        );
        $site = sanitize_title( get_bloginfo( 'name' ) ) ?: 'site';
        $filename = $site . '-GRFuel-backup-' . wp_date( 'dmY-His' ) . '.json';
        $this->send_private_response_headers();
        header( 'Content-Type: application/json; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'X-Content-Type-Options: nosniff' );
        echo wp_json_encode( $backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        exit;
    }

    private function backup_redirect( string $status ): void {
        wp_safe_redirect( add_query_arg( 'grfuel_backup_status', sanitize_key( $status ), admin_url( 'admin.php?page=grfuel-backup' ) ) );
        exit;
    }

    public function import_backup(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) ); }
        check_admin_referer( 'grfuel_import_backup' );
        if ( empty( $_POST['confirm_replace'] ) ) { $this->backup_redirect( 'invalid' ); }
        if ( empty( $_FILES['grfuel_backup_file'] ) || ! is_array( $_FILES['grfuel_backup_file'] ) ) { $this->backup_redirect( 'upload_error' ); }
        $file = $_FILES['grfuel_backup_file'];
        if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) { $this->backup_redirect( 'upload_error' ); }
        if ( (int) ( $file['size'] ?? 0 ) > 20 * MB_IN_BYTES ) { $this->backup_redirect( 'too_large' ); }
        $tmp = (string) ( $file['tmp_name'] ?? '' );
        if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) { $this->backup_redirect( 'upload_error' ); }
        $raw = file_get_contents( $tmp );
        $backup = is_string( $raw ) ? json_decode( $raw, true ) : null;
        if ( ! is_array( $backup ) || 'grfuel-manager-backup' !== ( $backup['format'] ?? '' ) || 1 !== (int) ( $backup['format_version'] ?? 0 ) || ! is_array( $backup['data'] ?? null ) ) { $this->backup_redirect( 'invalid' ); }
        $data = $backup['data'];
        $checksum = hash( 'sha256', wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
        if ( empty( $backup['checksum'] ) || ! hash_equals( (string) $backup['checksum'], $checksum ) ) { $this->backup_redirect( 'checksum_error' ); }
        if ( ! isset( $data['settings'], $data['price_history'], $data['forms'], $data['submissions'] ) || ! is_array( $data['forms'] ) || ! is_array( $data['submissions'] ) ) { $this->backup_redirect( 'invalid' ); }

        if ( count( $data['forms'] ) > 200 || count( $data['submissions'] ) > 100000 ) { $this->backup_redirect( 'invalid' ); }
        $normalized_forms = array();
        foreach ( $data['forms'] as $form ) {
            if ( ! is_array( $form ) ) { $this->backup_redirect( 'invalid' ); }
            $normalized = $this->normalize_imported_form( $form );
            if ( null === $normalized ) { $this->backup_redirect( 'invalid' ); }
            $normalized['id'] = absint( $form['id'] ?? 0 );
            $normalized['status'] = in_array( (string) ( $form['status'] ?? '' ), array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), true ) ? (string) $form['status'] : 'draft';
            $normalized['date'] = sanitize_text_field( (string) ( $form['date'] ?? current_time( 'mysql' ) ) );
            $normalized['date_gmt'] = sanitize_text_field( (string) ( $form['date_gmt'] ?? current_time( 'mysql', true ) ) );
            $normalized_forms[] = $normalized;
        }
        $normalized_submissions = array();
        foreach ( $data['submissions'] as $row ) {
            if ( ! is_array( $row ) ) { $this->backup_redirect( 'invalid' ); }
            $clean_payload = $this->normalize_imported_submission_payload( $row['payload'] ?? '{}' );
            if ( null === $clean_payload ) { $this->backup_redirect( 'invalid' ); }
            $row['payload'] = $clean_payload;
            $normalized_submissions[] = $row;
        }
        $normalized_history = $this->normalize_imported_price_history( $data['price_history'] );
        if ( null === $normalized_history ) { $this->backup_redirect( 'invalid' ); }
        $incoming_settings = is_array( $data['settings'] ) ? $data['settings'] : array();
        $current_settings = (array) get_option( self::OPTION, array() );
        foreach ( array( 'secret_key', 'onesignal_api_key' ) as $secret_key ) {
            if ( ! array_key_exists( $secret_key, $incoming_settings ) && isset( $current_settings[ $secret_key ] ) ) $incoming_settings[ $secret_key ] = $current_settings[ $secret_key ];
        }
        $normalized_settings = $this->sanitize_settings( $incoming_settings );

        global $wpdb;
        $this->create_table();
        $existing = get_posts( array( 'post_type' => self::CPT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
        foreach ( $existing as $form_id ) { wp_delete_post( (int) $form_id, true ); }
        $wpdb->query( 'DELETE FROM ' . $this->table() );

        $form_map = array();
        foreach ( $normalized_forms as $form ) {
            if ( ! is_array( $form ) ) { continue; }
            $old_id = absint( $form['id'] ?? 0 );
            $postarr = array(
                'post_type' => self::CPT,
                'post_title' => $form['title'],
                'post_status' => in_array( (string) ( $form['status'] ?? '' ), array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), true ) ? (string) $form['status'] : 'publish',
                'post_date' => sanitize_text_field( (string) ( $form['date'] ?? current_time( 'mysql' ) ) ),
                'post_date_gmt' => sanitize_text_field( (string) ( $form['date_gmt'] ?? current_time( 'mysql', true ) ) ),
                'menu_order' => (int) $form['menu_order'],
            );
            if ( $old_id > 0 && ! get_post( $old_id ) ) { $postarr['import_id'] = $old_id; }
            $new_id = wp_insert_post( wp_slash( $postarr ), true );
            if ( is_wp_error( $new_id ) ) { $this->backup_redirect( 'restore_error' ); }
            $form_map[ $old_id ] = (int) $new_id;
            update_post_meta( $new_id, '_sfv_fields', $form['fields'] );
            update_post_meta( $new_id, '_sfv_confirmation', $form['confirmation'] );
        }

        update_option( self::OPTION, $normalized_settings, false );
        update_option( self::PRICE_HISTORY_OPTION, $normalized_history, false );
        foreach ( $normalized_submissions as $row ) {
            if ( ! is_array( $row ) ) { continue; }
            $old_form_id = absint( $row['form_id'] ?? 0 );
            $insert = array(
                'id' => absint( $row['id'] ?? 0 ),
                'form_id' => $form_map[ $old_form_id ] ?? $old_form_id,
                'submitted_at' => sanitize_text_field( (string) ( $row['submitted_at'] ?? current_time( 'mysql', true ) ) ),
                'payload' => (string) ( $row['payload'] ?? '{}' ),
                'ip_hash' => substr( sanitize_text_field( (string) ( $row['ip_hash'] ?? '' ) ), 0, 64 ),
                'user_agent' => substr( sanitize_text_field( (string) ( $row['user_agent'] ?? '' ) ), 0, 255 ),
            );
            if ( $insert['id'] < 1 ) { unset( $insert['id'] ); }
            $ok = $wpdb->insert( $this->table(), $insert, array_fill( 0, count( $insert ), '%s' ) );
            if ( false === $ok ) { $this->backup_redirect( 'restore_error' ); }
        }
        $max_id = (int) $wpdb->get_var( 'SELECT MAX(id) FROM ' . $this->table() );
        $wpdb->query( 'ALTER TABLE ' . $this->table() . ' AUTO_INCREMENT = ' . max( 1, $max_id + 1 ) );

        foreach ( (array) ( $data['user_state'] ?? array() ) as $state ) {
            if ( ! is_array( $state ) || empty( $state['user_login'] ) ) { continue; }
            $user = get_user_by( 'login', sanitize_user( (string) $state['user_login'] ) );
            if ( ! $user ) { continue; }
            update_user_meta( $user->ID, 'grfuel_last_read_order_id', absint( $state['last_read_order_id'] ?? 0 ) );
            update_user_meta( $user->ID, 'grfuel_last_notified_order_id', absint( $state['last_notified_order_id'] ?? 0 ) );
        }
        $this->backup_redirect( 'restored' );
    }

    public function documentation_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) ); }
        ?>
        <div class="wrap sfv-admin-page sfv-docs-page">
            <?php $this->admin_nav( 'documentation' ); ?>
            <?php $this->section_intro( 'Τεκμηρίωση', 'Παρουσιάζουμε τη δημιουργία φορμών, τις ρυθμίσεις ασφαλείας, τη διαχείριση υποβολών και τις διαθέσιμες εξαγωγές.' ); ?>
            <div class="sfv-docs-grid">
                <aside class="sfv-panel sfv-docs-index"><strong>Περιεχόμενα</strong><a href="#sfv-start">Γρήγορη εκκίνηση</a><a href="#sfv-forms-doc">Φόρμες</a><a href="#sfv-prices-doc">Τιμές Ημέρας</a><a href="#sfv-submissions-doc">Παραγγελίες & Export</a><a href="#sfv-notifications-doc">Ειδοποιήσεις</a><a href="#sfv-turnstile-doc">Turnstile</a><a href="#sfv-security-doc">Ασφάλεια</a><a href="#sfv-backup-doc">Backup / Restore</a></aside>
                <main class="sfv-panel sfv-docs-content">
                    <section id="sfv-start"><h2>Γρήγορη εκκίνηση</h2><ol><li>Από τη <strong>Διαχείριση φορμών</strong> πατάμε <strong>Νέα φόρμα</strong>, ορίζουμε το όνομα στο αναδυόμενο παράθυρο και συνεχίζουμε στον builder.</li><li>Μεταφέρουμε τα απαιτούμενα πεδία από την αριστερή παλέτα στο canvas με drag & drop.</li><li>Προσθέτουμε στη φόρμα ένα στοιχείο <strong>Submit</strong> και, προαιρετικά, ένα στοιχείο <strong>Turnstile</strong>.</li><li>Μετά τη δημοσίευση, αντιγράφουμε το shortcode στην επιθυμητή σελίδα YOOtheme.</li></ol></section>
                    <section id="sfv-forms-doc"><h2>Φόρμες</h2><p>Ο οπτικός builder υποστηρίζει drag & drop, προεπισκόπηση πεδίων και αλλαγή σειράς. Διατίθενται πεδία text, email, phone, number, date, dropdown, radio, checkbox, textarea, heading, ελεύθερο κείμενο, divider, submit, Cloudflare Turnstile, υπολογισμός τιμής, Νομός παράδοσης και εξαρτώμενη Πόλη παράδοσης.</p><h3>Διάταξη και breakpoints</h3><p>Κάθε πεδίο μπορεί να έχει πλάτος <code>1/1</code>, <code>1/2</code>, <code>1/3</code> ή <code>2/3</code>. Με breakpoint Small ή Medium η επιλεγμένη διάταξη εφαρμόζεται από το αντίστοιχο πλάτος οθόνης και πάνω, ενώ σε μικρότερες οθόνες το πεδίο παραμένει πλήρους πλάτους.</p><h3>Dependencies</h3><p>Τα πεδία μπορούν να εμφανίζονται ή να αποκρύπτονται ανάλογα με την τιμή άλλου πεδίου. Οι κανόνες ελέγχονται τόσο στον browser όσο και στον server. Η Πόλη παράδοσης συνδέεται με το πεδίο Νομός παράδοσης και εμφανίζει μόνο τις σχετικές πόλεις.</p><h3>Συναίνεση και απαντήσεις</h3><p>Στα checkbox συναίνεσης μπορεί να οριστεί ξεχωριστή τιμή υποβολής, ώστε τα exports να περιέχουν σύντομη και καθαρή τιμή αντί για HTML κείμενο. Στην ενότητα «Απαντήσεις μετά την υποβολή» ρυθμίζονται το μήνυμα επιτυχίας και το email επιβεβαίωσης προς τον χρήστη.</p></section>
                    <section id="sfv-prices-doc"><h2>Τιμές Ημέρας</h2><p>Διαχειριζόμαστε προϊόντα, slug, τιμή και μονάδα, καταχωρίζουμε ιστορικό τιμών και διαγράφουμε μεμονωμένες εγγραφές. Παρέχονται shortcodes για απλή εμφάνιση τιμής, desktop γράφημα και ξεχωριστό mobile γράφημα με σελιδοποίηση και tooltips.</p></section>
                    <section id="sfv-turnstile-doc"><h2>Cloudflare Turnstile</h2><p>Καταχωρίζουμε το Site Key και το Secret Key στις Ρυθμίσεις και προσθέτουμε το στοιχείο Turnstile στη φόρμα. Πραγματοποιούμε την επαλήθευση server-side πριν αποθηκευτεί η υποβολή.</p></section>
                    <section id="sfv-submissions-doc"><h2>Υποβολές, Export και διατήρηση δεδομένων</h2><p>Στις Ρυθμίσεις → PDF Branding ορίζουμε την επωνυμία, λογότυπο PNG/SVG και την παλέτα χρωμάτων των PDF. Οι τίτλοι των απαντήσεων στο PDF χρησιμοποιούν κατά προτεραιότητα το placeholder κάθε πεδίου και, όταν δεν υπάρχει, το label ή το field name.</p><p>Στην οθόνη Υποβολές & Export φιλτράρουμε ανά φόρμα και εξάγουμε πραγματικό αρχείο Excel <code>.xlsx</code> ή XML. Χρησιμοποιούμε φίλτρα export με εύρος ημερομηνιών και εύρος IDs. Επιλέγουμε μία ή περισσότερες υποβολές από τα checkboxes της λίστας και χρησιμοποιούμε το <strong>Export PDF</strong> για ενιαία, επαγγελματικά μορφοποιημένη εκτύπωση. Κάθε υποβολή τοποθετείται σε ξεχωριστή σελίδα PDF. Παραμένει διαθέσιμη και η μεμονωμένη ενέργεια PDF ανά γραμμή.</p><p>Για τη διαχείριση της περιόδου διατήρησης δεδομένων χρησιμοποιούμε την ενότητα <strong>GDPR & συντήρηση</strong>. Με το <strong>Clear submissions</strong> διαγράφουμε οριστικά όλες τις αποθηκευμένες υποβολές, χωρίς να αλλάζει ο επόμενος αριθμός ID. Με το <strong>Reset submission ID</strong> επαναφέρουμε την αρίθμηση ώστε η επόμενη υποβολή να ξεκινήσει από το 1. Η επαναφορά επιτρέπεται μόνο όταν δεν υπάρχουν αποθηκευμένες υποβολές.</p><div class="sfv-doc-callout"><strong>Προσοχή:</strong> Πριν από μαζική διαγραφή πραγματοποιούμε export ή άλλο αντίγραφο ασφαλείας, εφόσον απαιτείται από την πολιτική διατήρησης δεδομένων του οργανισμού. Οι ενέργειες διαγραφής δεν αναιρούνται.</div></section>
                    <section id="sfv-notifications-doc"><h2>Ειδοποιήσεις</h2><p>Οι νέες παραγγελίες εμφανίζονται με αριθμό μη αναγνωσμένων στο sidebar. Η σελίδα Παραγγελίες τις σημειώνει ως αναγνωσμένες. Προαιρετικά ενεργοποιούνται browser notifications, εσωτερική ειδοποίηση στο WordPress admin και δοκιμαστική ειδοποίηση για έλεγχο της άδειας του browser.</p></section>
                    <section id="sfv-backup-doc"><h2>Backup / Restore</h2><p>Η ενότητα Backup / Restore δημιουργεί ένα πλήρες αρχείο JSON με τις φόρμες, τις ρυθμίσεις, τα προϊόντα και το ιστορικό τιμών, τις παραγγελίες και την κατάσταση ειδοποιήσεων. Το αρχείο κατεβαίνει απευθείας στον υπολογιστή και δεν αποθηκεύεται στον server.</p><p>Κατά την επαναφορά, τα τρέχοντα δεδομένα του GRFuel Manager αντικαθίστανται από το περιεχόμενο του backup. Πριν από κάθε restore συνιστάται η λήψη νέου backup της τρέχουσας κατάστασης.</p></section><section id="sfv-security-doc"><h2>Ασφάλεια</h2><p>Για την προστασία των δεδομένων χρησιμοποιούμε nonces, capability checks, server-side validation, sanitization, escaping, prepared SQL, honeypot, ελάχιστο χρόνο συμπλήρωσης, rate limiting και προστασία από Excel formula injection.</p><div class="sfv-doc-callout"><strong>Σημαντικό:</strong> Πριν από τη χρήση σε production, πραγματοποιούμε δοκιμή της φόρμας σε staging και επιβεβαιώνουμε ότι το hosting διαθέτει PHP ZipArchive για τα XLSX exports.</div></section>
                </main>
            </div>
        </div>
        <?php
    }

    public function changelog_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) ); }
        ?>
        <div class="wrap sfv-admin-page sfv-changelog-page">
            <?php $this->admin_nav( 'changelog' ); ?>
            <?php $this->section_intro( 'Changelog', 'Όλες οι αλλαγές και οι βελτιώσεις του GRFuel Manager ανά έκδοση.' ); ?>
            <main class="sfv-panel sfv-docs-content grfuel-changelog-content">
                <section class="grfuel-changelog-shell"><header class="grfuel-changelog-hero"><span class="dashicons dashicons-backup"></span><div><small>GRFuel Manager</small><h2>Ιστορικό εκδόσεων</h2><p>Όλες οι βελτιώσεις και οι διορθώσεις του manager, οργανωμένες ανά έκδοση.</p></div></header><div class="grfuel-changelog-timeline"><article class="grfuel-changelog-release is-latest"><div class="grfuel-changelog-version"><strong>4.3.7</strong><span>Τρέχουσα έκδοση</span></div><ul><li>Επαναφορά όλων των αλλαγών έως την 4.3.6 πάνω στην ασφαλή βάση, χωρίς να επηρεάζονται τα Acrobat PDF icons.</li><li>Τα τελευταία έξι πεδία του PDF εμφανίζονται με σταθερή σειρά: Διεύθυνση, Τηλέφωνο, Email, Επιθυμητή ημερομηνία, Ποσότητα, Σύνολο παραγγελίας.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.3.3</strong></div><ul><li>Διορθώθηκε οριστικά το PDF ώστε να αφαιρεί την αποδοχή Πολιτικής Απορρήτου με έλεγχο πεδίου, label και αποθηκευμένης τιμής.</li><li>Το Σύνολο παραγγελίας εμφανίζεται στη θέση της αποδοχής και υπολογίζεται εκ νέου όταν λείπει από παλαιότερη παραγγελία.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.3.2</strong></div><ul><li>Πρώτη προσαρμογή της διάταξης PDF για απόκρυψη πολιτικής και μεταφορά του συνόλου.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.3.1</strong></div><ul><li>Προστέθηκε υδατογράφημα PNG/SVG στο PDF Branding με αποθηκευόμενο opacity και κεντραρισμένη διάταξη 3/4 × 3/5 της σελίδας.</li><li>Το εικονίδιο PDF στις παραγγελίες αντικαταστάθηκε με το κλασικό σύμβολο Acrobat.</li><li>Το Changelog ανασχεδιάστηκε σύμφωνα με τη διάταξη του Stavros Floral Studio Suite.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.3.0</strong></div><ul><li>Προστέθηκε admin-only mobile push μέσω OneSignal, με σύνδεση συσκευής, δοκιμαστική αποστολή και αυτόματη ειδοποίηση σε κάθε νέα παραγγελία.</li><li>Τα push εμφανίζουν μόνο ασφαλή στοιχεία παραγγελίας και το OneSignal API key παραμένει αποκλειστικά στον WordPress server.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.2.8</strong></div><ul><li>Διορθώθηκε το οριζόντιο overflow στο mobile ώστε ο manager, το sticky header και όλα τα panels να μην ξεπερνούν το διαθέσιμο πλάτος της οθόνης.</li><li>Φορτώνεται επίσημα η γραμματοσειρά Geom από Google Fonts και εφαρμόζεται σε ολόκληρο το περιβάλλον του manager.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.2.7</strong></div><ul><li>Διορθώθηκε το count-up animation των αριθμητικών τιμών στα tooltips των γραφημάτων κατά το hover ή το touch.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.2.6</strong></div><ul><li>Το mobile header τοποθετήθηκε στο απολύτως επάνω άκρο της οθόνης, ανεξάρτητα από την WordPress admin bar.</li><li>Το off-canvas sidebar καλύπτει πλέον ολόκληρο το διαθέσιμο ύψος της συσκευής, με υποστήριξη dynamic viewport και safe areas.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.2.5</strong></div><ul><li>Το tooltip των γραφημάτων ακολουθεί πλέον το animation του reference plugin, με ομαλή εμφάνιση και animated μετάβαση της αριθμητικής τιμής.</li><li>Το mobile menu ανασχεδιάστηκε με sticky επάνω μπάρα, burger αριστερά, κεντραρισμένο λογότυπο και ξεχωριστό κουμπί κλεισίματος μέσα στο off-canvas sidebar.</li><li>Οι κύκλοι του Changelog κεντραρίστηκαν επάνω στην κάθετη γραμμή και η έκδοση εμφανίζεται ως απλό κείμενο δεξιά από κάθε κύκλο.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.2.4</strong></div><ul><li>Το off-canvas sidebar στο κινητό εμφανίζει πλέον πάντα τόσο τα εικονίδια όσο και τους τίτλους των επιλογών.</li><li>Διορθώθηκε η θέση και το responsive μέγεθος του burger button ώστε να παραμένει πλήρως μέσα στην οθόνη.</li><li>Το Changelog έγινε πλήρους πλάτους, ευθυγραμμισμένο με το πλαίσιο τίτλου, και ανανεώθηκε αισθητικά.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.2.3</strong></div><ul><li>Διορθώθηκε η δυναμική εμφάνιση πεδίων στη Νέα Παραγγελία ώστε να ακολουθεί πιστά τα dependencies της επιλεγμένης φόρμας.</li><li>Τα υποχρεωτικά πεδία ενεργοποιούνται μόνο όταν είναι ορατά.</li><li>Η διάταξη πεδίων του admin χρησιμοποιεί ανεξάρτητο responsive grid και όχι classes του frontend theme.</li><li>Προστέθηκε sticky mobile header με λογότυπο και burger menu.</li><li>Ανασχεδιάστηκε αισθητικά η σελίδα Changelog.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.2.2</strong></div><ul><li>Προστέθηκε ομαλό stagger animation στους χαρακτήρες του tooltip των γραφημάτων.</li><li>Το Changelog μεταφέρθηκε σε ανεξάρτητη σελίδα και εμφανίζεται ως ξεχωριστή επιλογή στο sidebar.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.2.1</strong></div><ul><li>Βελτιώθηκε η στοίχιση και η εμφάνιση του footer στο sidebar.</li><li>Μόνο η λέξη Dazzlink αποτελεί σύνδεσμο, χωρίς background στο hover.</li><li>Η Έξοδος κεντραρίστηκε και στο hover εφαρμόζει μόνο διακριτικό scale up.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.2.0</strong></div><ul><li>Μη καταστροφικά database migrations και προστασία προϊόντων, τιμών ημέρας και ιστορικού κατά τα updates.</li><li>Πλήρως responsive manager με burger menu και off-canvas sidebar.</li><li>Νέο footer sidebar με Dazzlink link και ασφαλές WordPress logout.</li><li>Η γραμματοσειρά του manager ορίστηκε σε Geom με fallback στη γραμματοσειρά της σελίδας.</li><li>Διαφανές/inherit φόντο για τα δημόσια γραφήματα και προαιρετικό light mode.</li><li>Νέα Παραγγελία από admin με δυναμικά πεδία, required validation και dependencies από τον Form Builder.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.1.4</strong></div><ul><li>Επανήλθε ο οπτικός επιλογέας χρώματος στις ρυθμίσεις εμφάνισης της φόρμας.</li><li>Προστέθηκε ξεχωριστός slider opacity, με αυτόματη αποθήκευση σε μορφή RGBA.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.1.3</strong></div><ul><li>Προστέθηκε υποστήριξη χρωμάτων RGBA με opacity στις ρυθμίσεις εμφάνισης των φορμών.</li><li>Προστέθηκε Import μεμονωμένης φόρμας από το JSON αρχείο που δημιουργεί το αντίστοιχο Export.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.1.2</strong></div><ul><li>Αφαιρέθηκε το ανεπιθύμητο οριζόντιο scroll από το κάτω μέρος του sidebar.</li><li>Το sidebar χρησιμοποιεί πλέον αποκλειστικά κατακόρυφη κύλιση όταν το περιεχόμενο ξεπερνά το διαθέσιμο ύψος.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.1.1</strong></div><ul><li>Προστέθηκε κουμπί Export σε κάθε φόρμα για λήψη μεμονωμένου JSON backup της διαμόρφωσής της.</li><li>Το αρχείο περιλαμβάνει πεδία, dependencies, responsive πλάτη και ρυθμίσεις απάντησης μετά την υποβολή, με checksum ακεραιότητας.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.1.0</strong></div><ul><li>Προστέθηκε πλήρες τοπικό Backup / Restore για ρυθμίσεις, φόρμες, τιμές, ιστορικό και παραγγελίες.</li><li>Τα backup αρχεία ελέγχονται με checksum πριν από την επαναφορά και δεν αποθηκεύονται στον server.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.0.4</strong></div><ul><li>Διορθώθηκε η θέση του κουμπιού σύμπτυξης όταν το sidebar είναι κλειστό, ώστε να ακολουθεί ακριβώς την ίδια στήλη στοίχισης με τα υπόλοιπα εικονίδια.</li><li>Αφαιρέθηκε κάθε παλιό absolute positioning και μετατόπιση του κουμπιού στη συμπτυγμένη προβολή.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.0.3</strong></div><ul><li>Διορθώθηκε οριστικά η οριζόντια στοίχιση του εικονιδίου σύμπτυξης με τα υπόλοιπα εικονίδια όταν το sidebar είναι κλειστό.</li><li>Το κουμπί σύμπτυξης παραμένει πλήρως διαφανές σε hover και focus, χωρίς background.</li><li>Ενημερώθηκε το ενσωματωμένο changelog της τεκμηρίωσης με τα builds 4.0.2 και 4.0.3.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.0.2</strong></div><ul><li>Βελτιώθηκε η στοίχιση του εικονιδίου σύμπτυξης στη συμπτυγμένη προβολή.</li><li>Αφαιρέθηκε το background στο hover και στο focus του κουμπιού σύμπτυξης.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.0.1</strong></div><ul><li>Βελτιώθηκε η συμπτυγμένη εμφάνιση του sidebar και το ενεργό εικονίδιο.</li><li>Ανασχεδιάστηκε το κουμπί σύμπτυξης με κίνηση κατεύθυνσης και εμφάνιση της ετικέτας στο hover.</li><li>Ενοποιήθηκε το πλάτος της κεφαλίδας του Form Builder με τα υπόλοιπα panels.</li><li>Προστέθηκε ορατή ενότητα changelog στην τεκμηρίωση.</li></ul></article><article class="grfuel-changelog-release"><div class="grfuel-changelog-version"><strong>4.0.0</strong></div><ul><li>Πρώτη σταθερή έκδοση του GRFuel Manager.</li><li>Form Builder με responsive πλάτη, dependencies και εξαρτώμενα πεδία τόπου παράδοσης.</li><li>Διαχείριση παραγγελιών, XLSX/PDF exports, ειδοποιήσεις και μη αναγνωσμένο badge.</li><li>Τιμές Ημέρας, ιστορικό και ξεχωριστά desktop/mobile γραφήματα.</li></ul></article></div><p class="grfuel-changelog-note">Το πλήρες ιστορικό αλλαγών περιλαμβάνεται επίσης στο αρχείο <code>CHANGELOG.md</code> του plugin.</p></section>
            </main>
        </div>
        <?php
    }

    public function register_settings(): void {
        register_setting( 'sfv_settings_group', self::OPTION, array( 'sanitize_callback' => array( $this, 'sanitize_settings' ), 'default' => array() ) );
    }

    public function manager_settings_capability(): string {
        return self::CAPABILITY;
    }

    public function sanitize_settings( $input ): array {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return $this->settings();
        }
        $defaults = $this->settings();
        $font_keys = array( 'inherit', 'system', 'arial', 'georgia', 'monospace' );
        $weights = array( '300', '400', '500', '600', '700', '800' );
        $color = static function( $value, $fallback ) {
            $value = trim( (string) $value );
            $clean = sanitize_hex_color( $value );
            if ( $clean ) return $clean;
            if ( preg_match( '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*((?:0(?:\.\d+)?)|(?:1(?:\.0+)?))\s*\)$/i', $value, $matches ) ) {
                $red = min( 255, (int) $matches[1] );
                $green = min( 255, (int) $matches[2] );
                $blue = min( 255, (int) $matches[3] );
                $alpha = max( 0, min( 1, (float) $matches[4] ) );
                $alpha_text = rtrim( rtrim( number_format( $alpha, 3, '.', '' ), '0' ), '.' );
                return sprintf( 'rgba(%d, %d, %d, %s)', $red, $green, $blue, $alpha_text );
            }
            return $fallback;
        };
        $choice = static function( $value, array $allowed, $fallback ) { $value = sanitize_key( $value ); return in_array( $value, $allowed, true ) ? $value : $fallback; };
        $range = static function( $value, $min, $max, $fallback ) { $value = absint( $value ); return $value >= $min && $value <= $max ? $value : $fallback; };
        $input = is_array( $input ) ? $input : array();
        // The Settings screen does not post the products or chart options (they live on
        // Τιμές Ημέρας), so keep the stored values unless the submission carries them.
        $has_catalog = array_key_exists( 'daily_prices', $input );
        $has_chart = array_key_exists( 'chart_default_days', $input );
        $daily_prices = $has_catalog ? array() : (array) ( $defaults['daily_prices'] ?? array() );
        foreach ( $has_catalog ? array_slice( (array) $input['daily_prices'], 0, 20 ) : array() as $row ) {
            if ( ! is_array( $row ) ) continue;
            $name = mb_substr( sanitize_text_field( $row['name'] ?? '' ), 0, 100 );
            $slug = sanitize_title( $row['slug'] ?? $name );
            $price = max( 0, (float) str_replace( ',', '.', (string) ( $row['price'] ?? 0 ) ) );
            $unit = mb_substr( sanitize_text_field( $row['unit'] ?? 'λίτρο' ), 0, 40 );
            if ( '' !== $name && '' !== $slug ) $daily_prices[] = array( 'name' => $name, 'slug' => $slug, 'price' => number_format( $price, 3, '.', '' ), 'unit' => $unit ?: 'λίτρο' );
        }
        $stored_settings = (array) get_option( self::OPTION, array() );
        $submitted_secret_key = trim( (string) ( $input['secret_key'] ?? '' ) );
        $turnstile_secret_key = '' !== $submitted_secret_key ? sanitize_text_field( $submitted_secret_key ) : sanitize_text_field( $stored_settings['secret_key'] ?? '' );
        $submitted_api_key = trim( (string) ( $input['onesignal_api_key'] ?? '' ) );
        $onesignal_api_key = '' !== $submitted_api_key ? sanitize_text_field( $submitted_api_key ) : sanitize_text_field( $stored_settings['onesignal_api_key'] ?? '' );
        return array(
            'site_key' => sanitize_text_field( $input['site_key'] ?? '' ),
            'secret_key' => $turnstile_secret_key,
            'rate_limit' => max( 1, min( 100, absint( $input['rate_limit'] ?? 5 ) ) ),
            'retention_days' => max( 0, min( 3650, absint( $input['retention_days'] ?? 0 ) ) ),
            'manager_session_minutes' => max( 15, min( 10080, absint( $input['manager_session_minutes'] ?? 480 ) ) ),
            'label_color' => $color( $input['label_color'] ?? '', $defaults['label_color'] ),
            'label_font' => $choice( $input['label_font'] ?? '', $font_keys, 'inherit' ),
            'label_size' => $range( $input['label_size'] ?? 14, 10, 32, 14 ),
            'label_weight' => $choice( (string) ( $input['label_weight'] ?? '600' ), $weights, '600' ),
            'placeholder_color' => $color( $input['placeholder_color'] ?? '', $defaults['placeholder_color'] ),
            'placeholder_font' => $choice( $input['placeholder_font'] ?? '', $font_keys, 'inherit' ),
            'placeholder_size' => $range( $input['placeholder_size'] ?? 14, 10, 32, 14 ),
            'placeholder_weight' => $choice( (string) ( $input['placeholder_weight'] ?? '400' ), $weights, '400' ),
            'field_text_color' => $color( $input['field_text_color'] ?? '', $defaults['field_text_color'] ),
            'field_background' => $color( $input['field_background'] ?? '', $defaults['field_background'] ),
            'field_border_color' => $color( $input['field_border_color'] ?? '', $defaults['field_border_color'] ),
            'field_hover_text_color' => $color( $input['field_hover_text_color'] ?? '', $defaults['field_hover_text_color'] ),
            'field_hover_background' => $color( $input['field_hover_background'] ?? '', $defaults['field_hover_background'] ),
            'field_hover_border_color' => $color( $input['field_hover_border_color'] ?? '', $defaults['field_hover_border_color'] ),
            'field_focus_text_color' => $color( $input['field_focus_text_color'] ?? '', $defaults['field_focus_text_color'] ),
            'field_focus_background' => $color( $input['field_focus_background'] ?? '', $defaults['field_focus_background'] ),
            'field_focus_color' => $color( $input['field_focus_color'] ?? '', $defaults['field_focus_color'] ),
            'field_focus_outline_color' => $color( $input['field_focus_outline_color'] ?? '', $defaults['field_focus_outline_color'] ),
            'field_active_text_color' => $color( $input['field_active_text_color'] ?? '', $defaults['field_active_text_color'] ),
            'field_active_background' => $color( $input['field_active_background'] ?? '', $defaults['field_active_background'] ),
            'field_active_border_color' => $color( $input['field_active_border_color'] ?? '', $defaults['field_active_border_color'] ),
            'field_font' => $choice( $input['field_font'] ?? '', $font_keys, 'inherit' ),
            'field_size' => $range( $input['field_size'] ?? 14, 10, 32, 14 ),
            'field_weight' => $choice( (string) ( $input['field_weight'] ?? '400' ), $weights, '400' ),
            'field_height' => $range( $input['field_height'] ?? 44, 32, 72, 44 ),
            'field_radius' => $range( $input['field_radius'] ?? 8, 0, 32, 8 ),
            'submit_text_color' => $color( $input['submit_text_color'] ?? '', $defaults['submit_text_color'] ),
            'submit_background' => $color( $input['submit_background'] ?? '', $defaults['submit_background'] ),
            'submit_hover_background' => $color( $input['submit_hover_background'] ?? '', $defaults['submit_hover_background'] ),
            'submit_font' => $choice( $input['submit_font'] ?? '', $font_keys, 'inherit' ),
            'submit_size' => $range( $input['submit_size'] ?? 14, 10, 32, 14 ),
            'submit_weight' => $choice( (string) ( $input['submit_weight'] ?? '700' ), $weights, '700' ),
            'submit_height' => $range( $input['submit_height'] ?? 44, 32, 72, 44 ),
            'submit_radius' => $range( $input['submit_radius'] ?? 8, 0, 32, 8 ),
            'pdf_brand_name' => sanitize_text_field( $input['pdf_brand_name'] ?? get_bloginfo( 'name' ) ),
            'pdf_logo_url' => esc_url_raw( $input['pdf_logo_url'] ?? '' ),
            'pdf_watermark_url' => esc_url_raw( $input['pdf_watermark_url'] ?? '' ),
            'pdf_watermark_opacity' => $range( $input['pdf_watermark_opacity'] ?? 12, 0, 100, 12 ),
            'pdf_primary' => $color( $input['pdf_primary'] ?? '', $defaults['pdf_primary'] ),
            'pdf_accent' => $color( $input['pdf_accent'] ?? '', $defaults['pdf_accent'] ),
            'pdf_text' => $color( $input['pdf_text'] ?? '', $defaults['pdf_text'] ),
            'pdf_muted' => $color( $input['pdf_muted'] ?? '', $defaults['pdf_muted'] ),
            'pdf_surface' => $color( $input['pdf_surface'] ?? '', $defaults['pdf_surface'] ),
            'daily_prices' => $daily_prices,
            'notification_enabled' => empty( $input['notification_enabled'] ) ? 0 : 1,
            'notification_sound' => empty( $input['notification_sound'] ) ? 0 : 1,
            'notification_desktop' => empty( $input['notification_desktop'] ) ? 0 : 1,
            'notification_email' => empty( $input['notification_email'] ) ? 0 : 1,
            'notification_email_address' => sanitize_email( $input['notification_email_address'] ?? get_option( 'admin_email' ) ),
            'notification_interval' => max( 15, min( 300, absint( $input['notification_interval'] ?? 30 ) ) ),
            'notification_sound_choice' => in_array( sanitize_key( $input['notification_sound_choice'] ?? 'bell' ), array( 'bell','soft','digital','chime' ), true ) ? sanitize_key( $input['notification_sound_choice'] ?? 'bell' ) : 'bell',
            'push_enabled' => empty( $input['push_enabled'] ) ? 0 : 1,
            'onesignal_app_id' => sanitize_text_field( $input['onesignal_app_id'] ?? '' ),
            'onesignal_api_key' => $onesignal_api_key,
            'preview_theme' => 'dark' === sanitize_key( $input['preview_theme'] ?? 'light' ) ? 'dark' : 'light',
            'chart_default_days' => max( 7, min( 3650, absint( $has_chart ? $input['chart_default_days'] : ( $defaults['chart_default_days'] ?? 30 ) ) ) ),
            'chart_max_days' => max( 7, min( 3650, absint( $has_chart ? ( $input['chart_max_days'] ?? 365 ) : ( $defaults['chart_max_days'] ?? 365 ) ) ) ),
            'chart_frontend_controls' => empty( $has_chart ? ( $input['chart_frontend_controls'] ?? 0 ) : ( $defaults['chart_frontend_controls'] ?? 0 ) ) ? 0 : 1,
            'chart_background' => 'light' === sanitize_key( $has_chart ? ( $input['chart_background'] ?? 'transparent' ) : ( $defaults['chart_background'] ?? 'transparent' ) ) ? 'light' : 'transparent',
        );
    }

    public function settings_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) ); }
        $s = $this->settings();
        $configured = '' !== $s['site_key'] && '' !== $s['secret_key'];
        $font_options = array( 'inherit' => 'Theme / YOOtheme', 'system' => 'System UI', 'arial' => 'Arial', 'georgia' => 'Georgia', 'monospace' => 'Monospace' );
        $weight_options = array( '300', '400', '500', '600', '700', '800' );
        ?>
        <div class="wrap sfv-admin-page sfv-settings-page">
            <?php $this->admin_nav( 'settings' ); ?>
            <?php $this->section_intro(
                'Ρυθμίσεις',
                'Ρυθμίζουμε την ασφάλεια και την εμφάνιση όλων των φορμών από ένα οργανωμένο περιβάλλον.',
                array()
            ); ?>
            <form class="sfv-settings-workspace" method="post" action="options.php">
                <?php settings_fields( 'sfv_settings_group' ); ?>
                <div class="sfv-settings-left-column">
                <aside class="sfv-settings-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'secure-form-vault' ); ?>">
                    <div class="sfv-settings-nav-title"><span class="dashicons dashicons-admin-generic"></span><div><strong><?php esc_html_e( 'Ρυθμίσεις φόρμας', 'secure-form-vault' ); ?></strong><small><?php esc_html_e( 'Επιλέγουμε κατηγορία', 'secure-form-vault' ); ?></small></div></div>
                    <button type="button" class="sfv-settings-tab is-active" data-settings-tab="security"><span class="dashicons dashicons-shield-alt"></span><span><?php esc_html_e( 'Ασφάλεια', 'secure-form-vault' ); ?></span><i class="sfv-status-dot <?php echo $configured ? 'is-success' : 'is-warning'; ?>"></i></button>
                    <button type="button" class="sfv-settings-tab" data-settings-tab="pdf-branding"><span class="dashicons dashicons-art"></span><span><?php esc_html_e( 'PDF Branding', 'secure-form-vault' ); ?></span></button>
                    <button type="button" class="sfv-settings-tab" data-settings-tab="notifications"><span class="dashicons dashicons-bell"></span><span><?php esc_html_e( 'Ειδοποιήσεις', 'secure-form-vault' ); ?></span></button>
                    <button type="button" class="sfv-settings-tab" data-settings-tab="forms"><span class="dashicons dashicons-editor-textcolor"></span><span><?php esc_html_e( 'Φόρμες', 'secure-form-vault' ); ?></span></button>
                    <div class="sfv-settings-save-mobile"><button type="submit" class="sfv-btn sfv-btn-primary sfv-btn-block"><?php esc_html_e( 'Αποθήκευση', 'secure-form-vault' ); ?></button></div>
                </aside>


                <aside class="sfv-settings-preview-panel">
                    <div class="sfv-settings-preview-heading">
                        <div><span class="sfv-settings-kicker"><?php esc_html_e( 'Live preview', 'secure-form-vault' ); ?></span><h3><?php esc_html_e( 'Προεπισκόπηση φόρμας', 'secure-form-vault' ); ?></h3></div>
                        <div class="sfv-preview-theme-switch" role="group" aria-label="<?php esc_attr_e( 'Θέμα προεπισκόπησης', 'secure-form-vault' ); ?>">
                            <label class="<?php echo 'light' === $s['preview_theme'] ? 'is-active' : ''; ?>"><input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[preview_theme]" value="light" <?php checked( $s['preview_theme'], 'light' ); ?>><span class="dashicons dashicons-sun"></span><span><?php esc_html_e( 'Φωτεινό', 'secure-form-vault' ); ?></span></label>
                            <label class="<?php echo 'dark' === $s['preview_theme'] ? 'is-active' : ''; ?>"><input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[preview_theme]" value="dark" <?php checked( $s['preview_theme'], 'dark' ); ?>><span class="dashicons dashicons-moon"></span><span><?php esc_html_e( 'Σκοτεινό', 'secure-form-vault' ); ?></span></label>
                        </div>
                    </div>
                    <div class="sfv-live-preview is-<?php echo esc_attr( $s['preview_theme'] ); ?>" id="sfv-live-preview">
                        <label class="sfv-preview-live-label"><?php esc_html_e( 'Ονοματεπώνυμο', 'secure-form-vault' ); ?> <em>*</em></label>
                        <input class="sfv-preview-live-field" type="text" readonly placeholder="<?php esc_attr_e( 'Συμπληρώνουμε το ονοματεπώνυμο', 'secure-form-vault' ); ?>" disabled>
                        <label class="sfv-preview-live-label"><?php esc_html_e( 'Μήνυμα', 'secure-form-vault' ); ?></label>
                        <textarea class="sfv-preview-live-field" readonly placeholder="<?php esc_attr_e( 'Γράφουμε το μήνυμα', 'secure-form-vault' ); ?>" disabled></textarea>
                        <button class="sfv-preview-live-submit" type="button"><?php esc_html_e( 'Αποστολή', 'secure-form-vault' ); ?></button>
                    </div>
                    <p class="sfv-preview-caption"><?php esc_html_e( 'Η προεπισκόπηση ενημερώνεται άμεσα χωρίς αποθήκευση.', 'secure-form-vault' ); ?></p>
                </aside>
                </div>

                <main class="sfv-settings-main">
                    <section class="sfv-settings-pane is-active" data-settings-pane="security">
                        <div class="sfv-settings-pane-header"><div><span class="sfv-settings-kicker"><?php esc_html_e( 'Security', 'secure-form-vault' ); ?></span><h2><?php esc_html_e( 'Cloudflare Turnstile', 'secure-form-vault' ); ?></h2><p><?php esc_html_e( 'Προστατεύουμε τις φόρμες από bots και περιορίζουμε επαναλαμβανόμενες υποβολές.', 'secure-form-vault' ); ?></p></div><span class="sfv-status <?php echo $configured ? 'is-success' : 'is-warning'; ?>"><?php echo esc_html( $configured ? __( 'Configured', 'secure-form-vault' ) : __( 'Action required', 'secure-form-vault' ) ); ?></span></div>
                        <div class="sfv-settings-form-grid sfv-settings-form-grid-security">
                            <label class="sfv-setting-control sfv-setting-control-wide"><span><?php esc_html_e( 'Site key', 'secure-form-vault' ); ?></span><input type="text" id="sfv_site_key" name="<?php echo esc_attr( self::OPTION ); ?>[site_key]" value="<?php echo esc_attr( $s['site_key'] ); ?>" autocomplete="off"><small><?php esc_html_e( 'Το δημόσιο κλειδί που χρησιμοποιείται από το Turnstile widget.', 'secure-form-vault' ); ?></small></label>
                            <label class="sfv-setting-control sfv-setting-control-wide"><span><?php esc_html_e( 'Secret key', 'secure-form-vault' ); ?></span><input type="password" autocomplete="new-password" id="sfv_secret_key" name="<?php echo esc_attr( self::OPTION ); ?>[secret_key]" value="" placeholder="<?php echo empty( $s['secret_key'] ) ? esc_attr__( 'Enter the Turnstile secret', 'secure-form-vault' ) : esc_attr__( 'Saved — leave blank to keep it', 'secure-form-vault' ); ?>"><small><?php esc_html_e( 'Χρησιμοποιείται αποκλειστικά για server-side verification.', 'secure-form-vault' ); ?></small></label>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Υποβολές ανά IP / ώρα', 'secure-form-vault' ); ?></span><input type="number" min="1" max="100" id="sfv_rate_limit" name="<?php echo esc_attr( self::OPTION ); ?>[rate_limit]" value="<?php echo absint( $s['rate_limit'] ); ?>"><small><?php esc_html_e( 'Αποθηκεύεται ανώνυμο HMAC hash και όχι η πραγματική IP.', 'secure-form-vault' ); ?></small></label>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Αυτόματη διατήρηση (ημέρες)', 'secure-form-vault' ); ?></span><input type="number" min="0" max="3650" name="<?php echo esc_attr( self::OPTION ); ?>[retention_days]" value="<?php echo absint( $s['retention_days'] ); ?>"><small><?php esc_html_e( '0 = διατήρηση χωρίς αυτόματη διαγραφή. Οι παλαιότερες υποβολές διαγράφονται καθημερινά.', 'secure-form-vault' ); ?></small></label>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Διάρκεια συνεδρίας Manager', 'secure-form-vault' ); ?></span><div class="sfv-unit-input"><input type="number" min="15" max="10080" step="15" name="<?php echo esc_attr( self::OPTION ); ?>[manager_session_minutes]" value="<?php echo absint( $s['manager_session_minutes'] ); ?>"><span>λεπτά</span></div><small><?php esc_html_e( 'Από 15 λεπτά έως 7 ημέρες. Το floating κουμπί επαναφέρει αυτή τη διάρκεια από την αρχή.', 'secure-form-vault' ); ?></small></label>
                        </div>
                        <div class="sfv-settings-note"><span class="dashicons dashicons-lock"></span><div><strong><?php esc_html_e( 'Πολυεπίπεδη προστασία', 'secure-form-vault' ); ?></strong><p><?php esc_html_e( 'Το Turnstile λειτουργεί συμπληρωματικά με honeypot, timing checks και rate limiting.', 'secure-form-vault' ); ?></p></div></div>
                    </section>

                    <section class="sfv-settings-pane" data-settings-pane="pdf-branding">
                        <div class="sfv-settings-pane-header"><div><span class="sfv-settings-kicker"><?php esc_html_e( 'PDF exports', 'secure-form-vault' ); ?></span><h2><?php esc_html_e( 'Branding PDF', 'secure-form-vault' ); ?></h2><p><?php esc_html_e( 'Ορίζουμε το λογότυπο και τη χρωματική παλέτα που χρησιμοποιούνται στα PDF των υποβολών.', 'secure-form-vault' ); ?></p></div></div>
                        <div class="sfv-settings-form-grid">
                            <label class="sfv-setting-control sfv-setting-control-wide"><span><?php esc_html_e( 'Επωνυμία', 'secure-form-vault' ); ?></span><input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[pdf_brand_name]" value="<?php echo esc_attr( $s['pdf_brand_name'] ); ?>" maxlength="120"></label>
                            <label class="sfv-setting-control sfv-setting-control-wide"><span><?php esc_html_e( 'Logo URL (PNG ή SVG)', 'secure-form-vault' ); ?></span><div class="sfv-media-control"><input id="sfv_pdf_logo_url" type="url" name="<?php echo esc_attr( self::OPTION ); ?>[pdf_logo_url]" value="<?php echo esc_attr( $s['pdf_logo_url'] ); ?>" placeholder="https://example.gr/logo.svg"><button type="button" class="sfv-btn sfv-btn-secondary" id="sfv_select_pdf_logo"><?php esc_html_e( 'Επιλογή logo', 'secure-form-vault' ); ?></button><button type="button" class="sfv-btn sfv-btn-ghost" id="sfv_clear_pdf_logo"><?php esc_html_e( 'Καθαρισμός', 'secure-form-vault' ); ?></button></div><small><?php esc_html_e( 'Μπορεί να επιλεγεί εικόνα από τη βιβλιοθήκη πολυμέσων ή να καταχωριστεί ασφαλές URL PNG/SVG.', 'secure-form-vault' ); ?></small></label>
                            <label class="sfv-setting-control sfv-setting-control-wide"><span><?php esc_html_e( 'Υδατογράφημα PDF (PNG ή SVG)', 'secure-form-vault' ); ?></span><div class="sfv-media-control"><input id="sfv_pdf_watermark_url" type="url" name="<?php echo esc_attr( self::OPTION ); ?>[pdf_watermark_url]" value="<?php echo esc_attr( $s['pdf_watermark_url'] ); ?>" placeholder="https://example.gr/watermark.svg"><button type="button" class="sfv-btn sfv-btn-secondary" id="sfv_select_pdf_watermark"><?php esc_html_e( 'Επιλογή εικόνας', 'secure-form-vault' ); ?></button><button type="button" class="sfv-btn sfv-btn-ghost" id="sfv_clear_pdf_watermark"><?php esc_html_e( 'Καθαρισμός', 'secure-form-vault' ); ?></button></div><small><?php esc_html_e( 'Το υδατογράφημα τοποθετείται στο κέντρο κάθε σελίδας PDF και διατηρεί τις αναλογίες του.', 'secure-form-vault' ); ?></small></label>
                            <label class="sfv-setting-control sfv-setting-control-wide sfv-watermark-opacity"><span><?php esc_html_e( 'Opacity υδατογραφήματος', 'secure-form-vault' ); ?> <strong id="sfv_pdf_watermark_opacity_value"><?php echo absint( $s['pdf_watermark_opacity'] ); ?>%</strong></span><input id="sfv_pdf_watermark_opacity" type="range" min="0" max="100" step="1" name="<?php echo esc_attr( self::OPTION ); ?>[pdf_watermark_opacity]" value="<?php echo absint( $s['pdf_watermark_opacity'] ); ?>"></label>
                            <?php foreach ( array( 'pdf_primary' => 'Κύριο χρώμα', 'pdf_accent' => 'Χρώμα έμφασης', 'pdf_text' => 'Χρώμα κειμένου', 'pdf_muted' => 'Δευτερεύον κείμενο', 'pdf_surface' => 'Φόντο σελίδας' ) as $key => $label ) : ?>
                                <label class="sfv-setting-control"><span><?php echo esc_html( $label ); ?></span><div class="sfv-color-control"><input type="color" name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>"><code><?php echo esc_html( $s[ $key ] ); ?></code></div></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="sfv-settings-note"><span class="dashicons dashicons-info-outline"></span><div><strong><?php esc_html_e( 'Branding του οργανισμού', 'secure-form-vault' ); ?></strong><p><?php esc_html_e( 'Το λογότυπο Dazzlink παραμένει μόνο ως αναφορά δημιουργού του plugin και δεν χρησιμοποιείται πλέον ως κύριο λογότυπο στα PDF.', 'secure-form-vault' ); ?></p></div></div>
                    </section>

                    <section class="sfv-settings-pane" data-settings-pane="notifications">
                        <div class="sfv-settings-pane-header"><div><span class="sfv-settings-kicker">Νέες παραγγελίες</span><h2>Ειδοποιήσεις νέων παραγγελιών</h2><p>Ενεργοποιούμε browser polling, ήχο, desktop notification και email fallback.</p></div></div>
                        <div class="grfuel-notification-grid">
                            <label class="grfuel-toggle-card"><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[notification_enabled]" value="1" <?php checked(!empty($s['notification_enabled'])); ?>><span><strong>Ζωντανός έλεγχος παραγγελιών</strong><small>Ελέγχει για νέες εγγραφές όσο υπάρχει ανοιχτό WordPress admin tab.</small></span></label>
                            <label class="grfuel-toggle-card"><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[notification_sound]" value="1" <?php checked(!empty($s['notification_sound'])); ?>><span><strong>Ηχητική ειδοποίηση</strong><small>Απαιτεί μία αρχική αλληλεπίδραση με τη σελίδα λόγω των browser autoplay κανόνων.</small></span></label>
                            <label class="sfv-setting-control grfuel-sound-picker"><span>Ήχος ειδοποίησης</span><div><select name="<?php echo esc_attr(self::OPTION); ?>[notification_sound_choice]" id="grfuel-notification-sound"><?php foreach ( array( 'bell'=>'Καμπανάκι', 'soft'=>'Απαλός τόνος', 'digital'=>'Ψηφιακό σήμα', 'chime'=>'Διπλό κουδούνισμα' ) as $sound_key=>$sound_label ) : ?><option value="<?php echo esc_attr($sound_key); ?>" <?php selected($s['notification_sound_choice'] ?? 'bell',$sound_key); ?>><?php echo esc_html($sound_label); ?></option><?php endforeach; ?></select><button type="button" class="sfv-btn sfv-btn-secondary" id="grfuel-preview-sound">Ακρόαση</button></div></label>
                            <label class="grfuel-toggle-card"><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[notification_desktop]" value="1" <?php checked(!empty($s['notification_desktop'])); ?>><span><strong>Ειδοποίηση επιφάνειας εργασίας</strong><small>Ο browser θα ζητήσει άδεια την πρώτη φορά.</small></span></label>
                            <label class="grfuel-toggle-card"><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[notification_email]" value="1" <?php checked(!empty($s['notification_email'])); ?>><span><strong>Ειδοποίηση μέσω email</strong><small>Λειτουργεί ακόμη και όταν το admin ή ο browser είναι κλειστός.</small></span></label>
                            <label class="sfv-setting-control"><span>Email ειδοποιήσεων</span><input type="email" name="<?php echo esc_attr(self::OPTION); ?>[notification_email_address]" value="<?php echo esc_attr($s['notification_email_address']); ?>"></label>
                            <label class="sfv-setting-control"><span>Έλεγχος κάθε</span><div class="sfv-unit-input"><input type="number" min="15" max="300" name="<?php echo esc_attr(self::OPTION); ?>[notification_interval]" value="<?php echo absint($s['notification_interval']); ?>"><span>sec</span></div></label>
                            <div class="grfuel-push-panel" id="grfuel-push-panel">
                                <div class="grfuel-push-panel-head"><div><span class="dashicons dashicons-smartphone"></span><div><strong>Push στο κινητό με κλειστό browser</strong><small>Μόνο για συνδεδεμένους διαχειριστές του GRFuel Manager.</small></div></div><span class="grfuel-push-badge">OneSignal</span></div>
                                <label class="grfuel-toggle-card"><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[push_enabled]" value="1" <?php checked(!empty($s['push_enabled'])); ?>><span><strong>Ενεργοποίηση mobile push</strong><small>Στέλνει ασφαλή ειδοποίηση χωρίς προσωπικά δεδομένα για κάθε νέα παραγγελία.</small></span></label>
                                <div class="sfv-settings-form-grid grfuel-push-credentials">
                                    <label class="sfv-setting-control"><span>OneSignal App ID</span><input type="text" autocomplete="off" name="<?php echo esc_attr(self::OPTION); ?>[onesignal_app_id]" value="<?php echo esc_attr($s['onesignal_app_id']); ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></label>
                                    <label class="sfv-setting-control"><span>OneSignal App API Key</span><input type="password" autocomplete="new-password" name="<?php echo esc_attr(self::OPTION); ?>[onesignal_api_key]" value="" placeholder="<?php echo empty($s['onesignal_api_key']) ? 'Key από Settings → Keys & IDs' : 'Αποθηκευμένο — αφήστε κενό για διατήρηση'; ?>"><small>Το key αποθηκεύεται μόνο στον WordPress server και δεν αποστέλλεται ποτέ στον browser.</small></label>
                                </div>
                                <div class="grfuel-push-device-status" id="grfuel-push-device-status" role="status" aria-live="polite"><span class="grfuel-browser-status-dot"></span><div><strong>Αυτή η συσκευή</strong><small id="grfuel-push-status-text">Αναμονή ρύθμισης…</small></div></div>
                                <div class="grfuel-browser-notification-actions">
                                    <button type="button" class="sfv-btn sfv-btn-primary" id="grfuel-enable-push"><span class="dashicons dashicons-smartphone"></span>Σύνδεση αυτής της συσκευής</button>
                                    <button type="button" class="sfv-btn sfv-btn-secondary" id="grfuel-test-push"><span class="dashicons dashicons-megaphone"></span>Αποστολή δοκιμής</button>
                                </div>
                                <p class="grfuel-browser-notification-help" id="grfuel-push-help">Αποθηκεύστε πρώτα το App ID και το API Key. Σε iPhone ανοίξτε τον Manager από εικονίδιο στην αρχική οθόνη.</p>
                            </div>
                            <div class="grfuel-browser-notification-panel" id="grfuel-browser-notification-panel">
                                <div class="grfuel-browser-notification-status" id="grfuel-browser-notification-status" role="status" aria-live="polite">
                                    <span class="grfuel-browser-status-dot" aria-hidden="true"></span>
                                    <div><strong>Ειδοποιήσεις browser</strong><small id="grfuel-browser-notification-status-text">Έλεγχος κατάστασης…</small></div>
                                </div>
                                <div class="grfuel-browser-notification-actions">
                                    <button type="button" class="sfv-btn sfv-btn-secondary" id="grfuel-enable-browser-notifications"><span class="dashicons dashicons-bell"></span>Ενεργοποίηση</button>
                                    <button type="button" class="sfv-btn sfv-btn-secondary" id="grfuel-test-browser-notification"><span class="dashicons dashicons-megaphone"></span>Δοκιμαστική ειδοποίηση</button>
                                </div>
                                <p class="grfuel-browser-notification-help" id="grfuel-browser-notification-help"></p>
                            </div>
                        </div>
                        <div class="sfv-settings-note"><span class="dashicons dashicons-info-outline"></span><div><strong>Όριο browser</strong><p>Ήχος και desktop notification απαιτούν ανοιχτό browser με τουλάχιστον ένα admin tab. Για ενημέρωση με κλειστό browser χρησιμοποιούμε το email fallback. Το mobile push μέσω OneSignal λειτουργεί και με κλειστό browser. Σε iPhone απαιτείται εγκατάσταση του Manager στην αρχική οθόνη.</p></div></div>
                    </section>

                    <section class="sfv-settings-pane" data-settings-pane="forms">
                        <div class="sfv-settings-pane-header"><div><span class="sfv-settings-kicker"><?php esc_html_e( 'Τυπογραφία', 'secure-form-vault' ); ?></span><h2><?php esc_html_e( 'Ετικέτες', 'secure-form-vault' ); ?></h2><p><?php esc_html_e( 'Ορίζουμε την εμφάνιση των τίτλων που εμφανίζονται πάνω από τα πεδία.', 'secure-form-vault' ); ?></p></div></div>
                        <div class="sfv-settings-form-grid">
                            <label class="sfv-setting-control sfv-color-control"><span><?php esc_html_e( 'Χρώμα', 'secure-form-vault' ); ?></span><?php echo $this->rgba_picker_markup( 'label_color', (string) $s['label_color'], 'label-color' ); ?><small>Επιλέγουμε χρώμα και opacity.</small></label>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Γραμματοσειρά', 'secure-form-vault' ); ?></span><select data-preview="label-font" name="<?php echo esc_attr( self::OPTION ); ?>[label_font]"><?php foreach ( $font_options as $value => $text ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['label_font'], $value ); ?>><?php echo esc_html( $text ); ?></option><?php endforeach; ?></select></label>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Μέγεθος', 'secure-form-vault' ); ?></span><div class="sfv-unit-input"><input data-preview="label-size" type="number" min="10" max="32" name="<?php echo esc_attr( self::OPTION ); ?>[label_size]" value="<?php echo absint( $s['label_size'] ); ?>"><span>px</span></div></label>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Βάρος', 'secure-form-vault' ); ?></span><select data-preview="label-weight" name="<?php echo esc_attr( self::OPTION ); ?>[label_weight]"><?php foreach ( $weight_options as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['label_weight'], $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></label>
                        </div>
                    </section>

                    <section class="sfv-settings-pane" data-settings-pane="forms">
                        <div class="sfv-settings-pane-header"><div><span class="sfv-settings-kicker"><?php esc_html_e( 'Τυπογραφία', 'secure-form-vault' ); ?></span><h2><?php esc_html_e( 'Κείμενα πεδίων', 'secure-form-vault' ); ?></h2><p><?php esc_html_e( 'Ρυθμίζουμε το βοηθητικό κείμενο που εμφανίζεται μέσα στα inputs και στα dropdowns.', 'secure-form-vault' ); ?></p></div></div>
                        <div class="sfv-settings-form-grid">
                            <label class="sfv-setting-control sfv-color-control"><span><?php esc_html_e( 'Χρώμα', 'secure-form-vault' ); ?></span><?php echo $this->rgba_picker_markup( 'placeholder_color', (string) $s['placeholder_color'], 'placeholder-color' ); ?><small>Επιλέγουμε χρώμα και opacity.</small></label>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Γραμματοσειρά', 'secure-form-vault' ); ?></span><select data-preview="placeholder-font" name="<?php echo esc_attr( self::OPTION ); ?>[placeholder_font]"><?php foreach ( $font_options as $value => $text ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['placeholder_font'], $value ); ?>><?php echo esc_html( $text ); ?></option><?php endforeach; ?></select></label>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Μέγεθος', 'secure-form-vault' ); ?></span><div class="sfv-unit-input"><input data-preview="placeholder-size" type="number" min="10" max="32" name="<?php echo esc_attr( self::OPTION ); ?>[placeholder_size]" value="<?php echo absint( $s['placeholder_size'] ); ?>"><span>px</span></div></label>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Βάρος', 'secure-form-vault' ); ?></span><select data-preview="placeholder-weight" name="<?php echo esc_attr( self::OPTION ); ?>[placeholder_weight]"><?php foreach ( $weight_options as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['placeholder_weight'], $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></label>
                        </div>
                    </section>

                    <section class="sfv-settings-pane" data-settings-pane="forms">
                        <div class="sfv-settings-pane-header"><div><span class="sfv-settings-kicker"><?php esc_html_e( 'Στοιχεία φόρμας', 'secure-form-vault' ); ?></span><h2><?php esc_html_e( 'Πεδία', 'secure-form-vault' ); ?></h2><p><?php esc_html_e( 'Ορίζουμε την κανονική εμφάνιση και τις καταστάσεις hover, focus και active για inputs, selects και textarea.', 'secure-form-vault' ); ?></p></div></div>

                        <?php
                        $field_state_groups = array(
                            'default' => array(
                                'title' => __( 'Κανονική κατάσταση', 'secure-form-vault' ),
                                'description' => __( 'Η βασική εμφάνιση των πεδίων πριν από οποιαδήποτε αλληλεπίδραση.', 'secure-form-vault' ),
                                'colors' => array( 'field_text_color' => 'Κείμενο', 'field_background' => 'Φόντο', 'field_border_color' => 'Περίγραμμα' ),
                            ),
                            'hover' => array(
                                'title' => __( 'Hover', 'secure-form-vault' ),
                                'description' => __( 'Η εμφάνιση όταν ο δείκτης βρίσκεται επάνω στο πεδίο.', 'secure-form-vault' ),
                                'colors' => array( 'field_hover_text_color' => 'Κείμενο', 'field_hover_background' => 'Φόντο', 'field_hover_border_color' => 'Περίγραμμα' ),
                            ),
                            'focus' => array(
                                'title' => __( 'Focus', 'secure-form-vault' ),
                                'description' => __( 'Η εμφάνιση όταν το πεδίο είναι επιλεγμένο για πληκτρολόγηση.', 'secure-form-vault' ),
                                'colors' => array( 'field_focus_text_color' => 'Κείμενο', 'field_focus_background' => 'Φόντο', 'field_focus_color' => 'Περίγραμμα', 'field_focus_outline_color' => 'Εξωτερικό outline' ),
                            ),
                            'active' => array(
                                'title' => __( 'Active', 'secure-form-vault' ),
                                'description' => __( 'Η στιγμιαία εμφάνιση κατά το πάτημα ή την ενεργοποίηση του πεδίου.', 'secure-form-vault' ),
                                'colors' => array( 'field_active_text_color' => 'Κείμενο', 'field_active_background' => 'Φόντο', 'field_active_border_color' => 'Περίγραμμα' ),
                            ),
                        );
                        ?>
                        <div class="sfv-field-state-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Καταστάσεις πεδίων', 'secure-form-vault' ); ?>">
                            <?php foreach ( $field_state_groups as $state => $group ) : ?><button type="button" class="sfv-field-state-tab <?php echo 'default' === $state ? 'is-active' : ''; ?>" data-field-state-tab="<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $group['title'] ); ?></button><?php endforeach; ?>
                        </div>
                        <?php foreach ( $field_state_groups as $state => $group ) : ?>
                            <div class="sfv-field-state-panel <?php echo 'default' === $state ? 'is-active' : ''; ?>" data-field-state-panel="<?php echo esc_attr( $state ); ?>">
                                <div class="sfv-state-panel-heading"><strong><?php echo esc_html( $group['title'] ); ?></strong><span><?php echo esc_html( $group['description'] ); ?></span></div>
                                <div class="sfv-settings-form-grid">
                                    <?php foreach ( $group['colors'] as $key => $label ) : ?><label class="sfv-setting-control sfv-color-control"><span><?php echo esc_html( $label ); ?></span><?php echo $this->rgba_picker_markup( (string) $key, (string) $s[ $key ], str_replace( '_', '-', (string) $key ) ); ?><small>Επιλέγουμε χρώμα και opacity.</small></label><?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="sfv-settings-subsection">
                            <div class="sfv-state-panel-heading"><strong><?php esc_html_e( 'Τυπογραφία & διαστάσεις', 'secure-form-vault' ); ?></strong><span><?php esc_html_e( 'Κοινές ρυθμίσεις για όλες τις καταστάσεις.', 'secure-form-vault' ); ?></span></div>
                            <div class="sfv-settings-form-grid">
                                <label class="sfv-setting-control"><span><?php esc_html_e( 'Γραμματοσειρά', 'secure-form-vault' ); ?></span><select data-preview="field-font" name="<?php echo esc_attr( self::OPTION ); ?>[field_font]"><?php foreach ( $font_options as $value => $text ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['field_font'], $value ); ?>><?php echo esc_html( $text ); ?></option><?php endforeach; ?></select></label>
                                <label class="sfv-setting-control"><span><?php esc_html_e( 'Μέγεθος', 'secure-form-vault' ); ?></span><div class="sfv-unit-input"><input data-preview="field-size" type="number" min="10" max="32" name="<?php echo esc_attr( self::OPTION ); ?>[field_size]" value="<?php echo absint( $s['field_size'] ); ?>"><span>px</span></div></label>
                                <label class="sfv-setting-control"><span><?php esc_html_e( 'Βάρος', 'secure-form-vault' ); ?></span><select data-preview="field-weight" name="<?php echo esc_attr( self::OPTION ); ?>[field_weight]"><?php foreach ( $weight_options as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['field_weight'], $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></label>
                                <label class="sfv-setting-control"><span><?php esc_html_e( 'Ύψος', 'secure-form-vault' ); ?></span><div class="sfv-unit-input"><input data-preview="field-height" type="number" min="32" max="72" name="<?php echo esc_attr( self::OPTION ); ?>[field_height]" value="<?php echo absint( $s['field_height'] ); ?>"><span>px</span></div></label>
                                <label class="sfv-setting-control"><span><?php esc_html_e( 'Στρογγύλεμα', 'secure-form-vault' ); ?></span><div class="sfv-unit-input"><input data-preview="field-radius" type="number" min="0" max="32" name="<?php echo esc_attr( self::OPTION ); ?>[field_radius]" value="<?php echo absint( $s['field_radius'] ); ?>"><span>px</span></div></label>
                            </div>
                        </div>
                    </section>

                    <section class="sfv-settings-pane" data-settings-pane="forms">
                        <div class="sfv-settings-pane-header"><div><span class="sfv-settings-kicker"><?php esc_html_e( 'Κουμπί ενέργειας', 'secure-form-vault' ); ?></span><h2><?php esc_html_e( 'Κουμπί υποβολής', 'secure-form-vault' ); ?></h2><p><?php esc_html_e( 'Προσαρμόζουμε το βασικό κουμπί υποβολής και το hover state του.', 'secure-form-vault' ); ?></p></div></div>
                        <div class="sfv-settings-form-grid">
                            <?php foreach ( array( 'submit_text_color' => 'Χρώμα κειμένου', 'submit_background' => 'Φόντο', 'submit_hover_background' => 'Hover' ) as $key => $label ) : ?><label class="sfv-setting-control sfv-color-control"><span><?php echo esc_html( $label ); ?></span><?php echo $this->rgba_picker_markup( (string) $key, (string) $s[ $key ], str_replace( '_', '-', (string) $key ) ); ?><small>Επιλέγουμε χρώμα και opacity.</small></label><?php endforeach; ?>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Γραμματοσειρά', 'secure-form-vault' ); ?></span><select data-preview="submit-font" name="<?php echo esc_attr( self::OPTION ); ?>[submit_font]"><?php foreach ( $font_options as $value => $text ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['submit_font'], $value ); ?>><?php echo esc_html( $text ); ?></option><?php endforeach; ?></select></label>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Μέγεθος', 'secure-form-vault' ); ?></span><div class="sfv-unit-input"><input data-preview="submit-size" type="number" min="10" max="32" name="<?php echo esc_attr( self::OPTION ); ?>[submit_size]" value="<?php echo absint( $s['submit_size'] ); ?>"><span>px</span></div></label>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Βάρος', 'secure-form-vault' ); ?></span><select data-preview="submit-weight" name="<?php echo esc_attr( self::OPTION ); ?>[submit_weight]"><?php foreach ( $weight_options as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['submit_weight'], $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></label>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Ύψος', 'secure-form-vault' ); ?></span><div class="sfv-unit-input"><input data-preview="submit-height" type="number" min="32" max="72" name="<?php echo esc_attr( self::OPTION ); ?>[submit_height]" value="<?php echo absint( $s['submit_height'] ); ?>"><span>px</span></div></label>
                            <label class="sfv-setting-control"><span><?php esc_html_e( 'Στρογγύλεμα', 'secure-form-vault' ); ?></span><div class="sfv-unit-input"><input data-preview="submit-radius" type="number" min="0" max="32" name="<?php echo esc_attr( self::OPTION ); ?>[submit_radius]" value="<?php echo absint( $s['submit_radius'] ); ?>"><span>px</span></div></label>
                        </div>
                    </section>

                    <footer class="sfv-settings-footer"><div><strong><?php esc_html_e( 'Οι αλλαγές εφαρμόζονται σε όλες τις φόρμες', 'secure-form-vault' ); ?></strong><small><?php esc_html_e( 'Οι ρυθμίσεις είναι scoped και δεν επηρεάζουν άλλα στοιχεία του YOOtheme.', 'secure-form-vault' ); ?></small></div><button type="submit" class="sfv-btn sfv-btn-primary"><span class="dashicons dashicons-saved"></span><?php esc_html_e( 'Αποθήκευση ρυθμίσεων', 'secure-form-vault' ); ?></button></footer>
                </main>

            </form>
        </div>
        <?php
    }

    private function rgba_picker_markup( string $key, string $value, string $preview = '' ): string {
        $hex = '#000000';
        $alpha = 1.0;
        $value = trim( $value );

        if ( preg_match( '/^#([0-9a-f]{6})$/i', $value, $matches ) ) {
            $hex = '#' . strtolower( $matches[1] );
        } elseif ( preg_match( '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*((?:0(?:\.\d+)?)|(?:1(?:\.0+)?))\s*\)$/i', $value, $matches ) ) {
            $red = min( 255, absint( $matches[1] ) );
            $green = min( 255, absint( $matches[2] ) );
            $blue = min( 255, absint( $matches[3] ) );
            $alpha = min( 1, max( 0, (float) $matches[4] ) );
            $hex = sprintf( '#%02x%02x%02x', $red, $green, $blue );
        }

        $name = self::OPTION . '[' . $key . ']';
        ob_start();
        ?>
        <div class="sfv-rgba-picker" data-rgba-control>
            <div class="sfv-rgba-picker-row">
                <input type="color" class="sfv-rgba-color" value="<?php echo esc_attr( $hex ); ?>" aria-label="<?php esc_attr_e( 'Επιλογή χρώματος', 'secure-form-vault' ); ?>">
                <div class="sfv-rgba-opacity-wrap">
                    <span><?php esc_html_e( 'Opacity', 'secure-form-vault' ); ?></span>
                    <input type="range" class="sfv-rgba-opacity" min="0" max="100" step="1" value="<?php echo esc_attr( (string) round( $alpha * 100 ) ); ?>">
                    <output><?php echo esc_html( (string) round( $alpha * 100 ) ); ?>%</output>
                </div>
            </div>
            <input type="text" class="sfv-rgba-value" <?php echo $preview ? 'data-preview="' . esc_attr( $preview ) . '"' : ''; ?> name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" spellcheck="false" autocomplete="off">
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function payload_value_by_terms( array $payload, array $terms ): string {
        foreach ( $payload as $key => $item ) {
            $label = $this->xlsx_normalize_text( (string) ( $item['label'] ?? '' ) );
            $field_key = $this->xlsx_normalize_text( (string) $key );
            $haystack = $label . ' ' . $field_key;
            foreach ( $terms as $term ) {
                if ( str_contains( $haystack, $this->xlsx_normalize_text( (string) $term ) ) ) {
                    return trim( (string) ( $item['value'] ?? '' ) );
                }
            }
        }
        return '';
    }

    private function submission_summary( array $payload, int $form_id = 0 ): array {
        if ( $form_id > 0 ) {
            $form_fields = get_post_meta( $form_id, '_sfv_fields', true );
            foreach ( (array) $form_fields as $field ) {
                $key = sanitize_key( $field['key'] ?? '' );
                if ( '' === $key || ! isset( $payload[ $key ] ) || ! is_array( $payload[ $key ] ) ) continue;
                $schema_label = trim( wp_strip_all_tags( (string) ( $field['label'] ?? '' ) ) );
                $placeholder = trim( wp_strip_all_tags( (string) ( $field['placeholder'] ?? '' ) ) );
                $payload[ $key ]['label'] = trim( $schema_label . ' ' . $placeholder );
            }
        }
        $customer_type = $this->payload_value_by_terms( $payload, array( 'τυπος πελατη', 'τύπος πελάτη', 'customer type', 'customer_type' ) );
        $customer_type_normalized = $this->xlsx_normalize_text( $customer_type );
        $is_company = (bool) preg_match( '/εταιρ|εταιρει|επιχειρ|company|business|επαγγελ|νομικ|corporate|enterprise/u', $customer_type_normalized );
        $company = $this->payload_value_by_terms( $payload, array( 'επωνυμια', 'επωνυμία', 'εταιρεια', 'εταιρεία', 'company name', 'business_name' ) );
        $full_name = $this->payload_value_by_terms( $payload, array( 'ονοματεπωνυμο', 'ονοματεπώνυμο', 'ονομα και επωνυμο', 'όνομα και επώνυμο', 'ονομα πελατη', 'όνομα πελάτη', 'customer name', 'full name', 'fullname', 'full_name' ) );
        $first = $this->payload_value_by_terms( $payload, array( 'ονομα', 'όνομα', 'first name', 'first_name' ) );
        $last = $this->payload_value_by_terms( $payload, array( 'επωνυμο', 'επώνυμο', 'surname', 'last name', 'last_name' ) );
        $person = trim( $full_name ?: trim( $first . ' ' . $last ) );
        $display_name = $is_company && $company ? $company : ( $person ?: $company );

        $product = $this->payload_value_by_terms( $payload, array( 'προιον παραγγελιας', 'προϊόν παραγγελίας', 'προιον', 'προϊόν', 'product', 'fuel' ) );
        $quantity = $this->payload_value_by_terms( $payload, array( 'ποσοτητα', 'ποσότητα', 'quantity', 'litres', 'liters', 'λιτρα', 'λίτρα' ) );

        // A single-product calculator is a display/calculation element, so its product
        // is not posted as a regular input. Recover the fixed product from the form schema.
        if ( '' === $product && $form_id > 0 ) {
            $form_fields = get_post_meta( $form_id, '_sfv_fields', true );
            foreach ( (array) $form_fields as $field ) {
                if ( ! is_array( $field ) || 'single_price_calculator' !== ( $field['type'] ?? '' ) ) continue;
                $product = trim( (string) ( $field['product_label'] ?? '' ) );
                if ( '' === $product ) {
                    $rows = $this->sanitize_pricing_rows( $field['pricing_rows'] ?? array() );
                    $product = trim( (string) ( $rows[0]['product'] ?? '' ) );
                }
                if ( '' !== $product ) break;
            }
        }

        return array(
            'name' => $display_name ?: __( 'Οικιακός πελάτης', 'secure-form-vault' ),
            'customer' => $display_name ?: __( 'Οικιακός πελάτης', 'secure-form-vault' ),
            'customer_type' => $customer_type,
            'is_company' => $is_company,
            'product' => $product ?: '—',
            'quantity' => $quantity ?: '—',
        );
    }

    public function new_order_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) );
        $form_id = absint( $_GET['form_id'] ?? 0 );
        $forms = get_posts( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
        $this->admin_nav( 'new-order' );
        echo '<div class="wrap sfv-admin-page grfuel-new-order-page">';
        $this->section_intro( 'Νέα Παραγγελία', 'Καταχώριση τηλεφωνικής ή χειροκίνητης παραγγελίας με τα ζωντανά πεδία και dependencies της φόρμας.' );
        if ( isset( $_GET['created'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Η παραγγελία αποθηκεύτηκε επιτυχώς.</p></div>';
        echo '<section class="sfv-panel grfuel-admin-order-panel"><form method="get" class="grfuel-admin-form-picker"><input type="hidden" name="page" value="grfuel-new-order"><label><span>Επιλογή φόρμας</span><select name="form_id" onchange="this.form.submit()"><option value="">— Επιλέξτε φόρμα —</option>';
        foreach ( $forms as $form ) printf( '<option value="%d" %s>%s</option>', absint( $form->ID ), selected( $form_id, $form->ID, false ), esc_html( $form->post_title ) );
        echo '</select></label><noscript><button class="sfv-btn sfv-btn-secondary">Φόρτωση</button></noscript></form>';
        if ( $form_id && self::CPT === get_post_type( $form_id ) ) {
            $fields = array_filter( (array) get_post_meta( $form_id, '_sfv_fields', true ), 'is_array' );
            wp_add_inline_style( 'sfv-front', $this->appearance_css( $this->settings() ) );
            echo '<form class="sfv-form uk-form-stacked grfuel-admin-order-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="grfuel_create_admin_order"><input type="hidden" name="form_id" value="' . absint( $form_id ) . '">';
            wp_nonce_field( 'grfuel_create_admin_order_' . $form_id );
            echo '<div class="sfv-grid grfuel-admin-order-grid">';
            foreach ( $fields as $field ) {
                if ( in_array( sanitize_key( $field['type'] ?? '' ), array( 'turnstile', 'submit' ), true ) ) continue;
                $this->render_field( $field, $form_id, $this->settings(), true );
            }
            echo '<div class="sfv-field grfuel-admin-field grfuel-admin-width-1-1 grfuel-admin-order-submit"><button type="submit" class="sfv-btn sfv-btn-primary grfuel-admin-order-save"><span class="dashicons dashicons-saved" aria-hidden="true"></span><span>Αποθήκευση παραγγελίας</span></button></div></div></form>';
        } else {
            echo '<div class="sfv-empty-submissions"><span class="dashicons dashicons-clipboard"></span><h3>Επιλέξτε φόρμα</h3><p>Τα πεδία φορτώνονται απευθείας από τον Form Builder και ενημερώνονται αυτόματα όταν αλλάζει η φόρμα.</p></div>';
        }
        echo '</section></div>';
    }

    public function create_admin_order(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) );
        $form_id = absint( $_POST['form_id'] ?? 0 );
        check_admin_referer( 'grfuel_create_admin_order_' . $form_id );
        if ( ! $form_id || self::CPT !== get_post_type( $form_id ) ) wp_die( 'Invalid form.' );
        $fields = array_filter( (array) get_post_meta( $form_id, '_sfv_fields', true ), 'is_array' );
        $posted = isset( $_POST['sfv_fields'] ) && is_array( $_POST['sfv_fields'] ) ? wp_unslash( $_POST['sfv_fields'] ) : array();
        $payload = array();
        foreach ( $fields as $field ) {
            $key = sanitize_key( $field['key'] ?? '' ); $type = sanitize_key( $field['type'] ?? 'text' );
            if ( ! $key || in_array( $type, array( 'submit','turnstile','heading','content_text','divider','price_calculator','single_price_calculator' ), true ) ) continue;
            $raw = $posted[$key] ?? ''; if ( is_array( $raw ) ) wp_die( 'Invalid value.' ); $raw = (string) $raw;
            if ( 'email' === $type ) $value = sanitize_email( $raw );
            elseif ( 'textarea' === $type ) $value = mb_substr( sanitize_textarea_field( $raw ), 0, 5000 );
            elseif ( 'checkbox' === $type ) $value = '1' === $raw ? sanitize_text_field( $field['checkbox_value'] ?? 'Ναι' ) : '';
            elseif ( 'number' === $type ) { $value = str_replace( ',', '.', sanitize_text_field( $raw ) ); if ( '' !== $value && ! is_numeric( $value ) ) wp_die( 'Invalid number.' ); }
            else $value = mb_substr( sanitize_text_field( $raw ), 0, 500 );
            if ( 'radio' === $type && ! empty( $field['message_only_enabled'] ) && $value === (string) ( $field['message_only_option'] ?? '' ) ) {
                wp_die( esc_html( (string) ( $field['message_only_text'] ?? 'Η συγκεκριμένη επιλογή δεν είναι διαθέσιμη αυτή την περίοδο.' ) ) );
            }
            $visible = $this->dependency_matches( $field['dependency'] ?? array(), $posted );
            if ( $visible && ! empty( $field['required'] ) && '' === trim( (string) $value ) ) wp_die( 'Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία.' );
            $payload[$key] = array( 'label' => sanitize_text_field( wp_strip_all_tags( (string) ( $field['label'] ?? $key ) ) ), 'value' => $value );
        }
        $payload['_grfuel_source'] = array( 'label' => 'Πηγή καταχώρισης', 'value' => 'Διαχειριστής' );
        $user = wp_get_current_user();
        $payload['_grfuel_created_by'] = array( 'label' => 'Καταχωρίστηκε από', 'value' => $user->display_name . ' (#' . $user->ID . ')' );
        $summary = $this->submission_summary( $payload, $form_id );
        $quantity = max( 0, (float) str_replace( ',', '.', (string) ( '—' === $summary['quantity'] ? 0 : $summary['quantity'] ) ) );
        $price = $this->order_price_details( $form_id, '—' === $summary['product'] ? '' : (string) $summary['product'], $quantity );
        if ( null !== $price ) $payload['_sfv_order_total'] = array( 'label' => 'Τιμή παραγγελίας', 'value' => number_format( (float) $price['total'], 2, '.', '' ) . ' €' );
        global $wpdb;
        $ok = $wpdb->insert( $this->table(), array( 'form_id'=>$form_id, 'submitted_at'=>current_time('mysql',true), 'payload'=>wp_json_encode($payload,JSON_UNESCAPED_UNICODE), 'ip_hash'=>'admin:' . get_current_user_id(), 'user_agent'=>'GRFuel Manager admin' ), array('%d','%s','%s','%s','%s') );
        if ( false === $ok ) wp_die( 'Η παραγγελία δεν αποθηκεύτηκε.' );
        $this->send_admin_push_notification( (int) $wpdb->insert_id );
        wp_safe_redirect( add_query_arg( array( 'page'=>'grfuel-new-order', 'form_id'=>$form_id, 'created'=>1 ), admin_url('admin.php') ) ); exit;
    }

    public function submissions_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) ); }
        $this->mark_orders_read();
        global $wpdb;
        $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        $paged = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $per_page = 30;
        $offset = ( $paged - 1 ) * $per_page;
        $table = $this->table();
        $where_sql = $form_id ? $wpdb->prepare( ' WHERE form_id = %d', $form_id ) : '';
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table}{$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
        $filtered_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}{$where_sql}" );
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE submitted_at >= %s", gmdate( 'Y-m-d 00:00:00' ) ) );
        $week = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE submitted_at >= %s", gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS ) ) );
        $active_forms = (int) wp_count_posts( self::CPT )->publish;
        $pages = max( 1, (int) ceil( $filtered_total / $per_page ) );
        ?>
        <div class="wrap sfv-admin-page">
            <?php $this->admin_nav( 'submissions' ); ?>
            <?php $this->section_intro(
                'Παραγγελίες',
                'Ελέγχουμε τα αιτήματα των επισκεπτών, εφαρμόζουμε φίλτρα και δημιουργούμε αρχεία Excel, XML ή PDF.',
                array(
                    array( 'label' => __( 'Εξαγωγή παραγγελιών', 'secure-form-vault' ), 'url' => '#sfv-export', 'class' => 'sfv-btn-primary', 'icon' => 'dashicons-download' ),
                )
            ); ?>
            <?php
            $maintenance_status = sanitize_key( wp_unslash( $_GET['sfv_status'] ?? '' ) );
            $maintenance_messages = array(
                'cleared' => array( 'success', __( 'Όλες οι υποβολές διαγράφηκαν οριστικά.', 'secure-form-vault' ) ),
                'reset' => array( 'success', __( 'Η αρίθμηση υποβολών επαναφέρθηκε. Η επόμενη υποβολή θα έχει ID 1.', 'secure-form-vault' ) ),
                'reset_blocked' => array( 'warning', __( 'Η επαναφορά ID επιτρέπεται μόνο όταν δεν υπάρχουν αποθηκευμένες υποβολές.', 'secure-form-vault' ) ),
                'maintenance_error' => array( 'error', __( 'Η ενέργεια δεν ολοκληρώθηκε. Ελέγχουμε τη βάση δεδομένων και δοκιμάζουμε ξανά.', 'secure-form-vault' ) ),
            );
            if ( isset( $maintenance_messages[ $maintenance_status ] ) ) : list( $notice_type, $notice_text ) = $maintenance_messages[ $maintenance_status ]; ?>
                <div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible sfv-maintenance-notice"><p><?php echo esc_html( $notice_text ); ?></p></div>
            <?php endif; ?>
            <section class="sfv-stats-grid" aria-label="<?php esc_attr_e( 'Στατιστικά παραγγελιών', 'secure-form-vault' ); ?>">
                <article class="sfv-stat-card"><span class="dashicons dashicons-database"></span><div><strong><?php echo number_format_i18n( $total ); ?></strong><small><?php esc_html_e( 'Σύνολο παραγγελιών', 'secure-form-vault' ); ?></small></div></article>
                <article class="sfv-stat-card"><span class="dashicons dashicons-calendar-alt"></span><div><strong><?php echo number_format_i18n( $today ); ?></strong><small><?php esc_html_e( 'Σημερινές παραγγελίες', 'secure-form-vault' ); ?></small></div></article>
                <article class="sfv-stat-card"><span class="dashicons dashicons-chart-line"></span><div><strong><?php echo number_format_i18n( $week ); ?></strong><small><?php esc_html_e( 'Τελευταίες 7 ημέρες', 'secure-form-vault' ); ?></small></div></article>
                <article class="sfv-stat-card"><span class="dashicons dashicons-feedback"></span><div><strong><?php echo number_format_i18n( $active_forms ); ?></strong><small><?php esc_html_e( 'Δημοσιευμένες φόρμες', 'secure-form-vault' ); ?></small></div></article>
            </section>
            <div class="sfv-submissions-layout">
                <main class="sfv-panel sfv-submissions-panel">
                    <div class="sfv-panel-heading sfv-list-heading"><div><h2><?php esc_html_e( 'Τελευταίες παραγγελίες', 'secure-form-vault' ); ?></h2><p><?php echo esc_html( sprintf( _n( '%s αντίστοιχη εγγραφή', '%s αντίστοιχες εγγραφές', $filtered_total, 'secure-form-vault' ), number_format_i18n( $filtered_total ) ) ); ?></p></div>
                        <form class="sfv-filter-form" method="get"><input type="hidden" name="post_type" value="<?php echo esc_attr( self::CPT ); ?>"><input type="hidden" name="page" value="sfv-submissions"><?php wp_dropdown_pages( array( 'post_type' => self::CPT, 'name' => 'form_id', 'selected' => $form_id, 'show_option_all' => __( 'Όλες οι φόρμες', 'secure-form-vault' ), 'class' => 'sfv-select' ) ); ?><button class="sfv-btn sfv-btn-secondary" type="submit"><?php esc_html_e( 'Εφαρμογή φίλτρου', 'secure-form-vault' ); ?></button></form>
                    </div>
                    <form id="sfv-bulk-pdf-form" method="post" target="_blank" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="sfv_export_selected_pdf"><?php wp_nonce_field( 'sfv_export_selected_pdf' ); ?>
                        <div class="sfv-bulk-toolbar">
                            <label class="sfv-check-all"><input type="checkbox" id="sfv-select-all"><span><?php esc_html_e( 'Επιλογή όλων', 'secure-form-vault' ); ?></span></label>
                            <button class="sfv-btn sfv-btn-primary" type="submit" id="sfv-bulk-pdf" disabled><svg class="sfv-acrobat-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12.4 2.2c1.4.2 1.8 2.2 1.2 4.7-.2.8-.5 1.7-.9 2.7 1.1 2 2.3 3.5 3.5 4.6 2.6-.4 4.5-.2 5.1.8.4.7.1 1.6-.7 2.2-1.2.9-3.2.4-5.2-1.2-2.2.4-4.7 1.1-6.8 2-1.2 2.1-2.6 3.5-3.8 3.7-.8.1-1.5-.3-1.7-1-.4-1.3 1.2-2.9 4.2-4.3 1-1.8 2-4 2.8-6.1-.8-1.8-1.2-3.5-.9-4.9.3-2 1.7-3.4 3.2-3.2Zm-6.7 17.5c.4-.2.9-.7 1.5-1.5-1.1.6-1.6 1.1-1.5 1.5Zm4.5-3.7c1.3-.5 2.7-.9 4.1-1.2-.9-.9-1.7-2-2.5-3.2-.5 1.4-1 2.9-1.6 4.4Zm1.4-11.6c-.3.3-.4 1.5.1 3 .3-1 .5-1.9.4-2.5 0-.4-.2-.6-.5-.5Zm6.2 11.2c1 .5 1.8.7 2.1.5.1-.1.1-.2 0-.3-.3-.4-1.1-.5-2.1-.2Z"/></svg><?php esc_html_e( 'Εξαγωγή PDF', 'secure-form-vault' ); ?> <span class="sfv-selected-count">0</span></button>
                        </div>
                        <div class="sfv-submission-table" role="table">
                            <div class="sfv-submission-row sfv-submission-head" role="row"><span></span><span><?php esc_html_e( 'Πελάτης', 'secure-form-vault' ); ?></span><span><?php esc_html_e( 'Προϊόν παραγγελίας', 'secure-form-vault' ); ?></span><span><?php esc_html_e( 'Ποσότητα', 'secure-form-vault' ); ?></span><span><?php esc_html_e( 'Ημερομηνία', 'secure-form-vault' ); ?></span><span></span></div>
                            <?php foreach ( $rows as $row ) : $payload = json_decode( $row->payload, true ); $payload = is_array( $payload ) ? $payload : array(); $summary = $this->submission_summary( $payload, (int) $row->form_id ); ?>
                                <div class="sfv-submission-row" role="row">
                                    <label class="sfv-row-checkbox"><input class="sfv-submission-check" type="checkbox" name="submission_ids[]" value="<?php echo absint( $row->id ); ?>"><span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Επιλογή υποβολής #%d', 'secure-form-vault' ), $row->id ) ); ?></span></label>
                                    <div class="sfv-row-primary"><strong><?php echo esc_html( $summary['name'] ); ?></strong><small>#<?php echo absint( $row->id ); ?> · <?php echo esc_html( get_the_title( $row->form_id ) ?: __( 'Διαγραμμένη φόρμα', 'secure-form-vault' ) ); ?></small></div>
                                    <div><span class="sfv-mobile-label"><?php esc_html_e( 'Προϊόν', 'secure-form-vault' ); ?></span><?php echo esc_html( $summary['product'] ); ?></div>
                                    <div><span class="sfv-mobile-label"><?php esc_html_e( 'Ποσότητα', 'secure-form-vault' ); ?></span><?php echo esc_html( $summary['quantity'] ); ?></div>
                                    <time datetime="<?php echo esc_attr( mysql2date( 'c', $row->submitted_at, false ) ); ?>"><?php echo esc_html( get_date_from_gmt( $row->submitted_at, 'd/m/Y H:i' ) ); ?></time>
                                    <div class="sfv-row-actions"><a class="sfv-icon-action" target="_blank" rel="noopener" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sfv_export_submission_pdf&id=' . absint( $row->id ) ), 'sfv_export_submission_pdf_' . absint( $row->id ) ) ); ?>" aria-label="<?php esc_attr_e( 'PDF', 'secure-form-vault' ); ?>"><svg class="sfv-acrobat-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12.4 2.2c1.4.2 1.8 2.2 1.2 4.7-.2.8-.5 1.7-.9 2.7 1.1 2 2.3 3.5 3.5 4.6 2.6-.4 4.5-.2 5.1.8.4.7.1 1.6-.7 2.2-1.2.9-3.2.4-5.2-1.2-2.2.4-4.7 1.1-6.8 2-1.2 2.1-2.6 3.5-3.8 3.7-.8.1-1.5-.3-1.7-1-.4-1.3 1.2-2.9 4.2-4.3 1-1.8 2-4 2.8-6.1-.8-1.8-1.2-3.5-.9-4.9.3-2 1.7-3.4 3.2-3.2Zm-6.7 17.5c.4-.2.9-.7 1.5-1.5-1.1.6-1.6 1.1-1.5 1.5Zm4.5-3.7c1.3-.5 2.7-.9 4.1-1.2-.9-.9-1.7-2-2.5-3.2-.5 1.4-1 2.9-1.6 4.4Zm1.4-11.6c-.3.3-.4 1.5.1 3 .3-1 .5-1.9.4-2.5 0-.4-.2-.6-.5-.5Zm6.2 11.2c1 .5 1.8.7 2.1.5.1-.1.1-.2 0-.3-.3-.4-1.1-.5-2.1-.2Z"/></svg></a><?php if ( current_user_can( self::ADMIN_CAPABILITY ) ) : ?><button type="submit" form="sfv-delete-<?php echo absint( $row->id ); ?>" class="sfv-icon-action sfv-delete-link" aria-label="<?php esc_attr_e( 'Διαγραφή παραγγελίας', 'secure-form-vault' ); ?>"><span class="dashicons dashicons-trash"></span></button><?php endif; ?></div>
                                </div>
                            <?php endforeach; if ( empty( $rows ) ) : ?><div class="sfv-empty-submissions"><span class="dashicons dashicons-email-alt"></span><h3><?php esc_html_e( 'Δεν βρέθηκαν παραγγελίες', 'secure-form-vault' ); ?></h3><p><?php esc_html_e( 'Οι νέες παραγγελίες θα εμφανίζονται εδώ αυτόματα.', 'secure-form-vault' ); ?></p></div><?php endif; ?>
                        </div>
                    </form>
                    <?php if ( current_user_can( self::ADMIN_CAPABILITY ) ) : foreach ( $rows as $delete_row ) : ?>
                        <form id="sfv-delete-<?php echo absint( $delete_row->id ); ?>" class="sfv-inline-delete-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Να διαγραφεί οριστικά αυτή η παραγγελία;', 'secure-form-vault' ) ); ?>')">
                            <input type="hidden" name="action" value="sfv_delete_submission"><input type="hidden" name="id" value="<?php echo absint( $delete_row->id ); ?>"><?php wp_nonce_field( 'sfv_delete_' . absint( $delete_row->id ) ); ?>
                        </form>
                    <?php endforeach; endif; ?>
                    <?php if ( $pages > 1 ) : ?><nav class="sfv-pagination" aria-label="<?php esc_attr_e( 'Σελιδοποίηση παραγγελιών', 'secure-form-vault' ); ?>"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => $pages, 'prev_text' => '←', 'next_text' => '→' ) ) ); ?></nav><?php endif; ?>
                </main>
                <aside id="sfv-export" class="sfv-panel sfv-export-card">
                    <div class="sfv-export-icon"><span class="dashicons dashicons-download"></span></div><h2><?php esc_html_e( 'Εξαγωγή παραγγελιών', 'secure-form-vault' ); ?></h2><p><?php esc_html_e( 'Επιλέξτε φόρμα και, προαιρετικά, περιορίστε την εξαγωγή με εύρος ημερομηνιών ή αριθμών παραγγελίας.', 'secure-form-vault' ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="sfv_export"><?php wp_nonce_field( 'sfv_export' ); ?>
                        <?php wp_dropdown_pages( array( 'post_type' => self::CPT, 'name' => 'form_id', 'selected' => $form_id, 'show_option_all' => __( 'Όλες οι φόρμες', 'secure-form-vault' ), 'class' => 'sfv-select', 'aria_label' => __( 'Επιλογή φόρμας', 'secure-form-vault' ) ) ); ?>
                        <div class="sfv-field-pair"><label><span><?php esc_html_e( 'Από ημερομηνία', 'secure-form-vault' ); ?></span><input type="date" name="date_from"></label><label><span><?php esc_html_e( 'Έως ημερομηνία', 'secure-form-vault' ); ?></span><input type="date" name="date_to"></label></div>
                        <div class="sfv-field-pair"><label><span><?php esc_html_e( 'Από ID', 'secure-form-vault' ); ?></span><input type="number" min="1" name="id_from"></label><label><span><?php esc_html_e( 'Έως ID', 'secure-form-vault' ); ?></span><input type="number" min="1" name="id_to"></label></div>
                        <label><span><?php esc_html_e( 'Μορφή αρχείου', 'secure-form-vault' ); ?></span><select name="format"><option value="xlsx"><?php esc_html_e( 'Βιβλίο εργασίας Excel (.xlsx)', 'secure-form-vault' ); ?></option><option value="xml"><?php esc_html_e( 'Αρχείο XML (.xml)', 'secure-form-vault' ); ?></option></select></label>
                        <button class="sfv-btn sfv-btn-primary sfv-btn-block" type="submit"><span class="dashicons dashicons-download"></span><?php esc_html_e( 'Δημιουργία αρχείου εξαγωγής', 'secure-form-vault' ); ?></button>
                    </form>
                    <?php if ( current_user_can( self::ADMIN_CAPABILITY ) ) : ?><div class="sfv-maintenance-card"><div class="sfv-maintenance-heading"><span class="dashicons dashicons-shield-alt"></span><div><h3><?php esc_html_e( 'GDPR & συντήρηση', 'secure-form-vault' ); ?></h3><p><?php esc_html_e( 'Οριστική διαγραφή δεδομένων και διαχείριση αρίθμησης.', 'secure-form-vault' ); ?></p></div></div>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Θα διαγραφούν οριστικά όλες οι υποβολές από τη βάση δεδομένων. Η ενέργεια δεν αναιρείται. Συνέχεια;', 'secure-form-vault' ) ); ?>');"><input type="hidden" name="action" value="sfv_clear_submissions"><?php wp_nonce_field( 'sfv_clear_submissions' ); ?><button type="submit" class="sfv-btn sfv-btn-danger sfv-btn-block"><span class="dashicons dashicons-trash"></span><?php esc_html_e( 'Διαγραφή όλων των παραγγελιών', 'secure-form-vault' ); ?></button></form>
                        <p class="sfv-maintenance-help"><?php esc_html_e( 'Η διαγραφή δεν επαναφέρει αυτόματα τον επόμενο αριθμό ID.', 'secure-form-vault' ); ?></p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Η επόμενη υποβολή θα ξεκινήσει από το ID 1. Η επαναφορά επιτρέπεται μόνο όταν ο πίνακας είναι άδειος. Συνέχεια;', 'secure-form-vault' ) ); ?>');"><input type="hidden" name="action" value="sfv_reset_submission_id"><?php wp_nonce_field( 'sfv_reset_submission_id' ); ?><button type="submit" class="sfv-btn sfv-btn-secondary sfv-btn-block"><span class="dashicons dashicons-update-alt"></span><?php esc_html_e( 'Επαναφορά αρίθμησης παραγγελιών', 'secure-form-vault' ); ?></button></form>
                    </div><?php endif; ?>
                </aside>
            </div>
        </div>
        <script>(function(){var all=document.getElementById('sfv-select-all'),checks=[].slice.call(document.querySelectorAll('.sfv-submission-check')),button=document.getElementById('sfv-bulk-pdf'),count=document.querySelector('.sfv-selected-count');function sync(){var n=checks.filter(function(c){return c.checked}).length;if(button)button.disabled=!n;if(count)count.textContent=n;if(all){all.checked=checks.length>0&&n===checks.length;all.indeterminate=n>0&&n<checks.length}}checks.forEach(function(c){c.addEventListener('change',sync)});if(all)all.addEventListener('change',function(){checks.forEach(function(c){c.checked=all.checked});sync()});sync()}());</script>
        <?php
    }

    public function export_selected_pdf(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) ); }
        check_admin_referer( 'sfv_export_selected_pdf' );
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $_POST['submission_ids'] ?? array() ) ) ) ) );
        if ( ! $ids || count( $ids ) > 100 ) { wp_die( esc_html__( 'Επιλέγουμε από 1 έως 100 υποβολές.', 'secure-form-vault' ) ); }
        global $wpdb;
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql = $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id IN ({$placeholders}) ORDER BY id ASC", $ids );
        $rows = $wpdb->get_results( $sql );
        if ( ! $rows ) { wp_die( esc_html__( 'Δεν βρέθηκαν οι επιλεγμένες υποβολές.', 'secure-form-vault' ) ); }
        $this->render_submission_print( $rows );
    }

    public function export_submission_pdf(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) ); }
        $id = absint( $_GET['id'] ?? 0 );
        check_admin_referer( 'sfv_export_submission_pdf_' . $id );
        if ( ! $id ) { wp_die( esc_html__( 'Invalid submission.', 'secure-form-vault' ) ); }
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", $id ) );
        if ( ! $row ) { wp_die( esc_html__( 'Submission not found.', 'secure-form-vault' ) ); }
        $this->render_submission_print( array( $row ) );
    }

    private function render_submission_print( array $rows ): void {
        nocache_headers();
        header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
        header( 'Referrer-Policy: no-referrer' );
        $pdf_settings = $this->settings();
        $pdf_logo = esc_url( (string) $pdf_settings['pdf_logo_url'] );
        $pdf_watermark = esc_url( (string) ( $pdf_settings['pdf_watermark_url'] ?? '' ) );
        $pdf_watermark_opacity = max( 0, min( 100, absint( $pdf_settings['pdf_watermark_opacity'] ?? 12 ) ) ) / 100;
        $pdf_brand = trim( (string) $pdf_settings['pdf_brand_name'] );
        if ( '' === $pdf_brand ) { $pdf_brand = get_bloginfo( 'name' ); }
        header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src 'self' data: https:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'" );
        ?>
<!doctype html><html lang="el"><head><meta charset="<?php echo esc_attr( get_option( 'blog_charset', 'UTF-8' ) ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php esc_html_e( 'Αναφορά υποβολών', 'secure-form-vault' ); ?></title>
<style>
@page{size:A4;margin:0}:root{--pdf-primary:<?php echo esc_attr($pdf_settings['pdf_primary']); ?>;--pdf-accent:<?php echo esc_attr($pdf_settings['pdf_accent']); ?>;--pdf-text:<?php echo esc_attr($pdf_settings['pdf_text']); ?>;--pdf-muted:<?php echo esc_attr($pdf_settings['pdf_muted']); ?>;--pdf-surface:<?php echo esc_attr($pdf_settings['pdf_surface']); ?>}*{box-sizing:border-box}body{margin:0;background:#e9eef5;color:var(--pdf-text);font-family:"Geom",Arial,"Helvetica Neue",sans-serif;font-size:12px;line-height:1.5}.toolbar{position:sticky;top:0;z-index:20;display:flex;justify-content:center;padding:12px;background:rgba(233,238,245,.96);border-bottom:1px solid #d7dee8}.toolbar button{border:0;border-radius:10px;padding:11px 18px;background:var(--pdf-accent);color:#fff;font-weight:800}.document{width:min(820px,calc(100% - 28px));margin:22px auto}.sheet{position:relative;display:flex;flex-direction:column;min-height:270mm;margin-bottom:22px;padding:30px 32px 24px;background:var(--pdf-surface);border-radius:18px;box-shadow:0 18px 60px rgba(30,44,70,.13);overflow:hidden;break-after:page}.pdf-watermark{position:absolute;left:50%;top:50%;width:75%;height:60%;transform:translate(-50%,-50%);object-fit:contain;object-position:center;pointer-events:none;z-index:0}.sheet>*:not(.pdf-watermark){position:relative;z-index:1}.gradient-bar{position:absolute!important;inset:0 0 auto;height:8px}.gradient-bar svg{display:block;width:100%;height:100%}.top{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;padding:8px 0 22px;border-bottom:1px solid #dfe5ee}.identity img{display:block;max-width:190px;max-height:64px;width:auto;height:auto;margin-bottom:14px}.brand-name{margin-bottom:14px;color:var(--pdf-primary);font-size:22px;font-weight:850;letter-spacing:-.02em}.identity h1{margin:0 0 6px;font-size:22px;line-height:1.2}.identity p{margin:0;color:var(--pdf-muted);font-size:11px}.doc-badge{min-width:128px;padding:14px;border:1px solid #dfe5ee;border-radius:14px;background:#f8fafc;text-align:right}.doc-badge small{display:block;color:var(--pdf-muted);font-size:10px;font-weight:800;letter-spacing:.02em}.doc-badge strong{display:block;margin-top:4px;font-size:20px}.summary{display:grid;grid-template-columns:1.25fr 1fr 1fr;gap:12px;margin:20px 0}.summary div{min-width:0;padding:13px 14px;border:1px solid #e3e8f0;border-radius:12px;background:#fbfcfe}.summary small,.answer dt{display:block;margin-bottom:5px;color:var(--pdf-muted);font-size:11px;font-weight:700;letter-spacing:0;text-transform:none}.summary strong{display:block;overflow-wrap:anywhere;font-size:13px}.answers-title{display:flex;align-items:center;gap:9px;margin:22px 0 5px;font-size:18px;font-weight:800;text-transform:none}.answers-title:after{content:"";height:1px;flex:1;background:#e3e8f0}.answers{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 24px;margin:0}.answers.is-company{grid-template-columns:repeat(6,minmax(0,1fr))}.answers.is-company .answer{grid-column:span 3}.answers.is-company .answer.business-third{grid-column:span 2}.answer{padding:13px 0;border-bottom:1px solid #edf0f4;break-inside:avoid}.answer.full{grid-column:1/-1}.answer dd{margin:0;white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px}.footer{display:flex;justify-content:flex-end;gap:20px;margin-top:auto;padding-top:16px;border-top:1px solid #dfe5ee;color:var(--pdf-muted);font-size:9px;text-align:right}.page-number:after{content:counter(page)}@media print{body{background:#fff;print-color-adjust:exact;-webkit-print-color-adjust:exact}.toolbar{display:none}.document{width:100%;margin:0}.sheet{width:210mm;min-height:297mm;margin:0;padding:18mm 16mm 13mm;border-radius:0;box-shadow:none;overflow:hidden}.gradient-bar{height:9mm}.top{padding-top:4mm}.sheet:last-child{break-after:auto}}@media(max-width:640px){.sheet{padding:25px 20px}.top{flex-direction:column}.doc-badge{text-align:left}.summary,.answers,.answers.is-company{grid-template-columns:1fr}.answers.is-company .answer,.answers.is-company .answer.business-third{grid-column:auto}.answer.full{grid-column:auto}}
</style></head><body><div class="toolbar"><button type="button" onclick="window.print()"><?php esc_html_e( 'Αποθήκευση ως PDF / Εκτύπωση', 'secure-form-vault' ); ?></button></div><main class="document">
<?php foreach ( $rows as $row ) : $payload=json_decode($row->payload,true);$payload=is_array($payload)?$payload:array();$summary=$this->submission_summary($payload,(int)$row->form_id);$form_title=get_the_title($row->form_id)?:__('Διαγραμμένη φόρμα','secure-form-vault');$submitted_local=get_date_from_gmt($row->submitted_at,'d/m/Y · '.get_option('time_format'));$field_titles=array();$privacy_field_keys=array();$form_fields=get_post_meta((int)$row->form_id,'_sfv_fields',true);if(is_array($form_fields)){foreach($form_fields as $form_field){$field_key=sanitize_key($form_field['key']??'');if(''===$field_key)continue;$placeholder=trim((string)($form_field['placeholder']??''));$field_label=trim((string)($form_field['label']??''));$checkbox_value=trim((string)($form_field['checkbox_value']??''));$title=''!==$placeholder?$placeholder:(''!==$field_label?$field_label:$field_key);$field_titles[$field_key]=preg_replace('/\s*\*\s*$/u','',$title);$privacy_probe=$this->xlsx_normalize_text($field_key.' '.$field_label.' '.$placeholder.' '.$checkbox_value);if('checkbox'===sanitize_key((string)($form_field['type']??''))&&(str_contains($privacy_probe,'πολιτικη απορρητου')||str_contains($privacy_probe,'privacy')||str_contains($privacy_probe,'gdpr')||str_contains($privacy_probe,'διαβασα κατανοησα και συμφωνω'))){$privacy_field_keys[$field_key]=true;}}} ?>
<section class="sheet"><?php if ( $pdf_watermark ) : ?><img class="pdf-watermark" src="<?php echo esc_url( $pdf_watermark ); ?>" alt="" aria-hidden="true" style="opacity:<?php echo esc_attr( number_format( $pdf_watermark_opacity, 2, '.', '' ) ); ?>"><?php endif; ?><div class="gradient-bar" aria-hidden="true"><svg viewBox="0 0 1000 20" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="sfvPdfGradient" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="<?php echo esc_attr($pdf_settings['pdf_primary']); ?>"/><stop offset="68%" stop-color="<?php echo esc_attr($pdf_settings['pdf_accent']); ?>"/><stop offset="100%" stop-color="<?php echo esc_attr($pdf_settings['pdf_accent']); ?>" stop-opacity=".55"/></linearGradient></defs><rect width="1000" height="20" fill="url(#sfvPdfGradient)"/></svg></div><header class="top"><div class="identity"><?php if ( $pdf_logo ) : ?><img src="<?php echo esc_url( $pdf_logo ); ?>" alt="<?php echo esc_attr( $pdf_brand ); ?>"><?php else : ?><div class="brand-name"><?php echo esc_html( $pdf_brand ); ?></div><?php endif; ?><h1><?php echo esc_html($form_title); ?></h1><p><?php echo esc_html($submitted_local); ?></p></div><div class="doc-badge"><small><?php esc_html_e('Submission ID','secure-form-vault'); ?></small><strong>#<?php echo absint($row->id); ?></strong></div></header>
<section class="summary"><div><small><?php esc_html_e('Πελάτης','secure-form-vault'); ?></small><strong><?php echo esc_html($summary['name']); ?></strong></div><div><small><?php esc_html_e('Προϊόν','secure-form-vault'); ?></small><strong><?php echo esc_html($summary['product']); ?></strong></div><div><small><?php esc_html_e('Ποσότητα','secure-form-vault'); ?></small><strong><?php echo esc_html($summary['quantity']); ?></strong></div></section>
<h2 class="answers-title"><?php esc_html_e('Στοιχεία αιτήματος','secure-form-vault'); ?></h2><?php
$print_items=array();$order_total_item=null;$privacy_item=null;$privacy_position=null;
foreach($payload as $key=>$item){
    $safe_item=is_array($item)?$item:array('label'=>'','value'=>(string)$item);
    $safe_key=sanitize_key((string)$key);
    $raw_label=trim((string)($field_titles[$safe_key]??''));
    if(''===$raw_label){$raw_label=trim((string)($safe_item['label']??''));}
    $raw_value=trim((string)($safe_item['value']??''));
    $label_probe=$this->xlsx_normalize_text($raw_label);
    if(str_contains($label_probe,'επιλεξτε προιον')){$raw_label=__('Προϊόν','secure-form-vault');}
    elseif(str_contains($label_probe,'επιλεξτε νομο')){$raw_label=__('Νομός','secure-form-vault');}
    elseif(str_contains($label_probe,'επιλεξτε πολη')){$raw_label=__('Πόλη','secure-form-vault');}
    $privacy_probe=$this->xlsx_normalize_text((string)$key.' '.$raw_label.' '.$raw_value);
    $is_privacy=isset($privacy_field_keys[$safe_key])||str_contains($privacy_probe,'πολιτικη απορρητου')||str_contains($privacy_probe,'privacy policy')||str_contains($privacy_probe,'gdpr')||str_contains($privacy_probe,'διαβασα κατανοησα και συμφωνω');
    if($is_privacy){$privacy_position=count($print_items);$privacy_item=array('key'=>$key,'item'=>array('label'=>'GDPR','value'=>__('Ναι','secure-form-vault')),'label'=>'GDPR');continue;}
    $canonical=$this->xlsx_canonical_field_key((string)$key,$raw_label);
    if('__order_total'===$canonical){$order_total_item=array('key'=>'__order_total','item'=>$safe_item,'label'=>__('Σύνολο παραγγελίας','secure-form-vault'));continue;}
    $print_items[]=array('key'=>$key,'item'=>$safe_item,'label'=>$raw_label);
}
if(empty($summary['is_company'])&&!$order_total_item){
    $quantity_for_pdf=max(0,(float)str_replace(',','.',(string)('—'===$summary['quantity']?0:$summary['quantity'])));
    $calculated_total=$this->order_price_details((int)$row->form_id,(string)('—'===$summary['product']?'':$summary['product']),$quantity_for_pdf);
    if(null!==$calculated_total){$order_total_item=array('key'=>'__order_total','item'=>array('value'=>number_format((float)$calculated_total['total'],2,',','.') . ' €'),'label'=>__('Σύνολο παραγγελίας','secure-form-vault'));}
}
if(empty($summary['is_company'])&&$order_total_item){
    $insert_at=null!==$privacy_position?min($privacy_position,count($print_items)):count($print_items);
    array_splice($print_items,$insert_at,0,array($order_total_item));
}

// Business PDFs start with the operational fields in the requested rows:
// customer type | company | responsible person,
// VAT | email | phone, product | quantity | delivery date,
// region | city | address. Remaining fields retain their original order.
if(!empty($summary['is_company'])){
    $business_order=array('customer_type','company','responsible','vat','email','phone','product','quantity','delivery_date','region','city','address');
    $business_items=array();$business_remaining=array();
    foreach($print_items as $print_item){
        $item_key=(string)($print_item['key']??'');$item_label=(string)($print_item['label']??'');
        $stored_label=is_array($print_item['item']??null)?(string)($print_item['item']['label']??''):'';
        $probe=$this->xlsx_normalize_text($item_key.' '.$item_label.' '.$stored_label);$slot='';
        if(str_contains($probe,'τυπος πελατη')||str_contains($probe,'customer type')){$slot='customer_type';$print_item['label']=__('Τύπος Πελάτη','secure-form-vault');}
        elseif(str_contains($probe,'επωνυμια')||str_contains($probe,'company name')||str_contains($probe,'business name')){$slot='company';$print_item['label']=__('Επωνυμία','secure-form-vault');}
        elseif(str_contains($probe,'υπευθυν')||str_contains($probe,'responsible')||str_contains($probe,'contact person')){$slot='responsible';$print_item['label']=__('Ονοματεπώνυμο Υπευθύνου','secure-form-vault');}
        elseif(str_contains($probe,'αφμ')||str_contains($probe,'vat')||str_contains($probe,'tax id')){$slot='vat';$print_item['label']=__('ΑΦΜ','secure-form-vault');}
        elseif(str_contains($probe,'email')||str_contains($probe,'e mail')){$slot='email';$print_item['label']=__('Email','secure-form-vault');}
        elseif(str_contains($probe,'τηλεφων')||str_contains($probe,'phone')||str_contains($probe,'mobile')){$slot='phone';$print_item['label']=__('Τηλέφωνο','secure-form-vault');}
        elseif(str_contains($probe,'προιον')||str_contains($probe,'product')||str_contains($probe,'fuel')){$slot='product';$print_item['label']=__('Προϊόν','secure-form-vault');}
        elseif(str_contains($probe,'ποσοτητα')||str_contains($probe,'quantity')||str_contains($probe,'λιτρα')){$slot='quantity';$print_item['label']=__('Ποσότητα','secure-form-vault');}
        elseif(str_contains($probe,'deliverydate')||str_contains($probe,'ημ/νια παραδοσης')||str_contains($probe,'ημερομηνια παραδοσης')||str_contains($probe,'επιθυμητη ημερομηνια')||str_contains($probe,'delivery date')){$slot='delivery_date';$print_item['label']=__('Ημερομηνία Παράδοσης','secure-form-vault');}
        elseif(str_contains($probe,'νομος')||str_contains($probe,'region')){$slot='region';$print_item['label']=__('Νομός','secure-form-vault');}
        elseif(str_contains($probe,'πολη')||str_contains($probe,'city')){$slot='city';$print_item['label']=__('Πόλη','secure-form-vault');}
        elseif(str_contains($probe,'διευθυν')||str_contains($probe,'address')||str_contains($probe,'street')){$slot='address';$print_item['label']=__('Διεύθυνση','secure-form-vault');}
        if(''!==$slot){
            $print_item['pdf_slot']=$slot;
            $new_value=trim((string)($print_item['item']['value']??''));
            $current_value=isset($business_items[$slot])?trim((string)($business_items[$slot]['item']['value']??'')):'';
            if(!isset($business_items[$slot])||(''===$current_value&&''!==$new_value)){$business_items[$slot]=$print_item;}
            continue;
        }
        $business_remaining[]=$print_item;
    }
    $print_items=array();
    foreach($business_order as $slot){if(isset($business_items[$slot])){$print_items[]=$business_items[$slot];}}
    $print_items=array_merge($print_items,$business_remaining);
}

// Residential PDFs use a stable two-column operational layout:
// customer type | name, VAT | meter, phone | address,
// fuel quantity | total, delivery date | email, message | GDPR.
if(empty($summary['is_company'])){
    if($privacy_item){$print_items[]=$privacy_item;}
    $residential_order=array('customer_type','name','vat','meter','phone','address','quantity','order_total','delivery_date','email','message','gdpr');
    $residential_items=array();$residential_remaining=array();
    foreach($print_items as $print_item){
    $item_key=(string)($print_item['key']??'');
    $item_label=(string)($print_item['label']??'');
    $stored_label=is_array($print_item['item']??null)?(string)($print_item['item']['label']??''):'';
    $probe=$this->xlsx_normalize_text($item_key.' '.$item_label.' '.$stored_label);
    $canonical=$this->xlsx_canonical_field_key($item_key,$item_label);
    $slot='';
    if(str_contains($probe,'τυπος πελατη')||str_contains($probe,'customer type')){$slot='customer_type';$print_item['label']=__('Τύπος Πελάτη','secure-form-vault');}
    elseif(str_contains($probe,'ονοματεπωνυμο')&&!str_contains($probe,'υπευθυν')&&!str_contains($probe,'εκπροσωπ')){$slot='name';$print_item['label']=__('Ονοματεπώνυμο','secure-form-vault');}
    elseif(str_contains($probe,'αφμ')||str_contains($probe,'vat')||str_contains($probe,'tax id')){$slot='vat';$print_item['label']=__('ΑΦΜ','secure-form-vault');}
    elseif(str_contains($probe,'μετρητη δεδδηε')||str_contains($probe,'deddie')||str_contains($probe,'meter')){$slot='meter';$print_item['label']=__('Αριθμός Μετρητή ΔΕΔΔΗΕ','secure-form-vault');}
    elseif(str_contains($probe,'τηλεφων')||str_contains($probe,'phone')||str_contains($probe,'mobile')){$slot='phone';$print_item['label']=__('Τηλέφωνο','secure-form-vault');}
    elseif(str_contains($probe,'διευθυν')||str_contains($probe,'address')||str_contains($probe,'οδος')||str_contains($probe,'street')){$slot='address';$print_item['label']=__('Διεύθυνση','secure-form-vault');}
    elseif('__order_total'===$canonical||'__order_total'===$item_key){$slot='order_total';$print_item['label']=__('Σύνολο Παραγγελίας','secure-form-vault');}
    elseif('__quantity'===$canonical||str_contains($probe,'ποσοτητα')||str_contains($probe,'quantity')||str_contains($probe,'λιτρα')||str_contains($probe,'litres')||str_contains($probe,'liters')){
        $slot='quantity';$print_item['label']=__('Ποσότητα Πετρελαίου','secure-form-vault');
    }elseif(str_contains($probe,'deliverydate')||str_contains($probe,'ημ/νια παραδοσης')||str_contains($probe,'ημερομηνια παραδοσης')||str_contains($probe,'επιθυμητη ημερομηνια')||str_contains($probe,'delivery date')){$slot='delivery_date';$print_item['label']=__('Ημερομηνία Παράδοσης','secure-form-vault');}
    elseif(str_contains($probe,'email')||str_contains($probe,'e mail')||str_contains($probe,'ηλεκτρονικο ταχυδρομειο')){$slot='email';$print_item['label']=__('Email','secure-form-vault');}
    elseif(str_contains($probe,'μηνυμα')||str_contains($probe,'message')||str_contains($probe,'comments')){$slot='message';$print_item['label']=__('Μήνυμα','secure-form-vault');}
    elseif('gdpr'===$item_key||str_contains($probe,'gdpr')||str_contains($probe,'πολιτικη απορρητου')){$slot='gdpr';$print_item['label']='GDPR';}
    if(''!==$slot){
        $print_item['pdf_slot']=$slot;
        $new_value=trim((string)($print_item['item']['value']??''));
        $current_value=isset($residential_items[$slot])?trim((string)($residential_items[$slot]['item']['value']??'')):'';
        if(!isset($residential_items[$slot])||(''===$current_value&&''!==$new_value)){$residential_items[$slot]=$print_item;}
        continue;
    }
    $residential_remaining[]=$print_item;
    }
    $print_items=array();
    foreach($residential_order as $slot){if(isset($residential_items[$slot])){$print_items[]=$residential_items[$slot];}}
    $print_items=array_merge($print_items,$residential_remaining);
}
?><dl class="answers<?php echo !empty($summary['is_company'])?' is-company':''; ?>"><?php foreach($print_items as $print_item):$key=$print_item['key'];$item=$print_item['item'];$label=trim((string)$print_item['label']);$value=trim((string)($item['value']??''));if(''===$value)continue;if(''===$label){$label=(string)$key;}
$display_probe=$this->xlsx_normalize_text((string)$key.' '.$label);
// All dates in the PDF use the unambiguous Greek format dd/mm/yyyy.
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)){$date_object=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if($date_object){$value=$date_object->format('d/m/Y');}}
// Currency values use a dot for thousands and a comma for decimals.
if('__order_total'===(string)$key||str_contains($display_probe,'συνολο')||str_contains($display_probe,'τιμη')||str_contains($display_probe,'ποσο')||str_contains($value,'€')){
    $money_text=preg_replace('/[^0-9,.-]/u','',$value);
    if(''!==$money_text&&preg_match('/-?\d/',$money_text)){
        if(str_contains($money_text,',')&&str_contains($money_text,'.')){$money_text=str_replace('.','',$money_text);$money_text=str_replace(',','.',$money_text);}elseif(str_contains($money_text,',')){$money_text=str_replace(',','.',$money_text);}
        if(is_numeric($money_text)){$value=number_format((float)$money_text,2,',','.') . ' €';}
    }
}
$residential_slot=empty($summary['is_company'])&&in_array((string)($print_item['pdf_slot']??''),array('customer_type','name','vat','meter','phone','address','quantity','order_total','delivery_date','email','message','gdpr'),true);$full=!$residential_slot&&(strlen($value)>85||str_contains($value,"\n"));$third=!empty($summary['is_company'])&&in_array((string)($print_item['pdf_slot']??''),array('customer_type','company','responsible','vat','email','phone','product','quantity','delivery_date','region','city','address'),true);?><div class="answer<?php echo $full?' full':''; ?><?php echo $third?' business-third':''; ?>"><dt><?php echo esc_html($label); ?></dt><dd><?php echo nl2br(esc_html($value)); ?></dd></div><?php endforeach; ?></dl>
<footer class="footer"><span><?php echo esc_html(sprintf(__('%1$s · Δημιουργήθηκε με GRFuel Manager','secure-form-vault'),$pdf_brand)); ?></span></footer></section>
<?php endforeach; ?></main><script>window.addEventListener('load',function(){var run=function(){setTimeout(function(){window.print()},120)};if(document.fonts&&document.fonts.ready){document.fonts.ready.then(run).catch(run);}else{run();}});</script></body></html><?php exit;
    }

    public function delete_submission(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::ADMIN_CAPABILITY ) ) {
            wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) );
        }
        $id = absint( $_POST['id'] ?? 0 );
        check_admin_referer( 'sfv_delete_' . $id );
        if ( ! $id ) {
            wp_die( esc_html__( 'Invalid submission.', 'secure-form-vault' ) );
        }
        global $wpdb;
        $deleted = $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
        $status = 1 === $deleted ? 'deleted' : 'not_found';
        wp_safe_redirect( add_query_arg( 'sfv_status', $status, admin_url( 'admin.php?page=sfv-submissions' ) ) );
        exit;
    }

    public function clear_submissions(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::ADMIN_CAPABILITY ) ) { wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) ); }
        check_admin_referer( 'sfv_clear_submissions' );
        global $wpdb;
        $result = $wpdb->query( "DELETE FROM {$this->table()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $status = false === $result ? 'maintenance_error' : 'cleared';
        wp_safe_redirect( add_query_arg( 'sfv_status', $status, admin_url( 'admin.php?page=sfv-submissions' ) ) );
        exit;
    }

    public function reset_submission_id(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::ADMIN_CAPABILITY ) ) { wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) ); }
        check_admin_referer( 'sfv_reset_submission_id' );
        global $wpdb;
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $count > 0 ) {
            wp_safe_redirect( add_query_arg( 'sfv_status', 'reset_blocked', admin_url( 'admin.php?page=sfv-submissions' ) ) );
            exit;
        }
        $result = $wpdb->query( "ALTER TABLE {$this->table()} AUTO_INCREMENT = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $status = false === $result ? 'maintenance_error' : 'reset';
        wp_safe_redirect( add_query_arg( 'sfv_status', $status, admin_url( 'admin.php?page=sfv-submissions' ) ) );
        exit;
    }

    public function export_submissions(): void {
        $this->require_post_request();
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'Δεν επιτρέπεται η πρόσβαση.', 'secure-form-vault' ) ); }
        check_admin_referer( 'sfv_export' );
        global $wpdb;
        $where = array( '1=1' ); $args = array();
        $form_id = absint( $_POST['form_id'] ?? 0 );
        $id_from = absint( $_POST['id_from'] ?? 0 ); $id_to = absint( $_POST['id_to'] ?? 0 );
        $date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
        $date_to = sanitize_text_field( wp_unslash( $_POST['date_to'] ?? '' ) );
        if ( $form_id ) { $where[] = 'form_id = %d'; $args[] = $form_id; }
        if ( $id_from ) { $where[] = 'id >= %d'; $args[] = $id_from; }
        if ( $id_to ) { $where[] = 'id <= %d'; $args[] = $id_to; }
        if ( $this->valid_date( $date_from ) ) { $where[] = 'submitted_at >= %s'; $args[] = $date_from . ' 00:00:00'; }
        if ( $this->valid_date( $date_to ) ) { $where[] = 'submitted_at <= %s'; $args[] = $date_to . ' 23:59:59'; }
        $sql = "SELECT * FROM {$this->table()} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id ASC LIMIT 100000';
        if ( $args ) { $sql = $wpdb->prepare( $sql, $args ); }
        $rows = $wpdb->get_results( $sql );
        $format = sanitize_key( $_POST['format'] ?? 'xlsx' );
        'xml' === $format ? $this->output_xml( $rows ) : $this->output_xlsx( $rows );
    }

    private function valid_date( string $date ): bool {
        $d = DateTime::createFromFormat( 'Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) === $date;
    }

    private function export_headers( string $type, string $ext ): void {
        $this->send_private_response_headers();
        $site_title = html_entity_decode( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        // Remove characters forbidden in download filenames without a regular
        // expression: an escaped slash inside the old character class could be
        // interpreted as the pattern delimiter on some PHP/PCRE combinations.
        $site_title = str_replace( array( '\\', '/', ':', '*', '?', '"', '<', '>', '|' ), '', $site_title );
        $site_title = preg_replace( '/\s+/u', '-', trim( $site_title ) ) ?? '';
        if ( '' === $site_title ) $site_title = 'GRFuel';
        $filename = $site_title . '-Παραγγελίες-' . current_time( 'dmy' ) . '.' . $ext;
        $ascii_fallback = sanitize_title( $site_title ) . '-orders-' . current_time( 'dmy' ) . '.' . $ext;
        if ( '' === sanitize_title( $site_title ) ) $ascii_fallback = 'grfuel-orders-' . current_time( 'dmy' ) . '.' . $ext;
        header( 'Content-Type: ' . $type );
        header( 'Content-Disposition: attachment; filename="' . $ascii_fallback . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
        header( 'X-Content-Type-Options: nosniff' );
    }

    private function output_xlsx( array $rows ): void {
        if ( ! class_exists( 'ZipArchive' ) ) {
            wp_die( esc_html__( 'XLSX export requires the PHP Zip extension (ZipArchive). Please ask the hosting provider to enable it.', 'secure-form-vault' ) );
        }

        $temp_file = wp_tempnam( 'sfv-export.xlsx' );
        if ( ! $temp_file ) {
            wp_die( esc_html__( 'The export file could not be created.', 'secure-form-vault' ) );
        }

        $sheet_file = wp_tempnam( 'sfv-sheet.xml' );
        if ( ! $sheet_file ) {
            @unlink( $temp_file );
            wp_die( esc_html__( 'The worksheet could not be created.', 'secure-form-vault' ) );
        }

        /*
         * Build one clean row per order. Columns follow the form-builder order.
         * Mutually exclusive customer-name and product fields are consolidated,
         * so domestic and business orders do not create unnecessary empty cells.
         */
        $field_columns = array();
        $prepared_rows = array();
        $form_column_cache = array();

        foreach ( $rows as $row ) {
            $form_id = absint( $row->form_id ?? 0 );
            $payload = json_decode( $row->payload, true );
            if ( ! is_array( $payload ) ) {
                $payload = array();
            }

            if ( ! isset( $form_column_cache[ $form_id ] ) ) {
                $form_column_cache[ $form_id ] = $this->xlsx_form_columns( $form_id, $payload );
            }
            foreach ( $form_column_cache[ $form_id ] as $key => $label ) {
                if ( ! isset( $field_columns[ $key ] ) ) {
                    $field_columns[ $key ] = $label;
                }
            }

            // Include legacy or removed fields after the current form fields.
            foreach ( $payload as $key => $item ) {
                $key = sanitize_key( (string) $key );
                if ( '' === $key ) continue;
                $canonical = $this->xlsx_canonical_field_key( $key, (string) ( $item['label'] ?? '' ) );
                if ( '__skip' === $canonical ) continue;
                if ( ! isset( $field_columns[ $canonical ] ) ) {
                    $field_columns[ $canonical ] = $this->xlsx_column_label( $canonical, (string) ( $item['label'] ?? $key ) );
                }
            }

            $values = array();
            foreach ( $payload as $key => $item ) {
                $key = sanitize_key( (string) $key );
                if ( '' === $key ) continue;
                $canonical = $this->xlsx_canonical_field_key( $key, (string) ( $item['label'] ?? '' ) );
                if ( '__skip' === $canonical ) continue;
                $value = $item['value'] ?? '';
                if ( is_array( $value ) ) {
                    $value = implode( ', ', array_map( 'strval', $value ) );
                }
                $value = trim( (string) $value );
                if ( '' !== $value && ! isset( $values[ $canonical ] ) ) {
                    $values[ $canonical ] = $value;
                }
            }

            $customer_type = $this->payload_value_by_terms( $payload, array( 'τυπος πελατη', 'τύπος πελάτη', 'customer type', 'customer_type' ) );
            $customer_type_normalized = $this->xlsx_normalize_text( $customer_type );
            $is_company = (bool) preg_match( '/εταιρ|εταιρει|επιχειρ|company|business|επαγγελ|νομικ|corporate|enterprise/u', $customer_type_normalized );
            $company = $this->payload_value_by_terms( $payload, array( 'επωνυμια', 'επωνυμία', 'εταιρεια', 'εταιρεία', 'company name', 'business_name' ) );
            $full_name = $this->payload_value_by_terms( $payload, array( 'ονοματεπωνυμο', 'ονοματεπώνυμο', 'ονομα και επωνυμο', 'όνομα και επώνυμο', 'ονομα πελατη', 'όνομα πελάτη', 'customer name', 'full name', 'fullname', 'full_name' ) );
            $first = $this->payload_value_by_terms( $payload, array( 'ονομα', 'όνομα', 'first name', 'first_name' ) );
            $last = $this->payload_value_by_terms( $payload, array( 'επωνυμο', 'επώνυμο', 'surname', 'last name', 'last_name' ) );
            $person = trim( $full_name ?: trim( $first . ' ' . $last ) );
            $is_company = $is_company || ( '' !== $company && '' === $person );
            $customer = $is_company && $company ? $company : ( $person ?: $company );
            if ( '' !== $customer ) {
                $values['__customer'] = $customer;
            }

            $summary = $this->submission_summary( $payload, $form_id );
            $resolved_product = $this->xlsx_resolve_product( $payload, $form_id );
            if ( '' === $resolved_product && '—' !== $summary['product'] ) {
                $resolved_product = trim( (string) $summary['product'] );
            }
            if ( '' !== $resolved_product ) {
                $values['__product_order'] = $resolved_product;
            }
            if ( '—' !== $summary['quantity'] && '' !== trim( (string) $summary['quantity'] ) ) {
                $values['__quantity'] = trim( (string) $summary['quantity'] );
            }
            if ( empty( $values['__order_total'] ) ) {
                $quantity_number = max( 0, (float) str_replace( ',', '.', (string) ( '—' === $summary['quantity'] ? 0 : $summary['quantity'] ) ) );
                $price_details = $this->order_price_details( $form_id, $resolved_product, $quantity_number );
                if ( null !== $price_details ) {
                    $values['__order_total'] = number_format( (float) $price_details['total'], 2, '.', '' ) . ' €';
                }
            }

            $submitted_at = (string) $row->submitted_at;
            if ( function_exists( 'get_date_from_gmt' ) ) {
                $submitted_at = get_date_from_gmt( $submitted_at, 'd/m/Y H:i' );
            }

            $prepared_rows[] = array(
                'id'           => (string) $row->id,
                'submitted_at' => $submitted_at,
                'field_values' => $values,
                'row_style'    => $is_company ? 3 : 2,
            );
        }

        // Final normalization pass. This deliberately re-checks the visible
        // labels too, so legacy forms cannot create duplicate customer/privacy
        // columns even when their stored field keys are unusual.
        $column_aliases = array();
        $normalized_columns = array();
        foreach ( $field_columns as $key => $label ) {
            $final_key = $this->xlsx_canonical_field_key( (string) $key, (string) $label );
            $normalized_label = $this->xlsx_normalize_text( (string) $label );
            $is_customer_type_label = str_contains( $normalized_label, 'τυπος πελατη' ) || str_contains( $normalized_label, 'customer type' );
            if ( ! $is_customer_type_label && ( str_contains( $normalized_label, 'ονοματεπωνυμο' ) || str_contains( $normalized_label, 'επωνυμια' ) || $normalized_label === 'πελατης' ) ) {
                $final_key = '__customer';
            }
            if ( str_contains( $normalized_label, 'πολιτικη απορρητου' ) || str_contains( $normalized_label, 'privacy' ) ) {
                $final_key = '__privacy';
            }
            if ( '__skip' === $final_key ) continue;
            $column_aliases[ $key ] = $final_key;
            if ( ! isset( $normalized_columns[ $final_key ] ) ) {
                $normalized_columns[ $final_key ] = $this->xlsx_column_label( $final_key, (string) $label );
            }
        }
        $field_columns = $normalized_columns;

        foreach ( $prepared_rows as &$prepared_row ) {
            $remapped_values = array();
            foreach ( $prepared_row['field_values'] as $value_key => $value ) {
                $final_key = $column_aliases[ $value_key ] ?? $this->xlsx_canonical_field_key( (string) $value_key, (string) $value_key );
                if ( '__skip' === $final_key ) continue;
                if ( '' !== trim( (string) $value ) && ! isset( $remapped_values[ $final_key ] ) ) {
                    $remapped_values[ $final_key ] = $value;
                }
            }
            $prepared_row['field_values'] = $remapped_values;
        }
        unset( $prepared_row );

        // Keep the form order, but always group the order data as:
        // Προϊόν παραγγελίας → Ποσότητα → Τιμή παραγγελίας.
        $ordered_columns = array();
        foreach ( $field_columns as $key => $label ) {
            if ( in_array( $key, array( '__quantity', '__order_total' ), true ) ) continue;
            $ordered_columns[ $key ] = $label;
            if ( '__product_order' === $key ) {
                $ordered_columns['__quantity'] = 'Ποσότητα';
                $ordered_columns['__order_total'] = 'Τιμή παραγγελίας';
            }
        }
        if ( ! isset( $ordered_columns['__product_order'] ) ) {
            $ordered_columns['__product_order'] = 'Προϊόν παραγγελίας';
            $ordered_columns['__quantity'] = 'Ποσότητα';
            $ordered_columns['__order_total'] = 'Τιμή παραγγελίας';
        }
        $field_columns = $ordered_columns;

        $headers = array_merge( array( 'Α/Α' ), array_values( $field_columns ) );
        $sheet = fopen( $sheet_file, 'wb' );
        if ( false === $sheet ) {
            @unlink( $temp_file );
            @unlink( $sheet_file );
            wp_die( esc_html__( 'The worksheet could not be written.', 'secure-form-vault' ) );
        }

        $column_count = max( 1, count( $headers ) );
        $last_column = $this->xlsx_column_name( $column_count );
        $cols_xml = '<cols>';
        foreach ( $headers as $index => $header ) {
            $column_number = $index + 1;
            if ( 1 === $column_number ) {
                $width = 11;
            } else {
                $plain_header = html_entity_decode( wp_strip_all_tags( (string) $header ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                $header_length = function_exists( 'mb_strlen' ) ? mb_strlen( $plain_header, 'UTF-8' ) : strlen( $plain_header );
                // Excel's width unit is narrower than an average Greek glyph. Add
                // enough breathing room so every heading is visible without a manual resize.
                $width = min( 80, max( 16, (int) ceil( $header_length * 1.35 ) + 4 ) );
            }
            $cols_xml .= '<col min="' . $column_number . '" max="' . $column_number . '" width="' . $width . '" customWidth="1"/>';
        }
        $cols_xml .= '</cols>';

        fwrite( $sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' );
        fwrite( $sheet, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="20"/>' . $cols_xml . '<sheetData>' );

        $row_number = 1;
        fwrite( $sheet, $this->xlsx_row_xml( $row_number, $headers, 1 ) );
        foreach ( $prepared_rows as $prepared ) {
            ++$row_number;
            $data = array( $prepared['id'] );
            foreach ( array_keys( $field_columns ) as $key ) {
                $value = trim( (string) ( $prepared['field_values'][ $key ] ?? '' ) );
                $data[] = '' === $value ? '—' : $value;
            }
            fwrite( $sheet, $this->xlsx_row_xml( $row_number, $data, (int) $prepared['row_style'] ) );
        }

        fwrite( $sheet, '</sheetData><pageMargins left="0.35" right="0.35" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>' );
        fclose( $sheet );

        $zip = new ZipArchive();
        if ( true !== $zip->open( $temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            @unlink( $temp_file );
            @unlink( $sheet_file );
            wp_die( esc_html__( 'The Excel archive could not be created.', 'secure-form-vault' ) );
        }

        $zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>' );
        $zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>' );
        $zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Παραγγελίες" sheetId="1" r:id="rId1"/></sheets></workbook>' );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
        $zip->addFromString( 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF172033"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF2FAF4"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF2F7FC"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color indexed="64"/></left><right style="thin"><color indexed="64"/></right><top style="thin"><color indexed="64"/></top><bottom style="thin"><color indexed="64"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>' );
        $zip->addFromString( 'docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>GRFuel Manager</Application></Properties>' );
        $created = gmdate( 'Y-m-d\TH:i:s\Z' );
        $zip->addFromString( 'docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>GRFuel Manager</dc:creator><cp:lastModifiedBy>GRFuel Manager</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified></cp:coreProperties>' );
        $zip->addFile( $sheet_file, 'xl/worksheets/sheet1.xml' );
        $zip->close();
        @unlink( $sheet_file );

        $this->export_headers( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx' );
        header( 'Content-Length: ' . (string) filesize( $temp_file ) );
        readfile( $temp_file );
        @unlink( $temp_file );
        exit;
    }

    private function xlsx_form_columns( int $form_id, array $payload ): array {
        $columns = array();
        $fields = $form_id > 0 ? get_post_meta( $form_id, '_sfv_fields', true ) : array();
        $fields = is_array( $fields ) ? $fields : array();
        $product_keys = array();
        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) || 'price_calculator' !== ( $field['type'] ?? '' ) ) continue;
            $product_key = sanitize_key( (string) ( $field['product_field'] ?? '' ) );
            if ( '' !== $product_key ) $product_keys[] = $product_key;
        }

        $non_export_types = array( 'heading', 'content_text', 'divider', 'submit', 'price_calculator', 'single_price_calculator' );
        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) continue;
            $type = sanitize_key( (string) ( $field['type'] ?? '' ) );
            if ( in_array( $type, array( 'price_calculator', 'single_price_calculator' ), true ) ) {
                if ( ! isset( $columns['__product_order'] ) ) $columns['__product_order'] = 'Προϊόν παραγγελίας';
                if ( ! isset( $columns['__quantity'] ) ) $columns['__quantity'] = 'Ποσότητα';
                if ( ! isset( $columns['__order_total'] ) ) $columns['__order_total'] = 'Τιμή παραγγελίας';
                continue;
            }
            if ( in_array( $type, $non_export_types, true ) || 'date' === $type ) continue;
            $key = sanitize_key( (string) ( $field['key'] ?? '' ) );
            if ( '' === $key ) continue;
            $label = trim( (string) ( $field['label'] ?? '' ) );
            $canonical = in_array( $key, $product_keys, true ) ? '__product_order' : $this->xlsx_canonical_field_key( $key, $label );
            if ( '__skip' === $canonical ) continue;
            if ( ! isset( $columns[ $canonical ] ) ) {
                $column_label = $label ?: $key;
                if ( 'checkbox' === $type ) {
                    $plain_checkbox_label = html_entity_decode( wp_strip_all_tags( $label ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                    $checkbox_haystack = $this->xlsx_normalize_text( $key . ' ' . $plain_checkbox_label . ' ' . (string) ( $field['checkbox_value'] ?? '' ) );
                    $column_label = ( str_contains( $checkbox_haystack, 'πολιτικη απορρητου' ) || str_contains( $checkbox_haystack, 'privacy' ) )
                        ? 'Πολιτική Απορρήτου'
                        : ( $plain_checkbox_label ?: (string) ( $field['checkbox_value'] ?? $key ) );
                }
                $columns[ $canonical ] = $this->xlsx_column_label( $canonical, $column_label );
            }
        }
        return $columns;
    }

    private function xlsx_canonical_field_key( string $key, string $label ): string {
        $normalized_key = $this->xlsx_normalize_text( $key );
        $normalized_label = $this->xlsx_normalize_text( html_entity_decode( wp_strip_all_tags( $label ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        $haystack = trim( $normalized_key . ' ' . $normalized_label );

        foreach ( array( 'security verification', 'security_verification', 'security-verification', 'επαληθευση ασφαλειας' ) as $term ) {
            if ( str_contains( $haystack, $this->xlsx_normalize_text( $term ) ) ) return '__skip';
        }

        // The export already includes the submission timestamp. A date input from
        // the form would create a second date column and unnecessarily widen files.
        if ( in_array( $normalized_key, array( 'date', 'submission_date', 'order_date', 'ημερομηνια' ), true )
            || in_array( $normalized_label, array( 'ημερομηνια', 'ημερομηνια υποβολης', 'submission date', 'order date' ), true ) ) {
            return '__skip';
        }

        if ( '_sfv_order_total' === $key ) return '__order_total';

        if ( str_contains( $haystack, 'πολιτικη απορρητου' ) || str_contains( $haystack, 'privacy policy' ) || str_contains( $haystack, 'privacy' ) || str_contains( $haystack, 'διαβασα κατανοησα και συμφωνω' ) ) {
            return '__privacy';
        }

        // Consolidate every personal/company name field into one customer column.
        // Exclude the customer-type selector, which must remain a separate column.
        $is_customer_type = str_contains( $haystack, 'τυπος πελατη' ) || str_contains( $haystack, 'customer type' ) || str_contains( $haystack, 'customer_type' );
        $customer_exact = array( 'πελατης', 'ονοματεπωνυμο', 'ονομα πελατη', 'επωνυμια επιχειρησης', 'επωνυμια', 'εταιρεια', 'company name', 'business name', 'business_name', 'fullname', 'full_name', 'customer name', 'customer' );
        if ( ! $is_customer_type && ( in_array( $normalized_key, $customer_exact, true ) || in_array( $normalized_label, $customer_exact, true ) ) ) {
            return '__customer';
        }
        if ( ! $is_customer_type ) {
            foreach ( array( 'ονοματεπωνυμο', 'ονομα πελατη', 'επωνυμια επιχειρησης', 'επωνυμια', 'company name', 'business name', 'customer name', 'πελατης' ) as $term ) {
                if ( str_contains( $haystack, $term ) ) return '__customer';
            }
        }

        foreach ( array( 'product order', 'product_order', 'προιον παραγγελιας', 'επιλογη προιοντος', 'επιλεγμενο προιον', 'fuel product' ) as $term ) {
            if ( str_contains( $haystack, $this->xlsx_normalize_text( $term ) ) ) return '__product_order';
        }
        foreach ( array( 'number quantity', 'number_quantity', 'ποσοτητα', 'quantity', 'litres', 'liters', 'λιτρα' ) as $term ) {
            if ( str_contains( $haystack, $this->xlsx_normalize_text( $term ) ) ) return '__quantity';
        }
        return sanitize_key( $key );
    }

    private function xlsx_normalize_text( string $value ): string {
        $value = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $value = remove_accents( $value );
        $value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
        // WordPress remove_accents() does not consistently fold Greek tonos and
        // dialytika. Normalize them explicitly so business/customer labels and
        // values compare identically across hosts and PHP builds.
        $value = strtr( $value, array(
            'ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ϊ' => 'ι', 'ΐ' => 'ι',
            'ό' => 'ο', 'ύ' => 'υ', 'ϋ' => 'υ', 'ΰ' => 'υ', 'ώ' => 'ω',
        ) );
        $value = preg_replace( '/[^\p{L}\p{N}_]+/u', ' ', $value ) ?? '';
        return trim( preg_replace( '/\s+/u', ' ', $value ) ?? '' );
    }

    private function xlsx_resolve_product( array $payload, int $form_id ): string {
        // The submitted order value is authoritative. A form may contain both a
        // business product dropdown and a residential single-product calculator;
        // the fixed calculator label must never overwrite the selected dropdown.
        foreach ( $payload as $payload_key => $item ) {
            if ( ! is_array( $item ) ) continue;
            $label = (string) ( $item['label'] ?? '' );
            $canonical = $this->xlsx_canonical_field_key( (string) $payload_key, $label );
            $value = trim( (string) ( $item['value'] ?? '' ) );
            if ( '__product_order' === $canonical && '' !== $value ) return $value;
        }

        $fields = $form_id > 0 ? get_post_meta( $form_id, '_sfv_fields', true ) : array();
        $fields = is_array( $fields ) ? $fields : array();
        $known_products = array();

        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) continue;
            $type = sanitize_key( (string) ( $field['type'] ?? '' ) );
            if ( ! in_array( $type, array( 'price_calculator', 'single_price_calculator' ), true ) ) continue;

            $rows = $this->sanitize_pricing_rows( $field['pricing_rows'] ?? array() );
            foreach ( $rows as $price_row ) {
                $name = trim( (string) ( $price_row['product'] ?? '' ) );
                if ( '' !== $name ) $known_products[] = $name;
            }

            if ( 'single_price_calculator' === $type ) {
                $fixed = trim( (string) ( $field['product_label'] ?? '' ) );
                if ( '' === $fixed && $rows ) $fixed = trim( (string) ( $rows[0]['product'] ?? '' ) );
                if ( '' !== $fixed ) return $fixed;
            }

            // product_field stores the form field key. Compare both raw and
            // sanitized forms because old forms/submissions may use either.
            $raw_product_key = trim( (string) ( $field['product_field'] ?? '' ) );
            $candidate_keys = array_values( array_unique( array_filter( array(
                $raw_product_key,
                sanitize_key( $raw_product_key ),
            ) ) ) );
            foreach ( $candidate_keys as $candidate_key ) {
                if ( isset( $payload[ $candidate_key ] ) ) {
                    $value = trim( (string) ( $payload[ $candidate_key ]['value'] ?? '' ) );
                    if ( '' !== $value ) return $value;
                }
            }

            // Last-resort key matching for legacy payloads.
            if ( '' !== $raw_product_key ) {
                $wanted_key = $this->xlsx_normalize_text( $raw_product_key );
                foreach ( $payload as $payload_key => $item ) {
                    if ( $this->xlsx_normalize_text( (string) $payload_key ) === $wanted_key ) {
                        $value = trim( (string) ( $item['value'] ?? '' ) );
                        if ( '' !== $value ) return $value;
                    }
                }
            }
        }

        // Compatibility with older submissions/forms: match the submitted value
        // against one of the products configured in the calculator.
        foreach ( $payload as $item ) {
            $value = trim( (string) ( $item['value'] ?? '' ) );
            if ( '' === $value ) continue;
            foreach ( $known_products as $product ) {
                if ( $this->xlsx_normalize_text( $value ) === $this->xlsx_normalize_text( $product ) ) return $product;
            }
        }

        return $this->payload_value_by_terms( $payload, array(
            'προιον παραγγελιας', 'προϊόν παραγγελίας', 'επιλογη προιοντος', 'επιλογή προϊόντος',
            'επιλεγμενο προιον', 'επιλεγμένο προϊόν', 'product order', 'selected product', 'product', 'fuel'
        ) );
    }

    private function xlsx_column_label( string $canonical, string $fallback ): string {
        if ( '__customer' === $canonical ) return 'Πελάτης';
        if ( '__product_order' === $canonical ) return 'Προϊόν παραγγελίας';
        if ( '__quantity' === $canonical ) return 'Ποσότητα';
        if ( '__order_total' === $canonical ) return 'Τιμή παραγγελίας';
        if ( '__privacy' === $canonical ) return 'Πολιτική Απορρήτου';
        $fallback = html_entity_decode( wp_strip_all_tags( $fallback ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $fallback = preg_replace( '/\s*\*\s*$/u', '', trim( $fallback ) );
        $privacy_haystack = $this->xlsx_normalize_text( $canonical . ' ' . $fallback );
        if ( str_contains( $privacy_haystack, 'πολιτικη απορρητου' ) || str_contains( $privacy_haystack, 'privacy' ) ) {
            return 'Πολιτική Απορρήτου';
        }
        return '' !== $fallback ? $fallback : $canonical;
    }

    private function xlsx_row_xml( int $row_number, array $values, int $style = 0 ): string {
        $xml = '<row r="' . $row_number . '">';
        foreach ( array_values( $values ) as $index => $value ) {
            $cell = $this->xlsx_column_name( $index + 1 ) . $row_number;
            $safe_value = $this->xlsx_safe_text( $value );
            $xml .= '<c r="' . $cell . '" t="inlineStr" s="' . max( 0, $style ) . '"><is><t xml:space="preserve">' . $safe_value . '</t></is></c>';
        }
        return $xml . '</row>';
    }

    private function xlsx_column_name( int $number ): string {
        $name = '';
        while ( $number > 0 ) {
            $number--;
            $name = chr( 65 + ( $number % 26 ) ) . $name;
            $number = intdiv( $number, 26 );
        }
        return $name;
    }

    private function xlsx_safe_text( $value ): string {
        $value = (string) $value;
        $value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value ) ?? '';
        if ( preg_match( '/^[=+\-@]/', ltrim( $value ) ) ) {
            $value = "'" . $value;
        }
        return htmlspecialchars( $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
    }

    private function output_xml( array $rows ): void {
        $this->export_headers( 'application/xml; charset=UTF-8', 'xml' );
        $xml = new XMLWriter(); $xml->openURI( 'php://output' ); $xml->startDocument( '1.0', 'UTF-8' ); $xml->startElement( 'submissions' );
        foreach ( $rows as $row ) {
            $xml->startElement( 'submission' ); $xml->writeAttribute( 'id', (string) $row->id );
            $xml->writeElement( 'form_id', (string) $row->form_id ); $xml->writeElement( 'form_title', get_the_title( $row->form_id ) ); $xml->writeElement( 'submitted_at_utc', $row->submitted_at ); $xml->startElement( 'fields' );
            foreach ( (array) json_decode( $row->payload, true ) as $key => $item ) {
                $xml->startElement( 'field' ); $xml->writeAttribute( 'key', (string) $key ); $xml->writeElement( 'label', (string) ( $item['label'] ?? '' ) ); $xml->writeElement( 'value', (string) ( $item['value'] ?? '' ) ); $xml->endElement();
            }
            $xml->endElement(); $xml->endElement();
        }
        $xml->endElement(); $xml->endDocument(); $xml->flush(); exit;
    }

    public function form_columns( array $columns ): array {
        $columns['sfv_shortcode'] = __( 'Σύντομος κώδικας', 'secure-form-vault' );
        return $columns;
    }

    public function form_column_content( string $column, int $post_id ): void {
        if ( 'sfv_shortcode' === $column ) { echo '<code>[secure_contact_form id="' . absint( $post_id ) . '"]</code>'; }
    }
}

SFV_Plugin::boot();
