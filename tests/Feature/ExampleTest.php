<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL sends visitors to the admin panel (which redirects on to the
     * login page when signed out).
     */
    public function test_the_root_redirects_to_the_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }
}
