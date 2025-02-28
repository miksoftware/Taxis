# Taxi Diamantes - Sistema de Gestión

Este proyecto es una aplicación para locución de taxis en el cual los usuarios pueden ingresar, registrarse y registrar todos los servicios realizados, llevando un control detallado de las operaciones.

## Estructura del Proyecto

La estructura actual del proyecto es la siguiente:


```
taxis
├── config
│   ├── database.php        # Configuración de la conexión a la base de datos
│   └── config.php          # Configuraciones generales de la aplicación
├── public
│   ├── index.php           # Punto de entrada de la aplicación
│   ├── assets
│   │   ├── css
│   │   │   └── styles.css   # Estilos CSS personalizados
│   │   └── js
│   │       └── scripts.js    # Scripts JavaScript personalizados
│   └── .htaccess            # Configuración del servidor web
├── src
│   ├── Controllers
│   │   ├── AuthController.php # Lógica de autenticación
│   │   └── HomeController.php  # Lógica para la vista principal
│   ├── Models
│   │   └── User.php           # Modelo de usuario
│   └── Helpers
│       └── Validation.php      # Métodos de validación
├── views
│   ├── auth
│   │   ├── login.php          # Vista del formulario de inicio de sesión
│   │   └── register.php       # Vista del formulario de registro
│   ├── layouts
│   │   └── main.php           # Estructura principal de la página
│   └── home
│       └── index.php          # Vista principal después de iniciar sesión
└── README.md                  # Documentación del proyecto
```


## Requisitos

- PHP 7.4 o superior
- Servidor web (Apache recomendado)
- Base de datos MySQL/MariaDB
- XAMPP (para desarrollo local)

## Instalación

1. Clona este repositorio en tu carpeta htdocs de XAMPP.
2. Configura la base de datos en `config/database.php`.
3. Importa la estructura de la base de datos desde el archivo SQL proporcionado.
4. Accede a `http://localhost/Taxis/Index.php` en tu navegador.

## Funcionalidades

- Registro de nuevos usuarios (administradores y operadores)
- Validación de datos de registro
- Sistema de autenticación seguro
- Gestión de servicios de taxi
- Reportes y estadísticas de operación

## Seguridad

El sistema implementa las siguientes medidas de seguridad:
- Contraseñas almacenadas con hash seguro
- Validación de formularios tanto del lado del cliente como del servidor
- Protección contra inyección SQL mediante el uso de consultas preparadas
- Control de acceso basado en roles

## Uso

- Regístrate como nuevo usuario utilizando el formulario de registro.
- Inicia sesión con tus credenciales en el formulario de inicio de sesión.
- Después de iniciar sesión, accederás al panel de control según tu rol.

## Desarrollo Futuro

- Implementación de panel de administración
- Sistema de reportes avanzados
- Aplicación móvil para conductores
- API para integración con otros sistemas

## Licencia

Este proyecto está bajo la Licencia MIT.