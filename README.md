<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Media Picker

简体中文 | [English](docs/README.en.md)

媒体选择与空文件夹管理，适用于 **PHP 8.4+、Laravel 13、Filament 5**。

## 功能与边界

扩展 Uni File Manager 的选择弹窗，文件浏览、上传、预览和选择仍由上游实现；本包增加有范围限制的空文件夹管理。

## 安装

先在宿主 Composer 配置本私有包及所需 `fayne/*` 依赖的软件源，再执行：

```bash
composer require fayne/laravel-filament-media-picker
php artisan filament:assets
```

Laravel 自动发现服务提供者。仅提供字段的包无需注册 Panel 插件；业务包按下面的示例注册。依赖版本以 [composer.json](composer.json) 为准，不要复制开发宿主的私有仓库凭据。

## 使用与配置

```php
use Fayne\MediaPicker\Forms\Components\ManagedFilePicker;

ManagedFilePicker::make('cover')
    ->storageArea('content-public')
    ->directory('banners/home')
    ->allowedMimeTypes(['image/*']);
```

宿主配置 Uni File Manager 的 `StorageAreaResolver` 和 `FileManagerAuthorizer`，为有权管理文件夹的人授予 `manage-empty-folders`；无需授予原始 rename/move/delete 权限。租户隔离由宿主 resolver/authorizer 负责。

弹窗展开“管理文件夹”后，可创建文件夹，重命名、移动、删除空子文件夹；移动时进入目标目录再确认，删除需要二次确认。操作成功刷新浏览器且保留待选文件。

后端重新校验源和目标范围，禁止修改根目录、文件及包含任何内容的文件夹。重命名、移动和删除要求本地文件系统；通过独占建目录和原子删除空目录避免并发上传导致文件丢失，其他驱动拒绝这三类操作。只使用宿主已有公开存储区域，不新增磁盘或媒体表。

不移动非空文件夹、不递归删除、不更新 CMS 引用。字段保存路径，Page Builder 的媒体适配仍由对应包管理。视图适配上游 `~0.8.3`，升级时需复核适配层；上游 MIT 声明保留在 [LICENSE-UPSTREAM](LICENSE-UPSTREAM)。

## 测试与上线

本包已提供独立 Testbench 启动测试（`composer test`）；在宿主验证字段渲染、输入、保存后回显、无效值拒绝和权限边界。不要将文档检查视为运行时验收。 开发宿主的 `tests/Feature/ManagedMediaPickerTest.php` 和 `tests/Feature/MediaLibraryTrialTest.php` 覆盖集成行为。

## 合作与贡献

通过 Issue 描述需求或缺陷，提供复现步骤、版本和预期行为；提交合并请求时补充相关验证，并说明尚未验证的环境。请勿提交密钥或业务敏感数据。商业合作及授权请联系 [Fayne Guo](mailto:fayne.guo@gmail.com)。

## 许可证

采用[专有许可证](LICENSE.md)。未经授权不得复制、分发或使用；第三方依赖仍遵循各自许可证。

## 开发与安全

[开发与 CI 约定](CONTRIBUTING.md) · [安全报告](SECURITY.md)。运行 `composer check` 检查语法和格式；有独立套件时同时运行测试。包不提交 `composer.lock`。
