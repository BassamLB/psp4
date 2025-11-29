<?php

namespace PHPUnit\Framework;

/**
 * PHPStan stub class to teach the analyzer about Laravel test helpers available on $this in Pest/PHPUnit tests.
 *
 * Use primitive/mixed types to avoid referencing framework classes in the stub.
 *
 * @method mixed get(string $uri, array<string,mixed> $headers = [])
 * @method mixed post(string $uri, array<string,mixed> $data = [], array<string,mixed> $headers = [])
 * @method mixed put(string $uri, array<string,mixed> $data = [], array<string,mixed> $headers = [])
 * @method mixed patch(string $uri, array<string,mixed> $data = [], array<string,mixed> $headers = [])
 * @method mixed delete(string $uri, array<string,mixed> $data = [], array<string,mixed> $headers = [])
 * @method $this actingAs(object $user, string|null $guard = null)
 * @method void assertAuthenticated()
 * @method void assertGuest()
 * @method $this from(string $uri)
 * @method $this withSession(array<string,mixed> $data)
 */
class TestCase
{
    // Stub only for static analysis; intentionally empty.
}
