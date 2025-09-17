/**
 * R&D Management System - Enhanced UI JavaScript
 * Provides interactive functionality and UI enhancements
 */

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all Bootstrap tooltips
    initTooltips();
    
    // Initialize all Bootstrap popovers
    initPopovers();
    
    // Setup form validations
    setupFormValidations();
    
    // Setup dynamic form elements
    setupDynamicForms();
    
    // Setup file upload previews
    setupFileUploads();
    
    // Setup dashboard charts if on dashboard page
    if (document.getElementById('projectStatusChart') || document.getElementById('resourceAllocationChart')) {
        setupDashboardCharts();
    }
    
    // Setup data tables
    setupDataTables();
    
    // Setup AJAX form submissions
    setupAjaxForms();
    
    // Setup notification handling
    setupNotifications();
    
    // Add animation classes to elements
    animateElements();
});

/**
 * Initialize Bootstrap tooltips
 */
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            boundary: document.body
        });
    });
}

/**
 * Initialize Bootstrap popovers
 */
function initPopovers() {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl, {
            trigger: 'focus',
            html: true
        });
    });
}

/**
 * Setup form validations
 */
function setupFormValidations() {
    // Get all forms with the class 'needs-validation'
    const forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
            
            // Custom password validation
            const password = form.querySelector('#password');
            const confirmPassword = form.querySelector('#confirm_password');
            
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
                event.preventDefault();
                event.stopPropagation();
            } else if (confirmPassword) {
                confirmPassword.setCustomValidity('');
            }
        }, false);
        
        // Real-time validation for password fields
        const password = form.querySelector('#password');
        const confirmPassword = form.querySelector('#confirm_password');
        
        if (password && confirmPassword) {
            confirmPassword.addEventListener('input', () => {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            });
            
            password.addEventListener('input', () => {
                if (confirmPassword.value && password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else if (confirmPassword.value) {
                    confirmPassword.setCustomValidity('');
                }
            });
        }
    });
}

/**
 * Setup dynamic form elements
 */
function setupDynamicForms() {
    // Project members dynamic form
    setupDynamicProjectMembers();
    
    // Project resources dynamic form
    setupDynamicProjectResources();
    
    // Role information display
    setupRoleInfoDisplay();
}

/**
 * Setup dynamic project members form
 */
function setupDynamicProjectMembers() {
    const addMemberBtn = document.getElementById('add-member-btn');
    if (!addMemberBtn) return;
    
    const memberTemplate = document.getElementById('member-template');
    const membersContainer = document.getElementById('members-container');
    
    addMemberBtn.addEventListener('click', function() {
        const memberIndex = document.querySelectorAll('.member-row').length;
        const newMember = memberTemplate.content.cloneNode(true);
        
        // Update IDs and names with the new index
        const inputs = newMember.querySelectorAll('select, input');
        inputs.forEach(input => {
            input.id = input.id.replace('INDEX', memberIndex);
            input.name = input.name.replace('INDEX', memberIndex);
        });
        
        // Add remove button functionality
        const removeBtn = newMember.querySelector('.remove-member-btn');
        removeBtn.addEventListener('click', function() {
            this.closest('.member-row').remove();
            updateMemberIndexes();
        });
        
        membersContainer.appendChild(newMember);
        
        // Initialize select2 if available
        if (typeof $.fn.select2 !== 'undefined') {
            $(`#member_id_${memberIndex}`).select2({
                theme: 'bootstrap4',
                placeholder: 'Select a member'
            });
        }
    });
    
    // Setup existing remove buttons
    document.querySelectorAll('.remove-member-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.member-row').remove();
            updateMemberIndexes();
        });
    });
    
    // Function to update indexes after removal
    function updateMemberIndexes() {
        const memberRows = document.querySelectorAll('.member-row');
        memberRows.forEach((row, index) => {
            const inputs = row.querySelectorAll('select, input');
            inputs.forEach(input => {
                const oldName = input.name;
                const newName = oldName.replace(/\d+/, index);
                input.name = newName;
                input.id = input.id.replace(/\d+/, index);
            });
        });
    }
}

/**
 * Setup dynamic project resources form
 */
