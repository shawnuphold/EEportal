<?php
/**
 * Plugin Name: Employee Portal
 * Plugin URI: https://yourwebsite.com
 * Description: Comprehensive employee management portal with secure document handling, time-off requests, and role-based dashboards.
 * Version: 1.0.0
 * Author: Your Company
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * Text Domain: employee-portal
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('EP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('EP_VERSION', '1.0.0');

/**
 * Main Employee Portal Class
 */
class EmployeePortal {
    
    /**
     * Single instance of the class
     */
    private static $_instance = null;
    
    /**
     * Main Employee Portal Instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->includes();
        $this->init();
    }
    
    /**
     * Hook into actions and filters
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('init', array($this, 'init'), 0);
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }
    
    /**
     * Include required core files
     */
    public function includes() {
        include_once EP_PLUGIN_PATH . 'includes/class-database.php';
        include_once EP_PLUGIN_PATH . 'includes/class-auth.php';
        include_once EP_PLUGIN_PATH . 'includes/class-file-manager.php';
        include_once EP_PLUGIN_PATH . 'includes/class-admin-dashboard.php';
        include_once EP_PLUGIN_PATH . 'includes/class-employee-dashboard.php';
    }
    
    /**
     * Init Employee Portal when WordPress initializes
     */
    public function init() {
        // Initialize classes
        $this->database = new EP_Database();
        $this->auth = new EP_Auth();
        $this->file_manager = new EP_File_Manager();
        $this->admin_dashboard = new EP_Admin_Dashboard();
        $this->employee_dashboard = new EP_Employee_Dashboard();
        
        // Add custom user roles
        $this->add_user_roles();
        
        // Handle shortcodes
        add_shortcode('employee_login', array($this, 'login_shortcode'));
        add_shortcode('employee_dashboard', array($this, 'dashboard_shortcode'));
        
        // Handle AJAX requests
        add_action('wp_ajax_ep_submit_time_off', array($this, 'handle_time_off_request'));
        add_action('wp_ajax_ep_upload_document', array($this, 'handle_document_upload'));
        add_action('wp_ajax_nopriv_ep_login', array($this, 'handle_login'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->database->create_tables();
        
        // Create upload directories
        $this->create_upload_directories();
        
        // Add user roles and capabilities
        $this->add_user_roles();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up temporary files
        $this->cleanup_temp_files();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Add custom user roles
     */
    private function add_user_roles() {
        // HR Manager role
        add_role('hr_manager', 'HR Manager', array(
            'read' => true,
            'manage_employees' => true,
            'upload_documents' => true,
            'manage_announcements' => true,
            'approve_time_off' => true,
        ));
        
        // Employee role
        add_role('employee', 'Employee', array(
            'read' => true,
            'view_own_profile' => true,
            'download_documents' => true,
            'submit_time_off' => true,
        ));
        
        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_employees');
            $admin_role->add_cap('upload_documents');
            $admin_role->add_cap('manage_announcements');
            $admin_role->add_cap('approve_time_off');
        }
    }
    
    /**
     * Create upload directories
     */
    private function create_upload_directories() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/employee-portal/';
        
        $directories = array(
            'w2-forms',
            'i9-forms',
            'paystubs',
            'policies',
            'temp'
        );
        
        foreach ($directories as $dir) {
            $full_path = $base_dir . $dir;
            if (!wp_mkdir_p($full_path)) {
                error_log('Failed to create directory: ' . $full_path);
            }
            
            // Create .htaccess to protect files
            $htaccess_content = "Order Deny,Allow\nDeny from all\n";
            file_put_contents($full_path . '/.htaccess', $htaccess_content);
        }
    }
    
    /**
     * Add admin menu
     */
    public function admin_menu() {
        add_menu_page(
            'Employee Portal',
            'Employee Portal',
            'manage_employees',
            'employee-portal',
            array($this->admin_dashboard, 'display_dashboard'),
            'dashicons-groups',
            30
        );
        
        add_submenu_page(
            'employee-portal',
            'Employees',
            'Employees',
            'manage_employees',
            'employee-portal-employees',
            array($this->admin_dashboard, 'display_employees')
        );
        
        add_submenu_page(
            'employee-portal',
            'Documents',
            'Documents',
            'upload_documents',
            'employee-portal-documents',
            array($this->admin_dashboard, 'display_documents')
        );
        
        add_submenu_page(
            'employee-portal',
            'Announcements',
            'Announcements',
            'manage_announcements',
            'employee-portal-announcements',
            array($this->admin_dashboard, 'display_announcements')
        );
        
        add_submenu_page(
            'employee-portal',
            'Time Off Requests',
            'Time Off',
            'approve_time_off',
            'employee-portal-time-off',
            array($this->admin_dashboard, 'display_time_off')
        );
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style('ep-public-style', EP_PLUGIN_URL . 'public/css/public-style.css', array(), EP_VERSION);
        wp_enqueue_script('ep-public-script', EP_PLUGIN_URL . 'public/js/public-script.js', array('jquery'), EP_VERSION, true);
        
        // Localize script for AJAX
        wp_localize_script('ep-public-script', 'ep_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ep_nonce'),
        ));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'employee-portal') === false) {
            return;
        }
        
        wp_enqueue_style('ep-admin-style', EP_PLUGIN_URL . 'admin/css/admin-style.css', array(), EP_VERSION);
        wp_enqueue_script('ep-admin-script', EP_PLUGIN_URL . 'admin/js/admin-script.js', array('jquery'), EP_VERSION, true);
        
        // Localize script for AJAX
        wp_localize_script('ep-admin-script', 'ep_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ep_admin_nonce'),
        ));
    }
    
    /**
     * Login shortcode
     */
    public function login_shortcode($atts) {
        $atts = shortcode_atts(array(
            'redirect' => '',
        ), $atts, 'employee_login');
        
        if (is_user_logged_in()) {
            return '<p>You are already logged in. <a href="' . wp_logout_url() . '">Logout</a></p>';
        }
        
        ob_start();
        include EP_PLUGIN_PATH . 'public/partials/login-form.php';
        return ob_get_clean();
    }
    
    /**
     * Dashboard shortcode
     */
    public function dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to access your dashboard.</p>';
        }
        
        $user = wp_get_current_user();
        
        if (current_user_can('manage_employees')) {
            return $this->admin_dashboard->display_frontend_dashboard();
        } elseif (current_user_can('view_own_profile')) {
            return $this->employee_dashboard->display_dashboard();
        }
        
        return '<p>You do not have permission to access this dashboard.</p>';
    }
    
    /**
     * Handle time off request AJAX
     */
    public function handle_time_off_request() {
        check_ajax_referer('ep_nonce', 'nonce');
        
        if (!current_user_can('submit_time_off')) {
            wp_die('Unauthorized');
        }
        
        // Process time off request
        $result = $this->employee_dashboard->submit_time_off_request($_POST);
        
        wp_send_json($result);
    }
    
    /**
     * Handle document upload AJAX
     */
    public function handle_document_upload() {
        check_ajax_referer('ep_admin_nonce', 'nonce');
        
        if (!current_user_can('upload_documents')) {
            wp_die('Unauthorized');
        }
        
        // Process document upload
        $result = $this->file_manager->handle_upload($_FILES, $_POST);
        
        wp_send_json($result);
    }
    
    /**
     * Handle login AJAX
     */
    public function handle_login() {
        check_ajax_referer('ep_nonce', 'nonce');
        
        $result = $this->auth->authenticate($_POST['username'], $_POST['password']);
        
        wp_send_json($result);
    }
    
    /**
     * Cleanup temporary files
     */
    private function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/employee-portal/temp/';
        
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}

/**
 * Main instance of Employee Portal
 */
function employee_portal() {
    return EmployeePortal::instance();
}

// Initialize the plugin
employee_portal();