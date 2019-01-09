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

}