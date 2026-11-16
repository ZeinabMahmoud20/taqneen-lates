<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\AddInstallments;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddInstallmentsPageTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_users_who_can_create_contracts_can_open_the_page(): void
    {
        $this->actingAs($this->user(UserRole::Regulator));

        $this->get(route('filament.admin.pages.add-installments'))
            ->assertOk()
            ->assertSee('إضافة أقساط')
            ->assertSee('تحقق فقط');
    }

    public function test_leadership_users_cannot_open_the_page(): void
    {
        $this->actingAs($this->user(UserRole::Leadership));

        $this->get(route('filament.admin.pages.add-installments'))
            ->assertForbidden();
    }

    public function test_the_page_has_the_expected_permission_rule(): void
    {
        $this->actingAs($this->user(UserRole::Regulator));
        $this->assertTrue(AddInstallments::canAccess());

        $this->actingAs($this->user(UserRole::Leadership));
        $this->assertFalse(AddInstallments::canAccess());
    }
}
