<?php
// controllers/GiftListController.php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../models/GiftList.php";
require_once __DIR__ . "/../models/Gift.php";

class GiftListController {
    private $pdo;
    private $giftListModel;
    private $giftModel;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->giftListModel = new GiftList($pdo);
        $this->giftModel = new Gift($pdo);
    }
    
    /**
     * Crea una nueva lista de regalos.
     */
    public function create($data, $user_id) {
        try {
            $preset_theme = isset($data["preset_theme"]) ? $data["preset_theme"] : null;
            $this->giftListModel->create(
                $user_id,
                $data["title"],
                $data["description"],
                $data["event_type"],
                $data["beneficiary1"],
                $data["beneficiary2"] ?? null,
                $preset_theme
            );
            $lastId = $this->pdo->lastInsertId();
            if (!$lastId) {
                error_log("GiftListController::create: lastInsertId() devolvió false");
                return false;
            }
            return $lastId;
        } catch (PDOException $e) {
            error_log("Error en GiftListController::create: " . $e->getMessage());
            return false;
        }
    }
    
    public function show($unique_link) {
        $list = $this->giftListModel->getByUniqueLink($unique_link);
        if ($list) {
            $list["gifts"] = $this->giftModel->getByGiftList($list["id"]);
            return $list;
        }
        return false;
    }
    
    public function update($id, $data) {
        $preset_theme = isset($data["preset_theme"]) ? $data["preset_theme"] : null;
        return $this->giftListModel->update(
            $id,
            $data["title"],
            $data["description"],
            $data["event_type"],
            $data["beneficiary1"],
            $data["beneficiary2"] ?? null,
            $preset_theme
        );
    }
    
    public function delete($id) {
        return $this->giftListModel->delete($id);
    }
    
    public function addGift($gift_list_id, $data) {
        return $this->giftModel->create(
            $gift_list_id,
            $data["name"],
            isset($data["description"]) ? $data["description"] : "",
            $data["price"],
            $data["stock"]
        );
    }
    
    public function getAll() {
        return $this->giftListModel->getAll();
    }
    
    public function search($keyword) {
        return $this->giftListModel->search($keyword);
    }
}
?>
