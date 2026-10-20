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

namespace Hallmark\Tests\Handler;

use function fopen;
use function stream_get_contents;

use Hallmark\Exception\InvalidArgumentException;
use Hallmark\Exception\RuntimeException;
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Level;
use Hallmark\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StreamHandler::class)]
final class StreamHandlerTest extends TestCase
{
    public function testWritesFormattedRecordToAnOpenResource(): void
    {
        $stream  = fopen('php://memory', 'a+');
        self::assertIsResource($stream);
        $handler = new StreamHandler($stream);
        $handler->setFormatter(new LineFormatter(format: '%message%'));

        $handler->handle($this->record(message: 'hello'));

        rewind($stream);

        self::assertSame('hello', stream_get_contents($stream));
    }

    public function testCreatesMissingDirectoryForAFilePath(): void
    {
        $dir  = sys_get_temp_dir() . '/log-test-' . uniqid('', true) . '/nested';
        $path = $dir . '/app.log';

        $handler = new StreamHandler($path);
        $handler->setFormatter(new LineFormatter(format: '%message%'));

        $handler->handle($this->record(message: 'x'));
        $handler->close();

        self::assertFileExists($path);
        self::assertStringEqualsFile($path, 'x');

        unlink($path);
        rmdir($dir);
        rmdir(dirname($dir));
    }

    public function testBelowThresholdIsNotWritten(): void
    {
        $stream  = fopen('php://memory', 'a+');
        self::assertIsResource($stream);
        $handler = new StreamHandler($stream, Level::Error);

        self::assertFalse($handler->handle($this->record(level: Level::Info)));

        rewind($stream);

        self::assertSame('', stream_get_contents($stream));
    }

    public function testRejectsNonStreamNonString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore argument.type */
        new StreamHandler(42);
    }

    public function testWritingAfterCloseWithoutUrlThrows(): void
    {
        $stream  = fopen('php://memory', 'a+');
        self::assertIsResource($stream);
        $handler = new StreamHandler($stream);
        $handler->close();

        $this->expectException(RuntimeException::class);

        $handler->handle($this->record());
    }
}
