<?php

declare(strict_types=1);

namespace App\Validation;

final class CategoryValidator
{
    /** @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function validate(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (contains_html($name)) {
            $errors['name'] = 'Name cannot contain HTML.';
        } elseif (mb_strlen($name) > 50) {
            $errors['name'] = 'Name must be 50 characters or fewer.';
        }

        return $errors;
    }
}
