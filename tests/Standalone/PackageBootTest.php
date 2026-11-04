<?php

namespace Fayne\MediaPicker\Tests\Standalone;

use Fayne\MediaPicker\MediaPickerServiceProvider;
use Orchestra\Testbench\TestCase;

class PackageBootTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [\Livewire\LivewireServiceProvider::class, MediaPickerServiceProvider::class];
    }

    public function test_package_registers_its_resources_without_a_host_panel(): void
    {
        $this->assertTrue(view()->exists('fayne-media-picker::forms.managed-file-picker'));
    }
}
