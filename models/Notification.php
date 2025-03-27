<?php
// models/Notification.php
require_once __DIR__ . '/../includes/ErrorHandler.php';

class Notification {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Crea una nueva notificación
     *
     * @param int $user_id ID del usuario destinatario
     * @param string $title Título de la notificación
     * @param string $message Mensaje
     * @param string $link Enlace opcional
     * @param string $type Tipo de notificación
     * @return int|bool ID de la notificación o false
     */
    public function create($user_id, $title, $message, $link = null, $type = 'system') {
        try {
            if (empty($user_id) || empty($title)) {
                throw new Exception("Usuario y título son obligatorios");
            }
            
            // Validar el tipo de notificación
            $validTypes = ['transaction', 'reservation', 'thank_you', 'expiry', 'system'];
            if (!in_array($type, $validTypes)) {
                $type = 'system';
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, title, message, link, type, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([$user_id, $title, $message, $link, $type]);
            
            if (!$result) {
                throw new Exception("Error al crear notificación");
            }
            
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Obtiene notificaciones de un usuario específico
     *
     * @param int $user_id ID del usuario
     * @param bool $unread_only Obtener solo notificaciones no leídas
     * @param int $limit Límite de resultados
     * @return array Notificaciones
     */
    public function getByUser($user_id, $unread_only = false, $limit = 20) {
        try {
            $sql = "
                SELECT * FROM notifications
                WHERE user_id = ?
            ";
            
            if ($unread_only) {
                $sql .= " AND is_read = 0";
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            if ($limit > 0) {
                $sql .= " LIMIT " . (int)$limit;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Marca una notificación como leída
     *
     * @param int $id ID de la notificación
     * @param int|null $user_id ID del usuario para validación
     * @return bool Resultado de la operación
     */
    public function markAsRead($id, $user_id = null) {
        try {
            $sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
            $params = [$id];
            
            // Si se proporciona un user_id, validar que la notificación pertenece a ese usuario
            if ($user_id !== null) {
                $sql .= " AND user_id = ?";
                $params[] = $user_id;
            }
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Marca todas las notificaciones de un usuario como leídas
     *
     * @param int $user_id ID del usuario
     * @return bool Resultado de la operación
     */
    public function markAllAsRead($user_id) {
        try {
            $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            return $stmt->execute([$user_id]);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Elimina una notificación
     *
     * @param int $id ID de la notificación
     * @param int|null $user_id ID del usuario para validación
     * @return bool Resultado de la operación
     */
    public function delete($id, $user_id = null) {
        try {
            $sql = "DELETE FROM notifications WHERE id = ?";
            $params = [$id];
            
            // Si se proporciona un user_id, validar que la notificación pertenece a ese usuario
            if ($user_id !== null) {
                $sql .= " AND user_id = ?";
                $params[] = $user_id;
            }
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Cuenta las notificaciones no leídas de un usuario
     *
     * @param int $user_id ID del usuario
     * @return int Cantidad de notificaciones no leídas
     */
    public function countUnread($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return 0;
        }
    }
    
    /**
     * Crea notificaciones para fechas de expiración
     *
     * @param int $days_before Días antes de la expiración para notificar
     * @return int Número de notificaciones enviadas
     */
    public function notifyExpiringGiftLists($days_before = 3) {
        try {
            // Encuentre listas que expiran en $days_before días
            $stmt = $this->pdo->prepare("
                SELECT gl.id, gl.title, gl.expiry_date, gl.user_id
                FROM gift_lists gl
                WHERE gl.expiry_date = DATE_ADD(CURDATE(), INTERVAL ? DAY)
                  AND gl.id NOT IN (
                    SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(n.message, 'ID: ', -1), ' ', 1)
                    FROM notifications n
                    WHERE n.type = 'expiry'
                      AND n.message LIKE '%expira en $days_before días%'
                  )
            ");
            $stmt->execute([$days_before]);
            $expiringLists = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $count = 0;
            foreach ($expiringLists as $list) {
                $title = "Tu lista de regalos expirará pronto";
                $message = "Tu lista '{$list['title']}' (ID: {$list['id']}) expira en {$days_before} días ({$list['expiry_date']}).";
                $link = "edit_giftlist.php?id={$list['id']}";
                
                if ($this->create($list['user_id'], $title, $message, $link, 'expiry')) {
                    $count++;
                }
            }
            
            return $count;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return 0;
        }
    }
    
    /**
     * Crea una notificación de compra para el propietario de una lista
     *
     * @param int $transaction_id ID de la transacción
     * @param int $gift_list_id ID de la lista
     * @param int $owner_id ID del propietario
     * @param string $gift_name Nombre del regalo
     * @param float $amount Monto
     * @return bool Resultado de la operación
     */
    public function notifyPurchase($transaction_id, $gift_list_id, $owner_id, $gift_name, $amount) {
        $title = "¡Alguien ha comprado un regalo!";
        $message = "Alguien ha comprado '{$gift_name}' de tu lista por $" . number_format($amount, 2) . ".";
        $link = "transaction_details.php?id={$transaction_id}";
        
        return $this->create($owner_id, $title, $message, $link, 'transaction');
    }
    
    /**
     * Crea una notificación de reserva para el propietario de una lista
     *
     * @param int $reservation_id ID de la reserva
     * @param int $owner_id ID del propietario
     * @param string $gift_name Nombre del regalo
     * @return bool Resultado de la operación
     */
    public function notifyReservation($reservation_id, $owner_id, $gift_name) {
        $title = "¡Regalo Reservado!";
        $message = "Alguien ha reservado '{$gift_name}' de tu lista. Se mantendrá reservado por 48 horas.";
        $link = "reservation_details.php?id={$reservation_id}";
        
        return $this->create($owner_id, $title, $message, $link, 'reservation');
    }
    
    /**
     * Crea una notificación de agradecimiento
     *
     * @param int $thank_you_id ID del agradecimiento
     * @param int $recipient_id ID del destinatario
     * @param string $sender_name Nombre del remitente
     * @return bool Resultado de la operación
     */
    public function notifyThankYou($thank_you_id, $recipient_id, $sender_name) {
        $title = "¡Has recibido un agradecimiento!";
        $message = "{$sender_name} te ha enviado una nota de agradecimiento por tu regalo.";
        $link = "thank_you.php?id={$thank_you_id}";
        
        return $this->create($recipient_id, $title, $message, $link, 'thank_you');
    }
}