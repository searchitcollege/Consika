/**
 * Main JavaScript file for Group Companies Management System
 */

// ============================================
// GLOBAL VARIABLES
// ============================================
const APP_URL = window.location.origin + '/groupcompanies';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

// ============================================
// DOCUMENT READY
// ============================================
$(document).ready(function() {
    initTooltips();
    initPopovers();
    initDataTables();
    initDatePickers();
    initSelect2();
    initFormValidation();
    initAutoDismissAlerts();
    initConfirmDialogs();
    initFileUploads();
});

// ============================================
// TOOLTIPS
// ============================================
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// ============================================
// POPOVERS
// ============================================
function initPopovers() {
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

// ============================================
// DATA TABLES
// ============================================
function initDataTables() {
    $('.data-table').each(function() {
        if (!$.fn.DataTable.isDataTable(this)) {
            $(this).DataTable({
                responsive: true,
                pageLength: 25,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    zeroRecords: "No matching records found",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                dom: '<"top"lf>rt<"bottom"ip><"clear">',
                initComplete: function() {
                    $(this).addClass('table-responsive');
                }
            });
        }
    });
}

// ============================================
// DATE PICKERS
// ============================================
function initDatePickers() {
    $('.datepicker').each(function() {
        $(this).flatpickr({
            dateFormat: 'Y-m-d',
            allowInput: true,
            altInput: true,
            altFormat: 'F j, Y',
            minDate: $(this).data('min-date') || null,
            maxDate: $(this).data('max-date') || null
        });
    });
    
    $('.datetimepicker').each(function() {
        $(this).flatpickr({
            enableTime: true,
            dateFormat: 'Y-m-d H:i:S',
            altInput: true,
            altFormat: 'F j, Y H:i',
            time_24hr: true
        });
    });
    
    $('.monthpicker').each(function() {
        $(this).flatpickr({
            plugins: [
                new monthSelectPlugin({
                    shorthand: true,
                    dateFormat: 'Y-m',
                    altFormat: 'F Y'
                })
            ]
        });
    });
}

// ============================================
// SELECT2
// ============================================
function initSelect2() {
    $('.select2').each(function() {
        $(this).select2({
            placeholder: $(this).data('placeholder') || 'Select option',
            allowClear: $(this).data('allow-clear') || false,
            width: '100%',
            theme: 'bootstrap5'
        });
    });
    
    $('.select2-ajax').each(function() {
        let url = $(this).data('url');
        $(this).select2({
            placeholder: $(this).data('placeholder') || 'Search...',
            allowClear: true,
            width: '100%',
            theme: 'bootstrap5',
            ajax: {
                url: url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        search: params.term,
                        page: params.page || 1
                    };
                },
                processResults: function(data, params) {
                    return {
                        results: data.data.map(item => ({
                            id: item.id,
                            text: item.text
                        }))
                    };
                },
                cache: true
            },
            minimumInputLength: 2
        });
    });
}

// ============================================
// FORM VALIDATION
// ============================================
function initFormValidation() {
    $('.needs-validation').each(function() {
        $(this).on('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            $(this).addClass('was-validated');
        });
    });
    
    // Custom validation methods
    $.validator.addMethod('phoneKE', function(value, element) {
        return this.optional(element) || /^(\+254|0)[71]\d{8}$/.test(value);
    }, 'Please enter a valid Kenyan phone number');
    
    $.validator.addMethod('idNumber', function(value, element) {
        return this.optional(element) || /^\d{7,8}$/.test(value);
    }, 'Please enter a valid ID number');
    
    // Apply validation to forms with validate class
    $('.validate-form').each(function() {
        $(this).validate({
            errorElement: 'div',
            errorClass: 'invalid-feedback',
            highlight: function(element) {
                $(element).addClass('is-invalid');
            },
            unhighlight: function(element) {
                $(element).removeClass('is-invalid');
            },
            errorPlacement: function(error, element) {
                if (element.parent('.input-group').length) {
                    error.insertAfter(element.parent());
                } else {
                    error.insertAfter(element);
                }
            }
        });
    });
}

