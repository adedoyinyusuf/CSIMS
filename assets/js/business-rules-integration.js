/**
 * CSIMS Business Rules Integration JavaScript
 * 
 * Provides client-side functionality for loan eligibility checking,
 * real-time validation, and enhanced user experience with business rules.
 * 
 * @version 1.0.0
 */

class CSIMSBusinessRules {
    constructor() {
        this.baseUrl = '/controllers/enhanced_loan_controller.php';
        this.eligibilityCache = new Map();
        this.init();
    }

    init() {
        // Initialize event listeners
        this.setupEligibilityChecker();
        this.setupLoanCalculator();
        this.setupSavingsValidator();
        
        console.log('CSIMS Business Rules Integration loaded');
    }

    /**
     * Setup real-time loan eligibility checking
     */
    setupEligibilityChecker() {
        const form = document.getElementById('loanApplicationForm');
        if (!form) return;

        const memberId = this.getMemberIdFromSession();
        const amountInput = document.getElementById('amount');
        const loanTypeSelect = document.getElementById('loan_type_id');
        const checkBtn = document.querySelector('[onclick="checkEligibility()"]');

        if (amountInput && loanTypeSelect) {
            // Debounced eligibility checking
            let timeout;
            const checkEligibilityDebounced = () => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    this.performEligibilityCheck(memberId, amountInput.value, loanTypeSelect.value);
                }, 1000);
            };

            amountInput.addEventListener('input', checkEligibilityDebounced);
            loanTypeSelect.addEventListener('change', checkEligibilityDebounced);
        }

        // Enhanced check eligibility button
        if (checkBtn) {
            checkBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleEligibilityCheck();
            });
        }
    }

    /**
     * Setup loan calculator functionality
     */
    setupLoanCalculator() {
        const amountInput = document.getElementById('amount');
        const termSelect = document.getElementById('term_months');
        const loanTypeSelect = document.getElementById('loan_type_id');
        const monthlyPaymentDisplay = document.getElementById('monthly_payment_display');

        if (!amountInput || !termSelect || !loanTypeSelect || !monthlyPaymentDisplay) return;

        const calculatePayment = () => {
            const amount = parseFloat(amountInput.value) || 0;
            const termMonths = parseInt(termSelect.value) || 0;
            const selectedOption = loanTypeSelect.options[loanTypeSelect.selectedIndex];
            const annualRate = parseFloat(selectedOption.getAttribute('data-rate')) || 0;

            if (amount > 0 && termMonths > 0 && annualRate > 0) {
                const monthlyRate = annualRate / 100 / 12;
                const monthlyPayment = amount * (
                    monthlyRate * Math.pow(1 + monthlyRate, termMonths)
                ) / (Math.pow(1 + monthlyRate, termMonths) - 1);
                
                monthlyPaymentDisplay.value = this.formatCurrency(monthlyPayment);
                
                // Update additional loan details
                this.updateLoanDetails(amount, monthlyPayment, termMonths, annualRate);
            } else {
                monthlyPaymentDisplay.value = '';
                this.clearLoanDetails();
            }
        };

        amountInput.addEventListener('input', calculatePayment);
        termSelect.addEventListener('change', calculatePayment);
        loanTypeSelect.addEventListener('change', calculatePayment);

        // Initial calculation
        calculatePayment();
    }

    /**
     * Setup savings validation
     */
    setupSavingsValidator() {
        const savingsInputs = document.querySelectorAll('input[name*="savings"], input[name*="contribution"]');
        
        savingsInputs.forEach(input => {
            input.addEventListener('blur', (e) => {
                this.validateSavingsAmount(e.target);
            });
        });
    }

    /**
     * Perform eligibility check via AJAX
     */
    async performEligibilityCheck(memberId, amount, loanTypeId) {
        if (!memberId || !amount || !loanTypeId) return;

        const cacheKey = `${memberId}-${amount}-${loanTypeId}`;
        
        // Check cache first
        if (this.eligibilityCache.has(cacheKey)) {
            this.displayEligibilityResults(this.eligibilityCache.get(cacheKey));
            return;
        }

        const resultsContainer = document.getElementById('eligibilityResults');
        if (!resultsContainer) return;

        try {
            this.showLoadingState(resultsContainer);

            const response = await fetch(`${this.baseUrl}?action=check_eligibility&member_id=${memberId}&amount=${amount}&loan_type_id=${loanTypeId}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            // Cache the result
            this.eligibilityCache.set(cacheKey, data);
            
            this.displayEligibilityResults(data);
            
        } catch (error) {
            console.error('Eligibility check error:', error);
            this.showErrorState(resultsContainer, 'Failed to check eligibility. Please try again.');
        }
    }

    /**
     * Handle eligibility check button click
     */
    async handleEligibilityCheck() {
        const memberId = this.getMemberIdFromSession();
        const amount = document.getElementById('amount').value;
        const loanTypeId = document.getElementById('loan_type_id').value;

        if (!amount || !loanTypeId) {
            this.showAlert('Please fill in the loan amount and type first.', 'warning');
            return;
        }

        await this.performEligibilityCheck(memberId, amount, loanTypeId);
    }

    /**
     * Display eligibility check results
     */
    displayEligibilityResults(data) {
        const resultsContainer = document.getElementById('eligibilityResults');
        if (!resultsContainer) return;

        let html = '';

        if (data.eligible) {
            html = `
                <div class="alert alert-success border-0">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-check-circle fa-lg me-2"></i>
                        <strong>Eligible for Loan</strong>
                    </div>
                    <small>All requirements met</small>
                </div>
            `;

            if (data.loan_preview) {
                html += `
                    <div class="mt-3 p-3 bg-light rounded">
                        <h6><i class="fas fa-calculator me-2"></i>Loan Preview</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <small class="text-muted">Monthly Payment</small>
                                <div class="fw-bold">₦${this.formatNumber(data.loan_preview.monthly_payment)}</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Total Amount</small>
                                <div class="fw-bold">₦${this.formatNumber(data.loan_preview.total_amount)}</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Interest Rate</small>
                                <div class="fw-bold">${data.loan_preview.interest_rate}%</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Processing Fee</small>
                                <div class="fw-bold">₦${this.formatNumber(data.loan_preview.processing_fee)}</div>
                            </div>
                        </div>
                    </div>
                `;
            }

            if (data.credit_score) {
                html += `
                    <div class="mt-3 text-center">
                        <div class="badge bg-primary fs-6 p-2">
                            Credit Score: ${data.credit_score.score} (${data.credit_score.rating})
                        </div>
                    </div>
                `;
            }

            // Enable submit button
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-secondary');
                submitBtn.classList.add('btn-success');
            }

        } else {
            html = `
                <div class="alert alert-danger border-0">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-times-circle fa-lg me-2"></i>
                        <strong>Not Eligible</strong>
                    </div>
                    <ul class="mb-0 ps-3">
                        ${data.errors.map(error => `<li><small>${error}</small></li>`).join('')}
                    </ul>
                </div>
            `;

            // Disable submit button
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.remove('btn-success');
                submitBtn.classList.add('btn-secondary');
            }
        }

        resultsContainer.innerHTML = html;
    }

    /**
     * Update loan details display
     */
    updateLoanDetails(amount, monthlyPayment, termMonths, annualRate) {
        const processingFee = amount * 0.01;
        const totalAmount = (monthlyPayment * termMonths) + processingFee;
        const totalInterest = totalAmount - amount - processingFee;

        // Update any loan details display elements
        const detailsContainer = document.getElementById('loanDetailsPreview');
        if (detailsContainer) {
            detailsContainer.innerHTML = `
                <div class="row g-2">
                    <div class="col-6">
                        <small class="text-muted">Total Interest</small>
                        <div>₦${this.formatNumber(totalInterest)}</div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Processing Fee</small>
                        <div>₦${this.formatNumber(processingFee)}</div>
                    </div>
                    <div class="col-12">
                        <hr>
                        <small class="text-muted">Total to Repay</small>
                        <div class="h5">₦${this.formatNumber(totalAmount)}</div>
                    </div>
                </div>
            `;
        }
    }

    /**
     * Clear loan details display
     */
    clearLoanDetails() {
        const detailsContainer = document.getElementById('loanDetailsPreview');
        if (detailsContainer) {
            detailsContainer.innerHTML = '<p class="text-muted text-center py-3">Enter loan details to see preview</p>';
        }
    }

    /**
     * Validate savings amount
     */
    async validateSavingsAmount(input) {
        const amount = parseFloat(input.value) || 0;
        const type = input.getAttribute('data-savings-type') || 'voluntary';

        if (amount <= 0) return;

        try {
            const response = await fetch('/controllers/enhanced_savings_controller.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'validate_savings',
                    amount: amount,
                    type: type
                })
            });

            const data = await response.json();
            
            if (data.valid) {
                this.showInputSuccess(input);
            } else {
                this.showInputError(input, data.errors.join(', '));
            }
        } catch (error) {
            console.error('Savings validation error:', error);
        }
    }

    /**
     * Show loading state
     */
    showLoadingState(container) {
        container.innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 mb-0">Checking eligibility...</p>
            </div>
        `;
    }

    /**
     * Show error state
     */
    showErrorState(container, message) {
        container.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
            </div>
        `;
    }

    /**
     * Show input success state
     */
    showInputSuccess(input) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        this.removeInputFeedback(input);
    }

    /**
     * Show input error state
     */
    showInputError(input, message) {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        this.removeInputFeedback(input);
        
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = message;
        input.parentNode.appendChild(feedback);
    }

    /**
     * Remove input feedback
     */
    removeInputFeedback(input) {
        const feedback = input.parentNode.querySelector('.invalid-feedback, .valid-feedback');
        if (feedback) {
            feedback.remove();
        }
    }

    /**
     * Show alert message
     */
    showAlert(message, type = 'info') {
        const alertHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Insert at top of main content
        const mainContent = document.querySelector('.container-fluid .container, main, .main-content');
        if (mainContent) {
            mainContent.insertAdjacentHTML('afterbegin', alertHTML);
        }
    }

    /**
     * Get member ID from session/page
     */
    getMemberIdFromSession() {
        // Try to get from global variable first
        if (typeof MEMBER_ID !== 'undefined') {
            return MEMBER_ID;
        }
        
        // Try to extract from page elements
        const memberIdElement = document.querySelector('[data-member-id]');
        if (memberIdElement) {
            return memberIdElement.getAttribute('data-member-id');
        }
        
        // Try to get from URL or other sources
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('member_id') || null;
    }

    /**
     * Format currency for display
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: 'NGN',
            minimumFractionDigits: 2
        }).format(amount).replace('NGN', '').trim();
    }

    /**
     * Format number for display
     */
    formatNumber(amount) {
        return new Intl.NumberFormat('en-NG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }

    /**
     * Utility method to make API calls
     */
    async apiCall(endpoint, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };

        const finalOptions = { ...defaultOptions, ...options };
        
        try {
            const response = await fetch(endpoint, finalOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('API call error:', error);
            throw error;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize business rules integration
    window.csimsBR = new CSIMSBusinessRules();
    
    // Global functions for backward compatibility
    window.checkEligibility = function() {
        window.csimsBR.handleEligibilityCheck();
    };
    
    window.calculateMonthlyPayment = function() {
        // This function is handled automatically by the class
        console.log('Monthly payment calculation is now automatic');
    };
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CSIMSBusinessRules;
}