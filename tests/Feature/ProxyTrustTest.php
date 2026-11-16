<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class ProxyTrustTest extends StoreTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.trusted_proxies' => [],
            'app.trust_proxy_headers' => true,
            'app.trusted_hosts' => ['localhost'],
        ]);
    }

    public function test_rightmost_forwarded_address_is_used_as_client_ip(): void
    {
        $this->probeRoute();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.66, 203.0.113.9'])
            ->get('/__proxy-probe')
            ->assertOk()
            ->assertJson(['ip' => '203.0.113.9']);
    }

    public function test_spoofed_leftmost_forwarded_address_is_ignored(): void
    {
        $this->probeRoute();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders(['X-Forwarded-For' => '6.6.6.6, 203.0.113.9'])
            ->get('/__proxy-probe')
            ->assertOk();

        $this->assertSame('203.0.113.9', $response->json('ip'));
    }

    public function test_forwarded_header_is_ignored_when_proxy_trust_is_disabled(): void
    {
        config(['app.trust_proxy_headers' => false]);
        $this->probeRoute();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->get('/__proxy-probe')
            ->assertOk()
            ->assertJson(['ip' => '10.0.0.5']);
    }

    public function test_invalid_forwarded_entries_fall_back_to_the_rightmost_valid_address(): void
    {
        $this->probeRoute();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders(['X-Forwarded-For' => 'not-an-ip, 198.51.100.5, also-bad'])
            ->get('/__proxy-probe')
            ->assertOk()
            ->assertJson(['ip' => '198.51.100.5']);
    }

    public function test_forwarded_proto_controls_https_detection(): void
    {
        $this->probeRoute();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.9',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/__proxy-probe')
            ->assertOk()
            ->assertJson(['secure' => true]);
    }

    public function test_rate_limiting_cannot_be_bypassed_with_different_spoofed_leftmost_values(): void
    {
        $this->probeRoute();

        $headers = fn (string $spoofed) => [
            'X-Forwarded-For' => $spoofed.', 203.0.113.10',
        ];

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders($headers('1.1.1.1'))
            ->get('/__proxy-probe')
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders($headers('2.2.2.2'))
            ->get('/__proxy-probe')
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders($headers('3.3.3.3'))
            ->get('/__proxy-probe')
            ->assertStatus(429);
    }

    public function test_untrusted_host_header_is_rejected(): void
    {
        config(['app.trusted_hosts' => ['store.example.test']]);
        $this->probeRoute();

        $this->get('http://store.example.test/__proxy-probe')->assertOk();

        $this->get('http://attacker.example.test/__proxy-probe')->assertStatus(400);
    }

    private function probeRoute(): void
    {
        Route::middleware(['web', 'throttle:2,1'])->get('/__proxy-probe', function (Request $request) {
            return response()->json([
                'ip' => $request->ip(),
                'secure' => $request->secure(),
            ]);
        });
    }
}
