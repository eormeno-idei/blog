<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Uploader Demo',
    'icon' => '',
    'banner' => [
        'confirm' => 'Confirmar Banner',
        'label' => 'Banner para posts - 256x144px',
        'processed_header' => 'Banner procesado exitosamente (16:9):

',
        'title' => 'Banner horizontal (16:9 - Tamaño 3)',
    ],
    'documents' => [
        'confirm' => 'Confirmar Documentos',
        'label' => 'Selecciona documentos (PDF, Word, Excel)',
        'processed_header' => 'Documentos procesados exitosamente:

',
        'title' => 'Carga de documentos',
    ],
    'errors' => [
        'no_banner' => 'No hay banner para procesar',
        'no_documents' => 'No hay documentos para procesar',
        'no_images' => 'No hay imágenes para procesar',
        'no_profile' => 'No hay foto de perfil para procesar',
        'no_story' => 'No hay story para procesar',
        'temp_not_found' => 'Archivo temporal no encontrado',
    ],
    'images' => [
        'confirm' => 'Confirmar Imágenes',
        'label' => 'Selecciona múltiples imágenes',
        'processed_header' => 'Imágenes procesadas exitosamente:

',
        'title' => 'Carga múltiple de imágenes (sin aspect ratio)',
    ],
    'instruction' => 'Prueba el componente de carga de archivos',
    'profile' => [
        'confirm' => 'Confirmar Foto de Perfil',
        'label' => 'Foto de perfil cuadrada - 192x192px',
        'processed_header' => 'Foto de perfil procesada exitosamente:

',
        'title' => 'Imagen de perfil (1:1 - Tamaño 2)',
    ],
    'result' => [
        'file_line_banner' => '🖼️ :name (:size MB)
',
        'file_line_document' => ':emoji :name (:size MB)
',
        'file_line_photo' => '📸 :name (:size MB)
',
        'file_line_story' => '📱 :name (:size MB)
',
        'initial' => 'Sube archivos para ver resultados',
        'path_line' => '   → :path',
        'path_line_with_spacing' => '   → :path

',
    ],
    'separator' => '───────────────────────────',
    'story' => [
        'confirm' => 'Confirmar Story',
        'label' => 'Imagen para stories - 108x192px',
        'processed_header' => 'Story procesada exitosamente (9:16):

',
        'title' => 'Story vertical (9:16 - Tamaño 2)',
    ],
    'title' => 'Demo de Componente Uploader',
];
