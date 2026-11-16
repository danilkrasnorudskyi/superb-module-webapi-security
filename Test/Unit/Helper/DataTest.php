<?php

namespace Superb\WebapiSecurity\Test\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Superb\WebapiSecurity\Helper\Data;

class DataTest extends TestCase
{
    /**
     * @dataProvider cidrMatchProvider
     */
    public function testCidrMatch(string $ip, string $range, bool $expected): void
    {
        $this->assertSame($expected, (new TestableData())->cidrMatch($ip, $range));
    }

    public static function cidrMatchProvider(): array
    {
        return [
            'exact host without prefix' => ['10.0.0.1', '10.0.0.1', true],
            'other host without prefix' => ['10.0.0.2', '10.0.0.1', false],
            '/32 same host' => ['10.0.0.1', '10.0.0.1/32', true],
            '/32 other host' => ['10.0.0.2', '10.0.0.1/32', false],
            '/24 inside' => ['192.168.1.200', '192.168.1.0/24', true],
            '/24 outside' => ['192.168.2.1', '192.168.1.0/24', false],
            '/24 misaligned subnet' => ['192.168.1.200', '192.168.1.5/24', true],
            '/0 matches any address' => ['8.8.8.8', '10.0.0.1/0', true],
            'leading zero prefix' => ['192.168.1.200', '192.168.1.0/024', true],
            // used to throw ArithmeticError on the negative shift
            '/33 rejected' => ['10.0.0.1', '10.0.0.1/33', false],
            '/64 rejected' => ['10.0.0.1', '10.0.0.1/64', false],
            '/-1 rejected' => ['10.0.0.1', '10.0.0.1/-1', false],
            'huge prefix rejected' => ['10.0.0.1', '10.0.0.1/99999999999999999999', false],
            // used to be cast to /0 and match every address
            'non-numeric prefix rejected' => ['8.8.8.8', '10.0.0.1/abc', false],
            'empty prefix rejected' => ['8.8.8.8', '10.0.0.1/', false],
            'fractional prefix rejected' => ['8.8.8.8', '10.0.0.1/8.5', false],
            'invalid subnet' => ['10.0.0.1', 'not-an-ip/24', false],
            'empty subnet' => ['10.0.0.1', '/24', false],
            'invalid client ip' => ['not-an-ip', '10.0.0.0/8', false],
            'ipv6 client ip' => ['::1', '10.0.0.0/8', false],
        ];
    }

    public function testInvalidCidrDoesNotStopOtherEntriesFromMatching(): void
    {
        $helper = new TestableData([
            Data::CONDITIONALLY_ALLOWED_REST_PATH => [
                ['path' => 'V1/orders', 'methods' => ['GET'], 'ip' => '10.0.0.1/33, 192.168.1.0/24'],
            ],
        ]);

        $this->assertTrue($helper->isPathConditionallyAllowed('/V1/orders', 'GET', '192.168.1.5', ''));
        $this->assertFalse($helper->isPathConditionallyAllowed('/V1/orders', 'GET', '10.0.0.1', ''));
    }

    public function testMalformedPrefixDoesNotAllowEveryAddress(): void
    {
        $helper = new TestableData([
            Data::CONDITIONALLY_ALLOWED_REST_PATH => [
                ['path' => 'V1/orders', 'methods' => ['GET'], 'ip' => "10.0.0.1/abc\n10.0.0.2/"],
            ],
        ]);

        $this->assertFalse($helper->isPathConditionallyAllowed('/V1/orders', 'GET', '8.8.8.8', ''));
    }

    public function testWhitelistCidrIsResolvedByName(): void
    {
        $helper = new TestableData([
            Data::WHITELISTS => [
                ['name' => 'office', 'values' => '203.0.113.0/24'],
            ],
            Data::CONDITIONALLY_ALLOWED_REST_PATH => [
                ['path' => 'V1/orders', 'methods' => ['GET'], 'ip' => 'office'],
            ],
        ]);

        $this->assertTrue($helper->isPathConditionallyAllowed('/V1/orders', 'GET', '203.0.113.7', ''));
        $this->assertFalse($helper->isPathConditionallyAllowed('/V1/orders', 'GET', '198.51.100.7', ''));
        $this->assertFalse($helper->isPathConditionallyAllowed('/V1/orders', 'POST', '203.0.113.7', ''));
    }
}

/**
 * Data with config rows injected directly, so the matching logic runs without Magento
 */
class TestableData extends Data
{
    private $rows;

    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
    }

    public function cidrMatch($ip, $range)
    {
        return parent::cidrMatch($ip, $range);
    }

    protected function getRows($path)
    {
        return $this->rows[$path] ?? [];
    }
}
