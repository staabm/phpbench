<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace PhpBench\Tests\Unit\Report\Generator;

use PhpBench\Dom\Document;
use PhpBench\Tests\TestCase;

abstract class GeneratorTestCase extends TestCase
{
    protected function assertXPathEval(Document $document, $expected, $expression): void
    {
        $result = $document->evaluate($expression);
        $this->assertEquals($expected, $result, sprintf('Xpath: %s', $expression));
    }

    protected function assertXPathCount(Document $document, $expected, $expression): void
    {
        $this->assertXPathEval($document, $expected, 'count(' . $expression . ')');
    }
}
