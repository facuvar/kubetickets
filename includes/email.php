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
    
    // Plantilla base moderna para emails
    private function getEmailTemplate($title, $content, $ticket_number = '') {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>KubeAgency - {$title}</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    background-color: #f8fafc;
                    color: #2d3748;
                    line-height: 1.6;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: #ffffff; 
                    border-radius: 12px; 
                    overflow: hidden; 
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                    border: 1px solid #e2e8f0;
                }
                .header { 
                    background: linear-gradient(135deg, #3182ce 0%, #2c5aa0 100%); 
                    color: #ffffff; 
                    padding: 32px 24px; 
                    text-align: center;
                }
                .header-content { position: relative; }
                .header h1 { 
                    margin: 0; 
                    font-size: 28px; 
                    font-weight: 700;
                    letter-spacing: -0.5px;
                }
                .header .subtitle { 
                    margin: 8px 0 0 0; 
                    opacity: 0.9; 
                    font-size: 14px;
                    font-weight: 500;
                }
                .header .ticket-badge {
                    display: inline-block;
                    background: rgba(255, 255, 255, 0.2);
                    padding: 6px 16px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                    margin-top: 12px;
                }
                .content { 
                    padding: 32px 24px; 
                    color: #2d3748;
                    background: #ffffff;
                }
                .content h2 {
                    color: #1a202c;
                    font-size: 22px;
                    font-weight: 600;
                    margin-bottom: 20px;
                    border-bottom: 2px solid #3182ce;
                    padding-bottom: 8px;
                }
                .ticket-card { 
                    background: #f7fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 20px 0;
                    position: relative;
                    border-left: 4px solid #3182ce;
                }
                .ticket-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 8px 0;
                    border-bottom: 1px solid #e2e8f0;
                }
                .ticket-row:last-child { border-bottom: none; }
                .ticket-label {
                    font-weight: 600;
                    color: #3182ce;
                    font-size: 13px;
                    min-width: 120px;
                }
                .ticket-value {
                    color: #4a5568;
                    font-size: 13px;
                    text-align: right;
                }
                .description-box {
                    background: #f7fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 6px;
                    padding: 16px;
                    margin: 16px 0;
                    color: #4a5568;
                    font-size: 14px;
                    line-height: 1.6;
                }
                .button { 
                    display: inline-block; 
                    background: #3182ce;
                    color: #ffffff; 
                    padding: 12px 24px; 
                    text-decoration: none; 
                    border-radius: 6px; 
                    margin: 20px 0;
                    font-weight: 600;
                    font-size: 14px;
                    border: none;
                    cursor: pointer;
                }
                .footer { 
                    background: #f7fafc;
                    padding: 24px; 
                    text-align: center; 
                    font-size: 12px; 
                    color: #718096; 
                    border-top: 1px solid #e2e8f0;
                }
                .footer p { margin: 6px 0; }
                .footer .logo-small {
                    color: #3182ce;
                    font-weight: 600;
                    font-size: 14px;
                    margin-bottom: 8px;
                }
                .priority-alta { color: #e53e3e; font-weight: 600; }
                .priority-critica { color: #c53030; font-weight: 700; }
                .priority-media { color: #d69e2e; font-weight: 600; }
                .priority-baja { color: #38a169; font-weight: 600; }
                .status-abierto { color: #ed8936; }
                .status-proceso { color: #3182ce; }
                .status-cerrado { color: #38a169; }
                @media (max-width: 600px) {
                    body { padding: 10px; }
                    .container { border-radius: 8px; }
                    .header, .content, .footer { padding: 20px; }
                    .header h1 { font-size: 24px; }
                    .content h2 { font-size: 20px; }
                    .ticket-row { flex-direction: column; align-items: flex-start; }
                    .ticket-value { text-align: left; margin-top: 4px; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='header-content'>
                        <h1>🚀 KubeAgency</h1>
                        <p class='subtitle'>Sistema de Soporte Técnico</p>
                        " . ($ticket_number ? "<div class='ticket-badge'>Ticket: {$ticket_number}</div>" : "") . "
                    </div>
                </div>
                <div class='content'>
                    <h2>{$title}</h2>
                    {$content}
                </div>
                <div class='footer'>
                    <div class='logo-small'>KUBE Soporte</div>
                    <p>Este email fue enviado automáticamente por nuestro sistema de tickets.</p>
                    <p>Por favor, no responda a este email. Para nuevas consultas, cree un ticket en nuestro sistema.</p>
                    <p style='margin-top: 16px; opacity: 0.7;'>© 2024 KubeAgency. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    // Notificar nuevo ticket a administradores
    public function notifyNewTicket($ticket_data, $admin_emails) {
        $subject = "[Nuevo Ticket] {$ticket_data['ticket_number']} - {$ticket_data['subject']}";
        
        $content = "
            <p style='font-size: 16px; margin-bottom: 24px;'>
                🎫 Se ha creado un nuevo ticket de soporte que requiere atención inmediata.
            </p>
            
            <div class='ticket-card'>
                <div class='ticket-row'>
                    <span class='ticket-label'>📋 Número:</span>
                    <span class='ticket-value'><strong>{$ticket_data['ticket_number']}</strong></span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>📝 Asunto:</span>
                    <span class='ticket-value'>{$ticket_data['subject']}</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>👤 Cliente:</span>
                    <span class='ticket-value'>{$ticket_data['cliente_name']}</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>📧 Email:</span>
                    <span class='ticket-value'>{$ticket_data['cliente_email']}</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>🏢 Empresa:</span>
                    <span class='ticket-value'>{$ticket_data['cliente_company']}</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>⚡ Prioridad:</span>
                    <span class='ticket-value priority-{$ticket_data['priority']}'>
                        🔥 " . strtoupper($ticket_data['priority']) . "
                    </span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>🏷️ Categoría:</span>
                    <span class='ticket-value'>" . ucfirst($ticket_data['category']) . "</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>📅 Creado:</span>
                    <span class='ticket-value'>" . date('d/m/Y H:i', strtotime($ticket_data['created_at'])) . "</span>
                </div>
            </div>
            
            <h3 style='color: #3182ce; margin: 24px 0 12px 0; font-size: 18px;'>📄 Descripción del Problema:</h3>
            <div class='description-box'>" . nl2br(htmlspecialchars($ticket_data['description'])) . "</div>
            
            <div style='text-align: center; margin: 32px 0;'>
                <a href='{$this->base_url}/ticket-detalle.php?id={$ticket_data['id']}' class='button'>
                    🔍 Ver Ticket Completo
                </a>
            </div>
        ";
        
        $body = $this->getEmailTemplate("🆘 Nuevo Ticket de Soporte", $content, $ticket_data['ticket_number']);
        
        foreach ($admin_emails as $email) {
            $this->sendEmail($email, $subject, $body);
        }
    }
    
    // Notificar cambio de estado al cliente
    public function notifyStatusChange($ticket_data, $old_status, $new_status, $changed_by) {
        $subject = "[Actualización] {$ticket_data['ticket_number']} - Estado cambiado a " . ucfirst($new_status);
        
        $status_emojis = [
            'abierto' => '🟡',
            'proceso' => '🟠', 
            'cerrado' => '🟢'
        ];
        
        $status_messages = [
            'abierto' => 'Su ticket está abierto y pendiente de asignación.',
            'proceso' => 'Su ticket está siendo procesado activamente por nuestro equipo.',
            'cerrado' => 'Su ticket ha sido resuelto exitosamente y cerrado.'
        ];
        
        $old_emoji = $status_emojis[$old_status] ?? '⚪';
        $new_emoji = $status_emojis[$new_status] ?? '⚪';
        $status_message = $status_messages[$new_status] ?? 'Estado actualizado.';
        
        $content = "
            <p style='font-size: 16px; margin-bottom: 24px;'>
                📢 El estado de su ticket ha sido actualizado por nuestro equipo.
            </p>
            
            <div class='ticket-card'>
                <div class='ticket-row'>
                    <span class='ticket-label'>📋 Número:</span>
                    <span class='ticket-value'><strong>{$ticket_data['ticket_number']}</strong></span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>📝 Asunto:</span>
                    <span class='ticket-value'>{$ticket_data['subject']}</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>📊 Estado Anterior:</span>
                    <span class='ticket-value status-{$old_status}'>
                        {$old_emoji} " . strtoupper($old_status) . "
                    </span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>🔄 Estado Actual:</span>
                    <span class='ticket-value status-{$new_status}' style='font-weight: 600;'>
                        {$new_emoji} " . strtoupper($new_status) . "
                    </span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>👨‍💼 Actualizado por:</span>
                    <span class='ticket-value'>{$changed_by}</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>📅 Fecha:</span>
                    <span class='ticket-value'>" . date('d/m/Y H:i') . "</span>
                </div>
            </div>
            
            <div style='background: #f0f8ff; border: 1px solid #3182ce; border-radius: 8px; padding: 20px; margin: 24px 0; text-align: center;'>
                <h3 style='color: #3182ce; margin: 0 0 8px 0; font-size: 16px;'>
                    ℹ️ Estado Actual
                </h3>
                <p style='margin: 0; font-size: 15px; color: #4a5568;'>
                    {$status_message}
                </p>
            </div>
            
            <div style='text-align: center; margin: 32px 0;'>
                <a href='{$this->base_url}/ticket-detalle.php?id={$ticket_data['id']}' class='button'>
                    📋 Ver Ticket Completo
                </a>
            </div>
        ";
        
        $body = $this->getEmailTemplate("🔄 Actualización de Ticket", $content, $ticket_data['ticket_number']);
        
        return $this->sendEmail($ticket_data['cliente_email'], $subject, $body);
    }
    
    // Notificar nueva respuesta al cliente
    public function notifyNewResponse($ticket_data, $message_data, $responder_name) {
        $subject = "[Respuesta] {$ticket_data['ticket_number']} - Nueva respuesta de {$responder_name}";
        
        $content = "
            <p style='font-size: 16px; margin-bottom: 24px;'>
                💬 Ha recibido una nueva respuesta en su ticket de soporte.
            </p>
            
            <div class='ticket-card'>
                <div class='ticket-row'>
                    <span class='ticket-label'>📋 Número:</span>
                    <span class='ticket-value'><strong>{$ticket_data['ticket_number']}</strong></span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>📝 Asunto:</span>
                    <span class='ticket-value'>{$ticket_data['subject']}</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>👨‍💼 Respuesta de:</span>
                    <span class='ticket-value'><strong>{$responder_name}</strong></span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>📅 Fecha:</span>
                    <span class='ticket-value'>" . date('d/m/Y H:i', strtotime($message_data['created_at'])) . "</span>
                </div>
            </div>
            
            <h3 style='color: #3182ce; margin: 24px 0 12px 0; font-size: 18px;'>💭 Nueva Respuesta:</h3>
            <div class='description-box'>" . nl2br(htmlspecialchars($message_data['message'])) . "</div>
            
            <div style='background: #f0f8ff; border: 1px solid #3182ce; border-radius: 8px; padding: 16px; margin: 24px 0; text-align: center;'>
                <p style='margin: 0; font-size: 14px; color: #4a5568;'>
                    💡 <strong>Consejo:</strong> Puede responder directamente desde el sistema para mantener toda la conversación organizada.
                </p>
            </div>
            
            <div style='text-align: center; margin: 32px 0;'>
                <a href='{$this->base_url}/ticket-detalle.php?id={$ticket_data['id']}' class='button'>
                    💬 Ver Conversación Completa
                </a>
            </div>
        ";
        
        $body = $this->getEmailTemplate("💬 Nueva Respuesta en su Ticket", $content, $ticket_data['ticket_number']);
        
        return $this->sendEmail($ticket_data['cliente_email'], $subject, $body);
    }
    
    // Notificar asignación de ticket al agente
    public function notifyTicketAssignment($ticket_data, $agent_email, $agent_name) {
        $subject = "[Asignado] {$ticket_data['ticket_number']} - Ticket asignado a usted";
        
        $content = "
            <p style='font-size: 16px; margin-bottom: 24px;'>
                👨‍💼 Se le ha asignado un nuevo ticket para gestionar. ¡Es momento de brillar!
            </p>
            
            <div class='ticket-card'>
                <div class='ticket-row'>
                    <span class='ticket-label'>📋 Número:</span>
                    <span class='ticket-value'><strong>{$ticket_data['ticket_number']}</strong></span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>📝 Asunto:</span>
                    <span class='ticket-value'>{$ticket_data['subject']}</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>👤 Cliente:</span>
                    <span class='ticket-value'>{$ticket_data['cliente_name']}</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>📧 Email:</span>
                    <span class='ticket-value'>{$ticket_data['cliente_email']}</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>🏢 Empresa:</span>
                    <span class='ticket-value'>{$ticket_data['cliente_company']}</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>⚡ Prioridad:</span>
                    <span class='ticket-value priority-{$ticket_data['priority']}'>
                        🔥 " . strtoupper($ticket_data['priority']) . "
                    </span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>🏷️ Categoría:</span>
                    <span class='ticket-value'>" . ucfirst($ticket_data['category']) . "</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>📅 Creado:</span>
                    <span class='ticket-value'>" . date('d/m/Y H:i', strtotime($ticket_data['created_at'])) . "</span>
                </div>
            </div>
            
            <h3 style='color: #3182ce; margin: 24px 0 12px 0; font-size: 18px;'>📄 Descripción del Problema:</h3>
            <div class='description-box'>" . nl2br(htmlspecialchars($ticket_data['description'])) . "</div>
            
            <div style='background: #f0f8ff; border: 1px solid #3182ce; border-radius: 8px; padding: 16px; margin: 24px 0; text-align: center;'>
                <p style='margin: 0; font-size: 14px; color: #4a5568;'>
                    🎯 <strong>Recordatorio:</strong> Responda al cliente lo antes posible para mantener altos nuestros estándares de servicio.
                </p>
            </div>
            
            <div style='text-align: center; margin: 32px 0;'>
                <a href='{$this->base_url}/ticket-detalle.php?id={$ticket_data['id']}' class='button'>
                    🚀 Comenzar a Trabajar
                </a>
            </div>
        ";
        
        $body = $this->getEmailTemplate("🎯 Ticket Asignado", $content, $ticket_data['ticket_number']);
        
        return $this->sendEmail($agent_email, $subject, $body);
    }
}