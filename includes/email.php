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
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    background: linear-gradient(135deg, #1a1d29 0%, #232840 50%, #1a1d29 100%);
                    color: #e2e8f0;
                    line-height: 1.6;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: linear-gradient(135deg, rgba(45, 55, 72, 0.95) 0%, rgba(55, 65, 81, 0.95) 100%); 
                    border-radius: 16px; 
                    overflow: hidden; 
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(79, 209, 199, 0.2);
                    backdrop-filter: blur(20px);
                }
                .header { 
                    background: linear-gradient(135deg, #38b2ac 0%, #4fd1c7 100%); 
                    color: #1a1d29; 
                    padding: 40px 30px; 
                    text-align: center; 
                    position: relative;
                    overflow: hidden;
                }
                .header::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"25\" cy=\"25\" r=\"1\" fill=\"%23ffffff\" opacity=\"0.1\"/><circle cx=\"75\" cy=\"75\" r=\"1\" fill=\"%23ffffff\" opacity=\"0.1\"/><circle cx=\"50\" cy=\"10\" r=\"0.5\" fill=\"%23ffffff\" opacity=\"0.15\"/><circle cx=\"20\" cy=\"60\" r=\"0.5\" fill=\"%23ffffff\" opacity=\"0.15\"/><circle cx=\"80\" cy=\"40\" r=\"0.5\" fill=\"%23ffffff\" opacity=\"0.15\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg>');
                    z-index: 1;
                }
                .header-content { position: relative; z-index: 2; }
                .header h1 { 
                    margin: 0; 
                    font-size: 32px; 
                    font-weight: 700;
                    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    letter-spacing: -0.5px;
                }
                .header .subtitle { 
                    margin: 8px 0 0 0; 
                    opacity: 0.8; 
                    font-size: 14px;
                    font-weight: 500;
                }
                .header .ticket-badge {
                    display: inline-block;
                    background: rgba(26, 29, 41, 0.2);
                    padding: 6px 16px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                    margin-top: 12px;
                    backdrop-filter: blur(10px);
                }
                .content { 
                    padding: 40px 30px; 
                    color: #e2e8f0;
                    position: relative;
                }
                .content h2 {
                    color: #f7fafc;
                    font-size: 24px;
                    font-weight: 600;
                    margin-bottom: 20px;
                    border-bottom: 2px solid rgba(79, 209, 199, 0.2);
                    padding-bottom: 12px;
                }
                .ticket-card { 
                    background: linear-gradient(135deg, rgba(26, 29, 41, 0.8) 0%, rgba(45, 55, 72, 0.8) 100%);
                    border: 1px solid rgba(79, 209, 199, 0.3);
                    border-radius: 12px;
                    padding: 24px;
                    margin: 24px 0;
                    position: relative;
                    overflow: hidden;
                }
                .ticket-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 4px;
                    height: 100%;
                    background: linear-gradient(135deg, #38b2ac, #4fd1c7);
                    border-radius: 0 2px 2px 0;
                }
                .ticket-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 8px 0;
                    border-bottom: 1px solid rgba(79, 209, 199, 0.1);
                }
                .ticket-row:last-child { border-bottom: none; }
                .ticket-label {
                    font-weight: 600;
                    color: #4fd1c7;
                    font-size: 13px;
                    min-width: 120px;
                }
                .ticket-value {
                    color: #e2e8f0;
                    font-size: 13px;
                    text-align: right;
                }
                .description-box {
                    background: rgba(26, 29, 41, 0.6);
                    border: 1px solid rgba(79, 209, 199, 0.2);
                    border-radius: 8px;
                    padding: 20px;
                    margin: 20px 0;
                    color: #cbd5e0;
                    font-size: 14px;
                    line-height: 1.7;
                }
                .button { 
                    display: inline-block; 
                    background: linear-gradient(135deg, #38b2ac 0%, #4fd1c7 100%);
                    color: #1a1d29; 
                    padding: 14px 28px; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    margin: 24px 0;
                    font-weight: 600;
                    font-size: 14px;
                    box-shadow: 0 4px 12px rgba(79, 209, 199, 0.3);
                    transition: all 0.3s ease;
                    border: none;
                    cursor: pointer;
                }
                .button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 20px rgba(79, 209, 199, 0.4);
                }
                .footer { 
                    background: linear-gradient(135deg, rgba(26, 29, 41, 0.9) 0%, rgba(35, 40, 64, 0.9) 100%);
                    padding: 30px; 
                    text-align: center; 
                    font-size: 12px; 
                    color: #a0aec0; 
                    border-top: 1px solid rgba(79, 209, 199, 0.2);
                }
                .footer p { margin: 8px 0; }
                .footer .logo-small {
                    color: #4fd1c7;
                    font-weight: 600;
                    font-size: 14px;
                    margin-bottom: 8px;
                }
                @media (max-width: 600px) {
                    body { padding: 10px; }
                    .container { border-radius: 12px; }
                    .header, .content, .footer { padding: 20px; }
                    .header h1 { font-size: 28px; }
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
                    <div class='logo-small'>KubeAgency Control</div>
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
        
        $priority_colors = [
            'baja' => '#38a169',
            'media' => '#ed8936', 
            'alta' => '#e53e3e',
            'critica' => '#c53030'
        ];
        $priority_color = $priority_colors[$ticket_data['priority']] ?? '#ed8936';
        
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
                    <span class='ticket-value' style='color: {$priority_color}; font-weight: 600;'>
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
            
            <h3 style='color: #4fd1c7; margin: 24px 0 12px 0; font-size: 18px;'>📄 Descripción del Problema:</h3>
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
        
        $status_data = [
            'abierto' => [
                'message' => 'Su ticket está abierto y pendiente de asignación.',
                'emoji' => '🟡',
                'color' => '#ed8936'
            ],
            'proceso' => [
                'message' => 'Su ticket está siendo procesado activamente por nuestro equipo.',
                'emoji' => '🟠',
                'color' => '#3182ce'
            ],
            'cerrado' => [
                'message' => 'Su ticket ha sido resuelto exitosamente y cerrado.',
                'emoji' => '🟢',
                'color' => '#38a169'
            ]
        ];
        
        $old_status_data = $status_data[$old_status] ?? ['emoji' => '⚪', 'color' => '#6b7280'];
        $new_status_data = $status_data[$new_status] ?? ['emoji' => '⚪', 'color' => '#6b7280'];
        
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
                    <span class='ticket-value' style='color: {$old_status_data['color']};'>
                        {$old_status_data['emoji']} " . strtoupper($old_status) . "
                    </span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>🔄 Estado Actual:</span>
                    <span class='ticket-value' style='color: {$new_status_data['color']}; font-weight: 600;'>
                        {$new_status_data['emoji']} " . strtoupper($new_status) . "
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
            
            <div style='background: rgba(56, 178, 172, 0.1); border: 1px solid rgba(56, 178, 172, 0.3); border-radius: 8px; padding: 20px; margin: 24px 0; text-align: center;'>
                <h3 style='color: #4fd1c7; margin: 0 0 8px 0; font-size: 16px;'>
                    ℹ️ Estado Actual
                </h3>
                <p style='margin: 0; font-size: 15px; color: #e2e8f0;'>
                    {$new_status_data['message']}
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
            
            <h3 style='color: #4fd1c7; margin: 24px 0 12px 0; font-size: 18px;'>💭 Nueva Respuesta:</h3>
            <div class='description-box'>" . nl2br(htmlspecialchars($message_data['message'])) . "</div>
            
            <div style='background: rgba(56, 178, 172, 0.1); border: 1px solid rgba(56, 178, 172, 0.3); border-radius: 8px; padding: 16px; margin: 24px 0; text-align: center;'>
                <p style='margin: 0; font-size: 14px; color: #e2e8f0;'>
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
        
        $priority_colors = [
            'baja' => '#38a169',
            'media' => '#ed8936', 
            'alta' => '#e53e3e',
            'critica' => '#c53030'
        ];
        $priority_color = $priority_colors[$ticket_data['priority']] ?? '#ed8936';
        
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
                    <span class='ticket-label'>🏢 Empresa:</span>
                    <span class='ticket-value'>{$ticket_data['cliente_company']}</span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>⚡ Prioridad:</span>
                    <span class='ticket-value' style='color: {$priority_color}; font-weight: 600;'>
                        🔥 " . strtoupper($ticket_data['priority']) . "
                    </span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>🎯 Asignado a:</span>
                    <span class='ticket-value'><strong>{$agent_name}</strong></span>
                </div>
                <div class='ticket-row'>
                    <span class='ticket-label'>📅 Fecha Asignación:</span>
                    <span class='ticket-value'>" . date('d/m/Y H:i') . "</span>
                </div>
            </div>
            
            <h3 style='color: #4fd1c7; margin: 24px 0 12px 0; font-size: 18px;'>📄 Descripción del Problema:</h3>
            <div class='description-box'>" . nl2br(htmlspecialchars($ticket_data['description'])) . "</div>
            
            <div style='background: rgba(237, 137, 54, 0.1); border: 1px solid rgba(237, 137, 54, 0.3); border-radius: 8px; padding: 16px; margin: 24px 0; text-align: center;'>
                <p style='margin: 0; font-size: 14px; color: #e2e8f0;'>
                    ⏱️ <strong>Recordatorio:</strong> Mantén al cliente informado sobre el progreso y responde en tiempo oportuno.
                </p>
            </div>
            
            <div style='text-align: center; margin: 32px 0;'>
                <a href='{$this->base_url}/ticket-detalle.php?id={$ticket_data['id']}' class='button'>
                    🚀 Gestionar Ticket
                </a>
            </div>
        ";
        
        $body = $this->getEmailTemplate("🎯 Ticket Asignado", $content, $ticket_data['ticket_number']);
        
        return $this->sendEmail($agent_email, $subject, $body);
    }
} 