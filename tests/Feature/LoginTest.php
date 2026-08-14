<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_login_after_ensure_command()
    {
        // Run the artisan command to ensure admins are seeded/updated
        Artisan::call('admin:ensure');

        // Try to log in
        $response = $this->postJson('/api/auth/login', [
            'email' => 'superadmin@upms.com',
            'password' => 'asdfghj69.',
        ]);

        // Debug output if it fails
        if ($response->status() !== 200) {
            fwrite(STDERR, "Login failed. Status: " . $response->status() . "\n");
            fwrite(STDERR, "Response body: " . $response->getContent() . "\n");
        }

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user' => [
                'id',
                'name',
                'email',
                'role',
            ]
        ]);
    }

    public function test_admin_can_login_after_ensure_command()
    {
        Artisan::call('admin:ensure');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
    }
}
