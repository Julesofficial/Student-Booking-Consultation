<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Notifications Service
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Dispatch an in-app notification to a user
 */
function create_notification(int $userId, string $title, string $message): bool
{
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, is_read, created_at)
            VALUES (:user_id, :title, :message, 0, NOW())
        ");
        return $stmt->execute([
            ':user_id' => $userId,
            ':title'   => trim($title),
            ':message' => trim($message)
        ]);
    } catch (PDOException $e) {
        error_log('Notification creation error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Retrieve total count of unread notifications for a user
 */
function get_unread_notifications_count(int $userId): int
{
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute([':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Retrieve recent notifications for a user
 */
function get_user_notifications(int $userId, int $limit = 20, bool $unreadOnly = false): array
{
    try {
        $pdo = get_db_connection();
        $sql = "SELECT * FROM notifications WHERE user_id = :user_id";
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Mark a single notification as read
 */
function mark_notification_as_read(int $notificationId, int $userId): bool
{
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
        return $stmt->execute([
            ':id'      => $notificationId,
            ':user_id' => $userId
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 */
function mark_all_notifications_as_read(int $userId): bool
{
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
        return $stmt->execute([':user_id' => $userId]);
    } catch (PDOException $e) {
        return false;
    }
}
