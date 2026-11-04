<?php

namespace Tests\Feature;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebSecurityTest extends TestCase
{
    private const SESSION_COOKIE = 'mealplaner_audit_session';

    private const PROBE = '/api/_audit/session';

    private string $sessionDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionDirectory = sys_get_temp_dir().'/mealplaner-session-audit-'.bin2hex(random_bytes(8));
        mkdir($this->sessionDirectory, 0700);
        $this->configureAuditRoutes();
        $this->withCredentials();
    }

    protected function tearDown(): void
    {
        if (isset($this->sessionDirectory)) {
            foreach (glob($this->sessionDirectory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->sessionDirectory);
        }

        parent::tearDown();
    }

    public function test_an_anonymous_file_session_survives_a_new_application_instance(): void
    {
        $this->startBrowserSession()->assertExactJson(['visits' => 1]);

        $this->refreshApplication();
        $this->configureAuditRoutes();

        $this->getJson(self::PROBE)->assertOk()->assertExactJson(['visits' => 2]);
    }

    public function test_the_session_cookie_has_http_only_and_same_site_protection(): void
    {
        $cookie = $this->startBrowserSession()->getCookie(self::SESSION_COOKIE, false);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
    }

    public function test_a_tampered_cookie_cannot_reuse_the_previous_session(): void
    {
        $this->startBrowserSession();
        $this->withUnencryptedCookie(self::SESSION_COOKIE, 'tampered-audit-cookie');
        $this->refreshApplication();
        $this->configureAuditRoutes();

        $this->getJson(self::PROBE)->assertOk()->assertExactJson(['visits' => 1]);
    }

    public function test_secure_cookie_configuration_applies_to_session_and_xsrf_cookies(): void
    {
        config(['session.secure' => true]);
        $response = $this->getJson('https://localhost'.self::PROBE)->assertOk();
        $session = $response->getCookie(self::SESSION_COOKIE, false);
        $xsrf = $response->getCookie('XSRF-TOKEN', false);

        $this->assertNotNull($session);
        $this->assertNotNull($xsrf);
        $this->assertTrue($session->isSecure());
        $this->assertTrue($session->isHttpOnly());
        $this->assertSame('lax', $session->getSameSite());
        $this->assertTrue($xsrf->isSecure());
        $this->assertFalse($xsrf->isHttpOnly());
    }

    /** @param array<string, string> $headers */
    #[DataProvider('invalidCsrfRequests')]
    public function test_web_mutations_reject_invalid_request_forgery_credentials(array $headers): void
    {
        $this->startBrowserSession();

        $this->postJson(self::PROBE, [], $headers)->assertStatus(419);
    }

    public function test_a_matching_csrf_token_header_allows_the_web_mutation(): void
    {
        $this->startBrowserSession();
        $token = $this->app->make('session.store')->token();

        $this->postJson(self::PROBE, [], ['X-CSRF-TOKEN' => $token])
            ->assertOk()->assertExactJson(['accepted' => true]);
    }

    public function test_the_encrypted_xsrf_cookie_is_accepted_as_a_request_header(): void
    {
        $cookie = $this->startBrowserSession()->getCookie('XSRF-TOKEN', false);
        $this->assertNotNull($cookie);

        $this->postJson(self::PROBE, [], ['X-XSRF-TOKEN' => $cookie->getValue()])
            ->assertOk()->assertExactJson(['accepted' => true]);
    }

    public function test_laravel_accepts_same_origin_fetch_metadata_without_a_token_header(): void
    {
        $this->startBrowserSession();

        $this->postJson(self::PROBE, [], ['Sec-Fetch-Site' => 'same-origin'])
            ->assertOk()->assertExactJson(['accepted' => true]);
    }

    public function test_the_native_session_guard_rejects_a_guest_on_a_json_route(): void
    {
        $this->getJson('/api/_audit/protected')
            ->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function invalidCsrfRequests(): array
    {
        return [
            'missing token and metadata' => [[]],
            'cross-site without token' => [['Sec-Fetch-Site' => 'cross-site']],
            'cross-site with wrong token' => [[
                'Sec-Fetch-Site' => 'cross-site', 'X-CSRF-TOKEN' => 'invalid-audit-token',
            ]],
        ];
    }

    private function startBrowserSession(): TestResponse
    {
        $response = $this->getJson(self::PROBE)->assertOk();
        $cookie = $response->getCookie(self::SESSION_COOKIE, false);
        $this->assertNotNull($cookie);
        $this->withUnencryptedCookie(self::SESSION_COOKIE, $cookie->getValue());

        return $response;
    }

    private function configureAuditRoutes(): void
    {
        config([
            'session.driver' => 'file',
            'session.files' => $this->sessionDirectory,
            'session.cookie' => self::SESSION_COOKIE,
            'session.lottery' => [0, 100],
        ]);

        // Preserve testing mode/database guards; bypass only Laravel's CSRF test shortcut.
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app->make(Encrypter::class)) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });

        // These routes exist only in this test application, never in production routing.
        Route::middleware('web')->get(self::PROBE, function (Request $request) {
            $visits = (int) $request->session()->get('audit_visits', 0) + 1;
            $request->session()->put('audit_visits', $visits);

            return response()->json(['visits' => $visits]);
        });
        Route::middleware('web')->post(self::PROBE, fn () => response()->json(['accepted' => true]));
        Route::middleware(['web', 'auth'])->get('/api/_audit/protected', fn () => response()->json(['private' => true]));
    }
}
