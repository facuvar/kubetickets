# 🎫 Sistema de Tickets KubeAgency

Sistema de gestión de tickets de soporte desarrollado para KubeAgency con diseño moderno y notificaciones automáticas.

## 🚀 Deploy Dual - Localhost + Railway

Este sistema está configurado para funcionar automáticamente tanto en **localhost** (XAMPP) como en **Railway** (producción).

### 🖥️ Localhost (XAMPP)
   ```bash
# Acceso directo
http://localhost/sistema-tickets/

# Base de datos
Host: localhost
Database: sistema_tickets_kube
User: root
Password: (vacío)
```

### ☁️ Railway (Producción)

**Variables de entorno requeridas:**
```env
# Base de datos MySQL
MYSQLHOST=mysql.railway.internal
MYSQLPORT=3306
MYSQLDATABASE=railway
MYSQLUSER=root
MYSQLPASSWORD=zqhKigXvCNxnmNkRiKaOVnwJeFvqWzIK

# SendGrid para emails
SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxx
SENDGRID_FROM_EMAIL=info@kubeagency.co
```

**Configuración automática:**
- ✅ Detección automática de entorno
- ✅ Configuración de base de datos dinámica
- ✅ Migración automática en Railway
- ✅ URLs dinámicas para emails

## 📧 Configuración SendGrid

