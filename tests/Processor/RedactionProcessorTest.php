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

namespace Hallmark\Tests\Processor;

use Hallmark\Processor\RedactionProcessor;
use Hallmark\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RedactionProcessor::class)]
final class RedactionProcessorTest extends TestCase
{
    public function testMasksSensitiveKeysCaseInsensitively(): void
    {
        $processor = new RedactionProcessor();
        $result    = $processor($this->record(context: [
            'user'     => 'sam',
            'Password' => 'hunter2',
            'token'    => 'abc123',
        ]));

        self::assertSame('sam', $result->context['user']);
        self::assertSame('[REDACTED]', $result->context['Password']);
        self::assertSame('[REDACTED]', $result->context['token']);
    }

    public function testRecursesIntoNestedArrays(): void
    {
        $processor = new RedactionProcessor();
        $result    = $processor($this->record(context: [
            'payment' => ['card_number' => '4242424242424242', 'amount' => 100],
        ]));

        self::assertSame(['card_number' => '[REDACTED]', 'amount' => 100], $result->context['payment']);
    }

    public function testCustomKeysAndReplacement(): void
    {
        $processor = new RedactionProcessor(keys: ['pin'], replacement: '***');
        $result    = $processor($this->record(context: ['pin' => 4321, 'token' => 'kept']));

        self::assertSame('***', $result->context['pin']);
        // 'token' is not in the custom key set, so it is left alone.
        self::assertSame('kept', $result->context['token']);
    }

    public function testAWildcardKeyMasksEveryKeyItMatches(): void
    {
        $processor = new RedactionProcessor(keys: ['*_token', 'x-api-*']);
        $result    = $processor($this->record(context: ['csrf_token' => 'a', 'Session_Token' => 'b', 'X-Api-Key' => 'c', 'tokens' => 'kept']));

        self::assertSame(['csrf_token' => '[REDACTED]', 'Session_Token' => '[REDACTED]', 'X-Api-Key' => '[REDACTED]', 'tokens' => 'kept'], $result->context);
    }

    public function testUntouchedRecordIsReturnedUnchanged(): void
    {
        $processor = new RedactionProcessor();
        $record    = $this->record(context: ['safe' => 1]);

        self::assertSame($record, $processor($record));
    }
}
