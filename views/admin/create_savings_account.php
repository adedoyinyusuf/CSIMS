<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../src/autoload.php';
require_once '../../includes/config/SystemConfigService.php';

// Auth check (admin only)
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Please login to continue';
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// DB connection
$database = Database::getInstance();
$conn = $database->getConnection();

// Load members for dropdown
$members = [];
try {
    $memberRepository = new \CSIMS\Repositories\MemberRepository($conn);
    $members = $memberRepository->findAll(['status' => 'Active'], ['first_name' => 'ASC']);
} catch (Exception $e) {
    error_log('Error loading members for savings account form: ' . $e->getMessage());
}

// Initialize SystemConfigService and derive default interest rate
$defaultSavingsInterest = '10.00';
try {
    $sysConfig = SystemConfigService::getInstance($conn ?? null);
    if ($sysConfig) {
        $defaultSavingsInterest = (string)$sysConfig->get('DEFAULT_INTEREST_RATE', (float)$defaultSavingsInterest);
    }
} catch (Exception $e) {
    error_log('create_savings_account: SystemConfigService init failed: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create Savings Account - <?php echo APP_NAME; ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include '../../views/includes/header.php'; ?>

    <div class="flex">
        <?php include '../../views/includes/sidebar.php'; ?>

        <main class="flex-1 md:ml-64 mt-16 p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Create Savings Account</h1>
                <p class="text-gray-600">Open a new savings account for a member</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <form method="POST" action="<?php echo BASE_URL; ?>/controllers/SavingsController.php?action=create_account" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Member</label>
                            <select name="member_id" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="">Select a member</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?php echo (int)$member->getMemberId(); ?>">
                                        <?php echo htmlspecialchars($member->getFirstName() . ' ' . $member->getLastName()); ?> (ID: <?php echo (int)$member->getMemberId(); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Account Type</label>
                            <select name="account_type" id="account_type" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="Regular">Regular</option>
                                <option value="Target">Target</option>
                                <option value="Fixed">Fixed</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Account Name</label>
                            <input type="text" name="account_name" id="account_name" class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="Regular Savings" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Opening Balance (₦)</label>
                            <input type="number" step="0.01" name="opening_balance" class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="0.00" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Balance (₦)</label>
                            <input type="number" step="0.01" name="minimum_balance" class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="0.00" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Interest Rate (%)</label>
                            <input type="number" step="0.01" name="interest_rate" class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="0.00" value="<?php echo isset($_POST['interest_rate']) ? htmlspecialchars($_POST['interest_rate']) : htmlspecialchars($defaultSavingsInterest); ?>" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Interest Calculation</label>
                            <select name="interest_calculation" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="monthly">Monthly</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>

                        <div id="target_fields" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Target Amount (₦)</label>
                            <input type="number" step="0.01" name="target_amount" class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="0.00" />
                            <label class="block text-sm font-medium text-gray-700 mb-2 mt-4">Monthly Target (₦)</label>
                            <input type="number" step="0.01" name="monthly_target" class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="0.00" />
                        </div>

                        <div id="fixed_fields" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maturity Date</label>
                            <input type="date" name="maturity_date" class="w-full border border-gray-300 rounded-md px-3 py-2" />
                        </div>

                        <div class="md:col-span-2 flex items-center">
                            <input type="checkbox" id="auto_deduct" name="auto_deduct" class="mr-2" />
                            <label for="auto_deduct" class="text-sm text-gray-700">Enable auto deduction</label>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="Optional notes"></textarea>
                        </div>
                    </div>

                    <div class="flex items-center space-x-3">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save mr-2"></i> Create Account
                        </button>
                        <a href="<?php echo BASE_URL; ?>/views/admin/savings_accounts.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-800 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Accounts
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
      const typeSelect = document.getElementById('account_type');
      const nameInput = document.getElementById('account_name');
      const targetFields = document.getElementById('target_fields');
      const fixedFields = document.getElementById('fixed_fields');

      function updateFields() {
        const type = typeSelect.value;
        nameInput.value = `${type.charAt(0).toUpperCase() + type.slice(1)} Savings`;
        targetFields.classList.toggle('hidden', type !== 'Target');
        fixedFields.classList.toggle('hidden', type !== 'Fixed');
      }

      typeSelect.addEventListener('change', updateFields);
      updateFields();
    </script>
</body>
</html>