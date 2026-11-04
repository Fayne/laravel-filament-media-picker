<?php

namespace Fayne\MediaPicker\Livewire;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use UniFileManager\Core\Contracts\FileManagerAuthorizer;
use UniFileManager\Core\Contracts\StorageAreaResolver;
use UniFileManager\Core\Exceptions\InvalidFilePath;
use UniFileManager\Core\Services\FileManager;
use UniFileManager\Core\Support\DirectoryScope;
use UniFileManager\Core\Support\MimeTypeMatcher;
use UniFileManager\FilamentFileManager\Support\PreviewUrlParameters;

class MediaBrowser extends FolderManager
{
    use WithFileUploads;
    use WithPagination;

    #[Locked]
    public bool $multiple = false;

    #[Locked]
    public int $maxFiles = 1;

    #[Locked]
    public array $allowedMimeTypes = [];

    #[Locked]
    public array $selectedPaths = [];

    #[Locked]
    public string $movePath = '';

    public string $viewMode = 'grid';

    public string $search = '';

    public array $uploads = [];

    public function mount(string $pickerId, string $storageArea, string $directory = '', bool $multiple = false, int $maxFiles = 1, array $allowedMimeTypes = [], array $selectedPaths = []): void
    {
        $this->pickerId = $pickerId;
        $this->storageArea = $storageArea;
        $this->directory = DirectoryScope::normalise($directory);
        $this->path = $this->directory;
        $this->multiple = $multiple;
        $this->maxFiles = $multiple ? max(1, $maxFiles) : 1;
        $this->allowedMimeTypes = $allowedMimeTypes ?: MimeTypeMatcher::DEFAULT_FILE_PICKER_MIME_TYPES;
        $this->selectedPaths = array_values(array_unique(array_filter($selectedPaths, 'is_string')));
        $mode = session('fayne-media-picker.view', 'grid');
        $this->viewMode = in_array($mode, ['grid', 'list'], true) ? $mode : 'grid';
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['grid', 'list'], true)) {
            $this->viewMode = $mode;
            session()->put('fayne-media-picker.view', $mode);
        }
    }

    public function open(string $path): void
    {
        try {
            $path = $this->scopedPath($path);
            if ($path !== $this->directory) {
                $parent = dirname($path) === '.' ? '' : dirname($path);
                $exists = collect($this->listing($parent))->contains(fn (array $item): bool => $item['path'] === $path && $item['type'] === 'directory');
                if (! $exists) {
                    throw ValidationException::withMessages(['folder' => '文件夹不存在，请刷新后重试。']);
                }
            }
            $this->listing($path);
            $this->path = $path;
            $this->search = '';
            $this->resetPage();
            $this->resetValidation();
        } catch (InvalidFilePath) {
            $this->addError('folder', '路径必须位于字段的所属路径内。');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function select(string $path): void
    {
        $file = $this->permittedFile($path);
        if (in_array($file['path'], $this->selectedPaths, true)) {
            $this->selectedPaths = array_values(array_diff($this->selectedPaths, [$file['path']]));
        } elseif (! $this->multiple) {
            $this->selectedPaths = [$file['path']];
        } elseif (count($this->selectedPaths) < $this->maxFiles) {
            $this->selectedPaths[] = $file['path'];
        } else {
            $this->addError('selection', '已达到文件数量上限。');
        }
    }

    public function confirmSelection(): void
    {
        if ($this->selectedPaths === [] || count($this->selectedPaths) > $this->maxFiles) {
            throw ValidationException::withMessages(['selection' => '请选择允许数量的文件。']);
        }
        foreach ($this->selectedPaths as $path) {
            $this->permittedFile($path);
        }
        $this->dispatch('unifile-manager:selected', pickerId: $this->pickerId, paths: $this->selectedPaths);
    }

    public function begin(string $operation, ?string $source = null): void
    {
        parent::begin($operation, $source);
        $this->movePath = $this->path;
    }

    public function openMoveDestination(string $path): void
    {
        $path = $this->scopedPath($path);
        if ($path === $this->source || ($this->source && str_starts_with($path, $this->source . '/'))) {
            throw ValidationException::withMessages(['folder' => '不能移动到自身或子目录。']);
        }
        app(\Fayne\MediaPicker\Support\EmptyFolderManager::class)->folders(auth()->user(), $this->storageArea, $this->directory, $path);
        $this->movePath = $path;
    }

    public function save(): void
    {
        $originalPath = $this->path;
        $operation = $this->operation;
        if ($operation === 'move') {
            $this->path = $this->scopedPath($this->movePath);
        }
        try {
            parent::save();
        } finally {
            $this->path = $originalPath;
        }
        if ($this->operation === null) {
            $this->dispatch('fayne-media:notice', pickerId: $this->pickerId, message: match ($operation) {
                'create' => '文件夹已创建', 'rename' => '名称已更新', 'move' => '文件夹已移动', default => '空文件夹已删除',
            });
        }
    }

    public function updatedUploads(): void
    {
        $limit = $this->multiple ? $this->maxFiles - count($this->selectedPaths) : 1;
        $limit = min($limit, max(1, (int) config('filament-file-manager.max_upload_files', 10)));
        $this->validate(['uploads' => ['required', 'array', 'min:1', 'max:' . max(0, $limit)], 'uploads.*' => ['file']]);
        foreach ($this->uploads as $file) {
            if (! MimeTypeMatcher::allows((string) $file->getMimeType(), $this->allowedMimeTypes)) {
                throw ValidationException::withMessages(['uploads' => '不支持此文件类型。']);
            }
        }
        $manager = $this->fileManager();
        $manager->validateUploads($this->uploads);
        $paths = [];
        foreach ($this->uploads as $file) {
            $paths[] = $manager->upload(auth()->user(), $file, $this->scopedPath($this->path));
        }
        $this->selectedPaths = $this->multiple ? array_values(array_unique([...$this->selectedPaths, ...$paths])) : $paths;
        $this->uploads = [];
        $this->resetValidation('uploads');
        $this->dispatch('fayne-media:notice', pickerId: $this->pickerId, message: '文件已上传并选中');
    }

    public function canManageFolders(): bool
    {
        return app(FileManagerAuthorizer::class)->can(auth()->user(), 'manage-empty-folders', $this->path);
    }

    public function breadcrumbs(?string $path = null): array
    {
        $path ??= $this->path;
        $result = [['path' => $this->directory, 'name' => '素材库']];
        $suffix = trim(substr($path, strlen($this->directory)), '/');
        $current = $this->directory;
        foreach (array_filter(explode('/', $suffix)) as $name) {
            $current = ltrim($current . '/' . $name, '/');
            $result[] = ['path' => $current, 'name' => $name];
        }

        return $result;
    }

    public function thumbnailUrl(string $path): string
    {
        return route('filament-file-manager.preview', PreviewUrlParameters::make($path, $this->storageArea, true));
    }

    public function isImage(array $item): bool
    {
        return str_starts_with((string) ($item['mime_type'] ?? ''), 'image/');
    }

    public function render(): mixed
    {
        $items = collect($this->listing($this->path))->filter(fn (array $item): bool => ($item['type'] === 'directory' || MimeTypeMatcher::allows((string) $item['mime_type'], $this->allowedMimeTypes))
            && ($this->search === '' || mb_stripos($item['name'], $this->search) !== false))->values();
        $page = max(1, $this->getPage());
        $total = $items->count();
        $page = min($page, max(1, (int) ceil($total / 24)));
        $visible = $items->slice(($page - 1) * 24, 24)->map(function (array $item): array {
            if ($item['type'] === 'directory') {
                $item['mutable'] = $this->canModifyFolder($item['path']);
            }

            return $item;
        });
        $destinations = $this->operation === 'move'
            ? app(\Fayne\MediaPicker\Support\EmptyFolderManager::class)->folders(auth()->user(), $this->storageArea, $this->directory, $this->movePath) : [];

        return view('fayne-media-picker::livewire.media-browser', ['items' => $visible, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / 24)), 'destinations' => $destinations]);
    }

    private function canModifyFolder(string $path): bool
    {
        if (! app(FileManagerAuthorizer::class)->can(auth()->user(), 'manage-empty-folders', $path)) {
            return false;
        }
        $area = app(StorageAreaResolver::class)->resolve($this->storageArea);
        $disk = Storage::disk($area['disk']);
        if (! $disk->getAdapter() instanceof \League\Flysystem\Local\LocalFilesystemAdapter) {
            return false;
        }
        $absolute = ltrim(trim($area['root'] ?? '', '/') . '/' . $path, '/');

        return $disk->directoryExists($absolute) && $disk->files($absolute) === [] && $disk->directories($absolute) === [];
    }

    private function permittedFile(string $path): array
    {
        $file = $this->fileManager()->selectableFile(auth()->user(), $this->scopedPath($path));
        if (! MimeTypeMatcher::allows((string) $file['mime_type'], $this->allowedMimeTypes)) {
            throw ValidationException::withMessages(['selection' => '请选择允许的文件类型。']);
        }

        return $file;
    }

    private function listing(string $path): array
    {
        return $this->fileManager()->list(auth()->user(), $this->scopedPath($path));
    }

    private function fileManager(): FileManager
    {
        return app(FileManager::class)->forArea($this->storageArea);
    }

    private function scopedPath(string $path): string
    {
        $path = DirectoryScope::normalise($path);
        if (! DirectoryScope::contains($this->directory, $path)) {
            throw ValidationException::withMessages(['folder' => '路径必须位于字段的所属路径内。']);
        }

        return $path;
    }
}
