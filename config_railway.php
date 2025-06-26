<?php
// Configuración para Railway - Sistema de Tickets KubeAgency
// Este archivo maneja la configuración usando variables de entorno

// Configuración de base de datos desde variables de entorno de Railway
$railway_db_host = getenv('MYSQLHOST') ?: 'localhost';
$railway_db_port = getenv('MYSQLPORT') ?: '3306';
$railway_db_name = getenv('MYSQLDATABASE') ?: 'sistema_tickets_kube';
$railway_db_user = getenv('MYSQLUSER') ?: 'root';
$railway_db_pass = getenv('MYSQLPASSWORD') ?: '';

// URL base del proyecto en Railway
$railway_url = getenv('RAILWAY_STATIC_URL') ?: getenv('PUBLIC_DOMAIN') ?: 'https://tu-proyecto.up.railway.app';

// Configuración de SendGrid
$sendgrid_api_key = getenv('SENDGRID_API_KEY') ?: 'YOUR_SENDGRID_API_KEY_HERE';
$sendgrid_from_email = getenv('SENDGRID_FROM_EMAIL') ?: 'info@kubeagency.co';

// Configuración de entorno
$environment = getenv('RAILWAY_ENVIRONMENT') ?: 'production';

// Función para obtener configuración de base de datos
function getRailwayDbConfig() {
    global $railway_db_host, $railway_db_port, $railway_db_name, $railway_db_user, $railway_db_pass;
    
    return [
        'host' => $railway_db_host,
        'port' => $railway_db_port,
        'dbname' => $railway_db_name,
        'username' => $railway_db_user,
        'password' => $railway_db_pass
    ];
}

// Función para obtener URL base
function getRailwayBaseUrl() {
    global $railway_url;
    return rtrim($railway_url, '/');
}

// Función para obtener configuración de SendGrid
function getRailwaySendGridConfig() {
    global $sendgrid_api_key, $sendgrid_from_email;
    
    return [
        'api_key' => $sendgrid_api_key,
        'from_email' => $sendgrid_from_email,
        'from_name' => 'KubeAgency - Sistema de Tickets'
    ];
}

// Debug info (solo en desarrollo)
function getRailwayDebugInfo() {
    global $environment;
    
    if ($environment !== 'production') {
        return [
            'environment' => $environment,
            'mysql_host' => getenv('MYSQLHOST'),
            'mysql_port' => getenv('MYSQLPORT'),
            'mysql_database' => getenv('MYSQLDATABASE'),
            'mysql_user' => getenv('MYSQLUSER'),
            'railway_url' => getenv('RAILWAY_STATIC_URL'),
            'public_domain' => getenv('PUBLIC_DOMAIN')
        ];
    }
    
    return null;
}
?> 