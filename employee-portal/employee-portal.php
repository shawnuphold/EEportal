<?php
/**
 * Plugin Name: Employee Portal
 * Plugin URI: https://github.com/yourusername/employee-portal
 * Description: Complete employee management system with secure document handling, time-off requests, and role-based dashboards.
 * Version: 1.0.0
 * Author: Your Company
 * License: GPL v2 or later
 * Text Domain: employee-portal
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EP_VERSION', '1.0.0');
define('EP_PLUGIN_FILE', __FILE__);
define('EP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Employee Portal Class
 */
class Employee_Portal {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('employee-portal', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Include required files
        $this->include_files();
        
        // Initialize components
        $this->init_hooks();
        
        // Add custom user roles
        $this->create_user_roles();
        
        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    private function include_files() {
        $includes = array(
            'includes/class-database.php',
            'includes/class-admin-dashboard.php',
            'includes/class-employee-dashboard.php',
            'includes/class-document-manager.php',
            'includes/class-time-off-manager.php',
            'includes/class-announcement-manager.php',
            'includes/class-shortcodes.php',
        );
        
        foreach ($includes as $file) {
            $file_path = EP_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                // Log missing file but don't break the plugin
                error_log("Employee Portal: Missing file - $file");
            }
        }
    }
    
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        
        // Initialize components if classes exist
        if (class_exists('EP_Database')) {
            new EP_Database();
        }
        
        if (class_exists('EP_Admin_Dashboard')) {
            new EP_Admin_Dashboard();
        }
        
        if (class_exists('EP_Shortcodes')) {
            new EP_Shortcodes();
        }
    }
    
    public function admin_menu() {
        // Main menu
        add_menu_page(
            'Employee Portal',
            'Employee Portal',
            'manage_options',
            'employee-portal',
            array($this, 'admin_dashboard_page'),
            'dashicons-groups',
            30
        );
        
        // Submenu pages
        add_submenu_page('employee-portal', 'Dashboard', 'Dashboard', 'manage_options', 'employee-portal');
        add_submenu_page('employee-portal', 'Employees', 'Employees', 'manage_employees', 'employee-portal-employees', array($this, 'employees_page'));
        add_submenu_page('employee-portal', 'Documents', 'Documents', 'upload_documents', 'employee-portal-documents', array($this, 'documents_page'));
        add_submenu_page('employee-portal', 'Time Off', 'Time Off', 'approve_time_off', 'employee-portal-time-off', array($this, 'time_off_page'));
        add_submenu_page('employee-portal', 'Announcements', 'Announcements', 'manage_announcements', 'employee-portal-announcements', array($this, 'announcements_page'));
    }
    
    public function admin_dashboard_page() {
        if (class_exists('EP_Admin_Dashboard')) {
            $dashboard = new EP_Admin_Dashboard();
            $dashboard->display_dashboard();
        } else {
            echo '<div class="wrap"><h1>Employee Portal</h1><p>Dashboard class not loaded. Please check plugin files.</p></div>';
        }
    }
    
    public function employees_page() {
        if (class_exists('EP_Admin_Dashboard')) {
            $dashboard = new EP_Admin_Dashboard();
            $dashboard->display_employees();
        } else {
            echo '<div class="wrap"><h1>Employees</h1><p>Admin dashboard class not loaded.</p></div>';
        }
    }
    
    public function documents_page() {
        if (class_exists('EP_Admin_Dashboard')) {
            $dashboard = new EP_Admin_Dashboard();
            $dashboard->display_documents();
        } else {
            echo '<div class="wrap"><h1>Documents</h1><p>Admin dashboard class not loaded.</p></div>';
        }
    }
    
    public function time_off_page() {
        if (class_exists('EP_Admin_Dashboard')) {
            $dashboard = new EP_Admin_Dashboard();
            $dashboard->display_time_off();
        } else {
            echo '<div class="wrap"><h1>Time Off</h1><p>Admin dashboard class not loaded.</p></div>';
        }
    }
    
    public function announcements_page() {
        if (class_exists('EP_Admin_Dashboard')) {
            $dashboard = new EP_Admin_Dashboard();
            $dashboard->display_announcements();
        } else {
            echo '<div class="wrap"><h1>Announcements</h1><p>Admin dashboard class not loaded.</p></div>';
        }
    }
    
    public function admin_scripts($hook) {
        if (strpos($hook, 'employee-portal') !== false) {
            wp_enqueue_style('employee-portal-admin', EP_PLUGIN_URL . 'admin/css/admin.css', array(), EP_VERSION);
            wp_enqueue_script('employee-portal-admin', EP_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), EP_VERSION, true);
        }
    }
    
    public function frontend_scripts() {
        wp_enqueue_style('employee-portal-frontend', EP_PLUGIN_URL . 'public/css/frontend.css', array(), EP_VERSION);
        wp_enqueue_script('employee-portal-frontend', EP_PLUGIN_URL . 'public/js/frontend.js', array('jquery'), EP_VERSION, true);
    }
    
    private function create_user_roles() {
        // Add custom capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_employees');
            $admin_role->add_cap('upload_documents');
            $admin_role->add_cap('approve_time_off');
            $admin_role->add_cap('manage_announcements');
        }
        
        // Create HR Manager role
        if (!get_role('hr_manager')) {
            add_role('hr_manager', 'HR Manager', array(
                'read' => true,
                'manage_employees' => true,
                'upload_documents' => true,
                'approve_time_off' => true,
                'manage_announcements' => true,
            ));
        }
        
        // Create Employee role
        if (!get_role('employee')) {
            add_role('employee', 'Employee', array(
                'read' => true,
            ));
        }
    }
    
    public function admin_notices() {
        if (get_option('ep_show_setup_notice') && current_user_can('manage_options')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>🎉 Employee Portal Activated!</strong> 
                    <a href="<?php echo admin_url('admin.php?page=employee-portal'); ?>" class="button button-primary" style="margin-left: 10px;">⚡ Auto Setup Now</a>
                </p>
            </div>
            <?php
        }
    }
    
    public function activate() {
        // Set activation flag
        update_option('ep_show_setup_notice', true);
        
        // Initialize database if class exists
        if (class_exists('EP_Database')) {
            $database = new EP_Database();
            $database->create_tables();
        }
        
        // Create user roles
        $this->create_user_roles();
        
        // Create upload directories
        $this->create_upload_directories();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Remove setup notice
        delete_option('ep_show_setup_notice');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function create_upload_directories() {
        $upload_dir = wp_upload_dir();
        $directories = array(
            'employee-portal',
            'employee-portal/w2s',
            'employee-portal/i9s',
            'employee-portal/paystubs',
            'employee-portal/policies',
            'employee-portal/others',
        );
        
        foreach ($directories as $dir) {
            $path = $upload_dir['basedir'] . '/' . $dir;
            if (!file_exists($path)) {
                wp_mkdir_p($path);
                
                // Create .htaccess for security
                $htaccess = $path . '/.htaccess';
                if (!file_exists($htaccess)) {
                    file_put_contents($htaccess, "deny from all\n");
                }
            }
        }
    }
}

// Initialize the plugin
function employee_portal_init() {
    return Employee_Portal::get_instance();
}

// Start the plugin
employee_portal_init();
