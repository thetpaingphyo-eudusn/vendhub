<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Validation\PasswordStrength;
use PHPUnit\Framework\TestCase;

final class PasswordStrengthTest extends TestCase
{
    public function testAcceptsStrongPassword(): void
    {
        $this->assertTrue(PasswordStrength::isStrong('Admin12345!'));
        $this->assertNull(PasswordStrength::error('Admin12345!'));
    }

    /** @dataProvider weakPasswords */
    public function testRejectsWeakPasswords(string $password): void
    {
        $this->assertFalse(PasswordStrength::isStrong($password));
        $this->assertNotNull(PasswordStrength::error($password));
    }

    /** @return list<array{0: string}> */
    public static function weakPasswords(): array
    {
        return [
            [''],
            ['short1!'],
            ['nouppercase1!'],
            ['NOLOWERCASE1!'],
            ['NoNumber!!'],
            ['NoSymbol123'],
        ];
    }
}
