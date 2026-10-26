# Gestión Local del Servicio de Cola de Videos (Remotion)

Este documento detalla la arquitectura, operación, comandos de control y resolución de problemas del servicio de cola dedicado a la generación de videos (`Reels`) en el entorno local.

---

## 1. Contexto y Arquitectura

### ¿Por qué una cola separada para videos?
El entorno de desarrollo web principal se ejecuta mediante **Lerd** en un contenedor Podman basado en **Alpine Linux**. Sin embargo, Remotion necesita:
- **`glibc`**: Los binarios de Chrome Headless Shell no son compatibles con `musl libc` (Alpine).
- **`setpriv` completo (`util-linux`)**: Remotion utiliza el flag `--pdeathsig` para asegurar que Chrome se cierre si Node.js finaliza. BusyBox (el estándar de Alpine) no soporta esta bandera.
- **Acceso a aceleración gráfica y dependencias multimedia**: Librerías de Chrome, fuentes TrueType y FFmpeg.

### División de responsabilidades
| Componente | Cola | Entorno | Motor / Intérprete |
| :--- | :--- | :--- | :--- |
| **Worker General (Lerd)** | `default` | Contenedor Alpine Podman | PHP 8.5 (FPM / CLI Lerd) |
| **Worker de Videos (Host)** | `videos` | Sistema Host Debian | PHP 8.4 nativo + Node.js (v22/v24) + Chrome |

Todos los trabajos de `GenerateReelPreviewJob` se despachan con `$this->onQueue('videos')`, quedando aislados del worker de Alpine y siendo atendidos exclusivamente en el host.

---

## 2. Configuración del Servicio en el Host (`systemd --user`)

El worker corre como un servicio de usuario gestionado por `systemd`, lo que significa que:
- Inicia automáticamente al iniciar sesión en el equipo.
- Se reinicia solo si ocurre algún error inesperado.
- No requiere dejar terminales abiertas en segundo plano.

### Ubicación del archivo de unidad
`~/.config/systemd/user/blog-queue-videos.service`

### Definición del servicio
```ini
[Unit]
Description=Blog Video Queue Worker (Remotion Host)
After=network.target

[Service]
Type=simple
WorkingDirectory=/home/eormeno/Escritorio/blog
Environment="PATH=/home/eormeno/.local/share/lerd/bin:/home/eormeno/.local/bin:/usr/local/bin:/usr/bin:/bin"
ExecStart=/usr/bin/php artisan queue:work --queue=videos --tries=1 --timeout=1200
Restart=always
RestartSec=3
SuccessExitStatus=1 130 143

[Install]
WantedBy=default.target
```

---

## 3. Comandos de Gestión Rápida

Todos los comandos se ejecutan sin `sudo`, usando el flag `--user`:

### Ver estado en tiempo real
```bash
systemctl --user status blog-queue-videos.service
```

### Monitorear logs en vivo (salida de renderizado y jobs)
```bash
journalctl --user -u blog-queue-videos.service -f
```

### Reiniciar el worker (ej. tras modificar código de Remotion o Jobs)
```bash
systemctl --user restart blog-queue-videos.service
```

### Detener el worker
```bash
systemctl --user stop blog-queue-videos.service
```

### Iniciar el worker
```bash
systemctl --user start blog-queue-videos.service
```

### Deshabilitar / Habilitar autoinicio con el sistema
```bash
# Deshabilitar autoinicio
systemctl --user disable blog-queue-videos.service

# Habilitar autoinicio
systemctl --user enable blog-queue-videos.service
```

---

## 4. Modo de Depuración Manual (Interactiva)

Si estás desarrollando o depurando animaciones de Remotion y quieres ver la barra de progreso de renderizado frame por frame directamente en tu terminal:

1. **Detén temporalmente el servicio en segundo plano:**
   ```bash
   systemctl --user stop blog-queue-videos.service
   ```

2. **Ejecuta el worker en primer plano:**
   ```bash
   composer queue:videos
   ```
   *(O mediante el comando directo: `/usr/bin/php artisan queue:work --queue=videos --tries=1 --timeout=1200`)*

3. **Al finalizar tus pruebas**, vuelve a activar el servicio en segundo plano:
   ```bash
   systemctl --user start blog-queue-videos.service
   ```

---

## 5. Salidas y Rutas de Archivos

- **Directorio de salida de videos:** `storage/app/public/reels/`
  - Formato: `preview_{jobId}_v{versionNumber}.mp4`
  - Acceso web: `http://localhost/storage/reels/preview_{jobId}_v{versionNumber}.mp4`
- **Archivos temporales JSON de props:** `storage/app/temp/reel_{jobId}_v{versionNumber}.json` *(se eliminan automáticamente al completar o fallar el render)*.
- **Motor Remotion:** `video-engine/`

---

## 6. Resolución de Problemas Frecuentes

### 1. El Reel queda indefinidamente en estado "Generando..."
- Verifica si el servicio está activo:
  ```bash
  systemctl --user status blog-queue-videos.service
  ```
- Si está inactivo (`inactive` o `failed`), inícialo con `systemctl --user start blog-queue-videos.service`.

### 2. Jobs fallidos acumulados
- Ver si hay jobs fallidos en la base de datos:
  ```bash
  /usr/bin/php artisan queue:failed
  ```
- Reintentar todos los trabajos fallidos:
  ```bash
  /usr/bin/php artisan queue:retry all
  ```
- Vaciar la cola de fallidos:
  ```bash
  /usr/bin/php artisan queue:flush
  ```

### 3. Diferencia entre entornos (DevContainer / Host)
- **En tu Host Debian**: El servicio systemd se encarga de la cola `videos`.
- **En DevContainer / Servidores basados en `start.sh`**: El script `./start.sh` levanta automáticamente un worker que atiende todas las colas: `--queue=default,emails,videos`. No es necesario configurar systemd en esos entornos.

