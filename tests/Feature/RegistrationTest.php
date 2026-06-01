<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_phone_number()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'tenant',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'phone' => '1234567890',
        ]);
    }

    public function test_phone_number_is_required_for_registration()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'tenant',
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['phone' => ['The phone field is required.']]);
    }
}
