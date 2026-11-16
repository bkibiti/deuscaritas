<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL sends everyone to /home (guests are then sent on to the login form).
     */
    public function test_the_root_url_redirects_to_the_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/home');
    }
}
