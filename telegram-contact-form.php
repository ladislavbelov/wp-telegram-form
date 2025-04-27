<?php
/*
Plugin Name: Telegram Contact Form
Description: A custom contact form that sends submissions to Telegram and stores them in the WordPress database.
Version: 1.3
Author: Vlad Belov
*/

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

// Создание таблицы при активации плагина
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

// Удаление таблицы при деактивации плагина (для тестирования)
register_deactivation_hook(__FILE__, 'tcf_drop_table');
function tcf_drop_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'telegram_form_requests';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

// Добавление стилей и скриптов
add_action('wp_enqueue_scripts', 'tcf_enqueue_scripts');
function tcf_enqueue_scripts() {
    // Загружаем базовые стили, если включены
    if (get_option('tcf_enqueue_basic_styles', 1)) {
        wp_enqueue_style('tcf-styles', plugin_dir_url(__FILE__) . 'assets/css/tcf-styles.css');
    }

    // Добавляем кастомный CSS
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

// Шорткод для формы
add_shortcode('telegram_contact_form', 'tcf_render_form');
function tcf_render_form() {
    // Получаем настройки полей
    $show_name = get_option('tcf_show_name', 1);
    $show_email = get_option('tcf_show_email', 1);
    $show_phone = get_option('tcf_show_phone', 1);
    $show_telegram = get_option('tcf_show_telegram', 1);
    $show_message = get_option('tcf_show_message', 1);

    ob_start();
    ?>
    <form id="telegram-contact-form" class="tcf-form">
        <?php if ($show_name): ?>
        <div class="form-group">
            <label for="tcf-name">Name:</label>
            <input type="text" id="tcf-name" name="name" <?php echo $show_name ? 'required' : ''; ?>>
        </div>
        <?php endif; ?>
        <?php if ($show_email): ?>
        <div class="form-group">
            <label for="tcf-email">Email:</label>
            <input type="email" id="tcf-email" name="email" <?php echo $show_email ? 'required' : ''; ?>>
        </div>
        <?php endif; ?>
        <?php if ($show_phone): ?>
        <div class="form-group">
            <label for="tcf-phone">Phone:</label>
            <input type="tel" id="tcf-phone" name="phone" <?php echo $show_phone ? 'required' : ''; ?>>
        </div>
        <?php endif; ?>
        <?php if ($show_telegram): ?>
        <div class="form-group">
            <label for="tcf-telegram">Telegram Username:</label>
            <input type="text" id="tcf-telegram" name="telegram_username" placeholder="@username" <?php echo $show_telegram ? 'required' : ''; ?>>
        </div>
        <?php endif; ?>
        <?php if ($show_message): ?>
        <div class="form-group">
            <label for="tcf-message">Message:</label>
            <textarea id="tcf-message" name="message" <?php echo $show_message ? 'required' : ''; ?>></textarea>
        </div>
        <?php endif; ?>
        <button type="submit" class="tcf-submit-btn"><?php echo esc_html(get_option('tcf_submit_button_text', 'Send Request')); ?></button>
        <div class="form-message"></div>
    </form>
    <?php
    return ob_get_clean();
}

// Обработка AJAX-запроса
add_action('wp_ajax_tcf_submit_form', 'tcf_submit_form');
add_action('wp_ajax_nopriv_tcf_submit_form', 'tcf_submit_form');
function tcf_submit_form() {
    check_ajax_referer('tcf_nonce', 'nonce');

    // Получаем настройки полей
    $show_name = get_option('tcf_show_name', 1);
    $show_email = get_option('tcf_show_email', 1);
    $show_phone = get_option('tcf_show_phone', 1);
    $show_telegram = get_option('tcf_show_telegram', 1);
    $show_message = get_option('tcf_show_message', 1);

    // Собираем данные формы, если поле включено, иначе пустая строка
    $name = $show_name && isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $email = $show_email && isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $phone = $show_phone && isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $telegram_username = $show_telegram && isset($_POST['telegram_username']) ? sanitize_text_field($_POST['telegram_username']) : '';
    $message = $show_message && isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $submitted_at = current_time('mysql');

    // Сохранение в базе данных
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

    // Формируем сообщение для Telegram и Email
    $notification_message = "New Contact Form Submission\n\n";
    if ($show_name) $notification_message .= "Name: $name\n";
    if ($show_email) $notification_message .= "Email: $email\n";
    if ($show_phone) $notification_message .= "Phone: $phone\n";
    if ($show_telegram) $notification_message .= "Telegram: $telegram_username\n";
    if ($show_message) $notification_message .= "Message: $message\n";
    $notification_message .= "IP: $ip_address\n";
    $notification_message .= "Submitted: $submitted_at\n";

    // Отправка в Telegram
    $bot_token = get_option('tcf_bot_token', '');
    $chat_id = get_option('tcf_chat_id', '');

    if (empty($bot_token) || empty($chat_id)) {
        wp_send_json_error('Telegram settings are not configured.');
    }

    $telegram_url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $telegram_data = array(
        'chat_id' => $chat_id,
        'text' => $notification_message,
    );

    $response = wp_remote_post($telegram_url, array(
        'body' => $telegram_data,
        'timeout' => 10,
    ));

    // Отправка уведомления на email
    $admin_email = get_option('tcf_admin_email', '');
    if (!empty($admin_email)) {
        $subject = 'New Contact Form Submission';
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($admin_email, $subject, $notification_message, $headers);
    }

    if (is_wp_error($response)) {
        wp_send_json_error('Failed to send to Telegram.');
    }

    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);
    if (isset($response_data['ok']) && $response_data['ok'] === true) {
        wp_send_json_success('Your request has been sent successfully!');
    } else {
        wp_send_json_error('Telegram API error.');
    }
}

