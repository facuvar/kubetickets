<?php
// Router para servidor integrado de PHP en Railway
// Este archivo maneja el enrutamiento cuando no se usa Apache

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Si es un archivo estático que existe, lo servimos directamente
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Si es la raíz o un archivo PHP que existe, lo incluimos
if ($uri === '/' || file_exists(__DIR__ . $uri)) {
    if ($uri === '/') {
        include_once __DIR__ . '/index.php';
    } else {
        include_once __DIR__ . $uri;
    }
} else {
    // Para cualquier otra ruta, redirigir al index
    include_once __DIR__ . '/index.php';
}
?> 