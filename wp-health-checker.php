<?php
/**
 * Plugin Name: WP Health Checker
 * Description: Un plugin para monitorear el estado y rendimiento de tu sitio WordPress.
 * Version: 1.0
 * Author: Esteban Selvaggi
 */

// Evitar el acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

class WP_Health_Checker {
    
    private $plugin_name = 'WP Health Checker';
    private $version = '1.0';

    public function __construct() {
        // Inicializar el plugin
        add_action('plugins_loaded', array($this, 'init'));
    }

    public function init() {
        // Registrar menús de administración
        add_action('admin_menu', array($this, 'register_admin_menu'));

        // Registrar estilos y scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Verificar si es domingo y si se debe enviar el informe
        add_action('wp_loaded', array($this, 'check_and_send_weekly_report'));

        // Registrar hook para capturar errores 404
        add_action('template_redirect', array($this, 'capture_404_errors'));
    }

    public function register_admin_menu() {
        add_menu_page(
            'WP Health Checker',
            'Health Checker',
            'manage_options',
            'wp-health-checker',
            array($this, 'admin_page'),
            'dashicons-chart-area',
            100
        );
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_style('wp-health-checker-style', plugins_url('css/style.css', __FILE__));
        wp_enqueue_script('wp-health-checker-script', plugins_url('js/script.js', __FILE__), array('jquery'), '1.0', true);
    }

    public function check_and_send_weekly_report() {
        if ($this->is_sunday() && $this->should_send_report()) {
            $report = $this->generate_health_report();
            $this->send_email_report($report);
            update_option('wp_health_checker_last_report_sent', current_time('timestamp'));
        }
    }

    private function is_sunday() {
        return (date('w') == 0);
    }

    private function should_send_report() {
        $last_sent = get_option('wp_health_checker_last_report_sent', 0);
        $week_start = strtotime('last sunday', current_time('timestamp'));
        return ($last_sent < $week_start);
    }

    public function capture_404_errors() {
        if (is_404()) {
            $current_url = home_url($_SERVER['REQUEST_URI']);
            $this->log_404_error($current_url);
        }
    }

    private function log_404_error($url) {
        $log = get_option('wp_health_checker_404_log', array());
        $log[] = array(
            'url' => $url,
            'time' => current_time('mysql')
        );
        update_option('wp_health_checker_404_log', array_slice($log, -100)); // Mantener solo los últimos 100 registros
    }

    public function admin_page() {
        ?>
        <div class="wrap wp-health-checker-wrap">
            <div class="wp-health-checker-header">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            </div>
            <div id="wp-health-checker-dashboard">
                <!-- El contenido del dashboard se cargará aquí mediante JavaScript -->
            </div>
            <div class="wp-health-checker-settings">
                <h3>Configuración de Correos Electrónicos de Administrador</h3>
                <ul id="wp-health-checker-admin-emails">
                    <!-- La lista de correos electrónicos se cargará aquí mediante JavaScript -->
                </ul>
                <input type="email" id="wp-health-checker-new-email" placeholder="Nuevo correo electrónico">
                <button id="wp-health-checker-add-email" class="button button-primary">Agregar Correo</button>
                <button id="wp-health-checker-test-email" class="button button-secondary">Enviar Correo de Prueba</button>
            </div>
        </div>
        <?php
    }
    private function generate_health_report() {
        $report = '';
        
        // Rendimiento del sitio web
        $report .= $this->check_site_performance();

        // Estado del sistema
        $report .= $this->check_system_status();

        // Seguridad básica
        $report .= $this->check_basic_security();

        // Monitoreo de comentarios
        $report .= $this->check_comments();

        // SEO básico
        $report .= $this->check_basic_seo();

        // E-commerce (si WooCommerce está instalado)
        if (class_exists('WooCommerce')) {
            $report .= $this->check_woocommerce();
        }

        // Accesibilidad y usabilidad básica
        $report .= $this->check_accessibility();

        return $report;
    }

    private function check_site_performance() {
        $report = "== Rendimiento del Sitio Web ==\n";
        
        // Tiempo de carga estimado
        $start = microtime(true);
        wp_remote_get(home_url());
        $load_time = microtime(true) - $start;
        $report .= "Tiempo de carga estimado: " . round($load_time, 2) . " segundos\n";

        // Tamaño total estimado de la página principal
        $response = wp_remote_get(home_url());
        $page_size = strlen(wp_remote_retrieve_body($response));
        $report .= "Tamaño estimado de la página principal: " . round($page_size / 1024, 2) . " KB\n";

        return $report;
    }

