<?php
// models/GiftList.php

class GiftList {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Inserta una nueva lista de regalos.
     *
     * @param int    $user_id
     * @param string $title
     * @param string $description
     * @param string $event_type
     * @param string $beneficiary1
     * @param string|null $beneficiary2
     * @param int|null $preset_theme
     * @return bool Resultado de la inserción.
     */
    public function create($user_id, $title, $description, $event_type, $beneficiary1, $beneficiary2 = null, $preset_theme = null) {
        // Generar un unique_link (puedes cambiar esta lógica)
        $unique_link = md5(uniqid(rand(), true));
        $sql = "INSERT INTO gift_lists (user_id, title, description, event_type, beneficiary1, beneficiary2, preset_theme, unique_link, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([$user_id, $title, $description, $event_type, $beneficiary1, $beneficiary2, $preset_theme, $unique_link]);
        if (!$result) {
            error_log("GiftList::create error: " . print_r($stmt->errorInfo(), true));
        }
        return $result;
    }
    
    /**
     * Actualiza una lista de regalos.
     */
    public function update($id, $title, $description, $event_type, $beneficiary1, $beneficiary2 = null, $preset_theme = null) {
        $sql = "UPDATE gift_lists SET title = ?, description = ?, event_type = ?, beneficiary1 = ?, beneficiary2 = ?, preset_theme = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([$title, $description, $event_type, $beneficiary1, $beneficiary2, $preset_theme, $id]);
        if (!$result) {
            error_log("GiftList::update error: " . print_r($stmt->errorInfo(), true));
        }
        return $result;
    }
    
    /**
     * Elimina una lista.
     */
    public function delete($id) {
        $sql = "DELETE FROM gift_lists WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([$id]);
        if (!$result) {
            error_log("GiftList::delete error: " . print_r($stmt->errorInfo(), true));
        }
        return $result;
    }
    
    /**
     * Obtiene una lista por unique_link.
     */
    public function getByUniqueLink($unique_link) {
        $sql = "SELECT * FROM gift_lists WHERE unique_link = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$unique_link]);
        return $stmt->fetch();
    }
    
    /**
     * Retorna todas las listas.
     */
    public function getAll() {
        $sql = "SELECT * FROM gift_lists ORDER BY created_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Busca listas por título o descripción.
     */
    public function search($keyword) {
        $sql = "SELECT * FROM gift_lists WHERE title LIKE ? OR description LIKE ? ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(["%$keyword%", "%$keyword%"]);
        return $stmt->fetchAll();
    }
}
?>
