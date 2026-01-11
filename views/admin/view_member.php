<?php
// Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/config.php';
// Config loaded successfully
if (session_status() === PHP_SESSION_NONE) {
    // Session should have been started by config/session
}


require_once '../../config/security.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';
require_once '../../controllers/loan_controller.php';
require_once '../../src/autoload.php';
require_once '../../includes/utilities.php';
require_once '../../includes/session.php';

$session = Session::getInstance();

// FORCE DEBUG - Continue execution with full error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access this page');
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();


// Check if member ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $session->setFlash('error', 'Member ID is required');
    header("Location: members.php");
    exit();
}

$member_id = (int)$_GET['id'];


// Initialize controllers
$memberController = new MemberController();
$loanController = new LoanController();


// Get member details
$member = $memberController->getMemberById($member_id);


if (!$member) {
    $session->setFlash('error', 'Member not found');
    header("Location: members.php");
    exit();
}

// Calculate age from date of birth
$age = '';
if (!empty($member['date_of_birth'])) {
    $dob = new DateTime($member['date_of_birth']);
    $now = new DateTime();
    $interval = $now->diff($dob);
    $age = $interval->y;
}

// Calculate days until membership expiry
$now = new DateTime();
$days_until_expiry = null;
$is_expired = false;
if (!empty($member['expiry_date']) && strtotime($member['expiry_date']) !== false) {
    $expiry_date = new DateTime($member['expiry_date']);
    $days_until_expiry = $now->diff($expiry_date)->days;
    $is_expired = $now > $expiry_date;
}

// Initialize DB for direct queries
$database = Database::getInstance();
$conn = $database->getConnection();

// Member total savings balance
$member_total_savings = 0.0;
try {
    $stmt = $conn->prepare("SELECT SUM(balance) as total FROM savings_accounts WHERE member_id = ?");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $member_total_savings = (float)($row['total'] ?? 0);
    }
} catch (Exception $e) {
    error_log('Error fetching total savings: ' . $e->getMessage());
}

// Fetch savings transactions
$savings_transactions = [];
try {
    $stmt = $conn->prepare("
        SELECT st.*, sa.account_number 
        FROM savings_transactions st 
        LEFT JOIN savings_accounts sa ON st.account_id = sa.account_id 
        WHERE st.member_id = ? 
        ORDER BY st.transaction_date DESC, st.transaction_id DESC 
        LIMIT 5
    ");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $savings_transactions[] = $row;
    }
} catch (Exception $e) {
    error_log('Error fetching savings transactions: ' . $e->getMessage());
}

// Fetch member loans
$member_loans = [];
try {
    if (method_exists($loanController, 'getLoansByMemberId')) {
        $member_loans = $loanController->getLoansByMemberId($member_id) ?? [];
    } elseif (method_exists($loanController, 'getMemberLoans')) {
        $member_loans = $loanController->getMemberLoans($member_id) ?? [];
    }
    if (is_array($member_loans)) {
        $member_loans = array_slice($member_loans, 0, 5);
    } else {
        $member_loans = [];
    }
} catch (Throwable $e) {
    error_log('Error fetching member loans: ' . $e->getMessage());
    $member_loans = [];
}

// Calculate loan statistics
$member_loan_outstanding = 0.0;
$member_loan_paid_total = 0.0;
try {
    $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'amount_paid'");
    $has_amount_paid = $col && $col->num_rows > 0;
    
    if ($has_amount_paid) {
        $q = $conn->query("SELECT SUM(amount - amount_paid) AS total FROM loans WHERE member_id = {$member_id} AND LOWER(status) IN ('active','disbursed','approved')");
        if ($q) { $member_loan_outstanding = (float)($q->fetch_assoc()['total'] ?? 0); }
        
        $q = $conn->query("SELECT SUM(amount_paid) AS total FROM loans WHERE member_id = {$member_id}");
        if ($q) { $member_loan_paid_total = (float)($q->fetch_assoc()['total'] ?? 0); }
    }
} catch (Exception $e) { }

