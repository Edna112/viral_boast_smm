<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudinary Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Cloudinary image and video
    | management service. You can get these credentials from your Cloudinary
    | dashboard at https://cloudinary.com/console
    |
    */

    'cloud_name' => env('CLOUDINARY_CLOUD_NAME', 'dieqdgnyv'),
    'api_key' => env('CLOUDINARY_API_KEY', '212766687463963'),
    'api_secret' => env('CLOUDINARY_API_SECRET', 'Lzw7q8T74uIVtl_Dww87Q_nxkcU'),
    'secure' => env('CLOUDINARY_SECURE', true),
    
    /*
    |--------------------------------------------------------------------------
    | Default Upload Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for image uploads to Cloudinary
    |
    */
    
    'default_folder' => env('CLOUDINARY_DEFAULT_FOLDER', 'pis'),
    'profile_images_folder' => env('CLOUDINARY_PROFILE_IMAGES_FOLDER', 'pis_profile_images'),
    'task_submissions_folder' => env('CLOUDINARY_TASK_SUBMISSIONS_FOLDER', 'pis/task_submissions'),
    
    /*
    |--------------------------------------------------------------------------
    | Image Transformations
    |--------------------------------------------------------------------------
    |
    | Default transformations applied to uploaded images
    |
    */
    
    'profile_image_transformations' => [
        'width' => 400,
        'height' => 400,
        'crop' => 'fill',
        'gravity' => 'face',
        'quality' => 'auto',
        'format' => 'auto',
    ],
    
    'task_submission_transformations' => [
        'width' => 800,
        'height' => 600,
        'crop' => 'limit',
        'quality' => 'auto',
        'format' => 'auto',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Upload Options
    |--------------------------------------------------------------------------
    |
    | Additional options for file uploads
    |
    */
    
    'upload_options' => [
        'resource_type' => 'image',
        'allowed_formats' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'max_file_size' => 2048, // 2MB in KB
    ],
];
