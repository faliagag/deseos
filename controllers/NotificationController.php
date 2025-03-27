<?php
// controllers/NotificationController.php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../models/Notification.php";
require_once __DIR__ . "/../includes/ErrorHandler.php";
require_once __DIR__ . "/../includes/helpers.php";

class NotificationController {
    private $pdo;
    private $notificationModel;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->notificationModel = new Notification($pdo);
    }
    
    /**
     * Obtiene las notificaciones del usuario actual
     * 
     * @param int $user_id ID del usuario
     * @param bool $unread_only Si solo se deben obtener las no leídas
     * @param int $limit Límite de notificaciones
     * @return array Lista de notificaciones
     */
    public function getUserNotifications($user_id, $unread_only = false, $limit = 20) {
        if (empty($user_id)) {
            return [];
        }
        
        return $this->notificationModel->getByUser($user_id, $unread_only, $limit);
    }
    
    /**
     * Cuenta las notificaciones no leídas del usuario
     * 
     * @param int $user_id ID del usuario
     * @return int Número de notificaciones no leídas
     */
    public function countUnreadNotifications($user_id) {
        if (empty($user_id)) {
            return 0;
        }
        
        return $this->notificationModel->countUnread($user_id);
    }
    
    /**
     * Marca una notificación como leída
     * 
     * @param int $notification_id ID de la notificación
     * @param int $user_id ID del usuario
     * @return bool Resultado de la operación
     */
    public function markAsRead($notification_id, $user_id) {
        if (empty($notification_id) || empty($user_id)) {
            return false;
        }
        
        return $this->notificationModel->markAsRead($notification_id, $user_id);
    }
    
    /**
     * Marca todas las notificaciones de un usuario como leídas
     * 
     * @param int $user_id ID del usuario
     * @return bool Resultado de la operación
     */
    public function markAllAsRead($user_id) {
        if (empty($user_id)) {
            return false;
        }
        
        return $this->notificationModel->markAllAsRead($user_id);
    }
    
    /**
     * Elimina una notificación
     * 
     * @param int $notification_id ID de la notificación
     * @param int $user_id ID del usuario
     * @return bool Resultado de la operación
     */
    public function deleteNotification($notification_id, $user_id) {
        if (empty($notification_id) || empty($user_id)) {
            return false;
        }
        
        return $this->notificationModel->delete($notification_id, $user_id);
    }
    
    /**
     * Crea una notificación para un usuario
     * 
     * @param int $user_id ID del usuario
     * @param string $title Título
     * @param string $message Mensaje
     * @param string $link Enlace opcional
     * @param string $type Tipo de notificación
     * @return bool|int ID de la notificación o false
     */
    public function createNotification($user_id, $title, $message, $link = null, $type = 'system') {
        if (empty($user_id) || empty($title) || empty($message)) {
            return false;
        }
        
        return $this->notificationModel->create($user_id, $title, $message, $link, $type);
    }
    
    /**
     * Notifica a los usuarios sobre listas que expiran pronto
     * 
     * @param int $days_before Días antes de expirar
     * @return int Número de notificaciones enviadas
     */
    public function notifyExpiringLists($days_before = 3) {
        return $this->notificationModel->notifyExpiringGiftLists($days_before);
    }
    
    /**
     * Notifica al propietario sobre una nueva compra
     * 
     * @param array $transaction_data Datos de la transacción
     * @return bool Resultado de la operación
     */
    public function notifyTransactionToOwner($transaction_data) {
        try {
            // Obtener el propietario de la lista
            $stmt = $this->pdo->prepare("
                SELECT u.id, gl.title, g.name 
                FROM gift_lists gl
                JOIN users u ON gl.user_id = u.id
                LEFT JOIN gifts g ON g.id = ?
                WHERE gl.id = ?
            ");
            $stmt->execute([$transaction_data['gift_id'], $transaction_data['gift_list_id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return false;
            }
            
            $giftName = $data['name'] ?? 'un regalo';
            
            return $this->notificationModel->notifyPurchase(
                $transaction_data['id'],
                $transaction_data['gift_list_id'],
                $data['id'],
                $giftName,
                $transaction_data['amount']
            );
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Notifica sobre una reserva de regalo
     * 
     * @param array $reservation_data Datos de la reserva
     * @return bool Resultado de la operación
     */
    public function notifyGiftReservation($reservation_data) {
        try {
            // Obtener información de la lista y el regalo
            $stmt = $this->pdo->prepare("
                SELECT u.id as owner_id, gl.title as list_title, g.name as gift_name
                FROM gifts g
                JOIN gift_lists gl ON g.gift_list_id = gl.id
                JOIN users u ON gl.user_id = u.id
                WHERE g.id = ?
            ");
            $stmt->execute([$reservation_data['gift_id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return false;
            }
            
            return $this->notificationModel->notifyReservation(
                $reservation_data['id'],
                $data['owner_id'],
                $data['gift_name']
            );
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Notifica sobre un agradecimiento recibido
     * 
     * @param array $thank_you_data Datos del agradecimiento
     * @param string $sender_name Nombre del remitente
     * @return bool Resultado de la operación
     */
    public function notifyThankYou($thank_you_data, $sender_name) {
        return $this->notificationModel->notifyThankYou(
            $thank_you_data['id'],
            $thank_you_data['recipient_id'],
            $sender_name
        );
    }
}