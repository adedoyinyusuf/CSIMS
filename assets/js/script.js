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

// Enhanced sidebar functionality
class SidebarManager {
    constructor() {
        this.sidebar = document.getElementById('sidebarMenu');
        
        // Only initialize if sidebar exists (not on login page)
        if (!this.sidebar) {
            return;
        }
        
        this.overlay = null;
        this.init();
    }
    
    init() {
        this.createOverlay();
        this.bindEvents();
        this.handleResize();
    }
    
    createOverlay() {
        this.overlay = document.createElement('div');
        this.overlay.className = 'sidebar-overlay';
        document.body.appendChild(this.overlay);
        
        this.overlay.addEventListener('click', () => this.hideSidebar());
    }
    
    bindEvents() {
        // Mobile menu toggle
        const toggleBtn = document.querySelector('.navbar-toggler');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggleSidebar());
        }
        
        // Handle window resize
        window.addEventListener('resize', () => this.handleResize());
        
        // Smooth scrolling for navigation - only if sidebar exists
        if (this.sidebar) {
            this.sidebar.addEventListener('click', (e) => {
                if (e.target.closest('.nav-link')) {
                    this.addRippleEffect(e);
                }
            });
        }
    }
    
    toggleSidebar() {
        if (!this.sidebar) return;
        
        if (window.innerWidth <= 991.98) {
            this.sidebar.classList.toggle('show');
            this.overlay.classList.toggle('show');
            document.body.style.overflow = this.sidebar.classList.contains('show') ? 'hidden' : '';
        }
    }
    
    hideSidebar() {
        if (!this.sidebar) return;
        
        this.sidebar.classList.remove('show');
        this.overlay.classList.remove('show');
        document.body.style.overflow = '';
    }
    
    handleResize() {
        if (window.innerWidth > 991.98) {
            this.hideSidebar();
        }
    }
    
    addRippleEffect(e) {
        const link = e.target.closest('.nav-link');
        const ripple = document.createElement('span');
        const rect = link.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        `;
        
        link.appendChild(ripple);
        setTimeout(() => ripple.remove(), 600);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new SidebarManager();
});

// Add ripple animation CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Auto-adjust table action buttons based on content
document.addEventListener('DOMContentLoaded', () => {
    const tables = document.querySelectorAll('.table-responsive');
    tables.forEach(table => {
        const actionCells = table.querySelectorAll('td:last-child, th:last-child');
        actionCells.forEach(cell => {
            const buttons = cell.querySelectorAll('.btn');
            if (buttons.length > 3) {
                cell.style.minWidth = '150px';
            }
        });
    });
});

// Add these enhancements to your existing script.js

// Enhanced loading states and micro-interactions
class UIEnhancer {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupLoadingStates();
        this.setupMicroAnimations();
        this.setupKeyboardNavigation();
        this.setupMobileOptimizations();
        this.setupAccessibilityFeatures();
    }
    
    setupLoadingStates() {
        // Add loading states to buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.btn[type="submit"], .btn-primary')) {
                this.showButtonLoading(e.target);
            }
        });
        
        // Add skeleton loading for tables
        this.addSkeletonLoading();
    }
    
    showButtonLoading(button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        button.disabled = true;
        
        // Simulate loading (remove this in production)
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        }, 2000);
    }
    
    addSkeletonLoading() {
        const style = document.createElement('style');
        style.textContent = `
            .skeleton {
                background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                background-size: 200% 100%;
                animation: loading 1.5s infinite;
            }
            
            @keyframes loading {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }
            
            .skeleton-text {
                height: 1rem;
                border-radius: 4px;
                margin-bottom: 0.5rem;
            }
            
            .skeleton-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
            }
        `;
        document.head.appendChild(style);
    }
    
    setupMicroAnimations() {
        // Add hover animations to cards
        document.querySelectorAll('.card, .dashboard-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-2px)';
                card.style.transition = 'transform 0.2s ease';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });
        
        // Add ripple effect to buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.btn')) {
                this.createRipple(e);
            }
        });
    }
    
    createRipple(e) {
        const button = e.target;
        const ripple = document.createElement('span');
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        `;
        
        button.style.position = 'relative';
        button.style.overflow = 'hidden';
        button.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 600);
    }
    
    setupKeyboardNavigation() {
        // Enhanced keyboard navigation
        document.addEventListener('keydown', (e) => {
            // Alt + M for main menu
            if (e.altKey && e.key === 'm') {
                e.preventDefault();
                document.querySelector('.sidebar .nav-link')?.focus();
            }
            
            // Alt + S for search
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                document.querySelector('input[type="search"], input[placeholder*="Search"]')?.focus();
            }
            
            // Escape to close modals/dropdowns
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    bootstrap.Modal.getInstance(modal)?.hide();
                });
            }
        });
    }
    
    setupMobileOptimizations() {
        // Convert tables to cards on mobile
        if (window.innerWidth <= 768) {
            this.convertTablesToCards();
        }
        
        // Add swipe gestures for mobile navigation
        this.setupSwipeGestures();
    }
    
    convertTablesToCards() {
        const tables = document.querySelectorAll('.table-responsive table');
        tables.forEach(table => {
            const rows = table.querySelectorAll('tbody tr');
            const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
            
            const cardContainer = document.createElement('div');
            cardContainer.className = 'mobile-card-view d-md-none';
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const card = this.createMobileCard(headers, cells);
                cardContainer.appendChild(card);
            });
            
            table.parentNode.appendChild(cardContainer);
            table.classList.add('d-none', 'd-md-table');
        });
    }
    
    createMobileCard(headers, cells) {
        const card = document.createElement('div');
        card.className = 'member-card';
        
        let cardHTML = '<div class="member-card-header">';
        
        // Add avatar if first cell contains image
        const firstCell = cells[0];
        const img = firstCell.querySelector('img');
        if (img) {
            cardHTML += `<div class="member-card-avatar"><img src="${img.src}" alt="${img.alt}" class="w-100 h-100 rounded-circle object-fit-cover"></div>`;
        } else {
            cardHTML += '<div class="member-card-avatar"><i class="fas fa-user"></i></div>';
        }
        
        cardHTML += '<div class="member-card-info">';
        
        // Add main info
        cells.forEach((cell, index) => {
            if (index < headers.length - 1) { // Exclude actions column
                const header = headers[index];
                const content = cell.textContent.trim();
                if (content) {
                    cardHTML += `<div><strong>${header}:</strong> ${content}</div>`;
                }
            }
        });
        
        cardHTML += '</div></div>';
        
        // Add actions
        const actionsCell = cells[cells.length - 1];
        if (actionsCell) {
            cardHTML += '<div class="member-card-actions">';
            cardHTML += actionsCell.innerHTML;
            cardHTML += '</div>';
        }
        
        card.innerHTML = cardHTML;
        return card;
    }
    
    setupSwipeGestures() {
        let startX = 0;
        let startY = 0;
        
        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        });
        
        document.addEventListener('touchend', (e) => {
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            const diffX = startX - endX;
            const diffY = startY - endY;
            
            // Horizontal swipe
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                if (diffX > 0) {
                    // Swipe left - hide sidebar
                    document.querySelector('.sidebar')?.classList.remove('show');
                } else {
                    // Swipe right - show sidebar
                    document.querySelector('.sidebar')?.classList.add('show');
                }
            }
        });
    }
    
    setupAccessibilityFeatures() {
        // Add ARIA labels dynamically
        document.querySelectorAll('.btn').forEach(btn => {
            if (!btn.getAttribute('aria-label') && !btn.textContent.trim()) {
                const icon = btn.querySelector('i');
                if (icon) {
                    const iconClass = icon.className;
                    if (iconClass.includes('fa-edit')) btn.setAttribute('aria-label', 'Edit');
                    if (iconClass.includes('fa-trash')) btn.setAttribute('aria-label', 'Delete');
                    if (iconClass.includes('fa-eye')) btn.setAttribute('aria-label', 'View');
                }
            }
        });
        
        // Add skip links
        const skipLink = document.createElement('a');
        skipLink.href = '#main-content';
        skipLink.className = 'skip-link';
        skipLink.textContent = 'Skip to main content';
        document.body.insertBefore(skipLink, document.body.firstChild);
        
        // Add main content landmark
        const mainContent = document.querySelector('main');
        if (mainContent) {
            mainContent.id = 'main-content';
            mainContent.setAttribute('role', 'main');
        }
    }
}

// Initialize UI enhancements
document.addEventListener('DOMContentLoaded', () => {
    new UIEnhancer();
});

// Remove or comment out the entire service worker section
// Progressive Web App features - only register if sw.js exists
// if ('serviceWorker' in navigator) {
//     window.addEventListener('load', () => {
//         // Check if service worker file exists before registering
//         fetch('/sw.js', { method: 'HEAD' })
//             .then(response => {
//                 if (response.ok) {
//                     navigator.serviceWorker.register('/sw.js')
//                         .then(registration => console.log('SW registered'))
//                         .catch(error => console.log('SW registration failed'));
//                 }
//             })
//             .catch(() => {
//                 // Service worker file doesn't exist, skip registration
//                 console.log('Service worker file not found, skipping registration');
//             });
//     });
// }