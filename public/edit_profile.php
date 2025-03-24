<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../controllers/AuthController.php";
require_once __DIR__ . "/../includes/auth.php";

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación usando la clase Auth
$auth = new Auth($pdo);
if (!$auth->check()) {
    header("Location: login.php");
    exit;
}

// Obtener datos del usuario
$user = $auth->user();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $authController = new AuthController($pdo);
    $result = $authController->updateProfile($_POST, $user["id"]);
    if ($result["success"]) {
        $_SESSION["user"]["name"] = $_POST["name"];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = $result["message"];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Perfil - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Editar Perfil</h1>
        <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <form method="post" action="">
            <div class="mb-3">
                <label class="form-label">Nombre:</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user["name"]); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Nueva Contraseña (dejar en blanco para mantener la actual):</label>
                <input type="password" name="password" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Actualizar Perfil</button>
        </form>
        <p class="mt-3"><a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a></p>
    </div>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>