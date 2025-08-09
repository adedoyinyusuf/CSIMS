<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access the members page');
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Initialize member controller
$memberController = new MemberController();

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$membership_type = isset($_GET['membership_type']) ? $_GET['membership_type'] : '';

// Get members with pagination
$result = $memberController->getAllMembers($page, $search, $status, $membership_type);
$members = $result['members'];
$pagination = $result['pagination'];
$total_pages = $pagination['total_pages'];
$total_members = $pagination['total_items'];

// Get membership types for filter
$membership_types = $memberController->getMembershipTypes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - <?php echo APP_NAME; ?></title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
</head>
<body class="bg-gray-50">
    <!-- Include Header/Navbar -->
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex">
        <!-- Include Sidebar -->
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="flex-1 md:ml-64 mt-16 p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Members</h1>
                    <p class="text-gray-600">Manage and view all cooperative society members</p>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <a href="add_member.php" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
                        <i class="fas fa-user-plus mr-2"></i> Add New Member
                    </a>
                    <button type="button" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors" onclick="openImportModal()">
                        <i class="fas fa-user-plus mr-2"></i> Import New Members
                    </button>
                    <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors btn-export-csv" data-table="membersTable">
                        <i class="fas fa-file-export mr-2"></i> Export
                    </button>
                    <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors btn-print">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                </div>
            </div>
                
            <!-- Flash Messages -->
            <?php if ($session->hasFlash('success')): ?>
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3 text-green-600"></i>
                        <?php echo $session->getFlash('success'); ?>
                    </div>
                    <button type="button" class="text-green-600 hover:text-green-800" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if ($session->hasFlash('error')): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-3 text-red-600"></i>
                        <?php echo $session->getFlash('error'); ?>
                    </div>
                    <button type="button" class="text-red-600 hover:text-red-800" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
                
            <!-- Filter and Search -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Filter Members</h3>
                </div>
                <div class="p-6">
                    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        <div class="lg:col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="search" name="search" placeholder="Name, Email, Phone, ID" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="Active" <?php echo ($status == 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo ($status == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Expired" <?php echo ($status == 'Expired') ? 'selected' : ''; ?>>Expired</option>
                            </select>
                        </div>
                        <div>
                            <label for="membership_type" class="block text-sm font-medium text-gray-700 mb-2">Membership Type</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="membership_type" name="membership_type">
                                <option value="">All Types</option>
                                <?php foreach ($membership_types as $type): ?>
                                    <option value="<?php echo $type['membership_type_id']; ?>" <?php echo ($membership_type == $type['membership_type_id']) ? 'selected' : ''; ?>>
                                        <?php echo $type['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
                                <i class="fas fa-filter mr-2"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>
                
            <!-- Members Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Member List</h3>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-100 text-primary-800">
                        <?php echo $total_members; ?> Total Members
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full" id="membersTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Membership</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Join Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (count($members) > 0): ?>
                                <?php foreach ($members as $member): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo $member['member_id']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <?php if (!empty($member['photo'])): ?>
                                                    <?php 
                                                    // Handle different photo path formats
                                                    $photo_url = $member['photo'];
                                                    if (strpos($photo_url, 'assets/') === 0) {
                                                        // Photo path already includes assets/ directory
                                                        $photo_url = BASE_URL . '/' . $photo_url;
                                                    } else {
                                                        // Photo path is just filename, use default directory
                                                        $photo_url = BASE_URL . '/assets/images/members/' . $photo_url;
                                                    }
                                                    ?>
                                                    <img src="<?php echo $photo_url; ?>" alt="Profile" class="w-10 h-10 rounded-full mr-3">
                                                <?php else: ?>
                                                    <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                                        <i class="fas fa-user text-gray-600"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo $member['gender']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo $member['email']; ?></div>
                                            <div class="text-sm text-gray-500"><?php echo $member['phone']; ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?php echo $member['membership_type']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('M d, Y', strtotime($member['join_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div class="flex flex-col">
                                                <span><?php echo date('M d, Y', strtotime($member['expiry_date'])); ?></span>
                                                <?php 
                                                $expiry_date = strtotime($member['expiry_date']);
                                                $today = strtotime('today');
                                                $days_left = round(($expiry_date - $today) / (60 * 60 * 24));
                                                
                                                if ($days_left <= 0) {
                                                    echo '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 mt-1">Expired</span>';
                                                } elseif ($days_left <= 30) {
                                                    echo '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mt-1">' . $days_left . ' days left</span>';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($member['status'] == 'Active'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                            <?php elseif ($member['status'] == 'Inactive'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Inactive</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Expired</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <a href="<?php echo BASE_URL; ?>/views/admin/view_member.php?id=<?php echo $member['member_id']; ?>" class="inline-flex items-center px-2.5 py-1.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-lg hover:bg-blue-200 transition-colors" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>/views/admin/edit_member.php?id=<?php echo $member['member_id']; ?>" class="inline-flex items-center px-2.5 py-1.5 bg-indigo-100 text-indigo-700 text-xs font-medium rounded-lg hover:bg-indigo-200 transition-colors" title="Edit Member">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($days_left <= 0): ?>
                                                    <a href="<?php echo BASE_URL; ?>/views/admin/renew_membership.php?id=<?php echo $member['member_id']; ?>" class="inline-flex items-center px-2.5 py-1.5 bg-green-100 text-green-700 text-xs font-medium rounded-lg hover:bg-green-200 transition-colors" title="Renew Membership">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="<?php echo BASE_URL; ?>/views/admin/delete_member.php?id=<?php echo $member['member_id']; ?>" class="inline-flex items-center px-2.5 py-1.5 bg-red-100 text-red-700 text-xs font-medium rounded-lg hover:bg-red-200 transition-colors btn-delete" title="Delete Member">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-users text-3xl mb-2 text-gray-300"></i>
                                        <p>No members found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                        
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-gray-200">
                        <nav class="flex items-center justify-center" aria-label="Page navigation">
                            <div class="flex items-center space-x-1">
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&membership_type=<?php echo urlencode($membership_type); ?>" class="<?php echo ($page <= 1) ? 'pointer-events-none opacity-50' : ''; ?> inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors" aria-label="Previous">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&membership_type=<?php echo urlencode($membership_type); ?>" class="<?php echo ($page == $i) ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-500 border-gray-300 hover:bg-gray-50 hover:text-gray-700'; ?> inline-flex items-center px-3 py-2 text-sm font-medium border rounded-lg transition-colors">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&membership_type=<?php echo urlencode($membership_type); ?>" class="<?php echo ($page >= $total_pages) ? 'pointer-events-none opacity-50' : ''; ?> inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors" aria-label="Next">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
                
        </main>
    </div>
    
    <!-- Include Footer -->
    <?php include '../../views/includes/footer.php'; ?>
    
    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Import New Members</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeImportModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="importForm" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="importFile" class="block text-sm font-medium text-gray-700 mb-2">Select CSV File</label>
                        <input type="file" id="importFile" name="import_file" accept=".csv" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                        <p class="text-xs text-gray-500 mt-1">Only CSV files are supported. Maximum file size: 10MB</p>
                    </div>
                    
                    <!-- Hidden input for insert_only mode -->
                    <input type="hidden" id="importMode" name="import_mode" value="insert_only">
                    
                    <div class="mb-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                <span class="text-sm text-blue-800">This import will only create new member profiles and accounts. Existing members will be skipped.</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="createAccounts" name="create_accounts" value="true" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded" checked>
                            <label for="createAccounts" class="ml-2 block text-sm text-gray-700">Create login accounts for new members</label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Automatically generates username and password for each new member</p>
                    </div>
                    
                    <div class="mb-4" id="credentialsOptions" style="display: block;">
                        <div class="flex items-center">
                            <input type="checkbox" id="sendCredentials" name="send_credentials" value="true" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                            <label for="sendCredentials" class="ml-2 block text-sm text-gray-700">Send credentials via email</label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Email login details to new members automatically</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">CSV Format Requirements:</label>
                        <div class="text-xs text-gray-600 bg-gray-50 p-3 rounded">
                            <p class="font-medium mb-1">Required columns:</p>
                            <p>first_name, last_name, email</p>
                            <p class="font-medium mb-1 mt-2">Optional columns:</p>
                            <p>phone, gender, date_of_birth, address, membership_type_id, ippis_no, username, password</p>
                            <p class="mt-2"><strong>Note:</strong> First row should contain column headers</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeImportModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                            <i class="fas fa-user-plus mr-2"></i>Import New Members
                        </button>
                    </div>
                </form>
                
                <div id="importProgress" class="hidden mt-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-3"></div>
                            <span class="text-sm text-blue-800">Processing import...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" onerror="console.log('jQuery failed to load')"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js" onerror="console.log('DataTables failed to load')"></script>
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js" onerror="console.log('Custom script failed to load')"></script>
    
    <script>
        // Check if jQuery loaded successfully
        if (typeof $ !== 'undefined') {
            $(document).ready(function() {
                // Initialize DataTable with pagination disabled (we're using our own)
                $('#membersTable').DataTable({
                    "paging": false,
                    "ordering": true,
                    "info": false,
                    "searching": false
                });
            });
        } else {
            console.log('jQuery not available, skipping DataTable initialization');
        }
        
        // Import Modal Functions
        function openImportModal() {
            document.getElementById('importModal').classList.remove('hidden');
        }
        
        function closeImportModal() {
            document.getElementById('importModal').classList.add('hidden');
            document.getElementById('importForm').reset();
            document.getElementById('importProgress').classList.add('hidden');
        }
        
        // Show/hide credentials options based on create accounts checkbox
        document.getElementById('createAccounts').addEventListener('change', function() {
            const credentialsOptions = document.getElementById('credentialsOptions');
            if (this.checked) {
                credentialsOptions.style.display = 'block';
            } else {
                credentialsOptions.style.display = 'none';
                document.getElementById('sendCredentials').checked = false;
            }
        });
        
        // Handle Import Form Submission
        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('importFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a CSV file to import.');
                return;
            }
            
            // Show progress indicator
            document.getElementById('importProgress').classList.remove('hidden');
            
            // Create FormData object
            const formData = new FormData();
            formData.append('import_file', file);
            formData.append('import_mode', document.getElementById('importMode').value);
            formData.append('create_accounts', document.getElementById('createAccounts').checked ? 'true' : 'false');
            formData.append('send_credentials', document.getElementById('sendCredentials').checked ? 'true' : 'false');
            
            // Send AJAX request
            fetch('<?php echo BASE_URL; ?>/controllers/member_import_controller.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('importProgress').classList.add('hidden');
                
                if (data.success) {
                    let message = data.message;
                    
                    // Show credentials if not sent via email
                    if (data.credentials && data.credentials.length > 0) {
                        message += '\n\nNew Account Credentials:';
                        data.credentials.forEach(function(cred) {
                            message += '\n' + cred.name + ' - Username: ' + cred.username + ', Password: ' + cred.password;
                        });
                        message += '\n\nPlease save these credentials and share them securely with the members.';
                    }
                    
                    alert('Import completed successfully! ' + message);
                    closeImportModal();
                    location.reload(); // Refresh the page to show new/updated members
                } else {
                    alert('Import failed: ' + data.message);
                }
            })
            .catch(error => {
                document.getElementById('importProgress').classList.add('hidden');
                console.error('Error:', error);
                alert('An error occurred during import. Please try again.');
            });
        });
    </script>
</body>
</html>
