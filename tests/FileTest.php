<?php

namespace Pop\View\Test;

use Pop\View\Template\File;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{

    public function testSetTemplateException()
    {
        $this->expectException('Pop\View\Template\Exception');
        $template = new File(__DIR__ . '/tmp/home.phtml');
    }

    public function testRenderPropagatesExceptionWithoutLeakingOutputBuffer()
    {
        // renderTemplate()'s catch block previously called ob_clean() instead of ob_end_clean(),
        // which empties the output buffer's contents but leaves it on PHP's output-buffer stack -
        // the same bug class fixed in Stream::renderCompiled(). A raw PHP/PHTML template has always
        // been able to throw (straight `include`, no sandboxing), so this has always been reachable.
        $levelBefore = ob_get_level();

        $template = new File(__DIR__ . '/tmp/throwing.phtml');

        try {
            $template->render([]);
            $this->fail('Expected a RuntimeException to be thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom from template', $e->getMessage());
        }

        $this->assertSame($levelBefore, ob_get_level());
    }

}