function setupDynamicProjectResources() {
    const addResourceBtn = document.getElementById('add-resource-btn');
    if (!addResourceBtn) return;
    
    const resourceTemplate = document.getElementById('resource-template');
    const resourcesContainer = document.getElementById('resources-container');
    
    addResourceBtn.addEventListener('click', function() {
        const resourceIndex = document.querySelectorAll('.resource-row').length;
        const newResource = resourceTemplate.content.cloneNode(true);
        
        // Update IDs and names with the new index
        const inputs = newResource.querySelectorAll('select, input');
        inputs.forEach(input => {
            input.id = input.id.replace('INDEX', resourceIndex);
            input.name = input.name.replace('INDEX', resourceIndex);
        });
        
        // Add remove button functionality
        const removeBtn = newResource.querySelector('.remove-resource-btn');
        removeBtn.addEventListener('click', function() {
            this.closest('.resource-row').remove();
            updateResourceIndexes();
        });
        
        resourcesContainer.appendChild(newResource);
        
        // Initialize select2 if available
        if (typeof $.fn.select2 !== 'undefined') {
            $(`#resource_id_${resourceIndex}`).select2({
                theme: 'bootstrap4',
                placeholder: 'Select a resource'
            });
        }
    });
    
    // Setup existing remove buttons
    document.querySelectorAll('.remove-resource-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.resource-row').remove();
            updateResourceIndexes();
        });
    });
    
    // Function to update indexes after removal
    function updateResourceIndexes() {
        const resourceRows = document.querySelectorAll('.resource-row');
        resourceRows.forEach((row, index) => {
            const inputs = row.querySelectorAll('select, input');
            inputs.forEach(input => {
                const oldName = input.name;
                const newName = oldName.replace(/\d+/, index);
                input.name = newName;
                input.id = input.id.replace(/\d+/, index);
            });
        });
    }
}

/**
 * Setup role information display
 */
function setupRoleInfoDisplay() {
    const roleSelect = document.getElementById('role_id');
    if (!roleSelect) return;
    
    const noRoleSelected = document.getElementById('no-role-selected');
    
    function updateRoleInfo() {
        // Hide all role info divs
        document.querySelectorAll('.role-info').forEach(div => {
            div.style.display = 'none';
        });
        
        const selectedRole = roleSelect.value;
        if (selectedRole) {
            const roleInfoDiv = document.getElementById('role-info-' + selectedRole);
            if (roleInfoDiv) {
                roleInfoDiv.style.display = 'block';
                if (noRoleSelected) noRoleSelected.style.display = 'none';
            } else {
                if (noRoleSelected) noRoleSelected.style.display = 'block';
            }
        } else {
            if (noRoleSelected) noRoleSelected.style.display = 'block';
        }
    }
    
    roleSelect.addEventListener('change', updateRoleInfo);
    
    // Initialize role info display
    updateRoleInfo();
}

/**
 * Setup file upload previews
 */
