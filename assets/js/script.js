/**
 * Main JavaScript file for Cooperative Society Information Management System (CSIMS)
 * Version: 1.0
 */

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Toggle password visibility
    var togglePasswordButtons = document.querySelectorAll('.toggle-password');
    togglePasswordButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var targetId = this.getAttribute('data-target');
            var passwordInput = document.getElementById(targetId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordInput.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
    
    // Confirm delete actions
    var deleteButtons = document.querySelectorAll('.btn-delete, .delete-confirm');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Form validation
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Date picker initialization for date inputs
    var dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(function(input) {
        // You can add a date picker library initialization here if needed
    });
    
    // File input custom display
    var fileInputs = document.querySelectorAll('.custom-file-input');
    fileInputs.forEach(function(input) {
        input.addEventListener('change', function(e) {
            var fileName = this.files[0].name;
            var nextSibling = this.nextElementSibling;
            nextSibling.innerText = fileName;
        });
    });
    
    // Print functionality
    var printButtons = document.querySelectorAll('.btn-print');
    printButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            window.print();
        });
    });
    
    // Export to CSV functionality
    var exportButtons = document.querySelectorAll('.btn-export-csv');
    exportButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var tableId = this.getAttribute('data-table');
            exportTableToCSV(tableId, 'export.csv');
        });
    });
    
    // Password strength meter
    var passwordInputs = document.querySelectorAll('.password-strength');
    passwordInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            var password = this.value;
            var strengthMeter = document.getElementById(this.getAttribute('data-strength-meter'));
            var strengthText = document.getElementById(this.getAttribute('data-strength-text'));
            
            var strength = calculatePasswordStrength(password);
            
            // Update the strength meter
            if (strengthMeter) {
                strengthMeter.value = strength;
                
                // Update color based on strength
                if (strength < 40) {
                    strengthMeter.className = 'form-range password-strength-meter weak';
                } else if (strength < 80) {
                    strengthMeter.className = 'form-range password-strength-meter medium';
                } else {
                    strengthMeter.className = 'form-range password-strength-meter strong';
                }
            }
            
            // Update the strength text
            if (strengthText) {
                if (strength < 40) {
                    strengthText.textContent = 'Weak';
                    strengthText.className = 'text-danger';
                } else if (strength < 80) {
                    strengthText.textContent = 'Medium';
                    strengthText.className = 'text-warning';
                } else {
                    strengthText.textContent = 'Strong';
                    strengthText.className = 'text-success';
                }
            }
        });
    });
    
    // Password confirmation validation
    var passwordConfirmInputs = document.querySelectorAll('.password-confirm');
    passwordConfirmInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            var password = document.getElementById(this.getAttribute('data-password-input')).value;
            var confirmPassword = this.value;
            var feedbackElement = document.getElementById(this.getAttribute('data-feedback'));
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                if (feedbackElement) {
                    feedbackElement.textContent = 'Passwords do not match';
                    feedbackElement.className = 'invalid-feedback d-block';
                }
            } else {
                this.setCustomValidity('');
                if (feedbackElement) {
                    feedbackElement.textContent = 'Passwords match';
                    feedbackElement.className = 'valid-feedback d-block';
                }
            }
        });
    });
});

/**
 * Calculate password strength score (0-100)
 * @param {string} password - The password to evaluate
 * @return {number} - Strength score from 0 to 100
 */
function calculatePasswordStrength(password) {
    var score = 0;
    
    // Length contribution (up to 40 points)
    score += Math.min(password.length * 4, 40);
    
    // Complexity contribution (up to 60 points)
    var patterns = {
        digits: /\d/,
        lowercase: /[a-z]/,
        uppercase: /[A-Z]/,
        nonAlphanumeric: /[^a-zA-Z\d]/
    };
    
    var complexityScore = 0;
    for (var pattern in patterns) {
        if (patterns[pattern].test(password)) {
            complexityScore += 15;
        }
    }
    
    score += Math.min(complexityScore, 60);
    
    return Math.min(score, 100);
}

/**
 * Export HTML table to CSV file
 * @param {string} tableId - The ID of the table to export
 * @param {string} filename - The name of the file to download
 */
function exportTableToCSV(tableId, filename) {
    var csv = [];
    var table = document.getElementById(tableId);
    var rows = table.querySelectorAll('tr');
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (var j = 0; j < cols.length; j++) {
            // Replace any commas in the cell text with spaces to avoid CSV issues
            var text = cols[j].innerText.replace(/,/g, ' ');
            // Remove any line breaks
            text = text.replace(/\n/g, ' ');
            // Wrap in quotes to handle any special characters
            row.push('"' + text + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    downloadCSV(csv.join('\n'), filename);
}

/**
 * Trigger download of CSV content
 * @param {string} csv - The CSV content
 * @param {string} filename - The name of the file to download
 */
function downloadCSV(csv, filename) {
    var csvFile;
    var downloadLink;
    
    // Create CSV file
    csvFile = new Blob([csv], {type: 'text/csv'});
    
    // Create download link
    downloadLink = document.createElement('a');
    
    // Set file name
    downloadLink.download = filename;
    
    // Create link to file
    downloadLink.href = window.URL.createObjectURL(csvFile);
    
    // Hide download link
    downloadLink.style.display = 'none';
    
    // Add the link to DOM
    document.body.appendChild(downloadLink);
    
    // Click download link
    downloadLink.click();
    
    // Remove link from DOM
    document.body.removeChild(downloadLink);
}

/**
 * Format currency value
 * @param {number} amount - The amount to format
 * @param {string} currency - The currency code (default: 'USD')
 * @return {string} - Formatted currency string
 */
function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Format date to localized string
 * @param {string} dateString - The date string to format
 * @param {object} options - Intl.DateTimeFormat options
 * @return {string} - Formatted date string
 */
function formatDate(dateString, options = { year: 'numeric', month: 'long', day: 'numeric' }) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', options).format(date);
}

/**
 * Calculate days remaining until a date
 * @param {string} targetDateString - The target date
 * @return {number} - Number of days remaining (negative if past)
 */
function daysRemaining(targetDateString) {
    const targetDate = new Date(targetDateString);
    const currentDate = new Date();
    
    // Reset time part for accurate day calculation
    targetDate.setHours(0, 0, 0, 0);
    currentDate.setHours(0, 0, 0, 0);
    
    // Calculate difference in milliseconds and convert to days
    const differenceMs = targetDate - currentDate;
    return Math.ceil(differenceMs / (1000 * 60 * 60 * 24));
}

/**
 * Show a confirmation dialog
 * @param {string} message - The confirmation message
 * @param {function} onConfirm - Callback function when confirmed
 * @param {function} onCancel - Callback function when canceled
 */
function confirmAction(message, onConfirm, onCancel) {
    if (confirm(message)) {
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    } else {
        if (typeof onCancel === 'function') {
            onCancel();
        }
    }
}

/**
 * Debounce function to limit how often a function can be called
 * @param {function} func - The function to debounce
 * @param {number} wait - The debounce wait time in milliseconds
 * @return {function} - Debounced function
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