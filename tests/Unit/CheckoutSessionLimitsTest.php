<?php

namespace Tests\Unit;

use App\Models\OrderLine;
use App\Models\OrderRevision;
use App\Services\Payments\CheckoutSession;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CheckoutSessionLimitsTest extends TestCase
{
    #[DataProvider('lineCounts')]
    public function test_provider_line_limit_includes_the_exclusive_tax_line(int $services, bool $tax, bool $allowed): void
    {
        $accepted = (new OrderRevision)->forceFill(['total_minor' => '12500', 'tax_minor' => $tax ? '1500' : '0', 'policy_snapshot' => ['tax_mode' => $tax ? 'exclusive' : 'unconfigured']]);
        $accepted->setRelation('lines', new Collection(array_fill(0, $services, new OrderLine)));
        try {
            CheckoutSession::validateRequest($accepted);
            $this->assertTrue($allowed);
        } catch (HttpException $exception) {
            $this->assertFalse($allowed);
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public static function lineCounts(): array
    {
        return [[998, true, true], [999, false, true], [999, true, false], [1000, false, false]];
    }
}
