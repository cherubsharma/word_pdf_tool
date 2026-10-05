<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Convert Word to')
            ->assertSee('PDF Online.')
            ->assertSee('Drop your Word file here')
            ->assertSee('Select DOCX file')
            ->assertSee('HOW IT WORKS')
            ->assertSee('css/app.css')
            ->assertSee('js/app.js');
    }
}
