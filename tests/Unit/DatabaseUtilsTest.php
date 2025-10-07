<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DatabaseUtils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DatabaseUtilsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('dummy_records');

        Schema::create('dummy_records', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        DummyRecord::query()->create(['name' => 'demo']);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('dummy_records');

        parent::tearDown();
    }

    public function testSupportsPessimisticLockIsFalseForSqlite(): void
    {
        $this->assertFalse(DatabaseUtils::supportsPessimisticLock());
    }

    public function testApplyPessimisticLockDoesNotBreakOnSqlite(): void
    {
        $record = DatabaseUtils::applyPessimisticLock(DummyRecord::query())->first();

        $this->assertNotNull($record);
        $this->assertSame('demo', $record->name);
    }
}

/**
 * @extends Model<array<string, mixed>>
 */
final class DummyRecord extends Model
{
    protected $table = 'dummy_records';

    public $timestamps = false;

    protected $guarded = [];
}
