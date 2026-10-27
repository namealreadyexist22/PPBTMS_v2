<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionExpiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_page_is_sent_to_the_login_page(): void
    {
        $this->get(route('procurement.item-requests.index'))->assertRedirect(route('auth.login'));
    }

    public function test_a_background_request_is_told_the_session_expired_instead_of_getting_the_login_page(): void
    {
        // A modal or a list redrawn in place (jQuery sends X-Requested-With)
        $this->get(route('procurement.item-requests.entry'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(401)->assertJsonPath('message', 'Your session has expired. Please log in again.')
            ->assertJsonPath('login', route('auth.login'));

        // fetch() / JSON posts
        $this->postJson('/core/notifications/read-all')->assertStatus(401);
    }
}
