<?php
session_start();

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Acceso no autorizado');
}

// Obtener el nombre del archivo de la URL
$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    die('Archivo no especificado');
}

// Sanitizar el nombre del archivo
$filename = basename($filename);

// Ruta completa del archivo
$file_path = __DIR__ . '/uploads/tickets/' . $filename;

// Verificar que el archivo existe
if (!file_exists($file_path) || !is_file($file_path)) {
    http_response_code(404);
    die('Archivo no encontrado');
}

// Obtener información del archivo
$file_info = pathinfo($filename);
$extension = strtolower($file_info['extension'] ?? '');

// Verificar que sea un tipo de archivo permitido
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];
if (!in_array($extension, $allowed_extensions)) {
    http_response_code(403);
    die('Tipo de archivo no permitido');
}

// Configurar el tipo MIME
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'txt' => 'text/plain',
    'zip' => 'application/zip'
];

$mime_type = $mime_types[$extension] ?? 'application/octet-stream';

// Opcional: Verificar permisos del usuario para acceder al archivo
// (puedes agregar lógica adicional aquí para verificar si el usuario
// tiene acceso al ticket específico)

// Configurar headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));
header('Content-Disposition: inline; filename="' . $file_info['basename'] . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

// Para imágenes, mostrar inline; para otros archivos, forzar descarga si es necesario
if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
    header('Content-Disposition: inline; filename="' . $file_info['basename'] . '"');
} else {
    header('Content-Disposition: attachment; filename="' . $file_info['basename'] . '"');
}

// Leer y enviar el archivo
readfile($file_path);
exit;
?> 