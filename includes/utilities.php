<?php
class Utilities {
    // Sanitize input data
    public static function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
    
    // Validate email
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    // Generate random string
    public static function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
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
    
    // Upload file
    public static function uploadFile($file, $destination, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 2097152) {
        // Check if file was uploaded without errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error uploading file.'];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File size exceeds limit.'];
        }
        
        // Check file type
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'message' => 'File type not allowed.'];
        }
        
        // Generate unique filename
        $filename = time() . '_' . self::generateRandomString(8) . '_' . basename($file['name']);
        $targetFile = $destination . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            return ['success' => true, 'filename' => $filename, 'path' => $targetFile];
        } else {
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