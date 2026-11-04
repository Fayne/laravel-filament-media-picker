<?php

namespace Fayne\MediaPicker\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use UniFileManager\Core\Contracts\FileManagerAuthorizer;
use UniFileManager\Core\Contracts\StorageAreaResolver;
use UniFileManager\Core\Exceptions\InvalidFilePath;
use UniFileManager\Core\Services\FileManager;
use UniFileManager\Core\Services\ImageThumbnailer;
use UniFileManager\Core\Support\DirectoryScope;

final class EmptyFolderManager
{
    public function __construct(
        private FileManagerAuthorizer $authorizer,
        private StorageAreaResolver $areas,
        private ImageThumbnailer $thumbnailer,
    ) {}

    public function folders(mixed $user, string $area, string $scope, string $path): array
    {
        $path = $this->scoped($scope, $path);

        return array_values(array_filter(
            $this->manager($area, $scope)->list($user, $path),
            fn (array $item): bool => $item['type'] === 'directory',
        ));
    }

    public function create(mixed $user, string $area, string $scope, string $parent, string $name): void
    {
        $parent = $this->scoped($scope, $parent);
        $this->manager($area, $scope)->createDirectory($user, $parent, $name);
    }

    public function rename(mixed $user, string $area, string $scope, string $source, string $name): void
    {
        $source = $this->emptyFolder($user, $area, $scope, $source);
        $name = trim($name);
        if ($name === '' || $name !== basename($name) || in_array($name, ['.', '..'], true)
            || preg_match('/[\\x00-\\x1F\\x7F<>:"\\\\\\\\|?*]/u', $name)) {
            $this->invalid('文件夹名称包含不支持的字符。');
        }
        $parent = dirname($source) === '.' ? '' : dirname($source);
        $this->relocate($user, $area, $scope, $source, $parent, $name);
    }

    public function move(mixed $user, string $area, string $scope, string $source, string $destination): void
    {
        $source = $this->emptyFolder($user, $area, $scope, $source);
        $destination = $this->scoped($scope, $destination);
        $this->relocate($user, $area, $scope, $source, $destination, basename($source));
    }

    public function delete(mixed $user, string $area, string $scope, string $source): void
    {
        $source = $this->emptyFolder($user, $area, $scope, $source);
        if (! @rmdir($this->localPath($area, $source))) {
            $this->invalid('文件夹已非空、已不存在或无法删除，请刷新后重试。');
        }
    }

    private function relocate(mixed $user, string $area, string $scope, string $source, string $destination, string $name): void
    {
        $this->manager($area, $scope)->list($user, $destination);
        abort_unless($this->authorizer->can($user, 'manage-empty-folders', $destination), 403);
        if ($destination === $source || str_starts_with($destination, $source . '/')) {
            $this->invalid('不能移动到自身或子目录。');
        }
        $target = ltrim($destination . '/' . $name, '/');
        if ($source === $target) {
            return;
        }
        if (count(explode('/', $target)) > max(1, (int) config('unifilemanager.max_directory_depth', 7))) {
            $this->invalid('目标目录超过允许的最大层级。');
        }
        $sourcePath = $this->localPath($area, $source);
        $targetPath = $this->localPath($area, $target);
        // Exclusive mkdir reserves the destination; rmdir atomically refuses any contents.
        // Moving an empty directory requires no recursive file operation.
        if (! @mkdir($targetPath, 0777)) {
            $this->invalid('目标已存在、目标父目录不存在或无法创建文件夹。');
        }
        if (! @rmdir($sourcePath)) {
            @rmdir($targetPath);
            $this->invalid('源文件夹已非空或无法修改，请刷新后重试。');
        }
    }

    private function localPath(string $area, string $path): string
    {
        $configuration = $this->configuration($area);
        $disk = Storage::disk($configuration['disk']);
        if (! $disk->getAdapter() instanceof \League\Flysystem\Local\LocalFilesystemAdapter) {
            $this->invalid('此存储驱动不支持原子空目录操作；当前版本仅支持本地磁盘的重命名、移动和删除。');
        }

        return $disk->path(ltrim(trim($configuration['root'] ?? '', '/') . '/' . $path, '/'));
    }

    private function emptyFolder(mixed $user, string $area, string $scope, string $source): string
    {
        $source = $this->scoped($scope, $source);
        if ($source === DirectoryScope::normalise($scope)) {
            $this->invalid('不能修改所属路径的根目录。');
        }
        abort_unless($this->authorizer->can($user, 'manage-empty-folders', $source), 403);
        $configuration = $this->configuration($area);
        $disk = Storage::disk($configuration['disk']);
        $absolute = ltrim(trim($configuration['root'] ?? '', '/') . '/' . $source, '/');
        if (! $disk->directoryExists($absolute) || $disk->files($absolute) !== [] || $disk->directories($absolute) !== []) {
            $this->invalid('只能操作空文件夹；包含文件或子文件夹的目录不能修改。');
        }

        return $source;
    }

    private function scoped(string $scope, string $path): string
    {
        try {
            $scope = DirectoryScope::normalise($scope);
            $path = DirectoryScope::normalise($path);
        } catch (InvalidFilePath) {
            $this->invalid('路径必须位于字段的所属路径内。');
        }
        if (! DirectoryScope::contains($scope, $path)) {
            $this->invalid('路径必须位于字段的所属路径内。');
        }

        return $path;
    }

    private function configuration(string $area): array
    {
        $configuration = $this->areas->resolve($area);
        if (! ($configuration['enabled'] ?? false)) {
            $this->invalid('存储区不可用。');
        }

        return $configuration;
    }

    private function manager(string $area, string $scope): FileManager
    {
        $this->configuration($area);
        $authorizer = new class($this->authorizer, $scope) implements FileManagerAuthorizer
        {
            public function __construct(private FileManagerAuthorizer $host, private string $scope) {}

            public function can(mixed $user, string $operation, string $path = ''): bool
            {
                if (! DirectoryScope::contains(DirectoryScope::normalise($this->scope), DirectoryScope::normalise($path))) {
                    return false;
                }
                $ability = in_array($operation, ['create-directory', 'create', 'rename', 'move', 'delete'], true)
                    ? 'manage-empty-folders' : $operation;

                return $this->host->can($user, $ability, $path);
            }
        };

        return (new FileManager($authorizer, $this->thumbnailer, $this->areas))->forArea($area);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['folder' => $message]);
    }
}
