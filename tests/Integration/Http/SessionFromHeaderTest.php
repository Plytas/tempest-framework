<?php

declare(strict_types=1);

namespace Integration\Http;

use Tempest\Http\GenericRequest;
use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Http\Session\Resolvers\HeaderSessionIdResolver;
use Tempest\Http\Session\Session;
use Tempest\Http\Session\SessionConfig;
use Tempest\Testing\IntegrationTest;

/**
 * @internal
 * @small
 */
final class SessionFromHeaderTest extends IntegrationTest
{
    public function test_resolving_session_from_cookie(): void
    {
        $this->container->config(new SessionConfig(
            path: __DIR__ . '/sessions',
            idResolverClass: HeaderSessionIdResolver::class,
        ));

        $this->setSessionId('session_a');
        $sessionA = $this->container->get(Session::class);
        $sessionA->set('test', 'a');

        $this->setSessionId('session_b');
        $sessionB = $this->container->get(Session::class);
        $this->assertNull($sessionB->get('test'));

        $this->setSessionId('session_a');
        $sessionA = $this->container->get(Session::class);
        $this->assertEquals('a', $sessionA->get('test'));
    }

    private function setSessionId(string $id): void
    {
        $request = new GenericRequest(Method::GET, '/', [], [Session::ID => $id]);

        $this->container->register(Request::class, fn () => $request);
        $this->container->register(GenericRequest::class, fn () => $request);
    }
}
