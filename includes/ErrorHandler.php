<?php
class ErrorHandler {
    public static function logError($message, $context = []) {
        error_log(json_encode(['message' => $message, 'context' => $context]));
    }
    
    public static function handleException($exception) {
        self::logError($exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
    
    public static function displayError($message) {
        return "<div class='alert alert-danger'>$message</div>";
    }
}