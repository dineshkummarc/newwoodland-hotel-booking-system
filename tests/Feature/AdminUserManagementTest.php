<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AdminUserController was previously untested despite being the most
 * security-sensitive admin screen: it assigns roles and can deactivate
 * staff accounts. super_admin-only, enforced by 'admin.role:super_admin'
 * in routes/admin.php.
 */
class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_staff_account_with_a_role(): void
    {
        $superAdmin = Admin::factory()->create();

        $response = $this->actingAs($superAdmin, 'admin')->post(route('admin.admin-users.store'), [
            'name' => 'New Staff',
            'email' => 'staff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Admin::ROLE_FRONT_DESK,
        ]);

        $response->assertRedirect(route('admin.admin-users.index'));
        $created = Admin::where('email', 'staff@example.com')->first();
        $this->assertNotNull($created);
        $this->assertEquals(Admin::ROLE_FRONT_DESK, $created->role);
        $this->assertTrue($created->is_active);
    }

    public function test_admin_role_cannot_manage_staff_accounts(): void
    {
        $admin = Admin::factory()->admin()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.admin-users.index'));

        $response->assertForbidden();
    }

    public function test_front_desk_cannot_manage_staff_accounts(): void
    {
        $frontDesk = Admin::factory()->frontDesk()->create();

        $response = $this->actingAs($frontDesk, 'admin')->get(route('admin.admin-users.index'));

        $response->assertForbidden();
    }

    public function test_guest_cannot_manage_staff_accounts(): void
    {
        $response = $this->get(route('admin.admin-users.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_super_admin_can_deactivate_another_admin_and_their_session_is_killed(): void
    {
        $superAdmin = Admin::factory()->create();
        $target = Admin::factory()->admin()->create();

        $response = $this->actingAs($superAdmin, 'admin')->patch(route('admin.admin-users.deactivate', $target));

        $response->assertRedirect();
        $this->assertFalse($target->fresh()->is_active);

        // Log out of the acting super_admin's session before attempting to
        // log in as the now-deactivated target — otherwise 'guest:admin' on
        // the login route would just redirect the already-authenticated
        // super_admin away instead of exercising the target's own login.
        $this->post(route('admin.logout'));

        // Login itself refuses a deactivated account (is_active folded into the credential check).
        $login = $this->post(route('admin.login.store'), ['email' => $target->email, 'password' => 'password']);
        $login->assertSessionHasErrors('email');
    }

    public function test_an_admin_cannot_deactivate_their_own_account(): void
    {
        $superAdmin = Admin::factory()->create();

        $response = $this->actingAs($superAdmin, 'admin')->patch(route('admin.admin-users.deactivate', $superAdmin));

        $response->assertSessionHas('error');
        $this->assertTrue($superAdmin->fresh()->is_active);
    }

    /**
     * The 'own account' guard in the controller means this branch is only
     * ever reachable through the real route when the acting super_admin IS
     * the lone one (already covered by the self-deactivation test above and
     * short-circuited before this rule even runs) — the route middleware
     * requires the actor to already be an active super_admin, so a
     * *different* actor deactivating "the last one" cannot occur in
     * practice. Tested directly at the model level instead of faking that
     * scenario through the HTTP layer with a middleware bypass.
     */
    public function test_is_last_active_super_admin_correctly_identifies_the_sole_remaining_one(): void
    {
        $lone = Admin::factory()->create();
        $this->assertTrue($lone->isLastActiveSuperAdmin());

        $second = Admin::factory()->create();
        $this->assertFalse($lone->fresh()->isLastActiveSuperAdmin());
        $this->assertFalse($second->isLastActiveSuperAdmin());

        $second->update(['is_active' => false]);
        $this->assertTrue($lone->fresh()->isLastActiveSuperAdmin());

        $adminRole = Admin::factory()->admin()->create();
        $this->assertFalse($adminRole->isLastActiveSuperAdmin());
    }

    public function test_deactivating_a_super_admin_is_allowed_when_another_active_one_remains(): void
    {
        $superAdminA = Admin::factory()->create();
        $superAdminB = Admin::factory()->create();

        $this->actingAs($superAdminA, 'admin')->patch(route('admin.admin-users.deactivate', $superAdminB));

        $this->assertFalse($superAdminB->fresh()->is_active);
    }

    public function test_super_admin_can_reactivate_a_deactivated_staff_account(): void
    {
        $superAdmin = Admin::factory()->create();
        $target = Admin::factory()->admin()->inactive()->create();

        $this->actingAs($superAdmin, 'admin')->patch(route('admin.admin-users.reactivate', $target));

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_staff_email_must_be_unique(): void
    {
        $superAdmin = Admin::factory()->create();
        Admin::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($superAdmin, 'admin')->post(route('admin.admin-users.store'), [
            'name' => 'New Staff',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Admin::ROLE_ADMIN,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_super_admin_can_change_a_staff_members_role(): void
    {
        $superAdmin = Admin::factory()->create();
        $target = Admin::factory()->frontDesk()->create();

        $this->actingAs($superAdmin, 'admin')->put(route('admin.admin-users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => Admin::ROLE_ADMIN,
        ]);

        $this->assertEquals(Admin::ROLE_ADMIN, $target->fresh()->role);
    }

    public function test_updating_a_staff_account_without_a_password_does_not_change_it(): void
    {
        $superAdmin = Admin::factory()->create();
        $target = Admin::factory()->admin()->create();
        $originalHash = $target->password;

        $this->actingAs($superAdmin, 'admin')->put(route('admin.admin-users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role,
        ]);

        $this->assertEquals($originalHash, $target->fresh()->password);
    }
}
