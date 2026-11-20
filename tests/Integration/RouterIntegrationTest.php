<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controller\AuthController;
use App\Core\Container;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Router;
use App\Core\Http\Url;
use App\Core\Http\View;
use App\Exception\NotFoundException;
use App\Model\User;
use App\Security\RateLimiter;
use App\Service\AuthServiceInterface;
use App\Validation\RegisterValidator;
use PHPUnit\Framework\TestCase;

final class RouterIntegrationTest extends TestCase
{
    private string $rateDir;

    protected function setUp(): void
    {
        new Url('');
        $this->rateDir = sys_get_temp_dir() . '/vendhub-int-rate-' . bin2hex(random_bytes(4));
        $_SESSION = ['csrf' => 'test-csrf-token'];
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->rateDir)) {
            return;
        }
        foreach (glob($this->rateDir . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->rateDir);
    }

    public function testUnknownRouteBubblesNotFound(): void
    {
        $router = $this->router($this->createMock(AuthServiceInterface::class));

        $this->expectException(NotFoundException::class);
        $router->dispatch(new Request('GET', '/this-route-does-not-exist', [], [], [], []));
    }

    public function testLoginGetRouteRendersThroughRouter(): void
    {
        $auth = $this->createMock(AuthServiceInterface::class);
        $auth->method('user')->willReturn(null);
        $response = $this->router($auth)->dispatch(new Request('GET', '/login', [], [], [], []));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Sign In', $response->content);
    }

    public function testAuthenticatedUserHittingLoginIsRedirected(): void
    {
        $auth = $this->createMock(AuthServiceInterface::class);
        $auth->method('user')->willReturn(new User(2, 'Riley', 'user@vending.test', 'hash', 'user'));
        $response = $this->router($auth)->dispatch(new Request('GET', '/login', [], [], [], []));

        $this->assertSame(302, $response->status);
        $this->assertSame('/products', $response->headers['Location']);
    }

    private function router(AuthServiceInterface $auth): Router
    {
        $container = new Container();
        $container->set(AuthServiceInterface::class, static fn (): AuthServiceInterface => $auth);
        $container->set(RegisterValidator::class, static fn (): RegisterValidator => new RegisterValidator());
        $container->set(RateLimiter::class, fn (): RateLimiter => new RateLimiter($this->rateDir));
        $container->set(View::class, static fn (): View => new View(dirname(__DIR__, 2) . '/views'));
        $container->set(Url::class, static fn (): Url => new Url(''));
        $container->set(AuthController::class, static function (Container $c): AuthController {
            return new AuthController(
                $c->get(AuthServiceInterface::class),
                $c->get(RegisterValidator::class),
                $c->get(RateLimiter::class),
                $c->get(View::class),
                $c->get(Url::class),
            );
        });

        $router = new Router($container);
        $router->register(AuthController::class);

        return $router;
    }
}
