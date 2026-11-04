<?php

namespace Fayne\MediaPicker;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class MediaPickerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \Filament\Support\Facades\FilamentAsset::register([
            \Filament\Support\Assets\Css::make('media-browser', __DIR__ . '/../resources/css/media-browser.css'),
        ], package: 'fayne/laravel-filament-media-picker');
        Livewire::component('fayne-media-picker.browser', \Fayne\MediaPicker\Livewire\MediaBrowser::class);
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'fayne-media-picker');
        Livewire::component('fayne-media-picker.folder-manager', \Fayne\MediaPicker\Livewire\FolderManager::class);
    }
}
