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

namespace Hallmark\Handler;

use function chmod;
use function dirname;
use function error_get_last;
use function fclose;
use function flock;
use function fopen;
use function fwrite;
use function is_dir;
use function is_resource;
use function is_string;
use function mkdir;
use function sprintf;
use function str_starts_with;
use function strpos;
use function substr;

use const LOCK_EX;
use const LOCK_UN;

use Hallmark\Exception\InvalidArgumentException;
use Hallmark\Exception\RuntimeException;
use Hallmark\Level;
use Hallmark\LogRecord;

/**
 * Writes records to any stream — a file path, `php://stdout`, an open resource.
 *
 * The stream is opened lazily on the first write and, for a path we own, closed
 * on {@see self::close()}. The parent directory is created on demand, and
 * `$useLocking` wraps each write in an advisory `flock` so concurrent processes
 * appending to the same file do not interleave a line.
 */
class StreamHandler extends AbstractProcessingHandler
{
    /** @var resource|null */
    protected $stream;

    protected ?string $url = null;

    private bool $dirCreated = false;

    /**
     * @param resource|string $stream a file/URL path, or an already-open stream resource
     */
    public function __construct(
        $stream,
        Level $level = Level::Debug,
        bool $bubble = true,
        private readonly ?int $filePermission = null,
        private readonly bool $useLocking = false,
    ) {
        parent::__construct($level, $bubble);

        if (is_resource($stream)) {
            $this->stream = $stream;
        } elseif (is_string($stream)) {
            $this->url = $stream;
        } else {
            throw new InvalidArgumentException('A stream must either be a resource or a string path.');
        }
    }

    public function close(): void
    {
        // Only close streams we opened ourselves; an injected resource is the
        // caller's to manage.
        if ($this->url !== null && is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream     = null;
        $this->dirCreated = false;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    protected function write(LogRecord $record): void
    {
        if (!is_resource($this->stream)) {
            $this->openStream();
        }

        /** @var resource $stream */
        $stream = $this->stream;

        if ($this->useLocking) {
            flock($stream, LOCK_EX);
        }

        fwrite($stream, $record->formatted);

        if ($this->useLocking) {
            flock($stream, LOCK_UN);
        }
    }

    private function openStream(): void
    {
        $url = $this->url;

        if ($url === null || $url === '') {
            throw new RuntimeException('Missing stream url; the stream cannot be opened. This can be caused by a premature call to close().');
        }

        $this->createDir($url);

        $stream = @fopen($url, 'a');

        if ($this->filePermission !== null) {
            @chmod($url, $this->filePermission);
        }

        if (!is_resource($stream)) {
            $this->stream = null;

            throw new RuntimeException(sprintf('The stream or file "%s" could not be opened in append mode: %s', $url, $this->lastError()));
        }

        $this->stream = $stream;
    }

    private function createDir(string $url): void
    {
        if ($this->dirCreated) {
            return;
        }

        $dir = $this->dirFromStream($url);

        if ($dir !== null && !is_dir($dir)) {
            $created = @mkdir($dir, 0777, true);

            if ($created === false && !is_dir($dir)) {
                throw new RuntimeException(sprintf('There is no existing directory at "%s" and it could not be created: %s', $dir, $this->lastError()));
            }
        }

        $this->dirCreated = true;
    }

    private function dirFromStream(string $stream): ?string
    {
        $pos = strpos($stream, '://');

        if ($pos === false) {
            return dirname($stream);
        }

        if (str_starts_with($stream, 'file://')) {
            return dirname(substr($stream, 7));
        }

        // A non-file wrapper (php://, php memory, network) has no directory.
        return null;
    }

    private function lastError(): string
    {
        $error = error_get_last();

        return $error['message'] ?? '';
    }
}
