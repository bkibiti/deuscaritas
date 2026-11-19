<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        // "View Settings" lives in the hand-managed database, not in a migration.
        $this->admin->givePermissionTo(Permission::firstOrCreate(['name' => 'View Settings', 'guard_name' => 'web']));

        foreach (['group_name', 'registration_number', 'slogan'] as $index => $name) {
            DB::table('settings')->insert(['id' => $index + 1, 'name' => $name, 'value' => null]);
        }
    }

    public function test_the_settings_page_has_no_logo_field(): void
    {
        $response = $this->actingAs($this->admin)->get(route('configurations.index'));

        $response->assertOk()->assertSee('name="group_name"', false)->assertDontSee('name="logo"', false)->assertDontSee('type="file"', false);
    }

    public function test_settings_sit_under_the_masters_menu(): void
    {
        $html = $this->actingAs($this->admin)->get(route('configurations.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/Administration <i class="nav-arrow.*?<ul class="nav nav-treeview">.*?'.preg_quote(route('configurations.index'), '/').'.*?<\/ul>/s', $html);
        $this->assertSame(1, substr_count($html, 'href="'.route('configurations.index').'" class="nav-link'), 'Settings has one menu entry.');
        $this->assertStringContainsString('Administration / Settings', $html);
    }

    public function test_settings_are_saved(): void
    {
        $this->actingAs($this->admin)->post(route('configurations.update'), [
            'group_name' => 'Deus Caritas Schools',
            'slogan' => 'Education for Integral Formation',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', ['name' => 'group_name', 'value' => 'Deus Caritas Schools']);
        $this->assertDatabaseHas('settings', ['name' => 'slogan', 'value' => 'Education for Integral Formation']);
        $this->assertSame('Deus Caritas Schools', groupName());
    }
}
