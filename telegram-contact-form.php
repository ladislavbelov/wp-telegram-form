<?php
/*
Plugin Name: Telegram Contact Form
Description: A custom contact form that sends submissions to Telegram and stores them in the WordPress database.
Version: 1.5
Author: Vlad Belov
*/

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Initialize sessions for captcha
add_action('init', 'tcf_start_session', 1);
function tcf_start_session() {
    if (!session_id()) {
        session_start();
    }
}

// Clear session on login/logout
add_action('wp_logout', 'tcf_end_session');
add_action('wp_login', 'tcf_end_session');
function tcf_end_session() {
    session_destroy();
}

// Create database table on plugin activation
register_activation_hook(__FILE__, 'tcf_create_table');
function tcf_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'telegram_form_requests';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) DEFAULT NULL,
        email varchar(255) DEFAULT NULL,
        phone varchar(50) DEFAULT NULL,
        telegram_username varchar(255) DEFAULT NULL,
        message text DEFAULT NULL,
        ip_address varchar(45) DEFAULT NULL,
        submitted_at datetime NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Drop database table on plugin deactivation (for testing purposes)
register_deactivation_hook(__FILE__, 'tcf_drop_table');
function tcf_drop_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'telegram_form_requests';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

// Enqueue styles and scripts for the front-end
add_action('wp_enqueue_scripts', 'tcf_enqueue_scripts');
function tcf_enqueue_scripts() {
    // Enqueue basic styles if enabled
    if (get_option('tcf_enqueue_basic_styles', 1)) {
        wp_enqueue_style('tcf-styles', plugin_dir_url(__FILE__) . 'assets/css/tcf-styles.css');
    }

    // Add custom CSS if provided
    $custom_css = get_option('tcf_custom_css', '');
    if (!empty($custom_css)) {
        wp_add_inline_style('tcf-styles', $custom_css);
    }

    wp_enqueue_script('tcf-script', plugin_dir_url(__FILE__) . 'assets/js/tcf-script.js', array('jquery'), '1.1', true);
    wp_localize_script('tcf-script', 'tcfAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('tcf_nonce')
    ));
}

// Shortcode to render the contact form
add_shortcode('telegram_contact_form', 'tcf_render_form');
function tcf_render_form() {
    // Get field visibility settings
    $show_name = get_option('tcf_show_name', 1);
    $show_email = get_option('tcf_show_email', 1);
    $show_phone = get_option('tcf_show_phone', 1);
    $show_telegram = get_option('tcf_show_telegram', 1);
    $show_message = get_option('tcf_show_message', 1);
    $require_name = get_option('tcf_require_name', 1);
    $require_email = get_option('tcf_require_email', 1);
    $require_phone = get_option('tcf_require_phone', 1);
    $require_telegram = get_option('tcf_require_telegram', 1);
    $require_message = get_option('tcf_require_message', 1);
    $enable_captcha = get_option('tcf_enable_captcha', 1);

    ob_start();
    ?>
    <form id="telegram-contact-form" class="tcf-form">
        <?php if ($show_name): ?>
        <div class="form-group <?php echo $require_name ? 'required' : ''; ?>">
            <label for="tcf-name">Name<?php echo $require_name ? '*' : ''; ?>:</label>
            <input type="text" id="tcf-name" name="name" <?php echo $require_name ? 'required' : ''; ?>>
        </div>
        <?php endif; ?>
        <?php if ($show_email): ?>
        <div class="form-group <?php echo $require_email ? 'required' : ''; ?>">
            <label for="tcf-email">Email<?php echo $require_email ? '*' : ''; ?>:</label>
            <input type="email" id="tcf-email" name="email" <?php echo $require_email ? 'required' : ''; ?>>
        </div>
        <?php endif; ?>
        <?php if ($show_phone): ?>
        <div class="form-group <?php echo $require_phone ? 'required' : ''; ?>">
            <label for="tcf-phone">Phone<?php echo $require_phone ? '*' : ''; ?>:</label>
            <input type="tel" id="tcf-phone" name="phone" <?php echo $require_phone ? 'required' : ''; ?>>
        </div>
        <?php endif; ?>
        <?php if ($show_telegram): ?>
        <div class="form-group <?php echo $require_telegram ? 'required' : ''; ?>">
            <label for="tcf-telegram">Telegram Username<?php echo $require_telegram ? '*' : ''; ?>:</label>
            <input type="text" id="tcf-telegram" name="telegram_username" placeholder="@username" <?php echo $require_telegram ? 'required' : ''; ?>>
        </div>
        <?php endif; ?>
        <?php if ($show_message): ?>
        <div class="form-group <?php echo $require_message ? 'required' : ''; ?>">
            <label for="tcf-message">Message<?php echo $require_message ? '*' : ''; ?>:</label>
            <textarea id="tcf-message" name="message" <?php echo $require_message ? 'required' : ''; ?>></textarea>
        </div>
        <?php endif; ?>
        <?php if ($enable_captcha): ?>
        <?php
        // Start session if not already started
        if (!session_id()) {
            session_start();
        }
        $num1 = rand(1, 9);
        $num2 = rand(1, 9);
        $captcha_answer = $num1 + $num2;
        $_SESSION['tcf_captcha_answer'] = $captcha_answer;
        ?>
        <div class="form-group required">
            <label for="tcf-captcha">Captcha: <?php echo "$num1 + $num2 = ?"; ?></label>
            <input type="number" id="tcf-captcha" name="captcha" required>
        </div>
        <?php endif; ?>
        <button type="submit" class="tcf-submit-btn">Send Request</button>
        <div class="form-message"></div>
    </form>
    <?php
    return ob_get_clean();
}

