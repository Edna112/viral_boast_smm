<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateVapidKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webpush:generate-vapid-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate VAPID keys for Web Push notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating VAPID keys for Web Push notifications...');
        
        // Generate VAPID keys using OpenSSL
        $keyPair = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1'
        ]);
        
        if (!$keyPair) {
            $this->error('Failed to generate VAPID keys. Please ensure OpenSSL is properly configured.');
            return 1;
        }
        
        // Extract private key
        openssl_pkey_export($keyPair, $privateKey);
        
        // Extract public key
        $keyDetails = openssl_pkey_get_details($keyPair);
        $publicKey = $keyDetails['key'];
        
        // Convert to base64url format for VAPID
        $publicKeyPem = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r"], '', $publicKey);
        $publicKeyBinary = base64_decode($publicKeyPem);
        $publicKeyVapid = $this->base64urlEncode($publicKeyBinary);
        
        $privateKeyPem = str_replace(['-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', "\n", "\r"], '', $privateKey);
        $privateKeyBinary = base64_decode($privateKeyPem);
        $privateKeyVapid = $this->base64urlEncode($privateKeyBinary);
        
        $this->info('VAPID Keys Generated Successfully!');
        $this->newLine();
        
        $this->line('Add these to your .env file:');
        $this->newLine();
        
        $this->line('WEBPUSH_VAPID_PUBLIC_KEY=' . $publicKeyVapid);
        $this->line('WEBPUSH_VAPID_PRIVATE_KEY=' . $privateKeyVapid);
        $this->line('WEBPUSH_VAPID_SUBJECT=mailto:admin@viralboast.com');
        
        $this->newLine();
        $this->warn('Keep your private key secure and never expose it in client-side code!');
        
        return 0;
    }
    
    /**
     * Convert binary data to base64url format
     */
    private function base64urlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