// Calculate loan progress helper
function calculateLoanProgress($loan) {
    $amount = (float)($loan['amount'] ?? 0);
    $paid = isset($loan['amount_paid']) ? (float)$loan['amount_paid'] : 0;
    if ($amount <= 0) return 0;
    return min(100, round(($paid / $amount) * 100));
}

// Stats Helpers
function getMembershipDuration($joinDate) {
    if (empty($joinDate)) return '0Y';
    $start = new DateTime($joinDate);
    $end = new DateTime();
    $diff = $end->diff($start);
    if ($diff->y > 0) return $diff->y . 'Y';
    return $diff->m . 'M';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Profile - <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></title>
    
    <!-- Dependencies -->
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Theme Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd',
                            300: '#7dd3fc', 400: '#38bdf8', 500: '#0ea5e9',
                            600: '#0284c7', 700: '#0369a1', 800: '#075985', 900: '#0c4a6e'
                        },
                        secondary: {
                            50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0',
                            300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b',
                            600: '#475569', 700: '#334155', 800: '#1e293b', 900: '#0f172a'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-secondary-50 text-secondary-900 font-sans antialiased">


    <!-- Sidebar & Header Layout Wrapper -->
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-secondary-50 md:ml-64 transition-all duration-300">
            <!-- Header -->
            <?php include '../../views/includes/header.php'; ?>

            <!-- Page Content -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                
                <!-- Profile Header Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-secondary-200 p-6 mb-8 relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-6 opacity-5">
                        <i class="fas fa-users text-9xl text-primary-900"></i>
                    </div>
                    
                    <div class="relative z-10 flex flex-col md:flex-row items-center md:items-start gap-6">
                        <!-- Avatar -->
                        <div class="relative group">
                            <div class="w-32 h-32 rounded-2xl bg-secondary-100 flex items-center justify-center border-4 border-white shadow-md overflow-hidden">
                                <?php if (!empty($member['photo'])): ?>
                                    <img src="<?php echo BASE_URL; ?>/assets/images/members/<?php echo $member['photo']; ?>" 
                                         alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span class="text-4xl font-bold text-secondary-400">
                                        <?php echo substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <!-- Status Badge -->
                            <div class="absolute -bottom-3 left-1/2 transform -translate-x-1/2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?php echo $member['status'] == 'Active' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-secondary-100 text-secondary-800 border border-secondary-200'; ?> shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 <?php echo $member['status'] == 'Active' ? 'bg-green-500' : 'bg-secondary-500'; ?>"></span>
                                    <?php echo ucfirst($member['status']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 text-center md:text-left mt-2">
                            <h1 class="text-3xl font-bold text-secondary-900 tracking-tight">
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                            </h1>
                            <p class="text-secondary-500 font-medium mb-4 flex items-center justify-center md:justify-start gap-2">
                                <span><?php echo htmlspecialchars($member['occupation'] ?? 'Member'); ?></span>
                                <span class="text-secondary-300">•</span>
                                <span class="text-primary-600"><?php echo htmlspecialchars($member['ippis_no'] ?? 'No IPPIS'); ?></span>
                            </p>
                            
                            <!-- Badges -->
                            <div class="flex flex-wrap items-center justify-center md:justify-start gap-3 text-sm">
                                <div class="bg-secondary-50 px-3 py-1 rounded-lg border border-secondary-200 text-secondary-600 flex items-center">
                                    <i class="fas fa-id-card mr-2 text-secondary-400"></i>
                                    ID: <?php echo $member['member_id']; ?>
                                </div>
                                <div class="bg-secondary-50 px-3 py-1 rounded-lg border border-secondary-200 text-secondary-600 flex items-center">
                                    <i class="fas fa-calendar mr-2 text-secondary-400"></i>
                                    Joined <?php echo !empty($member['join_date']) ? date('M Y', strtotime($member['join_date'])) : 'N/A'; ?>
                                </div>
                                <div class="bg-secondary-50 px-3 py-1 rounded-lg border border-secondary-200 text-secondary-600 flex items-center">
                                    <i class="fas fa-envelope mr-2 text-secondary-400"></i>
                                    <?php echo htmlspecialchars($member['email'] ?? 'No Email'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col sm:flex-row gap-3 mt-4 md:mt-2">
                            <a href="<?php echo BASE_URL; ?>/views/admin/members.php" class="inline-flex items-center px-4 py-2 bg-white border border-secondary-300 rounded-xl font-medium text-secondary-700 hover:bg-secondary-50 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <i class="fas fa-arrow-left mr-2"></i> Back
                            </a>
                            <a href="<?php echo BASE_URL; ?>/views/admin/edit_member.php?id=<?php echo $member_id; ?>" class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-xl font-medium text-white hover:bg-primary-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <i class="fas fa-edit mr-2"></i> Edit
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Savings -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-secondary-100 hover:shadow-md transition-shadow duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                                <i class="fas fa-wallet text-xl"></i>
                            </div>
                            <span class="text-xs font-semibold text-secondary-500 uppercase tracking-wider">Savings</span>
                        </div>
                        <h3 class="text-2xl font-bold text-secondary-900 mb-1">
                            ₦<?php echo number_format($member_total_savings, 2); ?>
                        </h3>
                        <p class="text-sm text-secondary-500">Total Balance</p>
                    </div>

                    <!-- Outstanding -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-secondary-100 hover:shadow-md transition-shadow duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                                <i class="fas fa-hand-holding-usd text-xl"></i>
                            </div>
                            <span class="text-xs font-semibold text-secondary-500 uppercase tracking-wider">Loans</span>
                        </div>
                        <h3 class="text-2xl font-bold text-secondary-900 mb-1">
                            ₦<?php echo number_format($member_loan_outstanding, 2); ?>
                        </h3>
                        <p class="text-sm text-secondary-500">Outstanding Principal</p>
                    </div>

                    <!-- Repaid -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-secondary-100 hover:shadow-md transition-shadow duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center text-green-600">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                            <span class="text-xs font-semibold text-secondary-500 uppercase tracking-wider">Repaid</span>
                        </div>
                        <h3 class="text-2xl font-bold text-secondary-900 mb-1">
                            ₦<?php echo number_format($member_loan_paid_total, 2); ?>
                        </h3>
                        <p class="text-sm text-secondary-500">Total Repaid</p>
                    </div>

                    <!-- Membership -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-secondary-100 hover:shadow-md transition-shadow duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600">
                                <i class="fas fa-crown text-xl"></i>
                            </div>
                            <span class="text-xs font-semibold text-secondary-500 uppercase tracking-wider">Tenure</span>
                        </div>
                        <h3 class="text-2xl font-bold text-secondary-900 mb-1">
                            <?php echo getMembershipDuration($member['join_date']); ?>
                        </h3>
                        <p class="text-sm text-secondary-500"><?php echo isset($member['member_type_label']) ? ucfirst($member['member_type_label']) : 'Standard'; ?></p>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Left Column (2/3) -->
                    <div class="lg:col-span-2 space-y-8">
                        
                        <!-- Personal Info -->
                        <section class="bg-white rounded-2xl shadow-sm border border-secondary-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-secondary-100 flex items-center justify-between bg-secondary-50/50">
                                <h2 class="text-lg font-bold text-secondary-900 flex items-center gap-2">
                                    <i class="fas fa-user-circle text-primary-500"></i> Personal Information
                                </h2>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Field Group -->
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-secondary-400 uppercase tracking-wider">Full Name</label>
                                        <p class="text-base font-medium text-secondary-900"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></p>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-secondary-400 uppercase tracking-wider">Gender</label>
                                        <p class="text-base font-medium text-secondary-900"><?php echo htmlspecialchars($member['gender'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-secondary-400 uppercase tracking-wider">Date of Birth</label>
                                        <p class="text-base font-medium text-secondary-900">
                                            <?php echo !empty($member['date_of_birth']) ? date('M d, Y', strtotime($member['date_of_birth'])) : 'N/A'; ?>
                                            <?php if ($age): ?><span class="text-secondary-400 text-sm">(<?php echo $age; ?> years)</span><?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-secondary-400 uppercase tracking-wider">IPPIS Number</label>
                                        <p class="text-base font-medium text-secondary-900 font-mono"><?php echo htmlspecialchars($member['ippis_no'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-secondary-400 uppercase tracking-wider">Phone</label>
                                        <p class="text-base font-medium text-secondary-900"><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-secondary-400 uppercase tracking-wider">Email</label>
                                        <p class="text-base font-medium text-secondary-900 break-words"><?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="md:col-span-2 space-y-1">
                                        <label class="text-xs font-semibold text-secondary-400 uppercase tracking-wider">Address</label>
                                        <p class="text-base font-medium text-secondary-900"><?php echo htmlspecialchars($member['address'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Recent Savings -->
                        <section class="bg-white rounded-2xl shadow-sm border border-secondary-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-secondary-100 flex items-center justify-between bg-secondary-50/50">
                                <h2 class="text-lg font-bold text-secondary-900 flex items-center gap-2">
                                    <i class="fas fa-history text-primary-500"></i> Recent Savings
                                </h2>
                                <a href="<?php echo BASE_URL; ?>/views/admin/savings_accounts.php?member_id=<?php echo $member_id; ?>" class="text-sm font-medium text-primary-600 hover:text-primary-700">View All</a>
                            </div>
                            <?php if (empty($savings_transactions)): ?>
                                <div class="p-8 text-center text-secondary-500">
                                    <i class="fas fa-inbox text-4xl mb-3 text-secondary-200"></i>
                                    <p>No transactions found</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm">
                                        <thead>
                                            <tr class="bg-secondary-50 text-secondary-500 uppercase tracking-wider text-xs border-b border-secondary-100">
                                                <th class="px-6 py-3 font-semibold">Date</th>
                                                <th class="px-6 py-3 font-semibold">Type</th>
                                                <th class="px-6 py-3 font-semibold text-right">Amount</th>
                                                <th class="px-6 py-3 font-semibold">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-secondary-100">
                                            <?php foreach ($savings_transactions as $tx): ?>
                                                <tr class="hover:bg-secondary-50/50 transition-colors">
                                                    <td class="px-6 py-4 text-secondary-900 whitespace-nowrap">
                                                        <?php echo date('M d, Y', strtotime($tx['transaction_date'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 text-secondary-700">
                                                        <?php echo ucfirst($tx['transaction_type']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 text-right font-medium <?php echo $tx['transaction_type'] == 'withdrawal' ? 'text-red-600' : 'text-green-600'; ?>">
                                                        <?php echo $tx['transaction_type'] == 'withdrawal' ? '-' : '+'; ?>₦<?php echo number_format($tx['amount'], 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                            Success
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </section>

                        <!-- Active Loans -->
                        <section class="bg-white rounded-2xl shadow-sm border border-secondary-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-secondary-100 flex items-center justify-between bg-secondary-50/50">
                                <h2 class="text-lg font-bold text-secondary-900 flex items-center gap-2">
                                    <i class="fas fa-file-invoice-dollar text-primary-500"></i> Active Loans
                                </h2>
                                <a href="<?php echo BASE_URL; ?>/views/admin/member_loans.php?member_id=<?php echo $member_id; ?>" class="text-sm font-medium text-primary-600 hover:text-primary-700">View All</a>
                            </div>
                            <?php if (empty($member_loans)): ?>
                                <div class="p-8 text-center text-secondary-500">
                                    <i class="fas fa-file-invoice text-4xl mb-3 text-secondary-200"></i>
                                    <p>No active loans</p>
                                </div>
                            <?php else: ?>
                                <div class="divide-y divide-secondary-100">
                                    <?php foreach ($member_loans as $loan): 
                                        $progress = calculateLoanProgress($loan);
                                    ?>
                                        <div class="p-6 hover:bg-secondary-50/50 transition-colors">
                                            <div class="flex justify-between items-start mb-3">
                                                <div>
                                                    <h4 class="text-base font-semibold text-secondary-900">₦<?php echo number_format($loan['amount'], 2); ?></h4>
                                                    <p class="text-sm text-secondary-500 mt-0.5"><?php echo htmlspecialchars($loan['purpose'] ?? 'Personal Loan'); ?></p>
                                                </div>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                                                    <?php echo ucfirst($loan['status']); ?>
                                                </span>
                                            </div>
                                            <div class="space-y-2">
                                                <div class="flex justify-between text-xs text-secondary-500 font-medium">
                                                    <span>Repayment Progress</span>
                                                    <span><?php echo $progress; ?>%</span>
                                                </div>
                                                <div class="w-full bg-secondary-100 rounded-full h-2 overflow-hidden">
                                                    <div class="bg-primary-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo $progress; ?>%"></div>
                                                </div>
                                                <div class="flex justify-between text-xs text-secondary-400 mt-1">
                                                    <span>Paid: ₦<?php echo number_format($loan['amount_paid'] ?? 0, 2); ?></span>
                                                    <span>Balance: ₦<?php echo number_format(($loan['amount'] ?? 0) - ($loan['amount_paid'] ?? 0), 2); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>

                    </div>

                    <!-- Right Column (1/3) -->
                    <div class="space-y-6">
                        
                        <!-- Quick Actions -->
                        <div class="bg-white rounded-2xl shadow-sm border border-secondary-200 overflow-hidden">
                             <div class="px-6 py-4 border-b border-secondary-100 bg-secondary-50/50">
                                <h2 class="text-lg font-bold text-secondary-900">Quick Actions</h2>
                            </div>
                            <div class="p-4 space-y-3">
                                <a href="<?php echo BASE_URL; ?>/views/admin/add_loan.php?member_id=<?php echo $member_id; ?>" class="flex items-center justify-between p-3 rounded-xl bg-secondary-50 hover:bg-primary-50 border border-secondary-100 hover:border-primary-100 transition-all group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center text-primary-600 shadow-sm border border-secondary-100">
                                            <i class="fas fa-plus"></i>
                                        </div>
                                        <span class="font-medium text-secondary-700 group-hover:text-primary-700">New Loan</span>
                                    </div>
                                    <i class="fas fa-chevron-right text-secondary-300 group-hover:text-primary-400"></i>
                                </a>

                                <a href="<?php echo BASE_URL; ?>/views/admin/savings_accounts.php?member_id=<?php echo $member_id; ?>" class="flex items-center justify-between p-3 rounded-xl bg-secondary-50 hover:bg-primary-50 border border-secondary-100 hover:border-primary-100 transition-all group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center text-green-600 shadow-sm border border-secondary-100">
                                            <i class="fas fa-piggy-bank"></i>
                                        </div>
                                        <span class="font-medium text-secondary-700 group-hover:text-primary-700">Manage Savings</span>
                                    </div>
                                    <i class="fas fa-chevron-right text-secondary-300 group-hover:text-primary-400"></i>
                                </a>

                                <button type="button" data-bs-toggle="modal" data-bs-target="#resetPasswordModal" class="w-full flex items-center justify-between p-3 rounded-xl bg-secondary-50 hover:bg-primary-50 border border-secondary-100 hover:border-primary-100 transition-all group text-left">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center text-amber-600 shadow-sm border border-secondary-100">
                                            <i class="fas fa-key"></i>
                                        </div>
                                        <span class="font-medium text-secondary-700 group-hover:text-primary-700">Reset Password</span>
                                    </div>
                                    <i class="fas fa-chevron-right text-secondary-300 group-hover:text-primary-400"></i>
                                </button>
                                
                                <button onclick="window.print()" class="w-full flex items-center justify-between p-3 rounded-xl bg-secondary-50 hover:bg-primary-50 border border-secondary-100 hover:border-primary-100 transition-all group text-left">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center text-secondary-600 shadow-sm border border-secondary-100">
                                            <i class="fas fa-print"></i>
                                        </div>
                                        <span class="font-medium text-secondary-700 group-hover:text-primary-700">Print Profile</span>
                                    </div>
                                    <i class="fas fa-chevron-right text-secondary-300 group-hover:text-primary-400"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Membership Details -->
                        <div class="bg-white rounded-2xl shadow-sm border border-secondary-200 overflow-hidden">
                             <div class="px-6 py-4 border-b border-secondary-100 bg-secondary-50/50">
                                <h2 class="text-lg font-bold text-secondary-900">Membership</h2>
                            </div>
                            <div class="p-6 space-y-4">
                                <div>
                                    <label class="text-xs font-semibold text-secondary-400 uppercase tracking-wider block mb-1">Status</label>
                                    <?php if ($is_expired): ?>
                                        <span class="inline-flex items-center text-red-600 font-medium">
                                            <i class="fas fa-times-circle mr-2"></i> Expired
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center text-green-600 font-medium">
                                            <i class="fas fa-check-circle mr-2"></i> Active
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-secondary-400 uppercase tracking-wider block mb-1">Joined</label>
                                    <p class="text-base font-medium text-secondary-900"><?php echo !empty($member['join_date']) ? date('M d, Y', strtotime($member['join_date'])) : 'N/A'; ?></p>
                                </div>
                                <?php if (!empty($member['expiry_date'])): ?>
                                    <div>
                                        <label class="text-xs font-semibold text-secondary-400 uppercase tracking-wider block mb-1">Expires</label>
                                        <p class="text-base font-medium text-secondary-900"><?php echo date('M d, Y', strtotime($member['expiry_date'])); ?></p>
                                        <?php if ($days_until_expiry !== null && !$is_expired && $days_until_expiry <= 30): ?>
                                            <p class="text-xs text-amber-600 mt-1"><i class="fas fa-exclamation-triangle"></i> Renew soon</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Password Reset Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true" style="display:none;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-2xl border-0 shadow-xl overflow-hidden">
                <div class="bg-primary-600 px-6 py-4 border-b border-primary-500 flex justify-between items-center text-white">
                    <h5 class="font-bold text-lg"><i class="fas fa-key mr-2"></i> Reset Password</h5>
                    <button type="button" class="text-white hover:text-primary-100" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
                </div>
                <form action="<?php echo BASE_URL; ?>/views/admin/reset_member_password.php" method="POST">
                    <div class="p-6 bg-white">
                        <input type="hidden" name="csrf_token" value="<?php echo CSRFProtection::generateToken(); ?>">
                        <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                        
                        <p class="text-secondary-600 mb-4">Resetting password for <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>.</p>
                        
                        <div class="flex items-center gap-3 p-3 bg-secondary-50 rounded-xl border border-secondary-200">
                             <input type="checkbox" id="generate_password" name="generate_password" value="1" checked class="rounded border-secondary-300 text-primary-600 focus:ring-primary-500 h-5 w-5">
                             <label for="generate_password" class="text-sm font-medium text-secondary-700">Auto-generate secure password</label>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-secondary-50 border-t border-secondary-100 flex justify-end gap-3">
                        <button type="button" class="px-4 py-2 border border-secondary-300 rounded-lg text-secondary-700 font-medium hover:bg-white transition-colors" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 rounded-lg text-white font-medium hover:bg-primary-700 transition-colors">Confirm Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS for Modal (Legacy support for logic) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
