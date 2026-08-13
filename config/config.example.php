<?php
return [
    'app_name' => 'Revisa Mi Vivienda',
    'base_url' => '',
    'db' => [
        'host' => 'localhost',
        'name' => 'quake_damage',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    // En Hostinger todo el proyecto vive dentro de public_html.
    'uploads_dir' => __DIR__ . '/../uploads',
    'max_photos' => 8,
    'max_photo_mb' => 5,
    'max_active_cases' => 5,
    'assignment_hours' => 48,
];
