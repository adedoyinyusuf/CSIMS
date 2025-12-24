<?php
/**
 * Migration Investigation Script
 * 
 * This script checks which migrations are actually applied to the database
 * to help determine which duplicate files should be kept.
 */

// Include database configuration
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration Investigation Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            line-height: 1.6;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 2em; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .content { padding: 30px; }
        .section { 
            margin-bottom: 30px; 
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .section h2 { 
            color: #667eea; 
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        .section h3 { 
            color: #764ba2; 
            margin: 15px 0 10px 0;
            font-size: 1.2em;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0;
            background: white;
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd;
        }
        th { 
            background: #667eea; 
            color: white;
            font-weight: 600;
        }
        tr:hover { background-color: #f5f5f5; }
        .status { 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 0.85em;
            font-weight: 600;
            display: inline-block;
        }
        .status.exists { background: #d4edda; color: #155724; }
        .status.missing { background: #f8d7da; color: #721c24; }
        .status.warning { background: #fff3cd; color: #856404; }
        .code { 
            background: #2d2d2d; 
            color: #f8f8f2; 
            padding: 15px; 
            border-radius: 6px; 
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .recommendation {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .recommendation strong { color: #1976D2; }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #ddd;
            color: #666;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 10px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-card h4 {
            color: #667eea;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Migration Investigation Report</h1>
            <p>CSIMS Database Migration Analysis - <?php echo date('F d, Y H:i:s'); ?></p>
        </div>

        <div class="content">
            <?php
            // Initialize results array
            $results = [
                'tables' => [],
                'migration_007_version' => 'unknown',
                'migration_008_version' => 'unknown',
                'notifications_version' => 'unknown',
                'recommendations' => []
            ];

            try {
                // Test database connection
                if (!$conn) {
                    throw new Exception("Database connection failed");
                }

                echo '<div class="section">';
                echo '<h2>✅ Database Connection Status</h2>';
                echo '<p><span class="status exists">Connected to database: ' . DB_NAME . '</span></p>';
                echo '</div>';

                // Get all tables
                echo '<div class="section">';
                echo '<h2>📊 Database Tables Overview</h2>';
                
                $result = $conn->query("SHOW TABLES");
                $tables = [];
                while ($row = $result->fetch_array()) {
                    $tables[] = $row[0];
                }
                
                echo '<p>Total tables found: <strong>' . count($tables) . '</strong></p>';
                echo '<div class="code">';
                echo implode(', ', $tables);
                echo '</div>';
                $results['tables'] = $tables;
                echo '</div>';

                // Check Migration 007 - Enhanced Cooperative Schema
                echo '<div class="section">';
                echo '<h2>🔎 Investigation 1: Migration 007 - Enhanced Cooperative Schema</h2>';
                echo '<h3>Testing which version is applied...</h3>';
                
                $migration_007_indicators = [
                    'workflow_approvals table' => "SHOW TABLES LIKE 'workflow_approvals'",
                    'loan_guarantors table' => "SHOW TABLES LIKE 'loan_guarantors'",
                    'savings_accounts table' => "SHOW TABLES LIKE 'savings_accounts'",
                    'member_types table' => "SHOW TABLES LIKE 'member_types'",
                    'loan_types table' => "SHOW TABLES LIKE 'loan_types'"
                ];

                echo '<table>';
                echo '<tr><th>Expected Feature</th><th>Status</th><th>Notes</th></tr>';
                
                $schema_completeness = 0;
                foreach ($migration_007_indicators as $feature => $query) {
                    $check = $conn->query($query);
                    $exists = $check->num_rows > 0;
                    if ($exists) $schema_completeness++;
                    
                    echo '<tr>';
                    echo '<td>' . $feature . '</td>';
                    echo '<td><span class="status ' . ($exists ? 'exists' : 'missing') . '">' . 
                         ($exists ? 'EXISTS' : 'MISSING') . '</span></td>';
                    echo '<td>' . ($exists ? '✓ Table found' : '✗ Table not found') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';

                // Determine which version based on completeness
                if ($schema_completeness == 5) {
                    $results['migration_007_version'] = 'full';
                    echo '<div class="recommendation">';
                    echo '<strong>✅ FINDING:</strong> Full enhanced cooperative schema detected. ';
                    echo 'Keep <code>007_enhanced_cooperative_schema.sql</code>';
                    echo '</div>';
                } else if ($schema_completeness >= 3) {
                    $results['migration_007_version'] = 'partial';
                    echo '<div class="recommendation">';
                    echo '<strong>⚠️ FINDING:</strong> Partial schema detected. ';
                    echo 'Possibly using <code>007_enhanced_cooperative_schema_simple.sql</code>';
                    echo '</div>';
                } else {
                    $results['migration_007_version'] = 'minimal';
                    echo '<div class="recommendation">';
                    echo '<strong>⚠️ FINDING:</strong> Minimal schema detected. Migration may not have been fully applied.';
                    echo '</div>';
                }
                echo '</div>';

                // Check Migration 008 - System Config vs Member Fields
                echo '<div class="section">';
                echo '<h2>🔎 Investigation 2: Migration 008 - Conflict Resolution</h2>';
                echo '<h3>Determining which 008 migration was applied...</h3>';
                
                echo '<table>';
                echo '<tr><th>Migration Purpose</th><th>Key Table</th><th>Status</th><th>Verdict</th></tr>';
                
                // Check system_config table
                $system_config_exists = $conn->query("SHOW TABLES LIKE 'system_config'")->num_rows > 0;
                echo '<tr>';
                echo '<td>008_create_system_config.sql</td>';
                echo '<td>system_config</td>';
                echo '<td><span class="status ' . ($system_config_exists ? 'exists' : 'missing') . '">' . 
                     ($system_config_exists ? 'EXISTS' : 'MISSING') . '</span></td>';
                echo '<td>' . ($system_config_exists ? '✅ Applied' : '❌ Not applied') . '</td>';
                echo '</tr>';

                // Check member extra loan fields
                $member_loan_fields = false;
                if (in_array('members', $tables)) {
                    $member_cols = $conn->query("SHOW COLUMNS FROM members LIKE '%loan%'");
                    $member_loan_fields = $member_cols->num_rows > 0;
                }
                
                echo '<tr>';
                echo '<td>008_add_member_extra_loan_fields.sql</td>';
                echo '<td>members (loan columns)</td>';
                echo '<td><span class="status ' . ($member_loan_fields ? 'exists' : 'missing') . '">' . 
                     ($member_loan_fields ? 'EXISTS' : 'MISSING') . '</span></td>';
                echo '<td>' . ($member_loan_fields ? '✅ Applied' : '❌ Not applied') . '</td>';
                echo '</tr>';
                echo '</table>';

                if ($system_config_exists && $member_loan_fields) {
                    echo '<div class="recommendation">';
                    echo '<strong>⚠️ CONFLICT DETECTED:</strong> Both migrations numbered 008 appear to be applied! ';
                    echo 'This confirms the numbering conflict. ';
                    echo '<br><strong>RECOMMENDATION:</strong> Renumber <code>008_add_member_extra_loan_fields.sql</code> to <code>011_add_member_extra_loan_fields.sql</code>';
                    echo '</div>';
                    $results['migration_008_version'] = 'both_applied';
                } else if ($system_config_exists) {
                    echo '<div class="recommendation">';
                    echo '<strong>✅ CLEAR:</strong> Keep <code>008_create_system_config.sql</code> as migration 008. ';
                    echo 'The member loan fields migration can be numbered differently.';
                    echo '</div>';
                    $results['migration_008_version'] = 'system_config';
                } else if ($member_loan_fields) {
                    echo '<div class="recommendation">';
                    echo '<strong>✅ CLEAR:</strong> The member loan fields migration is active. ';
                    echo 'Keep it and move system_config to different number if needed.';
                    echo '</div>';
                    $results['migration_008_version'] = 'member_fields';
                } else {
                    echo '<div class="recommendation">';
                    echo '<strong>⚠️ NEITHER APPLIED:</strong> Neither 008 migration appears to be applied yet.';
                    echo '</div>';
                    $results['migration_008_version'] = 'none';
                }
                echo '</div>';

                // Check Notification System
                echo '<div class="section">';
                echo '<h2>🔎 Investigation 3: Notification Triggers</h2>';
                
                $triggers_result = $conn->query("SHOW TRIGGERS LIKE '%notification%'");
                $trigger_count = $triggers_result->num_rows;
                
                echo '<p>Notification-related triggers found: <strong>' . $trigger_count . '</strong></p>';
                
                if ($trigger_count > 0) {
                    echo '<table>';
                    echo '<tr><th>Trigger Name</th><th>Table</th><th>Event</th><th>Timing</th></tr>';
                    while ($trigger = $triggers_result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . $trigger['Trigger'] . '</td>';
                        echo '<td>' . $trigger['Table'] . '</td>';
                        echo '<td>' . $trigger['Event'] . '</td>';
                        echo '<td>' . $trigger['Timing'] . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';

                    if ($trigger_count >= 5) {
                        echo '<div class="recommendation">';
                        echo '<strong>✅ FINDING:</strong> Complex trigger system detected. ';
                        echo 'Likely using <code>notification_triggers_schema.sql</code>';
                        echo '</div>';
                        $results['notifications_version'] = 'complex';
                    } else {
                        echo '<div class="recommendation">';
                        echo '<strong>✅ FINDING:</strong> Simple trigger system detected. ';
                        echo 'Likely using <code>notification_triggers_schema_simple.sql</code>';
                        echo '</div>';
                        $results['notifications_version'] = 'simple';
                    }
                } else {
                    echo '<p><span class="status warning">No notification triggers found</span></p>';
                    echo '<div class="recommendation">';
                    echo '<strong>ℹ️ INFO:</strong> Notification triggers may not be implemented yet.';
                    echo '</div>';
                    $results['notifications_version'] = 'none';
                }
                echo '</div>';

                // Check for migrations tracking table
                echo '<div class="section">';
                echo '<h2>📋 Migration Tracking System</h2>';
                
                $has_migrations_table = in_array('migrations', $tables);
                
                if ($has_migrations_table) {
                    echo '<p><span class="status exists">Migrations table EXISTS</span></p>';
                    
                    $migrations = $conn->query("SELECT * FROM migrations ORDER BY id");
                    if ($migrations->num_rows > 0) {
                        echo '<h3>Applied Migrations:</h3>';
                        echo '<table>';
                        echo '<tr><th>#</th><th>Migration Name</th><th>Batch</th><th>Executed At</th></tr>';
                        while ($migration = $migrations->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . $migration['id'] . '</td>';
                            echo '<td>' . ($migration['migration'] ?? 'N/A') . '</td>';
                            echo '<td>' . ($migration['batch'] ?? 'N/A') . '</td>';
                            echo '<td>' . ($migration['executed_at'] ?? 'N/A') . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    } else {
                        echo '<p><span class="status warning">Migrations table is empty</span></p>';
                    }
                } else {
                    echo '<p><span class="status missing">No migrations tracking table found</span></p>';
                    echo '<div class="recommendation">';
                    echo '<strong>💡 SUGGESTION:</strong> Consider implementing a migrations tracking table to prevent duplicate migrations.';
                    echo '</div>';
                }
                echo '</div>';

                // Check for unnumbered migrations
                echo '<div class="section">';
                echo '<h2>🔎 Investigation 4: Unnumbered Migrations</h2>';
                
                $unnumbered_checks = [
                    'add_admin_profile_fields' => [
                        'table' => 'admins',
                        'columns' => ['bio', 'profile_image', 'phone']
                    ],
                    'add_extended_member_fields' => [
                        'table' => 'members',
                        'columns' => ['marital_status', 'occupation', 'next_of_kin']
                    ],
                    'add_member_type_to_members' => [
                        'table' => 'members',
                        'columns' => ['member_type_id']
                    ]
                ];

                echo '<table>';
                echo '<tr><th>Migration File</th><th>Target Table</th><th>Status</th></tr>';
                
                foreach ($unnumbered_checks as $migration_name => $check) {
                    $table = $check['table'];
                    $applied = false;
                    
                    if (in_array($table, $tables)) {
                        foreach ($check['columns'] as $col) {
                            $col_check = $conn->query("SHOW COLUMNS FROM $table LIKE '$col'");
                            if ($col_check->num_rows > 0) {
                                $applied = true;
                                break;
                            }
                        }
                    }
                    
                    echo '<tr>';
                    echo '<td>' . $migration_name . '.sql</td>';
                    echo '<td>' . $table . '</td>';
                    echo '<td><span class="status ' . ($applied ? 'exists' : 'missing') . '">' . 
                         ($applied ? '✅ APPLIED' : '❌ NOT APPLIED') . '</span></td>';
                    echo '</tr>';
                }
                echo '</table>';

                echo '<div class="recommendation">';
                echo '<strong>📝 RECOMMENDATION:</strong> Number these migrations sequentially (e.g., 012, 013, 014) to maintain proper migration order.';
                echo '</div>';
                echo '</div>';

            } catch (Exception $e) {
                echo '<div class="section" style="border-left-color: #dc3545; background: #f8d7da;">';
                echo '<h2 style="color: #dc3545;">❌ Error</h2>';
                echo '<p style="color: #721c24;">' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
            ?>

            <!-- Final Recommendations Summary -->
            <div class="section" style="border-left-color: #28a745; background: #d4edda;">
                <h2 style="color: #28a745;">✅ Final Recommendations Summary</h2>
                
                <div class="summary-card">
                    <h4>Migration 007 - Enhanced Cooperative Schema</h4>
                    <?php if ($results['migration_007_version'] == 'full'): ?>
                        <p><strong>Action:</strong> KEEP <code>007_enhanced_cooperative_schema.sql</code></p>
                        <p><strong>Remove:</strong> Move <code>007_*_fixed.sql</code> and <code>007_*_simple.sql</code> to deprecated folder</p>
                    <?php elseif ($results['migration_007_version'] == 'partial'): ?>
                        <p><strong>Action:</strong> KEEP <code>007_enhanced_cooperative_schema_simple.sql</code></p>
                        <p><strong>Remove:</strong> Move other variants to deprecated folder</p>
                    <?php else: ?>
                        <p><strong>Action:</strong> ⚠️ Investigate further - compare file contents</p>
                    <?php endif; ?>
                </div>

                <div class="summary-card">
                    <h4>Migration 008 - Numbering Conflict</h4>
                    <?php if ($results['migration_008_version'] == 'both_applied'): ?>
                        <p><strong>Action:</strong> KEEP <code>008_create_system_config.sql</code> as migration 008</p>
                        <p><strong>Renumber:</strong> <code>008_add_member_extra_loan_fields.sql</code> → <code>011_add_member_extra_loan_fields.sql</code></p>
                        <p><strong>Remove:</strong> Move <code>008_*_fixed.sql</code> to deprecated folder</p>
                    <?php else: ?>
                        <p><strong>Action:</strong> Review and separate these migrations with different numbers</p>
                    <?php endif; ?>
                </div>

                <div class="summary-card">
                    <h4>Unnumbered Migrations</h4>
                    <p><strong>Action:</strong> Add sequential numbers:</p>
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <li><code>012_add_admin_profile_fields.sql</code></li>
                        <li><code>013_add_extended_member_fields.sql</code></li>
                        <li><code>014_add_member_type_to_members.sql</code></li>
                    </ul>
                </div>

                <div class="summary-card">
                    <h4>Next Steps</h4>
                    <ol style="margin-left: 20px; margin-top: 10px;">
                        <li>✅ Review this report and confirm findings</li>
                        <li>📦 Create <code>database/migrations/deprecated/</code> folder</li>
                        <li>🔄 Move duplicate files to deprecated folder</li>
                        <li>🔢 Renumber conflicting migrations</li>
                        <li>🧪 Test database setup with cleaned migrations</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Generated by CSIMS Migration Investigation Tool</p>
            <p>Report saved at: <?php echo __FILE__; ?></p>
            <p>For questions, refer to: <code>docs/MIGRATION_CLEANUP_ANALYSIS.md</code></p>
        </div>
    </div>
</body>
</html>
