<?php
// Configuración automática para localhost y Railway
// Este archivo detecta el entorno y configura la base de datos automáticamente

class Config {
    private static $instance = null;
    private $db_config = [];
    private $environment = '';
    private $base_url = '';
    
    private function __construct() {
        $this->detectEnvironment();
        $this->setupDatabase();
        $this->setupBaseUrl();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function detectEnvironment() {
        // Detectar si estamos en Railway
        if (getenv('RAILWAY_ENVIRONMENT') || getenv('MYSQLHOST')) {
            $this->environment = 'railway';
        } 
        // Detectar si estamos en localhost
        elseif ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['HTTP_HOST'] === 'localhost') {
            $this->environment = 'localhost';
        }
        // Detectar otros entornos locales
        elseif (in_array($_SERVER['SERVER_NAME'], ['127.0.0.1', 'localhost', '::1'])) {
            $this->environment = 'localhost';
        }
        else {
            $this->environment = 'production';
        }
    }
    
    private function setupDatabase() {
        switch ($this->environment) {
            case 'railway':
                $this->db_config = [
                    'host' => getenv('MYSQLHOST') ?: 'localhost',
                    'port' => getenv('MYSQLPORT') ?: '3306',
                    'dbname' => getenv('MYSQLDATABASE') ?: 'sistema_tickets_kube',
                    'username' => getenv('MYSQLUSER') ?: 'root',
                    'password' => getenv('MYSQLPASSWORD') ?: ''
                ];
                break;
                
            case 'localhost':
            default:
                $this->db_config = [
                    'host' => 'localhost',
                    'port' => '3306',
                    'dbname' => 'sistema_tickets_kube',
                    'username' => 'root',
                    'password' => ''
                ];
                break;
        }
    }
    
    private function setupBaseUrl() {
        switch ($this->environment) {
            case 'railway':
                $this->base_url = getenv('RAILWAY_STATIC_URL') ?: getenv('PUBLIC_DOMAIN') ?: 'https://kubetickets.up.railway.app';
                break;
                
            case 'localhost':
                $this->base_url = 'http://localhost/sistema-tickets';
                break;
                
            default:
                $this->base_url = 'https://' . $_SERVER['HTTP_HOST'];
                break;
        }
    }
    
    // Getters públicos
    public function getEnvironment() {
        return $this->environment;
    }
    
    public function getDbConfig() {
        return $this->db_config;
    }
    
    public function getDbConnection() {
        $config = $this->db_config;
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
        
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        return $pdo;
    }
    
    public function getBaseUrl() {
        return rtrim($this->base_url, '/');
    }
    
    public function getSendGridConfig() {
        $api_key = getenv('SENDGRID_API_KEY') ?: 'YOUR_SENDGRID_API_KEY_HERE';
        $from_email = getenv('SENDGRID_FROM_EMAIL') ?: 'info@kubeagency.co';
        
        return [
            'api_key' => $api_key,
            'from_email' => $from_email,
            'from_name' => 'KubeAgency - Sistema de Tickets'
        ];
    }
    
    public function isLocalhost() {
        return $this->environment === 'localhost';
    }
    
    public function isRailway() {
        return $this->environment === 'railway';
    }
    
    public function isProduction() {
        return $this->environment === 'production' || $this->environment === 'railway';
    }
    
    // Debug info
    public function getDebugInfo() {
        return [
            'environment' => $this->environment,
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
            'http_host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'base_url' => $this->base_url,
            'db_host' => $this->db_config['host'],
            'db_name' => $this->db_config['dbname'],
            'railway_env' => getenv('RAILWAY_ENVIRONMENT'),
            'mysql_host' => getenv('MYSQLHOST')
        ];
    }
}

// Funciones de conveniencia para backward compatibility
function getDbConnection() {
    return Config::getInstance()->getDbConnection();
}

function getBaseUrl() {
    return Config::getInstance()->getBaseUrl();
}

function getSendGridConfig() {
    return Config::getInstance()->getSendGridConfig();
}

function isLocalhost() {
    return Config::getInstance()->isLocalhost();
}

function isRailway() {
    return Config::getInstance()->isRailway();
}
?> 