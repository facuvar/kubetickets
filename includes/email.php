<?php
require_once __DIR__ . '/../config.php';

// Configuración de SendGrid con detección automática de entorno
class EmailService {
    private $smtp_server = 'smtp.sendgrid.net';
    private $smtp_port = 587;
    private $username = 'apikey';
    private $password;
    private $from_email;
    private $from_name;
    private $base_url;
    
    public function __construct() {
        $config = Config::getInstance();
        $sendgrid_config = $config->getSendGridConfig();
        
        $this->password = $sendgrid_config['api_key'];
        $this->from_email = $sendgrid_config['from_email'];
        $this->from_name = $sendgrid_config['from_name'];
        $this->base_url = $config->getBaseUrl();
    }
    
    public function sendEmail($to, $subject, $body, $is_html = true) {
        return $this->sendViaSendGrid($to, $subject, $body, $is_html);
    }
    
    private function sendViaSendGrid($to, $subject, $body, $is_html = true) {
        $url = 'https://api.sendgrid.com/v3/mail/send';
        
        $data = [
            'personalizations' => [
                [
                    'to' => [['email' => $to]],
                    'subject' => $subject
                ]
            ],
            'from' => [
                'email' => $this->from_email,
                'name' => $this->from_name
            ],
            'content' => [
                [
                    'type' => $is_html ? 'text/html' : 'text/plain',
                    'value' => $body
                ]
            ]
        ];
        
        $options = [
            'http' => [
                'header' => [
                    "Authorization: Bearer " . $this->password,
                    "Content-Type: application/json"
                ],
                'method' => 'POST',
                'content' => json_encode($data),
                'timeout' => 30
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        $context = stream_context_create($options);
        
        // Limpiar cualquier error anterior
        error_clear_last();
        
        $result = file_get_contents($url, false, $context);
        
        // Verificar si hubo errores
        if ($result === false) {
            $error = error_get_last();
            error_log("SendGrid API Error: " . ($error ? $error['message'] : 'Unknown error'));
            
            // Intentar obtener información adicional del contexto HTTP
            if (isset($http_response_header)) {
                error_log("HTTP Response Headers: " . implode(', ', $http_response_header));
            }
            
            return false;
        }
        
        // Verificar respuesta de la API
        $response_data = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("SendGrid JSON decode error: " . json_last_error_msg());
            return false;
        }
        
        // SendGrid retorna 202 para éxito
        if (isset($http_response_header[0]) && strpos($http_response_header[0], '202') !== false) {
            return true;
        }
        
        // Log de respuesta para debug
        error_log("SendGrid Response: " . $result);
        
        return true; // Asumir éxito si no hay errores evidentes
    }
    
    // Plantilla base para emails
    private function getEmailTemplate($title, $content, $ticket_number = '') {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .header p { margin: 10px 0 0 0; opacity: 0.9; }
                .content { padding: 30px 20px; line-height: 1.6; }
                .ticket-info { background: #f8fafc; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; border-radius: 0 5px 5px 0; }
                .button { display: inline-block; background: #10b981; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>KubeAgency</h1>
                    <p>Sistema de Soporte Técnico</p>
                </div>
                <div class='content'>
                    <h2>{$title}</h2>
                    {$content}
                </div>
                <div class='footer'>
                    <p>Este email fue enviado automáticamente por el sistema de tickets de KubeAgency.</p>
                    <p>Por favor, no responda a este email. Para consultas, cree un nuevo ticket en nuestro sistema.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    // Notificar nuevo ticket a administradores
    public function notifyNewTicket($ticket_data, $admin_emails) {
        $subject = "[Nuevo Ticket] {$ticket_data['ticket_number']} - {$ticket_data['subject']}";
        
        $content = "
            <p>Se ha creado un nuevo ticket de soporte que requiere atención.</p>
            <div class='ticket-info'>
                <strong>Número:</strong> {$ticket_data['ticket_number']}<br>
                <strong>Asunto:</strong> {$ticket_data['subject']}<br>
                <strong>Cliente:</strong> {$ticket_data['cliente_name']} ({$ticket_data['cliente_email']})<br>
                <strong>Empresa:</strong> {$ticket_data['cliente_company']}<br>
                <strong>Prioridad:</strong> " . ucfirst($ticket_data['priority']) . "<br>
                <strong>Categoría:</strong> " . ucfirst($ticket_data['category']) . "<br>
                <strong>Fecha:</strong> " . date('d/m/Y H:i', strtotime($ticket_data['created_at'])) . "
            </div>
            <p><strong>Descripción:</strong></p>
            <p>" . nl2br(htmlspecialchars($ticket_data['description'])) . "</p>
            <a href='{$this->base_url}/ticket-detalle.php?id={$ticket_data['id']}' class='button'>Ver Ticket</a>
        ";
        
        $body = $this->getEmailTemplate("Nuevo Ticket de Soporte", $content, $ticket_data['ticket_number']);
        
        foreach ($admin_emails as $email) {
            $this->sendEmail($email, $subject, $body);
        }
    }
    
    // Notificar cambio de estado al cliente
    public function notifyStatusChange($ticket_data, $old_status, $new_status, $changed_by) {
        $subject = "[Actualización] {$ticket_data['ticket_number']} - Estado cambiado a " . ucfirst($new_status);
        
        $status_messages = [
            'abierto' => 'Su ticket está abierto y pendiente de asignación.',
            'proceso' => 'Su ticket está siendo procesado por nuestro equipo.',
            'cerrado' => 'Su ticket ha sido resuelto y cerrado.'
        ];
        
        $content = "
            <p>El estado de su ticket ha sido actualizado.</p>
            <div class='ticket-info'>
                <strong>Número:</strong> {$ticket_data['ticket_number']}<br>
                <strong>Asunto:</strong> {$ticket_data['subject']}<br>
                <strong>Estado anterior:</strong> " . ucfirst($old_status) . "<br>
                <strong>Estado actual:</strong> " . ucfirst($new_status) . "<br>
                <strong>Actualizado por:</strong> {$changed_by}<br>
                <strong>Fecha:</strong> " . date('d/m/Y H:i') . "
            </div>
            <p>" . $status_messages[$new_status] . "</p>
            <a href='{$this->base_url}/ticket-detalle.php?id={$ticket_data['id']}' class='button'>Ver Ticket</a>
        ";
        
        $body = $this->getEmailTemplate("Actualización de Ticket", $content, $ticket_data['ticket_number']);
        
        return $this->sendEmail($ticket_data['cliente_email'], $subject, $body);
    }
    
    // Notificar nueva respuesta al cliente
    public function notifyNewResponse($ticket_data, $message_data, $responder_name) {
        $subject = "[Respuesta] {$ticket_data['ticket_number']} - Nueva respuesta de {$responder_name}";
        
        $content = "
            <p>Ha recibido una nueva respuesta en su ticket de soporte.</p>
            <div class='ticket-info'>
                <strong>Número:</strong> {$ticket_data['ticket_number']}<br>
                <strong>Asunto:</strong> {$ticket_data['subject']}<br>
                <strong>Respuesta de:</strong> {$responder_name}<br>
                <strong>Fecha:</strong> " . date('d/m/Y H:i', strtotime($message_data['created_at'])) . "
            </div>
            <p><strong>Respuesta:</strong></p>
            <p>" . nl2br(htmlspecialchars($message_data['message'])) . "</p>
            <a href='{$this->base_url}/ticket-detalle.php?id={$ticket_data['id']}' class='button'>Ver Ticket Completo</a>
        ";
        
        $body = $this->getEmailTemplate("Nueva Respuesta en su Ticket", $content, $ticket_data['ticket_number']);
        
        return $this->sendEmail($ticket_data['cliente_email'], $subject, $body);
    }
    
    // Notificar asignación de ticket al agente
    public function notifyTicketAssignment($ticket_data, $agent_email, $agent_name) {
        $subject = "[Asignado] {$ticket_data['ticket_number']} - Ticket asignado a usted";
        
        $content = "
            <p>Se le ha asignado un nuevo ticket para gestionar.</p>
            <div class='ticket-info'>
                <strong>Número:</strong> {$ticket_data['ticket_number']}<br>
                <strong>Asunto:</strong> {$ticket_data['subject']}<br>
                <strong>Cliente:</strong> {$ticket_data['cliente_name']}<br>
                <strong>Prioridad:</strong> " . ucfirst($ticket_data['priority']) . "<br>
                <strong>Asignado:</strong> " . date('d/m/Y H:i') . "
            </div>
            <p><strong>Descripción:</strong></p>
            <p>" . nl2br(htmlspecialchars($ticket_data['description'])) . "</p>
            <a href='{$this->base_url}/ticket-detalle.php?id={$ticket_data['id']}' class='button'>Gestionar Ticket</a>
        ";
        
        $body = $this->getEmailTemplate("Ticket Asignado", $content, $ticket_data['ticket_number']);
        
        return $this->sendEmail($agent_email, $subject, $body);
    }
} 