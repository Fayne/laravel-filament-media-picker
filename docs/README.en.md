<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Media Picker

[简体中文](../README.md) | English

For **PHP 8.4+, Laravel 13 and Filament 5**.

## Installation

Configure authorized Composer sources for this private package and its required `fayne/*` dependencies in the host first.

```bash
composer require fayne/laravel-filament-media-picker
php artisan filament:assets
```

Laravel discovers the service provider automatically. Field-only packages need no Panel plugin. See [composer.json](../composer.json) for exact dependencies; do not copy private credentials from a development host.

## Features, usage and boundaries

Adds empty-folder management inside Uni File Manager's picker modal. The upstream
explorer continues to handle file browsing, uploads, previews and selection.

## Requirements and installation

PHP 8.4+, Filament 5 and `unifilemanager/filament-file-manager ~0.8.3`.
The service provider is auto-discovered. Install the package through a Composer
path repository during development; publish it before using a tagged version.
Run `php artisan filament:assets` for the upstream stylesheet.

```php
use Fayne\MediaPicker\Forms\Components\ManagedFilePicker;

ManagedFilePicker::make('cover')
    ->storageArea('content-public')
    ->directory('banners/home')
    ->allowedMimeTypes(['image/*']);
```

Inside the selection modal, expand **管理文件夹**. Folder navigation here is
independent of the upstream file browser. Create folders in the current folder;
rename or delete an empty child folder. To move one, select 移动, navigate to its
destination, then choose 移动到当前文件夹. Delete requires a separate confirmation.
The file browser refreshes after successful operations without remounting, so
pending multiple selections are retained.

## Authorization and boundaries

The host must configure Uni File Manager's `StorageAreaResolver` and
`FileManagerAuthorizer`. In addition to normal read/upload permissions, grant the
`manage-empty-folders` operation to users who may manage folders. Do **not** grant
raw `rename`, `move`, or `delete` operations merely to enable this extension.
The extension grants only scoped folder creation and guarded empty-folder operations. Raw file mutations remain denied.

The backend rechecks source and destination against the field directory, rejects
modifying the directory root, rejects files, and refuses folders containing any
file or subfolder (including hidden contents). Invalid area keys fail closed.
Only existing public host storage areas are used; the package creates no disks,
media database tables, or global service overrides. Tenant isolation remains the
responsibility of the host's resolver/authorizer.

Rename, move and delete require a local filesystem adapter. They reserve the
destination with exclusive mkdir and remove the source with atomic rmdir;
a concurrent upload makes the operation fail without moving or deleting files.
Other storage drivers reject these three operations. Folder creation continues
to use the upstream core service.

First release does not manage files, recursively delete folders, move nonempty
folders, or update CMS references. The standard field stores paths; Page Builder's
ContentMediaPicker retains its existing disk/path adapter.

## Upstream compatibility

The field view is adapted from the upstream 0.8.3 MIT view. Its original license
is retained in LICENSE-UPSTREAM. The final upstream explorer is composed rather
than copied or replaced. Review the small field-view adapter whenever upgrading
the upstream package; no vendor files are modified.

## Validation

The development host runs integration tests in
`tests/Feature/ManagedMediaPickerTest.php` and
`tests/Feature/MediaLibraryTrialTest.php`, covering scoped operations, permissions,
nonempty folder protection, confirmation, form rendering and stored media values.

## Testing and deployment

The package includes standalone Testbench boot tests (`composer test`). Verify rendering, input, save/reload, invalid values, and authorization in the host. Documentation checks are not runtime acceptance. The development host contains `tests/Feature/ManagedMediaPickerTest.php` and `tests/Feature/MediaLibraryTrialTest.php` for integration coverage.

## Cooperation and contributions

Open an issue with reproduction steps, versions and expected behavior. Include relevant checks and outstanding environment acceptance in pull requests. Never submit credentials or sensitive business data. For commercial cooperation and licensing, contact [Fayne Guo](mailto:fayne.guo@gmail.com).

## License

[Proprietary license](../LICENSE.md). Copying, distribution and use require authorization. Third-party dependencies retain their own licenses.

## Development and security

[Development and CI](../CONTRIBUTING.md) · [Security reports](../SECURITY.md). Run `composer check` for syntax, formatting and available standalone tests. Do not commit `composer.lock`.
