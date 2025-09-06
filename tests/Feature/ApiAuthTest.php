<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_registration()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ]);

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'userId',
                         'email',
                         'referralCode'
                     ]
                 ]);
    }

    public function test_user_registration_with_phone()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone' => '+1234567890',
            'password' => 'Password123!'
        ]);

        $response->assertStatus(201)
                 ->assertJson(['success' => true]);
    }

    public function test_user_registration_with_referral_code()
    {
        // First create a user to get a referral code
        $referrer = $this->postJson('/api/v1/auth/register', [
            'name' => 'Referrer User',
            'email' => 'referrer@example.com',
            'password' => 'Password123!'
        ]);

        $referralCode = $referrer->json('data.referralCode');

        // Register new user with referral code
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'referralCode' => $referralCode
        ]);

        $response->assertStatus(201)
                 ->assertJson(['success' => true]);
    }

    public function test_user_login_with_email()
    {
        // First register a user
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ]);

        // Then login
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'success',
                     'token',
                     'user' => [
                         'id',
                         'name',
                         'email',
                         'referralCode',
                         'emailVerified',
                         'phoneVerified'
                     ]
                 ]);
    }

    public function test_user_login_with_phone()
    {
        // First register a user with phone
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone' => '+1234567890',
            'password' => 'Password123!'
        ]);

        // Then login with phone
        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+1234567890',
            'password' => 'Password123!'
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    public function test_email_verification()
    {
        // Register a user
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ]);

        // Get verification code from database (simplified for testing)
        $user = \App\Models\User::where('email', 'test@example.com')->first();
        $verificationCode = $user->email_verification_code;

        // Verify email
        $response = $this->postJson('/api/v1/auth/verify-email', [
            'email' => 'test@example.com',
            'code' => $verificationCode
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    public function test_resend_verification()
    {
        // Register a user
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ]);

        // Clear the verification code to avoid rate limiting
        $user = \App\Models\User::where('email', 'test@example.com')->first();
        $user->update([
            'email_verification_code' => null,
            'email_verification_expires_at' => null
        ]);

        // Resend verification
        $response = $this->postJson('/api/v1/auth/resend-verification', [
            'email' => 'test@example.com'
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    public function test_forgot_password()
    {
        // Register a user
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ]);

        // Request password reset
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'test@example.com'
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    public function test_get_user_profile()
    {
        // Register and login to get token
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ]);

        $token = $loginResponse->json('token');

        // Get profile
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'id',
                     'name',
                     'email',
                     'referral_code'
                 ]);
    }

    public function test_logout()
    {
        // Register and login to get token
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ]);

        $token = $loginResponse->json('token');

        // Logout
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    public function test_invalid_credentials()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(401)
                 ->assertJson(['success' => false]);
    }

    public function test_duplicate_email_registration()
    {
        // Register first user
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ]);

        // Try to register with same email
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another User',
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ]);

        $response->assertStatus(409)
                 ->assertJson(['success' => false]);
    }
}