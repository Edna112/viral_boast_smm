# Cloudinary Setup Guide

## 🚀 **Cloudinary Integration for Profile Images**

This guide will help you set up Cloudinary for storing profile images in your Laravel application.

## 📋 **Prerequisites**

1. **Cloudinary Account**: Sign up at [cloudinary.com](https://cloudinary.com)
2. **Laravel Application**: Your existing Laravel app with profile management

## 🔧 **Setup Steps**

### **1. Get Cloudinary Credentials**

1. Log in to your [Cloudinary Dashboard](https://cloudinary.com/console)
2. Go to **Dashboard** → **Product Environment Credentials**
3. Copy the following values:
    - **Cloud Name**
    - **API Key**
    - **API Secret**

### **2. Configure Environment Variables**

Add these variables to your `.env` file:

```env
# Cloudinary Configuration
CLOUDINARY_CLOUD_NAME=your_cloud_name_here
CLOUDINARY_API_KEY=your_api_key_here
CLOUDINARY_API_SECRET=your_api_secret_here
CLOUDINARY_SECURE=true
CLOUDINARY_DEFAULT_FOLDER=pis
CLOUDINARY_PROFILE_IMAGES_FOLDER=pis/profile_images
```

### **3. Install Dependencies**

The Cloudinary PHP SDK is already installed. If you need to reinstall:

```bash
composer require cloudinary/cloudinary_php
```

### **4. Configuration Files**

The following files have been created/updated:

-   ✅ `config/cloudinary.php` - Cloudinary configuration
-   ✅ `app/Services/CloudinaryService.php` - Cloudinary service class
-   ✅ `app/Http/Controllers/Api/ProfileController.php` - Updated to use Cloudinary

## 🎯 **Features Implemented**

### **✅ Automatic Image Optimization**

-   **Resize**: 400x400 pixels
-   **Crop**: Fill with face detection
-   **Quality**: Auto-optimized
-   **Format**: Auto (WebP when supported)

### **✅ Smart Image Management**

-   **Upload**: Direct to Cloudinary with transformations
-   **Delete**: Automatic cleanup of old images
-   **URL Generation**: Optimized URLs with transformations

### **✅ Backward Compatibility**

-   **Mixed Storage**: Handles both Cloudinary and local storage
-   **Migration Ready**: Existing local images still work
-   **Fallback Support**: Graceful handling of upload failures

## 📁 **File Structure**

```
pis_smm/
├── config/
│   └── cloudinary.php              ← Cloudinary configuration
├── app/
│   ├── Services/
│   │   └── CloudinaryService.php   ← Cloudinary service class
│   └── Http/Controllers/Api/
│       └── ProfileController.php   ← Updated controller
└── .env                            ← Environment variables
```

## 🔄 **API Usage**

### **Upload Profile Image**

```http
PUT /api/v1/profile
Content-Type: multipart/form-data
Authorization: Bearer {token}

{
  "name": "John Doe",
  "email": "john@example.com",
  "profile_image": [file upload]
}
```

### **Response**

```json
{
    "success": true,
    "message": "Profile updated successfully",
    "data": {
        "user": {
            "profile_image": "https://res.cloudinary.com/your_cloud/image/upload/v1234567890/pis/profile_images/profile_uuid_timestamp.jpg"
        }
    }
}
```

## 🛠️ **Configuration Options**

### **Image Transformations**

Edit `config/cloudinary.php` to customize:

```php
'profile_image_transformations' => [
    'width' => 400,           // Image width
    'height' => 400,          // Image height
    'crop' => 'fill',         // Crop mode
    'gravity' => 'face',      // Face detection
    'quality' => 'auto',      // Auto quality
    'format' => 'auto',       // Auto format (WebP)
],
```

### **Upload Settings**

```php
'upload_options' => [
    'resource_type' => 'image',
    'allowed_formats' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'max_file_size' => 2048, // 2MB in KB
],
```

## 🧪 **Testing**

### **1. Test Upload**

```bash
curl -X PUT "http://127.0.0.1:8000/api/v1/profile" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "name=Test User" \
  -F "email=test@example.com" \
  -F "profile_image=@/path/to/image.jpg"
```

### **2. Test Response**

Check that the response includes a Cloudinary URL:

```json
{
    "profile_image": "https://res.cloudinary.com/your_cloud/image/upload/..."
}
```

## 🔒 **Security Features**

-   ✅ **File Type Validation**: Only image files allowed
-   ✅ **Size Limits**: 2MB maximum file size
-   ✅ **Secure URLs**: HTTPS by default
-   ✅ **Access Control**: Private folder structure

## 📊 **Benefits**

### **Performance**

-   ⚡ **CDN Delivery**: Global content delivery
-   🖼️ **Auto Optimization**: WebP format, quality optimization
-   📱 **Responsive Images**: Multiple sizes available

### **Reliability**

-   🔄 **Automatic Backups**: Built-in redundancy
-   🛡️ **DDoS Protection**: Enterprise-grade security
-   📈 **Scalability**: Handles traffic spikes

### **Cost Efficiency**

-   💰 **Pay-as-you-go**: Only pay for what you use
-   🎯 **Smart Transformations**: Reduce bandwidth usage
-   📉 **Storage Optimization**: Automatic compression

## 🚨 **Troubleshooting**

### **Common Issues**

1. **Invalid Credentials**

    ```
    Error: Invalid cloud_name, api_key, or api_secret
    ```

    **Solution**: Check your `.env` file credentials

2. **Upload Failed**

    ```
    Error: Failed to upload profile image
    ```

    **Solution**: Check file size and format

3. **Delete Failed**
    ```
    Error: Cloudinary delete failed
    ```
    **Solution**: Check if image exists in Cloudinary

### **Debug Mode**

Enable logging in `config/logging.php`:

```php
'channels' => [
    'cloudinary' => [
        'driver' => 'single',
        'path' => storage_path('logs/cloudinary.log'),
        'level' => 'debug',
    ],
],
```

## 📞 **Support**

-   **Cloudinary Docs**: [cloudinary.com/documentation](https://cloudinary.com/documentation)
-   **Laravel Integration**: [cloudinary.com/documentation/laravel_integration](https://cloudinary.com/documentation/laravel_integration)
-   **API Reference**: [cloudinary.com/documentation/image_transformation_reference](https://cloudinary.com/documentation/image_transformation_reference)

---

## ✅ **Setup Complete!**

Your Laravel application is now configured to use Cloudinary for profile image storage. Images will be automatically optimized, delivered via CDN, and managed efficiently.

**Next Steps:**

1. Add your Cloudinary credentials to `.env`
2. Test the profile image upload
3. Monitor usage in your Cloudinary dashboard
