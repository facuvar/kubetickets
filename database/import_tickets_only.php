<?php
// Script para importar solo tickets y mensajes faltantes
require_once '../config.php';

echo "🎫 Importando tickets faltantes a Railway...\n\n";

try {
    // Verificar que estamos en Railway
    $config = Config::getInstance();
    if (!$config->isRailway()) {
        throw new Exception("Este script solo debe ejecutarse en Railway");
    }
    
    echo "✅ Entorno detectado: Railway\n";
    
    // Conectar a Railway
    $pdo = $config->getDbConnection();
    echo "✅ Conexión exitosa a Railway MySQL\n\n";
    
    // Verificar estado actual
    $ticket_count = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
    $message_count = $pdo->query("SELECT COUNT(*) FROM ticket_messages")->fetchColumn();
    
    echo "📊 Estado actual:\n";
    echo "- Tickets: $ticket_count\n";
    echo "- Mensajes: $message_count\n\n";
    
    if ($ticket_count == 0) {
        echo "🎫 Importando tickets desde localhost...\n";
        
        $tickets_data = [
            [1, 'No andan los mails', 'No funciona algo', 'abierto', 'alta', 3, null, 'KUBE-001', 'No andan los mails'],
            [2, 'Bot problemas', 'es un problema que viene desde ayer', 'abierto', 'alta', 3, null, 'KUBE-002', 'no me anda el bot'],
            [3, 'Problemas varios', 'no andan las cosas de la maica', 'abierto', 'media', 3, null, 'KUBE-003', 'no me anda el bot'],
            [4, 'Issue crítico', 'no andan las cosas de la maica', 'abierto', 'media', 3, 2, 'KUBE-004', 'no me anda el bot'],
            [5, 'Bot agresivo', 'el bot se vuelve loco yputea a l gente', 'abierto', 'media', 3, null, 'KUBE-005', 'no me anda el botija'],
            [6, 'Bot insulta', 'el bot insulta y les dice Pollo a todos los clientes', 'abierto', 'alta', 3, null, 'KUBE-006', 'no me anda el bot'],
            [7, 'Problema pollo', 'le dice pollo a todos', 'abierto', 'alta', 3, null, 'KUBE-007', 'no me anda el bot'],
            [8, 'Robot issues', 'pollea y pollea', 'cerrado', 'alta', 3, null, 'KUBE-008', 'no me anda el robot']
        ];
        
        $ticket_stmt = $pdo->prepare("INSERT IGNORE INTO tickets (id, title, description, status, priority, cliente_id, agente_id, ticket_number, subject, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        
        foreach ($tickets_data as $ticket) {
            $ticket_stmt->execute($ticket);
        }
        echo "✅ " . count($tickets_data) . " tickets importados\n\n";
        
        // Actualizar ticket cerrado
        $pdo->prepare("UPDATE tickets SET closed_at = NOW() WHERE id = 8")->execute();
    } else {
        echo "⚠️  Los tickets ya existen, saltando importación\n\n";
    }
    
    if ($message_count == 0) {
        echo "💬 Importando mensajes...\n";
        $message_stmt = $pdo->prepare("INSERT IGNORE INTO ticket_messages (id, ticket_id, user_id, message, is_internal, created_at) VALUES (1, 4, 1, 'Estamos trabajando en eso, en breve estara solucionado', 0, NOW())");
        $message_stmt->execute();
        echo "✅ 1 mensaje importado\n\n";
    } else {
        echo "⚠️  Los mensajes ya existen, saltando importación\n\n";
    }
    
    // Verificar resultado final
    echo "🔍 VERIFICACIÓN FINAL:\n";
    
    $final_tickets = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
    $final_messages = $pdo->query("SELECT COUNT(*) FROM ticket_messages")->fetchColumn();
    $final_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $final_config = $pdo->query("SELECT COUNT(*) FROM system_config")->fetchColumn();
    
    echo "- users: $final_users registros\n";
    echo "- tickets: $final_tickets registros\n";
    echo "- ticket_messages: $final_messages registros\n";
    echo "- system_config: $final_config registros\n";
    
    $total = $final_users + $final_tickets + $final_messages + $final_config;
    echo "\n📊 TOTAL: $total registros\n\n";
    
    // Mostrar algunos tickets de ejemplo
    if ($final_tickets > 0) {
        echo "🎫 TICKETS IMPORTADOS:\n";
        $tickets_stmt = $pdo->query("SELECT ticket_number, subject, status, priority FROM tickets ORDER BY id LIMIT 5");
        $tickets = $tickets_stmt->fetchAll();
        
        foreach ($tickets as $ticket) {
            echo "- {$ticket['ticket_number']}: {$ticket['subject']} [{$ticket['status']}] [{$ticket['priority']}]\n";
        }
        echo "\n";
    }
    
    echo "🎉 ¡Importación de tickets completada!\n";
    echo "🔗 Ya puedes usar el sistema completo\n";
    echo "🔑 Login: admin@kubeagency.co / admin123\n";
    
} catch(PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    exit(1);
} catch(Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
    exit(1);
}
?> 