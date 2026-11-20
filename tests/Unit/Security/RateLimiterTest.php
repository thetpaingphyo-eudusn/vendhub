<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/vendhub-rate-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testItBlocksAfterMaxAttempts(): void
    {
        $limiter = new RateLimiter($this->dir);

        $this->assertFalse($limiter->tooManyAttempts('login', 3));
        $limiter->hit('login', 60);
        $limiter->hit('login', 60);
        $limiter->hit('login', 60);

        $this->assertTrue($limiter->tooManyAttempts('login', 3));
        $this->assertGreaterThan(0, $limiter->availableIn('login'));
    }

    public function testClearResetsTheBucket(): void
    {
        $limiter = new RateLimiter($this->dir);
        $limiter->hit('token', 60);
        $limiter->hit('token', 60);
        $limiter->hit('token', 60);
        $limiter->clear('token');

        $this->assertFalse($limiter->tooManyAttempts('token', 3));
    }

    public function testAttemptChecksAndConsumesTheLimitUnderOneLock(): void
    {
        $limiter = new RateLimiter($this->dir);

        $this->assertTrue($limiter->attempt('purchase', 2, 60)['allowed']);
        $this->assertTrue($limiter->attempt('purchase', 2, 60)['allowed']);
        $blocked = $limiter->attempt('purchase', 2, 60);

        $this->assertFalse($blocked['allowed']);
        $this->assertGreaterThan(0, $blocked['retry_after']);
    }
}
