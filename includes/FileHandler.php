<?php
class FileHandler {
    private $upload_dir;
    private $allowed_types;
    private $max_size;
    private $db;

    public function __construct($upload_dir = 'uploads', $allowed_types = null, $max_size = 5242880) {
        $this->upload_dir = rtrim($upload_dir, '/');
        $this->allowed_types = $allowed_types ?? [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif'
        ];
        $this->max_size = $max_size;
        $this->db = Database::getInstance();
        
        $this->initializeUploadDirectory();
    }

    private function initializeUploadDirectory() {
        if (!file_exists($this->upload_dir)) {
            if (!mkdir($this->upload_dir, 0755, true)) {
                throw new RuntimeException('無法創建上傳目錄');
            }
        }

        if (!is_writable($this->upload_dir)) {
            throw new RuntimeException('上傳目錄無寫入權限');
        }

        // 創建子目錄
        $subdirs = ['images', 'thumbnails', 'temp'];
        foreach ($subdirs as $dir) {
            $path = $this->upload_dir . '/' . $dir;
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    public function handleUpload($file, $user_id, $generate_thumbnail = true) {
        try {
            $this->validateFile($file);
            $mime_type = $this->validateFileType($file);
            $extension = $this->allowed_types[$mime_type];
            
            // 生成安全的檔案名
            $filename = $this->generateSecureFilename($extension);
            $filepath = $this->upload_dir . '/images/' . $filename;
            
            // 移動檔案
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new RuntimeException('無法移動上傳的檔案');
            }

            // 生成縮圖
            if ($generate_thumbnail) {
                $this->createThumbnail($filepath, $extension);
            }

            // 記錄到資料庫
            $this->recordFile($user_id, $filename, $file['name'], $mime_type, $file['size'], $filepath);

            return $filename;
        } catch (Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            throw $e;
        }
    }

    private function validateFile($file) {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid parameters');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('檔案大小超過限制');
            case UPLOAD_ERR_PARTIAL:
                throw new RuntimeException('檔案上傳不完整');
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('沒有檔案被上傳');
            default:
                throw new RuntimeException('未知錯誤');
        }

        if ($file['size'] > $this->max_size) {
            throw new RuntimeException('檔案大小超過限制');
        }
    }

    private function validateFileType($file) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mime_type, $this->allowed_types)) {
            throw new RuntimeException('不支援的檔案類型');
        }

        // 額外的圖片驗證
        if (strpos($mime_type, 'image/') === 0) {
            $image_info = @getimagesize($file['tmp_name']);
            if ($image_info === false) {
                throw new RuntimeException('無效的圖片檔案');
            }
        }

        return $mime_type;
    }

    private function generateSecureFilename($extension) {
        return sprintf('%s.%s',
            bin2hex(random_bytes(16)),
            $extension
        );
    }

    private function createThumbnail($filepath, $extension) {
        $thumbnail_path = str_replace('/images/', '/thumbnails/', $filepath);
        
        list($width, $height) = getimagesize($filepath);
        $new_width = 200;
        $new_height = floor($height * ($new_width / $width));
        
        $thumb = imagecreatetruecolor($new_width, $new_height);
        
        // 處理透明度
        if ($extension == 'png' || $extension == 'gif') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefilledrectangle($thumb, 0, 0, $new_width, $new_height, $transparent);
        }
        
        $source = $this->createImageResource($filepath, $extension);
        
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        $this->saveImage($thumb, $thumbnail_path, $extension);
        
        imagedestroy($thumb);
        imagedestroy($source);
    }

    private function createImageResource($filepath, $extension) {
        switch ($extension) {
            case 'jpg':
                return imagecreatefromjpeg($filepath);
            case 'png':
                $image = imagecreatefrompng($filepath);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                return $image;
            case 'gif':
                return imagecreatefromgif($filepath);
            default:
                throw new RuntimeException('不支援的圖片類型');
        }
    }

    private function saveImage($image, $filepath, $extension) {
        switch ($extension) {
            case 'jpg':
                imagejpeg($image, $filepath, 80);
                break;
            case 'png':
                imagepng($image, $filepath, 8);
                break;
            case 'gif':
                imagegif($image, $filepath);
                break;
        }
    }

    private function recordFile($user_id, $filename, $original_filename, $mime_type, $size, $filepath) {
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO files (user_id, filename, original_filename, mime_type, file_size, file_path)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $filename,
            $original_filename,
            $mime_type,
            $size,
            $filepath
        ]);
    }
} 