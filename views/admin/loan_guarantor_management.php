<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../src/autoload.php';

// Only admins can access this page
checkAdminAuth();

// Get current page and search parameters
$page = (int)($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$loan_id = (int)($_GET['loan_id'] ?? 0);

$pageTitle = "Loan Guarantor Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - CSIMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <?php include '../includes/admin_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Page Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-handshake text-blue-600 mr-3"></i>
                        Loan Guarantor Management
                    </h1>
                    <p class="text-gray-600 mt-2">Manage loan guarantors and guarantee relationships</p>
                </div>
                <button 
                    onclick="openAddGuarantorModal()" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors"
                >
                    <i class="fas fa-plus mr-2"></i>Add Guarantor
                </button>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input 
                        type="text" 
                        id="searchInput"
                        placeholder="Search by member name, loan ID..." 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        value="<?= htmlspecialchars($search) ?>"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Loan ID</label>
                    <input 
                        type="number" 
                        id="loanIdFilter"
                        placeholder="Filter by loan ID" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        value="<?= $loan_id ? $loan_id : '' ?>"
                    >
                </div>
                <div class="flex items-end">
                    <button 
                        onclick="searchGuarantors()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors mr-2"
                    >
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    <button 
                        onclick="clearSearch()" 
                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md transition-colors"
                    >
                        <i class="fas fa-times mr-2"></i>Clear
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Guarantors</p>
                        <p class="text-2xl font-bold text-gray-900" id="totalGuarantors">-</p>
                    </div>
                    <i class="fas fa-users text-blue-500 text-xl"></i>
                </div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Active Guarantors</p>
                        <p class="text-2xl font-bold text-green-600" id="activeGuarantors">-</p>
                    </div>
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Guarantee Amount</p>
                        <p class="text-2xl font-bold text-blue-600" id="totalGuaranteeAmount">-</p>
                    </div>
                    <i class="fas fa-money-bill-wave text-blue-500 text-xl"></i>
                </div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Unique Guarantors</p>
                        <p class="text-2xl font-bold text-purple-600" id="uniqueGuarantors">-</p>
                    </div>
                    <i class="fas fa-user-friends text-purple-500 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Guarantors Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Loan Guarantors</h3>
            </div>
            
            <!-- Loading State -->
            <div id="loadingState" class="flex justify-center items-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <span class="ml-3 text-gray-600">Loading guarantors...</span>
            </div>

            <!-- Table -->
            <div id="guarantorsTable" class="hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Loan Details
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Guarantor
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Guarantee Amount
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody id="guarantorsTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Table rows will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div id="paginationSection" class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <!-- Pagination will be populated by JavaScript -->
                </div>
            </div>

            <!-- No Results State -->
            <div id="noResultsState" class="hidden py-8 text-center">
                <i class="fas fa-search text-gray-400 text-4xl mb-4"></i>
                <p class="text-gray-600">No guarantors found matching your criteria.</p>
            </div>
        </div>
    </div>

    <!-- Add/Edit Guarantor Modal -->
    <div id="guarantorModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Add Guarantor</h3>
                    <button onclick="closeGuarantorModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="guarantorForm" class="space-y-4">
                    <input type="hidden" id="guarantorId" name="guarantor_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="loanId" class="block text-sm font-medium text-gray-700">Loan ID *</label>
                            <select id="loanId" name="loan_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Loan</option>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                        </div>
                        
                        <div>
                            <label for="guarantorMemberId" class="block text-sm font-medium text-gray-700">Guarantor Member *</label>
                            <select id="guarantorMemberId" name="guarantor_member_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Member</option>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="guaranteeAmount" class="block text-sm font-medium text-gray-700">Guarantee Amount (₦)</label>
                            <input type="number" step="0.01" id="guaranteeAmount" name="guarantee_amount" min="0" 
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="guaranteePercentage" class="block text-sm font-medium text-gray-700">Guarantee Percentage (%)</label>
                            <input type="number" step="0.01" id="guaranteePercentage" name="guarantee_percentage" min="0" max="100" 
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="guarantorType" class="block text-sm font-medium text-gray-700">Guarantor Type *</label>
                            <select id="guarantorType" name="guarantor_type" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="Individual">Individual</option>
                                <option value="Corporate">Corporate</option>
                                <option value="Family">Family</option>
                                <option value="Group">Group</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="guarantorStatus" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="guarantorStatus" name="status" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Released">Released</option>
                                <option value="Defaulted">Defaulted</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label for="guarantorNotes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea id="guarantorNotes" name="notes" rows="3" 
                                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeGuarantorModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                            Save Guarantor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <div id="messageContainer" class="fixed top-4 right-4 z-50"></div>

    <script>
        // Global variables
        let currentPage = <?= $page ?>;
        let currentSearch = '<?= htmlspecialchars($search) ?>';
        let currentLoanId = <?= $loan_id ?>;
        let isEditMode = false;
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadStatistics();
            loadGuarantors();
            loadDropdownData();
        });
        
        // Load guarantor statistics
        async function loadStatistics() {
            try {
                const response = await fetch('/api/guarantor-statistics.php');
                const data = await response.json();
                
                if (data.success) {
                    const stats = data.data;
                    document.getElementById('totalGuarantors').textContent = stats.total_guarantors || '0';
                    document.getElementById('activeGuarantors').textContent = stats.active_guarantors || '0';
                    document.getElementById('totalGuaranteeAmount').textContent = '₦' + (stats.total_guarantee_amount ? parseFloat(stats.total_guarantee_amount).toLocaleString() : '0');
                    document.getElementById('uniqueGuarantors').textContent = stats.unique_guarantors || '0';
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }
        
        // Load guarantors list
        async function loadGuarantors() {
            showLoading();
            
            try {
                let url = `/api/guarantors.php?page=${currentPage}`;
                if (currentSearch) url += `&search=${encodeURIComponent(currentSearch)}`;
                if (currentLoanId) url += `&loan_id=${currentLoanId}`;
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    displayGuarantors(data.data);
                    displayPagination(data.meta.pagination);
                    showTable();
                } else {
                    showNoResults();
                }
            } catch (error) {
                console.error('Error loading guarantors:', error);
                showError('Failed to load guarantors. Please try again.');
                showNoResults();
            }
        }
        
        // Display guarantors in table
        function displayGuarantors(guarantors) {
            const tbody = document.getElementById('guarantorsTableBody');
            
            if (guarantors.length === 0) {
                showNoResults();
                return;
            }
            
            tbody.innerHTML = guarantors.map(guarantor => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div>
                            <div class="text-sm font-medium text-gray-900">Loan #${guarantor.loan_id}</div>
                            <div class="text-sm text-gray-500">₦${parseFloat(guarantor.loan_amount || 0).toLocaleString()}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div>
                            <div class="text-sm font-medium text-gray-900">${guarantor.guarantor_first_name} ${guarantor.guarantor_last_name}</div>
                            <div class="text-sm text-gray-500">${guarantor.guarantor_email || ''}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div>
                            ${guarantor.guarantee_amount > 0 ? 
                                `<div class="text-sm font-medium text-gray-900">₦${parseFloat(guarantor.guarantee_amount).toLocaleString()}</div>` : 
                                ''}
                            ${guarantor.guarantee_percentage > 0 ? 
                                `<div class="text-sm text-gray-500">${guarantor.guarantee_percentage}% of loan</div>` : 
                                ''}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            ${guarantor.guarantor_type}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(guarantor.status)}">
                            ${guarantor.status}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                        <button onclick="editGuarantor(${guarantor.guarantor_id})" 
                                class="text-blue-600 hover:text-blue-900 transition-colors">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteGuarantor(${guarantor.guarantor_id})" 
                                class="text-red-600 hover:text-red-900 transition-colors">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }
        
        // Get status color classes
        function getStatusColor(status) {
            switch (status.toLowerCase()) {
                case 'active': return 'bg-green-100 text-green-800';
                case 'inactive': return 'bg-gray-100 text-gray-800';
                case 'released': return 'bg-blue-100 text-blue-800';
                case 'defaulted': return 'bg-red-100 text-red-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }
        
        // Search functionality
        function searchGuarantors() {
            currentSearch = document.getElementById('searchInput').value;
            currentLoanId = parseInt(document.getElementById('loanIdFilter').value) || 0;
            currentPage = 1;
            loadGuarantors();
        }
        
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            document.getElementById('loanIdFilter').value = '';
            currentSearch = '';
            currentLoanId = 0;
            currentPage = 1;
            loadGuarantors();
        }
        
        // Modal functions
        function openAddGuarantorModal() {
            isEditMode = false;
            document.getElementById('modalTitle').textContent = 'Add Guarantor';
            document.getElementById('guarantorForm').reset();
            document.getElementById('guarantorId').value = '';
            document.getElementById('guarantorModal').classList.remove('hidden');
        }
        
        function closeGuarantorModal() {
            document.getElementById('guarantorModal').classList.add('hidden');
        }
        
        // Edit guarantor
        async function editGuarantor(guarantorId) {
            try {
                const response = await fetch(`/api/guarantors.php?id=${guarantorId}`);
                const data = await response.json();
                
                if (data.success) {
                    const guarantor = data.data;
                    isEditMode = true;
                    
                    document.getElementById('modalTitle').textContent = 'Edit Guarantor';
                    document.getElementById('guarantorId').value = guarantor.guarantor_id;
                    document.getElementById('loanId').value = guarantor.loan_id;
                    document.getElementById('guarantorMemberId').value = guarantor.guarantor_member_id;
                    document.getElementById('guaranteeAmount').value = guarantor.guarantee_amount;
                    document.getElementById('guaranteePercentage').value = guarantor.guarantee_percentage;
                    document.getElementById('guarantorType').value = guarantor.guarantor_type;
                    document.getElementById('guarantorStatus').value = guarantor.status;
                    document.getElementById('guarantorNotes').value = guarantor.notes || '';
                    
                    document.getElementById('guarantorModal').classList.remove('hidden');
                } else {
                    showError('Failed to load guarantor details');
                }
            } catch (error) {
                console.error('Error loading guarantor:', error);
                showError('Failed to load guarantor details');
            }
        }
        
        // Delete guarantor
        async function deleteGuarantor(guarantorId) {
            if (!confirm('Are you sure you want to remove this guarantor?')) {
                return;
            }
            
            try {
                const response = await fetch(`/api/guarantors.php?id=${guarantorId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Guarantor removed successfully');
                    loadGuarantors();
                    loadStatistics();
                } else {
                    showError(data.message || 'Failed to remove guarantor');
                }
            } catch (error) {
                console.error('Error deleting guarantor:', error);
                showError('Failed to remove guarantor');
            }
        }
        
        // Form submission
        document.getElementById('guarantorForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const guarantorData = Object.fromEntries(formData);
            
            try {
                const url = isEditMode ? `/api/guarantors.php?id=${guarantorData.guarantor_id}` : '/api/guarantors.php';
                const method = isEditMode ? 'PUT' : 'POST';
                
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(guarantorData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess(isEditMode ? 'Guarantor updated successfully' : 'Guarantor added successfully');
                    closeGuarantorModal();
                    loadGuarantors();
                    loadStatistics();
                } else {
                    showError(data.message || 'Failed to save guarantor');
                }
            } catch (error) {
                console.error('Error saving guarantor:', error);
                showError('Failed to save guarantor');
            }
        });
        
        // Utility functions
        function showLoading() {
            document.getElementById('loadingState').classList.remove('hidden');
            document.getElementById('guarantorsTable').classList.add('hidden');
            document.getElementById('noResultsState').classList.add('hidden');
        }
        
        function showTable() {
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('guarantorsTable').classList.remove('hidden');
            document.getElementById('noResultsState').classList.add('hidden');
        }
        
        function showNoResults() {
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('guarantorsTable').classList.add('hidden');
            document.getElementById('noResultsState').classList.remove('hidden');
        }
        
        function showSuccess(message) {
            showMessage(message, 'success');
        }
        
        function showError(message) {
            showMessage(message, 'error');
        }
        
        function showMessage(message, type) {
            const container = document.getElementById('messageContainer');
            const messageDiv = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            
            messageDiv.className = `${bgColor} text-white px-4 py-2 rounded-md mb-2 shadow-lg`;
            messageDiv.textContent = message;
            
            container.appendChild(messageDiv);
            
            setTimeout(() => {
                container.removeChild(messageDiv);
            }, 5000);
        }
        
        // Load dropdown data
        async function loadDropdownData() {
            // Load loans and members for dropdowns
            // This would typically load from your API endpoints
        }
        
        // Pagination
        function displayPagination(pagination) {
            const section = document.getElementById('paginationSection');
            if (pagination.total_pages <= 1) {
                section.innerHTML = '';
                return;
            }
            
            let paginationHtml = '<div class="flex items-center justify-between">';
            paginationHtml += `<div class="text-sm text-gray-700">
                Showing ${((pagination.current_page - 1) * pagination.per_page) + 1} to ${Math.min(pagination.current_page * pagination.per_page, pagination.total)} of ${pagination.total} results
            </div>`;
            
            paginationHtml += '<div class="flex space-x-2">';
            
            for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
                const isActive = i === pagination.current_page;
                paginationHtml += `<button onclick="goToPage(${i})" class="${isActive ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'} px-3 py-2 border border-gray-300 rounded-md text-sm">${i}</button>`;
            }
            
            paginationHtml += '</div></div>';
            section.innerHTML = paginationHtml;
        }
        
        function goToPage(page) {
            currentPage = page;
            loadGuarantors();
        }
        
        // Enter key search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchGuarantors();
            }
        });
        
        document.getElementById('loanIdFilter').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchGuarantors();
            }
        });
    </script>
</body>
</html>