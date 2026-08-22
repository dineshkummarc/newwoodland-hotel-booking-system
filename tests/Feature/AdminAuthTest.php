<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_login_succeeds(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret123')]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_invalid_login_fails(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret123')]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_unauthenticated_admin_cannot_access_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_admin_can_access_dashboard(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
    }

    public function test_logout_works(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');
        $response = $this->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_admin_guard_does_not_grant_access_to_web_guarded_users(): void
    {
        $response = $this->get(route('admin.rooms.index'));

        $response->assertRedirect(route('admin.login'));
    }
}