// Handle AJAX form submission
add_action('wp_ajax_tcf_submit_form', 'tcf_submit_form');
add_action('wp_ajax_nopriv_tcf_submit_form', 'tcf_submit_form');
function tcf_submit_form() {
    check_ajax_referer('tcf_nonce', 'nonce');

    // Check built-in captcha if enabled
    $enable_captcha = get_option('tcf_enable_captcha', 1);
    if ($enable_captcha) {
        // Start session if not already started
        if (!session_id()) {
            session_start();
        }
        $captcha_answer = isset($_SESSION['tcf_captcha_answer']) ? intval($_SESSION['tcf_captcha_answer']) : 0;
        $user_captcha = isset($_POST['captcha']) ? intval($_POST['captcha']) : 0;
        if ($user_captcha !== $captcha_answer) {
            wp_send_json_error('Incorrect captcha answer. Please try again.');
        }
        // Clear captcha session after verification
        unset($_SESSION['tcf_captcha_answer']);
    }

    // Check CleanTalk if enabled
    $enable_cleantalk = get_option('tcf_enable_cleantalk', 1);
    if ($enable_cleantalk) {
        $cleantalk_api_key = get_option('tcf_cleantalk_api_key', '');
        if (!empty($cleantalk_api_key)) {
            $cleantalk_url = 'https://moderate.cleantalk.org/api2.0';
            $cleantalk_data = array(
                'method_name' => 'check_message',
                'auth_key' => $cleantalk_api_key,
                'sender_ip' => $_SERVER['REMOTE_ADDR'],
                'sender_email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
                'message' => isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '',
            );

            $response = wp_remote_post($cleantalk_url, array(
                'body' => $cleantalk_data,
                'timeout' => 10,
            ));

            if (!is_wp_error($response)) {
                $response_body = wp_remote_retrieve_body($response);
                $response_data = json_decode($response_body, true);
                if (isset($response_data['allow']) && $response_data['allow'] == 0) {
                    wp_send_json_error('Your submission was flagged as spam by CleanTalk.');
                }
            }
        }
    }

    // Get field visibility settings
    $show_name = get_option('tcf_show_name', 1);
    $show_email = get_option('tcf_show_email', 1);
    $show_phone = get_option('tcf_show_phone', 1);
    $show_telegram = get_option('tcf_show_telegram', 1);
    $show_message = get_option('tcf_show_message', 1);

    // Collect form data, set empty string for disabled fields
    $name = $show_name && isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $email = $show_email && isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $phone = $show_phone && isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $telegram_username = $show_telegram && isset($_POST['telegram_username']) ? sanitize_text_field($_POST['telegram_username']) : '';
    $message = $show_message && isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $submitted_at = current_time('mysql');

    // Save submission to the database
    global $wpdb;
    $table_name = $wpdb->prefix . 'telegram_form_requests';
    $wpdb->insert($table_name, array(
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'telegram_username' => $telegram_username,
        'message' => $message,
        'ip_address' => $ip_address,
        'submitted_at' => $submitted_at,
    ));

    // Send to Telegram if enabled
    $enable_telegram = get_option('tcf_enable_telegram', 1);
    $bot_token = get_option('tcf_bot_token', '');
    $chat_id = get_option('tcf_chat_id', '');

    $telegram_message = "New Contact Form Submission\n\n";
    if ($show_name) $telegram_message .= "Name: $name\n";
    if ($show_email) $telegram_message .= "Email: $email\n";
    if ($show_phone) $telegram_message .= "Phone: $phone\n";
    if ($show_telegram) $telegram_message .= "Telegram: $telegram_username\n";
    if ($show_message) $telegram_message .= "Message: $message\n";
    $telegram_message .= "IP: $ip_address\n";
    $telegram_message .= "Submitted: $submitted_at\n";

    if ($enable_telegram && (!empty($bot_token) && !empty($chat_id))) {
        $telegram_url = "https://api.telegram.org/bot$bot_token/sendMessage";
        $telegram_data = array(
            'chat_id' => $chat_id,
            'text' => $telegram_message,
        );

        $response = wp_remote_post($telegram_url, array(
            'body' => $telegram_data,
            'timeout' => 10,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('Failed to send to Telegram.');
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        if (!isset($response_data['ok']) || $response_data['ok'] !== true) {
            wp_send_json_error('Telegram API error.');
        }
    }

    // Return success even if Telegram is disabled, as the submission is saved in the database
    wp_send_json_success('Your request has been sent successfully!');
}

// Add admin menu for the plugin
add_action('admin_menu', 'tcf_admin_menu');
function tcf_admin_menu() {
    add_menu_page(
        'Telegram Contact Form',
        'Telegram Form',
        'manage_options',
        'telegram-contact-form',
        'tcf_requests_page',
        'dashicons-email-alt',
        80
    );
    add_submenu_page(
        'telegram-contact-form',
        'Requests',
        'Requests',
        'manage_options',
        'telegram-contact-form',
        'tcf_requests_page'
    );
    add_submenu_page(
        'telegram-contact-form',
        'Settings',
        'Settings',
        'manage_options',
        'tcf-settings',
        'tcf_settings_page'
    );
    add_submenu_page(
        'telegram-contact-form',
        'Analytics',
        'Analytics',
        'manage_options',
        'tcf-analytics',
        'tcf_analytics_page'
    );
}

// Admin page: Requests
function tcf_requests_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'telegram_form_requests';

    // Handle deletion of a request
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['request_id'])) {
        $request_id = intval($_GET['request_id']);
        $wpdb->delete($table_name, array('id' => $request_id));
        echo '<div class="updated"><p>Request deleted successfully.</p></div>';
    }

    $requests = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submitted_at DESC");
    ?>
    <div class="wrap">
        <h1>Telegram Form Requests</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Telegram</th>
                    <th>Message</th>
                    <th>IP Address</th>
                    <th>Submitted At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request) : ?>
                    <tr>
                        <td><?php echo esc_html($request->id); ?></td>
                        <td><?php echo esc_html($request->name); ?></td>
                        <td><?php echo esc_html($request->email); ?></td>
                        <td><?php echo esc_html($request->phone); ?></td>
                        <td><?php echo esc_html($request->telegram_username); ?></td>
                        <td><?php echo esc_html($request->message); ?></td>
                        <td><?php echo esc_html($request->ip_address); ?></td>
                        <td><?php echo esc_html($request->submitted_at); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=telegram-contact-form&action=delete&request_id=' . $request->id); ?>" onclick="return confirm('Are you sure you want to delete this request?');" class="button button-small">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Admin page: Settings
