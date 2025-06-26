# 🎫 Sistema de Tickets KubeAgency

Sistema completo de gestión de tickets de soporte para KubeAgency, desarrollado en PHP con MySQL. Compatible con **localhost** y **Railway** automáticamente.

## 🚀 Deploy Rápido

### **Opción 1: Localhost (XAMPP)**
```bash
# 1. Descargar proyecto
git clone https://github.com/facuvar/kubetickets.git
cd kubetickets

# 2. Mover a XAMPP
mv kubetickets C:/xampp/htdocs/sistema-tickets

# 3. Crear base de datos
php database/migrate.php

# 4. Acceder
http://localhost/sistema-tickets/
```

### **Opción 2: Railway (Nube)**
```bash
# 1. Fork este repo en GitHub
# 2. Crear proyecto en Railway desde GitHub
# 3. Agregar servicio MySQL en Railway
# 4. Configurar variables de entorno (ver abajo)
# 5. ¡Listo! Tu app estará en xxx.up.railway.app
```

## ⚙️ Variables de Entorno para Railway

```env
# Base de datos (Railway las genera automáticamente)
MYSQLHOST=containers-us-west-xxx.railway.app
MYSQLPORT=6543
MYSQLDATABASE=railway
MYSQLUSER=root
MYSQLPASSWORD=tu_password_generado

# SendGrid (opcional)
SENDGRID_API_KEY=SG.tu_api_key_aqui
SENDGRID_FROM_EMAIL=info@kubeagency.co
```

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

## 📂 Estructura del Proyecto

```
kubetickets/
├── 🔧 config.php              # Configuración automática (localhost/Railway)
├── 🗄️ database/
│   ├── migrate.php            # Migración automática
│   └── setup.php             # Setup inicial
├── 📧 includes/
│   └── email.php             # Servicio de notificaciones (URLs dinámicas)
├── 📁 uploads/
│   ├── tickets/              # Archivos adjuntos
│   └── avatars/              # Fotos de perfil
├── 🏠 index.php              # Dashboard principal
├── 🔐 login.php              # Autenticación
├── ➕ nuevo-ticket.php       # Formulario moderno de tickets
├── 📋 tickets.php            # Lista de tickets (admin) + eliminación
├── 🎫 ticket-detalle.php     # Vista detallada de ticket
├── 📝 mis-tickets.php        # Tickets del cliente
├── 👥 usuarios.php           # Gestión completa de usuarios
├── ⚙️ configuracion.php      # Configuración del sistema
├── 📊 reportes.php           # Reportes y estadísticas
├── 🚀 railway.toml           # Configuración Railway
├── 📦 nixpacks.toml          # Configuración Nixpacks
├── 🚀 deploy.bat             # Script de deploy Windows
└── 📖 README.md              # Esta documentación
```

## 🚀 Configuración Dual (Localhost + Railway)

El sistema **detecta automáticamente** el entorno y se configura solo:

### **🏠 Localhost**
```php
// Se detecta automáticamente
$config = [
    'environment' => 'localhost',
    'db_host' => 'localhost',
    'base_url' => 'http://localhost/sistema-tickets'
];
```

### **☁️ Railway**
```php
// Se detecta automáticamente por variables de entorno
$config = [
    'environment' => 'railway',
    'db_host' => getenv('MYSQLHOST'),
    'base_url' => getenv('RAILWAY_STATIC_URL')
];
```

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