<?php
require_once __DIR__ . '/../config/config.php';

$db = \Database::getInstance()->getConnection();

echo "Deleting existing loans to prepare for re-seeding with monthly payment data...\n\n";

$result = $db->query("DELETE FROM loans");

if ($result) {
    echo "✓ All loans deleted successfully\n";
    echo "  Ready to re-seed with monthly payment data\n\n";
} else {
    echo "✗ ERROR: Failed to delete loans\n";
    echo "  Error: " . $db->error . "\n";
    exit(1);
}

echo "Run: php scripts/seed_loans.php\n";
?>