1. Crear cuenta en [SendGrid](https://sendgrid.com/)
2. Generar API Key con permisos "Mail Send"
3. Agregar variables en Railway:
   - `SENDGRID_API_KEY`
   - `SENDGRID_FROM_EMAIL`

## 🛠️ Características

### ✅ Sistema de Tickets
- Creación, edición y eliminación de tickets
- Estados: abierto, proceso, cerrado
- Prioridades: baja, media, alta, crítica
- Numeración automática KUBE-XXX
- Archivos adjuntos

### ✅ Gestión de Usuarios
- Roles: admin, agente, cliente
- CRUD completo con validaciones
- Protección contra eliminación del usuario actual

### ✅ Notificaciones Email
- Nuevos tickets → Administradores
- Cambio de estado → Cliente
- Nuevas respuestas → Cliente
- Asignación de agente → Agente

### ✅ Interfaz Moderna
- Tema oscuro profesional
- Responsive design
- Efectos de hover y animaciones
- Fondo con partículas animadas

## 🗄️ Base de Datos

```sql
-- Tablas principales
- users (usuarios del sistema)
- tickets (tickets de soporte)
- ticket_messages (conversación)
- ticket_attachments (archivos adjuntos)
- system_config (configuración)
```

## 🔐 Credenciales por Defecto

```
Usuario: admin@kubeagency.co
Contraseña: admin123
```

## 📁 Estructura del Proyecto

```
sistema-tickets/
├── config.php              # Configuración automática
├── config_railway.php      # Específico Railway
├── deploy_railway.php      # Script migración Railway
├── database/
│   ├── migrate.php         # Migración completa
│   └── setup.php          # Configuración inicial
├── includes/
│   └── email.php          # Sistema de emails
├── uploads/               # Archivos subidos
│   ├── tickets/          # Adjuntos de tickets
│   └── avatars/          # Avatares de usuarios
└── *.php                 # Páginas del sistema
```

## 🚀 Instrucciones de Deploy

### Localhost (XAMPP)
1. Clonar en `C:/xampp/htdocs/`
2. Crear base de datos `sistema_tickets_kube`
3. Acceder a `http://localhost/sistema-tickets/`

### Railway
1. Conectar repositorio GitHub
2. Crear servicio MySQL
3. Agregar variables de entorno
4. Deploy automático

## 🔧 Tecnologías

- **Backend**: PHP 8.2+ (vanilla)
- **Base de datos**: MySQL
- **Email**: SendGrid API
- **Frontend**: HTML5, CSS3, JavaScript
- **Deploy**: Railway + GitHub

## 📝 Licencia

MIT License - KubeAgency 2025

## ✨ Características Principales

### 🎫 **Gestión de Tickets**
- ✅ Creación de tickets con categorías y prioridades
- ✅ Estados: Abierto, En Proceso, Cerrado
- ✅ Prioridades: Baja, Media, Alta, Crítica
- ✅ Numeración automática (KUBE-001, KUBE-002, etc.)
- ✅ Archivos adjuntos con validación
- ✅ Sistema de comentarios/respuestas
- ✅ Asignación automática y manual de agentes
- ✅ **Eliminación completa de tickets** (admin/agente)

### 👥 **Sistema de Usuarios**
- ✅ **Administradores**: Control total del sistema
- ✅ **Agentes**: Gestión de tickets asignados
- ✅ **Clientes**: Creación y seguimiento de tickets
- ✅ Autenticación segura con sesiones
- ✅ **CRUD completo de usuarios** (crear, editar, eliminar)
- ✅ Validación de emails únicos

### 📧 **Notificaciones por Email**
- ✅ Integración con **SendGrid**
- ✅ **URLs dinámicas** (funciona en localhost y Railway)
- ✅ Notificaciones automáticas para:
  - Nuevos tickets (a administradores)
  - Cambios de estado (a clientes)
  - Nuevas respuestas (a clientes)
  - Asignaciones (a agentes)
- ✅ Plantillas HTML profesionales

### 📊 **Dashboard y Reportes**
- ✅ Panel de control con estadísticas
- ✅ Listado de tickets con filtros
- ✅ Indicadores de actividad
- ✅ Métricas de rendimiento
- ✅ **Botones de acciones principales** reorganizados

### 🎨 **Interfaz Moderna**
- ✅ Diseño **responsive** (móvil y desktop)
- ✅ **Tema oscuro profesional** con gradientes
- ✅ **Fondo animado** con partículas luminosas
- ✅ Iconos Font Awesome 6
- ✅ **Efectos hover avanzados** y animaciones
- ✅ UX optimizada para eficiencia
- ✅ **Consistencia visual** en todos los archivos

## 🔧 Tecnologías

- **Backend**: PHP 8.2+
- **Base de datos**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript ES6+
- **Email**: SendGrid API
- **Deploy**: Railway + GitHub
- **Iconos**: Font Awesome 6
- **Tipografía**: Inter (Google Fonts)

## 📋 Checklist Post-Deploy

### **✅ Localhost**
- [ ] XAMPP ejecutándose
- [ ] Base de datos creada
- [ ] Permisos de `/uploads/` configurados
- [ ] Acceso a `http://localhost/sistema-tickets/`

### **☁️ Railway**
- [ ] Proyecto conectado a GitHub
- [ ] Servicio MySQL agregado
- [ ] Variables de entorno configuradas
- [ ] Build exitoso
- [ ] Acceso a `xxx.up.railway.app`

## 🎯 Uso del Sistema

### **👤 Para Clientes**
1. **Login** → Ver dashboard personal
2. **Crear Ticket** → Formulario moderno con drag & drop
3. **Mis Tickets** → Seguimiento visual con badges
4. **Responder** → Comunicación fluida con agentes

### **🛠️ Para Agentes**
1. **Dashboard** → Tickets asignados + estadísticas
2. **Gestionar** → Cambiar estados, responder, asignar
3. **Eliminar** → Borrar tickets completos (con confirmación)

### **⚡ Para Administradores**
1. **Control Total** → Todos los tickets del sistema
2. **Usuarios** → CRUD completo (crear, editar, eliminar)
3. **Configuración** → Ajustar parámetros globales
4. **Reportes** → Métricas y estadísticas avanzadas

## 👥 Usuarios Predeterminados

### **Administradores**
- `admin@kubeagency.co` / `admin123`
- `facundo@kubeagency.co` / `facundo123`

### **Agente**
- `agente@kubeagency.co` / `agente123`

### **Cliente Demo**
- `cliente@kubeagency.co` / `cliente123`

## 🔒 Seguridad

- ✅ **Validación de entradas** en todos los formularios
- ✅ **Sanitización** de datos antes de mostrar
- ✅ **Protección CSRF** en formularios críticos
- ✅ **Sesiones seguras** con validación de roles
- ✅ **Validación de archivos** con tipos permitidos
- ✅ **URLs dinámicas** para prevenir hardcoding

## 🐛 Debugging

### **Información de entorno**
```php
// En cualquier archivo PHP
require_once 'config.php';
$debug = Config::getInstance()->getDebugInfo();
var_dump($debug);
```

### **Logs de email**
```bash
# Los errores de SendGrid se logean automáticamente
tail -f /var/log/php_errors.log
```

## 📞 Soporte

- **GitHub**: [Issues en kubetickets](https://github.com/facuvar/kubetickets/issues)
- **Email**: info@kubeagency.co
- **Sistema**: Crear ticket desde la app 😊

## 📄 Licencia

© 2025 KubeAgency. Todos los derechos reservados.

---

**🚀 Desarrollado con ❤️ para funcionar en cualquier entorno**  
**⚡ localhost → Railway en minutos** 