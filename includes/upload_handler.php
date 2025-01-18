<?php
class UploadHandler {
    private $upload_dir;
    private $allowed_types;
    private $max_size;
    
    public function __construct($upload_dir = 'uploads', $allowed_types = ['image/jpeg', 'image/png', 'image/gif'], $max_size = 5242880) {
        $this->upload_dir = $upload_dir;
        $this->allowed_types = $allowed_types;
        $this->max_size = $max_size;
        
        // 確保上傳目錄存在
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
    }
    
    public function handle_upload($file, $generate_thumbnail = true) {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid parameters.');
        }
        
        // 檢查錯誤
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('File size exceeded.');
            default:
                throw new RuntimeException('Unknown error.');
        }
        
        // 檢查檔案大小
        if ($file['size'] > $this->max_size) {
            throw new RuntimeException('File size exceeded.');
        }
        
        // 檢查 MIME 類型
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);
        if (!in_array($mime_type, $this->allowed_types)) {
            throw new RuntimeException('Invalid file format.');
        }
        
        // 生成安全的檔案名
        $extension = array_search($mime_type, [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        ], true);
        
        $filename = sprintf('%s.%s',
            sha1_file($file['tmp_name']),
            $extension
        );
        
        // 移動檔案
        $filepath = sprintf('%s/%s', $this->upload_dir, $filename);
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new RuntimeException('Failed to move uploaded file.');
        }
        
        // 生成縮圖
        if ($generate_thumbnail) {
            $this->create_thumbnail($filepath, $extension);
        }
        
        return $filename;
    }
    
    private function create_thumbnail($filepath, $extension) {
        $thumbnail_path = str_replace('.' . $extension, '_thumb.' . $extension, $filepath);
        
        list($width, $height) = getimagesize($filepath);
        $new_width = 200;
        $new_height = floor($height * ($new_width / $width));
        
        $thumb = imagecreatetruecolor($new_width, $new_height);
        
        switch ($extension) {
            case 'jpg':
                $source = imagecreatefromjpeg($filepath);
                break;
            case 'png':
                $source = imagecreatefrompng($filepath);
                break;
            case 'gif':
                $source = imagecreatefromgif($filepath);
                break;
        }
        
        imagecopyresized($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        switch ($extension) {
            case 'jpg':
                imagejpeg($thumb, $thumbnail_path, 80);
                break;
            case 'png':
                imagepng($thumb, $thumbnail_path, 8);
                break;
            case 'gif':
                imagegif($thumb, $thumbnail_path);
                break;
        }
        
        imagedestroy($thumb);
        imagedestroy($source);
    }
} 