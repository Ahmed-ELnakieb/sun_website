/**
 * Sun Trading Company - Admin Panel JavaScript
 */

// Global variables
let sidebarOpen = false;

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeSidebar();
    initializeTooltips();
    initializeConfirmDialogs();
    initializeGlobalSearch();
    initializeAutoSave();
    
    console.log('Admin panel initialized');
});

/**
 * Sidebar functionality
 */
function initializeSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = createSidebarOverlay();
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', toggleSidebar);
        
        // Close sidebar when clicking overlay
        overlay.addEventListener('click', closeSidebar);
        
        // Close sidebar on window resize if mobile
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) {
                closeSidebar();
            }
        });
    }
}

function createSidebarOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
    return overlay;
}

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar && overlay) {
        sidebarOpen = !sidebarOpen;
        
        if (sidebarOpen) {
            sidebar.classList.add('show');
            overlay.classList.add('show');
        } else {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }
    }
}

function closeSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar && overlay) {
        sidebarOpen = false;
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    }
}

/**
 * Initialize tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Confirm dialogs for dangerous actions
 */
function initializeConfirmDialogs() {
    const dangerousActions = document.querySelectorAll('.confirm-action');
    
    dangerousActions.forEach(element => {
        element.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to perform this action?';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

/**
 * Global search functionality
 */
function initializeGlobalSearch() {
    const searchForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('globalSearch');
    
    if (searchForm && searchInput) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            performGlobalSearch(searchInput.value);
        });
        
        // Auto-search on typing (debounced)
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 3) {
                    performGlobalSearch(this.value);
                }
            }, 500);
        });
    }
}

function performGlobalSearch(query) {
    // This would typically make an AJAX request to search across the admin panel
    console.log('Searching for:', query);
    
    // For now, redirect to products page with search
    if (query.trim()) {
        window.location.href = `products.php?search=${encodeURIComponent(query)}`;
    }
}

/**
 * Auto-save functionality for forms
 */
function initializeAutoSave() {
    const autoSaveForms = document.querySelectorAll('.auto-save');
    
    autoSaveForms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('input', debounce(function() {
                autoSaveForm(form);
            }, 2000));
        });
    });
}

function autoSaveForm(form) {
    const formData = new FormData(form);
    formData.append('auto_save', '1');
    
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Auto-saved successfully', 'success');
        }
    })
    .catch(error => {
        console.error('Auto-save error:', error);
    });
}

/**
 * Notification system
 */
function showNotification(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after duration
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, duration);
}

/**
 * Image preview functionality
 */
function previewImage(input, previewId) {
    const file = input.files[0];
    const preview = document.getElementById(previewId);
    
    if (file && preview) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        
        reader.readAsDataURL(file);
    }
}

/**
 * Dynamic form fields
 */
function addFormField(containerId, template) {
    const container = document.getElementById(containerId);
    const templateElement = document.getElementById(template);
    
    if (container && templateElement) {
        const newElement = templateElement.content.cloneNode(true);
        container.appendChild(newElement);
    }
}

function removeFormField(button) {
    const fieldGroup = button.closest('.form-field-group');
    if (fieldGroup) {
        fieldGroup.remove();
    }
}

/**
 * Sortable lists
 */
function initializeSortable(selector) {
    const elements = document.querySelectorAll(selector);
    
    elements.forEach(element => {
        new Sortable(element, {
            animation: 150,
            onEnd: function(evt) {
                updateSortOrder(element);
            }
        });
    });
}

function updateSortOrder(container) {
    const items = container.querySelectorAll('[data-id]');
    const order = Array.from(items).map((item, index) => ({
        id: item.getAttribute('data-id'),
        order: index + 1
    }));
    
    // Send order to server
    fetch('ajax/update-order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ order: order })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Order updated successfully', 'success');
        } else {
            showNotification('Failed to update order', 'danger');
        }
    });
}

/**
 * Bulk actions
 */
function initializeBulkActions() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    const bulkActionSelect = document.getElementById('bulkAction');
    const bulkActionButton = document.getElementById('bulkActionButton');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionButton();
        });
    }
    
    itemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActionButton);
    });
    
    if (bulkActionButton) {
        bulkActionButton.addEventListener('click', performBulkAction);
    }
}

function updateBulkActionButton() {
    const selectedItems = document.querySelectorAll('.item-checkbox:checked');
    const bulkActionButton = document.getElementById('bulkActionButton');
    
    if (bulkActionButton) {
        bulkActionButton.disabled = selectedItems.length === 0;
    }
}

function performBulkAction() {
    const selectedItems = Array.from(document.querySelectorAll('.item-checkbox:checked'))
                               .map(checkbox => checkbox.value);
    const action = document.getElementById('bulkAction').value;
    
    if (selectedItems.length === 0 || !action) {
        alert('Please select items and action');
        return;
    }
    
    if (confirm(`Are you sure you want to ${action} ${selectedItems.length} item(s)?`)) {
        // Perform bulk action
        fetch('ajax/bulk-action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                items: selectedItems
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

/**
 * File upload with progress
 */
function uploadWithProgress(fileInput, progressBarId, callback) {
    const files = fileInput.files;
    const progressBar = document.getElementById(progressBarId);
    
    if (files.length === 0) return;
    
    const formData = new FormData();
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }
    
    const xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            if (progressBar) {
                progressBar.style.width = percentComplete + '%';
                progressBar.setAttribute('aria-valuenow', percentComplete);
            }
        }
    });
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (callback) callback(response);
        }
    };
    
    xhr.open('POST', 'ajax/upload.php');
    xhr.send(formData);
}

/**
 * Utility functions
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function formatFileSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;
    
    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }
    
    return `${size.toFixed(2)} ${units[unitIndex]}`;
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showNotification('Copied to clipboard!', 'success', 2000);
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
        showNotification('Failed to copy to clipboard', 'danger');
    });
}

/**
 * Theme management
 */
function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('admin-theme', theme);
}

function getTheme() {
    return localStorage.getItem('admin-theme') || 'light';
}

// Initialize theme
document.documentElement.setAttribute('data-theme', getTheme());

/**
 * Export functions for global use
 */
window.adminPanel = {
    showNotification,
    previewImage,
    addFormField,
    removeFormField,
    copyToClipboard,
    setTheme,
    getTheme
};