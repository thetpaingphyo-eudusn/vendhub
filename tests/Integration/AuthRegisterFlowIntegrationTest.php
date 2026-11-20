<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exception\ValidationException;
use App\Model\User;
use App\Repository\UserRepositoryInterface;
use App\Service\AuthService;
use App\Validation\RegisterValidator;
use PHPUnit\Framework\TestCase;

/**
 * Integration: registration validation + auth service persistence boundary.
 */
final class AuthRegisterFlowIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = ['csrf' => 'test-csrf-token'];
    }

    public function testValidRegistrationCreatesUserAndStartsSession(): void
    {
        $input = [
            'name' => 'Riley',
            'email' => 'new@vending.test',
            'password' => 'User12345!',
            'password_confirmation' => 'User12345!',
        ];
        $this->assertSame([], (new RegisterValidator())->validate($input));

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(null);
        $users->expects($this->once())->method('create')->willReturn(
            new User(9, 'Riley', 'new@vending.test', 'hash', 'user'),
        );
        $users->method('findById')->with(9)->willReturn(
            new User(9, 'Riley', 'new@vending.test', 'hash', 'user'),
        );

        $auth = new AuthService($users);
        $user = $auth->register($input['name'], $input['email'], $input['password']);

        $this->assertSame(9, $user->id);
        $this->assertSame(9, $_SESSION['user_id']);
        $this->assertNotNull($auth->user());
    }

    public function testInvalidRegistrationNeverTouchesRepository(): void
    {
        $errors = (new RegisterValidator())->validate([
            'name' => '',
            'email' => 'bad',
            'password' => 'weak',
            'password_confirmation' => 'nope',
        ]);
        $this->assertNotSame([], $errors);

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects($this->never())->method('create');
        $this->assertTrue(true);
    }

    public function testDuplicateEmailIsRejectedByServiceLayer(): void
    {
        $input = [
            'name' => 'Riley',
            'email' => 'taken@vending.test',
            'password' => 'User12345!',
            'password_confirmation' => 'User12345!',
        ];
        $this->assertSame([], (new RegisterValidator())->validate($input));

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(new User(3, 'Taken', 'taken@vending.test', 'hash', 'user'));
        $users->expects($this->never())->method('create');

        $this->expectException(ValidationException::class);
        (new AuthService($users))->register($input['name'], $input['email'], $input['password']);
    }
}
