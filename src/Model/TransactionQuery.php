<?php

declare(strict_types=1);

namespace App\Model;

final class TransactionQuery
{
    /** @var list<string> */
    public const SORTS = ['when', 'customer', 'product', 'qty', 'unit_price', 'total'];

    public function __construct(
        public string $sort,
        public string $direction,
        public string $start = '',
        public string $end = '',
        public int $limit = 200,
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function from(array $input): self
    {
        $requested = (string) ($input['sort'] ?? '');
        $sort = in_array($requested, self::SORTS, true) ? $requested : 'when';
        $direction = strtolower((string) ($input['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $start = self::normalizeDateTime((string) ($input['start'] ?? ''));
        $end = self::normalizeDateTime((string) ($input['end'] ?? ''));
        $limit = max(1, min(200, (int) ($input['limit'] ?? 200)));

        return new self($sort, $direction, $start, $end, $limit);
    }

    private static function normalizeDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Accept HTML datetime-local: 2026-11-20T15:30
        $value = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
            return $value . ':00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return '';
    }

    public function startForInput(): string
    {
        return $this->toInputValue($this->start);
    }

    public function endForInput(): string
    {
        return $this->toInputValue($this->end);
    }

    private function toInputValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value . 'T00:00';
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})(:\d{2})?$/', $value, $m) === 1) {
            return $m[1] . 'T' . $m[2];
        }

        return '';
    }
}