function setupFileUploads() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        const previewContainer = document.getElementById(input.id + '_preview');
        if (!previewContainer) return;
        
        input.addEventListener('change', function(e) {
            previewContainer.innerHTML = '';
            
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const fileType = file.type;
                
                // Create file info element
                const fileInfo = document.createElement('div');
                fileInfo.className = 'file-info mt-2';
                
                // Add file icon based on type
                let iconClass = 'fas fa-file';
                if (fileType.startsWith('image/')) {
                    iconClass = 'fas fa-file-image';
                } else if (fileType === 'application/pdf') {
                    iconClass = 'fas fa-file-pdf';
                } else if (fileType.includes('word')) {
                    iconClass = 'fas fa-file-word';
                } else if (fileType.includes('excel') || fileType.includes('spreadsheet')) {
                    iconClass = 'fas fa-file-excel';
                } else if (fileType.includes('powerpoint') || fileType.includes('presentation')) {
                    iconClass = 'fas fa-file-powerpoint';
                }
                
                fileInfo.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="${iconClass} fa-2x me-2"></i>
                        <div>
                            <div class="font-weight-bold">${file.name}</div>
                            <div class="text-muted small">${formatFileSize(file.size)}</div>
                        </div>
                    </div>
                `;
                
                // Add preview for images
                if (fileType.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'img-thumbnail mt-2';
                        img.style.maxHeight = '200px';
                        previewContainer.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                }
                
                previewContainer.appendChild(fileInfo);
            }
        });
    });
    
    // Format file size to human-readable format
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

/**
 * Setup dashboard charts
 */
function setupDashboardCharts() {
    // Project Status Chart
    const projectStatusChart = document.getElementById('projectStatusChart');
    if (projectStatusChart) {
        const ctx = projectStatusChart.getContext('2d');
        
        // Get data from data attributes
        const labels = JSON.parse(projectStatusChart.dataset.labels || '["Active", "Completed", "On Hold", "Cancelled"]');
        const data = JSON.parse(projectStatusChart.dataset.values || '[4, 2, 1, 0]');
        const colors = [
            'rgba(78, 115, 223, 0.8)',
            'rgba(28, 200, 138, 0.8)',
            'rgba(246, 194, 62, 0.8)',
            'rgba(231, 74, 59, 0.8)'
        ];
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    hoverBackgroundColor: colors.map(color => color.replace('0.8', '1')),
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                maintainAspectRatio: false,
                tooltips: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    caretPadding: 10,
                },
                legend: {
                    display: true,
                    position: 'bottom'
                },
                cutoutPercentage: 70,
            },
        });
    }
    
    // Resource Allocation Chart
    const resourceAllocationChart = document.getElementById('resourceAllocationChart');
    if (resourceAllocationChart) {
        const ctx = resourceAllocationChart.getContext('2d');
        
        // Get data from data attributes
        const labels = JSON.parse(resourceAllocationChart.dataset.labels || '["Personnel", "Equipment", "Facility", "Financial"]');
        const allocated = JSON.parse(resourceAllocationChart.dataset.allocated || '[70, 50, 30, 40]');
        const available = JSON.parse(resourceAllocationChart.dataset.available || '[30, 50, 70, 60]');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: "Allocated",
                        backgroundColor: "rgba(78, 115, 223, 0.8)",
                        borderColor: "rgba(78, 115, 223, 1)",
                        data: allocated,
                    },
                    {
                        label: "Available",
                        backgroundColor: "rgba(28, 200, 138, 0.8)",
                        borderColor: "rgba(28, 200, 138, 1)",
                        data: available,
                    }
                ],
            },
            options: {
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        left: 10,
                        right: 25,
                        top: 25,
                        bottom: 0
                    }
                },
                scales: {
                    xAxes: [{
                        gridLines: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            maxTicksLimit: 6
                        },
                        maxBarThickness: 25,
                    }],
                    yAxes: [{
                        ticks: {
                            min: 0,
                            max: 100,
                            maxTicksLimit: 5,
                            padding: 10,
                            callback: function(value) {
                                return value + '%';
                            }
                        },
                        gridLines: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    }],
                },
                legend: {
                    display: true,
                    position: 'bottom'
                },
                tooltips: {
                    titleMarginBottom: 10,
                    titleFontColor: '#6e707e',
                    titleFontSize: 14,
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    caretPadding: 10,
                    callbacks: {
                        label: function(tooltipItem, chart) {
                            return tooltipItem.yLabel + '%';
                        }
                    }
                },
            }
        });
    }
}

/**
 * Setup data tables
 */
function setupDataTables() {
    const dataTables = document.querySelectorAll('.datatable');
    
    dataTables.forEach(table => {
        if (typeof $.fn.DataTable !== 'undefined') {
            $(table).DataTable({
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    paginate: {
                        first: '<i class="fas fa-angle-double-left"></i>',
                        previous: '<i class="fas fa-angle-left"></i>',
                        next: '<i class="fas fa-angle-right"></i>',
                        last: '<i class="fas fa-angle-double-right"></i>'
                    }
                }
            });
        }
    });
}

/**
 * Setup AJAX form submissions
 */
function setupAjaxForms() {
    const ajaxForms = document.querySelectorAll('.ajax-form');
    
    ajaxForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: form.method,
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                
                if (data.success) {
                    // Show success message
                    showAlert('success', data.message || 'Operation completed successfully.');
                    
                    // Redirect if specified
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    }
                    
                    // Reset form if specified
                    if (data.reset) {
                        form.reset();
                    }
                    
                    // Refresh page if specified
                    if (data.refresh) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                } else {
                    // Show error message
                    showAlert('danger', data.message || 'An error occurred. Please try again.');
                    
                    // Show field errors if any
                    if (data.errors) {
                        for (const field in data.errors) {
                            const input = form.querySelector(`[name="${field}"]`);
                            if (input) {
                                input.classList.add('is-invalid');
                                
                                // Create or update error message
                                let errorDiv = input.nextElementSibling;
                                if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
                                    errorDiv = document.createElement('div');
                                    errorDiv.className = 'invalid-feedback';
                                    input.parentNode.insertBefore(errorDiv, input.nextSibling);
                                }
                                errorDiv.textContent = data.errors[field];
                            }
                        }
                    }
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                showAlert('danger', 'An error occurred. Please try again.');
                console.error('Error:', error);
            });
        });
    });
    
    // Function to show alert messages
    function showAlert(type, message) {
        const alertContainer = document.getElementById('alert-container');
        if (!alertContainer) return;
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        alertContainer.appendChild(alert);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            alert.classList.remove('show');
            setTimeout(() => {
                alertContainer.removeChild(alert);
            }, 150);
        }, 5000);
    }
}

/**
 * Setup notification handling
 */
function setupNotifications() {
    // Match existing header markup: #notificationsDropdown and a .badge inside the toggle
    const notificationDropdown = document.getElementById('notificationsDropdown');
    if (!notificationDropdown) return;
    
    const notificationsList = document.getElementById('notificationsList');
    // Try to find the badge within the dropdown toggle; create if missing
    let notificationBadge = notificationDropdown.querySelector('.badge');
    
    // Load notifications via AJAX
    function loadNotifications() {
        fetch('api/get_notifications.php', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update notification badge
                if (data.unread_count > 0) {
                    if (!notificationBadge) {
                        notificationBadge = document.createElement('span');
                        notificationBadge.className = 'badge bg-danger';
                        notificationDropdown.appendChild(notificationBadge);
                    }
                    notificationBadge.textContent = data.unread_count;
                    notificationBadge.style.display = '';
                } else if (notificationBadge) {
                    notificationBadge.style.display = 'none';
                }
                
                // Update notifications list
                notificationsList.innerHTML = '';
                
                if (data.notifications.length === 0) {
                    notificationsList.innerHTML = '<div class="dropdown-item text-center">No notifications</div>';
                } else {
                    data.notifications.forEach(notification => {
                        const notificationItem = document.createElement('a');
                        notificationItem.className = `dropdown-item d-flex align-items-center notification-item ${notification.is_read ? '' : 'unread'}`;
                        notificationItem.href = `notification_detail.php?id=${notification.id}`;
                        
                        notificationItem.innerHTML = `
                            <div class="me-3">
                                <div class="icon-circle bg-primary">
                                    <i class="fas ${getNotificationIcon(notification.type)} text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500">${notification.time_ago}</div>
                                <span class="notification-title">${notification.title}</span>
                            </div>
                        `;
                        
                        notificationsList.appendChild(notificationItem);
                    });
                    
                    // Add "View All" link
                    const viewAllLink = document.createElement('a');
                    viewAllLink.className = 'dropdown-item text-center small text-gray-500';
                    viewAllLink.href = 'notifications.php';
                    viewAllLink.textContent = 'View All Notifications';
                    notificationsList.appendChild(viewAllLink);
                }
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
        });
    }
    
    // Get notification icon based on type
    function getNotificationIcon(type) {
        switch (type) {
            case 'project':
                return 'fa-project-diagram';
            case 'document':
                return 'fa-file-alt';
            case 'resource':
                return 'fa-cubes';
            case 'patent':
                return 'fa-certificate';
            case 'user':
                return 'fa-user';
            default:
                return 'fa-bell';
        }
    }
    
    // Load notifications on page load
    loadNotifications();
    
    // Refresh notifications every 60 seconds
    setInterval(loadNotifications, 60000);
    
    // Load notifications when dropdown is opened
    notificationDropdown.addEventListener('show.bs.dropdown', loadNotifications);
}

/**
 * Add animation classes to elements
 */
function animateElements() {
    // Add fade-in animation to cards
    document.querySelectorAll('.card').forEach((card, index) => {
        card.classList.add('fade-in');
        card.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Add slide-in animation to dashboard cards
    document.querySelectorAll('.dashboard-card').forEach((card, index) => {
        card.classList.add('slide-in');
        card.style.animationDelay = `${index * 0.1}s`;
    });
}