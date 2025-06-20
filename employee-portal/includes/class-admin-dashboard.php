<?php
/**
 * Admin Dashboard Class
 * 
 * Handles the admin interface for the Employee Portal
 */

if (!defined('ABSPATH')) {
    exit;
}

class EP_Admin_Dashboard {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_ep_save_employee', array($this, 'save_employee'));
        add_action('wp_ajax_ep_delete_employee', array($this, 'delete_employee'));
        add_action('wp_ajax_ep_save_announcement', array($this, 'save_announcement'));
        add_action('wp_ajax_ep_delete_announcement', array($this, 'delete_announcement'));
        add_action('wp_ajax_ep_approve_time_off', array($this, 'approve_time_off'));
        add_action('wp_ajax_ep_search_employees', array($this, 'search_employees'));
    }
    
    /**
     * Display main dashboard
     */
    public function display_dashboard() {
        if (!current_user_can('manage_employees')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        
        $database = new EP_Database();
        $stats = $database->get_dashboard_stats();
        
        ?>
        <div class="wrap">
            <h1>Employee Portal Dashboard</h1>
            
            <!-- Dashboard Statistics -->
            <div class="ep-admin-stats">
                <div class="ep-stat-box">
                    <h3><?php echo esc_html($stats['total_employees']); ?></h3>
                    <p>Active Employees</p>
                </div>
                
                <div class="ep-stat-box">
                    <h3><?php echo esc_html($stats['pending_time_off']); ?></h3>
                    <p>Pending Time Off Requests</p>
                </div>
                
                <div class="ep-stat-box">
                    <h3><?php echo esc_html($stats['documents_this_month']); ?></h3>
                    <p>Documents Uploaded This Month</p>
                </div>
                
                <div class="ep-stat-box">
                    <h3><?php echo count($stats['employees_by_department']); ?></h3>
                    <p>Departments</p>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="ep-quick-actions">
                <h2>Quick Actions</h2>
                <div class="ep-action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=employee-portal-employees&action=add'); ?>" 
                       class="button button-primary">Add New Employee</a>
                    <a href="<?php echo admin_url('admin.php?page=employee-portal-documents'); ?>" 
                       class="button">Upload Documents</a>
                    <a href="<?php echo admin_url('admin.php?page=employee-portal-announcements&action=add'); ?>" 
                       class="button">Create Announcement</a>
                    <a href="<?php echo admin_url('admin.php?page=employee-portal-time-off'); ?>" 
                       class="button">Review Time Off Requests</a>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="ep-recent-activity">
                <h2>Recent Activity</h2>
                <?php $this->display_recent_activity(); ?>
            </div>
            
            <!-- Department Overview -->
            <?php if (!empty($stats['employees_by_department'])): ?>
            <div class="ep-department-overview">
                <h2>Employees by Department</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Employee Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['employees_by_department'] as $dept): ?>
                        <tr>
                            <td><?php echo esc_html($dept->department ?: 'Unassigned'); ?></td>
                            <td><?php echo esc_html($dept->count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Display employees management page
     */
    public function display_employees() {
        if (!current_user_can('manage_employees')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
        
        switch ($action) {
            case 'add':
                $this->display_employee_form();
                break;
            case 'edit':
                $this->display_employee_form($employee_id);
                break;
            case 'view':
                $this->display_employee_profile($employee_id);
                break;
            default:
                $this->display_employees_list();
                break;
        }
    }
    
    /**
     * Display employees list
     */
    private function display_employees_list() {
        $database = new EP_Database();
        $employees = $database->get_all_employees('active');
        
        ?>
        <div class="wrap">
            <h1>
                Employees 
                <a href="<?php echo admin_url('admin.php?page=employee-portal-employees&action=add'); ?>" 
                   class="page-title-action">Add New Employee</a>
            </h1>
            
            <!-- Search and Filter -->
            <div class="ep-search-filter">
                <form method="get">
                    <input type="hidden" name="page" value="employee-portal-employees">
                    <p class="search-box">
                        <input type="search" name="s" value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>" 
                               placeholder="Search employees...">
                        <input type="submit" class="button" value="Search">
                    </p>
                </form>
            </div>
            
            <!-- Employees Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Hire Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><?php echo esc_html($employee->employee_id); ?></td>
                            <td>
                                <strong>
                                    <a href="<?php echo admin_url('admin.php?page=employee-portal-employees&action=view&employee_id=' . $employee->id); ?>">
                                        <?php echo esc_html($employee->first_name . ' ' . $employee->last_name); ?>
                                    </a>
                                </strong>
                            </td>
                            <td><?php echo esc_html($employee->email); ?></td>
                            <td><?php echo esc_html($employee->department); ?></td>
                            <td><?php echo esc_html($employee->position); ?></td>
                            <td><?php echo esc_html(date('M j, Y', strtotime($employee->hire_date))); ?></td>
                            <td>
                                <span class="ep-status ep-status-<?php echo esc_attr($employee->status); ?>">
                                    <?php echo esc_html(ucfirst($employee->status)); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=employee-portal-employees&action=edit&employee_id=' . $employee->id); ?>" 
                                   class="button button-small">Edit</a>
                                <a href="<?php echo admin_url('admin.php?page=employee-portal-employees&action=view&employee_id=' . $employee->id); ?>" 
                                   class="button button-small">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">No employees found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Display employee form (add/edit)
     */
    private function display_employee_form($employee_id = 0) {
        $employee = null;
        $is_edit = false;
        
        if ($employee_id) {
            $database = new EP_Database();
            $employee = $database->get_employee($employee_id);
            $is_edit = true;
            
            if (!$employee) {
                wp_die('Employee not found.');
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo $is_edit ? 'Edit Employee' : 'Add New Employee'; ?></h1>
            
            <form id="employee-form" method="post">
                <?php wp_nonce_field('ep_save_employee', 'employee_nonce'); ?>
                <?php if ($is_edit): ?>
                    <input type="hidden" name="employee_id" value="<?php echo esc_attr($employee->id); ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="employee_id">Employee ID</label>
                        </th>
                        <td>
                            <input type="text" name="employee_id" id="employee_id" 
                                   value="<?php echo esc_attr($employee ? $employee->employee_id : ''); ?>" 
                                   class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="first_name">First Name</label>
                        </th>
                        <td>
                            <input type="text" name="first_name" id="first_name" 
                                   value="<?php echo esc_attr($employee ? $employee->first_name : ''); ?>" 
                                   class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="last_name">Last Name</label>
                        </th>
                        <td>
                            <input type="text" name="last_name" id="last_name" 
                                   value="<?php echo esc_attr($employee ? $employee->last_name : ''); ?>" 
                                   class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="email">Email</label>
                        </th>
                        <td>
                            <input type="email" name="email" id="email" 
                                   value="<?php echo esc_attr($employee ? $employee->email : ''); ?>" 
                                   class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="phone">Phone</label>
                        </th>
                        <td>
                            <input type="tel" name="phone" id="phone" 
                                   value="<?php echo esc_attr($employee ? $employee->phone : ''); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="department">Department</label>
                        </th>
                        <td>
                            <input type="text" name="department" id="department" 
                                   value="<?php echo esc_attr($employee ? $employee->department : ''); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="position">Position</label>
                        </th>
                        <td>
                            <input type="text" name="position" id="position" 
                                   value="<?php echo esc_attr($employee ? $employee->position : ''); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hire_date">Hire Date</label>
                        </th>
                        <td>
                            <input type="date" name="hire_date" id="hire_date" 
                                   value="<?php echo esc_attr($employee ? $employee->hire_date : ''); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="salary">Salary</label>
                        </th>
                        <td>
                            <input type="number" name="salary" id="salary" step="0.01" 
                                   value="<?php echo esc_attr($employee ? $employee->salary : ''); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="status">Status</label>
                        </th>
                        <td>
                            <select name="status" id="status">
                                <option value="active" <?php selected($employee ? $employee->status : 'active', 'active'); ?>>Active</option>
                                <option value="inactive" <?php selected($employee ? $employee->status : '', 'inactive'); ?>>Inactive</option>
                                <option value="terminated" <?php selected($employee ? $employee->status : '', 'terminated'); ?>>Terminated</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="address">Address</label>
                        </th>
                        <td>
                            <textarea name="address" id="address" rows="3" class="large-text"><?php echo esc_textarea($employee ? $employee->address : ''); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="emergency_contact_name">Emergency Contact Name</label>
                        </th>
                        <td>
                            <input type="text" name="emergency_contact_name" id="emergency_contact_name" 
                                   value="<?php echo esc_attr($employee ? $employee->emergency_contact_name : ''); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="emergency_contact_phone">Emergency Contact Phone</label>
                        </th>
                        <td>
                            <input type="tel" name="emergency_contact_phone" id="emergency_contact_phone" 
                                   value="<?php echo esc_attr($employee ? $employee->emergency_contact_phone : ''); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                </table>
                
                <?php if (!$is_edit): ?>
                <h3>WordPress User Account</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="username">Username</label>
                        </th>
                        <td>
                            <input type="text" name="username" id="username" class="regular-text" required>
                            <p class="description">Username for WordPress login</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="password">Password</label>
                        </th>
                        <td>
                            <input type="password" name="password" id="password" class="regular-text" required>
                            <p class="description">Initial password (user should change on first login)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="user_role">User Role</label>
                        </th>
                        <td>
                            <select name="user_role" id="user_role">
                                <option value="employee">Employee</option>
                                <option value="hr_manager">HR Manager</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php endif; ?>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" 
                           value="<?php echo $is_edit ? 'Update Employee' : 'Add Employee'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=employee-portal-employees'); ?>" 
                       class="button">Cancel</a>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#employee-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += '&action=ep_save_employee';
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    beforeSend: function() {
                        $('#employee-form input[type="submit"]').prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Employee saved successfully!');
                            window.location.href = '<?php echo admin_url('admin.php?page=employee-portal-employees'); ?>';
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('An error occurred while saving the employee.');
                    },
                    complete: function() {
                        $('#employee-form input[type="submit"]').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Display documents management page
     */
    public function display_documents() {
        if (!current_user_can('upload_documents')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        
        $database = new EP_Database();
        $employees = $database->get_all_employees('active');
        
        ?>
        <div class="wrap">
            <h1>Document Management</h1>
            
            <!-- Document Upload Form -->
            <div class="ep-upload-section">
                <h2>Upload New Document</h2>
                <form id="document-upload-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('ep_upload_document', 'upload_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="employee_select">Employee</label>
                            </th>
                            <td>
                                <select name="employee_id" id="employee_select" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo esc_attr($emp->id); ?>">
                                            <?php echo esc_html($emp->first_name . ' ' . $emp->last_name . ' (' . $emp->employee_id . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="document_type">Document Type</label>
                            </th>
                            <td>
                                <select name="document_type" id="document_type" required>
                                    <option value="">Select Type</option>
                                    <option value="w2">W-2 Tax Form</option>
                                    <option value="i9">I-9 Employment Form</option>
                                    <option value="paystub">Pay Stub</option>
                                    <option value="policy">Policy Document</option>
                                    <option value="other">Other</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr id="year-row" style="display: none;">
                            <th scope="row">
                                <label for="document_year">Year</label>
                            </th>
                            <td>
                                <input type="number" name="year" id="document_year" 
                                       min="2000" max="<?php echo date('Y'); ?>" 
                                       value="<?php echo date('Y'); ?>">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="document_file">Document File</label>
                            </th>
                            <td>
                                <input type="file" name="document" id="document_file" 
                                       accept=".pdf" required>
                                <p class="description">Only PDF files are allowed. Maximum file size: 10MB.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="submit" class="button-primary" value="Upload Document">
                    </p>
                </form>
            </div>
            
            <!-- Recent Documents -->
            <div class="ep-recent-documents">
                <h2>Recently Uploaded Documents</h2>
                <?php $this->display_recent_documents(); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Show year field for W-2 documents
            $('#document_type').on('change', function() {
                if ($(this).val() === 'w2') {
                    $('#year-row').show();
                } else {
                    $('#year-row').hide();
                }
            });
            
            // Handle document upload
            $('#document-upload-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('action', 'ep_upload_document');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        $('#document-upload-form input[type="submit"]').prop('disabled', true).val('Uploading...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Document uploaded successfully!');
                            $('#document-upload-form')[0].reset();
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('An error occurred while uploading the document.');
                    },
                    complete: function() {
                        $('#document-upload-form input[type="submit"]').prop('disabled', false).val('Upload Document');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Display recent activity
     */
    private function display_recent_activity() {
        // This would typically query a log table or recent changes
        echo '<p>Recent activity tracking will be implemented here.</p>';
    }
    
    /**
     * Display recent documents
     */
    private function display_recent_documents() {
        global $wpdb;
        
        $documents = $wpdb->get_results(
            "SELECT d.*, e.first_name, e.last_name, e.employee_id 
             FROM {$wpdb->prefix}ep_documents d 
             LEFT JOIN {$wpdb->prefix}ep_employees e ON d.employee_id = e.id 
             WHERE d.is_active = 1 
             ORDER BY d.upload_date DESC 
             LIMIT 10"
        );
        
        if (!empty($documents)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>Employee</th>';
            echo '<th>Document Type</th>';
            echo '<th>File Name</th>';
            echo '<th>Upload Date</th>';
            echo '<th>Actions</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            
            foreach ($documents as $doc) {
                echo '<tr>';
                echo '<td>' . esc_html($doc->first_name . ' ' . $doc->last_name . ' (' . $doc->employee_id . ')') . '</td>';
                echo '<td>' . esc_html(ucfirst($doc->document_type)) . '</td>';
                echo '<td>' . esc_html($doc->document_name) . '</td>';
                echo '<td>' . esc_html(date('M j, Y g:i A', strtotime($doc->upload_date))) . '</td>';
                echo '<td>';
                echo '<button class="button button-small ep-delete-doc" data-doc-id="' . esc_attr($doc->id) . '">Delete</button>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>No documents found.</p>';
        }
    }
    
    /**
     * AJAX: Save employee
     */
    public function save_employee() {
        check_ajax_referer('ep_save_employee', 'employee_nonce');
        
        if (!current_user_can('manage_employees')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        // Implementation for saving employee data
        // This would include creating WordPress user account if needed
        // and saving employee profile data
        
        wp_send_json_success('Employee saved successfully.');
    }
    
    /**
     * AJAX: Delete employee
     */
    public function delete_employee() {
        check_ajax_referer('ep_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_employees')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        $employee_id = intval($_POST['employee_id']);
        
        $database = new EP_Database();
        $result = $database->delete_employee($employee_id);
        
        if ($result) {
            wp_send_json_success('Employee deleted successfully.');
        } else {
            wp_send_json_error('Failed to delete employee.');
        }
    }
    
    /**
     * Display announcements page
     */
    public function display_announcements() {
        if (!current_user_can('manage_announcements')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        
        echo '<div class="wrap">';
        echo '<h1>Announcements Management</h1>';
        echo '<p>Announcements management interface will be implemented here.</p>';
        echo '</div>';
    }
    
    /**
     * Display time off requests page
     */
    public function display_time_off() {
        if (!current_user_can('approve_time_off')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        
        echo '<div class="wrap">';
        echo '<h1>Time Off Requests</h1>';
        echo '<p>Time off requests management interface will be implemented here.</p>';
        echo '</div>';
    }
}