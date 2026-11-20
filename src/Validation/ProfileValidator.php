<?php

declare(strict_types=1);

namespace App\Validation;

final class ProfileValidator
{
    /** @param array<string, mixed> $input
     *  @return array<string, string>
     */
    public function validate(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $current = (string) ($input['current_password'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirmation'] ?? '');
        $changingPassword = $current !== '' || $password !== '' || $confirmation !== '';

        if ($name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (contains_html($name)) {
            $errors['name'] = 'Name cannot contain HTML.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Name must be 100 characters or fewer.';
        }

        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if ($changingPassword) {
            if ($current === '') {
                $errors['current_password'] = 'Current password is required.';
            }
            $passwordError = PasswordStrength::error($password);
            if ($passwordError !== null) {
                $errors['password'] = $passwordError;
            }
            if ($password !== $confirmation) {
                $errors['password_confirmation'] = 'Password confirmation does not match.';
            }
        }

        return $errors;
    }
}
