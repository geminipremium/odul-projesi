<?php
/**
 * Plugin Name:       Liora Holding Kullanýcý Ödül Sistemi
 * Plugin URI:        https://lioraholding.com
 * Description:       Ödül aktivasyon takibi, hediye kartý ödülleri ve ürün gönderimleri içeren kapsamlý Kullanýcý Ödül Sistemi.
 * Version:           1.5.0
 * Author:            Liora Holding
 * Author URI:        https://lioraholding.com
 * License:           GPL v2 or later
 * Text Domain:       liora-rewards
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Eklenti sabitleri
define('LIORA_REWARDS_VERSION', '1.5.0');
define('LIORA_REWARDS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LIORA_REWARDS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('LIORA_REWARDS_PREFIX', 'liora_rewards_');

class LioraRewardsUserRewards {

    public function __construct() {
        add_action('init', array($this, 'init_plugin'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_and_styles'));
        add_shortcode(LIORA_REWARDS_PREFIX . 'panel', array($this, 'render_rewards_system_shortcode'));

        $ajax_actions = [
            'auth', 'check_session', 'activation_payment',
            'product_submission', 'gift_card_claim', 'get_dashboard_data'
        ];

        foreach ($ajax_actions as $action) {
            add_action('wp_ajax_' . LIORA_REWARDS_PREFIX . $action, array($this, 'handle_' . $action));
            if (in_array($action, ['auth', 'check_session'])) {
                add_action('wp_ajax_nopriv_' . LIORA_REWARDS_PREFIX . $action, array($this, 'handle_' . $action));
            }
        }
        
        register_activation_hook(__FILE__, array($this, 'plugin_activate'));
        add_action('admin_menu', array($this, 'add_admin_menu_pages'));
    }

    public function init_plugin() {
        load_plugin_textdomain('liora-rewards', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function plugin_activate() {
        $this->create_database_tables();
        
        if (get_option(LIORA_REWARDS_PREFIX . 'settings') === false) {
            $default_settings = array(
                'reward_per_product' => 1.00,
                'activation_payment_amount' => 5.00,
                'activation_bonus_amount' => 10.00,
                'gift_card_redeem_threshold' => 10.00,
                'terms_url' => home_url('/kullanim-kosullari'),
                'privacy_url' => home_url('/gizlilik-politikasi'),
                'contact_url' => home_url('/iletisim'),
            );
            update_option(LIORA_REWARDS_PREFIX . 'settings', $default_settings);
        }
    }

    public function create_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // dbDelta'nýn hassas format kurallarýna göre düzenlendi:
        $table_name_user_rewards = $wpdb->prefix . LIORA_REWARDS_PREFIX . 'user_rewards';
        $sql_user_rewards = "CREATE TABLE $table_name_user_rewards (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            balance decimal(10,2) NOT NULL DEFAULT '0.00',
            activation_bonus_status tinyint(1) NOT NULL DEFAULT 0, 
            shopping_reward_balance decimal(10,2) NOT NULL DEFAULT '0.00',
            approved_products_count int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_user_rewards);

        $table_name_activation_payments = $wpdb->prefix . LIORA_REWARDS_PREFIX . 'activation_payments';
        $sql_activation_payments = "CREATE TABLE $table_name_activation_payments (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            amount decimal(10,2) NOT NULL,
            method varchar(50) NOT NULL,
            payment_details text,
            status varchar(20) NOT NULL DEFAULT 'pending',
            transaction_hash varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            reviewed_at datetime DEFAULT NULL,
            reviewed_by bigint(20) UNSIGNED DEFAULT NULL,
            notes text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_activation_payments);

        $table_name_product_submissions = $wpdb->prefix . LIORA_REWARDS_PREFIX . 'product_submissions';
        $sql_product_submissions = "CREATE TABLE $table_name_product_submissions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            product_url text NOT NULL,
            product_image_attach_id bigint(20) UNSIGNED DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            reward_amount decimal(10,2) DEFAULT '1.00',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            reviewed_at datetime DEFAULT NULL,
            reviewed_by bigint(20) UNSIGNED DEFAULT NULL,
            review_notes text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_product_submissions);

        $table_name_gift_card_claims = $wpdb->prefix . LIORA_REWARDS_PREFIX . 'gift_card_claims';
        $sql_gift_card_claims = "CREATE TABLE $table_name_gift_card_claims (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            gift_card_type varchar(50) NOT NULL,
            cost decimal(10,2) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending_fulfillment',
            gift_card_code varchar(255) DEFAULT NULL,
            claim_method varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            fulfilled_at datetime DEFAULT NULL,
            fulfilled_by bigint(20) UNSIGNED DEFAULT NULL,
            delivery_email varchar(255) DEFAULT NULL,
            notes text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_gift_card_claims);
    }

    public function enqueue_scripts_and_styles() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, LIORA_REWARDS_PREFIX . 'panel')) {
            wp_enqueue_script('react', 'https://unpkg.com/react@18/umd/react.production.min.js', array(), '18.2.0', true);
            wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js', array('react'), '18.2.0', true);
            wp_enqueue_script('babel-standalone', 'https://unpkg.com/@babel/standalone/babel.min.js', array(), '7.23.10', true); 
            wp_enqueue_style('font-awesome-6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css', array(), '6.5.2');
            wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap', array(), null);
        }
    }

    public function render_rewards_system_shortcode($atts) {
        $atts = shortcode_atts(array('theme' => 'default'), $atts, LIORA_REWARDS_PREFIX . 'panel');
        $options = get_option(LIORA_REWARDS_PREFIX . 'settings', array());
        ob_start();
        ?>
        <div id="<?php echo LIORA_REWARDS_PREFIX; ?>app-root"></div>
        <style>
            #<?php echo LIORA_REWARDS_PREFIX; ?>app-root { font-family: 'Inter', sans-serif; line-height: 1.6; color: #333; }
            .liora-rewards-container { max-width: 1100px; margin: 20px auto; padding: 15px; }
            .liora-rewards-card { background: white; border-radius: 12px; box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08); padding: 28px; margin-bottom: 24px; }
            .liora-rewards-auth-card { max-width: 420px; margin: 60px auto; padding: 32px; }
            .liora-rewards-header { background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); color: white; padding: 32px 28px; border-radius: 16px; margin-bottom: 28px; }
            .liora-rewards-balance-display { background: rgba(255,255,255,0.1); padding: 24px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.2); text-align: center; }
            .liora-rewards-balance-display p.label { opacity: 0.8; font-size: 0.9rem; margin-bottom: 4px; margin-top:0; }
            .liora-rewards-balance-display p.amount { font-size: 2.2rem; font-weight: bold; margin: 0; line-height:1.2; }
            .liora-rewards-balance-display .subtext { display: flex; align-items: center; justify-content: center; opacity: 0.7; font-size: 0.8rem; margin-top: 8px; }
            .liora-rewards-nav { display: flex; gap: 10px; margin-bottom: 28px; flex-wrap: wrap; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb; }
            .liora-rewards-nav-btn { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 500; transition: all 0.2s ease-in-out; display: flex; align-items: center; gap: 8px; }
            .liora-rewards-nav-btn.active { background: #4F46E5; color: white; border-color: #4F46E5; }
            .liora-rewards-nav-btn:hover:not(.active) { background: #e5e7eb; border-color: #d1d5db; }
            .liora-rewards-nav-btn.active:hover { background: #4338CA; }
            .liora-rewards-btn { background: linear-gradient(135deg, #4F46E5, #6D28D9); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s ease-in-out; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
            .liora-rewards-btn:hover { background: linear-gradient(135deg, #4338CA, #5B21B6); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); color: white; }
            .liora-rewards-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }
            .liora-rewards-btn-secondary { background: white; color: #4338CA; border: 1px solid #D1D5DB; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
            .liora-rewards-btn-secondary:hover { background: #f0f0ff; border-color: #A5B4FC; transform:none; box-shadow:none; color: #4338CA; }
            .liora-rewards-input { width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box; }
            .liora-rewards-input:focus { outline: none; border-color: #4F46E5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
            .liora-rewards-label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 0.9rem; color: #374151; }
            .liora-rewards-alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid transparent; display: flex; align-items: flex-start; }
            .liora-rewards-alert i { margin-right: 12px; font-size: 1.2em; margin-top: 2px; }
            .liora-rewards-alert.info { background-color: #EFF6FF; border-color: #BFDBFE; color: #1D4ED8; }
            .liora-rewards-alert.warning { background-color: #FFFBEB; border-color: #FDE68A; color: #B45309; }
            .liora-rewards-alert.error { background-color: #FEF2F2; border-color: #FECACA; color: #B91C1C; }
            .liora-rewards-alert.success { background-color: #F0FDF4; border-color: #BBF7D0; color: #15803D; }
            .liora-rewards-alert-dismissible { justify-content: space-between; }
            .liora-rewards-alert-close-btn { background: none; border: none; font-size: 1.2rem; cursor: pointer; opacity:0.7; padding:0; line-height:1; color: inherit; margin-left:15px; }
            .liora-rewards-alert-close-btn:hover { opacity:1; }
            .liora-rewards-grid { display: grid; gap: 20px; }
            .liora-rewards-grid.cols-1 { grid-template-columns: 1fr; }
            .liora-rewards-grid.cols-2 { grid-template-columns: repeat(2, 1fr); }
            .liora-rewards-grid.cols-3 { grid-template-columns: repeat(3, 1fr); }
            .liora-rewards-badge { display: inline-block; padding: 5px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
            .liora-rewards-badge.default { background: #4F46E5; color: white; }
            .liora-rewards-badge.success { background: #10B981; color: white; }
            .liora-rewards-badge.warning { background: #F59E0B; color: #422006; }
            .liora-rewards-badge.error { background: #EF4444; color: white; }
            .liora-rewards-badge.info { background: #3B82F6; color: white; }
            .liora-rewards-badge.locked { background: #6b7280; color: white; }
            .liora-rewards-loading { text-align: center; padding: 60px 20px; }
            .liora-rewards-spinner { border: 5px solid #e5e7eb; border-top: 5px solid #4F46E5; border-radius: 50%; width: 50px; height: 50px; animation: liora-rewards-spin 0.8s linear infinite; margin: 0 auto 20px; }
            @keyframes liora-rewards-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .liora-rewards-loading h3 { color: #374151; margin-bottom: 8px; }
            .liora-rewards-loading p { color: #6b7280; font-size: 0.9rem; }
            .liora-rewards-gift-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; cursor: pointer; transition: all 0.25s ease-in-out; display: flex; flex-direction: column; align-items: center; text-align: center; background-color: #fff; position: relative; }
            .liora-rewards-gift-card:hover { border-color: #A5B4FC; box-shadow: 0 8px 16px rgba(79, 70, 229, 0.1); transform: translateY(-3px); }
            .liora-rewards-gift-card.selected { border-color: #4F46E5; background: #EEF2FF; transform: scale(1.03); box-shadow: 0 6px 20px rgba(79, 70, 229, 0.15); }
            .liora-rewards-gift-card.selected .liora-rewards-gift-card-checkmark { opacity: 1; transform: scale(1); }
            .liora-rewards-gift-card.disabled { opacity: 0.6; cursor: not-allowed; filter: grayscale(80%); background-color: #f9fafb; }
            .liora-rewards-gift-card.disabled:hover { transform: none; box-shadow: none; border-color: #e5e7eb; }
            .liora-rewards-gift-card-logo-placeholder { width: 72px; height: 72px; background: #f3f4f6; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 16px; border: 1px solid #e5e7eb; color: #6b7280; overflow: hidden; }
            .liora-rewards-gift-card-logo-placeholder img { max-width: 90%; max-height: 90%; object-fit: contain; }
            .liora-rewards-gift-card-name { font-weight: 600; font-size: 1.1rem; color: #1f2937; margin-bottom: 4px; }
            .liora-rewards-gift-card-cost { color: #4F46E5; font-size: 0.9rem; font-weight: 500; }
            .liora-rewards-gift-card-checkmark { position: absolute; top: 12px; right: 12px; background: #4F46E5; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; opacity: 0; transform: scale(0.5); transition: opacity 0.2s, transform 0.2s; }
            .liora-rewards-form-group { margin-bottom: 20px; }
            .liora-rewards-checkbox-group { display: flex; align-items: flex-start; gap: 10px; }
            .liora-rewards-checkbox-group input[type="checkbox"] { margin-top: 3px; width: 18px; height: 18px; accent-color: #4F46E5; }
            .liora-rewards-checkbox-group label { font-size: 0.9rem; color: #4b5563; line-height: 1.5; }
            .liora-rewards-checkbox-group label a { color: #4F46E5; text-decoration: underline; }
            .liora-rewards-progress-bar-container { width: 100%; background-color: #e5e7eb; border-radius: 8px; margin: 10px 0; overflow: hidden; }
            .liora-rewards-progress-bar { width: 0%; height: 12px; background-color: #22C55E; border-radius: 8px; transition: width 0.5s ease-in-out; text-align: center; color: white; font-size: 0.7rem; line-height: 12px; }
            @media (max-width: 768px) {
                .liora-rewards-grid.cols-2, .liora-rewards-grid.cols-3 { grid-template-columns: 1fr; }
                .liora-rewards-nav { justify-content: flex-start; overflow-x: auto; padding-bottom: 10px; white-space: nowrap; -webkit-overflow-scrolling: touch; }
                .liora-rewards-nav::-webkit-scrollbar { height: 4px; }
                .liora-rewards-nav::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 2px; }
                .liora-rewards-nav-btn { padding: 8px 12px; font-size: 0.9rem; }
                .liora-rewards-header .liora-rewards-grid { grid-template-columns: 1fr; }
                .liora-rewards-balance-display p.amount { font-size: 1.8rem; }
                .liora-rewards-header { padding: 24px 20px; }
                .liora-rewards-card { padding: 20px; }
                .liora-rewards-auth-card { margin: 40px 15px; padding:24px; }
                .liora-rewards-gift-card { padding: 15px; }
                .liora-rewards-gift-card-logo-placeholder { width: 56px; height: 56px; font-size: 1.5rem; margin-bottom:12px;}
                .liora-rewards-gift-card-name { font-size: 1rem; }
            }
            @media (max-width: 480px) {
                .liora-rewards-balance-display p.amount { font-size: 1.6rem; }
                .liora-rewards-nav-btn { font-size: 0.85rem; gap: 6px; padding: 8px 10px;}
                .liora-rewards-btn { padding: 10px 18px; font-size:0.9rem; }
            }
        </style>

        <script type="text/javascript">
            var <?php echo LIORA_REWARDS_PREFIX; ?>ajax = <?php echo json_encode(array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(LIORA_REWARDS_PREFIX . 'nonce'),
                'is_user_logged_in' => is_user_logged_in(),
                'plugin_url' => LIORA_REWARDS_PLUGIN_URL,
                'terms_url' => esc_url($options['terms_url'] ?? ''),
                'privacy_url' => esc_url($options['privacy_url'] ?? ''),
                'contact_url' => esc_url($options['contact_url'] ?? ''),
                'i18n' => array(
                    'loading' => __('Yükleniyor...', 'liora-rewards'),
                )
            )); ?>;
        </script>