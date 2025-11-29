<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base test case for the application.
 *
 * PHPStan/IDE helpers: declare common Laravel test helper methods used across Pest tests.
 *
 * @method \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> get(string $uri, array<string,mixed> $headers = [])
 * @method \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> post(string $uri, array<string,mixed> $data = [], array<string,mixed> $headers = [])
 * @method \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> put(string $uri, array<string,mixed> $data = [], array<string,mixed> $headers = [])
 * @method \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> patch(string $uri, array<string,mixed> $data = [], array<string,mixed> $headers = [])
 * @method \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> delete(string $uri, array<string,mixed> $data = [], array<string,mixed> $headers = [])
 * @method $this actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, string|null $guard = null)
 * @method void assertAuthenticated()
 * @method void assertGuest()
 */
abstract class TestCase extends BaseTestCase
{
    //
}
