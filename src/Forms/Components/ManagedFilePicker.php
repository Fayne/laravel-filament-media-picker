<?php

namespace Fayne\MediaPicker\Forms\Components;

use UniFileManager\Core\Contracts\FileManagerAuthorizer;
use UniFileManager\FilamentFileManager\Filament\Forms\Components\UniFilePicker;

class ManagedFilePicker extends UniFilePicker
{
    protected string $view = 'fayne-media-picker::forms.managed-file-picker';

    public function canManageFolders(): bool
    {
        return app(FileManagerAuthorizer::class)->can(auth()->user(), 'manage-empty-folders', $this->getDirectory());
    }
}