// Добавление меню в админке
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
}

// Страница "Requests"
function tcf_requests_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'telegram_form_requests';

    // Обработка удаления
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

// Страница "Settings"
function tcf_settings_page() {
    if (isset($_POST['tcf_save_settings']) && check_admin_referer('tcf_save_settings_nonce')) {
        update_option('tcf_bot_token', sanitize_text_field($_POST['tcf_bot_token']));
        update_option('tcf_chat_id', sanitize_text_field($_POST['tcf_chat_id']));
        update_option('tcf_show_name', isset($_POST['tcf_show_name']) ? 1 : 0);
        update_option('tcf_show_email', isset($_POST['tcf_show_email']) ? 1 : 0);
        update_option('tcf_show_phone', isset($_POST['tcf_show_phone']) ? 1 : 0);
        update_option('tcf_show_telegram', isset($_POST['tcf_show_telegram']) ? 1 : 0);
        update_option('tcf_show_message', isset($_POST['tcf_show_message']) ? 1 : 0);
        update_option('tcf_enqueue_basic_styles', isset($_POST['tcf_enqueue_basic_styles']) ? 1 : 0);
        update_option('tcf_custom_css', sanitize_textarea_field($_POST['tcf_custom_css']));
        update_option('tcf_submit_button_text', sanitize_text_field($_POST['tcf_submit_button_text']));
        update_option('tcf_admin_email', sanitize_email($_POST['tcf_admin_email']));
        echo '<div class="updated"><p>Settings saved successfully.</p></div>';
    }

    $bot_token = get_option('tcf_bot_token', '');
    $chat_id = get_option('tcf_chat_id', '');
    $show_name = get_option('tcf_show_name', 1);
    $show_email = get_option('tcf_show_email', 1);
    $show_phone = get_option('tcf_show_phone', 1);
    $show_telegram = get_option('tcf_show_telegram', 1);
    $show_message = get_option('tcf_show_message', 1);
    $enqueue_basic_styles = get_option('tcf_enqueue_basic_styles', 1);
    $custom_css = get_option('tcf_custom_css', '');
    $admin_email = get_option('tcf_admin_email', '');
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
                    <th scope="row">Form Fields</th>
                    <td>
                        <label><input type="checkbox" name="tcf_show_name" <?php checked($show_name, 1); ?>> Show Name Field</label><br>
                        <label><input type="checkbox" name="tcf_show_email" <?php checked($show_email, 1); ?>> Show Email Field</label><br>
                        <label><input type="checkbox" name="tcf_show_phone" <?php checked($show_phone, 1); ?>> Show Phone Field</label><br>
                        <label><input type="checkbox" name="tcf_show_telegram" <?php checked($show_telegram, 1); ?>> Show Telegram Username Field</label><br>
                        <label><input type="checkbox" name="tcf_show_message" <?php checked($show_message, 1); ?>> Show Message Field</label>
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
    <th scope="row"><label for="tcf_submit_button_text">Submit Button Text</label></th>
    <td><input type="text" name="tcf_submit_button_text" id="tcf_submit_button_text" value="<?php echo esc_attr(get_option('tcf_submit_button_text', 'Send Request')); ?>" class="regular-text"></td>
</tr>
                <tr>
                    <th scope="row"><label for="tcf_admin_email">Admin Email for Notifications</label></th>
                    <td>
                        <input type="email" name="tcf_admin_email" id="tcf_admin_email" value="<?php echo esc_attr($admin_email); ?>" class="regular-text">
                        <p class="description">Enter the email address where you want to receive notifications about new form submissions.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="tcf_save_settings" class="button button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    <?php
}