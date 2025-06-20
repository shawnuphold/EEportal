<?php
/**
 * File Manager Class
 * 
 * Handles secure file uploads, downloads, and management for the Employee Portal
 */

if (!defined('ABSPATH')) {
    exit;
}

class EP_File_Manager {
    
    /**
     * Allowed file types
     */
    private $allowed_types = array('pdf');
    
    /**
     * Maximum file size (in bytes) - 10MB
     */
    private $max_file_size = 10485760;
    
    /**
     * Upload directory structure
     */
    private $upload_dirs = array(
        'w2' => 'w2-forms',
        'i9' => 'i9-forms',
        'paystub' => 'paystubs',
        'policy' => 'policies'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_ep_download_document', array($this, 'handle_download'));
        add_action('wp_ajax_ep_delete_document', array($this, 'handle_delete'));
    }
    
    /**
     * Handle file upload
     */
    public function handle_upload($files, $post_data) {
        // Verify nonce
        if (!wp_verify_nonce($post_data['nonce'], 'ep_admin_nonce')) {
            return array(
                'success' => false,
                'message' => 'Security check failed.'
            );
        }
        
        // Check user permissions
        if (!current_user_can('upload_documents')) {
            return array(
                'success' => false,
                'message' => 'You do not have permission to upload documents.'
            );
        }
        
        // Validate required fields
        if (empty($post_data['employee_id']) || empty($post_data['document_type'])) {
            return array(
                'success' => false,
                'message' => 'Employee ID and document type are required.'
            );
        }
        
        // Check if file was uploaded
        if (empty($files['document']['name'])) {
            return array(
                'success' => false,
                'message' => 'Please select a file to upload.'
            );
        }
        
        $file = $files['document'];
        
        // Validate file
        $validation = $this->validate_file($file);
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'message' => $validation['message']
            );
        }
        
        // Process upload
        $upload_result = $this->process_upload($file, $post_data);
        
        if ($upload_result['success']) {
            // Save to database
            $database = new EP_Database();
            $document_data = array(
                'employee_id' => intval($post_data['employee_id']),
                'document_type' => sanitize_text_field($post_data['document_type']),
                'document_name' => sanitize_file_name($file['name']),
                'file_path' => $upload_result['file_path'],
                'file_size' => $file['size'],
                'uploaded_by' => get_current_user_id(),
                'year' => isset($post_data['year']) ? intval($post_data['year']) : null
            );
            
            $document_id = $database->save_document($document_data);
            
            if ($document_id) {
                return array(
                    'success' => true,
                    'message' => 'Document uploaded successfully.',
                    'document_id' => $document_id
                );
            } else {
                // Delete uploaded file if database save failed
                if (file_exists($upload_result['full_path'])) {
                    unlink($upload_result['full_path']);
                }
                
                return array(
                    'success' => false,
                    'message' => 'Failed to save document information.'
                );
            }
        }
        
        return $upload_result;
    }
    
    /**
     * Validate uploaded file
     */
    private function validate_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array(
                'valid' => false,
                'message' => $this->get_upload_error_message($file['error'])
            );
        }
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return array(
                'valid' => false,
                'message' => 'File size exceeds the maximum limit of ' . size_format($this->max_file_size) . '.'
            );
        }
        
        // Check file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $this->allowed_types)) {
            return array(
                'valid' => false,
                'message' => 'Only PDF files are allowed.'
            );
        }
        
        // Check file MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if ($mime_type !== 'application/pdf') {
            return array(
                'valid' => false,
                'message' => 'Invalid file type. Only PDF files are allowed.'
            );
        }
        
        // Additional security check - scan file content
        if (!$this