    private function check_system_status() {
        $report = "\n== Estado del Sistema ==\n";
        
        // Versión de WordPress
        global $wp_version;
        $report .= "Versión de WordPress: $wp_version\n";

        // Versión de PHP
        $report .= "Versión de PHP: " . phpversion() . "\n";

        // Espacio en disco
        $disk_space = $this->get_disk_space();
        $report .= "Espacio en disco: $disk_space\n";

        // Plugins activos e inactivos
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins');
        $report .= "Plugins activos: " . count($active_plugins) . "\n";
        $report .= "Plugins inactivos: " . (count($all_plugins) - count($active_plugins)) . "\n";

        // Tema activo
        $active_theme = wp_get_theme();
        $report .= "Tema activo: " . $active_theme->get('Name') . " (versión " . $active_theme->get('Version') . ")\n";

        // Actualizaciones pendientes
        $updates = $this->get_pending_updates();
        $report .= "Actualizaciones pendientes: " . $updates . "\n";

        return $report;
    }

    private function get_disk_space() {
        $disk_space = "No disponible";
        if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
            $free_space = disk_free_space(ABSPATH);
            $total_space = disk_total_space(ABSPATH);
            if ($free_space !== false && $total_space !== false) {
                $used_space = $total_space - $free_space;
                $disk_space = "Usado: " . size_format($used_space) . " / Total: " . size_format($total_space);
            }
        }
        return $disk_space;
    }

    private function get_pending_updates() {
        $updates = 0;
        if (current_user_can('update_plugins')) {
            $update_plugins = get_site_transient('update_plugins');
            if (!empty($update_plugins->response)) {
                $updates += count($update_plugins->response);
            }
        }
        if (current_user_can('update_themes')) {
            $update_themes = get_site_transient('update_themes');
            if (!empty($update_themes->response)) {
                $updates += count($update_themes->response);
            }
        }
        if (current_user_can('update_core')) {
            $update_wordpress = get_core_updates();
            if (is_array($update_wordpress) && !empty($update_wordpress[0]->response) && $update_wordpress[0]->response == 'upgrade') {
                $updates++;
            }
        }
        return $updates;
    }
    private function check_basic_security() {
        $report = "\n== Seguridad Básica ==\n";
        
        // Intentos fallidos de inicio de sesión
        $failed_logins = get_option('wp_health_checker_failed_logins', 0);
        $report .= "Intentos fallidos de inicio de sesión (últimas 24 horas): $failed_logins\n";

        // Verificación de archivos críticos
        $critical_files = array(
            ABSPATH . 'wp-config.php',
            ABSPATH . 'wp-login.php',
            ABSPATH . 'wp-admin/index.php'
        );
        foreach ($critical_files as $file) {
            $hash = md5_file($file);
            $stored_hash = get_option('wp_health_checker_file_hash_' . basename($file));
            if ($stored_hash && $hash !== $stored_hash) {
                $report .= "¡Advertencia! El archivo " . basename($file) . " ha sido modificado.\n";
            } else {
                update_option('wp_health_checker_file_hash_' . basename($file), $hash);
            }
        }

        return $report;
    }

    private function check_comments() {
        $report = "\n== Monitoreo de Comentarios ==\n";
        
        $pending_comments = wp_count_comments()->moderated;
        $spam_comments = wp_count_comments()->spam;

        $report .= "Comentarios pendientes: $pending_comments\n";
        $report .= "Comentarios marcados como spam: $spam_comments\n";

        return $report;
    }

    private function check_basic_seo() {
        $report = "\n== SEO Básico ==\n";
        
        // Verificación de meta tags en la página principal
        $response = wp_remote_get(home_url());
        $body = wp_remote_retrieve_body($response);

        preg_match('/<title>(.*?)<\/title>/i', $body, $title_match);
        preg_match('/<meta name="description" content="(.*?)"/i', $body, $description_match);

        $report .= "Título de la página principal: " . (isset($title_match[1]) ? $title_match[1] : "No encontrado") . "\n";
        $report .= "Descripción de la página principal: " . (isset($description_match[1]) ? $description_match[1] : "No encontrada") . "\n";

        // Detección de errores 404 en enlaces internos
        $internal_404s = get_option('wp_health_checker_404_log', array());
        $report .= "Errores 404 internos recientes: " . count($internal_404s) . "\n";

        return $report;
    }

    private function check_woocommerce() {
        $report = "\n== E-commerce (WooCommerce) ==\n";
        
        // Conteo de pedidos recientes
        $args = array(
            'status' => 'processing',
            'limit' => -1,
            'return' => 'ids',
            'date_created' => '>' . date('Y-m-d', strtotime('-7 days'))
        );
        $recent_orders = wc_get_orders($args);
        $report .= "Pedidos recientes (últimos 7 días): " . count($recent_orders) . "\n";

        // Productos con stock bajo
        $low_stock_products = wc_get_low_stock_amount();
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_stock',
                    'value' => $low_stock_products,
                    'compare' => '<=',
                    'type' => 'NUMERIC'
                ),
                array(
                    'key' => '_manage_stock',
                    'value' => 'yes'
                )
            )
        );
        $low_stock_query = new WP_Query($args);
        $report .= "Productos con stock bajo: " . $low_stock_query->post_count . "\n";

        return $report;
    }

    private function check_accessibility() {
        $report = "\n== Accesibilidad y Usabilidad Básica ==\n";
        
        // Verificación simple de enlaces rotos en la página principal
        $response = wp_remote_get(home_url());
        $body = wp_remote_retrieve_body($response);
        preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"]*)"/', $body, $matches);
        
        $broken_links = 0;
        foreach ($matches[1] as $link) {
            if (strpos($link, home_url()) === 0) {
                $link_response = wp_remote_head($link);
                if (is_wp_error($link_response) || wp_remote_retrieve_response_code($link_response) == 404) {
                    $broken_links++;
                }
            }
        }

        $report .= "Enlaces rotos encontrados en la página principal: $broken_links\n";

        return $report;
    }

    private function send_email_report($report) {
        $to = $this->get_admin_emails();
        $subject = "Informe semanal de salud de " . get_bloginfo('name');
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        wp_mail($to, $subject, $report, $headers);
    }

    private function get_admin_emails() {
        $admin_emails = get_option('wp_health_checker_admin_emails', array());
        if (empty($admin_emails)) {
            $admin_emails[] = get_option('admin_email');
        }
        return $admin_emails;
    }
    public function ajax_get_health_report() {
        // Generar el informe de salud
        $report = $this->generate_health_report();

        // Convertir el informe en un formato adecuado para JSON
        $data = array(
            'load_time' => $this->get_load_time(),
            'page_size' => $this->get_page_size(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'disk_space' => $this->get_disk_space(),
            'active_plugins' => count(get_option('active_plugins')),
            'inactive_plugins' => count(get_plugins()) - count(get_option('active_plugins')),
            'active_theme' => wp_get_theme()->get('Name'),
            'pending_updates' => $this->get_pending_updates(),
            'failed_logins' => get_option('wp_health_checker_failed_logins', 0),
            'modified_files' => $this->check_critical_files(),
            'pending_comments' => wp_count_comments()->moderated,
            'spam_comments' => wp_count_comments()->spam,
            'home_title' => $this->get_home_title(),
            'home_description' => $this->get_home_description(),
            'internal_404s' => count(get_option('wp_health_checker_404_log', array())),
            'woocommerce' => class_exists('WooCommerce'),
            'recent_orders' => $this->get_recent_orders(),
            'low_stock_products' => $this->get_low_stock_products(),
            'broken_links' => $this->check_broken_links()
        );

        wp_send_json_success($data);
    }

    private function get_load_time() {
        $start = microtime(true);
        wp_remote_get(home_url());
        return round(microtime(true) - $start, 2);
    }

    private function get_page_size() {
        $response = wp_remote_get(home_url());
        return round(strlen(wp_remote_retrieve_body($response)) / 1024, 2);
    }

    private function check_critical_files() {
        $modified_files = 0;
        $critical_files = array(
            ABSPATH . 'wp-config.php',
            ABSPATH . 'wp-login.php',
            ABSPATH . 'wp-admin/index.php'
        );
        foreach ($critical_files as $file) {
            $hash = md5_file($file);
            $stored_hash = get_option('wp_health_checker_file_hash_' . basename($file));
            if ($stored_hash && $hash !== $stored_hash) {
                $modified_files++;
            } else {
                update_option('wp_health_checker_file_hash_' . basename($file), $hash);
            }
        }
        return $modified_files;
    }

    private function get_home_title() {
        $response = wp_remote_get(home_url());
        $body = wp_remote_retrieve_body($response);
        preg_match('/<title>(.*?)<\/title>/i', $body, $title_match);
        return isset($title_match[1]) ? $title_match[1] : "No encontrado";
    }

    private function get_home_description() {
        $response = wp_remote_get(home_url());
        $body = wp_remote_retrieve_body($response);
        preg_match('/<meta name="description" content="(.*?)"/i', $body, $description_match);
        return isset($description_match[1]) ? $description_match[1] : "No encontrada";
    }

    private function get_recent_orders() {
        if (!class_exists('WooCommerce')) {
            return 0;
        }
        $args = array(
            'status' => 'processing',
            'limit' => -1,
            'return' => 'ids',
            'date_created' => '>' . date('Y-m-d', strtotime('-7 days'))
        );
        return count(wc_get_orders($args));
    }

    private function get_low_stock_products() {
        if (!class_exists('WooCommerce')) {
            return 0;
        }
        $low_stock_amount = wc_get_low_stock_amount();
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_stock',
                    'value' => $low_stock_amount,
                    'compare' => '<=',
                    'type' => 'NUMERIC'
                ),
                array(
                    'key' => '_manage_stock',
                    'value' => 'yes'
                )
            )
        );
        $query = new WP_Query($args);
        return $query->post_count;
    }

    private function check_broken_links() {
        $broken_links = 0;
        $response = wp_remote_get(home_url());
        $body = wp_remote_retrieve_body($response);
        preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"]*)"/', $body, $matches);
        
        foreach ($matches[1] as $link) {
            if (strpos($link, home_url()) === 0) {
                $link_response = wp_remote_head($link);
                if (is_wp_error($link_response) || wp_remote_retrieve_response_code($link_response) == 404) {
                    $broken_links++;
                }
            }
        }
        return $broken_links;
    }

    public function ajax_add_admin_email() {
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            wp_send_json_error('Correo electrónico inválido');
        }

        $admin_emails = get_option('wp_health_checker_admin_emails', array());
        if (!in_array($email, $admin_emails)) {
            $admin_emails[] = $email;
            update_option('wp_health_checker_admin_emails', $admin_emails);
            wp_send_json_success();
        } else {
            wp_send_json_error('El correo electrónico ya existe');
        }
    }

    public function ajax_remove_admin_email() {
        $email = sanitize_email($_POST['email']);
        $admin_emails = get_option('wp_health_checker_admin_emails', array());
        $index = array_search($email, $admin_emails);
        if ($index !== false) {
            unset($admin_emails[$index]);
            update_option('wp_health_checker_admin_emails', array_values($admin_emails));
            wp_send_json_success();
        } else {
            wp_send_json_error('Correo electrónico no encontrado');
        }
    }

    public function ajax_get_admin_emails() {
        $admin_emails = get_option('wp_health_checker_admin_emails', array());
        wp_send_json_success($admin_emails);
    }

    public function send_test_email() {
        $to = $this->get_admin_emails();
        $subject = "Correo de prueba de WP Health Checker";
        $message = "Este es un correo de prueba enviado desde el plugin WP Health Checker. " .
                   "Si estás recibiendo este mensaje, la configuración de correo electrónico " .
                   "para las notificaciones del plugin está funcionando correctamente.";
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            wp_send_json_success('Correo de prueba enviado con éxito.');
        } else {
            wp_send_json_error('Error al enviar el correo de prueba. Por favor, verifica la configuración de tu servidor de correo.');
        }
    }
}

