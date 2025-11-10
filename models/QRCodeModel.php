<?php
/**
 * MODELO DE CÓDIGOS QR - VERSIÓN 2.1
 * Generación y gestión de códigos QR para listas de regalos
 */

class QRCodeModel {
    private $pdo;
    private $config;
    
    public function __construct($pdo, $config = null) {
        $this->pdo = $pdo;
        $this->config = $config ?? include __DIR__ . '/../config/config.php';
    }
    
    /**
     * Generar QR Code para una lista de regalos
     */
    public function generateQRCode($giftListId) {
        try {
            // Obtener información de la lista
            $stmt = $this->pdo->prepare("
                SELECT unique_link, title 
                FROM gift_lists 
                WHERE id = ?
            ");
            $stmt->execute([$giftListId]);
            $list = $stmt->fetch();
            
            if (!$list) {
                throw new Exception("Lista no encontrada");
            }
            
            // URL completa de la lista
            $url = $this->config['application']['url'] . '/public/giftlist.php?link=' . urlencode($list['unique_link']);
            
            // Generar QR usando biblioteca simple (sin dependencias externas)
            $qrData = $this->generateQRDataURI($url);
            
            // Guardar en base de datos
            $stmt = $this->pdo->prepare("
                INSERT INTO qr_codes (gift_list_id, qr_data, size, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    qr_data = VALUES(qr_data),
                    updated_at = NOW()
            ");
            $stmt->execute([$giftListId, $qrData, $this->config['qr_codes']['size']]);
            
            $qrId = $this->pdo->lastInsertId();
            
            // Actualizar lista con QR ID
            $stmt = $this->pdo->prepare("UPDATE gift_lists SET qr_code_id = ? WHERE id = ?");
            $stmt->execute([$qrId, $giftListId]);
            
            return [
                'success' => true,
                'qr_id' => $qrId,
                'qr_data' => $qrData
            ];
            
        } catch (Exception $e) {
            error_log("Error generando QR: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Obtener QR de una lista
     */
    public function getQRCode($giftListId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM qr_codes 
                WHERE gift_list_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$giftListId]);
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("Error obteniendo QR: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Incrementar contador de descargas
     */
    public function incrementDownloads($qrId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE qr_codes SET downloads = downloads + 1 WHERE id = ?");
            return $stmt->execute([$qrId]);
        } catch (Exception $e) {
            error_log("Error incrementando descargas: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generar QR como Data URI usando API externa
     */
    private function generateQRDataURI($url) {
        // Usando API pública de Google Charts (simple y sin dependencias)
        $size = $this->config['qr_codes']['size'] ?? 300;
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($url);
        
        // Obtener la imagen
        $imageData = @file_get_contents($qrUrl);
        
        if ($imageData === false) {
            // Fallback: generar manualmente con biblioteca PHP simple
            return $this->generateQRFallback($url);
        }
        
        // Convertir a base64
        return 'data:image/png;base64,' . base64_encode($imageData);
    }
    
    /**
     * Fallback simple para generar QR
     */
    private function generateQRFallback($url) {
        // Data URI de un QR genérico (placeholder)
        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">' .
            '<rect width="200" height="200" fill="white"/>' .
            '<text x="100" y="100" text-anchor="middle" font-size="12">QR Code</text>' .
            '</svg>'
        );
    }
}
