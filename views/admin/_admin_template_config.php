<?php
/**
 * CSIMS Admin Template Configuration Helper
 * 
 * This file contains configuration arrays and functions to help
 * quickly implement the admin template across different pages.
 */

class AdminTemplateConfig {
    
    /**
     * Common page configurations
     */
    public static function getPageConfigs() {
        return [
            'dashboard' => [
                'title' => 'Dashboard',
                'description' => 'Overview of system statistics and recent activities',
                'icon' => 'fas fa-tachometer-alt',
                'stats_enabled' => true,
                'filters_enabled' => false,
                'actions' => ['export', 'print']
            ],
            
            'members' => [
                'title' => 'Member Management',
                'description' => 'Manage cooperative members and their information',
                'icon' => 'fas fa-users',
                'stats_enabled' => true,
                'filters_enabled' => true,
                'actions' => ['add', 'import', 'export', 'print']
            ],
            
            'loans' => [
                'title' => 'Loan Management',
                'description' => 'Manage loan applications, approvals, and repayments',
                'icon' => 'fas fa-money-check-alt',
                'stats_enabled' => true,
                'filters_enabled' => true,
                'actions' => ['add', 'import', 'export', 'print']
            ],
            
            'savings' => [
                'title' => 'Savings Management',
                'description' => 'Track member savings accounts and transactions',
                'icon' => 'fas fa-piggy-bank',
                'stats_enabled' => true,
                'filters_enabled' => true,
                'actions' => ['add', 'import', 'export', 'print']
            ],
            
            'transactions' => [
                'title' => 'Transaction History',
                'description' => 'View and manage all financial transactions',
                'icon' => 'fas fa-exchange-alt',
                'stats_enabled' => true,
                'filters_enabled' => true,
                'actions' => ['export', 'print']
            ],
            
            'reports' => [
                'title' => 'Reports & Analytics',
                'description' => 'Generate comprehensive reports and analytics',
                'icon' => 'fas fa-chart-line',
                'stats_enabled' => false,
                'filters_enabled' => true,
                'actions' => ['export', 'print']
            ],
            
            'settings' => [
                'title' => 'System Settings',
                'description' => 'Configure system parameters and preferences',
                'icon' => 'fas fa-cog',
                'stats_enabled' => false,
                'filters_enabled' => false,
                'actions' => ['export']
            ],
            
            'users' => [
                'title' => 'User Management',
                'description' => 'Manage system users and their permissions',
                'icon' => 'fas fa-user-shield',
                'stats_enabled' => true,
                'filters_enabled' => true,
                'actions' => ['add', 'export', 'print']
            ],
            
            'audit' => [
                'title' => 'Audit Trail',
                'description' => 'View system activity logs and audit information',
                'icon' => 'fas fa-history',
                'stats_enabled' => true,
                'filters_enabled' => true,
                'actions' => ['export', 'print']
            ],
            
            'notifications' => [
                'title' => 'Notifications',
                'description' => 'Manage system notifications and alerts',
                'icon' => 'fas fa-bell',
                'stats_enabled' => true,
                'filters_enabled' => true,
                'actions' => ['add', 'export', 'print']
            ]
        ];
    }
    
    /**
     * Get configuration for a specific page
     */
    public static function getPageConfig($page_name) {
        $configs = self::getPageConfigs();
        return isset($configs[$page_name]) ? $configs[$page_name] : null;
    }
    
    /**
     * Generate action buttons HTML
     */
    public static function generateActionButtons($actions = []) {
        $buttons = '';
        $button_configs = [
            'add' => [
                'icon' => 'fas fa-plus',
                'text' => 'Add New',
                'class' => 'btn btn-primary',
                'onclick' => 'openAddModal()'
            ],
            'import' => [
                'icon' => 'fas fa-file-import',
                'text' => 'Import',
                'class' => 'btn btn-secondary',
                'onclick' => 'openImportModal()'
            ],
            'export' => [
                'icon' => 'fas fa-file-export',
                'text' => 'Export',
                'class' => 'btn btn-outline',
                'onclick' => 'exportData()'
            ],
            'print' => [
                'icon' => 'fas fa-print',
                'text' => 'Print',
                'class' => 'btn btn-outline',
                'onclick' => 'printData()'
            ]
        ];
        
        foreach ($actions as $action) {
            if (isset($button_configs[$action])) {
                $config = $button_configs[$action];
                $buttons .= sprintf(
                    '<button type="button" class="%s" onclick="%s">
                        <i class="%s mr-2"></i> %s
                    </button>',
                    $config['class'],
                    $config['onclick'],
                    $config['icon'],
                    $config['text']
                );
            }
        }
        
        return $buttons;
    }
    
