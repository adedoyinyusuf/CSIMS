/**
 * CSIMS - Main Application JavaScript
 * Consolidates common functionality and removes inline scripts
 * 
 * @version 2.0.0
 * @author CSIMS Development Team
 */

const CSIMS = {
    /**
     * Initialize the application
     */
    init() {
        console.log('🚀 CSIMS App initializing...');
        
        this.initFormValidation();
        this.initDataTables();
        this.initTooltips();
        this.initModals();
        this.initAjaxForms();
        this.initConfirmDialogs();
        this.initDatePickers();
        this.initNumberFormatting();
        
        console.log('✅ CSIMS App initialized successfully');
    },

    /**
     * Form Validation
     */
    initFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                // Clear previous errors
                this.clearFormErrors(form);
                
                if (!this.validateForm(form)) {
                    e.preventDefault();
                    this.showNotification('Validation Error', 'Please fix the errors in the form', 'error');
                }
            });
            
            // Real-time validation
            form.querySelectorAll('[required], [data-validate-rule]').forEach(input => {
                input.addEventListener('blur', () => {
                    this.validateField(input);
                });
            });
        });
    },

    validateForm(form) {
        let isValid = true;
        
        // Required fields
        form.querySelectorAll('[required]').forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        // Custom validation rules
        form.querySelectorAll('[data-validate-rule]').forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    },

    validateField(input) {
        const value = input.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        // Required validation
        if (input.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        }
        
        // Email validation
        if (isValid && input.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address';
            }
        }
        
        // Custom validation rules
        if (isValid && input.dataset.validateRule) {
            const rule = input.dataset.validateRule;
            const ruleValue = input.dataset.validateValue;
            
            switch (rule) {
                case 'min':
                    if (parseFloat(value) < parseFloat(ruleValue)) {
                        isValid = false;
                        errorMessage = `Value must be at least ${ruleValue}`;
                    }
                    break;
                case 'max':
                    if (parseFloat(value) > parseFloat(ruleValue)) {
                        isValid = false;
                        errorMessage = `Value must be at most ${ruleValue}`;
                    }
                    break;
                case 'minlength':
                    if (value.length < parseInt(ruleValue)) {
                        isValid = false;
                        errorMessage = `Must be at least ${ruleValue} characters`;
                    }
                    break;
                case 'pattern':
                    const regex = new RegExp(ruleValue);
                    if (!regex.test(value)) {
                        isValid = false;
                        errorMessage = input.dataset.validateMessage || 'Invalid format';
                    }
                    break;
            }
        }
        
        if (!isValid) {
            this.showFieldError(input, errorMessage);
        } else {
            this.clearFieldError(input);
        }
        
        return isValid;
    },

    showFieldError(input, message) {
        this.clearFieldError(input);
        
        input.classList.add('form-input-error');
        
        const error = document.createElement('div');
        error.className = 'form-error';
        error.innerHTML = `<i class="fas fa-exclamation-circle mr-1"></i>${message}`;
        
        input.parentNode.appendChild(error);
    },

    clearFieldError(input) {
        input.classList.remove('form-input-error');
        
        const error = input.parentNode.querySelector('.form-error');
        if (error) {
            error.remove();
        }
    },

    clearFormErrors(form) {
        form.querySelectorAll('.form-error').forEach(error => error.remove());
        form.querySelectorAll('.form-input-error').forEach(input => {
            input.classList.remove('form-input-error');
        });
    },

    /**
     * DataTables Initialization
     */
    initDataTables() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
            return;
        }
        
        jQuery('[data-datatable]').each(function() {
            const $table = jQuery(this);
            const options = {
                responsive: true,
                pageLength: parseInt($table.data('page-length')) || 25,
                order: [],
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    zeroRecords: "No matching records found",
                    emptyTable: "No data available in table",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                dom: '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>'
            };
            
            // Add export buttons if requested
            if ($table.data('export')) {
                options.dom = '<"flex justify-between items-center mb-4"Blf>rt<"flex justify-between items-center mt-4"ip>';
                options.buttons = ['copy', 'excel', 'pdf', 'print'];
            }
            
            $table.DataTable(options);
        });
    },

    /**
     * Tooltip Initialization
     */
    initTooltips() {
        document.querySelectorAll('[data-tooltip]').forEach(el => {
            el.title = el.dataset.tooltip;
            
            // You can replace this with a more sophisticated tooltip library
            el.classList.add('cursor-help');
        });
    },

    /**
     * Modal Handling
     */
    initModals() {
        // Open modal
        document.querySelectorAll('[data-modal-toggle]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const modalId = btn.dataset.modalToggle;
                this.toggleModal(modalId);
            });
        });
        
        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.add('hidden');
                }
            });
        });
        
        // Close modal on close button
        document.querySelectorAll('[data-modal-close]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const modal = btn.closest('.modal-overlay');
                if (modal) {
                    modal.classList.add('hidden');
                }
            });
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay:not(.hidden)').forEach(modal => {
                    modal.classList.add('hidden');
                });
            }
        });
    },

    toggleModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.toggle('hidden');
        }
    },

    /**
     * AJAX Form Submission
     */
    initAjaxForms() {
        document.querySelectorAll('form[data-ajax]').forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitAjaxForm(form);
            });
        });
    },

    async submitAjaxForm(form) {
        const submitBtn = form.querySelector('[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : '';
        
        try {
            // Disable submit button
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner mr-2"></span> Processing...';
            }
            
            const formData = new FormData(form);
            const url = form.getAttribute('action') || window.location.href;
            const method = form.getAttribute('method') || 'POST';
            
            const response = await fetch(url, {
                method: method,
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Success', result.message || 'Operation completed successfully', 'success');
                
                // Reset form if requested
                if (form.dataset.resetOnSuccess) {
                    form.reset();
                }
                
                // Redirect if provided
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1000);
                }
                
                // Reload if requested
                if (form.dataset.reloadOnSuccess) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } else {
                this.showNotification('Error', result.message || 'An error occurred', 'error');
                
                // Show field-specific errors
                if (result.errors) {
                    Object.keys(result.errors).forEach(field => {
                        const input = form.querySelector(`[name="${field}"]`);
                        if (input) {
                            this.showFieldError(input, result.errors[field]);
                        }
                    });
                }
            }
        } catch (error) {
            console.error('AJAX Error:', error);
            this.showNotification('Error', 'Network error occurred. Please try again.', 'error');
        } finally {
            // Re-enable submit button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    },

    /**
     * Confirmation Dialogs
     */
    initConfirmDialogs() {
        document.querySelectorAll('[data-confirm]').forEach(element => {
            element.addEventListener('click', (e) => {
                const message = element.dataset.confirm || 'Are you sure?';
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    },

    /**
     * Date Pickers (basic implementation)
     */
    initDatePickers() {
        document.querySelectorAll('input[type="date"]').forEach(input => {
            // Modern browsers have native date pickers
            // You can enhance this with a library like flatpickr if needed
            input.classList.add('form-input');
        });
    },

    /**
     * Number Formatting
     */
    initNumberFormatting() {
        document.querySelectorAll('[data-format="currency"]').forEach(el => {
            const value = parseFloat(el.textContent.replace(/[^0-9.-]+/g, ''));
            if (!isNaN(value)) {
                el.textContent = this.formatCurrency(value);
            }
        });
        
        document.querySelectorAll('[data-format="number"]').forEach(el => {
            const value = parseFloat(el.textContent.replace(/[^0-9.-]+/g, ''));
            if (!isNaN(value)) {
                el.textContent = this.formatNumber(value);
            }
        });
    },

    /**
     * Notification System
     */
    showNotification(title, message, type = 'info') {
        // Remove existing notifications of same type
        document.querySelectorAll(`.notification-${type}`).forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type} fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 slide-in-right max-w-md`;
        
        const bgColors = {
            success: 'bg-green-50 border-green-200 text-green-800',
            error: 'bg-red-50 border-red-200 text-red-800',
            warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
            info: 'bg-blue-50 border-blue-200 text-blue-800'
        };
        
        notification.className += ` ${bgColors[type] || bgColors.info} border`;
        
        notification.innerHTML = `
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    ${this.getNotificationIcon(type)}
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium">${this.escapeHtml(title)}</h3>
                    <p class="mt-1 text-sm">${this.escapeHtml(message)}</p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 flex-shrink-0 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    },

    getNotificationIcon(type) {
        const icons = {
            success: '<i class="fas fa-check-circle text-green-600 text-xl"></i>',
            error: '<i class="fas fa-exclamation-circle text-red-600 text-xl"></i>',
            warning: '<i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>',
            info: '<i class="fas fa-info-circle text-blue-600 text-xl"></i>'
        };
        return icons[type] || icons.info;
    },

    /**
     * Helper Utilities
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: 'NGN',
            minimumFractionDigits: 2
        }).format(amount);
    },

    formatNumber(number) {
        return new Intl.NumberFormat('en-NG').format(number);
    },

    formatDate(date) {
        return new Intl.DateTimeFormat('en-NG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        }).format(new Date(date));
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Debounce function for search inputs
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Copy to clipboard
     */
    copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                this.showNotification('Copied', 'Text copied to clipboard', 'success');
            });
        } else {
            // Fallback
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            this.showNotification('Copied', 'Text copied to clipboard', 'success');
        }
    }
};

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => CSIMS.init());
} else {
    CSIMS.init();
}

// Expose globally
window.CSIMS = CSIMS;

// Add fadeOut animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
`;
document.head.appendChild(style);
