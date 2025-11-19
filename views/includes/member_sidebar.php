<?php
$current = basename($_SERVER['PHP_SELF']);
$memberName = '';
if (isset($member) && is_array($member)) {
    $first = trim($member['first_name'] ?? '');
    $last = trim($member['last_name'] ?? '');
    $memberName = trim($first . ' ' . $last);
}
?>
<div class="d-flex flex-column flex-shrink-0 p-3" style="background:#ffffff; min-height:100vh; border-right:1px solid #e5e7eb;">
  <a href="member_dashboard.php" class="d-flex align-items-center mb-3 text-dark text-decoration-none">
    <i class="fas fa-university me-2"></i>
    <span class="fs-5 fw-bold">Member Portal</span>
  </a>
  <?php if (!empty($memberName)): ?>
  <div class="mb-3 text-muted">
    <small>Welcome,</small>
    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($memberName); ?></div>
  </div>
  <?php endif; ?>
  <hr>
  <ul class="nav flex-column mb-auto">
    <li class="nav-item">
      <a href="member_dashboard.php" class="text-dark text-decoration-none py-2 px-3 rounded <?php echo $current === 'member_dashboard.php' ? 'bg-light fw-semibold' : ''; ?>">
        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
      </a>
    </li>
    <li class="nav-item">
      <a href="member_profile.php" class="text-dark text-decoration-none py-2 px-3 rounded <?php echo $current === 'member_profile.php' ? 'bg-light fw-semibold' : ''; ?>">
        <i class="fas fa-user me-2"></i> My Profile
      </a>
    </li>
    <li class="nav-item">
      <a href="member_loans.php" class="text-dark text-decoration-none py-2 px-3 rounded <?php echo $current === 'member_loans.php' ? 'bg-light fw-semibold' : ''; ?>">
        <i class="fas fa-money-bill-wave me-2"></i> My Loans
      </a>
    </li>
    <li class="nav-item">
      <a href="member_savings.php" class="text-dark text-decoration-none py-2 px-3 rounded <?php echo $current === 'member_savings.php' ? 'bg-light fw-semibold' : ''; ?>">
        <i class="fas fa-piggy-bank me-2"></i> My Savings
      </a>
    </li>
    <li class="nav-item">
      <a href="member_messages.php" class="text-dark text-decoration-none py-2 px-3 rounded <?php echo $current === 'member_messages.php' ? 'bg-light fw-semibold' : ''; ?>">
        <i class="fas fa-envelope me-2"></i> Messages
      </a>
    </li>
    <li class="nav-item">
      <a href="member_loan_application_business_rules.php" class="text-dark text-decoration-none py-2 px-3 rounded <?php echo $current === 'member_loan_application_business_rules.php' ? 'bg-light fw-semibold' : ''; ?>">
        <i class="fas fa-plus-circle me-2"></i> Apply for Loan
      </a>
    </li>
  </ul>
  <hr>
  <div class="mt-2">
    <a href="member_logout.php" class="btn btn-outline-danger w-100">
      <i class="fas fa-sign-out-alt me-2"></i> Logout
    </a>
  </div>
</div>