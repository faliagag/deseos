<?php
// controllers/SearchController.php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../models/GiftList.php";

class SearchController {
    private $pdo;
    private $giftListModel;
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->giftListModel = new GiftList($pdo);
    }
    public function search($keyword) {
        return $this->giftListModel->search($keyword);
    }
}
