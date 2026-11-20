<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\TransactionQuery;
use PHPUnit\Framework\TestCase;

final class TransactionQueryTest extends TestCase
{
    public function testDefaultsAndSafeSort(): void
    {
        $query = TransactionQuery::from(['sort' => 'drop table', 'dir' => 'sideways']);

        $this->assertSame('when', $query->sort);
        $this->assertSame('desc', $query->direction);
        $this->assertSame('', $query->start);
        $this->assertSame('', $query->end);
    }

    public function testDateTimeLocalIsNormalized(): void
    {
        $query = TransactionQuery::from([
            'sort' => 'total',
            'dir' => 'asc',
            'start' => '2026-11-01T09:30',
            'end' => '2026-11-20T18:00',
        ]);

        $this->assertSame('total', $query->sort);
        $this->assertSame('asc', $query->direction);
        $this->assertSame('2026-11-01 09:30:00', $query->start);
        $this->assertSame('2026-11-20 18:00:00', $query->end);
        $this->assertSame('2026-11-01T09:30', $query->startForInput());
        $this->assertSame('2026-11-20T18:00', $query->endForInput());
    }
}
