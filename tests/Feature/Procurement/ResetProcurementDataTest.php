<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResetProcurementDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_empties_procurement_data_and_keeps_users_and_settings(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ppmp-attachments/x/quote.pdf', 'pdf');

        $user = User::factory()->create();
        DB::table('settings')->insert(['key' => 'officials_lm_hope_user_id', 'value' => (string) $user->id]);
        activity()->causedBy($user)->log('something');

        $this->artisan('procurement:reset', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('activity_log', 0);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('settings', ['key' => 'officials_lm_hope_user_id']);
        Storage::disk('local')->assertMissing('ppmp-attachments/x/quote.pdf');
    }

    public function test_it_asks_before_emptying(): void
    {
        activity()->log('something');

        $this->artisan('procurement:reset')
            ->expectsConfirmation('Empty these tables? This cannot be undone.', 'no')
            ->assertSuccessful();

        $this->assertDatabaseCount('activity_log', 1);
    }
}
