<?php
/**
 * Migration Investigation CLI Script
 * Run: php scripts/investigate_migrations_cli.php
 */

require_once __DIR__ . '/../config/database.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║      CSIMS Migration Investigation Report                     ║\n";
echo "║      " . date('Y-m-d H:i:s') . "                                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

try {
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    echo "✅ Database Connection: SUCCESS (Database: " . DB_NAME . ")\n";
    echo str_repeat("─", 70) . "\n\n";

    // Get all tables
    echo "📊 SCANNING DATABASE TABLES...\n\n";
    $result = $conn->query("SHOW TABLES");
    $tables = [];
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    echo "   Found " . count($tables) . " tables\n";
    echo "   Tables: " . implode(", ", array_slice($tables, 0, 10));
    if (count($tables) > 10) echo "... (+" . (count($tables) - 10) . " more)";
    echo "\n\n";
    echo str_repeat("─", 70) . "\n\n";

    // Investigation 1: Migration 007
    echo "🔎 INVESTIGATION 1: Migration 007 - Enhanced Cooperative Schema\n\n";
    
    $migration_007_checks = [
        'workflow_approvals' => false,
        'loan_guarantors' => false,
        'savings_accounts' => false,
        'member_types' => false,
        'loan_types' => false
    ];

    foreach ($migration_007_checks as $table => $exists) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        $migration_007_checks[$table] = $check->num_rows > 0;
        $status = $migration_007_checks[$table] ? "✅ EXISTS" : "❌ MISSING";
        echo "   $status - $table\n";
    }

    $completeness = count(array_filter($migration_007_checks));
    echo "\n   Schema Completeness: $completeness/5 tables\n";
    
    if ($completeness == 5) {
        echo "\n   🎯 VERDICT: FULL enhanced schema detected\n";
        echo "   📝 ACTION: KEEP '007_enhanced_cooperative_schema.sql'\n";
        echo "   🗑️  REMOVE: Move _fixed and _simple versions to deprecated/\n";
    } elseif ($completeness >= 3) {
        echo "\n   ⚠️  VERDICT: PARTIAL schema detected\n";
        echo "   📝 ACTION: Likely using '007_enhanced_cooperative_schema_simple.sql'\n";
    } else {
        echo "\n   ⚠️  VERDICT: MINIMAL schema - migration may not be fully applied\n";
    }

    echo "\n" . str_repeat("─", 70) . "\n\n";

    // Investigation 2: Migration 008 Conflict
    echo "🔎 INVESTIGATION 2: Migration 008 - Conflict Resolution\n\n";

    $system_config_exists = $conn->query("SHOW TABLES LIKE 'system_config'")->num_rows > 0;
    echo "   " . ($system_config_exists ? "✅" : "❌") . " system_config table: " . 
         ($system_config_exists ? "EXISTS" : "MISSING") . "\n";

    $member_loan_fields = false;
    if (in_array('members', $tables)) {
        $member_cols = $conn->query("SHOW COLUMNS FROM members");
        $cols = [];
        while ($col = $member_cols->fetch_assoc()) {
            $cols[] = $col['Field'];
        }
        $member_loan_fields = count(array_filter($cols, function($c) { 
            return stripos($c, 'loan') !== false; 
        })) > 0;
    }
    echo "   " . ($member_loan_fields ? "✅" : "❌") . " member loan fields: " . 
         ($member_loan_fields ? "DETECTED" : "NOT FOUND") . "\n";

    if ($system_config_exists && $member_loan_fields) {
        echo "\n   ⚠️  VERDICT: BOTH migrations numbered 008 are applied!\n";
        echo "   📝 ACTION: Keep '008_create_system_config.sql' as #008\n";
        echo "   🔄 ACTION: Renumber '008_add_member_extra_loan_fields.sql' → '011_...'\n";
        echo "   🗑️  ACTION: Move '008_*_fixed.sql' to deprecated/\n";
    } elseif ($system_config_exists) {
        echo "\n   ✅ VERDICT: system_config migration is primary\n";
        echo "   📝 ACTION: Keep '008_create_system_config.sql'\n";
    }

    echo "\n" . str_repeat("─", 70) . "\n\n";

    // Investigation 3: Notification Triggers
    echo "🔎 INVESTIGATION 3: Notification Triggers\n\n";

    $triggers_result = $conn->query("SHOW TRIGGERS");
    $notification_triggers = [];
    while ($trigger = $triggers_result->fetch_assoc()) {
        if (stripos($trigger['Trigger'], 'notification') !== false) {
            $notification_triggers[] = $trigger['Trigger'];
        }
    }

    $trigger_count = count($notification_triggers);
    echo "   Found $trigger_count notification-related triggers\n";
    
    if ($trigger_count > 0) {
        echo "   Triggers: " . implode(", ", $notification_triggers) . "\n";
        
        if ($trigger_count >= 5) {
            echo "\n   🎯 VERDICT: Complex trigger system detected\n";
            echo "   📝 ACTION: Keep 'notification_triggers_schema.sql'\n";
        } else {
            echo "\n   🎯 VERDICT: Simple trigger system detected\n";
            echo "   📝 ACTION: Keep 'notification_triggers_schema_simple.sql'\n";
        }
    } else {
        echo "\n   ℹ️  VERDICT: No notification triggers found\n";
    }

    echo "\n" . str_repeat("─", 70) . "\n\n";

    // Investigation 4: Migration Tracking
    echo "🔎 INVESTIGATION 4: Migration Tracking System\n\n";

    if (in_array('migrations', $tables)) {
        echo "   ✅ migrations table EXISTS\n\n";
        
        $migrations = $conn->query("SELECT * FROM migrations ORDER BY id LIMIT 20");
        if ($migrations && $migrations->num_rows > 0) {
            echo "   Applied Migrations:\n";
            echo "   " . str_repeat("─", 60) . "\n";
            $count = 1;
            while ($migration = $migrations->fetch_assoc()) {
                $name = $migration['migration'] ?? $migration['filename'] ?? 'N/A';
                $executed = $migration['executed_at'] ?? $migration['created_at'] ?? 'N/A';
                echo "   " . str_pad($count++, 3) . ". " . str_pad($name, 40) . " [$executed]\n";
            }
            echo "   " . str_repeat("─", 60) . "\n";
        } else {
            echo "   ⚠️  migrations table is EMPTY\n";
        }
    } else {
        echo "   ❌ migrations table NOT FOUND\n";
        echo "\n   💡 SUGGESTION: Create a migrations tracking table\n";
    }

    echo "\n" . str_repeat("═", 70) . "\n\n";

    // Final Summary
    echo "✅ FINAL RECOMMENDATIONS SUMMARY\n\n";
    
    echo "1. CREATE FOLDER:\n";
    echo "   mkdir database/migrations/deprecated\n\n";

    echo "2. MOVE DUPLICATES TO DEPRECATED:\n";
    echo "   - database/migrations/007_enhanced_cooperative_schema_fixed.sql\n";
    echo "   - database/migrations/007_enhanced_cooperative_schema_simple.sql\n";
    echo "   - database/migrations/008_create_system_config_fixed.sql\n\n";

    if ($system_config_exists && $member_loan_fields) {
        echo "3. RENUMBER CONFLICT:\n";
        echo "   mv database/migrations/008_add_member_extra_loan_fields.sql \\\n";
        echo "      database/migrations/011_add_member_extra_loan_fields.sql\n\n";
    }

    echo "4. NUMBER UNNUMBERED MIGRATIONS:\n";
    echo "   - add_admin_profile_fields.sql → 012_add_admin_profile_fields.sql\n";
    echo "   - add_extended_member_fields.sql → 013_add_extended_member_fields.sql\n";
    echo "   - add_member_type_to_members.sql → 014_add_member_type_to_members.sql\n\n";

    echo str_repeat("═", 70) . "\n\n";
    echo "📄 Full analysis available in: docs/MIGRATION_CLEANUP_ANALYSIS.md\n";
    echo "🌐 Web report available at: http://localhost/CSIMS/investigate_migrations.php\n\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "✅ Investigation complete!\n\n";