// ============================================
// AUTO DISMISS ALERTS
// ============================================
function initAutoDismissAlerts() {
    $('.alert-auto-dismiss').each(function() {
        let alert = $(this);
        setTimeout(function() {
            alert.fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    });
}

// ============================================
// CONFIRM DIALOGS
// ============================================
// ============================================
// CONFIRM DIALOGS
// ============================================
function initConfirmDialogs() {
    $('.confirm-delete').on('click', function(e) {
        e.preventDefault();
        let message = $(this).data('message') || 'Are you sure you want to delete this item?';
        let url = $(this).attr('href');
        
        showConfirmDialog(message, function() {
            window.location.href = url;
        });
    });
    
    $('.confirm-action').on('click', function(e) {
        e.preventDefault();
        let message = $(this).data('message') || 'Are you sure you want to perform this action?';
        let callback = $(this).data('callback');
        
        showConfirmDialog(message, function() {
            if (callback && window[callback]) {
                window[callback]();
            }
        });
    });
}

// ============================================
// FILE UPLOADS
// ============================================
function initFileUploads() {
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
    });
    
    $('.file-dropzone').on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });
    
    $('.file-dropzone').on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });
    
    $('.file-dropzone').on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        
        let files = e.originalEvent.dataTransfer.files;
        let input = $(this).find('input[type="file"]');
        
        if (input.length) {
            input.prop('files', files);
            $(this).find('.file-name').text(files.length + ' file(s) selected');
        }
    });
}

// ============================================
// CUSTOM FUNCTIONS
// ============================================

/**
 * Show confirmation dialog
 * @param {string} message - Confirmation message
 * @param {function} callback - Function to execute on confirm
 */
