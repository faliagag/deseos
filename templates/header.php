<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'GiftList App'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $config['application']['url']; ?>/assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $config['application']['url']; ?>/index.php">GiftList App</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if(isset($_SESSION["user"])): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $config['application']['url']; ?>/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $config['application']['url']; ?>/logout.php">Cerrar Sesión</a></li>
                    <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $config['application']['url']; ?>/login.php">Iniciar Sesión</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $config['application']['url']; ?>/register.php">Registrarse</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <?php 
        $flash = get_flash_message();
        if ($flash): 
        ?>
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
            <?php echo $flash['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>