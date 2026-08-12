<?php

namespace Pop\View\Test\Template\Stream;

use Pop\View\Template\Stream\Cache;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pop-view-cache-test-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') as $entry) {
            is_dir($entry) ? rmdir($entry) : unlink($entry);
        }
        rmdir($this->dir);
    }

    public function testMissReturnsNull()
    {
        $cache = new Cache($this->dir);
        $this->assertNull($cache->get('nonexistent-key', 0));
    }

    public function testPutThenGetReturnsSource()
    {
        $cache = new Cache($this->dir);
        $key   = Cache::key('<html>[{title}]</html>');

        $cache->put($key, "<?php echo 'hello'; ?>");

        $this->assertSame("<?php echo 'hello'; ?>", $cache->get($key, 0));
    }

    public function testKeyIsStableForSameContent()
    {
        $a = Cache::key('same content');
        $b = Cache::key('same content');
        $this->assertSame($a, $b);
    }

    public function testKeyDiffersForDifferentContent()
    {
        $a = Cache::key('content one');
        $b = Cache::key('content two');
        $this->assertNotSame($a, $b);
    }

    public function testStaleCacheReturnsNull()
    {
        $cache = new Cache($this->dir);
        $key   = Cache::key('<html>[{title}]</html>');

        $cache->put($key, "<?php echo 'old'; ?>");

        // Pretend a contributing source file was modified after the cache was written.
        $future = time() + 60;
        $this->assertNull($cache->get($key, $future));
    }

    public function testFreshCacheReturnsSource()
    {
        $cache = new Cache($this->dir);
        $key   = Cache::key('<html>[{title}]</html>');

        $cache->put($key, "<?php echo 'fresh'; ?>");

        $past = time() - 3600;
        $this->assertSame("<?php echo 'fresh'; ?>", $cache->get($key, $past));
    }

    public function testPutThrowsWhenDirectoryDoesNotExist()
    {
        $this->expectException(\Pop\View\Template\Stream\Exception::class);

        $cache = new Cache($this->dir . DIRECTORY_SEPARATOR . 'does-not-exist');
        $cache->put(Cache::key('x'), "<?php ?>");
    }

    public function testGetDirReturnsConfiguredDirectory()
    {
        $cache = new Cache($this->dir);
        $this->assertSame($this->dir, $cache->getDir());
    }

    public function testGetDirStripsTrailingSlash()
    {
        $cache = new Cache($this->dir . '/');
        $this->assertSame($this->dir, $cache->getDir());
    }

    public function testPutThrowsWhenWriteFails()
    {
        // A pathologically long key (put() trusts the caller, unlike Cache::key()'s fixed-length
        // sha256 output) pushes the target filename past the filesystem's NAME_MAX, making
        // file_put_contents() to the temp file fail before rename() is ever attempted.
        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        $this->expectExceptionMessageMatches('/Failed to write compiled template/');

        $cache = new Cache($this->dir);
        $cache->put(str_repeat('a', 300), "<?php ?>");
    }

    public function testPutThrowsWhenRenameFails()
    {
        // Pre-create a directory at the exact path the compiled file would occupy: the temp-file
        // write still succeeds, but rename() onto an existing directory fails.
        $key = Cache::key('rename-failure');
        mkdir($this->dir . DIRECTORY_SEPARATOR . $key . '.php');

        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        $this->expectExceptionMessageMatches('/Failed to move compiled template/');

        $cache = new Cache($this->dir);
        $cache->put($key, "<?php ?>");
    }
}
