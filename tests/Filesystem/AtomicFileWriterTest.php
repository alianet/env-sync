<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Tests\Filesystem;

use Alianet\EnvSync\Filesystem\AtomicFileWriter;
use PHPUnit\Framework\TestCase;

final class AtomicFileWriterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/env-sync-writer-test-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory));
    }

    protected function tearDown(): void
    {
        foreach (new \FilesystemIterator($this->directory) as $file) {
            if (!$file instanceof \SplFileInfo) {
                throw new \RuntimeException('Unexpected temporary directory entry.');
            }
            unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testRejectsSymbolicLinkWithoutChangingItOrItsTarget(): void
    {
        $target = $this->directory.'/actual.env';
        $link = $this->directory.'/.env';
        self::assertNotFalse(file_put_contents($target, "SECRET=original\n"));
        self::assertTrue(symlink($target, $link));

        try {
            (new AtomicFileWriter())->write($link, "SECRET=replacement\n");
            self::fail('Expected the symbolic link to be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Refusing to replace symbolic link: '.$link, $exception->getMessage());
        }

        self::assertTrue(is_link($link));
        self::assertSame("SECRET=original\n", file_get_contents($target));
        self::assertCount(2, iterator_to_array(new \FilesystemIterator($this->directory)));
    }
}
