<?php

namespace CSIMS\Services;

/**
 * AuditLogger
 * 
 * Standalone audit logger for recording system activities across services.
 * Mirrors BaseController::logActivity behavior including size-based rotation.
 */
class AuditLogger
{
    /**
     * Log an activity entry to storage/logs/audit.log with rotation.
     *
     * @param string $action
     * @param string $entity
     * @param mixed $entityId
     * @param array $details
     */
    public function log(string $action, string $entity, $entityId = null, array $details = []): void
    {
        // Attempt to get current session user (admin or member)
        if (!isset($_SESSION)) {
            @session_start();
        }

        $user = $_SESSION['admin_user'] ?? $_SESSION['member_user'] ?? null;

        // Derive actor from details, with fallback to session user
        $actorFields = ['approved_by', 'rejected_by', 'disbursed_by', 'created_by', 'updated_by', 'performed_by'];
        $actorFromDetails = null;
        foreach ($actorFields as $field) {
            if (isset($details[$field]) && is_string($details[$field]) && trim($details[$field]) !== '') {
                $actorFromDetails = trim($details[$field]);
                break;
            }
        }

        $sessionActor = null;
        if (is_array($user)) {
            $sessionActor = $user['username']
                ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
                ?? null;
        }
        $actorName = $actorFromDetails ?: ($sessionActor ?: 'System');
        $actorSource = $actorFromDetails ? 'details' : 'session';

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => is_array($user) ? ($user['member_id'] ?? $user['admin_id'] ?? null) : null,
            'user_type' => (is_array($user) && isset($user['admin_id'])) ? 'admin' : ((is_array($user) && isset($user['member_id'])) ? 'member' : 'unknown'),
            'actor_name' => $actorName,
            'actor_source' => $actorSource,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        $basePath = dirname(__DIR__, 2);
        $logsDir = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0775, true);
        }
        $logFile = $logsDir . DIRECTORY_SEPARATOR . 'audit.log';

        // Rotate logs if exceeding max size (5 MB)
        $maxSizeBytes = 5 * 1024 * 1024; // 5 MB
        try {
            if (file_exists($logFile)) {
                $size = @filesize($logFile);
                if (is_int($size) && $size >= $maxSizeBytes) {
                    $timestamp = date('Ymd-His');
                    $archiveFile = $logsDir . DIRECTORY_SEPARATOR . 'audit-' . $timestamp . '.log';
                    @rename($logFile, $archiveFile);
                    // Seed new log with rotation marker
                    @file_put_contents($logFile, json_encode([
                        'timestamp' => date('Y-m-d H:i:s'),
                        'action' => 'log_rotated',
                        'entity' => 'audit',
                        'details' => ['from' => basename($archiveFile), 'max_size_bytes' => $maxSizeBytes]
                    ]) . PHP_EOL, FILE_APPEND | LOCK_EX);
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal; continue with normal append
        }

        $line = json_encode($logData) . PHP_EOL;
        if (@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('Activity Log (fallback): ' . $line);
        }
    }
}