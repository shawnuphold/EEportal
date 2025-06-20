<?php
/**
 * Authentication Class
 * 
 * Handles user authentication and authorization for the Employee Portal
 */

if (!defined('ABSPATH')) {
    exit;
}

class EP_Auth {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_login', array($this, 'on_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'on_user_logout'));
        add_filter('login_redirect', array($this, 'custom_login_redirect'), 10, 3);
    }
    
    /**
     * Authenticate user credentials
     */
    public function authenticate($username, $password) {
        // Sanitize input
        $username = sanitize_user($username);
        $password = sanitize_text_field($password);
        
        if (empty($username) || empty($password)) {
            return array(
                'success' => false,
                'message' => 'Username and password are required.'
            );
        }
        
        // Attempt authentication
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            return array(
                'success' => false,
                'message' => 'Invalid username or password.'
            );
        }
        
        // Check if user has proper role
        if (!$this->user_has_portal_access($user)) {
            return array(
                'success' => false,
                'message' => 'You do not have access to the employee portal.'
            );
        }
        
        // Log the user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        
        return array(
            'success' => true,
            'message' => 'Login successful.',
            'redirect_url' => $this->get_redirect_url($user)
        );
    }
    
    /**
     * Check if user has portal access
     */
    public function user_has_portal_access($user) {
        if (!$user || is_wp_error($user)) {
            return false;
        }
        
        $allowed_roles = array('administrator', 'hr_manager', 'employee');
        
        foreach ($allowed_roles as $role) {
            if (in_array($role, $user->roles)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get redirect URL based on user role
     */
    public function get_redirect_url($user) {
        if (!$user || is_wp_error($user)) {
            return home_url();
        }
        
        // Check for custom redirect in session
        if (isset($_SESSION['ep_redirect_after_login'])) {
            $redirect = $_SESSION['ep_redirect_after_login'];
            unset($_SESSION['ep_redirect_after_login']);
            return $redirect;
        }
        
        // Default redirects based on role
        if (user_can($user, 'manage_employees')) {
            return admin_url('admin.php?page=employee-portal');
        } elseif (user_can($user, 'view_own_profile')) {
            // Redirect to employee dashboard page
            return $this->get_employee_dashboard_url();
        }
        
        return home_url();
    }
    
    /**
     * Get employee dashboard URL
     */
    private function get_employee_dashboard_url() {
        // Look for a page with the employee dashboard shortcode
        $pages = get_posts(array(
            'post_type' => 'page',
            'meta_query' => array(
                array(
                    'key' => '_wp_page_template',
                    'value' => 'employee-dashboard',
                    'compare' => 'LIKE'
                )
            )
        ));
        
        if (!empty($pages)) {
            return get_permalink($pages[0]->ID);
        }
        
        // Fallback: look for shortcode in page content
        $pages = get_posts(array(
            'post_type' => 'page',
            'post_content' => '[employee_dashboard]',
            'post_status' => 'publish'
        ));
        
        if (!empty($pages)) {
            return get_permalink($pages[0]->ID);
        }
        
        return home_url();
    }
    
    /**
     * Handle user login
     */
    public function on_user_login($user_login, $user) {
        // Log login activity
        $this->log_user_activity($user->ID, 'login');
        
        // Update last login time
        update_user_meta($user->ID, 'ep_last_login', current_time('mysql'));
    }
    
    /**
     * Handle user logout
     */
    public function on_user_logout() {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            // Log logout activity
            $this->log_user_activity($user_id, 'logout');
            
            // Update last logout time
            update_user_meta($user_id, 'ep_last_logout', current_time('mysql'));
        }
    }
    
    /**
     * Custom login redirect
     */
    public function custom_login_redirect($redirect_to, $request, $user) {
        if (!is_wp_error($user) && $this->user_has_portal_access($user)) {
            return $this->get_redirect_url($user);
        }
        
        return $redirect_to;
    }
    
    /**
     * Check if current user can access employee data
     */
    public function can_access_employee_data($employee_id) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $current_user = wp_get_current_user();
        
        // Admins and HR managers can access all employee data
        if (current_user_can('manage_employees')) {
            return true;
        }
        
        // Employees can only access their own data
        if (current_user_can('view_own_profile')) {
            $database = new EP_Database();
            $employee = $database->get_employee_by_user_id($current_user->ID);
            
            return $employee && $employee->id == $employee_id;
        }
        
        return false;
    }
    
    /**
     * Check if current user can upload documents for employee
     */
    public function can_upload_documents_for_employee($employee_id) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Only admins and HR managers can upload documents
        return current_user_can('upload_documents');
    }
    
    /**
     * Check if current user can approve time off requests
     */
    public function can_approve_time_off() {
        return current_user_can('approve_time_off');
    }
    
    /**
     * Check if current user can manage announcements
     */
    public function can_manage_announcements() {
        return current_user_can('manage_announcements');
    }
    
    /**
     * Generate secure nonce for specific action
     */
    public function generate_nonce($action) {
        return wp_create_nonce('ep_' . $action);
    }
    
    /**
     * Verify nonce for specific action
     */
    public function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, 'ep_' . $action);
    }
    
    /**
     * Log user activity
     */
    private function log_user_activity($user_id, $activity, $details = '') {
        $log_entry = array(
            'user_id' => $user_id,
            'activity' => $activity,
            'details' => $details,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => current_time('mysql')
        );
        
        // Store in user meta (you could also create a separate log table)
        $existing_logs = get_user_meta($user_id, 'ep_activity_log', true);
        if (!is_array($existing_logs)) {
            $existing_logs = array();
        }
        
        // Keep only last 50 entries
        if (count($existing_logs) >= 50) {
            $existing_logs = array_slice($existing_logs, -49);
        }
        
        $existing_logs[] = $log_entry;
        update_user_meta($user_id, 'ep_activity_log', $existing_logs);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Force password reset for user
     */
    public function force_password_reset($user_id) {
        update_user_meta($user_id, 'ep_force_password_reset', true);
    }
    
    /**
     * Check if user needs password reset
     */
    public function needs_password_reset($user_id) {
        return get_user_meta($user_id, 'ep_force_password_reset', true);
    }
    
    /**
     * Clear password reset flag
     */
    public function clear_password_reset_flag($user_id) {
        delete_user_meta($user_id, 'ep_force_password_reset');
    }
    
    /**
     * Check password strength
     */
    public function check_password_strength($password) {
        $strength = 0;
        $feedback = array();
        
        // Length check
        if (strlen($password) >= 8) {
            $strength += 1;
        } else {
            $feedback[] = 'Password should be at least 8 characters long.';
        }
        
        // Uppercase check
        if (preg_match('/[A-Z]/', $password)) {
            $strength += 1;
        } else {
            $feedback[] = 'Password should contain at least one uppercase letter.';
        }
        
        // Lowercase check
        if (preg_match('/[a-z]/', $password)) {
            $strength += 1;
        } else {
            $feedback[] = 'Password should contain at least one lowercase letter.';
        }
        
        // Number check
        if (preg_match('/[0-9]/', $password)) {
            $strength += 1;
        } else {
            $feedback[] = 'Password should contain at least one number.';
        }
        
        // Special character check
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $strength += 1;
        } else {
            $feedback[] = 'Password should contain at least one special character.';
        }
        
        return array(
            'strength' => $strength,
            'feedback' => $feedback,
            'is_strong' => $strength >= 4
        );
    }
    
    /**
     * Generate secure random password
     */
    public function generate_secure_password($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[wp_rand(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Send password reset email
     */
    public function send_password_reset_email($user_id) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }
        
        $reset_key = get_password_reset_key($user);
        
        if (is_wp_error($reset_key)) {
            return false;
        }
        
        $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login), 'login');
        
        $subject = 'Password Reset - Employee Portal';
        $message = "Hello {$user->first_name},\n\n";
        $message .= "You have requested a password reset for your Employee Portal account.\n\n";
        $message .= "Please click the following link to reset your password:\n";
        $message .= $reset_url . "\n\n";
        $message .= "If you did not request this password reset, please ignore this email.\n\n";
        $message .= "Best regards,\nHR Team";
        
        return wp_mail($user->user_email, $subject, $message);
    }
}