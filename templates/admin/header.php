<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Panel Administrativo'; ?> - GiftList App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $config['application']['url']; ?>/assets/css/admin.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $config['application']['url']; ?>/admin/dashboard.php">GiftList Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="<?php echo $config['application']['url']; ?>/admin/users.php">Usuarios</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $config['application']['url']; ?>/admin/giftlists.php">Listas de Regalo</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $config['application']['url']; ?>/admin/preset_product_lists.php">Listas Predeterminadas</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $config['application']['url']; ?>/admin/transactions.php">Transacciones</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="<?php echo $config['application']['url']; ?>/logout.php">Cerrar Sesión</a></li>
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