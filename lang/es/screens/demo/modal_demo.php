<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Modales',
    'icon' => '🖼️',
    'actions' => [
        'open_confirmation' => 'Abrir Diálogo de Confirmación',
        'open_error' => 'Abrir Diálogo de Error',
        'open_timeout_with_button' => 'Abrir Diálogo con Tiempo (10 seg)',
        'open_timeout_without_button' => 'Abrir Timeout sin botón',
        'settings' => 'Configuración',
        'open_user_modal' => 'Abrir Pantalla como Modal',
    ],
    'auto_close_dialog' => [
        'message' => 'Este diálogo se cerrará automáticamente en:',
        'title' => 'Auto cierre',
    ],
    'confirm_dialog' => [
        'cancel_label' => 'No, Cancelar',
        'confirm_label' => 'Sí, Continuar',
        'message' => '¿Seguro que deseas continuar con esta acción?',
        'title' => 'Confirmar Acción',
    ],
    'error_dialog' => [
        'message' => 'No se pudo conectar con el servidor.
Verifica tu conexión a internet e inténtalo nuevamente.',
        'title' => 'Error de conexión',
    ],
    'instruction' => 'Haz clic en el botón de abajo para abrir un diálogo de confirmación:',
    'result' => [
        'cancelled' => 'Acción cancelada por el usuario',
        'confirmed' => 'Acción confirmada! Tipo: :type',
        'user_saved' => 'Usuario guardado desde el modal: :name (:role, :email)',
    ],
    'user_modal' => [
        'title' => 'Formulario de Usuario (Screen Modal)',
        'description' => 'Esta pantalla fue invocada como modal mediante openModal().',
        'name_label' => 'Nombre completo',
        'name_placeholder' => 'Ej: Juan Pérez',
        'name_required' => 'El nombre es obligatorio',
        'email_label' => 'Correo electrónico',
        'email_placeholder' => 'Ej: juan@ejemplo.com',
        'role_label' => 'Rol del usuario',
        'cancel' => 'Cancelar',
        'submit' => 'Guardar y Retornar',
    ],
    'settings_dialog' => [
        'message' => '¿Quieres resetear la configuración?
Esta acción no se puede deshacer.',
        'title' => 'Configuración',
    ],
    'success_dialog' => [
        'message' => 'La configuración se reseteó correctamente.',
        'title' => 'Completado!',
    ],
    'timeout_dialog' => [
        'message' => 'Este mensaje se cerrará automáticamente en:',
        'title' => 'Notificación Temporal',
    ],
    'title' => 'Demo de Componente Modal',
];
