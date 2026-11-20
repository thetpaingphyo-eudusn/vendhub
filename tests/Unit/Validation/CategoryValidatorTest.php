<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Validation\CategoryValidator;
use PHPUnit\Framework\TestCase;

final class CategoryValidatorTest extends TestCase
{
    public function testAcceptsPlainName(): void
    {
        $this->assertSame([], (new CategoryValidator())->validate(['name' => 'Drink']));
    }

    public function testRejectsBlankHtmlAndTooLong(): void
    {
        $validator = new CategoryValidator();
        $this->assertArrayHasKey('name', $validator->validate(['name' => '']));
        $this->assertArrayHasKey('name', $validator->validate(['name' => '<b>Drink</b>']));
        $this->assertArrayHasKey('name', $validator->validate(['name' => str_repeat('x', 51)]));
    }
}
