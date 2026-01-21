<?php
// fix_sql_for_tidb.php
// Reads csims_db.sql and formats it for TiDB compatibility

$inputFile = __DIR__ . '/../docs/csims_db.sql';
$outputFile = __DIR__ . '/../docs/csims_production.sql';

if (!file_exists($inputFile)) {
    die("Input file not found: $inputFile\n");
}

$sql = file_get_contents($inputFile);

echo "Processing SQL file...\n";

// 1. Remove Trailing ALTER TABLE ... MODIFY ... AUTO_INCREMENT
$sql = preg_replace('/ALTER TABLE\s+`[^`]+`\s+MODIFY\s+`[^`]+`\s+.*?\s+AUTO_INCREMENT.*?;/si', '', $sql);
echo "Removed explicit ALTER AUTO_INCREMENT statements.\n";

// 2. Inject AUTO_INCREMENT into CREATE TABLE
$count = 0;
$sql = preg_replace_callback('/(CREATE TABLE\s+`[^`]+`\s+\(\s+`[^`]+`\s+int\([0-9]+\)\s+NOT NULL)(?!.*AUTO_INCREMENT)(,)/i', function($m) {
    return $m[1] . ' AUTO_INCREMENT' . $m[2];
}, $sql, -1, $count);
echo "Injected AUTO_INCREMENT into $count table definitions.\n";

// 3. Remove DELIMITER triggers (Broad match)
$sql = preg_replace('/DELIMITER\s+\$\$.*?DELIMITER\s+;/si', '', $sql);
echo "Removed DELIMITER triggers (Broad match).\n";

// 4. Downgrade FULLTEXT KEY to INDEX
$sql = str_replace(['FULLTEXT KEY', 'FULLTEXT INDEX'], 'INDEX', $sql);
echo "Downgraded FULLTEXT to INDEX.\n";

// 5. Fix INDEX KEY 
$sql = str_replace('INDEX KEY', 'INDEX', $sql);
echo "Fixed INDEX KEY syntax.\n";

// 6. Cleanup delimiters lines
$sql = preg_replace('/^DELIMITER\s+.*$/m', '', $sql);

// 7. Remove Comments
$sql = preg_replace('/^--.*$/m', '', $sql);
$sql = preg_replace('/\/\*!.*?\*\//s', '', $sql);
$sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

// 8. Fix Timestamp Syntax (current_timestamp() -> CURRENT_TIMESTAMP)
$sql = str_ireplace('current_timestamp()', 'CURRENT_TIMESTAMP', $sql);
echo "Fixed timestamp syntax.\n";

// 9. Remove Column Comments (Simplifies syntax)
// Matches COMMENT '...' (Handles escaped quotes badly but sufficient for standard dump)
$sql = preg_replace("/COMMENT\s+'[^']+'/i", "", $sql);
echo "Removed column comments.\n";

// 10. Remove CHECK constraints (Simple heuristic)
// Use specific match for JSON_VALID which is common
$sql = preg_replace("/CHECK\s*\(\s*json_valid\s*\(`[^`]+`\)\s*\)/i", "", $sql);
echo "Removed JSON CHECK constraints.\n";

// 11. Remove blank lines
$sql = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $sql);

// Write output
file_put_contents($outputFile, $sql);
echo "Done! Optimized file saved to: $outputFile\n";
?>