function showConfirmDialog(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Show toast notification
 * @param {string} message - Toast message
 * @param {string} type - Toast type (success, error, warning, info)
 */
function showToast(message, type = 'info') {
    let toast = $(`
        <div class="toast-notification toast-${type}">
            <div class="toast-icon">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 
                                   type === 'error' ? 'exclamation-circle' : 
                                   type === 'warning' ? 'exclamation-triangle' : 
                                   'info-circle'}"></i>
            </div>
            <div class="toast-message">${message}</div>
            <button class="toast-close"><i class="fas fa-times"></i></button>
        </div>
    `);
    
    $('body').append(toast);
    
    setTimeout(function() {
        toast.addClass('show');
    }, 100);
    
    setTimeout(function() {
        toast.removeClass('show');
        setTimeout(function() {
            toast.remove();
        }, 300);
    }, 5000);
    
    toast.find('.toast-close').on('click', function() {
        toast.removeClass('show');
        setTimeout(function() {
            toast.remove();
        }, 300);
    });
}

/**
 * Format currency
 * @param {number} amount - Amount to format
 * @returns {string} Formatted currency
 */
function formatCurrency(amount) {
    return 'GHS ' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Format date
 * @param {string} date - Date string
 * @returns {string} Formatted date
 */
function formatDate(date) {
    return new Date(date).toLocaleDateString('en-KE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Format datetime
 * @param {string} datetime - Datetime string
 * @returns {string} Formatted datetime
 */
function formatDateTime(datetime) {
    return new Date(datetime).toLocaleString('en-KE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Show loading overlay
 */
function showLoading() {
    let overlay = $(`
        <div class="loading-overlay">
            <div class="spinner"></div>
        </div>
    `);
    $('body').append(overlay);
}

/**
 * Hide loading overlay
 */
function hideLoading() {
    $('.loading-overlay').remove();
}

/**
 * Make AJAX request
 * @param {string} url - Request URL
 * @param {string} method - HTTP method
 * @param {object} data - Request data
 * @param {function} success - Success callback
 * @param {function} error - Error callback
 */
function ajaxRequest(url, method = 'GET', data = null, success = null, error = null) {
    $.ajax({
        url: url,
        type: method,
        data: data,
        dataType: 'json',
        beforeSend: function() {
            showLoading();
        },
        success: function(response) {
            hideLoading();
            if (response.success) {
                if (success) success(response.data);
                if (response.message) showToast(response.message, 'success');
            } else {
                if (error) error(response.message);
                if (response.message) showToast(response.message, 'error');
            }
        },
        error: function(xhr, status, errorThrown) {
            hideLoading();
            showToast('An error occurred: ' + errorThrown, 'error');
            if (error) error(errorThrown);
        }
    });
}

/**
 * Print element
 * @param {string} elementId - Element ID to print
 */
function printElement(elementId) {
    let printContents = document.getElementById(elementId).innerHTML;
    let originalContents = document.body.innerHTML;
    
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
}

/**
 * Export table to CSV
 * @param {string} tableId - Table ID to export
 * @param {string} filename - Export filename
 */
function exportTableToCSV(tableId, filename = 'export.csv') {
    let csv = [];
    let rows = document.querySelectorAll('#' + tableId + ' tr');
    
    for (let row of rows) {
        let rowData = [];
        let cols = row.querySelectorAll('td, th');
        
        for (let col of cols) {
            rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
        }
        
        csv.push(rowData.join(','));
    }
    
    let csvContent = csv.join('\n');
    let blob = new Blob([csvContent], { type: 'text/csv' });
    let url = window.URL.createObjectURL(blob);
    let a = document.createElement('a');
    
    a.href = url;
    a.download = filename;
    a.click();
    
    window.URL.revokeObjectURL(url);
}

/**
 * Preview image before upload
 * @param {object} input - File input element
 * @param {string} previewId - Preview image element ID
 */
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        let reader = new FileReader();
        
        reader.onload = function(e) {
            $('#' + previewId).attr('src', e.target.result).show();
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// ============================================
// MODULE FUNCTIONS
// ============================================

/**
 * Load module data dynamically
 * @param {string} module - Module name
 * @param {string} action - Action to perform
 * @param {object} params - Additional parameters
 */
function loadModuleData(module, action, params = {}) {
    let url = APP_URL + '/api/module-data.php?module=' + module + '&action=' + action;
    
    $.each(params, function(key, value) {
        url += '&' + key + '=' + encodeURIComponent(value);
    });
    
    ajaxRequest(url, 'GET', null, function(data) {
        // Handle the data based on module and action
        console.log('Module data loaded:', data);
    });
}

/**
 * Update dashboard stats
 */
function updateDashboardStats() {
    ajaxRequest(APP_URL + '/api/dashboard-stats.php', 'GET', null, function(data) {
        $('.stat-value').each(function() {
            let stat = $(this).data('stat');
            if (data[stat]) {
                $(this).text(data[stat]);
            }
        });
    });
}

/**
 * Refresh notifications
 */
function refreshNotifications() {
    ajaxRequest(APP_URL + '/api/notifications.php', 'GET', null, function(data) {
        let count = data.unread_count || 0;
        $('.notification-badge .badge-count').text(count);
        
        if (count > 0) {
            $('.notification-badge').addClass('has-notifications');
        } else {
            $('.notification-badge').removeClass('has-notifications');
        }
    });
}

// ============================================
// EVENT HANDLERS
// ============================================

// Handle sidebar toggle
$('.sidebar-toggle').on('click', function() {
    $('.sidebar').toggleClass('collapsed');
    $('.main-content').toggleClass('expanded');
});

// Handle search form
$('.search-form').on('submit', function(e) {
    e.preventDefault();
    let query = $(this).find('input[name="search"]').val();
    window.location.href = APP_URL + '/search.php?q=' + encodeURIComponent(query);
});

// Handle print buttons
$('.btn-print').on('click', function() {
    let elementId = $(this).data('print');
    if (elementId) {
        printElement(elementId);
    } else {
        window.print();
    }
});

// Handle export buttons
$('.btn-export-csv').on('click', function() {
    let tableId = $(this).data('table');
    let filename = $(this).data('filename') || 'export.csv';
    
    if (tableId) {
        exportTableToCSV(tableId, filename);
    }
});

// Handle refresh buttons
$('.btn-refresh').on('click', function() {
    location.reload();
});

// ============================================
// POLLING FOR UPDATES
// ============================================

// Check for new notifications every minute
setInterval(function() {
    if ($('body').hasClass('logged-in')) {
        refreshNotifications();
    }
}, 60000);

// Auto-refresh dashboard stats every 5 minutes
if ($('body').hasClass('dashboard-page')) {
    setInterval(function() {
        updateDashboardStats();
    }, 300000);
}

// ============================================
// KEYBOARD SHORTCUTS
// ============================================
$(document).on('keydown', function(e) {
    // Ctrl + S - Save form
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        $('form[data-shortcut-save]').submit();
    }
    
    // Ctrl + F - Focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        $('input[name="search"]').focus();
    }
    
    // Ctrl + N - New item
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        let newUrl = $('.btn-new').attr('href');
        if (newUrl) {
            window.location.href = newUrl;
        }
    }
    
    // Esc - Close modals
    if (e.key === 'Escape') {
        $('.modal.show').modal('hide');
    }
});

// ============================================
//  HAMBURGER MENU
// ============================================
// Toggle sidebar
document.getElementById('sidebarToggle').addEventListener('click', function () {
    document.querySelector('.sidebar').classList.toggle('active');
});

// Close when clicking outside
document.addEventListener('click', function (e) {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('active');
    }
});