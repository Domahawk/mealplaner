<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        config(['hashing.bcrypt.rounds' => 4]);
    }

    public function test_direct_guest_requests_cannot_bypass_authentication_with_client_identifiers(): void
    {
        $this->get('/account?user_id=1')->assertRedirect('/login');
        $this->getJson('/account')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
        $this->get('/api/auth/user?user_id=1', ['Accept' => 'text/html', 'X-User-Id' => '1'])
            ->assertUnauthorized()->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    #[DataProvider('publicRoutes')]
    public function test_public_routes_remain_accessible_to_guests(string $path): void
    {
        $this->get($path)->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function publicRoutes(): array
    {
        return [
            'home' => ['/'],
            'about' => ['/about'],
            'login' => ['/login'],
            'registration' => ['/register'],
            'session bootstrap' => ['/api/auth/session'],
            'API liveness' => ['/api/health'],
            'framework liveness' => ['/up'],
        ];
    }

    public function test_account_identity_is_scoped_to_the_session_not_another_users_identifiers(): void
    {
        $alice = User::create(['name' => 'Alice Fixture', 'email' => 'alice@example.test', 'password' => 'authorization-fixture-123']);
        $bob = User::create(['name' => 'Bob Fixture', 'email' => 'bob@example.test', 'password' => 'authorization-fixture-456']);

        foreach ([[$alice, $bob], [$bob, $alice]] as [$current, $other]) {
            $this->actingAs($current, 'web');
            $query = http_build_query(['id' => $other->id, 'user_id' => $other->id, 'owner_id' => $other->id, 'email' => $other->email]);

            foreach (['/api/auth/user', '/api/auth/session'] as $path) {
                $this->getJson($path.'?'.$query, ['X-User-Id' => (string) $other->id])
                    ->assertOk()->assertExactJson(['user' => $current->only(['id', 'name', 'email'])])
                    ->assertHeader('Cache-Control', 'no-store, private');
            }
            $this->get('/account?'.$query)->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertDontSee($other->email);
        }
    }

    public function test_unknown_account_and_api_paths_do_not_expose_another_account_or_the_spa(): void
    {
        $user = User::create(['name' => 'Private Fixture', 'email' => 'private@example.test', 'password' => 'authorization-fixture-123']);
        $paths = ['/account/'.$user->id, '/account/nested/missing', '/api/auth/user/'.$user->id, '/api/auth/session/'.$user->id];

        foreach ($paths as $path) {
            $this->get($path)->assertNotFound()->assertDontSee('<div id="app"></div>', false);
        }
        $this->actingAs($user, 'web');
        foreach ($paths as $path) {
            $this->get($path)->assertNotFound()->assertDontSee('<div id="app"></div>', false);
        }
    }
}
