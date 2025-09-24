<?php
require_once '../includes/db.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Twilio\Rest\Client;

class NotificationModel {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = getConnection();
        $this->config = include '../config/config.php';
    }
    
    /**
     * Enviar notificación por email
     */
    public function sendEmail($to, $subject, $template, $data = []) {
        try {
            if (!$this->config['notifications']['email_enabled']) {
                return false;
            }
            
            $mail = new PHPMailer(true);
            
            // Configuración SMTP
            $mail->isSMTP();
            $mail->Host = $this->config['smtp']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp']['username'];
            $mail->Password = $this->config['smtp']['password'];
            $mail->SMTPSecure = $this->config['smtp']['secure'];
            $mail->Port = $this->config['smtp']['port'];
            $mail->CharSet = 'UTF-8';
            
            // Configuración del mensaje
            $mail->setFrom($this->config['smtp']['from_email'], $this->config['smtp']['from_name']);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            
            // Cargar template
            $htmlContent = $this->loadEmailTemplate($template, $data);
            $mail->Body = $htmlContent;
            $mail->AltBody = strip_tags($htmlContent);
            
            $result = $mail->send();
            
            // Registrar en log de notificaciones
            $this->logNotification([
                'type' => 'email',
                'recipient' => $to,
                'template' => $template,
                'subject' => $subject,
                'status' => $result ? 'sent' : 'failed',
                'data' => json_encode($data)
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Error enviando email: ' . $e->getMessage());
            $this->logNotification([
                'type' => 'email',
                'recipient' => $to,
                'template' => $template,
                'subject' => $subject,
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Enviar SMS usando Twilio
     */
    public function sendSMS($to, $message, $template = null, $data = []) {
        try {
            if (!$this->config['notifications']['sms_enabled'] || !$this->config['twilio']['enabled']) {
                return false;
            }
            
            $client = new Client(
                $this->config['twilio']['sid'],
                $this->config['twilio']['token']
            );
            
            // Si se proporciona template, cargar el mensaje desde template
            if ($template) {
                $message = $this->loadSMSTemplate($template, $data);
            }
            
            $result = $client->messages->create(
                $to,
                [
                    'from' => $this->config['twilio']['from'],
                    'body' => $message
                ]
            );
            
            // Registrar en log
            $this->logNotification([
                'type' => 'sms',
                'recipient' => $to,
                'template' => $template,
                'message' => $message,
                'status' => $result->status === 'queued' ? 'sent' : 'failed',
                'external_id' => $result->sid
            ]);
            
            return $result->status === 'queued';
            
        } catch (Exception $e) {
            error_log('Error enviando SMS: ' . $e->getMessage());
            $this->logNotification([
                'type' => 'sms',
                'recipient' => $to,
                'template' => $template,
                'message' => $message,
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Crear notificación en tiempo real para el sistema
     */
    public function createSystemNotification($userId, $title, $message, $type = 'info', $data = []) {
        $stmt = $this->db->prepare("
            INSERT INTO system_notifications (user_id, title, message, type, data, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $userId,
            $title,
            $message,
            $type,
            json_encode($data)
        ]);
        
        if ($result) {
            // Enviar notificación en tiempo real via WebSocket o Server-Sent Events
            $this->sendRealTimeNotification($userId, [
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        return $result;
    }
    
    /**
     * Notificaciones específicas del sistema
     */
    
    // Confirmación de pago
    public function sendPaymentConfirmation($email, $transaction, $paymentInfo) {
        $this->sendEmail(
            $email,
            'Confirmación de Pago - Lista de Deseos',
            'payment_confirmation',
            [
                'transaction' => $transaction,
                'payment' => $paymentInfo,
                'app_name' => $this->config['application']['name']
            ]
        );
    }
    
    // Notificación de compra al propietario
    public function sendGiftPurchaseNotification($ownerEmail, $transaction, $gift) {
        $this->sendEmail(
            $ownerEmail,
            '¡Alguien compró de tu lista de deseos!',
            'gift_purchased',
            [
                'transaction' => $transaction,
                'gift' => $gift,
                'app_name' => $this->config['application']['name']
            ]
        );
    }
    
    // Bienvenida a nuevo usuario
    public function sendWelcomeEmail($email, $userName) {
        $this->sendEmail(
            $email,
            'Bienvenido a ' . $this->config['application']['name'],
            'welcome',
            [
                'user_name' => $userName,
                'app_name' => $this->config['application']['name'],
                'app_url' => $this->config['application']['url']
            ]
        );
    }
    
    // Recordatorio de lista inactiva
    public function sendListInactivityReminder($email, $listTitle, $daysSinceLastActivity) {
        $this->sendEmail(
            $email,
            'Tu lista \"' . $listTitle . '\" necesita atención',
            'list_inactivity',
            [
                'list_title' => $listTitle,
                'days_inactive' => $daysSinceLastActivity,
                'app_url' => $this->config['application']['url']
            ]
        );
    }
    
    // Notificación de lista próxima a evento
    public function sendEventReminderNotification($email, $listTitle, $eventDate) {
        $daysUntilEvent = (new DateTime($eventDate))->diff(new DateTime())->days;
        
        $this->sendEmail(
            $email,
            'Tu evento se acerca - \"' . $listTitle . '\"',
            'event_reminder',
            [
                'list_title' => $listTitle,
                'event_date' => $eventDate,
                'days_until' => $daysUntilEvent,
                'app_url' => $this->config['application']['url']
            ]
        );
    }
    
    /**
     * Cargar template de email
     */
    private function loadEmailTemplate($template, $data) {
        $templatePath = $this->config['notifications']['templates_path'] . 'email/' . $template . '.html';
        
        if (!file_exists($templatePath)) {
            throw new Exception('Template no encontrado: ' . $template);
        }
        
        $content = file_get_contents($templatePath);
        
        // Reemplazar variables en el template
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        // Variables por defecto
        $content = str_replace('{{current_year}}', date('Y'), $content);
        $content = str_replace('{{app_name}}', $this->config['application']['name'], $content);
        $content = str_replace('{{app_url}}', $this->config['application']['url'], $content);
        
        return $content;
    }
    
    /**
     * Cargar template de SMS
     */
    private function loadSMSTemplate($template, $data) {
        $templatePath = $this->config['notifications']['templates_path'] . 'sms/' . $template . '.txt';
        
        if (!file_exists($templatePath)) {
            throw new Exception('SMS template no encontrado: ' . $template);
        }
        
        $content = file_get_contents($templatePath);
        
        // Reemplazar variables
        foreach ($data as $key => $value) {
            if (!is_array($value) && !is_object($value)) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
            }
        }
        
        return $content;
    }
    
    /**
     * Registrar notificación en log
     */
    private function logNotification($data) {
        $stmt = $this->db->prepare("
            INSERT INTO notification_logs (type, recipient, template, subject, message, status, 
                                         data, error_message, external_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['type'] ?? null,
            $data['recipient'] ?? null,
            $data['template'] ?? null,
            $data['subject'] ?? null,
            $data['message'] ?? null,
            $data['status'] ?? 'pending',
            $data['data'] ?? null,
            $data['error_message'] ?? null,
            $data['external_id'] ?? null
        ]);
    }
    
    /**
     * Obtener notificaciones del sistema para un usuario
     */
    public function getSystemNotifications($userId, $limit = 50, $unreadOnly = false) {
        $whereClause = "WHERE user_id = ?";
        $params = [$userId];
        
        if ($unreadOnly) {
            $whereClause .= " AND read_at IS NULL";
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM system_notifications 
            $whereClause 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        $params[] = $limit;
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Marcar notificación como leída
     */
    public function markNotificationAsRead($notificationId, $userId) {
        $stmt = $this->db->prepare("
            UPDATE system_notifications 
            SET read_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        
        return $stmt->execute([$notificationId, $userId]);
    }
    
    /**
     * Enviar notificación en tiempo real
     */
    private function sendRealTimeNotification($userId, $data) {
        // Implementar con WebSockets, Server-Sent Events o similar
        // Por ahora, simplemente logeamos
        error_log('Real-time notification for user ' . $userId . ': ' . json_encode($data));
        
        // Aquí podrías integrar con Redis, WebSocket server, etc.
        // file_put_contents('../cache/notifications_' . $userId . '.json', json_encode($data));
    }
    
    /**
     * Programar notificación para el futuro
     */
    public function scheduleNotification($type, $recipient, $template, $data, $sendAt) {
        $stmt = $this->db->prepare("
            INSERT INTO scheduled_notifications (type, recipient, template, data, send_at, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $type,
            $recipient,
            $template,
            json_encode($data),
            $sendAt
        ]);
    }
    
    /**
     * Procesar notificaciones programadas
     */
    public function processScheduledNotifications() {
        $stmt = $this->db->prepare("
            SELECT * FROM scheduled_notifications 
            WHERE send_at <= NOW() AND status = 'pending'
            ORDER BY send_at ASC
        ");
        
        $stmt->execute();
        $notifications = $stmt->fetchAll();
        
        foreach ($notifications as $notification) {
            $data = json_decode($notification['data'], true);
            $success = false;
            
            if ($notification['type'] === 'email') {
                $success = $this->sendEmail(
                    $notification['recipient'],
                    $data['subject'] ?? 'Notificación',
                    $notification['template'],
                    $data
                );
            } elseif ($notification['type'] === 'sms') {
                $success = $this->sendSMS(
                    $notification['recipient'],
                    null,
                    $notification['template'],
                    $data
                );
            }
            
            // Actualizar estado
            $updateStmt = $this->db->prepare("
                UPDATE scheduled_notifications 
                SET status = ?, processed_at = NOW() 
                WHERE id = ?
            ");
            
            $updateStmt->execute([
                $success ? 'sent' : 'failed',
                $notification['id']
            ]);
        }
        
        return count($notifications);
    }
}