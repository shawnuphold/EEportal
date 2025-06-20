<?php
/**
 * Database Management Class
 * 
 * Handles all database operations for the Employee Portal
 */

if (!defined('ABSPATH')) {
    exit;
}

class EP_Database {
    
    /**
     * Database version
     */
    private $db_version = '1.0.0';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Check if database needs updating
        if (get_option('ep_db_version') !== $this->db_version) {
            $this->create_tables();
            update_option('ep_db_version', $this->db_version);
        }
    }
    
    /**
     * Create all database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Employee profiles table
        $employees_table = $wpdb->prefix . 'ep_employees';
        $employees_sql = "CREATE TABLE $employees_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            wp_user_id int(11) NOT NULL,
            employee_id varchar(50) NOT NULL UNIQUE,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20),
            department varchar(100),
            position varchar(100),
            hire_date date,
            salary decimal(10,2),
            status enum('active', 'inactive', 'terminated') DEFAULT 'active',
            manager_id int(11),
            address text,
            emergency_contact_name varchar(100),
            emergency_contact_phone varchar(20),
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY wp_user_id (wp_user_id),
            KEY employee_id (employee_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Documents table
        $documents_table = $wpdb->prefix . 'ep_documents';
        $documents_sql = "CREATE TABLE $documents_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            document_type enum('w2', 'i9', 'paystub', 'policy', 'other') NOT NULL,
            document_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size int(11),
            upload_date timestamp DEFAULT CURRENT_TIMESTAMP,
            uploaded_by int(11) NOT NULL,
            year year,
            is_active boolean DEFAULT TRUE,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY document_type (document_type),
            KEY uploaded_by (uploaded_by)
        ) $charset_collate;";
        
        // Time off requests table
        $time_off_table = $wpdb->prefix . 'ep_time_off_requests';
        $time_off_sql = "CREATE TABLE $time_off_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            request_type enum('vacation', 'sick', 'personal', 'other') NOT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            days_requested decimal(3,1) NOT NULL,
            reason text,
            status enum('pending', 'approved', 'denied') DEFAULT 'pending',
            approved_by int(11),
            request_date timestamp DEFAULT CURRENT_TIMESTAMP,
            response_date timestamp NULL,
            notes text,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY status (status),
            KEY approved_by (approved_by)
        ) $charset_collate;";
        
        // Announcements table
        $announcements_table = $wpdb->prefix . 'ep_announcements';
        $announcements_sql = "CREATE TABLE $announcements_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            content text NOT NULL,
            author_id int(11) NOT NULL,
            publish_date timestamp DEFAULT CURRENT_TIMESTAMP,
            is_active boolean DEFAULT TRUE,
            priority enum('low', 'medium', 'high') DEFAULT 'medium',
            target_departments text,
            expiry_date date,
            PRIMARY KEY (id),
            KEY author_id (author_id),
            KEY is_active (is_active),
            KEY priority (priority)
        ) $charset_collate;";
        
        // Schedules table
        $schedules_table = $wpdb->prefix . 'ep_schedules';
        $schedules_sql = "CREATE TABLE $schedules_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            schedule_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            break_duration int(11) DEFAULT 30,
            notes text,
            created_by int(11) NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY schedule_date (schedule_date),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($employees_sql);
        dbDelta($documents_sql);
        dbDelta($time_off_sql);
        dbDelta($announcements_sql);
        dbDelta($schedules_sql);
    }
    
    /**
     * Get employee by ID
     */
    public function get_employee($employee_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ep_employees';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $employee_id
        ));
    }
    
    /**
     * Get employee by WordPress user ID
     */
    public function get_employee_by_user_id($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ep_employees';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE wp_user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Get all employees
     */
    public function get_all_employees($status = 'all') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ep_employees';
        
        if ($status === 'all') {
            return $wpdb->get_results("SELECT * FROM $table_name ORDER BY last_name, first_name");
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE status = %s ORDER BY last_name, first_name",
                $status
            ));
        }
    }
    
    /**
     * Insert or update employee
     */
    public function save_employee($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ep_employees';
        
        if (isset($data['id']) && $data['id']) {
            // Update existing employee
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $data['id']),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%s', '%s', '%s'),
                array('%d')
            );
            
            return $result !== false ? $data['id'] : false;
        } else {
            // Insert new employee
            $result = $wpdb->insert(
                $table_name,
                $data,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%s', '%s', '%s')
            );
            
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Delete employee
     */
    public function delete_employee($employee_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ep_employees';
        
        return $wpdb->delete(
            $table_name,
            array('id' => $employee_id),
            array('%d')
        );
    }
    
    /**
     * Get documents for employee
     */
    public function get_employee_documents($employee_id, $document_type = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ep_documents';
        
        if ($document_type) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE employee_id = %d AND document_type = %s AND is_active = 1 ORDER BY upload_date DESC",
                $employee_id,
                $document_type
            ));
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE employee_id = %d AND is_active = 1 ORDER BY upload_date DESC",
                $employee_id
            ));
        }
    }
    
    /**
     * Save document record
     */
    public function save_document($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ep_documents';
        
        $result = $wpdb->insert(
            $table_name,
            $data,
            array('%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d')
        );
        