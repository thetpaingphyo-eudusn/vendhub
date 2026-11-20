<?php

declare(strict_types=1);

namespace App\Security;

final class RateLimiter
{
    public function __construct(private string $directory)
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $state = $this->read($key);

        return $state['attempts'] >= $maxAttempts && $state['reset_at'] > time();
    }

    public function hit(string $key, int $decaySeconds): int
    {
        $now = time();
        $path = $this->path($key);
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return 0;
        }

        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $state = $this->decode(is_string($raw) ? $raw : '');
            if ($state['reset_at'] <= $now) {
                $state = [
                    'attempts' => 0,
                    'reset_at' => $now + max(1, $decaySeconds),
                ];
            }
            $state['attempts']++;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
            fflush($handle);

            return $state['attempts'];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return array{allowed: bool, retry_after: int} */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): array
    {
        $now = time();
        $path = $this->path($key);
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return ['allowed' => false, 'retry_after' => max(1, $decaySeconds)];
        }

        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $state = $this->decode(is_string($raw) ? $raw : '');
            if ($state['reset_at'] <= $now) {
                $state = ['attempts' => 0, 'reset_at' => $now + max(1, $decaySeconds)];
            }
            if ($state['attempts'] >= $maxAttempts) {
                return ['allowed' => false, 'retry_after' => max(1, $state['reset_at'] - $now)];
            }

            $state['attempts']++;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
            fflush($handle);

            return ['allowed' => true, 'retry_after' => 0];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function clear(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function availableIn(string $key): int
    {
        $state = $this->read($key);
        $remaining = $state['reset_at'] - time();

        return max(0, $remaining);
    }

    /** @return array{attempts: int, reset_at: int} */
    private function read(string $key): array
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return ['attempts' => 0, 'reset_at' => 0];
        }

        $raw = file_get_contents($path);

        return $this->decode(is_string($raw) ? $raw : '');
    }

    /** @return array{attempts: int, reset_at: int} */
    private function decode(string $raw): array
    {
        if ($raw === '') {
            return ['attempts' => 0, 'reset_at' => 0];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['attempts' => 0, 'reset_at' => 0];
        }

        return [
            'attempts' => max(0, (int) ($data['attempts'] ?? 0)),
            'reset_at' => max(0, (int) ($data['reset_at'] ?? 0)),
        ];
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.json';
    }
}
