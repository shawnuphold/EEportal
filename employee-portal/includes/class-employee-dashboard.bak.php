<?php
/**
 * Employee Dashboard Template
 * 
 * This template displays the employee self-service dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current user and employee data
$current_user = wp_get_current_user();
$database = new EP_Database();
$employee = $database->get_employee_by_user_id($current_user->ID);

if (!$employee) {
    echo '<div class="ep-alert ep-alert-error">Employee profile not found. Please contact HR.</div>';
    return;
}

// Get employee documents
$documents = $database->get_employee_documents($employee->id);
$w2_docs = array_filter($documents, function($doc) { return $doc->document_type === 'w2'; });
$i9_docs = array_filter($documents, function($doc) { return $doc->document_type === 'i9'; });
$paystub_docs = array_filter($documents, function($doc) { return $doc->document_type === 'paystub'; });

// Get time off requests
$time_off_requests = $database->get_time_off_requests($employee->id);

// Get announcements
$announcements = $database->get_announcements(true, $employee->department);

// Get schedule (next 7 days)
$start_date = date('Y-m-d');
$end_date = date('Y-m-d', strtotime('+7 days'));
$schedule = $database->get_employee_schedules($employee->id, $start_date, $end_date);
?>

<div class="employee-portal ep-dashboard">
    <div class="ep-container">
        
        <!-- Dashboard Header -->
        <div class="ep-dashboard-header">
            <h1>Welcome back, <?php echo esc_html($employee->first_name); ?>!</h1>
            <p>Employee ID: <?php echo esc_html($employee->employee_id); ?> | Department: <?php echo esc_html($employee->department); ?></p>
        </div>

        <!-- Navigation Tabs -->
        <div class="ep-dashboard-nav">
            <ul class="ep-nav-tabs">
                <li><a href="#profile" class="ep-nav-link active" data-tab="profile">My Profile</a></li>
                <li><a href="#documents" class="ep-nav-link" data-tab="documents">Documents</a></li>
                <li><a href="#time-off" class="ep-nav-link" data-tab="time-off">Time Off</a></li>
                <li><a href="#schedule" class="ep-nav-link" data-tab="schedule">Schedule</a></li>
                <li><a href="#announcements" class="ep-nav-link" data-tab="announcements">Announcements</a></li>
            </ul>
        </div>

        <!-- Profile Section -->
        <div id="profile-section" class="ep-content-section ep-tab-content active">
            <h3>My Profile</h3>
            
            <div class="ep-profile-info">
                <div class="ep-profile-field">
                    <label>Full Name</label>
                    <span><?php echo esc_html($employee->first_name . ' ' . $employee->last_name); ?></span>
                </div>
                
                <div class="ep-profile-field">
                    <label>Email</label>
                    <span><?php echo esc_html($employee->email); ?></span>
                </div>
                
                <div class="ep-profile-field">
                    <label>Phone</label>
                    <span><?php echo esc_html($employee->phone ?: 'Not provided'); ?></span>
                </div>
                
                <div class="ep-profile-field">
                    <label>Department</label>
                    <span><?php echo esc_html($employee->department); ?></span>
                </div>
                
                <div class="ep-profile-field">
                    <label>Position</label>
                    <span><?php echo esc_html($employee->position); ?></span>
                </div>
                
                <div class="ep-profile-field">
                    <label>Hire Date</label>
                    <span><?php echo esc_html(date('F j, Y', strtotime($employee->hire_date))); ?></span>
                </div>
                
                <?php if ($employee->manager_id): 
                    $manager = $database->get_employee($employee->manager_id);
                    if ($manager): ?>
                <div class="ep-profile-field">
                    <label>Manager</label>
                    <span><?php echo esc_html($manager->first_name . ' ' . $manager->last_name); ?></span>
                </div>
                <?php endif; endif; ?>
                
                <div class="ep-profile-field">
                    <label>Employment Status</label>
                    <span class="ep-status-<?php echo esc_attr($employee->status); ?>">
                        <?php echo esc_html(ucfirst($employee->status)); ?>
                    </span>
                </div>
            </div>
            
            <?php if ($employee->emergency_contact_name): ?>
            <h4>Emergency Contact</h4>
            <div class="ep-profile-info">
                <div class="ep-profile-field">
                    <label>Contact Name</label>
                    <span><?php echo esc_html($employee->emergency_contact_name); ?></span>
                </div>
                
                <div class="ep-profile-field">
                    <label>Contact Phone</label>
                    <span><?php echo esc_html($employee->emergency_contact_phone); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Documents Section -->
        <div id="documents-section" class="ep-content-section ep-tab-content" style="display: none;">
            <h3>My Documents</h3>
            
            <!-- W-2 Forms -->
            <div class="ep-document-category">
                <h4>W-2 Tax Forms</h4>
                <?php if (!empty($w2_docs)): ?>
                    <div class="ep-documents-grid">
                        <?php foreach ($w2_docs as $doc): ?>
                            <div class="ep-document-card">
                                <div class="ep-document-icon"></div>
                                <div class="ep-document-name"><?php echo esc_html($doc->document_name); ?></div>
                                <div class="ep-document-meta">
                                    Year: <?php echo esc_html($doc->year ?: 'N/A'); ?><br>
                                    Uploaded: <?php echo esc_html(date('M j, Y', strtotime($doc->upload_date))); ?><br>
                                    Size: <?php echo esc_html(size_format($doc->file_size)); ?>
                                </div>
                                <div class="ep-document-actions">
                                    <a href="<?php echo esc_url($this->get_download_url($doc->id)); ?>" 
                                       class="ep-btn ep-btn-primary ep-btn-sm">Download</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No W-2 forms available.</p>
                <?php endif; ?>
            </div>

            <!-- I-9 Forms -->
            <div class="ep-document-category">
                <h4>I-9 Employment Eligibility Forms</h4>
                <?php if (!empty($i9_docs)): ?>
                    <div class="ep-documents-grid">
                        <?php foreach ($i9_docs as $doc): ?>
                            <div class="ep-document-card">
                                <div class="ep-document-icon"></div>
                                <div class="ep-document-name"><?php echo esc_html($doc->document_name); ?></div>
                                <div class="ep-document-meta">
                                    Uploaded: <?php echo esc_html(date('M j, Y', strtotime($doc->upload_date))); ?><br>
                                    Size: <?php echo esc_html(size_format($doc->file_size)); ?>
                                </div>
                                <div class="ep-document-actions">
                                    <a href="<?php echo esc_url($this->get_download_url($doc->id)); ?>" 
                                       class="ep-btn ep-btn-primary ep-btn-sm">Download</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No I-9 forms available.</p>
                <?php endif; ?>
            </div>

            <!-- Pay Stubs -->
            <div class="ep-document-category">
                <h4>Pay Stubs</h4>
                <?php if (!empty($paystub_docs)): ?>
                    <div class="ep-documents-grid">
                        <?php foreach (array_slice($paystub_docs, 0, 6) as $doc): ?>
                            <div class="ep-document-card">
                                <div class="ep-document-icon"></div>
                                <div class="ep-document-name"><?php echo esc_html($doc->document_name); ?></div>
                                <div class="ep-document-meta">
                                    Uploaded: <?php echo esc_html(date('M j, Y', strtotime($doc->upload_date))); ?><br>
                                    Size: <?php echo esc_html(size_format($doc->file_size)); ?>
                                </div>
                                <div class="ep-document-actions">
                                    <a href="<?php echo esc_url($this->get_download_url($doc->id)); ?>" 
                                       class="ep-btn ep-btn-primary ep-btn-sm">Download</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($paystub_docs) > 6): ?>
                        <p><a href="#" class="ep-btn ep-btn-secondary" id="load-more-paystubs">Load More Pay Stubs</a></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>No pay stubs available.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Time Off Section -->
        <div id="time-off-section" class="ep-content-section ep-tab-content" style="display: none;">
            <h3>Time Off Management</h3>
            
            <!-- Time Off Request Form -->
            <div class="ep-time-off-form">
                <h4>Submit New Time Off Request</h4>
                <form id="time-off-form" method="post">
                    <?php wp_nonce_field('ep_time_off', 'time_off_nonce'); ?>
                    <input type="hidden" name="employee_id" value="<?php echo esc_attr($employee->id); ?>">
                    
                    <div class="ep-form-row">
                        <div class="ep-form-group">
                            <label for="request_type">Request Type</label>
                            <select name="request_type" id="request_type" required>
                                <option value="">Select Type</option>
                                <option value="vacation">Vacation</option>
                                <option value="sick">Sick Leave</option>
                                <option value="personal">Personal Day</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="ep-form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" name="start_date" id="start_date" required>
                        </div>
                        
                        <div class="ep-form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" name="end_date" id="end_date" required>
                        </div>
                        
                        <div class="ep-form-group">
                            <label for="days_requested">Days Requested</label>
                            <input type="number" name="days_requested" id="days_requested" step="0.5" min="0.5" required>
                        </div>
                    </div>
                    
                    <div class="ep-form-group">
                        <label for="reason">Reason (Optional)</label>
                        <textarea name="reason" id="reason" placeholder="Please provide details about your time off request..."></textarea>
                    </div>
                    
                    <button type="submit" class="ep-btn ep-btn-primary">Submit Request</button>
                </form>
            </div>
            
            <!-- Time Off Requests History -->
            <h4>My Time Off Requests</h4>
            <?php if (!empty($time_off_requests)): ?>
                <div class="ep-requests-list">
                    <?php foreach ($time_off_requests as $request): ?>
                        <div class="ep-request-item">
                            <div class="ep-request-info">
                                <h4><?php echo esc_html(ucfirst($request->request_type)); ?> - 
                                    <?php echo esc_html($request->days_requested); ?> day<?php echo $request->days_requested != 1 ? 's' : ''; ?></h4>
                                <p>
                                    <strong>Dates:</strong> 
                                    <?php echo esc_html(date('M j, Y', strtotime($request->start_date))); ?> - 
                                    <?php echo esc_html(date('M j, Y', strtotime($request->end_date))); ?>
                                </p>
                                <?php if ($request->reason): ?>
                                    <p><strong>Reason:</strong> <?php echo esc_html($request->reason); ?></p>
                                <?php endif; ?>
                                <p><strong>Requested:</strong> <?php echo esc_html(date('M j, Y g:i A', strtotime($request->request_date))); ?></p>
                                <?php if ($request->notes): ?>
                                    <p><strong>Notes:</strong> <?php echo esc_html($request->notes); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="ep-request-status ep-status-<?php echo esc_attr($request->status); ?>">
                                <?php echo esc_html(ucfirst($request->status)); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No time off requests found.</p>
            <?php endif; ?>
        </div>

        <!-- Schedule Section -->
        <div id="schedule-section" class="ep-content-section ep-tab-content" style="display: none;">
            <h3>My Schedule (Next 7 Days)</h3>
            
            <?php if (!empty($schedule)): ?>
                <div class="ep-schedule">
                    <div class="ep-schedule-header">Upcoming Schedule</div>
                    <?php foreach ($schedule as $shift): ?>
                        <div class="ep-schedule-day">
                            <div class="ep-schedule-date">
                                <?php echo esc_html(date('l, F j, Y', strtotime($shift->schedule_date))); ?>
                            </div>
                            <div class="ep-schedule-time">
                                <?php echo esc_html(date('g:i A', strtotime($shift->start_time))); ?> - 
                                <?php echo esc_html(date('g:i A', strtotime($shift->end_time))); ?>
                                <?php if ($shift->break_duration): ?>
                                    (<?php echo esc_html($shift->break_duration); ?> min break)
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No schedule information available for the next 7 days.</p>
            <?php endif; ?>
        </div>

        <!-- Announcements Section -->
        <div id="announcements-section" class="ep-content-section ep-tab-content" style="display: none;">
            <h3>Company Announcements</h3>
            
            <?php if (!empty($announcements)): ?>
                <div class="ep-announcements">
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="ep-announcement <?php echo esc_attr($announcement->priority); ?>-priority">
                            <h4><?php echo esc_html($announcement->title); ?></h4>
                            <div><?php echo wp_kses_post(wpautop($announcement->content)); ?></div>
                            <div class="ep-announcement-meta">
                                Published: <?php echo esc_html(date('F j, Y g:i A', strtotime($announcement->publish_date))); ?>
                                <?php if ($announcement->expiry_date): ?>
                                    | Expires: <?php echo esc_html(date('F j, Y', strtotime($announcement->expiry_date))); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No announcements at this time.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab navigation
    $('.ep-nav-link').on('click', function(e) {
        e.preventDefault();
        
        var targetTab = $(this).data('tab');
        
        // Remove active class from all tabs and content
        $('.ep-nav-link').removeClass('active');
        $('.ep-tab-content').removeClass('active').hide();
        
        // Add active class to clicked tab and show content
        $(this).addClass('active');
        $('#' + targetTab + '-section').addClass('active').show();
    });
    
    // Time off form submission
    $('#time-off-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=ep_submit_time_off&nonce=' + ep_ajax.n