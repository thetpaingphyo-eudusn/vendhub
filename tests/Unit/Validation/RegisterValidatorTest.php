<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Validation\RegisterValidator;
use PHPUnit\Framework\TestCase;

final class RegisterValidatorTest extends TestCase
{
    public function testStrongPasswordAndValidEmailPass(): void
    {
        $errors = (new RegisterValidator())->validate([
            'name' => 'Riley',
            'email' => 'user@vending.test',
            'password' => 'User12345!',
            'password_confirmation' => 'User12345!',
        ]);

        $this->assertSame([], $errors);
    }

    public function testWeakPasswordAndMismatchFail(): void
    {
        $errors = (new RegisterValidator())->validate([
            'name' => 'Riley',
            'email' => 'not-an-email',
            'password' => 'weak',
            'password_confirmation' => 'other',
        ]);

        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
        $this->assertArrayHasKey('password_confirmation', $errors);
    }

    public function testHtmlNameIsRejected(): void
    {
        $errors = (new RegisterValidator())->validate([
            'name' => '<script>x</script>',
            'email' => 'ok@test.com',
            'password' => 'User12345!',
            'password_confirmation' => 'User12345!',
        ]);

        $this->assertArrayHasKey('name', $errors);
    }
}
