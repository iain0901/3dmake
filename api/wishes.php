<?php
$db = new Database();
$conn = $db->getConnection();

switch ($method) {
    case 'GET':
        // 獲取許願列表或單個許願
        $wish_id = $_GET['id'] ?? null;
        
        if ($wish_id) {
            $stmt = $conn->prepare("
                SELECT w.*, u.email as user_email, u.nickname,
                (SELECT COUNT(*) FROM comments WHERE wish_id = w.wish_id) as comment_count
                FROM wishes w
                JOIN users u ON w.user_id = u.user_id
                WHERE w.wish_id = ?
            ");
            $stmt->execute([$wish_id]);
            $wish = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$wish) {
                http_response_code(404);
                echo json_encode(['error' => 'Wish not found']);
                exit;
            }
            
            echo json_encode($wish);
        } else {
            // 分頁和過濾
            $page = $_GET['page'] ?? 1;
            $per_page = $_GET['per_page'] ?? 20;
            $status = $_GET['status'] ?? '';
            
            $where = [];
            $params = [];
            
            if ($status) {
                $where[] = "w.status = ?";
                $params[] = $status;
            }
            
            $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            $stmt = $conn->prepare("
                SELECT w.*, u.email as user_email, u.nickname,
                (SELECT COUNT(*) FROM comments WHERE wish_id = w.wish_id) as comment_count
                FROM wishes w
                JOIN users u ON w.user_id = u.user_id
                $where_clause
                ORDER BY w.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $offset = ($page - 1) * $per_page;
            $params[] = (int)$per_page;
            $params[] = (int)$offset;
            
            $stmt->execute($params);
            $wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($wishes);
        }
        break;
        
    case 'POST':
        // 創建新許願
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['title']) || !isset($data['description'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO wishes (user_id, title, description, tags)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $data['title'],
            $data['description'],
            $data['tags'] ?? null
        ]);
        
        $wish_id = $conn->lastInsertId();
        
        echo json_encode(['id' => $wish_id, 'message' => 'Wish created successfully']);
        break;
        
    case 'PUT':
        // 更新許願
        $wish_id = $_GET['id'] ?? null;
        
        if (!$wish_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing wish ID']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 檢查權限
        $stmt = $conn->prepare("SELECT user_id FROM wishes WHERE wish_id = ?");
        $stmt->execute([$wish_id]);
        $wish_user_id = $stmt->fetchColumn();
        
        if ($wish_user_id != $user_id) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            exit;
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['title'])) {
            $updates[] = "title = ?";
            $params[] = $data['title'];
        }
        
        if (isset($data['description'])) {
            $updates[] = "description = ?";
            $params[] = $data['description'];
        }
        
        if (isset($data['tags'])) {
            $updates[] = "tags = ?";
            $params[] = $data['tags'];
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }
        
        $params[] = $wish_id;
        
        $stmt = $conn->prepare("
            UPDATE wishes 
            SET " . implode(", ", $updates) . "
            WHERE wish_id = ?
        ");
        
        $stmt->execute($params);
        
        echo json_encode(['message' => 'Wish updated successfully']);
        break;
        
    case 'DELETE':
        // 刪除許願
        $wish_id = $_GET['id'] ?? null;
        
        if (!$wish_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing wish ID']);
            exit;
        }
        
        // 檢查權限
        $stmt = $conn->prepare("SELECT user_id FROM wishes WHERE wish_id = ?");
        $stmt->execute([$wish_id]);
        $wish_user_id = $stmt->fetchColumn();
        
        if ($wish_user_id != $user_id) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            exit;
        }
        
        $conn->beginTransaction();
        
        try {
            // 刪除相關評論
            $stmt = $conn->prepare("DELETE FROM comments WHERE wish_id = ?");
            $stmt->execute([$wish_id]);
            
            // 刪除許願
            $stmt = $conn->prepare("DELETE FROM wishes WHERE wish_id = ?");
            $stmt->execute([$wish_id]);
            
            $conn->commit();
            
            echo json_encode(['message' => 'Wish deleted successfully']);
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
} 