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
use Hallmark\Exception\InvalidArgumentException;
use Hallmark\Formatter\JsonFormatter;
use Hallmark\Tests\Fixtures\SampleEvent;
use Hallmark\Processor\IdGenerator;
use Hallmark\Processor\UidProcessor;
use Hallmark\Laravel\CreateHallmarkLogger;
use Hallmark\Laravel\HallmarkServiceProvider;
use Hallmark\Laravel\StackHandler;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HallmarkServiceProvider::class)]
#[CoversClass(CreateHallmarkLogger::class)]
#[CoversClass(ChannelLogger::class)]
#[CoversClass(ContextLogProcessor::class)]
#[CoversClass(StackHandler::class)]
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

    public function testDaysFromEnvWritesAFilePerDay(): void
    {
        $this->channel(['days' => '7']);

        Log::channel('hallmark')->info('dated');

        self::assertFileExists($this->dir . '/app-' . date('Y-m-d') . '.log');
    }

    public function testABooleanFromEnvIsRead(): void
    {
        $this->channel(['replace_placeholders' => '1']);

        Log::channel('hallmark')->info('User {id} signed in', ['id' => 7]);

        self::assertStringContainsString('User 7 signed in', $this->written());
    }

    public function testASettingItCannotUseIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The hallmark channel\'s "days" must be an integer, got string \'fourteen\'.');

        self::assertNotNull($this->app);

        (new CreateHallmarkLogger($this->app))(['path' => $this->dir . '/app.log', 'days' => 'fourteen']);
    }

    public function testAHallmarkChannelJoinsAStack(): void
    {
        $this->channel(['level' => 'warning']);
        config(['logging.channels.both' => ['driver' => 'stack', 'channels' => ['hallmark']]]);

        Log::channel('both')->info('below the threshold');
        Log::channel('both')->warning('Low disk space', ['free_mb' => 128]);

        self::assertFileMatchesFormat("[%s] testing.WARNING: Low disk space {\"free_mb\":128} []\n", $this->dir . '/app.log');
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

    public function testTheFormatterAndItsArgumentsComeFromTheChannel(): void
    {
        // `maxLineBytes` as env() gives it, a string.
        $this->channel(['formatter' => JsonFormatter::class, 'formatter_with' => ['maxLineBytes' => '200']]);

        Log::channel('hallmark')->warning(str_repeat('w', 1000));

        $line = $this->written();

        self::assertLessThanOrEqual(200, strlen($line));
        self::assertStringContainsString('"truncated":true', $line);
    }

    public function testTheDefaultFormatterKeepsTheHandlersOwn(): void
    {
        $this->channel(['formatter' => 'default']);

        Log::channel('hallmark')->warning('Low disk space');

        self::assertStringContainsString('testing.WARNING: Low disk space', $this->written());
    }

    public function testProcessorsComeFromTheChannel(): void
    {
        $this->channel(['formatter' => JsonFormatter::class, 'processors' => [
            ['processor' => UidProcessor::class, 'with' => ['length' => 16, 'perRecord' => true]],
        ]]);

        Log::channel('hallmark')->info('first');
        Log::channel('hallmark')->info('second');

        $uids = array_map(static function (string $line): mixed {
            $record = json_decode($line, true);

            return is_array($record) && is_array($record['extra'] ?? null) ? $record['extra']['uid'] ?? null : null;
        }, explode("\n", trim($this->written())));

        self::assertCount(2, $uids);
        self::assertIsString($uids[0]);
        self::assertIsString($uids[1]);
        self::assertNotSame($uids[0], $uids[1]);
    }

    public function testAnIdGeneratorBoundInTheContainerReachesTheChannelsProcessors(): void
    {
        self::assertNotNull($this->app);

        $this->app->instance(IdGenerator::class, new class implements IdGenerator {
            public function generate(): string
            {
                return '01JBXQ2W6ZJ3V8C5R7N4T9K2MH';
            }
        });
        $this->channel(['formatter' => JsonFormatter::class, 'processors' => [
            ['processor' => UidProcessor::class, 'with' => ['perRecord' => true]],
        ]]);

        Log::channel('hallmark')->info('a ULID, as Laravents mints its message ids');

        self::assertStringContainsString('"uid":"01JBXQ2W6ZJ3V8C5R7N4T9K2MH"', $this->written());
    }

    public function testAFormatterThatIsNoneIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The hallmark channel\'s "formatter" must be a class implementing Hallmark\Formatter\FormatterInterface, got string \'stdClass\'.');

        self::assertNotNull($this->app);

        (new CreateHallmarkLogger($this->app))(['path' => $this->dir . '/app.log', 'formatter' => \stdClass::class]);
    }

    public function testAProcessorThatIsNoneIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::assertNotNull($this->app);

        (new CreateHallmarkLogger($this->app))(['path' => $this->dir . '/app.log', 'processors' => [\stdClass::class]]);
    }

    public function testACataloguedEventIsLoggedThroughLaravelsLogger(): void
    {
        $this->channel();

        $laravel = Log::channel('hallmark');
        self::assertInstanceOf(IlluminateLogger::class, $laravel);

        // What `$laravel->event(...)` runs: Laravel's logger declares no event(), so PHP sends the call to __call().
        $laravel->__call('event', [SampleEvent::WebhookReceived, ['id' => 7]]);

        self::assertStringContainsString('testing.INFO: Payment webhook received {"id":7,"event_code":"PAY-1101"}', $this->written());
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
