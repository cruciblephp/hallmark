<?php

declare(strict_types=1);
/*
 * This file is part of the Hallmark library.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hallmark\Tests\Laravel;

use Illuminate\Log\Logger as IlluminateLogger;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Hallmark\Laravel\ChannelLogger;
use Hallmark\Laravel\ContextLogProcessor;
use Hallmark\Laravel\CreateHallmarkLogger;
use Hallmark\Laravel\HallmarkServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HallmarkServiceProvider::class)]
#[CoversClass(CreateHallmarkLogger::class)]
#[CoversClass(ChannelLogger::class)]
#[CoversClass(ContextLogProcessor::class)]
final class HallmarkChannelTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/hallmark-laravel-' . uniqid('', true);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testAHallmarkChannelWritesThroughLaravelsLogFacade(): void
    {
        $this->channel(['level' => 'warning']);

        Log::channel('hallmark')->info('below the threshold');
        Log::channel('hallmark')->warning('Low disk space', ['free_mb' => 128]);

        self::assertFileMatchesFormat("[%s] testing.WARNING: Low disk space {\"free_mb\":128} []\n", $this->dir . '/app.log');
    }

    public function testContextIdsAppearOnEveryLine(): void
    {
        $this->channel();

        Context::add('correlation_id', 'req-42');
        Log::channel('hallmark')->error('payment failed');

        self::assertStringContainsString('{"correlation_id":"req-42"}', $this->written());
    }

    public function testReplacePlaceholdersInterpolatesTheMessage(): void
    {
        $this->channel(['replace_placeholders' => true]);

        Log::channel('hallmark')->info('User {id} signed in', ['id' => 7]);

        self::assertStringContainsString('User 7 signed in', $this->written());
    }

    public function testDaysWritesAFilePerDay(): void
    {
        $this->channel(['days' => 7]);

        Log::channel('hallmark')->info('dated');

        self::assertFileExists($this->dir . '/app-' . date('Y-m-d') . '.log');
    }

    public function testTheCustomViaFormBuildsTheSameChannel(): void
    {
        config(['logging.channels.via' => [
            'driver' => 'custom',
            'via'    => CreateHallmarkLogger::class,
            'path'   => $this->dir . '/app.log',
        ]]);

        Log::channel('via')->notice('through via');

        self::assertStringContainsString('NOTICE: through via', $this->written());
    }

    public function testTheHallmarkLoggerIsReachableBehindTheChannel(): void
    {
        $this->channel(['name' => 'shop']);

        $laravel = Log::channel('hallmark');
        self::assertInstanceOf(IlluminateLogger::class, $laravel);

        $channel = $laravel->getLogger();
        self::assertInstanceOf(ChannelLogger::class, $channel);
        self::assertSame('shop', $channel->getLogger()->name());
    }

    protected function getPackageProviders($app): array
    {
        return [HallmarkServiceProvider::class];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function channel(array $config = []): void
    {
        config(['logging.channels.hallmark' => ['driver' => 'hallmark', 'path' => $this->dir . '/app.log', ...$config]]);
    }

    private function written(): string
    {
        $files = glob($this->dir . '/*.log') ?: [];

        self::assertCount(1, $files);

        $contents = file_get_contents($files[0]);
        self::assertIsString($contents);

        return $contents;
    }
}
