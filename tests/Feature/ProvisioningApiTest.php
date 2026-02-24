<?php

namespace Tests\Feature;

use App\Services\AccountProvisioner;
use Mockery;
use Tests\TestCase;

class ProvisioningApiTest extends TestCase
{
    public function test_it_rejects_missing_token(): void
    {
        $response = $this->postJson('/api/provision/accounts', [
            'display_name' => 'Provision User',
        ]);

        $response->assertStatus(401)->assertJson([
            'ok' => false,
            'error' => 'unauthorized',
        ]);
    }

    public function test_it_provisions_account_with_valid_token(): void
    {
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        putenv('LINKSTACK_PROVISION_TOKEN=provision-secret');

        $mock = Mockery::mock(AccountProvisioner::class);
        $mock->shouldReceive('provision')->once()->andReturn([
            'user_id' => 987,
            'public_url' => 'http://localhost/@provision-user',
        ]);
        $this->app->instance(AccountProvisioner::class, $mock);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer provision-secret',
            'Accept' => 'application/json',
        ])->postJson('/api/provision/accounts', [
            'display_name' => 'Provision User',
            'links' => [
                ['title' => 'Site', 'url' => 'https://example.com', 'order' => 1],
            ],
            'avatar' => ['type' => 'none'],
        ]);

        $response->assertStatus(201)->assertJson([
            'ok' => true,
            'user_id' => 987,
            'public_url' => 'http://localhost/@provision-user',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
