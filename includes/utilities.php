<?php
class Utilities {
    // Enhanced sanitize input data
    public static function sanitizeInput($data, $type = 'string') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $data);
        }
        
        $data = trim($data);
        $data = stripslashes($data);
        
        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            case 'string':
            default:
                return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
        }
    }
    
    // Validate email
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    // Generate cryptographically secure random string
    public static function generateRandomString($length = 10, $type = 'alphanumeric') {
        switch ($type) {
            case 'alphanumeric':
                $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'alpha':
                $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'numeric':
                $characters = '0123456789';
                break;
            case 'hex':
                return bin2hex(random_bytes($length / 2));
            case 'base64':
                return base64_encode(random_bytes($length));
            default:
                $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        
        $randomString = '';
        $charactersLength = strlen($characters);
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
    
    // Generate secure token
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    // Validate and sanitize URL
    public static function validateUrl($url) {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            // Additional security: check for allowed protocols
            $allowedProtocols = ['http', 'https'];
            $protocol = parse_url($url, PHP_URL_SCHEME);
            if (in_array($protocol, $allowedProtocols)) {
                return $url;
            }
        }
        return false;
    }
    
    // Check if request is AJAX
    public static function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    // Get client IP address securely
    public static function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    // Format date
    public static function formatDate($date, $format = 'Y-m-d') {
        $dateObj = new DateTime($date);
        return $dateObj->format($format);
    }
    
    // Validate date
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    // Calculate date difference
    public static function dateDifference($date1, $date2, $format = '%a') {
        $datetime1 = new DateTime($date1);
        $datetime2 = new DateTime($date2);
        $interval = $datetime1->diff($datetime2);
        return $interval->format($format);
    }
    
    // Format currency
    public static function formatCurrency($amount, $symbol = '₦') {
        return $symbol . number_format($amount, 2);
    }
    
    // Enhanced secure file upload
    public static function uploadFile($file, $destination, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 2097152) {
        // Check if file was uploaded without errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            SecurityLogger::logSuspiciousActivity('File upload error', [
                'error_code' => $file['error'],
                'filename' => $file['name'] ?? 'unknown'
            ]);
            return ['success' => false, 'message' => 'Error uploading file.'];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            SecurityLogger::logSuspiciousActivity('File upload size exceeded', [
                'file_size' => $file['size'],
                'max_size' => $maxSize,
                'filename' => $file['name']
            ]);
            return ['success' => false, 'message' => 'File size exceeds limit.'];
        }
        
        // Enhanced file type validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($detectedType, $allowedTypes) || !in_array($file['type'], $allowedTypes)) {
            SecurityLogger::logSuspiciousActivity('Invalid file type upload attempt', [
                'detected_type' => $detectedType,
                'reported_type' => $file['type'],
                'filename' => $file['name'],
                'allowed_types' => $allowedTypes
            ]);
            return ['success' => false, 'message' => 'File type not allowed.'];
        }
        
        // Sanitize filename
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '', $originalName);
        
        // Generate unique filename
        $filename = time() . '_' . self::generateRandomString(8) . '_' . $sanitizedName . '.' . $extension;
        $targetFile = $destination . $filename;
        
        // Ensure destination directory exists and is secure
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            // Set secure file permissions
            chmod($targetFile, 0644);
            
            SecurityLogger::logSecurityEvent('File uploaded successfully', [
                'filename' => $filename,
                'original_name' => $file['name'],
                'size' => $file['size'],
                'type' => $detectedType
            ]);
            
            return ['success' => true, 'filename' => $filename, 'path' => $targetFile];
        } else {
            SecurityLogger::logSuspiciousActivity('File upload failed', [
                'filename' => $file['name'],
                'destination' => $destination
            ]);
            return ['success' => false, 'message' => 'Failed to move uploaded file.'];
        }
    }
    
    // Redirect to URL
    public static function redirect($url) {
        header("Location: $url");
        exit();
    }
    
    // Pagination
    public static function paginate($totalItems, $itemsPerPage = 10, $currentPage = 1) {
        $totalPages = ceil($totalItems / $itemsPerPage);
        $currentPage = max(1, min($currentPage, $totalPages));
        $offset = ($currentPage - 1) * $itemsPerPage;
        
        return [
            'total_items' => $totalItems,
            'items_per_page' => $itemsPerPage,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'offset' => $offset
        ];
    }
    
    // Generate pagination links
    public static function paginationLinks($pagination, $baseUrl) {
        $links = '';
        $totalPages = $pagination['total_pages'];
        $currentPage = $pagination['current_page'];
        
        if ($totalPages <= 1) {
            return '';
        }
        
        $links .= '<ul class="pagination">';
        
        // Previous link
        if ($currentPage > 1) {
            $links .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage - 1) . '">&laquo; Previous</a></li>';
        } else {
            $links .= '<li class="page-item disabled"><a class="page-link" href="#">&laquo; Previous</a></li>';
        }
        
        // Page links
        $startPage = max(1, $currentPage - 2);
        $endPage = min($totalPages, $currentPage + 2);
        
        if ($startPage > 1) {
            $links .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=1">1</a></li>';
            if ($startPage > 2) {
                $links .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
            }
        }
        
        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i == $currentPage) {
                $links .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
            } else {
                $links .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
            }
        }
        
        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                $links .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
            }
            $links .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $totalPages . '">' . $totalPages . '</a></li>';
        }
        
        // Next link
        if ($currentPage < $totalPages) {
            $links .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage + 1) . '">Next &raquo;</a></li>';
        } else {
            $links .= '<li class="page-item disabled"><a class="page-link" href="#">Next &raquo;</a></li>';
        }
        
        $links .= '</ul>';
        
        return $links;
    }
}
?>