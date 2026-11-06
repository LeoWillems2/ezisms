<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staat de assistenttoegang uit, dan bestaat er niets van
 * (implementatie/14b §1.2, §12.1). Uit is de stand van phpunit.xml.
 */
class AssistenttoegangUitTest extends TestCase
{
    use RefreshDatabase;

    public function test_zonder_isms_assistent_bestaan_de_routes_niet(): void
    {
        $this->assertFalse(config('assistent.aan'));

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertNotFound();
        $this->get('/oauth/authorize')->assertNotFound();
        $this->postJson('/oauth/register', [])->assertNotFound();
        $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
        $this->getJson('/.well-known/oauth-protected-resource')->assertNotFound();
    }
}
