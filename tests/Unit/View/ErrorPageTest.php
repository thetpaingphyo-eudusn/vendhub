<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use App\Core\Http\View;
use PHPUnit\Framework\TestCase;

final class ErrorPageTest extends TestCase
{
    public function testNotFoundPageShowsClearMessage(): void
    {
        $view = new View(dirname(__DIR__, 3) . '/views');
        $html = $view->render('errors/404', [
            'title' => 'Page not found',
            'message' => 'Page not found.',
            'layout' => 'errors/layout',
        ]);

        $this->assertStringContainsString('Page not found', $html);
        $this->assertStringContainsString('error-page__code', $html);
        $this->assertStringContainsString('>404<', $html);
        $this->assertStringContainsString('error-shell', $html);
        $this->assertStringNotContainsString('store-app', $html);
    }
}