    /**
     * Common statistics card configurations
     */
    public static function getStatsConfigs() {
        return [
            'members' => [
                'total' => [
                    'label' => 'Total Members',
                    'icon' => 'fas fa-users',
                    'color' => 'lapis-lazuli'
                ],
                'active' => [
                    'label' => 'Active Members',
                    'icon' => 'fas fa-user-check',
                    'color' => 'success'
                ],
                'new_this_month' => [
                    'label' => 'New This Month',
                    'icon' => 'fas fa-user-plus',
                    'color' => 'persian-orange'
                ],
                'pending_approval' => [
                    'label' => 'Pending Approval',
                    'icon' => 'fas fa-user-clock',
                    'color' => 'warning'
                ]
            ],
            
            'loans' => [
                'total' => [
                    'label' => 'Total Loans',
                    'icon' => 'fas fa-money-check-alt',
                    'color' => 'lapis-lazuli'
                ],
                'active' => [
                    'label' => 'Active Loans',
                    'icon' => 'fas fa-hand-holding-usd',
                    'color' => 'success'
                ],
                'pending' => [
                    'label' => 'Pending Applications',
                    'icon' => 'fas fa-clock',
                    'color' => 'warning'
                ],
                'overdue' => [
                    'label' => 'Overdue Payments',
                    'icon' => 'fas fa-exclamation-triangle',
                    'color' => 'error'
                ]
            ],
            
            'savings' => [
                'total_accounts' => [
                    'label' => 'Total Accounts',
                    'icon' => 'fas fa-piggy-bank',
                    'color' => 'lapis-lazuli'
                ],
                'total_balance' => [
                    'label' => 'Total Balance',
                    'icon' => 'fas fa-coins',
                    'color' => 'success'
                ],
                'this_month_deposits' => [
                    'label' => 'This Month Deposits',
                    'icon' => 'fas fa-arrow-up',
                    'color' => 'persian-orange'
                ],
                'this_month_withdrawals' => [
                    'label' => 'This Month Withdrawals',
                    'icon' => 'fas fa-arrow-down',
                    'color' => 'warning'
                ]
            ]
        ];
    }
    
    /**
     * Common filter configurations
     */
    public static function getFilterConfigs() {
        return [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => [
                    '' => 'All Statuses',
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                    'pending' => 'Pending',
                    'suspended' => 'Suspended'
                ]
            ],
            
            'date_range' => [
                'label' => 'Date Range',
                'type' => 'date_range',
                'from_name' => 'date_from',
                'to_name' => 'date_to'
            ],
            
            'amount_range' => [
                'label' => 'Amount Range',
                'type' => 'number_range',
                'from_name' => 'amount_from',
                'to_name' => 'amount_to'
            ],
            
            'member_type' => [
                'label' => 'Member Type',
                'type' => 'select',
                'options' => [
                    '' => 'All Types',
                    'regular' => 'Regular',
                    'premium' => 'Premium',
                    'corporate' => 'Corporate'
                ]
            ],
            
            'loan_type' => [
                'label' => 'Loan Type',
                'type' => 'select',
                'options' => [
                    '' => 'All Types',
                    'personal' => 'Personal',
                    'business' => 'Business',
                    'emergency' => 'Emergency',
                    'education' => 'Education'
                ]
            ]
        ];
    }
    
    /**
     * Generate filter form HTML
     */
    public static function generateFilterForm($filters = []) {
        $filter_configs = self::getFilterConfigs();
        $form_html = '';
        
        foreach ($filters as $filter_name) {
            if (isset($filter_configs[$filter_name])) {
                $config = $filter_configs[$filter_name];
                $form_html .= self::generateFilterField($filter_name, $config);
            }
        }
        
        return $form_html;
    }
    
    /**
     * Generate individual filter field
     */
    private static function generateFilterField($name, $config) {
        switch ($config['type']) {
            case 'select':
                $options_html = '';
                foreach ($config['options'] as $value => $label) {
                    $options_html .= "<option value=\"{$value}\">{$label}</option>";
                }
                return "
                <div>
                    <label for=\"{$name}\" class=\"form-label\">{$config['label']}</label>
                    <select class=\"form-control\" id=\"{$name}\" name=\"{$name}\">
                        {$options_html}
                    </select>
                </div>";
                
            case 'date_range':
                return "
                <div>
                    <label class=\"form-label\">{$config['label']}</label>
                    <div class=\"flex space-x-2\">
                        <input type=\"date\" class=\"form-control\" name=\"{$config['from_name']}\" placeholder=\"From\">
                        <input type=\"date\" class=\"form-control\" name=\"{$config['to_name']}\" placeholder=\"To\">
                    </div>
                </div>";
                
            case 'number_range':
                return "
                <div>
                    <label class=\"form-label\">{$config['label']}</label>
                    <div class=\"flex space-x-2\">
                        <input type=\"number\" class=\"form-control\" name=\"{$config['from_name']}\" placeholder=\"Min\">
                        <input type=\"number\" class=\"form-control\" name=\"{$config['to_name']}\" placeholder=\"Max\">
                    </div>
                </div>";
                
            default:
                return '';
        }
    }
    
    /**
     * Common JavaScript functions for admin pages
     */
    public static function getCommonJavaScript() {
        return '
        // Common utility functions for admin pages
        
        function showLoadingSpinner(element) {
            const original = element.innerHTML;
            element.innerHTML = \'<i class="fas fa-spinner fa-spin mr-2"></i> Loading...\';
            element.disabled = true;
            return original;
        }
        
        function hideLoadingSpinner(element, originalContent) {
            element.innerHTML = originalContent;
            element.disabled = false;
        }
        
        function showAlert(message, type = "success") {
            const alertClass = type === "success" ? "alert-success" : "alert-error";
            const iconClass = type === "success" ? "fas fa-check-circle" : "fas fa-exclamation-circle";
            const colorVar = type === "success" ? "--success" : "--error";
            
            const alertHtml = `
                <div class="${alertClass} flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="${iconClass} mr-3" style="color: var(${colorVar});"></i>
                        <span>${message}</span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.getElementById("mainContent").insertAdjacentHTML("afterbegin", alertHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const alert = document.querySelector(`.${alertClass}`);
                if (alert) {
                    alert.style.opacity = "0";
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        }
        
        function confirmDelete(message = "Are you sure you want to delete this item?") {
            return confirm(message);
        }
        
        function formatCurrency(amount) {
            return new Intl.NumberFormat("en-US", {
                style: "currency",
                currency: "USD"
            }).format(amount);
        }
        
        function formatDate(date) {
            return new Date(date).toLocaleDateString();
        }
        
        function validateForm(formId) {
            const form = document.getElementById(formId);
            return form.checkValidity();
        }
        ';
    }
}
?>