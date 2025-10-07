<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\ApiController;
use ReflectionClass;
use Tests\TestCase;

final class ApiControllerTest extends TestCase
{
    public function testEtagEqualsWeakIgnoresWeakPrefixAndQuotes(): void
    {
        $controller = new class extends ApiController
        {
            public function etagEqualsWeakProxy(string $a, string $b): bool
            {
                $reflection = new ReflectionClass(ApiController::class);
                $method = $reflection->getMethod('etagEqualsWeak');
                $method->setAccessible(true);

                return (bool) $method->invoke($this, $a, $b);
            }
        };

        $this->assertTrue($controller->etagEqualsWeakProxy('W/"abc"', '"abc"'));
        $this->assertTrue($controller->etagEqualsWeakProxy('"abc"', 'W/"abc"'));
        $this->assertFalse($controller->etagEqualsWeakProxy('"abc"', '"def"'));
        $this->assertFalse($controller->etagEqualsWeakProxy('""', '"abc"'));
    }
}
