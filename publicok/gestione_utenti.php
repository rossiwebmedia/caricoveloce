<?php
require_once 'config.php';
require_once 'functions.php';

// Titolo della pagina
$pageTitle = 'Gestione Utenti';

// Verifica che l'utente sia amministratore
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

// Include l'header
include 'header.php';

// Connessione al database
$conn = getDbConnection();

// Messaggio di notifica
$notification = '';

// Gestione dell'invio del form per aggiungere/modificare utenti
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $nome = sanitizeInput($_POST['nome_completo']);
    $ruolo = sanitizeInput($_POST['ruolo']);
    $password = $_POST['password'] ?? '';
    
    try {
        // Verifica che lo username non sia già in uso (escludendo l'utente corrente in caso di modifica)
        $stmt = $conn->prepare("SELECT id FROM utenti WHERE username = ? AND id != ?");
        $stmt->execute([$username, $userId]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            $notification = [
                'type' => 'danger',
                'message' => "Username già in uso. Scegli un altro username."
            ];
        } else {
            if ($userId > 0) {
                // Aggiorna un utente esistente
                if (!empty($password)) {
                    // Aggiorna anche la password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("
                        UPDATE utenti 
                        SET username = ?, email = ?, nome_completo = ?, ruolo = ?, password = ?, aggiornato_il = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$username, $email, $nome, $ruolo, $hashedPassword, $userId]);
                } else {
                    // Aggiorna senza modificare la password
                    $stmt = $conn->prepare("
                        UPDATE utenti 
                        SET username = ?, email = ?, nome_completo = ?, ruolo = ?, aggiornato_il = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$username, $email, $nome, $ruolo, $userId]);
                }
                
                $notification = [
                    'type' => 'success',
                    'message' => "Utente aggiornato con successo."
                ];
            } else {
                // Crea un nuovo utente
                if (empty($password)) {
                    // Genera una password casuale se non specificata
                    $password = generateRandomPassword();
                }
                
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("
                    INSERT INTO utenti (username, password, email, nome_completo, ruolo)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$username, $hashedPassword, $email, $nome, $ruolo]);
                
                $notification = [
                    'type' => 'success',
                    'message' => "Utente creato con successo. Password: $password"
                ];
            }
        }
    } catch (Exception $e) {
        $notification = [
            'type' => 'danger',
            'message' => "Errore durante il salvataggio dell'utente: " . $e->getMessage()
        ];
    }
}

// Gestione della rimozione di un utente
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete'])) {
    $userId = intval($_GET['delete']);
    
    try {
        // Non permettere l'eliminazione dell'utente corrente o dell'amministratore principale (ID 1)
        if ($userId === $_SESSION['user_id'] || $userId === 1) {
            $notification = [
                'type' => 'warning',
                'message' => "Non è possibile eliminare questo utente."
            ];
        } else {
            // Elimina l'utente
            $stmt = $conn->prepare("DELETE FROM utenti WHERE id = ?");
            $stmt->execute([$userId]);
            
            $notification = [
                'type' => 'success',
                'message' => "Utente eliminato con successo."
            ];
        }
    } catch (Exception $e) {
        $notification = [
            'type' => 'danger',
            'message' => "Errore durante l'eliminazione dell'utente: " . $e->getMessage()
        ];
    }
}

// Ottieni tutti gli utenti
$stmt = $conn->query("SELECT * FROM utenti ORDER BY id ASC");
$users = $stmt->fetchAll();

// Mostra la notifica se presente
if (!empty($notification)) {
    echo '<div class="alert alert-' . $notification['type'] . ' alert-dismissible fade show mb-4" role="alert">';
    echo $notification['message'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>';
    echo '</div>';
}
?>

<div class="row">
    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Aggiungi Utente</h5>
            </div>
            <div class="card-body">
                <form action="gestione_utenti.php" method="post" id="userForm">
                    <input type="hidden" name="save_user" value="1">
                    <input type="hidden" name="user_id" id="user_id" value="0">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username:</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nome_completo" class="form-label">Nome completo:</label>
                        <input type="text" class="form-control" id="nome_completo" name="nome_completo" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ruolo" class="form-label">Ruolo:</label>
                        <select class="form-control" id="ruolo" name="ruolo" required>
                            <option value="viewer">Visualizzatore</option>
                            <option value="editor">Editor</option>
                            <option value="admin">Amministratore</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            Password:
                            <small class="text-muted" id="password-help">Lascia vuoto per generare una password casuale</small>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password">
                            <button class="btn btn-outline-secondary" type="button" id="toggle-password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> <span id="buttonLabel">Aggiungi Utente</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="resetForm">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Lista Utenti</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Ruolo</th>
                                <th>Ultimo accesso</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['nome_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php 
                                        switch ($user['ruolo']) {
                                            case 'admin':
                                                echo '<span class="badge bg-danger">Amministratore</span>';
                                                break;
                                            case 'editor':
                                                echo '<span class="badge bg-primary">Editor</span>';
                                                break;
                                            default:
                                                echo '<span class="badge bg-secondary">Visualizzatore</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo $user['ultimo_accesso'] ? date('d/m/Y H:i', strtotime($user['ultimo_accesso'])) : 'Mai'; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary edit-user" 
                                                data-id="<?php echo $user['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                data-name="<?php echo htmlspecialchars($user['nome_completo']); ?>"
                                                data-role="<?php echo htmlspecialchars($user['ruolo']); ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        
                                        <?php if ($user['id'] !== $_SESSION['user_id'] && $user['id'] !== 1): ?>
                                            <a href="gestione_utenti.php?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Sei sicuro di voler eliminare questo utente?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Nessun utente trovato.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Script aggiuntivi per la pagina di gestione utenti
$additionalScripts = <<<EOT
<script>
$(document).ready(function() {
    // Gestione visibilità password
    $('#toggle-password').on('click', function() {
        const passwordInput = $('#password');
        const icon = $(this).find('i');
        
        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            passwordInput.attr('type', 'password');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });
    
    // Modifica utente
    $('.edit-user').on('click', function() {
        const id = $(this).data('id');
        const username = $(this).data('username');
        const email = $(this).data('email');
        const name = $(this).data('name');
        const role = $(this).data('role');
        
        $('#user_id').val(id);
        $('#username').val(username);
        $('#email').val(email);
        $('#nome_completo').val(name);
        $('#ruolo').val(role);
        
        $('#buttonLabel').text('Aggiorna Utente');
        $('#password-help').text('Lascia vuoto per mantenere la password attuale');
        
        // Scorri alla form
        $('html, body').animate({
            scrollTop: $('#userForm').offset().top - 100
        }, 500);
    });
    
    // Reset form
    $('#resetForm').on('click', function() {
        $('#user_id').val('0');
        $('#username').val('');
        $('#email').val('');
        $('#nome_completo').val('');
        $('#ruolo').val('viewer');
        $('#password').val('');
        
        $('#buttonLabel').text('Aggiungi Utente');
        $('#password-help').text('Lascia vuoto per generare una password casuale');
    });
});
</script>
EOT;

// Include il footer
include 'footer.php';
?>
