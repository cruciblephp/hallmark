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

namespace Hallmark\Tests\Docs;

use Hallmark\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every PHP sample in the README and the manual runs as written, and prints the
 * output shown under it. A sample followed by an `<!-- not run: ... -->` comment
 * is skipped; the comment names the test that holds it instead.
 */
final class DocsSamplesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function samples(): iterable
    {
        $root  = \dirname(__DIR__, 2);
        $files = [$root . '/README.md', ...(\glob($root . '/docs/*.md') ?: [])];

        foreach ($files as $file) {
            $lines = \file($file, \FILE_IGNORE_NEW_LINES) ?: [];
            $count = \count($lines);

            for ($i = 0; $i < $count; $i++) {
                if (\preg_match('/^```php\s*$/', $lines[$i]) !== 1) {
                    continue;
                }

                $start = $i + 1;
                $code  = [];

                for ($i++; $i < $count && !\str_starts_with($lines[$i], '```'); $i++) {
                    $code[] = $lines[$i];
                }

                $next = $i + 1;

                while ($next < $count && \trim($lines[$next]) === '') {
                    $next++;
                }

                if ($next < $count && \str_starts_with(\trim($lines[$next]), '<!-- not run')) {
                    continue;
                }

                $expected = null;

                if ($next < $count && \preg_match('/^```text\s*$/', $lines[$next]) === 1) {
                    $shown = [];

                    for ($j = $next + 1; $j < $count && !\str_starts_with($lines[$j], '```'); $j++) {
                        $shown[] = $lines[$j];
                    }

                    $expected = \implode("\n", $shown);
                }

                yield \basename(\dirname($file)) . '/' . \basename($file) . ':' . $start => [\implode("\n", $code), $expected];
            }
        }
    }

    #[DataProvider('samples')]
    public function testTheSampleRunsAndPrintsWhatTheDocsShow(string $code, ?string $expected): void
    {
        // Resolved, as PHP reports a script's path: macOS's temp folder is behind a symlink.
        $dir = (\realpath(\sys_get_temp_dir()) ?: \sys_get_temp_dir()) . '/' . \uniqid('hallmark-doc-', true);
        \mkdir($dir);
        \file_put_contents($dir . '/sample.php', "<?php\nrequire " . \var_export(\dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ";\n" . $code . "\n");

        $process = \proc_open([\PHP_BINARY, $dir . '/sample.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $dir);
        self::assertIsResource($process);
        $stdout = (string) \stream_get_contents($pipes[1]);
        $stderr = (string) \stream_get_contents($pipes[2]);
        $status = \proc_close($process);

        \exec('rm -rf ' . \escapeshellarg($dir));

        self::assertSame('', $stderr, 'The sample wrote to stderr.');
        self::assertSame(0, $status, 'The sample exited with ' . $status . '.');

        if ($expected !== null) {
            self::assertSame($this->normalize($expected), $this->normalize(\str_replace($dir . '/', '', $stdout)));
        }
    }

    /** Dates and times move between runs; nothing else may. */
    private function normalize(string $text): string
    {
        $text = (string) \preg_replace('/\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}(\.\d+)?([+-]\d{2}:\d{2}|Z)?)?/', '<date>', $text);

        return \implode("\n", \array_map(\rtrim(...), \explode("\n", \rtrim($text))));
    }
}
