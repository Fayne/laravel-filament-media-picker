<?php

namespace Fayne\MediaPicker\Livewire;

use Fayne\MediaPicker\Support\EmptyFolderManager;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use UniFileManager\Core\Contracts\FileManagerAuthorizer;
use UniFileManager\Core\Exceptions\InvalidFilePath;
use UniFileManager\Core\Support\DirectoryScope;

class FolderManager extends Component
{
    #[Locked]
    public string $pickerId = '';

    #[Locked]
    public string $storageArea = '';

    #[Locked]
    public string $directory = '';

    #[Locked]
    public string $path = '';

    #[Locked]
    public ?string $operation = null;

    #[Locked]
    public ?string $source = null;

    public string $folderName = '';

    public function mount(string $pickerId, string $storageArea, string $directory = ''): void
    {
        $this->pickerId = $pickerId;
        $this->storageArea = $storageArea;
        $this->directory = DirectoryScope::normalise($directory);
        $this->path = $this->directory;
        $this->authorizeManagement();
    }

    public function open(string $path): void
    {
        $this->authorizeManagement();
        // Listing validates scope, existence and read permission before changing navigation.
        $this->manager()->folders(auth()->user(), $this->storageArea, $this->directory, $path);
        $this->path = DirectoryScope::normalise($path);
    }

    public function up(): void
    {
        $parent = dirname($this->path);
        $this->open($this->path === $this->directory ? $this->directory : ($parent === '.' ? '' : $parent));
    }

    public function begin(string $operation, ?string $source = null): void
    {
        $this->authorizeManagement();
        abort_unless(in_array($operation, ['create', 'rename', 'move', 'delete'], true), 422);
        $this->resetValidation();
        $this->operation = $operation;
        $this->source = $source;
        $this->folderName = $operation === 'rename' ? basename($source ?? '') : '';
    }

    public function cancel(): void
    {
        $this->reset('operation', 'source', 'folderName');
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorizeManagement();
        if (in_array($this->operation, ['create', 'rename'], true)) {
            $this->validate(['folderName' => ['required', 'string', 'max:255']]);
        }
        $manager = $this->manager();
        try {
            match ($this->operation) {
                'create' => $manager->create(auth()->user(), $this->storageArea, $this->directory, $this->path, $this->folderName),
                'rename' => $manager->rename(auth()->user(), $this->storageArea, $this->directory, $this->source ?? '', $this->folderName),
                'move' => $manager->move(auth()->user(), $this->storageArea, $this->directory, $this->source ?? '', $this->path),
                'delete' => $manager->delete(auth()->user(), $this->storageArea, $this->directory, $this->source ?? ''),
                default => throw ValidationException::withMessages(['folder' => '请选择操作。']),
            };
        } catch (InvalidFilePath $exception) {
            $this->addError('folder', $exception->getMessage());

            return;
        }
        $this->cancel();
        $this->dispatch('fayne-media:folders-changed', pickerId: $this->pickerId);
    }

    public function render(): mixed
    {
        $this->authorizeManagement();

        return view('fayne-media-picker::livewire.folder-manager', [
            'folders' => $this->manager()->folders(auth()->user(), $this->storageArea, $this->directory, $this->path),
        ]);
    }

    private function manager(): EmptyFolderManager
    {
        return app(EmptyFolderManager::class);
    }

    private function authorizeManagement(): void
    {
        abort_unless(app(FileManagerAuthorizer::class)->can(auth()->user(), 'manage-empty-folders', $this->directory), 403);
    }
}
