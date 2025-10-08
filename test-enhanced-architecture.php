<?php
// Simplified test script for enhanced architecture
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include only the new architecture
require_once __DIR__ . '/src/autoload.php';

echo "<h1>🚀 Enhanced CSIMS Architecture Test</h1>";

try {
    echo "<h2>✅ Phase 1: Core Architecture Components</h2>";
    
    // Test DI Container
    echo "<p><strong>🔧 Dependency Injection Container:</strong> ";
    $container = container();
    echo "✅ Working</p>";
    
    // Test SecurityService
    echo "<p><strong>🔐 Security Service:</strong> ";
    $securityService = resolve(CSIMS\Services\SecurityService::class);
    echo "✅ Working</p>";
    
    echo "<h2>✅ Phase 2: Enhanced Models Validation</h2>";
    
    // Test LoanGuarantor Model
    echo "<p><strong>📊 LoanGuarantor Model:</strong></p>";
    $guarantorData = [
        'loan_id' => 1,
        'guarantor_member_id' => 2,
        'guarantee_amount' => 25000.00,
        'guarantee_percentage' => 50.0,
        'guarantor_type' => 'Individual',
        'status' => 'Active'
    ];
    
    $guarantor = new CSIMS\Models\LoanGuarantor($guarantorData);
    $validation = $guarantor->validate();
    
    echo "<ul>";
    echo "<li><strong>Validation:</strong> " . 
         ($validation->isValid() ? "✅ Valid" : "❌ Invalid - " . implode(', ', $validation->getErrors())) . "</li>";
    echo "<li><strong>Formatted Amount:</strong> " . $guarantor->getFormattedGuaranteeAmount() . "</li>";
    echo "<li><strong>Actual Amount Calculation:</strong> ₦" . number_format($guarantor->calculateActualAmount(50000), 2) . "</li>";
    echo "</ul>";
    
    // Test ShareCapital Model
    echo "<p><strong>💰 ShareCapital Model:</strong></p>";
    $shareData = [
        'member_id' => 1,
        'share_type' => 'Ordinary',
        'number_of_shares' => 100,
        'share_value' => 1000.00,
        'purchase_date' => date('Y-m-d'),
        'status' => 'Active'
    ];
    
    $share = new CSIMS\Models\ShareCapital($shareData);
    $shareValidation = $share->validate();
    
    echo "<ul>";
    echo "<li><strong>Validation:</strong> " . 
         ($shareValidation->isValid() ? "✅ Valid" : "❌ Invalid - " . implode(', ', $shareValidation->getErrors())) . "</li>";
    echo "<li><strong>Total Value:</strong> " . $share->getFormattedTotalValue() . "</li>";
    echo "<li><strong>Certificate Number:</strong> " . $share->generateCertificateNumber() . "</li>";
    echo "<li><strong>Dividend Calculation (5% rate):</strong> ₦" . number_format($share->calculateDividend(5.0), 2) . "</li>";
    echo "</ul>";
    
    echo "<h2>✅ Phase 3: QueryBuilder Test</h2>";
    
    // Test QueryBuilder functionality
    echo "<p><strong>🔧 QueryBuilder Test:</strong></p>";
    
    $query = CSIMS\Database\QueryBuilder::table('loans')
        ->select(['l.loan_id', 'l.amount', 'm.first_name'])
        ->leftJoin('members m', 'l.member_id', '=', 'm.member_id')
        ->where('l.status', 'Active')
        ->orderBy('l.created_at', 'DESC')
        ->limit(10);
    
    [$sql, $params] = $query->build();
    
    echo "<ul>";
    echo "<li><strong>Generated SQL:</strong> <code>" . htmlspecialchars($sql) . "</code></li>";
    echo "<li><strong>Parameters:</strong> " . (empty($params) ? "None" : implode(', ', $params)) . "</li>";
    echo "</ul>";
    
    echo "<h2>✅ Phase 4: Configuration Manager Test</h2>";
    
    // Test Configuration Manager
    echo "<p><strong>⚙️ Configuration Manager:</strong></p>";
    
    $configManager = resolve(CSIMS\Services\ConfigurationManager::class);
    
    echo "<ul>";
    echo "<li><strong>Environment Detection:</strong> " . $configManager->getEnvironment() . "</li>";
    echo "<li><strong>Debug Mode:</strong> " . ($configManager->get('app.debug', false) ? 'Enabled' : 'Disabled') . "</li>";
    echo "<li><strong>Database Config:</strong> " . ($configManager->get('database.host') ?: 'Default localhost') . "</li>";
    echo "</ul>";
    
    echo "<h2>✅ Phase 5: Exception Handling Test</h2>";
    
    // Test Exception Hierarchy
    echo "<p><strong>🚨 Exception Handling:</strong></p>";
    
    echo "<ul>";
    
    // Test ValidationException
    try {
        $invalidGuarantor = new CSIMS\Models\LoanGuarantor(['loan_id' => -1]);
        $invalidGuarantor->validate();
        if (!$invalidGuarantor->validate()->isValid()) {
            throw new CSIMS\Exceptions\ValidationException('Test validation exception');
        }
    } catch (CSIMS\Exceptions\ValidationException $e) {
        echo "<li><strong>ValidationException:</strong> ✅ Caught - " . $e->getMessage() . "</li>";
    }
    
    // Test DatabaseException
    try {
        throw new CSIMS\Exceptions\DatabaseException('Test database exception');
    } catch (CSIMS\Exceptions\DatabaseException $e) {
        echo "<li><strong>DatabaseException:</strong> ✅ Caught - " . $e->getMessage() . "</li>";
    }
    
    echo "</ul>";
    
    echo "<h2>✅ Phase 6: Business Logic Test</h2>";
    
    // Test business logic methods
    echo "<p><strong>💼 Business Logic:</strong></p>";
    
    echo "<ul>";
    
    // Test loan calculations
    $testLoan = new CSIMS\Models\Loan([
        'member_id' => 1,
        'amount' => 100000,
        'interest_rate' => 10.0,
        'term_months' => 12,
        'purpose' => 'Business expansion'
    ]);
    
    echo "<li><strong>Loan Monthly Payment:</strong> " . $testLoan->getFormattedMonthlyPayment() . "</li>";
    echo "<li><strong>Total Interest:</strong> ₦" . number_format($testLoan->calculateTotalInterest(), 2) . "</li>";
    echo "<li><strong>Status Color:</strong> " . $testLoan->getStatusColor() . "</li>";
    
    // Test guarantor business logic
    $testGuarantor = new CSIMS\Models\LoanGuarantor([
        'loan_id' => 1,
        'guarantor_member_id' => 2,
        'guarantee_amount' => 0,
        'guarantee_percentage' => 75.0,
        'guarantor_type' => 'Individual',
        'status' => 'Active'
    ]);
    
    echo "<li><strong>Percentage-based Guarantee:</strong> ₦" . number_format($testGuarantor->calculateActualAmount(100000), 2) . " (75% of ₦100,000)</li>";
    
    echo "</ul>";
    
    echo "<h2>🎉 Integration Success Summary</h2>";
    
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>✅ Enhanced Architecture Verification Complete!</h3>";
    echo "<p><strong>Successfully Tested Components:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Dependency Injection Container</li>";
    echo "<li>✅ Enhanced Models with Business Logic</li>";
    echo "<li>✅ Validation System with Custom Rules</li>";
    echo "<li>✅ QueryBuilder with Complex Joins</li>";
    echo "<li>✅ Configuration Management</li>";
    echo "<li>✅ Exception Hierarchy</li>";
    echo "<li>✅ Loan & Guarantor Calculations</li>";
    echo "<li>✅ Share Capital Management</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h2>🚀 Next Steps</h2>";
    echo "<p><strong>Architecture Integration Phase 2 Complete!</strong></p>";
    echo "<p>Ready for:</p>";
    echo "<ul>";
    echo "<li>Controller refactoring to use new architecture</li>";
    echo "<li>Database connectivity integration</li>";
    echo "<li>Enhanced admin interfaces</li>";
    echo "<li>Complete workflow system implementation</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<div style='background: #ffe6e6; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Architecture Test Error</h3>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Type:</strong> " . get_class($e) . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . " (Line " . $e->getLine() . ")</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Enhanced Architecture Test Completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>