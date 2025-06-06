<?php
/**
 * Plugin Name: AgMedya User Rewards System
 * Plugin URI: https://agmedya.com
 * Description: Complete User Rewards System with investment tracking, gift card rewards, and product submissions. Professional React-based interface with full WordPress integration.
 * Version: 1.0.0
 * Author: AgMedya
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AGMEDYA_REWARDS_VERSION', '1.0.0');
define('AGMEDYA_REWARDS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AGMEDYA_REWARDS_PLUGIN_PATH', plugin_dir_path(__FILE__));

class AgMedyaUserRewards {

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('agmedya_rewards', array($this, 'render_rewards_system'));
        add_action('wp_ajax_agmedya_auth', array($this, 'handle_auth'));
        add_action('wp_ajax_nopriv_agmedya_auth', array($this, 'handle_auth'));
        add_action('wp_ajax_agmedya_investment', array($this, 'handle_investment'));
        add_action('wp_ajax_agmedya_product_submission', array($this, 'handle_product_submission'));
        add_action('wp_ajax_agmedya_gift_card_claim', array($this, 'handle_gift_card_claim'));
        add_action('wp_ajax_agmedya_get_dashboard', array($this, 'get_dashboard_data'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    public function init() {
        $this->create_tables();
    }

    public function activate() {
        $this->create_tables();
    }

    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Users rewards table
        $table_name = $wpdb->prefix . 'agmedya_user_rewards';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            balance decimal(10,2) NOT NULL DEFAULT '0.00',
            has_initial_deposit tinyint(1) NOT NULL DEFAULT 0,
            approved_products int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

        // Investments table
        $investments_table = $wpdb->prefix . 'agmedya_investments';
        $sql2 = "CREATE TABLE IF NOT EXISTS $investments_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            method varchar(50) NOT NULL,
            payment_details text,
            status varchar(20) NOT NULL DEFAULT 'pending',
            transaction_hash varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            reviewed_at datetime,
            reviewed_by bigint(20),
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Product submissions table
        $submissions_table = $wpdb->prefix . 'agmedya_product_submissions';
        $sql3 = "CREATE TABLE IF NOT EXISTS $submissions_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            product_url text NOT NULL,
            product_image varchar(255),
            status varchar(20) NOT NULL DEFAULT 'pending',
            reward_amount decimal(10,2),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            reviewed_at datetime,
            reviewed_by bigint(20),
            review_notes text,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Gift card claims table
        $gift_cards_table = $wpdb->prefix . 'agmedya_gift_card_claims';
        $sql4 = "CREATE TABLE IF NOT EXISTS $gift_cards_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            gift_card_type varchar(50) NOT NULL,
            cost decimal(10,2) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            gift_card_code varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            fulfilled_at datetime,
            fulfilled_by bigint(20),
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
    }

    public function enqueue_scripts() {
        wp_enqueue_script('react', 'https://unpkg.com/react@18/umd/react.production.min.js', array(), '18.0.0', true);
        wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js', array('react'), '18.0.0', true);
        wp_enqueue_script('babel-standalone', 'https://unpkg.com/@babel/standalone/babel.min.js', array(), '7.0.0', true);
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
        wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    }

    public function render_rewards_system($atts) {
        $atts = shortcode_atts(array(
            'theme' => 'default'
        ), $atts);

        ob_start();
        ?>
        <div id="agmedya-rewards-root"></div>

        <style>
        /* WordPress Integration Styles */
        #agmedya-rewards-root {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
        }

        .agmedya-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .agmedya-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 24px;
            margin-bottom: 20px;
        }

        .agmedya-header {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #6366f1 100%);
            color: white;
            padding: 32px 24px;
            border-radius: 16px;
            margin-bottom: 24px;
        }

        .agmedya-balance {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 8px 0;
        }

        .agmedya-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .agmedya-nav-btn {
            background: #64748b;
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }

        .agmedya-nav-btn.active {
            background: #3b82f6;
        }

        .agmedya-nav-btn:hover {
            background: #475569;
        }

        .agmedya-nav-btn.active:hover {
            background: #2563eb;
        }

        .agmedya-btn {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .agmedya-btn:hover {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            transform: translateY(-1px);
        }

        .agmedya-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .agmedya-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s;
        }

        .agmedya-input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .agmedya-alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .agmedya-alert.info {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            color: #1e40af;
        }

        .agmedya-alert.warning {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            color: #92400e;
        }

        .agmedya-alert.error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #dc2626;
        }

        .agmedya-alert.success {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
        }

        .agmedya-grid {
            display: grid;
            gap: 16px;
        }

        .agmedya-grid.cols-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .agmedya-grid.cols-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .agmedya-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .agmedya-badge.default {
            background: #3b82f6;
            color: white;
        }

        .agmedya-badge.success {
            background: #10b981;
            color: white;
        }

        .agmedya-badge.warning {
            background: #f59e0b;
            color: white;
        }

        .agmedya-badge.error {
            background: #ef4444;
            color: white;
        }

        .agmedya-loading {
            text-align: center;
            padding: 40px;
        }

        .agmedya-spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: agmedya-spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes agmedya-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .agmedya-gift-card {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .agmedya-gift-card:hover {
            border-color: #94a3b8;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .agmedya-gift-card.selected {
            border-color: #3b82f6;
            background: #eff6ff;
            transform: scale(1.02);
        }

        .agmedya-gift-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            filter: grayscale(100%);
        }

        @media (max-width: 768px) {
            .agmedya-grid.cols-2,
            .agmedya-grid.cols-3 {
                grid-template-columns: 1fr;
            }

            .agmedya-nav {
                justify-content: center;
            }

            .agmedya-balance {
                font-size: 2rem;
            }
        }
        </style>

        <script type="text/javascript">
            var agmedya_ajax = <?php echo json_encode(array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('agmedya_nonce'),
                'user_id' => get_current_user_id()
            )); ?>;
        </script>

        <script type="text/babel">
        const { useState, useEffect } = React;

        // WordPress integration utilities
        const wpAjax = {
            post: async (action, data) => {
                const formData = new FormData();
                formData.append('action', action);
                formData.append('nonce', agmedya_ajax.nonce);
                Object.keys(data).forEach(key => {
                    formData.append(key, data[key]);
                });

                const response = await fetch(agmedya_ajax.ajax_url, {
                    method: 'POST',
                    body: formData
                });

                return await response.json();
            }
        };

        // Main Application Component
        function AgMedyaRewardsApp() {
            const [currentView, setCurrentView] = useState('auth');
            const [currentUser, setCurrentUser] = useState(null);
            const [activeSection, setActiveSection] = useState('overview');
            const [isLoading, setIsLoading] = useState(true);
            const [dashboardData, setDashboardData] = useState(null);

            useEffect(() => {
                // Check if user is logged in via WordPress
                if (agmedya_ajax.user_id && agmedya_ajax.user_id !== '0') {
                    loadDashboardData(agmedya_ajax.user_id);
                } else {
                    setIsLoading(false);
                }
            }, []);

            const loadDashboardData = async (userId) => {
                try {
                    const response = await wpAjax.post('agmedya_get_dashboard', { user_id: userId });
                    if (response.success) {
                        setCurrentUser(response.data.user);
                        setDashboardData(response.data);
                        setCurrentView('dashboard');
                    }
                } catch (error) {
                    console.error('Failed to load dashboard:', error);
                } finally {
                    setIsLoading(false);
                }
            };

            if (isLoading) {
                return (
                    <div className="agmedya-container">
                        <div className="agmedya-loading">
                            <div className="agmedya-spinner"></div>
                            <h3>Initializing WordPress Integration...</h3>
                            <p>AgMedya User Rewards System v1.0.0</p>
                        </div>
                    </div>
                );
            }

            return (
                <div className="agmedya-container">
                    {/* WordPress Integration Notice */}
                    <div className="agmedya-alert info">
                        <i className="fab fa-wordpress"></i>
                        <strong> WordPress Integration Active</strong> | Plugin Version 1.0.0 | AgMedya User Rewards System
                    </div>

                    {currentView === 'auth' ? (
                        <AuthView onLogin={setCurrentUser} onViewChange={setCurrentView} />
                    ) : (
                        <DashboardView
                            user={currentUser}
                            dashboardData={dashboardData}
                            activeSection={activeSection}
                            setActiveSection={setActiveSection}
                            onLogout={() => {
                                setCurrentUser(null);
                                setCurrentView('auth');
                                setActiveSection('overview');
                            }}
                        />
                    )}
                </div>
            );
        }

        // Authentication Component
        function AuthView({ onLogin, onViewChange }) {
            const [authMode, setAuthMode] = useState('register');
            const [formData, setFormData] = useState({
                first_name: '',
                last_name: '',
                email: '',
                password: ''
            });
            const [isSubmitting, setIsSubmitting] = useState(false);
            const [error, setError] = useState('');

            const handleInputChange = (e) => {
                setFormData(prev => ({
                    ...prev,
                    [e.target.name]: e.target.value
                }));
            };

            const handleSubmit = async (e) => {
                e.preventDefault();
                setIsSubmitting(true);
                setError('');

                try {
                    const response = await wpAjax.post('agmedya_auth', {
                        mode: authMode,
                        ...formData
                    });

                    if (response.success) {
                        onLogin(response.data.user);
                        onViewChange('dashboard');
                    } else {
                        setError(response.data.message || 'Authentication failed');
                    }
                } catch (err) {
                    setError('Network error. Please try again.');
                } finally {
                    setIsSubmitting(false);
                }
            };

            return (
                <div className="agmedya-card" style={{ maxWidth: '400px', margin: '40px auto' }}>
                    <div style={{ textAlign: 'center', marginBottom: '32px' }}>
                        <div style={{
                            width: '64px',
                            height: '64px',
                            background: 'linear-gradient(135deg, #3b82f6, #8b5cf6)',
                            borderRadius: '16px',
                            margin: '0 auto 16px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center'
                        }}>
                            <i className="fas fa-shield-alt" style={{ fontSize: '24px', color: 'white' }}></i>
                        </div>
                        <h2>AgMedya Rewards</h2>
                        <p style={{ color: '#64748b' }}>WordPress User Portal</p>
                    </div>

                    {authMode === 'register' && (
                        <div className="agmedya-alert warning">
                            <p><strong>Important Notice</strong></p>
                            <p>Please register using your real email address. Do not use fake or temporary emails. All gift card rewards will be sent to your registered email.</p>
                        </div>
                    )}

                    <form onSubmit={handleSubmit}>
                        {authMode === 'register' && (
                            <div className="agmedya-grid cols-2" style={{ marginBottom: '16px' }}>
                                <input
                                    type="text"
                                    name="first_name"
                                    required
                                    placeholder="First Name"
                                    value={formData.first_name}
                                    onChange={handleInputChange}
                                    className="agmedya-input"
                                />
                                <input
                                    type="text"
                                    name="last_name"
                                    required
                                    placeholder="Last Name"
                                    value={formData.last_name}
                                    onChange={handleInputChange}
                                    className="agmedya-input"
                                />
                            </div>
                        )}

                        <div style={{ marginBottom: '16px' }}>
                            <input
                                type="email"
                                name="email"
                                required
                                placeholder={authMode === 'register' ? 'Use Gmail, Hotmail, etc.' : 'Email Address'}
                                value={formData.email}
                                onChange={handleInputChange}
                                className="agmedya-input"
                            />
                        </div>

                        <div style={{ marginBottom: '24px' }}>
                            <input
                                type="password"
                                name="password"
                                required
                                minLength="6"
                                placeholder="Password"
                                value={formData.password}
                                onChange={handleInputChange}
                                className="agmedya-input"
                            />
                        </div>

                        {error && (
                            <div className="agmedya-alert error" style={{ marginBottom: '16px' }}>
                                {error}
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={isSubmitting}
                            className="agmedya-btn"
                            style={{ width: '100%', marginBottom: '16px' }}
                        >
                            <i className="fab fa-wordpress" style={{ marginRight: '8px' }}></i>
                            {isSubmitting ? 'Processing...' : (authMode === 'login' ? 'Login to WordPress' : 'Create Account')}
                        </button>
                    </form>

                    <div style={{ textAlign: 'center' }}>
                        <button
                            onClick={() => setAuthMode(authMode === 'login' ? 'register' : 'login')}
                            style={{
                                background: 'none',
                                border: 'none',
                                color: '#3b82f6',
                                cursor: 'pointer',
                                textDecoration: 'underline'
                            }}
                        >
                            {authMode === 'login' ? "Don't have an account? Register" : 'Already have an account? Login'}
                        </button>
                    </div>
                </div>
            );
        }

        // Dashboard Component
        function DashboardView({ user, dashboardData, activeSection, setActiveSection, onLogout }) {
            const sections = [
                { id: 'overview', label: 'Overview', icon: 'fas fa-tachometer-alt' },
                { id: 'investment', label: 'Investment', icon: 'fas fa-credit-card' },
                { id: 'gift-cards', label: 'Gift Cards', icon: 'fas fa-gift' },
                { id: 'products', label: 'Products', icon: 'fas fa-file-alt' },
                { id: 'settings', label: 'Settings', icon: 'fas fa-user' },
                { id: 'support', label: 'Support', icon: 'fas fa-info-circle' }
            ];

            return (
                <div>
                    {/* Header Balance Card */}
                    <div className="agmedya-header">
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '24px' }}>
                            <div>
                                <div style={{ display: 'flex', alignItems: 'center', marginBottom: '8px' }}>
                                    <i className="fab fa-wordpress" style={{ marginRight: '8px', opacity: '0.8' }}></i>
                                    <span style={{ opacity: '0.8' }}>WordPress User</span>
                                </div>
                                <h1 style={{ fontSize: '1.5rem', fontWeight: 'bold', marginBottom: '4px' }}>
                                    Welcome back, {user.display_name || user.first_name}!
                                </h1>
                                <p style={{ opacity: '0.8' }}>{user.user_email}</p>
                            </div>
                            <button
                                onClick={onLogout}
                                style={{
                                    background: 'rgba(255,255,255,0.2)',
                                    border: 'none',
                                    color: 'white',
                                    padding: '8px 12px',
                                    borderRadius: '8px',
                                    cursor: 'pointer'
                                }}
                            >
                                <i className="fas fa-sign-out-alt"></i>
                            </button>
                        </div>

                        <div className="agmedya-grid cols-3">
                            <div style={{
                                background: 'rgba(255,255,255,0.1)',
                                padding: '24px',
                                borderRadius: '16px',
                                border: '1px solid rgba(255,255,255,0.2)'
                            }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                                    <div>
                                        <p style={{ opacity: '0.8', fontSize: '0.875rem' }}>Your Balance</p>
                                        <p className="agmedya-balance">${dashboardData?.user.balance || '0.00'}</p>
                                    </div>
                                    <div style={{
                                        width: '48px',
                                        height: '48px',
                                        background: 'rgba(34,197,94,0.2)',
                                        borderRadius: '12px',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center'
                                    }}>
                                        <i className="fas fa-wallet" style={{ color: '#22c55e', fontSize: '20px' }}></i>
                                    </div>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', opacity: '0.6', fontSize: '0.75rem' }}>
                                    <i className="fas fa-info-circle" style={{ marginRight: '4px' }}></i>
                                    <span>Withdrawable balance</span>
                                </div>
                            </div>

                            <div style={{
                                background: 'rgba(255,255,255,0.1)',
                                padding: '24px',
                                borderRadius: '16px',
                                border: '1px solid rgba(255,255,255,0.2)'
                            }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                                    <div>
                                        <p style={{ opacity: '0.8', fontSize: '0.875rem' }}>Gift Cards</p>
                                        <p className="agmedya-balance">
                                            {dashboardData?.user.has_initial_deposit && parseFloat(dashboardData?.user.balance || 0) >= 10 ? 'Available' : 'Locked'}
                                        </p>
                                    </div>
                                    <div style={{
                                        width: '48px',
                                        height: '48px',
                                        background: 'rgba(245,158,11,0.2)',
                                        borderRadius: '12px',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center'
                                    }}>
                                        <i className="fas fa-gift" style={{ color: '#f59e0b', fontSize: '20px' }}></i>
                                    </div>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', opacity: '0.6', fontSize: '0.75rem' }}>
                                    <i className="fas fa-lock" style={{ marginRight: '4px' }}></i>
                                    <span>
                                        {dashboardData?.user.has_initial_deposit
                                            ? 'Requires $10 balance'
                                            : 'Requires $5 initial deposit'
                                        }
                                    </span>
                                </div>
                            </div>

                            <div style={{
                                background: 'rgba(255,255,255,0.1)',
                                padding: '24px',
                                borderRadius: '16px',
                                border: '1px solid rgba(255,255,255,0.2)'
                            }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                                    <div>
                                        <p style={{ opacity: '0.8', fontSize: '0.875rem' }}>Products</p>
                                        <p className="agmedya-balance">{dashboardData?.user.approved_products || 0}/10</p>
                                    </div>
                                    <div style={{
                                        width: '48px',
                                        height: '48px',
                                        background: 'rgba(59,130,246,0.2)',
                                        borderRadius: '12px',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center'
                                    }}>
                                        <i className="fas fa-file-alt" style={{ color: '#3b82f6', fontSize: '20px' }}></i>
                                    </div>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', opacity: '0.6', fontSize: '0.75rem' }}>
                                    <i className="fas fa-chart-line" style={{ marginRight: '4px' }}></i>
                                    <span>Approved submissions</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Navigation */}
                    <div className="agmedya-nav">
                        {sections.map(({ id, label, icon }) => (
                            <button
                                key={id}
                                className={`agmedya-nav-btn ${activeSection === id ? 'active' : ''}`}
                                onClick={() => setActiveSection(id)}
                            >
                                <i className={icon} style={{ marginRight: '8px' }}></i>
                                {label}
                            </button>
                        ))}
                    </div>

                    {/* Content Sections */}
                    {activeSection === 'overview' && <OverviewSection user={dashboardData?.user} />}
                    {activeSection === 'investment' && <InvestmentSection user={dashboardData?.user} />}
                    {activeSection === 'gift-cards' && <GiftCardsSection user={dashboardData?.user} />}
                    {activeSection === 'products' && <ProductsSection user={dashboardData?.user} />}
                    {activeSection === 'settings' && <SettingsSection user={dashboardData?.user} />}
                    {activeSection === 'support' && <SupportSection />}
                </div>
            );
        }

        // Overview Section
        function OverviewSection({ user }) {
            return (
                <div>
                    <div className="agmedya-card">
                        <h3><i className="fas fa-shield-alt" style={{ color: '#3b82f6', marginRight: '8px' }}></i>Account Status</h3>
                        <div className="agmedya-grid cols-2" style={{ marginTop: '16px' }}>
                            <div style={{
                                padding: '16px',
                                background: user?.has_initial_deposit ? '#dcfce7' : '#fee2e2',
                                borderRadius: '12px',
                                border: user?.has_initial_deposit ? '1px solid #86efac' : '1px solid #fca5a5'
                            }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span style={{ fontWeight: '500' }}>Initial Deposit</span>
                                    <span className={`agmedya-badge ${user?.has_initial_deposit ? 'success' : 'error'}`}>
                                        {user?.has_initial_deposit ? 'Complete' : 'Required'}
                                    </span>
                                </div>
                            </div>

                            <div style={{
                                padding: '16px',
                                background: user?.has_initial_deposit && parseFloat(user?.balance || 0) >= 10 ? '#dcfce7' : '#fef3c7',
                                borderRadius: '12px',
                                border: user?.has_initial_deposit && parseFloat(user?.balance || 0) >= 10 ? '1px solid #86efac' : '1px solid #fcd34d'
                            }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span style={{ fontWeight: '500' }}>Gift Cards</span>
                                    <span className={`agmedya-badge ${user?.has_initial_deposit && parseFloat(user?.balance || 0) >= 10 ? 'success' : 'warning'}`}>
                                        {user?.has_initial_deposit && parseFloat(user?.balance || 0) >= 10 ? 'Available' : 'Locked'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="agmedya-alert info" style={{ marginTop: '24px' }}>
                            <i className="fas fa-lightbulb" style={{ marginRight: '8px' }}></i>
                            <strong>Quick Start:</strong> {user?.has_initial_deposit
                                ? "Your account is active! Submit products and claim gift cards when you reach $10 balance."
                                : "Make your $5 initial deposit to unlock gift card rewards and start earning!"
                            }
                        </div>
                    </div>

                    <div className="agmedya-card">
                        <h3><i className="fas fa-clock" style={{ color: '#8b5cf6', marginRight: '8px' }}></i>Recent Activity</h3>
                        <div style={{ textAlign: 'center', padding: '40px' }}>
                            <i className="fas fa-clock" style={{ fontSize: '48px', color: '#9ca3af', marginBottom: '16px' }}></i>
                            <p style={{ color: '#6b7280', fontWeight: '500', marginBottom: '8px' }}>No activity yet</p>
                            <p style={{ color: '#9ca3af', fontSize: '0.875rem' }}>Your transactions will appear here</p>
                        </div>
                    </div>
                </div>
            );
        }

        // Investment Section
        function InvestmentSection({ user }) {
            const [selectedAmount, setSelectedAmount] = useState(user?.has_initial_deposit ? null : 5);
            const [isSubmitting, setIsSubmitting] = useState(false);

            const availableAmounts = user?.has_initial_deposit ? [10, 25, 50, 75, 100] : [5];

            const handleInvestmentSubmit = async (method, paymentDetails) => {
                if (!selectedAmount) return;

                setIsSubmitting(true);
                try {
                    const response = await wpAjax.post('agmedya_investment', {
                        amount: selectedAmount,
                        method: method,
                        payment_details: paymentDetails
                    });

                    if (response.success) {
                        alert('Investment submitted successfully!');
                    } else {
                        alert('Error: ' + (response.data.message || 'Failed to submit investment'));
                    }
                } catch (error) {
                    alert('Network error. Please try again.');
                } finally {
                    setIsSubmitting(false);
                }
            };

            const copyToClipboard = (text) => {
                navigator.clipboard.writeText(text).then(() => {
                    alert('Copied to clipboard!');
                });
            };

            return (
                <div className="agmedya-card">
                    <h3><i className="fas fa-credit-card" style={{ color: '#3b82f6', marginRight: '8px' }}></i>Make Investment</h3>

                    {!user?.has_initial_deposit && (
                        <div className="agmedya-alert error">
                            <strong>Initial $5 Deposit Required</strong><br />
                            Your account must be verified with exactly $5 to unlock all features and gift card rewards.
                        </div>
                    )}

                    <div className="agmedya-alert info">
                        {user?.has_initial_deposit
                            ? "You can now invest up to $100 to earn additional rewards."
                            : "Start with exactly $5 to verify your account and unlock all features."
                        }
                    </div>

                    {/* Amount Selection */}
                    <div style={{ marginBottom: '24px' }}>
                        <h4>Select Investment Amount</h4>
                        <div className="agmedya-grid cols-3" style={{ marginTop: '16px' }}>
                            {availableAmounts.slice(0, 3).map((amount) => (
                                <button
                                    key={amount}
                                    onClick={() => setSelectedAmount(amount)}
                                    style={{
                                        padding: '16px',
                                        border: selectedAmount === amount ? '2px solid #3b82f6' : '2px solid #e2e8f0',
                                        borderRadius: '8px',
                                        background: selectedAmount === amount ? '#eff6ff' : 'white',
                                        cursor: 'pointer',
                                        fontWeight: 'bold'
                                    }}
                                >
                                    ${amount}
                                    {amount === 5 && <div style={{ fontSize: '0.75rem', opacity: '0.7' }}>Initial Deposit</div>}
                                </button>
                            ))}
                        </div>

                        {user?.has_initial_deposit && availableAmounts.length > 3 && (
                            <div className="agmedya-grid cols-2" style={{ marginTop: '16px' }}>
                                {availableAmounts.slice(3).map((amount) => (
                                    <button
                                        key={amount}
                                        onClick={() => setSelectedAmount(amount)}
                                        style={{
                                            padding: '16px',
                                            border: selectedAmount === amount ? '2px solid #3b82f6' : '2px solid #e2e8f0',
                                            borderRadius: '8px',
                                            background: selectedAmount === amount ? '#eff6ff' : 'white',
                                            cursor: 'pointer',
                                            fontWeight: 'bold'
                                        }}
                                    >
                                        ${amount}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    {selectedAmount && (
                        <div className="agmedya-alert info">
                            Selected amount: <strong>${selectedAmount}</strong>
                        </div>
                    )}

                    {/* Payment Methods */}
                    <div style={{ marginBottom: '24px' }}>
                        <h4><i className="fas fa-university" style={{ color: '#3b82f6', marginRight: '8px' }}></i>IBAN Bank Transfer</h4>
                        <div style={{
                            background: '#f8fafc',
                            border: '1px solid #e2e8f0',
                            borderRadius: '8px',
                            padding: '16px',
                            marginTop: '8px'
                        }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span style={{ fontFamily: 'monospace', fontSize: '0.875rem' }}>TR18 0001 0019 8566 8967 0450 04</span>
                                <button
                                    onClick={() => copyToClipboard('TR18 0001 0019 8566 8967 0450 04')}
                                    style={{
                                        background: '#64748b',
                                        color: 'white',
                                        border: 'none',
                                        padding: '8px 12px',
                                        borderRadius: '4px',
                                        cursor: 'pointer'
                                    }}
                                >
                                    <i className="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div className="agmedya-alert error" style={{ marginTop: '8px' }}>
                            Send exactly ${selectedAmount || 0} USD for account verification
                        </div>
                        {selectedAmount && (
                            <button
                                onClick={() => handleInvestmentSubmit('bank_transfer', 'TR18 0001 0019 8566 8967 0450 04')}
                                disabled={isSubmitting}
                                className="agmedya-btn"
                                style={{ width: '100%', marginTop: '8px' }}
                            >
                                <i className="fas fa-paper-plane" style={{ marginRight: '8px' }}></i>
                                {isSubmitting ? 'Processing...' : `Submit $${selectedAmount} Bank Transfer`}
                            </button>
                        )}
                    </div>

                    <div>
                        <h4><i className="fas fa-shield-alt" style={{ color: '#f59e0b', marginRight: '8px' }}></i>Cryptocurrency (LTC)</h4>
                        <div style={{
                            background: '#f8fafc',
                            border: '1px solid #e2e8f0',
                            borderRadius: '8px',
                            padding: '16px',
                            marginTop: '8px'
                        }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span style={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>LTC1A2B3C4D5E6F7G8H9I0J1K2L3M4N5O6P7Q8R9S0</span>
                                <button
                                    onClick={() => copyToClipboard('LTC1A2B3C4D5E6F7G8H9I0J1K2L3M4N5O6P7Q8R9S0')}
                                    style={{
                                        background: '#64748b',
                                        color: 'white',
                                        border: 'none',
                                        padding: '8px 12px',
                                        borderRadius: '4px',
                                        cursor: 'pointer'
                                    }}
                                >
                                    <i className="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div className="agmedya-alert info" style={{ marginTop: '8px' }}>
                            Network fees may apply. Send equivalent ${selectedAmount || 0} in LTC.
                        </div>
                        {selectedAmount && (
                            <button
                                onClick={() => handleInvestmentSubmit('cryptocurrency', 'LTC1A2B3C4D5E6F7G8H9I0J1K2L3M4N5O6P7Q8R9S0')}
                                disabled={isSubmitting}
                                className="agmedya-btn"
                                style={{ width: '100%', marginTop: '8px', background: '#64748b' }}
                            >
                                <i className="fas fa-paper-plane" style={{ marginRight: '8px' }}></i>
                                {isSubmitting ? 'Processing...' : `Submit $${selectedAmount} Crypto Transfer`}
                            </button>
                        )}
                    </div>
                </div>
            );
        }

        // Gift Cards Section
        function GiftCardsSection({ user }) {
            const [selectedGiftCard, setSelectedGiftCard] = useState(null);
            const [isSubmitting, setIsSubmitting] = useState(false);

            const giftCards = [
                { id: 'amazon', name: 'Amazon', logo: '??', cost: 10, available: true },
                { id: 'steam', name: 'Steam', logo: '??', cost: 10, available: true },
                { id: 'playstore', name: 'Google Play', logo: '??', cost: 10, available: true },
                { id: 'psstore', name: 'PlayStation Store', logo: '??', cost: 10, available: true },
                { id: 'xbox', name: 'Xbox', logo: '??', cost: 10, available: true },
                { id: 'mobilelegends', name: 'Mobile Legends', logo: '??', cost: 10, available: true },
                { id: 'pubgmobile', name: 'PUBG Mobile', logo: '??', cost: 10, available: true },
                { id: 'razergold', name: 'Razer Gold', logo: '??', cost: 10, available: true },
                { id: 'binance', name: 'Binance', logo: '?', cost: 10, available: true },
            ];

            const canClaimGiftCards = user?.has_initial_deposit && parseFloat(user?.balance || 0) >= 10;

            const handleGiftCardClaim = (cardId) => {
                if (!canClaimGiftCards) return;
                setSelectedGiftCard(cardId);
            };

            const confirmClaim = async () => {
                if (!selectedGiftCard) return;

                const card = giftCards.find(c => c.id === selectedGiftCard);
                if (!card) return;

                setIsSubmitting(true);
                try {
                    const response = await wpAjax.post('agmedya_gift_card_claim', {
                        gift_card_type: card.id,
                        cost: card.cost
                    });

                    if (response.success) {
                        alert('Gift card claimed successfully!');
                        setSelectedGiftCard(null);
                    } else {
                        alert('Error: ' + (response.data.message || 'Failed to claim gift card'));
                    }
                } catch (error) {
                    alert('Network error. Please try again.');
                } finally {
                    setIsSubmitting(false);
                }
            };

            return (
                <div>
                    <div className="agmedya-card" style={{ background: 'linear-gradient(135deg, #8b5cf6, #ec4899)', color: 'white', textAlign: 'center' }}>
                        <i className="fas fa-gift" style={{ fontSize: '48px', marginBottom: '12px' }}></i>
                        <h2 style={{ marginBottom: '8px' }}>Gift Card Rewards</h2>
                        <p style={{ opacity: '0.9' }}>Unlock premium gift cards with your balance</p>
                    </div>

                    {!canClaimGiftCards && (
                        <div className="agmedya-alert warning">
                            <h4>Gift Cards Currently Locked</h4>
                            <p>
                                {!user?.has_initial_deposit
                                    ? "Complete your $5 initial deposit to unlock all gift card rewards"
                                    : "Reach $10 balance to start claiming gift cards"
                                }
                            </p>
                            <div style={{ fontSize: '0.875rem' }}>
                                Progress: ${user?.balance || 0} / $10.00 required balance
                            </div>
                        </div>
                    )}

                    <div className="agmedya-grid cols-3">
                        {giftCards.map((card) => {
                            const isSelected = selectedGiftCard === card.id;
                            const isAvailable = canClaimGiftCards && card.available;

                            return (
                                <div
                                    key={card.id}
                                    className={`agmedya-gift-card ${isSelected ? 'selected' : ''} ${!isAvailable ? 'disabled' : ''}`}
                                    onClick={() => isAvailable && handleGiftCardClaim(card.id)}
                                >
                                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                        <div style={{ display: 'flex', alignItems: 'center' }}>
                                            <div style={{
                                                width: '64px',
                                                height: '64px',
                                                background: 'white',
                                                borderRadius: '12px',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                fontSize: '32px',
                                                marginRight: '16px',
                                                border: '1px solid #e2e8f0'
                                            }}>
                                                {card.logo}
                                            </div>
                                            <div>
                                                <p style={{ fontWeight: 'bold', fontSize: '1.125rem', color: '#1f2937' }}>{card.name}</p>
                                                <p style={{ color: '#6b7280', fontSize: '0.875rem' }}>${card.cost} Gift Card</p>
                                            </div>
                                        </div>
                                        <div>
                                            {isSelected && (
                                                <div style={{
                                                    background: '#dbeafe',
                                                    borderRadius: '50%',
                                                    padding: '8px',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center'
                                                }}>
                                                    <i className="fas fa-check" style={{ color: '#2563eb' }}></i>
                                                </div>
                                            )}
                                            {!isSelected && isAvailable && (
                                                <div style={{
                                                    background: '#f3f4f6',
                                                    borderRadius: '50%',
                                                    padding: '8px',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center'
                                                }}>
                                                    <i className="fas fa-gift" style={{ color: '#6b7280' }}></i>
                                                </div>
                                            )}
                                            {!isAvailable && (
                                                <div style={{
                                                    background: '#e5e7eb',
                                                    borderRadius: '50%',
                                                    padding: '8px',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center'
                                                }}>
                                                    <i className="fas fa-times" style={{ color: '#9ca3af' }}></i>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {selectedGiftCard && (
                        <div className="agmedya-alert success">
                            <i className="fas fa-check" style={{ marginRight: '8px' }}></i>
                            <strong>Gift Card Selected</strong><br />
                            <p style={{ margin: '8px 0' }}>
                                You have selected a {giftCards.find(c => c.id === selectedGiftCard)?.name} gift card worth $10.
                                This will be deducted from your balance upon confirmation.
                            </p>
                            <div style={{ display: 'flex', gap: '8px', marginTop: '16px' }}>
                                <button
                                    onClick={confirmClaim}
                                    disabled={isSubmitting}
                                    className="agmedya-btn"
                                    style={{ background: '#10b981' }}
                                >
                                    {isSubmitting ? 'Processing...' : 'Confirm Gift Card Claim'}
                                </button>
                                <button
                                    onClick={() => setSelectedGiftCard(null)}
                                    disabled={isSubmitting}
                                    style={{
                                        background: 'white',
                                        border: '1px solid #d1d5db',
                                        padding: '12px 24px',
                                        borderRadius: '8px',
                                        cursor: 'pointer'
                                    }}
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            );
        }

        // Products Section
        function ProductsSection({ user }) {
            const [productUrl, setProductUrl] = useState('');
            const [productImage, setProductImage] = useState(null);
            const [isSubmitting, setIsSubmitting] = useState(false);

            const handleFileChange = (e) => {
                const file = e.target.files?.[0];
                if (file) {
                    if (file.size > 5 * 1024 * 1024) { // 5MB limit
                        alert('File too large. Please select a file smaller than 5MB');
                        return;
                    }
                    setProductImage(file);
                }
            };

            const handleSubmit = async (e) => {
                e.preventDefault();
                if (!productUrl.trim()) return;

                setIsSubmitting(true);
                try {
                    const response = await wpAjax.post('agmedya_product_submission', {
                        product_url: productUrl.trim(),
                        product_image: productImage ? productImage.name : ''
                    });

                    if (response.success) {
                        alert('Product submitted successfully!');
                        setProductUrl('');
                        setProductImage(null);
                    } else {
                        alert('Error: ' + (response.data.message || 'Failed to submit product'));
                    }
                } catch (error) {
                    alert('Network error. Please try again.');
                } finally {
                    setIsSubmitting(false);
                }
            };

            return (
                <div className="agmedya-card">
                    <h3><i className="fas fa-file-alt" style={{ color: '#8b5cf6', marginRight: '8px' }}></i>Product Submission</h3>

                    <div className="agmedya-alert info">
                        <i className="fas fa-info-circle" style={{ marginRight: '8px' }}></i>
                        <strong>How It Works:</strong> Submit product URLs from verified platforms to earn rewards.
                        Each approved submission adds to your account balance.
                    </div>

                    <form onSubmit={handleSubmit}>
                        <div style={{ marginBottom: '16px' }}>
                            <label style={{ display: 'block', marginBottom: '8px', fontWeight: '500' }}>
                                <i className="fas fa-file-alt" style={{ color: '#3b82f6', marginRight: '8px' }}></i>
                                Product URL
                            </label>
                            <input
                                type="url"
                                value={productUrl}
                                onChange={(e) => setProductUrl(e.target.value)}
                                placeholder="https://amazon.com/product-name/dp/XXXXXXXXXX"
                                className="agmedya-input"
                                required
                            />
                            <p style={{ fontSize: '0.75rem', color: '#6b7280', marginTop: '8px' }}>
                                <i className="fas fa-check" style={{ color: '#10b981', marginRight: '4px' }}></i>
                                Supported: Amazon, eBay, AliExpress, Walmart, Target
                            </p>
                        </div>

                        <div style={{ marginBottom: '24px' }}>
                            <label style={{ display: 'block', marginBottom: '8px', fontWeight: '500' }}>
                                <i className="fas fa-upload" style={{ color: '#8b5cf6', marginRight: '8px' }}></i>
                                Product Image (Optional)
                            </label>
                            <div
                                style={{
                                    border: '2px dashed #d1d5db',
                                    borderRadius: '12px',
                                    padding: '32px',
                                    textAlign: 'center',
                                    cursor: 'pointer',
                                    transition: 'border-color 0.2s'
                                }}
                                onClick={() => document.getElementById('product-image')?.click()}
                            >
                                <i className="fas fa-upload" style={{ fontSize: '32px', color: '#9ca3af', marginBottom: '12px' }}></i>
                                <p style={{ fontWeight: '500', marginBottom: '4px' }}>
                                    {productImage ? productImage.name : 'Drop your image here or click to browse'}
                                </p>
                                <p style={{ color: '#9ca3af', fontSize: '0.875rem' }}>PNG, JPG up to 5MB</p>
                                <input
                                    id="product-image"
                                    type="file"
                                    accept="image/*"
                                    onChange={handleFileChange}
                                    style={{ display: 'none' }}
                                />
                            </div>
                        </div>

                        <button
                            type="submit"
                            disabled={!productUrl.trim() || isSubmitting}
                            className="agmedya-btn"
                            style={{ width: '100%', background: 'linear-gradient(135deg, #8b5cf6, #ec4899)' }}
                        >
                            <i className="fas fa-paper-plane" style={{ marginRight: '8px' }}></i>
                            {isSubmitting ? 'Submitting...' : 'Submit Product for Review'}
                        </button>
                    </form>

                    <div className="agmedya-alert success" style={{ marginTop: '24px' }}>
                        <i className="fab fa-wordpress" style={{ marginRight: '8px' }}></i>
                        <strong>WordPress Media Library:</strong> Images are automatically uploaded to your WordPress media library and linked to your submission.
                    </div>
                </div>
            );
        }

        // Settings Section
        function SettingsSection({ user }) {
            return (
                <div className="agmedya-card">
                    <h3><i className="fas fa-user" style={{ color: '#3b82f6', marginRight: '8px' }}></i>Account Settings</h3>

                    <div style={{ marginBottom: '24px' }}>
                        <h4>Profile Information</h4>
                        <div className="agmedya-grid cols-2" style={{ marginTop: '16px' }}>
                            <div>
                                <label style={{ display: 'block', marginBottom: '4px', fontSize: '0.875rem', fontWeight: '500' }}>First Name</label>
                                <input type="text" value={user?.first_name || ''} disabled className="agmedya-input" style={{ background: '#f9fafb' }} />
                            </div>
                            <div>
                                <label style={{ display: 'block', marginBottom: '4px', fontSize: '0.875rem', fontWeight: '500' }}>Last Name</label>
                                <input type="text" value={user?.last_name || ''} disabled className="agmedya-input" style={{ background: '#f9fafb' }} />
                            </div>
                        </div>
                        <div style={{ marginTop: '16px' }}>
                            <label style={{ display: 'block', marginBottom: '4px', fontSize: '0.875rem', fontWeight: '500' }}>Email Address</label>
                            <input type="email" value={user?.user_email || ''} disabled className="agmedya-input" style={{ background: '#f9fafb' }} />
                            <p style={{ fontSize: '0.75rem', color: '#6b7280', marginTop: '4px' }}>
                                <i className="fas fa-times" style={{ marginRight: '4px' }}></i>
                                Email is synchronized with WordPress and cannot be changed here
                            </p>
                        </div>
                    </div>

                    <div style={{ marginBottom: '24px' }}>
                        <h4><i className="fas fa-shield-alt" style={{ color: '#10b981', marginRight: '8px' }}></i>Account Status</h4>
                        <div style={{ marginTop: '16px' }}>
                            <div style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                padding: '16px',
                                background: '#dcfce7',
                                borderRadius: '12px',
                                border: '1px solid #86efac',
                                marginBottom: '8px'
                            }}>
                                <div style={{ display: 'flex', alignItems: 'center' }}>
                                    <i className="fas fa-check" style={{ color: '#10b981', marginRight: '12px' }}></i>
                                    <span style={{ fontWeight: '500' }}>WordPress Integration</span>
                                </div>
                                <span className="agmedya-badge success">Active</span>
                            </div>

                            <div style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                padding: '16px',
                                background: user?.has_initial_deposit ? '#dcfce7' : '#fee2e2',
                                borderRadius: '12px',
                                border: user?.has_initial_deposit ? '1px solid #86efac' : '1px solid #fca5a5'
                            }}>
                                <div style={{ display: 'flex', alignItems: 'center' }}>
                                    <i className={`fas fa-${user?.has_initial_deposit ? 'check' : 'times'}`} style={{ color: user?.has_initial_deposit ? '#10b981' : '#ef4444', marginRight: '12px' }}></i>
                                    <span style={{ fontWeight: '500' }}>Initial Deposit</span>
                                </div>
                                <span className={`agmedya-badge ${user?.has_initial_deposit ? 'success' : 'error'}`}>
                                    {user?.has_initial_deposit ? 'Complete' : 'Required'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="agmedya-alert info">
                        <i className="fas fa-sync-alt" style={{ marginRight: '8px' }}></i>
                        <strong>Auto-Sync Status:</strong> Your rewards account is automatically synchronized with your WordPress profile.
                    </div>
                </div>
            );
        }

        // Support Section
        function SupportSection() {
            const giftCardPlatforms = [
                'Amazon', 'Steam', 'Google Play', 'PlayStation',
                'Xbox', 'Mobile Legends', 'PUBG Mobile', 'Binance'
            ];

            return (
                <div>
                    <div className="agmedya-card">
                        <h3><i className="fas fa-info-circle" style={{ color: '#3b82f6', marginRight: '8px' }}></i>Support & Information</h3>

                        <div style={{ marginBottom: '32px' }}>
                            <h4>How the System Works</h4>
                            <div style={{ marginTop: '16px' }}>
                                {[
                                    { step: '1', title: 'Registration & WordPress Sync', desc: 'Your account integrates with WordPress for seamless management' },
                                    { step: '2', title: 'Initial Investment ($5)', desc: 'Verify your account and unlock all platform features' },
                                    { step: '3', title: 'Product Submissions', desc: 'Submit qualifying product URLs to earn balance rewards' },
                                    { step: '4', title: 'Gift Card Rewards', desc: 'Claim premium gift cards when you reach $10 balance' }
                                ].map((item) => (
                                    <div key={item.step} style={{ display: 'flex', alignItems: 'flex-start', marginBottom: '16px' }}>
                                        <div style={{
                                            width: '32px',
                                            height: '32px',
                                            background: '#3b82f6',
                                            color: 'white',
                                            borderRadius: '50%',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            fontWeight: 'bold',
                                            fontSize: '0.875rem',
                                            marginRight: '16px',
                                            flexShrink: 0
                                        }}>
                                            {item.step}
                                        </div>
                                        <div>
                                            <p style={{ fontWeight: '500', marginBottom: '4px' }}>{item.title}</p>
                                            <p style={{ color: '#6b7280', fontSize: '0.875rem' }}>{item.desc}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div>
                            <h4>Available Gift Card Platforms</h4>
                            <div className="agmedya-grid cols-2" style={{ marginTop: '16px' }}>
                                {giftCardPlatforms.map(platform => (
                                    <div key={platform} style={{
                                        padding: '12px',
                                        background: '#f8fafc',
                                        borderRadius: '8px',
                                        textAlign: 'center',
                                        border: '1px solid #e2e8f0'
                                    }}>
                                        <i className="fas fa-gift" style={{ color: '#8b5cf6', marginBottom: '4px' }}></i>
                                        <p style={{ fontSize: '0.875rem', fontWeight: '500' }}>{platform}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="agmedya-card" style={{ background: 'linear-gradient(135deg, #eff6ff, #e0e7ff)', border: '1px solid #93c5fd' }}>
                        <h3 style={{ color: '#1e40af' }}><i className="fab fa-wordpress" style={{ marginRight: '8px' }}></i>WordPress Integration Features</h3>

                        <div className="agmedya-grid cols-2" style={{ marginTop: '16px' }}>
                            <div style={{ fontSize: '0.875rem', color: '#1e40af' }}>
                                <div style={{ display: 'flex', alignItems: 'center', marginBottom: '12px' }}>
                                    <i className="fas fa-database" style={{ marginRight: '8px' }}></i>
                                    <span>Complete database integration with WP tables</span>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', marginBottom: '12px' }}>
                                    <i className="fas fa-users" style={{ marginRight: '8px' }}></i>
                                    <span>Automatic user synchronization</span>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', marginBottom: '12px' }}>
                                    <i className="fas fa-shield-alt" style={{ marginRight: '8px' }}></i>
                                    <span>WordPress security standards compliance</span>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center' }}>
                                    <i className="fas fa-mobile-alt" style={{ marginRight: '8px' }}></i>
                                    <span>Fully responsive design</span>
                                </div>
                            </div>
                            <div style={{ fontSize: '0.875rem', color: '#1e40af' }}>
                                <div style={{ display: 'flex', alignItems: 'center', marginBottom: '12px' }}>
                                    <i className="fas fa-code" style={{ marginRight: '8px' }}></i>
                                    <span>Easy shortcode implementation</span>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', marginBottom: '12px' }}>
                                    <i className="fas fa-upload" style={{ marginRight: '8px' }}></i>
                                    <span>WordPress media library integration</span>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', marginBottom: '12px' }}>
                                    <i className="fas fa-cog" style={{ marginRight: '8px' }}></i>
                                    <span>Admin panel management tools</span>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center' }}>
                                    <i className="fas fa-plug" style={{ marginRight: '8px' }}></i>
                                    <span>Plugin compatibility guaranteed</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="agmedya-card" style={{ textAlign: 'center' }}>
                        <div style={{ fontSize: '0.75rem', color: '#6b7280' }}>
                            <p>© 2024 AgMedya User Rewards System. All rights reserved.</p>
                            <p>WordPress Plugin v1.0.0 | React Frontend v1.0.0</p>
                            <p style={{ display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <span style={{ width: '8px', height: '8px', background: '#10b981', borderRadius: '50%', marginRight: '8px' }}></span>
                                System Status: Active | Last Updated: January 2024
                            </p>
                        </div>
                    </div>
                </div>
            );
        }

        // Render the application
        ReactDOM.render(<AgMedyaRewardsApp />, document.getElementById('agmedya-rewards-root'));
        </script>
        <?php
        return ob_get_clean();
    }

    // AJAX Handlers
    public function handle_auth() {
        check_ajax_referer('agmedya_nonce', 'nonce');

        $mode = sanitize_text_field($_POST['mode']);
        $email = sanitize_email($_POST['email']);
        $password = sanitize_text_field($_POST['password']);

        if ($mode === 'register') {
            $first_name = sanitize_text_field($_POST['first_name']);
            $last_name = sanitize_text_field($_POST['last_name']);

            // Check if user exists
            $existing_user = get_user_by('email', $email);
            if ($existing_user) {
                wp_send_json_error(array('message' => 'User already exists with this email'));
                return;
            }

            // Create WordPress user
            $user_id = wp_create_user($email, $password, $email);
            if (is_wp_error($user_id)) {
                wp_send_json_error(array('message' => $user_id->get_error_message()));
                return;
            }

            // Update user meta
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $first_name . ' ' . $last_name
            ));

            // Create rewards record
            global $wpdb;
            $table_name = $wpdb->prefix . 'agmedya_user_rewards';
            $wpdb->insert($table_name, array(
                'user_id' => $user_id,
                'balance' => '0.00',
                'has_initial_deposit' => 0,
                'approved_products' => 0
            ));

            $user = get_user_by('id', $user_id);
            wp_send_json_success(array(
                'user' => array(
                    'id' => $user->ID,
                    'user_email' => $user->user_email,
                    'first_name' => get_user_meta($user->ID, 'first_name', true),
                    'last_name' => get_user_meta($user->ID, 'last_name', true),
                    'display_name' => $user->display_name
                )
            ));

        } else {
            // Login
            $user = wp_authenticate($email, $password);
            if (is_wp_error($user)) {
                wp_send_json_error(array('message' => 'Invalid credentials'));
                return;
            }

            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);

            wp_send_json_success(array(
                'user' => array(
                    'id' => $user->ID,
                    'user_email' => $user->user_email,
                    'first_name' => get_user_meta($user->ID, 'first_name', true),
                    'last_name' => get_user_meta($user->ID, 'last_name', true),
                    'display_name' => $user->display_name
                )
            ));
        }
    }

    public function get_dashboard_data() {
        check_ajax_referer('agmedya_nonce', 'nonce');

        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);

        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
            return;
        }

        global $wpdb;
        $rewards_table = $wpdb->prefix . 'agmedya_user_rewards';

        $rewards_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $rewards_table WHERE user_id = %d",
            $user_id
        ));

        if (!$rewards_data) {
            // Create rewards record if it doesn't exist
            $wpdb->insert($rewards_table, array(
                'user_id' => $user_id,
                'balance' => '0.00',
                'has_initial_deposit' => 0,
                'approved_products' => 0
            ));

            $rewards_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $rewards_table WHERE user_id = %d",
                $user_id
            ));
        }

        wp_send_json_success(array(
            'user' => array(
                'id' => $user->ID,
                'user_email' => $user->user_email,
                'first_name' => get_user_meta($user->ID, 'first_name', true),
                'last_name' => get_user_meta($user->ID, 'last_name', true),
                'display_name' => $user->display_name,
                'balance' => $rewards_data->balance,
                'has_initial_deposit' => (bool)$rewards_data->has_initial_deposit,
                'approved_products' => $rewards_data->approved_products
            )
        ));
    }

    public function handle_investment() {
        check_ajax_referer('agmedya_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'User not logged in'));
            return;
        }

        $user_id = get_current_user_id();
        $amount = floatval($_POST['amount']);
        $method = sanitize_text_field($_POST['method']);
        $payment_details = sanitize_text_field($_POST['payment_details']);

        global $wpdb;
        $investments_table = $wpdb->prefix . 'agmedya_investments';

        $result = $wpdb->insert($investments_table, array(
            'user_id' => $user_id,
            'amount' => $amount,
            'method' => $method,
            'payment_details' => $payment_details,
            'status' => 'pending'
        ));

        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to submit investment'));
            return;
        }

        wp_send_json_success(array('message' => 'Investment submitted successfully'));
    }

    public function handle_product_submission() {
        check_ajax_referer('agmedya_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'User not logged in'));
            return;
        }

        $user_id = get_current_user_id();
        $product_url = esc_url_raw($_POST['product_url']);
        $product_image = sanitize_text_field($_POST['product_image']);

        global $wpdb;
        $submissions_table = $wpdb->prefix . 'agmedya_product_submissions';

        $result = $wpdb->insert($submissions_table, array(
            'user_id' => $user_id,
            'product_url' => $product_url,
            'product_image' => $product_image,
            'status' => 'pending'
        ));

        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to submit product'));
            return;
        }

        wp_send_json_success(array('message' => 'Product submitted successfully'));
    }

    public function handle_gift_card_claim() {
        check_ajax_referer('agmedya_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'User not logged in'));
            return;
        }

        $user_id = get_current_user_id();
        $gift_card_type = sanitize_text_field($_POST['gift_card_type']);
        $cost = floatval($_POST['cost']);

        global $wpdb;

        // Check user balance and deposit status
        $rewards_table = $wpdb->prefix . 'agmedya_user_rewards';
        $user_rewards = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $rewards_table WHERE user_id = %d",
            $user_id
        ));

        if (!$user_rewards || !$user_rewards->has_initial_deposit) {
            wp_send_json_error(array('message' => 'Initial deposit required'));
            return;
        }

        if (floatval($user_rewards->balance) < $cost) {
            wp_send_json_error(array('message' => 'Insufficient balance'));
            return;
        }

        // Create gift card claim
        $gift_cards_table = $wpdb->prefix . 'agmedya_gift_card_claims';
        $result = $wpdb->insert($gift_cards_table, array(
            'user_id' => $user_id,
            'gift_card_type' => $gift_card_type,
            'cost' => $cost,
            'status' => 'pending'
        ));

        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to claim gift card'));
            return;
        }

        // Deduct balance
        $new_balance = floatval($user_rewards->balance) - $cost;
        $wpdb->update(
            $rewards_table,
            array('balance' => number_format($new_balance, 2, '.', '')),
            array('user_id' => $user_id)
        );

        wp_send_json_success(array('message' => 'Gift card claimed successfully'));
    }
}

// Initialize the plugin
new AgMedyaUserRewards();
?>
