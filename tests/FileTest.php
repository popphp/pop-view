<?php

namespace Pop\View\Test;

use Pop\View\Template\File;

class FileTest extends \PHPUnit_Framework_TestCase
{

    public function testSetTemplateException()
    {
        $this->setExpectedException('Pop\View\Template\Exception');
        $template = new File(__DIR__ . '/tmp/home.phtml');
    }

}