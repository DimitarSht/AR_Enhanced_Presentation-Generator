<?php

class PresentationDB {

    public static function createPresentation($userId, $originalFilename, $storedFilename, $fileSize, $ipAddress = null, $userAgent = null) {
        $db = getDB();
        $sql = "INSERT INTO presentations (user_id, original_filename, stored_filename, file_size, ip_address, user_agent)
                VALUES (:user_id, :original_filename, :stored_filename, :file_size, :ip_address, :user_agent)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':original_filename' => $originalFilename,
            ':stored_filename' => $storedFilename,
            ':file_size' => $fileSize,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
        ]);
        return $db->lastInsertId();
    }

    public static function updateStatus($presentationId, $status) {
        $db = getDB();
        $sql = "UPDATE presentations SET status = :status WHERE presentation_id = :pid";
        $stmt = $db->prepare($sql);
        $stmt->execute([':status' => $status, ':pid' => $presentationId]);
    }

    public static function logProcessing($presentationId, $level, $message, $details = null) {
        $db = getDB();
        $sql = "INSERT INTO processing_logs (presentation_id, level, message, details)
                VALUES (:pid, :level, :message, :details)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':pid' => $presentationId,
            ':level' => $level,
            ':message' => $message,
            ':details' => $details ? json_encode($details) : null,
        ]);
    }

    public static function getPresentationsForUser($userId) {
        $db = getDB();
        $sql = "SELECT * FROM presentations WHERE user_id = :uid ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public static function getAllPresentations() {
        $db = getDB();
        $sql = "SELECT p.*, u.username FROM presentations p LEFT JOIN users u ON p.user_id = u.user_id ORDER BY p.created_at DESC";
        return $db->query($sql)->fetchAll();
    }

    public static function deletePresentation($presentationId, $userId = null) {
        $db = getDB();

        $sql = "SELECT stored_filename, user_id FROM presentations WHERE presentation_id = :pid";
        $stmt = $db->prepare($sql);
        $stmt->execute([':pid' => $presentationId]);
        $pres = $stmt->fetch();

        if (!$pres) return false;

        if ($userId !== null && (int)$pres['user_id'] !== (int)$userId) {
            return false;
        }

        $uploadPath = UPLOAD_DIR . $pres['stored_filename'];
        $processedPath = PROCESSED_DIR . 'processed_' . $pres['stored_filename'];
        if (file_exists($uploadPath)) @unlink($uploadPath);
        if (file_exists($processedPath)) @unlink($processedPath);

        $sql = "DELETE FROM presentations WHERE presentation_id = :pid";
        $stmt = $db->prepare($sql);
        $stmt->execute([':pid' => $presentationId]);
        return true;
    }

    public static function getAllUsers() {
        $db = getDB();
        $sql = "SELECT user_id, username, email, role, created_at, last_login FROM users ORDER BY created_at DESC";
        return $db->query($sql)->fetchAll();
    }

    public static function getProcessingLogs($limit = 100) {
        $db = getDB();
        $sql = "SELECT pl.*, p.original_filename
                FROM processing_logs pl
                JOIN presentations p ON pl.presentation_id = p.presentation_id
                ORDER BY pl.created_at DESC
                LIMIT :lim";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getStats() {
        $db = getDB();
        $stats = [];
        $stats['total_users'] = $db->query("SELECT COUNT(*) as cnt FROM users")->fetch()['cnt'];
        $stats['total_presentations'] = $db->query("SELECT COUNT(*) as cnt FROM presentations")->fetch()['cnt'];
        $stats['presentations_by_status'] = $db->query("SELECT status, COUNT(*) as cnt FROM presentations GROUP BY status")->fetchAll();
        return $stats;
    }
}
