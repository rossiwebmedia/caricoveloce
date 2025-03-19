<?php
// Verifica che l'utente sia loggato
// Commentato temporaneamente per risolvere il ciclo di reindirizzamento
// if (!isLoggedIn()) {
//     header('Location: login.php');
//     exit;
// }

// Ottieni informazioni utente
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Utente';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';

// Determina la pagina attiva
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/product-style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* CSS di base per il layout */
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background-color: #f8f9fa;
        }
        
        .wrapper {
            display: flex;
        }
        
        #sidebar {
            width: 250px;
            min-height: 100vh;
            background: #343a40;
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
        }
        
        #content {
            width: calc(100% - 250px);
            min-height: 100vh;
            padding: 20px;
            margin-left: 250px;
        }
        
        #sidebar .sidebar-header {
            padding: 20px;
            background: #202938;
        }
        
        #sidebar ul.components {
            padding: 20px 0;
            list-style-type: none;
        }
        
        #sidebar ul li a {
            padding: 10px 20px;
            display: block;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }
        
        #sidebar ul li a:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        #sidebar ul li.active > a {
            color: #fff;
            background: rgba(0, 123, 255, 0.5);
        }
        
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
            }
            
            #sidebar.active {
                margin-left: 0;
            }
            
            #content {
                width: 100%;
                margin-left: 0;
            }
        }
        /* Stile per i sottomenu */
#sidebar ul ul a {
    padding-left: 30px;
    background: rgba(0, 0, 0, 0.2);
    font-size: 0.9em;
}

#sidebar ul ul li.active > a {
    background: rgba(0, 123, 255, 0.3);
}
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3><?php echo SITE_NAME; ?></h3>
            </div>

          <ul class="list-unstyled components">
    <li class="<?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
        <a href="index.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    </li>
    <li class="<?php echo in_array($currentPage, ['prodotti.php', 'inserimento_prodotti.php', 'gestione_prodotti.php', 'inserimento_multiplo_prodotti.php']) ? 'active' : ''; ?>">
        <a href="#prodottiSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
            <i class="bi bi-box me-2"></i> Prodotti
        </a>
        <ul class="collapse list-unstyled <?php echo in_array($currentPage, ['prodotti.php', 'inserimento_prodotti.php', 'gestione_prodotti.php', 'inserimento_multiplo_prodotti.php']) ? 'show' : ''; ?>" id="prodottiSubmenu">
            <li class="<?php echo $currentPage === 'prodotti.php' ? 'active' : ''; ?>">
                <a href="prodotti.php" class="ms-3"><i class="bi bi-list me-2"></i> Lista Prodotti</a>
            </li>
            <li class="<?php echo $currentPage === 'inserimento_prodotti.php' ? 'active' : ''; ?>">
                <a href="inserimento_prodotti.php" class="ms-3"><i class="bi bi-plus-circle me-2"></i> Inserimento Singolo</a>
            </li>
            <li class="<?php echo $currentPage === 'inserimento_multiplo_prodotti.php' ? 'active' : ''; ?>">
                <a href="inserimento_multiplo_prodotti.php" class="ms-3"><i class="bi bi-list-check me-2"></i> Inserimento Multiplo</a>
            </li>
            <li class="<?php echo $currentPage === 'gestione_prodotti.php' ? 'active' : ''; ?>">
                <a href="gestione_prodotti.php" class="ms-3"><i class="bi bi-gear me-2"></i> Gestione</a>
            </li>
        </ul>
    </li>
<li class="<?php echo in_array($currentPage, ['gestione_ean.php', 'visualizza_ean.php']) ? 'active' : ''; ?>">
    <a href="#eanSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo in_array($currentPage, ['gestione_ean.php', 'visualizza_ean.php']) ? 'true' : 'false'; ?>" class="dropdown-toggle">
        <i class="bi bi-upc-scan me-2"></i> Codici EAN
    </a>
    <ul class="collapse list-unstyled <?php echo in_array($currentPage, ['gestione_ean.php', 'visualizza_ean.php']) ? 'show' : ''; ?>" id="eanSubmenu">
        <li class="<?php echo $currentPage === 'gestione_ean.php' ? 'active' : ''; ?>">
            <a href="gestione_ean.php" class="ms-3"><i class="bi bi-upload me-2"></i> Importa EAN</a>
        </li>
        <li class="<?php echo $currentPage === 'visualizza_ean.php' ? 'active' : ''; ?>">
            <a href="visualizza_ean.php" class="ms-3"><i class="bi bi-search me-2"></i> Visualizza EAN</a>
        </li>
    </ul>
</li>
    <?php if ($userRole === 'admin'): ?>
    <li class="<?php echo $currentPage === 'gestione_utenti.php' ? 'active' : ''; ?>">
        <a href="gestione_utenti.php"><i class="bi bi-people me-2"></i> Gestione Utenti</a>
    </li>
    <?php endif; ?>
    <li class="<?php echo $currentPage === 'impostazioni.php' ? 'active' : ''; ?>">
        <a href="impostazioni.php"><i class="bi bi-gear me-2"></i> Impostazioni</a>
    </li>
</ul>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info text-white">
                        <i class="bi bi-list"></i>
                    </button>
                    
                    <div class="ms-auto">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($username); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                                <li><a class="dropdown-item" href="profilo.php">Profilo</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid main-content">
                <?php if (isset($pageTitle)): ?>
                <h2 class="mb-4"><?php echo htmlspecialchars($pageTitle); ?></h2>
                <?php endif; ?>
                
                <!-- Qui inizia il contenuto specifico della pagina -->