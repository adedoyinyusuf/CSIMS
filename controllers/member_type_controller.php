<?php
require_once __DIR__ . '/../config/database.php';

class MemberTypeController {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }

    public function getAllMemberTypes($page = 1, $limit = 10, $search = '') {
        $offset = ($page - 1) * $limit;
        
        $where = "WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $where .= " AND (mt.type_name LIKE ? OR mt.description LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= 'ss';
        }
        
        // Count total records
        $countSql = "SELECT COUNT(*) AS total FROM member_types mt $where";
        $countStmt = $this->conn->prepare($countSql);
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $total_records = $countRes ? ($countRes->fetch_assoc()['total'] ?? 0) : 0;
        $countStmt->close();
        
        // Get paginated results with member counts and normalized aliases
        $sql = "SELECT 
                    mt.type_id AS id,
                    mt.type_name AS name,
                    mt.description,
                    mt.created_at,
                    COUNT(m.member_id) AS member_count
                FROM member_types mt
                LEFT JOIN members m ON mt.type_id = m.member_type_id
                $where
                GROUP BY mt.type_id
                ORDER BY mt.created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        $types2 = $types . 'ii';
        $params2 = $params;
        $params2[] = $limit;
        $params2[] = $offset;
        $stmt->bind_param($types2, ...$params2);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $member_types = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $member_types[] = $row;
            }
        }
        $stmt->close();
        
        return [
            'member_types' => $member_types,
            'total_records' => $total_records,
            'total_pages' => $limit > 0 ? (int)ceil($total_records / $limit) : 0,
            'current_page' => $page
        ];
    }

    public function getMemberTypeById($id) {
        $sql = "SELECT 
                    mt.type_id AS id,
                    mt.type_name AS name,
                    mt.description,
                    mt.created_at,
                    COUNT(m.member_id) AS member_count
                FROM member_types mt
                LEFT JOIN members m ON mt.type_id = m.member_type_id
                WHERE mt.type_id = ?
                GROUP BY mt.type_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $member_type = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $member_type;
    }

    public function createMemberType($dataOrName, $description = null) {
        $name = is_array($dataOrName) ? ($dataOrName['name'] ?? '') : $dataOrName;
        $desc = is_array($dataOrName) ? ($dataOrName['description'] ?? '') : ($description ?? '');
        $sql = "INSERT INTO member_types (type_name, description) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $name, $desc);
        $ok = $stmt->execute();
        $newId = $this->conn->insert_id;
        $stmt->close();
        return $ok ? $newId : false;
    }

    public function updateMemberType($id, $dataOrName, $description = null) {
        $name = is_array($dataOrName) ? ($dataOrName['name'] ?? '') : $dataOrName;
        $desc = is_array($dataOrName) ? ($dataOrName['description'] ?? '') : ($description ?? '');
        $sql = "UPDATE member_types SET type_name = ?, description = ? WHERE type_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ssi', $name, $desc, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteMemberType($id) {
        // Prevent deletion if members reference this type
        $checkSql = "SELECT COUNT(*) AS cnt FROM members WHERE member_type_id = ?";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bind_param('i', $id);
        $checkStmt->execute();
        $chkRes = $checkStmt->get_result();
        $cnt = $chkRes ? ($chkRes->fetch_assoc()['cnt'] ?? 0) : 0;
        $checkStmt->close();
        if ($cnt > 0) {
            return false;
        }
        
        $sql = "DELETE FROM member_types WHERE type_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}