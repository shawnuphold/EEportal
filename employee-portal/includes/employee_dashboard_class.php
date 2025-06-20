<?php
/**
 * Employee Dashboard Class
 * 
 * Handles the employee self-service dashboard functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class EP_Employee_Dashboard {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_ep_submit_time_off', array($this, 'submit_time_off_request'));
    }
    
    /**
     * Display employee dashboard
     */
    public function display_dashboard() {
        if (!is_user_logged_in()) {
            return '<p>Please log in to access your dashboard.</p>';
        }
        
        ob_start();
        include EP_PLUGIN_PATH . 'public/partials/employee-dashboard.php';
        return ob_get_clean();
    }
    
    /**
     * Submit time off request
     */
    public function submit_time_off_request($data = null) {
        if ($data === null) {
            $data = $_POST;
        }
        
        // Validate nonce
        if (!wp_verify_nonce($data['time_off_nonce'], 'ep_time_off')) {
            wp_send_json_error('Security check failed.');
            return;
        }
        
        // Validate required fields
        $required_fields = array('employee_id', 'request_type', 'start_date', 'end_date', 'days_requested');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                wp_send_json_error('All required fields must be filled.');
                return;
            }
        }
        
        // Validate dates
        $start_date = strtotime($data['start_date']);
        $end_date = strtotime($data['end_date']);
        
        if ($start_date >= $end_date) {
            wp_send_json_error('End date must be after start date.');
            return;
        }
        
        if ($start_date < strtotime('today')) {
            wp_send_json_error('Start date cannot be in the past.');
            return;
        }
        
        // Prepare data for database
        $request_data = array(
            'employee_id' => intval($data['employee_id']),
            'request_type' => sanitize_text_field($data['request_type']),
            'start_date' => date('Y-m-d', $start_date),
            'end_date' => date('Y-m-d', $end_date),
            'days_requested' => floatval($data['days_requested']),
            'reason' => sanitize_textarea_field($data['reason']),
            'status' => 'pending'
        );
        
        // Save to database
        $database = new EP_Database();
        $result = $database->save_time_off_request($request_data);
        
        if ($result) {
            wp_send_json_success('Time off request submitted successfully.');
        } else {
            wp_send_json_error('Failed to submit time off request.');
        }
    }
    
    /**
     * Get download URL for document
     */
    public function get_download_url($document_id) {
        $file_manager = new EP_File_Manager();
        return $file_manager->get_download_url($document_id);
    }
    
    /**
     * Display frontend dashboard for admins
     */
    public function display_frontend_dashboard() {
        return '<div class="ep-admin-frontend"><h2>Admin Dashboard</h2><p>Access the full admin panel from WordPress Admin → Employee Portal</p></div>';
    }
}