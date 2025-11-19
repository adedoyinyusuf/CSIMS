<?php
require_once '../config/config.php';
require_once '../config/member_auth_check.php';
require_once '../config/database.php';
require_once '../controllers/loan_controller.php';
require_once '../controllers/member_controller.php';
// Check if member is logged in
// if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
//     header('Location: member_login.php');
//     exit();
// }

$loanController = new LoanController();
$memberController = new MemberController();

$member_id = $_SESSION['member_id'] ?? $_SESSION['user_id'];
$member = $memberController->getMemberById($member_id);

// Get member's loans
$loans = $loanController->getLoansByMemberId($member_id);

// Calculate totals
$total_loan_amount = 0;
$active_loans = 0;
$pending_loans = 0;

foreach ($loans as $loan) {
    if ($loan['status'] === 'Active') {
        $total_loan_amount += $loan['amount'];
        $active_loans++;
    } elseif ($loan['status'] === 'Pending') {
        $pending_loans++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Loans - NPC CTLStaff Loan Society</title>
    <!-- Assets centralized via includes/member_header.php -->
    <style>
        .sidebar {
            min-height: 100vh;
            background: #ffffff;
            box-shadow: 2px 0 10px rgba(0,0,0,0.06);
        }
        .sidebar .nav-link {
            color: var(--text-secondary) !important;
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: var(--text-primary) !important;
            background-color: var(--primary-50) !important;
        }
        /* Ensure any legacy white text is readable on white sidebar */
        .sidebar .text-white, .sidebar .text-white-50 { color: var(--text-secondary) !important; }
        .sidebar h4 { color: var(--text-primary); }
        .sidebar .fw-bold { color: var(--text-primary); }
        .card {
            background: var(--surface-primary);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            box-shadow: 0 4px 20px var(--shadow-sm);
        }
        .stat-card {
            background: #ffffff;
            border-top: 3px solid var(--member-primary);
            color: var(--text-primary);
        }
        .loan-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #cce7ff; color: #004085; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/member_header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-money-bill-wave me-2" style="color: var(--true-blue);"></i> My Loans</h2>
                        <a href="member_loan_application.php" class="btn btn-outline">
                            <i class="fas fa-plus me-2" style="color: var(--accent-color);"></i> Apply for New Loan
                        </a>
                    </div>
                    
                    <!-- Loan Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card card-member stat-card stat-card-standard">
                                <div class="card-body">
                                    <div class="stat-text">
                                        <p class="label">Total Loans</p>
                                        <p class="value">₦<?php echo number_format($total_loan_amount, 2); ?></p>
                                        <p class="sublabel">Active loans total</p>
                                    </div>
                                    <div class="icon-wrap" style="background: linear-gradient(135deg, var(--lapis-lazuli) 0%, var(--true-blue) 100%);">
                                        <i class="fas fa-hand-holding-usd text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-member stat-card stat-card-standard">
                                <div class="card-body">
                                    <div class="stat-text">
                                        <p class="label">Active Loans</p>
                                        <p class="value"><?php echo $active_loans; ?></p>
                                        <p class="sublabel">Currently active</p>
                                    </div>
                                    <div class="icon-wrap" style="background: linear-gradient(135deg, var(--persian-orange) 0%, var(--success) 100%);">
                                        <i class="fas fa-check-circle text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-member stat-card stat-card-standard">
                                <div class="card-body">
                                    <div class="stat-text">
                                        <p class="label">Pending Applications</p>
                                        <p class="value"><?php echo $pending_loans; ?></p>
                                        <p class="sublabel">Awaiting Review</p>
                                    </div>
                                    <div class="icon-wrap" style="background: linear-gradient(135deg, var(--persian-orange) 0%, var(--jasper) 100%);">
                                        <i class="fas fa-clock text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loans List -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Loan History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($loans)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-money-bill-wave fa-3x text-muted mb-3" style="color: var(--true-blue);"></i>
                                    <h5 class="text-muted">No loans found</h5>
                                    <p class="text-muted">You haven't applied for any loans yet.</p>
                                    <a href="member_loan_application.php" class="btn btn-standard btn-outline">
                                        <i class="fas fa-plus me-2" style="color: var(--accent-color);"></i> Apply for Your First Loan
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-standard">
                                        <thead>
                                            <tr>
                                                <th>Application Date</th>
                                                <th>Amount</th>
                                                <th>Purpose</th>
                                                <th>Term</th>
                                                <th>Interest Rate</th>
                                                <th>Monthly Payment</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($loans as $loan): ?>
                                                <?php
                                                // Calculate monthly payment
                                                $amount = floatval($loan['amount']);
                                                $termMonths = intval($loan['term']);
                                                $interestRate = floatval($loan['interest_rate']);
                                                
                                                if ($amount > 0 && $termMonths > 0 && $interestRate >= 0) {
                                                    $monthlyRate = $interestRate / 100 / 12;
                                                    if ($monthlyRate == 0) {
                                                        $monthlyPayment = $amount / $termMonths;
                                                    } else {
                                                        $monthlyPayment = ($amount * $monthlyRate * pow(1 + $monthlyRate, $termMonths)) / 
                                                                         (pow(1 + $monthlyRate, $termMonths) - 1);
                                                    }
                                                } else {
                                                    $monthlyPayment = 0;
                                                }
                                                ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($loan['application_date'])); ?></td>
                                                    <td>₦<?php echo number_format($loan['amount'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars($loan['purpose']); ?></td>
                                                    <td><?php echo $loan['term']; ?> months</td>
                                                    <td><?php echo $loan['interest_rate']; ?>%</td>
                                                    <td>₦<?php echo number_format($monthlyPayment, 2); ?></td>
                                                    <td>
                                                        <span class="loan-status status-<?php echo strtolower($loan['status']); ?>">
                                                            <?php echo $loan['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="member_loan_details.php?id=<?php echo $loan['loan_id']; ?>" 
                                                           class="btn btn-standard btn-sm btn-outline">
                                                            <i class="fas fa-eye" style="color: var(--true-blue);"></i> View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Filter & Search Loans -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <label for="memberLoanSearch" class="form-label">Search Loans</label>
                            <input type="text" class="form-control" id="memberLoanSearch" placeholder="Search by purpose, status or amount">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function(){
        const input = document.getElementById('memberLoanSearch');
        if (!input) return;
        input.addEventListener('input', function(){
            const query = this.value.trim().toLowerCase();
            const table = document.querySelector('.table');
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    })();
    </script>
</body>
</html>