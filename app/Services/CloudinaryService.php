<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    protected $cloudinary;
    protected $uploadApi;

    public function __construct()
    {
        // Configure Cloudinary using environment URL
        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');
        
        // Set environment variable for Cloudinary
        putenv("CLOUDINARY_URL=cloudinary://{$apiKey}:{$apiSecret}@{$cloudName}");

        $this->cloudinary = new Cloudinary();
        $this->uploadApi = new UploadApi();
    }

    /**
     * Upload profile image to Cloudinary
     *
     * @param UploadedFile $file
     * @param string $userId
     * @return array
     */
    public function uploadProfileImage(UploadedFile $file, string $userId): array
    {
        try {
            $publicId = 'profile_' . $userId . '_' . time();
            
            $result = $this->uploadApi->upload(
                $file->getRealPath(),
                [
                    'public_id' => $publicId,
                    'folder' => config('cloudinary.profile_images_folder', 'pis/profile_images'),
                    'transformation' => config('cloudinary.profile_image_transformations', [
                        'width' => 400,
                        'height' => 400,
                        'crop' => 'fill',
                        'gravity' => 'face',
                        'quality' => 'auto',
                        'format' => 'auto',
                    ]),
                    'resource_type' => 'image',
                ]
            );

            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
                'url' => $result['url'],
                'width' => $result['width'],
                'height' => $result['height'],
                'format' => $result['format'],
                'bytes' => $result['bytes'],
            ];

        } catch (\Exception $e) {
            Log::error('Cloudinary upload failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload base64 image to Cloudinary
     *
     * @param string $base64Image
     * @param string $userId
     * @return array
     */
    public function uploadBase64Image(string $base64Image, string $userId): array
    {
        try {
            $publicId = 'profile_' . $userId . '_' . time();
            
            // Remove data:image/...;base64, prefix if present
            if (strpos($base64Image, 'data:image/') === 0) {
                $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
            }
            
            $result = $this->uploadApi->upload(
                'data:image/jpeg;base64,' . $base64Image,
                [
                    'public_id' => $publicId,
                    'folder' => config('cloudinary.profile_images_folder', 'pis/profile_images'),
                    'transformation' => config('cloudinary.profile_image_transformations', [
                        'width' => 400,
                        'height' => 400,
                        'crop' => 'fill',
                        'gravity' => 'face',
                        'quality' => 'auto',
                        'format' => 'auto',
                    ]),
                    'resource_type' => 'image',
                ]
            );

            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
                'url' => $result['url'],
                'width' => $result['width'],
                'height' => $result['height'],
                'format' => $result['format'],
                'bytes' => $result['bytes'],
            ];

        } catch (\Exception $e) {
            Log::error('Cloudinary base64 upload failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete image from Cloudinary
     *
     * @param string $publicId
     * @return array
     */
    public function deleteImage(string $publicId): array
    {
        try {
            $result = $this->uploadApi->destroy($publicId);

            return [
                'success' => true,
                'result' => $result,
            ];

        } catch (\Exception $e) {
            Log::error('Cloudinary delete failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract public ID from Cloudinary URL
     *
     * @param string $url
     * @return string|null
     */
    public function extractPublicId(string $url): ?string
    {
        // Extract public ID from Cloudinary URL
        // Format: https://res.cloudinary.com/cloud_name/image/upload/v1234567890/folder/public_id.ext
        $pattern = '/\/upload\/(?:v\d+\/)?(.+?)(?:\.[^.]+)?$/';
        
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Upload task submission image to Cloudinary
     *
     * @param UploadedFile $file
     * @param string $userId
     * @param int $taskId
     * @return array
     */
    public function uploadTaskSubmissionImage(UploadedFile $file, string $userId, int $taskId): array
    {
        try {
            $publicId = 'task_submission_' . $userId . '_' . $taskId . '_' . time();
            
            $result = $this->uploadApi->upload(
                $file->getRealPath(),
                [
                    'public_id' => $publicId,
                    'folder' => config('cloudinary.task_submissions_folder', 'pis/task_submissions'),
                    'transformation' => config('cloudinary.task_submission_transformations', [
                        'width' => 800,
                        'height' => 600,
                        'crop' => 'limit',
                        'quality' => 'auto',
                        'format' => 'auto',
                    ]),
                    'resource_type' => 'image',
                ]
            );

            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
                'url' => $result['url'],
                'width' => $result['width'],
                'height' => $result['height'],
                'format' => $result['format'],
                'bytes' => $result['bytes'],
            ];

        } catch (\Exception $e) {
            Log::error('Cloudinary task submission upload failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload task submission image from base64 string to Cloudinary
     *
     * @param string $base64Image
     * @param string $userId
     * @param int $taskId
     * @return array
     */
    public function uploadTaskSubmissionImageFromBase64(string $base64Image, string $userId, int $taskId): array
    {
        try {
            $publicId = 'task_submission_' . $userId . '_' . $taskId . '_' . time();
            
            // Remove data:image/...;base64, prefix if present
            if (strpos($base64Image, 'data:image/') === 0) {
                $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
            }
            
            $result = $this->uploadApi->upload(
                'data:image/jpeg;base64,' . $base64Image,
                [
                    'public_id' => $publicId,
                    'folder' => config('cloudinary.task_submissions_folder', 'pis/task_submissions'),
                    'transformation' => config('cloudinary.task_submission_transformations', [
                        'width' => 800,
                        'height' => 600,
                        'crop' => 'limit',
                        'quality' => 'auto',
                        'format' => 'auto',
                    ]),
                    'resource_type' => 'image',
                ]
            );

            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
                'url' => $result['url'],
                'width' => $result['width'],
                'height' => $result['height'],
                'format' => $result['format'],
                'bytes' => $result['bytes'],
            ];

        } catch (\Exception $e) {
            Log::error('Cloudinary task submission base64 upload failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate optimized image URL with transformations
     *
     * @param string $publicId
     * @param array $transformations
     * @return string
     */
    public function getOptimizedUrl(string $publicId, array $transformations = []): string
    {
        $defaultTransformations = config('cloudinary.profile_image_transformations', []);
        $transformations = array_merge($defaultTransformations, $transformations);

        // Build transformation string
        $transformationString = '';
        if (!empty($transformations)) {
            $transformationString = implode(',', array_map(function($key, $value) {
                return "{$key}_{$value}";
            }, array_keys($transformations), $transformations));
        }

        return $this->cloudinary->image($publicId)
            ->resize(\Cloudinary\Transformation\Resize::fill($transformations['width'] ?? 400, $transformations['height'] ?? 400))
            ->quality($transformations['quality'] ?? 'auto')
            ->format($transformations['format'] ?? 'auto')
            ->toUrl();
    }
}
