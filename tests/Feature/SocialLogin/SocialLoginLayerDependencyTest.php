<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\SocialLogin;

use ITHilbert\UserAuth\Application\SocialLogin\Exceptions\SocialProviderUnavailableException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Abhängigkeiten zeigen nur nach innen: Domain und Application kennen keine Infrastructure.
 */
final class SocialLoginLayerDependencyTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function phpFilesIn(string $relativeDir): array
    {
        $dir = dirname(__DIR__, 3).'/src/'.$relativeDir;
        $this->assertDirectoryExists($dir);

        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_domain_does_not_depend_on_application_or_infrastructure(): void
    {
        foreach ($this->phpFilesIn('Domain') as $file) {
            $source = (string) file_get_contents($file);
            $this->assertStringNotContainsString('ITHilbert\\UserAuth\\Infrastructure', $source, $file);
            $this->assertStringNotContainsString('ITHilbert\\UserAuth\\Application', $source, $file);
        }
    }

    public function test_application_does_not_depend_on_infrastructure(): void
    {
        foreach ($this->phpFilesIn('Application') as $file) {
            $this->assertStringNotContainsString(
                'ITHilbert\\UserAuth\\Infrastructure',
                (string) file_get_contents($file),
                $file,
            );
        }
    }

    public function test_provider_unavailable_exception_belongs_to_application_port(): void
    {
        $this->assertTrue(class_exists(SocialProviderUnavailableException::class));
        $this->assertFalse(class_exists('ITHilbert\\UserAuth\\Infrastructure\\SocialLogin\\SocialProviderUnavailableException'));
    }
}
