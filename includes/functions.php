<?php
/**
 * Utility functions for formatting, sanitization, and output helpers
 */

/**
 * Escape HTML output to prevent XSS
 */
function sanitize(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Human-readable time ago format
 */
function timeAgo(string $datetime): string {
    $timestamp = strtotime($datetime);
    if (!$timestamp) return 'Just now';

    $difference = time() - $timestamp;
    if ($difference < 10) return 'Just now';
    if ($difference < 60) return $difference . 's ago';
    if ($difference < 3600) return floor($difference / 60) . 'm ago';
    if ($difference < 86400) return floor($difference / 3600) . 'h ago';
    if ($difference < 604800) return floor($difference / 86400) . 'd ago';
    if ($difference < 2592000) return floor($difference / 604800) . 'w ago';
    
    return date('M j, Y', $timestamp);
}

/**
 * Basic markdown-like text formatting for posts and chat (bold, italic, code, links)
 */
function formatMessage(string $text): string {
    $text = sanitize($text);
    
    // Bold **text**
    $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
    // Italic *text*
    $text = preg_replace('/(?<!\*)\*(?!\*)(.*?)\*/s', '<em>$1</em>', $text);
    // Inline code `code`
    $text = preg_replace('/`(.*?)`/s', '<code>$1</code>', $text);
    // Line breaks
    $text = nl2br($text);

    return $text;
}

/**
 * Send JSON API Response
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}


/**
 * Secure File / Image Upload Handler
 */
function handleUpload(array $fileArray): ?string {
    if (empty($fileArray) || $fileArray['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'zip'];
    $allowedMimeTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'text/plain', 'application/zip', 'application/x-zip-compressed'
    ];

    $fileTmpPath = $fileArray['tmp_name'];
    $fileName = $fileArray['name'];
    $fileSize = $fileArray['size'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Limit file size to 5MB (Shared hosting safe)
    if ($fileSize > 5 * 1024 * 1024) {
        throw new Exception("File size exceeds maximum limit of 5MB.");
    }

    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception("Invalid file extension. Allowed: " . implode(', ', $allowedExtensions));
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileTmpPath);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimeTypes)) {
        throw new Exception("Invalid file type uploaded.");
    }

    $uploadDir = __DIR__ . '/../uploads/attachments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newFileName = md5(uniqid(microtime(), true)) . '.' . $fileExtension;
    $destPath = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmpPath, $destPath)) {
        return 'uploads/attachments/' . $newFileName;
    }

    throw new Exception("Failed to move uploaded file.");
}

/**
 * Format Attachment HTML preview (Image or File download link)
 */
function renderAttachmentHtml(?string $attachmentPath): string {
    if (empty($attachmentPath)) return '';

    $ext = strtolower(pathinfo($attachmentPath, PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

    if ($isImage) {
        return '<div style="margin-top: 0.75rem;"><a href="' . sanitize($attachmentPath) . '" target="_blank"><img src="' . sanitize($attachmentPath) . '" alt="Attachment Image" style="max-width: 100%; max-height: 350px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); object-fit: contain;"></a></div>';
    } else {
        $filename = basename($attachmentPath);
        return '<div style="margin-top: 0.75rem;"><a href="' . sanitize($attachmentPath) . '" download class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 0.4rem;">📎 Download Attachment (' . sanitize($filename) . ')</a></div>';
    }
}


/**
 * Generate Avatar URL
 * Uses uploaded custom avatar if available, otherwise generates Pravatar.cc seeded avatar URL
 */
function getAvatarUrl(?string $avatar, string $username): string {
    if (!empty($avatar) && $avatar !== 'default.png' && file_exists(__DIR__ . '/../uploads/avatars/' . $avatar)) {
        return 'uploads/avatars/' . $avatar;
    }
    
    // Seed pravatar.cc with crc32 hash of username for persistent user avatar images (IDs 1-70)
    $avatarId = (abs(crc32($username)) % 70) + 1;
    return "https://i.pravatar.cc/150?img=" . $avatarId;
}

