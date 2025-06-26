<?php
session_start();

// Verificar acceso de admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Configuración de base de datos
$host = 'localhost';
$dbname = 'sistema_tickets_kube';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$message = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $user_password = $_POST['user_password'] ?? '';
        $role = $_POST['role'] ?? 'cliente';
        $company = trim($_POST['company'] ?? '');
        
        if (!empty($name) && !empty($email) && !empty($user_password)) {
            try {
                // Verificar si el email ya existe
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $existing_user = $stmt->fetch();
                
                if ($existing_user) {
                    $error = 'Ya existe un usuario con ese email';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, company) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $email, password_hash($user_password, PASSWORD_DEFAULT), $role, $company]);
                    $message = 'Usuario creado exitosamente';
                }
            } catch(PDOException $e) {
                $error = 'Error al crear usuario: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'edit') {
        $user_id = $_POST['user_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'cliente';
        $company = trim($_POST['company'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        
        if (!empty($user_id) && !empty($name) && !empty($email)) {
            try {
                // Verificar si el email ya existe en otro usuario
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                $existing_user = $stmt->fetch();
                
                if ($existing_user) {
                    $error = 'Ya existe otro usuario con ese email';
                } else {
                    if (!empty($new_password)) {
                        // Actualizar con nueva contraseña
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, company = ? WHERE id = ?");
                        $stmt->execute([$name, $email, password_hash($new_password, PASSWORD_DEFAULT), $role, $company, $user_id]);
                    } else {
                        // Actualizar sin cambiar contraseña
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, company = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $role, $company, $user_id]);
                    }
                    $message = 'Usuario actualizado exitosamente';
                }
            } catch(PDOException $e) {
                $error = 'Error al actualizar usuario: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete') {
        $user_id = $_POST['user_id'] ?? '';
        
        if (!empty($user_id) && $user_id != $_SESSION['user_id']) {
            try {
                // Verificar si el usuario tiene tickets asociados
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE cliente_id = ? OR agente_id = ?");
                $stmt->execute([$user_id, $user_id]);
                $ticket_count = $stmt->fetch()['count'];
                
                if ($ticket_count > 0) {
                    $error = 'No se puede eliminar el usuario porque tiene tickets asociados';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $message = 'Usuario eliminado exitosamente';
                }
            } catch(PDOException $e) {
                $error = 'Error al eliminar usuario: ' . $e->getMessage();
            }
        } else {
            $error = 'No se puede eliminar el usuario actual';
        }
    }
}

// Obtener usuarios
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$usuarios = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - KubeAgency</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #1a1d29;
            min-height: 100vh;
            color: #e2e8f0;
            font-size: 13px;
            line-height: 1.4;
        }

        /* Fondo minimalista */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1a1d29 0%, #232840 100%);
            z-index: -1;
        }

        .header {
            background: #2d3748;
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #4a5568;
            font-size: 12px;
        }
        
        .header a { 
            color: white; 
            text-decoration: none; 
        }

        .header h1 {
            color: #f7fafc;
            font-size: 1.25rem;
            font-weight: 500;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .card {
            background: #2d3748;
            border-radius: 6px;
            padding: 1rem;
            border: 1px solid #4a5568;
            margin-bottom: 1rem;
        }
        
        .alert {
            padding: 0.5rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 12px;
        }
        
        .alert-success {
            background: #c6f6d5;
            border: 1px solid #9ae6b4;
            color: #2f855a;
        }
        
        .alert-error {
            background: #fed7d7;
            border: 1px solid #feb2b2;
            color: #c53030;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 6px;
            border: 1px solid #4a5568;
        }
        
        .role-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .role-admin { 
            background: #3182ce; 
            color: white; 
        }
        
        .role-agente { 
            background: #ed8936; 
            color: white; 
        }
        
        .role-cliente { 
            background: #38a169; 
            color: white; 
        }

        .users-table {
            background: #2d3748;
            border: 1px solid #4a5568;
            border-radius: 4px;
            overflow: hidden;
            width: 100%;
            table-layout: fixed;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .table th,
        .table td {
            padding: 0.75rem 0.5rem;
            text-align: left;
            border-bottom: 1px solid #4a5568;
            word-wrap: break-word;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table th {
            background: #374151;
            color: #f7fafc;
            font-weight: 500;
            font-size: 11px;
        }

        .table td {
            color: #e2e8f0;
        }

        .table tr:hover td {
            background: #374151;
        }

        .btn {
            padding: 0.375rem 0.75rem;
            border: 1px solid #4a5568;
            border-radius: 4px;
            background: #6b7280;
            color: white;
            text-decoration: none;
            font-size: 11px;
            font-weight: 400;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-family: inherit;
        }

        .btn:hover {
            background: #4b5563;
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid #4a5568;
            color: #cbd5e0;
        }

        .btn-secondary:hover {
            background: #4a5568;
        }

        .btn-danger {
            background: #e53e3e;
        }

        .btn-danger:hover {
            background: #c53030;
        }

        .badge {
            padding: 0.125rem 0.375rem;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 500;
        }

        .badge-admin { background: #3182ce; color: white; }
        .badge-agente { background: #ed8936; color: white; }
        .badge-cliente { background: #38a169; color: white; }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: #2d3748;
            border: 1px solid #4a5568;
            border-radius: 6px;
            padding: 1rem;
            width: 90%;
            max-width: 500px;
        }

        .modal-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #4a5568;
        }

        .modal-title {
            color: #f7fafc;
            font-size: 1rem;
            font-weight: 500;
        }

        .close {
            background: none;
            border: none;
            color: #cbd5e0;
            font-size: 1.25rem;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.25rem;
            color: #cbd5e0;
            font-size: 11px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #4a5568;
            border-radius: 4px;
            background: rgba(26, 27, 35, 0.8);
            color: #e2e8f0;
            font-size: 13px;
            transition: border-color 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3182ce;
        }

        .form-group select {
            background-image: url("data:image/svg+xml;charset=utf-8,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%233182ce' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1rem;
            padding-right: 2rem;
        }

        .form-group select option {
            background: #1a1d29;
            color: #e2e8f0;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 10px;
            margin-right: 0.25rem;
        }

        .btn-edit {
            background: #3182ce;
            border-color: #3182ce;
        }

        .btn-edit:hover {
            background: #2c5282;
        }

        .btn-delete {
            background: #e53e3e;
            border-color: #e53e3e;
        }

        .btn-delete:hover {
            background: #c53030;
        }

        .actions-column {
            white-space: nowrap;
        }

        .modal {
            display: none;
        }

        .modal.show {
            display: flex;
        }

        .modal-footer {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #4a5568;
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
        <h2><i class="fas fa-microchip"></i> KubeAgency Control - Usuarios</h2>
        <div>
            <a href="logout.php" class="btn btn-secondary">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario para crear usuario -->
        <div class="card">
            <h3 style="margin-bottom: 1rem;">
                <i class="fas fa-user-plus"></i> Crear Nuevo Usuario
            </h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Nombre Completo *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_password">Contraseña *</label>
                        <input type="password" id="user_password" name="user_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Rol *</label>
                        <select id="role" name="role" required>
                            <option value="cliente">Cliente</option>
                            <option value="agente">Agente</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="company">Empresa</label>
                        <input type="text" id="company" name="company">
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Crear Usuario
                </button>
            </form>
        </div>

        <!-- Lista de usuarios -->
        <div class="card">
            <h3 style="margin-bottom: 1rem;">
                <i class="fas fa-users"></i> Usuarios Registrados (<?php echo count($usuarios); ?>)
            </h3>
            
            <div class="table-container">
                <table class="table users-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 20%;">Nombre</th>
                            <th style="width: 25%;">Email</th>
                            <th style="width: 10%;">Rol</th>
                            <th style="width: 15%;">Empresa</th>
                            <th style="width: 10%;">Registro</th>
                            <th style="width: 15%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?php echo $usuario['id']; ?></td>
                                <td><?php echo htmlspecialchars($usuario['name']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $usuario['role']; ?>">
                                        <?php echo ucfirst($usuario['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($usuario['company'] ?? '-'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($usuario['created_at'])); ?></td>
                                <td class="actions-column">
                                    <button class="btn btn-sm btn-edit" onclick="editUser(<?php echo htmlspecialchars(json_encode($usuario)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-sm btn-delete" onclick="confirmDelete(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para editar usuario -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Editar Usuario</h4>
                <button type="button" class="close" onclick="closeModal('editModal')">&times;</button>
            </div>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label for="edit_name">Nombre Completo *</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email *</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_role">Rol *</label>
                    <select id="edit_role" name="role" required>
                        <option value="cliente">Cliente</option>
                        <option value="agente">Agente</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_company">Empresa</label>
                    <input type="text" id="edit_company" name="company">
                </div>
                
                <div class="form-group">
                    <label for="edit_password">Nueva Contraseña (dejar vacío para mantener la actual)</label>
                    <input type="password" id="edit_password" name="new_password">
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancelar</button>
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para confirmar eliminación -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirmar Eliminación</h4>
                <button type="button" class="close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            
            <p>¿Está seguro que desea eliminar al usuario <strong id="delete_user_name"></strong>?</p>
            <p style="color: #f56565; font-size: 0.9rem; margin-top: 0.5rem;">
                <i class="fas fa-exclamation-triangle"></i> Esta acción no se puede deshacer.
            </p>
            
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_user_id">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancelar</button>
                    <button type="submit" class="btn btn-delete">
                        <i class="fas fa-trash"></i> Eliminar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_company').value = user.company || '';
            document.getElementById('edit_password').value = '';
            
            document.getElementById('editModal').classList.add('show');
        }

        function confirmDelete(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_user_name').textContent = userName;
            
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html> 