// Inicializar el plugin
$wp_health_checker = new WP_Health_Checker();

// Registrar acciones AJAX
add_action('wp_ajax_get_health_report', array($wp_health_checker, 'ajax_get_health_report'));
add_action('wp_ajax_add_admin_email', array($wp_health_checker, 'ajax_add_admin_email'));
add_action('wp_ajax_remove_admin_email', array($wp_health_checker, 'ajax_remove_admin_email'));
add_action('wp_ajax_get_admin_emails', array($wp_health_checker, 'ajax_get_admin_emails'));
add_action('wp_ajax_send_test_email', array($wp_health_checker, 'send_test_email'));

// Función para registrar la activación del plugin
function activate_wp_health_checker() {
    // Establecer la opción inicial de última fecha de envío
    if (!get_option('wp_health_checker_last_report_sent')) {
        update_option('wp_health_checker_last_report_sent', current_time('timestamp'));
    }
    
    // Aquí puedes agregar cualquier otra lógica necesaria para la activación del plugin
}
register_activation_hook(__FILE__, 'activate_wp_health_checker');

// Función para registrar la desactivación del plugin
function deactivate_wp_health_checker() {
    // Aquí puedes agregar cualquier lógica necesaria para la desactivación del plugin
    // Por ejemplo, limpiar opciones o tablas en la base de datos si es necesario
}
register_deactivation_hook(__FILE__, 'deactivate_wp_health_checker');