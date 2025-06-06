<script type="text/babel">
            const { useState, useEffect, useCallback, Fragment } = React;

            const wpAjax = {
                post: async (action, data = {}) => {
                    const formData = new FormData();
                    formData.append('action', '<?php echo LIORA_REWARDS_PREFIX; ?>' + action);
                    formData.append('nonce', <?php echo LIORA_REWARDS_PREFIX; ?>ajax.nonce);
                    
                    Object.keys(data).forEach(key => {
                        if (data[key] instanceof File) {
                            formData.append(key, data[key], data[key].name);
                        } else if (data[key] !== null && data[key] !== undefined) {
                            formData.append(key, data[key]);
                        }
                    });

                    const response = await fetch(<?php echo LIORA_REWARDS_PREFIX; ?>ajax.ajax_url, {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('AJAX HTTP Error:', response.status, errorText);
                        throw new Error(`<?php _e('Að yanýtý sorunlu: ', 'liora-rewards'); ?> ${response.statusText}`);
                    }
                    return await response.json();
                }
            };

            function LioraRewardsApp() {
                const [view, setView] = useState('loading');
                const [currentUser, setCurrentUser] = useState(null);
                const [activeSection, setActiveSection] = useState('overview');
                const [dashboardData, setDashboardData] = useState(null);
                const [appError, setAppError] = useState('');

                const checkUserSessionAndLoadData = useCallback(async () => {
                    setView('loading');
                    try {
                        const sessionResponse = await wpAjax.post('check_session');
                        if (sessionResponse.success && sessionResponse.data.logged_in) {
                            const dashResponse = await wpAjax.post('get_dashboard_data');
                            if (dashResponse.success) {
                                setCurrentUser(dashResponse.data.user);
                                setDashboardData(dashResponse.data);
                                setView('dashboard');
                            } else {
                                throw new Error(dashResponse.data?.message || '<?php _e('Kullanýcý paneli verileri alýnamadý.', 'liora-rewards'); ?>');
                            }
                        } else {
                            setView('auth');
                        }
                    } catch (error) {
                        console.error('Session check or initial load failed:', error);
                        setAppError(error.message || '<?php _e('Oturum bilgileri yüklenirken bir sorun oluþtu.', 'liora-rewards'); ?>');
                        setView('auth');
                    }
                }, []);
                
                useEffect(() => {
                    checkUserSessionAndLoadData();
                }, [checkUserSessionAndLoadData]);
                
                const handleLoginSuccess = useCallback(async () => {
                    await checkUserSessionAndLoadData();
                }, [checkUserSessionAndLoadData]);

                const handleLogout = () => {
                    window.location.href = '<?php echo wp_logout_url(get_permalink()); ?>';
                };

                const renderContent = () => {
                    switch(view) {
                        case 'loading':
                            return (
                                <div className="liora-rewards-loading">
                                    <div className="liora-rewards-spinner"></div>
                                    <h3>{<?php echo LIORA_REWARDS_PREFIX; ?>ajax.i18n.loading}</h3>
                                </div>
                            );
                        case 'auth':
                            return <AuthView onLoginSuccess={handleLoginSuccess} />;
                        case 'dashboard':
                            if (currentUser && dashboardData) {
                                return (
                                    <DashboardView
                                        user={currentUser}
                                        dashboardData={dashboardData}
                                        activeSection={activeSection}
                                        setActiveSection={setActiveSection}
                                        onLogout={handleLogout}
                                        refreshDashboardData={checkUserSessionAndLoadData}
                                    />
                                );
                            }
                            // Veri eksikse yükleme ekraný göster
                            return (
                                <div className="liora-rewards-loading">
                                    <div className="liora-rewards-spinner"></div>
                                    <h3><?php _e('Kullanýcý Paneli Yükleniyor...', 'liora-rewards'); ?></h3>
                                </div>
                            );
                        default:
                            return (
                                <div className="liora-rewards-alert error">
                                    <i className="fas fa-exclamation-triangle"></i>
                                    <span>{appError || '<?php _e('Uygulama yüklenirken beklenmedik bir hata oluþtu.', 'liora-rewards'); ?>'}</span>
                                    <button onClick={checkUserSessionAndLoadData} className="liora-rewards-btn liora-rewards-btn-secondary" style={{marginLeft:'auto'}}><?php _e('Tekrar Dene', 'liora-rewards'); ?></button>
                                </div>
                            );
                    }
                };

                return <div className="liora-rewards-container">{renderContent()}</div>;
            }

            function AuthView({ onLoginSuccess }) {
                // ... AuthView'in tam içeriði ...
            }
            
            function DashboardView({ user, dashboardData, activeSection, setActiveSection, onLogout, refreshDashboardData }) {
                // ... DashboardView'in tam içeriði ...
            }

            // Diðer tüm React component'leri (OverviewSection, ActivationSection, vb.) buraya eklenecek
            // ...

            const rootElement = document.getElementById('<?php echo LIORA_REWARDS_PREFIX; ?>app-root');
            if (rootElement) {
                const root = ReactDOM.createRoot(rootElement);
                root.render(<LioraRewardsApp />);
            }

        </script>
        <?php
        return ob_get_clean();
    }
    
    // PHP AJAX HANDLER FONKSÝYONLARI BURADA BAÞLIYOR

    public function handle_check_session() {
        check_ajax_referer(LIORA_REWARDS_PREFIX . 'nonce', 'nonce');
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            wp_send_json_success(array(
                'logged_in' => true,
                'user_data' => array(
                    'id' => $user->ID,
                    'user_email' => $user->user_email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'display_name' => $user->display_name
                )
            ));
        } else {
            wp_send_json_success(array('logged_in' => false));
        }
        wp_die();
    }
    
    public function handle_auth() {
        check_ajax_referer(LIORA_REWARDS_PREFIX . 'nonce', 'nonce');

        $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'login';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => __('Lütfen geçerli bir e-posta adresi girin.', 'liora-rewards')));
        }
        if (empty($password) || strlen($password) < 6) {
             wp_send_json_error(array('message' => __('Þifre en az 6 karakter olmalýdýr.', 'liora-rewards')));
        }

        if ($mode === 'register') {
            $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
            $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';

            if (empty($first_name) || empty($last_name)) {
                wp_send_json_error(array('message' => __('Ad ve soyad alanlarý zorunludur.', 'liora-rewards')));
            }

            $user_id = wp_create_user($email, $password, $email);
            if (is_wp_error($user_id)) {
                wp_send_json_error(array('message' => $user_id->get_error_message()));
            }

            wp_update_user(array( 'ID' => $user_id, 'first_name' => $first_name, 'last_name' => $last_name, 'display_name' => $first_name . ' ' . $last_name ));
            
            global $wpdb;
            $table_name_user_rewards = $wpdb->prefix . LIORA_REWARDS_PREFIX . 'user_rewards';
            $wpdb->insert($table_name_user_rewards, array( 'user_id' => $user_id, 'balance' => '0.00', 'activation_bonus_status' => 0, 'shopping_reward_balance' => '0.00', 'approved_products_count' => 0 ));
            
            wp_send_json_success(array( 'message' => __('Hesap baþarýyla oluþturuldu!', 'liora-rewards') ));

        } else { // Login
            $user = wp_authenticate($email, $password);
            if (is_wp_error($user)) {
                wp_send_json_error(array('message' => __('Geçersiz e-posta veya þifre.', 'liora-rewards')));
            }

            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
            
            wp_send_json_success(array('user' => array( 'id' => $user->ID, 'display_name' => $user->display_name )));
        }
        wp_die();
    }

    public function get_dashboard_data() {
        check_ajax_referer(LIORA_REWARDS_PREFIX . 'nonce', 'nonce');

        if (!is_user_logged_in()) {
             wp_send_json_error(array('message' => __('Oturumunuz sonlanmýþ. Lütfen tekrar giriþ yapýn.', 'liora-rewards')));
        }
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);

        global $wpdb;
        $rewards_table = $wpdb->prefix . LIORA_REWARDS_PREFIX . 'user_rewards';
        $rewards_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rewards_table WHERE user_id = %d", $user_id));

        if (!$rewards_data) {
            $wpdb->insert($rewards_table, array( 'user_id' => $user_id, 'balance' => '0.00', 'activation_bonus_status' => 0, 'shopping_reward_balance' => '0.00', 'approved_products_count' => 0 ));
            $rewards_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rewards_table WHERE user_id = %d", $user_id));
        }

        wp_send_json_success(array(
            'user' => array(
                'id' => $user->ID, 'user_email' => $user->user_email, 'first_name' => $user->first_name,
                'last_name' => $user->last_name, 'display_name' => $user->display_name,
                'balance' => $rewards_data->balance,
                'activation_bonus_status' => (int)$rewards_data->activation_bonus_status,
                'shopping_reward_balance' => $rewards_data->shopping_reward_balance,
                'approved_products_count' => (int)$rewards_data->approved_products_count
            )
        ));
        wp_die();
    }
    
    public function handle_activation_payment() {
        check_ajax_referer(LIORA_REWARDS_PREFIX . 'nonce', 'nonce');

        if (!is_user_logged_in()) { wp_send_json_error(array('message' => __('Lütfen önce giriþ yapýn.', 'liora-rewards'))); }

        $user_id = get_current_user_id();
        global $wpdb;
        $rewards_table = $wpdb->prefix . LIORA_REWARDS_PREFIX . 'user_rewards';
        $user_rewards_status = $wpdb->get_var($wpdb->prepare("SELECT activation_bonus_status FROM $rewards_table WHERE user_id = %d", $user_id));

        if ($user_rewards_status == 1 || $user_rewards_status == 2) {
             wp_send_json_error(array('message' => __('Ödül aktivasyon iþlemi zaten yapýlmýþ veya beklemede.', 'liora-rewards')));
        }

        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        if ($amount !== 5.00) {
            wp_send_json_error(array('message' => __('Aktivasyon için tam olarak $5 ödeme yapýlmalýdýr.', 'liora-rewards')));
        }
        $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';
        $payment_details = isset($_POST['payment_details']) ? sanitize_textarea_field($_POST['payment_details']) : '';
        $transaction_hash = isset($_POST['transaction_hash']) ? sanitize_text_field($_POST['transaction_hash']) : null;

        $payments_table = $wpdb->prefix . LIORA_REWARDS_PREFIX . 'activation_payments';
        $wpdb->insert($payments_table, array( 'user_id' => $user_id, 'amount' => $amount, 'method' => $method, 'payment_details' => $payment_details, 'status' => 'pending', 'transaction_hash' => $transaction_hash ));
        
        $wpdb->update( $rewards_table, array('activation_bonus_status' => 1), array('user_id' => $user_id) );

        wp_send_json_success(array('message' => __('Aktivasyon ödeme bildiriminiz alýndý. Onay süreci 3-7 iþ günü sürebilir.', 'liora-rewards')));
        wp_die();
    }

    public function handle_product_submission() {
        check_ajax_referer(LIORA_REWARDS_PREFIX . 'nonce', 'nonce');

        if (!is_user_logged_in()) { wp_send_json_error(array('message' => __('Lütfen önce giriþ yapýn.', 'liora-rewards'))); }

        $user_id = get_current_user_id();
        $product_url = isset($_POST['product_url']) ? esc_url_raw($_POST['product_url']) : '';
        $product_image_file = isset($_FILES['product_image']) ? $_FILES['product_image'] : null;
        $product_image_path = null;

        if (empty($product_url)) { wp_send_json_error(array('message' => __('Ürün URL\'si zorunludur.', 'liora-rewards'))); }
        
        if ($product_image_file && $product_image_file['size'] > 0) {
            if (!function_exists('wp_handle_upload')) { require_once(ABSPATH . 'wp-admin/includes/file.php'); }
            $uploaded_file = wp_handle_upload($product_image_file, array('test_form' => false));
            if ($uploaded_file && !isset($uploaded_file['error'])) {
                $attachment = array( 'guid' => $uploaded_file['url'], 'post_mime_type' => $uploaded_file['type'], 'post_title' => preg_replace('/\.[^.]+$/', '', basename($uploaded_file['file'])), 'post_content' => '', 'post_status' => 'inherit' );
                $attach_id = wp_insert_attachment( $attachment, $uploaded_file['file'] );
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $uploaded_file['file'] ) );
                $product_image_path = $attach_id;
            } else { wp_send_json_error(array('message' => __('Resim yüklenirken hata: ', 'liora-rewards') . $uploaded_file['error'])); }
        }

        global $wpdb;
        $submissions_table = $wpdb->prefix . LIORA_REWARDS_PREFIX . 'product_submissions';
        $wpdb->insert($submissions_table, array( 'user_id' => $user_id, 'product_url' => $product_url, 'product_image_attach_id' => $product_image_path, 'status' => 'pending', 'reward_amount' => '1.00' ));

        wp_send_json_success(array('message' => __('Ürün gönderim talebiniz alýndý. Onaylandýktan sonra bakiyenize eklenecektir.', 'liora-rewards')));
        wp_die();
    }

    public function handle_gift_card_claim() {
        check_ajax_referer(LIORA_REWARDS_PREFIX . 'nonce', 'nonce');

        if (!is_user_logged_in()) { wp_send_json_error(array('message' => __('Lütfen önce giriþ yapýn.', 'liora-rewards'))); }

        $user_id = get_current_user_id();
        $gift_card_type = isset($_POST['gift_card_type']) ? sanitize_text_field($_POST['gift_card_type']) : '';
        $cost = isset($_POST['cost']) ? floatval($_POST['cost']) : 0;
        $claim_method = isset($_POST['claim_method']) ? sanitize_text_field($_POST['claim_method']) : 'unknown';

        if (empty($gift_card_type) || $cost != 10.00) { wp_send_json_error(array('message' => __('Geçersiz hediye kartý bilgileri.', 'liora-rewards'))); }
        
        global $wpdb;
        $rewards_table = $wpdb->prefix . LIORA_REWARDS_PREFIX . 'user_rewards';
        $user_rewards = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rewards_table WHERE user_id = %d", $user_id));

        if (!$user_rewards || floatval($user_rewards->balance) < $cost) {
            wp_send_json_error(array('message' => __('Hediye kartý talep etmek için yetersiz bakiye.', 'liora-rewards')));
        }

        $gift_cards_table = $wpdb->prefix . LIORA_REWARDS_PREFIX . 'gift_card_claims';
        $wpdb->insert($gift_cards_table, array( 'user_id' => $user_id, 'gift_card_type' => $gift_card_type, 'cost' => $cost, 'status' => 'pending_fulfillment', 'claim_method' => $claim_method, 'delivery_email' => get_userdata($user_id)->user_email ));
        
        $new_balance = floatval($user_rewards->balance) - $cost;
        $wpdb->update( $rewards_table, array('balance' => number_format($new_balance, 2, '.', '')), array('user_id' => $user_id) );
        
        wp_send_json_success(array('message' => __('Hediye kartý talebiniz baþarýyla alýndý!', 'liora-rewards')));
        wp_die();
    }

    public function add_admin_menu_pages() {
        add_menu_page( __('Liora Ödül Paneli', 'liora-rewards'), __('Liora Ödüller', 'liora-rewards'), 'manage_options', LIORA_REWARDS_PREFIX . 'admin_dashboard', array($this, 'render_admin_page_callback'), 'dashicons-awards', 25 );
        add_submenu_page( LIORA_REWARDS_PREFIX . 'admin_dashboard', __('Genel Bakýþ', 'liora-rewards'), __('Genel Bakýþ', 'liora-rewards'), 'manage_options', LIORA_REWARDS_PREFIX . 'admin_dashboard', array($this, 'render_admin_page_callback') );
        add_submenu_page( LIORA_REWARDS_PREFIX . 'admin_dashboard', __('Kullanýcý Yönetimi', 'liora-rewards'), __('Kullanýcýlar', 'liora-rewards'), 'manage_options', LIORA_REWARDS_PREFIX . 'admin_users', array($this, 'render_admin_page_callback') );
        add_submenu_page( LIORA_REWARDS_PREFIX . 'admin_dashboard', __('Aktivasyon Talepleri', 'liora-rewards'), __('Aktivasyon Talepleri', 'liora-rewards'), 'manage_options', LIORA_REWARDS_PREFIX . 'admin_activation_payments', array($this, 'render_admin_page_callback') );
        add_submenu_page( LIORA_REWARDS_PREFIX . 'admin_dashboard', __('Ürün Gönderimleri', 'liora-rewards'), __('Ürün Gönderimleri', 'liora-rewards'), 'manage_options', LIORA_REWARDS_PREFIX . 'admin_product_submissions', array($this, 'render_admin_page_callback') );
        add_submenu_page( LIORA_REWARDS_PREFIX . 'admin_dashboard', __('Hediye Kartý Talepleri', 'liora-rewards'), __('Hediye Kartý Talepleri', 'liora-rewards'), 'manage_options', LIORA_REWARDS_PREFIX . 'admin_gift_card_claims', array($this, 'render_admin_page_callback') );
        add_submenu_page( LIORA_REWARDS_PREFIX . 'admin_dashboard', __('Eklenti Ayarlarý', 'liora-rewards'), __('Ayarlar', 'liora-rewards'), 'manage_options', LIORA_REWARDS_PREFIX . 'admin_settings', array($this, 'render_admin_page_callback') );
    }

    public function render_admin_page_callback() {
        // Bu taslak fonksiyon, gelecekte admin sayfalarý oluþturulduðunda her bir sayfa için ayrý ayrý render fonksiyonlarýna ayrýlmalýdýr.
        echo '<div class="wrap"><h1>' . esc_html__('Liora Holding Ödül Sistemi Yönetim Paneli', 'liora-rewards') . '</h1><p>' . esc_html__('Bu bölüm geliþtirme aþamasýndadýr.', 'liora-rewards') . '</p></div>';
    }

} 

if (class_exists('LioraRewardsUserRewards')) {
    new LioraRewardsUserRewards();
}