<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Uploader Demo',
    'icon' => '',
    'banner' => [
        'confirm' => 'Confirm Banner',
        'label' => 'Banner for posts - 256x144px',
        'processed_header' => 'Banner processed successfully (16:9):

',
        'title' => 'Horizontal banner (16:9 - Size 3)',
    ],
    'documents' => [
        'confirm' => 'Confirm Documents',
        'label' => 'Select documents (PDF, Word, Excel)',
        'processed_header' => 'Documents processed successfully:

',
        'title' => 'Document upload',
    ],
    'errors' => [
        'no_banner' => 'No banner to process',
        'no_documents' => 'No documents to process',
        'no_images' => 'No images to process',
        'no_profile' => 'No profile photo to process',
        'no_story' => 'No story to process',
        'temp_not_found' => 'Temporary file not found',
    ],
    'images' => [
        'confirm' => 'Confirm Images',
        'label' => 'Select multiple images',
        'processed_header' => 'Images processed successfully:

',
        'title' => 'Multiple image upload (no aspect ratio)',
    ],
    'instruction' => 'Try the file uploader component',
    'profile' => [
        'confirm' => 'Confirm Profile Photo',
        'label' => 'Square profile image - 192x192px',
        'processed_header' => 'Profile photo processed successfully:

',
        'title' => 'Profile image (1:1 - Size 2)',
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
        'initial' => 'Upload files to see results',
        'path_line' => '   → :path',
        'path_line_with_spacing' => '   → :path

',
    ],
    'separator' => '───────────────────────────',
    'story' => [
        'confirm' => 'Confirm Story',
        'label' => 'Story image - 108x192px',
        'processed_header' => 'Story processed successfully (9:16):

',
        'title' => 'Vertical story (9:16 - Size 2)',
    ],
    'title' => 'Uploader Component Demo',
];