function tcf_settings_page() {
    // Handle settings save
    if (isset($_POST['tcf_save_settings']) && check_admin_referer('tcf_save_settings_nonce')) {
        update_option('tcf_bot_token', sanitize_text_field($_POST['tcf_bot_token']));
        update_option('tcf_chat_id', sanitize_text_field($_POST['tcf_chat_id']));
        update_option('tcf_show_name', isset($_POST['tcf_show_name']) ? 1 : 0);
        update_option('tcf_show_email', isset($_POST['tcf_show_email']) ? 1 : 0);
        update_option('tcf_show_phone', isset($_POST['tcf_show_phone']) ? 1 : 0);
        update_option('tcf_show_telegram', isset($_POST['tcf_show_telegram']) ? 1 : 0);
        update_option('tcf_show_message', isset($_POST['tcf_show_message']) ? 1 : 0);
        update_option('tcf_require_name', isset($_POST['tcf_require_name']) ? 1 : 0);
        update_option('tcf_require_email', isset($_POST['tcf_require_email']) ? 1 : 0);
        update_option('tcf_require_phone', isset($_POST['tcf_require_phone']) ? 1 : 0);
        update_option('tcf_require_telegram', isset($_POST['tcf_require_telegram']) ? 1 : 0);
        update_option('tcf_require_message', isset($_POST['tcf_require_message']) ? 1 : 0);
        update_option('tcf_enqueue_basic_styles', isset($_POST['tcf_enqueue_basic_styles']) ? 1 : 0);
        update_option('tcf_custom_css', sanitize_textarea_field($_POST['tcf_custom_css']));
        update_option('tcf_cleantalk_api_key', sanitize_text_field($_POST['tcf_cleantalk_api_key']));
        update_option('tcf_enable_telegram', isset($_POST['tcf_enable_telegram']) ? 1 : 0);
        update_option('tcf_enable_cleantalk', isset($_POST['tcf_enable_cleantalk']) ? 1 : 0);
        update_option('tcf_enable_captcha', isset($_POST['tcf_enable_captcha']) ? 1 : 0);
        echo '<div class="updated"><p>Settings saved successfully.</p></div>';
    }

    // Handle test Telegram notification
    if (isset($_POST['tcf_test_telegram']) && check_admin_referer('tcf_save_settings_nonce')) {
        $bot_token = get_option('tcf_bot_token', '');
        $chat_id = get_option('tcf_chat_id', '');
        $enable_telegram = get_option('tcf_enable_telegram', 1);

        if (!$enable_telegram) {
            echo '<div class="notice notice-error"><p>Telegram notifications are disabled. Please enable them to test.</p></div>';
        } elseif (empty($bot_token) || empty($chat_id)) {
            echo '<div class="notice notice-error"><p>Please configure Telegram Bot Token and Chat ID before testing.</p></div>';
        } else {
            $test_message = "Test Notification from Telegram Contact Form\n\nThis is a test message to verify your Telegram settings.";
            $telegram_url = "https://api.telegram.org/bot$bot_token/sendMessage";
            $telegram_data = array(
                'chat_id' => $chat_id,
                'text' => $test_message,
            );

            $response = wp_remote_post($telegram_url, array(
                'body' => $telegram_data,
                'timeout' => 10,
            ));

            if (is_wp_error($response)) {
                echo '<div class="notice notice-error"><p>Failed to send test message to Telegram: ' . $response->get_error_message() . '</p></div>';
            } else {
                $response_body = wp_remote_retrieve_body($response);
                $response_data = json_decode($response_body, true);
                if (isset($response_data['ok']) && $response_data['ok'] === true) {
                    echo '<div class="notice notice-success"><p>Test message sent successfully to Telegram!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Telegram API error: ' . (isset($response_data['description']) ? $response_data['description'] : 'Unknown error') . '</p></div>';
                }
            }
        }
    }

    // Get settings values
    $bot_token = get_option('tcf_bot_token', '');
    $chat_id = get_option('tcf_chat_id', '');
    $show_name = get_option('tcf_show_name', 1);
    $show_email = get_option('tcf_show_email', 1);
    $show_phone = get_option('tcf_show_phone', 1);
    $show_telegram = get_option('tcf_show_telegram', 1);
    $show_message = get_option('tcf_show_message', 1);
    $require_name = get_option('tcf_require_name', 1);
    $require_email = get_option('tcf_require_email', 1);
    $require_phone = get_option('tcf_require_phone', 1);
    $require_telegram = get_option('tcf_require_telegram', 1);
    $require_message = get_option('tcf_require_message', 1);
    $enqueue_basic_styles = get_option('tcf_enqueue_basic_styles', 1);
    $custom_css = get_option('tcf_custom_css', '');
    $cleantalk_api_key = get_option('tcf_cleantalk_api_key', '');
    $enable_telegram = get_option('tcf_enable_telegram', 1);
    $enable_cleantalk = get_option('tcf_enable_cleantalk', 1);
    $enable_captcha = get_option('tcf_enable_captcha', 1);
    ?>
    <div class="wrap">
        <h1>Telegram Contact Form Settings</h1>
        <form method="post">
            <?php wp_nonce_field('tcf_save_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="tcf_bot_token">Telegram Bot Token</label></th>
                    <td><input type="text" name="tcf_bot_token" id="tcf_bot_token" value="<?php echo esc_attr($bot_token); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tcf_chat_id">Telegram Chat ID</label></th>
                    <td><input type="text" name="tcf_chat_id" id="tcf_chat_id" value="<?php echo esc_attr($chat_id); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Telegram Notifications</th>
                    <td>
                        <label><input type="checkbox" name="tcf_enable_telegram" <?php checked($enable_telegram, 1); ?>> Enable Telegram Notifications</label>
                        <p class="description">Check this box to enable sending form submissions to Telegram.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Telegram Setup Instructions</th>
                    <td>
                        <details>
                            <summary style="cursor: pointer; font-weight: bold;">Show Telegram Setup Instructions</summary>
                            <p><strong>How to Create a Telegram Bot and Get Your Bot Token and Chat ID:</strong></p>
                            <ol>
                                <li><strong>Create a Telegram Bot:</strong>
                                    <ul>
                                        <li>Open Telegram and search for <a href="https://t.me/BotFather" target="_blank">@BotFather</a>.</li>
                                        <li>Start a chat with BotFather and send the command <code>/start</code>.</li>
                                        <li>Send the command <code>/newbot</code> to create a new bot.</li>
                                        <li>Follow the instructions: choose a name for your bot (e.g., "MyContactBot") and a username ending in "Bot" (e.g., "MyContactFormBot").</li>
                                        <li>BotFather will provide you with a <strong>Bot Token</strong> (e.g., <code>123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11</code>). Copy this token and paste it into the "Telegram Bot Token" field above.</li>
                                    </ul>
                                </li>
                                <li><strong>Get Your Chat ID:</strong>
                                    <ul>
                                        <li>Open a chat with your newly created bot (search for its username in Telegram and press "Start").</li>
                                        <li>Send a message to the bot (e.g., "Hello").</li>
                                        <li>Open a new tab in your browser and go to: <code>https://api.telegram.org/botYOUR_BOT_TOKEN/getUpdates</code> (replace <code>YOUR_BOT_TOKEN</code> with your Bot Token).</li>
                                        <li>You will see a JSON response. Look for <code>"chat":{"id":YOUR_CHAT_ID}</code>. The <code>YOUR_CHAT_ID</code> (e.g., <code>123456789</code>) is your Chat ID. Copy it and paste it into the "Telegram Chat ID" field above.</li>
                                    </ul>
                                </li>
                                <li><strong>Test Your Setup:</strong>
                                    <ul>
                                        <li>After entering your Bot Token and Chat ID, click the "Test Telegram Notification" button below to send a test message.</li>
                                        <li>If you receive the test message in your Telegram chat, your setup is complete!</li>
                                    </ul>
                                </li>
                            </ol>
                            <p><strong>Note:</strong> Ensure your bot is not blocked, and you have started a chat with it before testing.</p>
                        </details>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Form Fields</th>
                    <td>
                        <label><input type="checkbox" name="tcf_show_name" <?php checked($show_name, 1); ?>> Show Name Field</label>
                        <label style="margin-left: 20px;"><input type="checkbox" name="tcf_require_name" <?php checked($require_name, 1); ?>> Required</label><br>
                        <label><input type="checkbox" name="tcf_show_email" <?php checked($show_email, 1); ?>> Show Email Field</label>
                        <label style="margin-left: 20px;"><input type="checkbox" name="tcf_require_email" <?php checked($require_email, 1); ?>> Required</label><br>
                        <label><input type="checkbox" name="tcf_show_phone" <?php checked($show_phone, 1); ?>> Show Phone Field</label>
                        <label style="margin-left: 20px;"><input type="checkbox" name="tcf_require_phone" <?php checked($require_phone, 1); ?>> Required</label><br>
                        <label><input type="checkbox" name="tcf_show_telegram" <?php checked($show_telegram, 1); ?>> Show Telegram Username Field</label>
                        <label style="margin-left: 20px;"><input type="checkbox" name="tcf_require_telegram" <?php checked($require_telegram, 1); ?>> Required</label><br>
                        <label><input type="checkbox" name="tcf_show_message" <?php checked($show_message, 1); ?>> Show Message Field</label>
                        <label style="margin-left: 20px;"><input type="checkbox" name="tcf_require_message" <?php checked($require_message, 1); ?>> Required</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Styling Options</th>
                    <td>
                        <label><input type="checkbox" name="tcf_enqueue_basic_styles" <?php checked($enqueue_basic_styles, 1); ?>> Enqueue Basic Styles</label><br>
                        <label for="tcf_custom_css">Custom CSS:</label><br>
                        <textarea name="tcf_custom_css" id="tcf_custom_css" rows="5" class="large-text"><?php echo esc_textarea($custom_css); ?></textarea>
                        <p class="description">Add your custom CSS to style the form. This will be applied after the basic styles (if enabled).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Spam Protection</th>
                    <td>
                        <label><input type="checkbox" name="tcf_enable_cleantalk" <?php checked($enable_cleantalk, 1); ?>> Enable CleanTalk Spam Protection</label><br>
                        <label for="tcf_cleantalk_api_key">CleanTalk API Key:</label><br>
                        <input type="text" name="tcf_cleantalk_api_key" id="tcf_cleantalk_api_key" value="<?php echo esc_attr($cleantalk_api_key); ?>" class="regular-text">
                        <p class="description">Enter your CleanTalk API key to enable spam protection. <a href="https://cleantalk.org/register" target="_blank">Get your API key here</a>.</p>
                        <br>
                        <label><input type="checkbox" name="tcf_enable_captcha" <?php checked($enable_captcha, 1); ?>> Enable Built-in Captcha</label><br>
                        <p class="description">Enable a simple math captcha to protect the form from bots.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="tcf_save_settings" class="button button-primary" value="Save Settings">
            </p>
            <p class="submit">
                <input type="submit" name="tcf_test_telegram" class="button button-secondary" value="Test Telegram Notification">
            </p>
        </form>
    </div>
    <?php
}

// Admin page: Analytics
function tcf_analytics_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'telegram_form_requests';

    // Get total number of submissions
    $total_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    // Get submissions in the last 7 days
    $last_7_days = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

    // Calculate average submissions per day
    $first_submission = $wpdb->get_var("SELECT MIN(submitted_at) FROM $table_name");
    if ($first_submission) {
        $days_since_first = (strtotime(current_time('mysql')) - strtotime($first_submission)) / (60 * 60 * 24);
        $average_per_day = $days_since_first > 0 ? round($total_submissions / $days_since_first, 2) : 0;
    } else {
        $average_per_day = 0;
    }

    // Get submissions per day for the last 30 days
    $submissions_per_day = $wpdb->get_results("
        SELECT DATE(submitted_at) as submission_date, COUNT(*) as count 
        FROM $table_name 
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
        GROUP BY DATE(submitted_at) 
        ORDER BY submission_date DESC
    ");
    ?>
    <div class="wrap">
        <h1>Telegram Form Analytics</h1>
        <div class="stats-overview" style="margin-bottom: 20px;">
            <h2>Overview</h2>
            <p><strong>Total Submissions:</strong> <?php echo esc_html($total_submissions); ?></p>
            <p><strong>Submissions in Last 7 Days:</strong> <?php echo esc_html($last_7_days); ?></p>
        </div>
        <h2>Submissions Per Day (Last 30 Days)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Number of Submissions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions_per_day as $day) : ?>
                    <tr>
                        <td><?php echo esc_html($day->submission_date); ?></td>
                        <td><?php echo esc_html($day->count); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($submissions_per_day)) : ?>
                    <tr>
                        <td colspan="2">No submissions in the last 30 days.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}