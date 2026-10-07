<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

Configuration::instance([
    'cloud' => [
        'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME'),
        'api_key'    => getenv('CLOUDINARY_API_KEY'),
        'api_secret' => getenv('CLOUDINARY_API_SECRET'),
    ],
    'url' => ['secure' => true]
]);

/**
 * Upload an image to Cloudinary and return the secure URL.
 */
function uploadToCloudinary($fileTmpPath, $folder = 'candidates') {
    try {
        $upload = new UploadApi();
        $result = $upload->upload($fileTmpPath, [
            'folder' => $folder,
            'resource_type' => 'image',
            'transformation' => [
                'width' => 500, 'height' => 500, 'crop' => 'fill', 'gravity' => 'face'
            ]
        ]);
        return $result['secure_url'];
    } catch (Exception $e) {
        error_log("Cloudinary upload error: " . $e->getMessage());
        return null;
    }
}

/**
 * Delete an image from Cloudinary by its URL.
 */
function deleteFromCloudinary($url) {
    if (empty($url)) return false;

    $parts = explode('/upload/', $url);
    if (count($parts) < 2) return false;

    $path = preg_replace('#^v\d+/#', '', $parts[1]);
    $path = preg_replace('#\.[a-zA-Z0-9]+$#', '', $path);

    try {
        $upload = new UploadApi();
        $upload->destroy($path);
        return true;
    } catch (Exception $e) {
        error_log("Cloudinary delete error: " . $e->getMessage());
        return false;
    }
}
