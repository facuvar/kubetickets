<?php
session_start();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($email) && !empty($password)) {
        // Usar configuración automática
        require_once 'config.php';
        
        try {
            $config = Config::getInstance();
            $pdo = $config->getDbConnection();
            
            // Buscar usuario
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login exitoso
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Email o contraseña incorrectos';
            }
        } catch(PDOException $e) {
            $error = 'Error de conexión a la base de datos. ' . $e->getMessage();
        }
    } else {
        $error = 'Por favor complete todos los campos';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KubeAgency Tickets</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #1a1d29;
            color: #e2e8f0;
            min-height: 100vh;
            font-size: 13px;
            line-height: 1.4;
            display: flex;
            align-items: center;
            justify-content: center;
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

        .login-container {
            background: #2d3748;
            border: 1px solid #4a5568;
            border-radius: 6px;
            padding: 1.5rem;
            width: 100%;
            max-width: 400px;
            margin: 1rem;
        }

        .logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .logo h1 {
            color: #f7fafc;
            font-size: 1.25rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .logo p {
            color: #cbd5e0;
            font-size: 11px;
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

        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #4a5568;
            border-radius: 4px;
            background: #1a1d29;
            color: #e2e8f0;
            font-size: 13px;
            transition: border-color 0.2s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3182ce;
        }

        .btn {
            width: 100%;
            padding: 0.5rem;
            border: none;
            border-radius: 4px;
            background: #3182ce;
            color: white;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn:hover {
            background: #2c5282;
        }

        .error {
            background: #fed7d7;
            border: 1px solid #feb2b2;
            color: #c53030;
            padding: 0.5rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 12px;
        }

        .success {
            background: #c6f6d5;
            border: 1px solid #9ae6b4;
            color: #2f855a;
            padding: 0.5rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 12px;
        }


    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>KubeAgency Control</h1>
            <p>Sistema de Soporte Técnico</p>
        </div>

        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i>
                    Email
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="admin@kubeagency.co"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i>
                    Contraseña
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="••••••••"
                    required
                >
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i>
                Iniciar Sesión
            </button>
        </form>


    </div>
</body>
</html> 