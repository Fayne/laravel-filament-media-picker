<div class="fmp-browser" x-data="{
    notice: '', noticeTimer: null, menu: null, menuTop: 0, menuLeft: 0, uploading: false, progress: 0, dragging: false,
    showMenu(event, path, mutable) {
        const bounds = this.$el.getBoundingClientRect(), button = event.currentTarget.getBoundingClientRect();
        this.menuTop = Math.max(8, Math.min(button.bottom - bounds.top + 5, bounds.height - 170));
        this.menuLeft = Math.max(8, Math.min(button.right - bounds.left - 190, bounds.width - 198));
        this.menu = { path, mutable };
        this.$nextTick(() => this.$refs.menu.querySelector('button:not(:disabled)')?.focus());
    }
}"
    x-on:fayne-media:notice.window="if ($event.detail.pickerId === @js($pickerId)) { notice = $event.detail.message; clearTimeout(noticeTimer); noticeTimer = setTimeout(() => notice = '', 2500); }"
    x-on:fayne-media:open.window="if ($event.detail.pickerId === @js($pickerId)) $wire.$refresh()"
    x-on:keydown.escape="if (menu) { menu = null; $event.stopPropagation(); }"
>
    <header class="fmp-header">
        <span class="fmp-brand-icon"><x-filament::icon icon="heroicon-o-photo" /></span>
        <div><h2>选择媒体</h2><p>为页面选择图片或文件</p></div>
        <button class="fmp-icon-button fmp-close" type="button" aria-label="关闭选择器" x-on:click="$dispatch('fayne-media:close', { pickerId: @js($pickerId) })"><x-filament::icon icon="heroicon-o-x-mark" /></button>
    </header>
    <div class="fmp-toolbar">
        <nav class="fmp-breadcrumbs" aria-label="当前目录">
            @foreach ($this->breadcrumbs() as $crumb)
                @if (! $loop->first)<x-filament::icon icon="heroicon-o-chevron-right" class="fmp-chevron" />@endif
                <button type="button" wire:click="open(@js($crumb['path']))" @if($loop->last) aria-current="location" @endif>{{ $crumb['name'] }}</button>
            @endforeach
        </nav>
        <div class="fmp-tools">
            @if ($this->canManageFolders())
                <button class="fmp-button fmp-secondary" type="button" wire:click="begin('create')"><x-filament::icon icon="heroicon-o-folder-plus" />新建文件夹</button>
            @endif
            @if (app(\UniFileManager\Core\Contracts\FileManagerAuthorizer::class)->can(auth()->user(), 'upload', $path))
                <button class="fmp-button fmp-primary" type="button" x-on:click="$refs.upload.click()" x-bind:disabled="uploading" @disabled($multiple && count($selectedPaths) >= $maxFiles)><x-filament::icon icon="heroicon-o-arrow-up-tray" />上传文件</button>
            @endif
            <div class="fmp-view-switch" role="group" aria-label="显示方式">
                <button type="button" wire:click="setViewMode('grid')" aria-label="预览模式" aria-pressed="{{ $viewMode === 'grid' ? 'true' : 'false' }}" x-on:click="menu = null"><x-filament::icon icon="heroicon-o-squares-2x2" /></button>
                <button type="button" wire:click="setViewMode('list')" aria-label="列表模式" aria-pressed="{{ $viewMode === 'list' ? 'true' : 'false' }}" x-on:click="menu = null"><x-filament::icon icon="heroicon-o-list-bullet" /></button>
            </div>
        </div>
    </div>
    <label class="fmp-search"><x-filament::icon icon="heroicon-o-magnifying-glass" /><input type="search" wire:model.live.debounce.250ms="search" placeholder="搜索当前目录…" aria-label="搜索当前目录" /></label>
    <input x-ref="upload" class="fmp-hidden-input" aria-label="上传文件" type="file" wire:model="uploads" accept="{{ implode(',', $allowedMimeTypes) }}" @if($multiple && ! \Livewire\Features\SupportFileUploads\FileUploadConfiguration::isUsingS3()) multiple @endif
        x-on:livewire-upload-start="uploading = true; progress = 0"
        x-on:livewire-upload-progress="progress = $event.detail.progress"
        x-on:livewire-upload-finish="uploading = false"
        x-on:livewire-upload-error="uploading = false" />
    <div class="fmp-upload-progress" x-show="uploading" x-cloak role="status"><span>正在上传… <span x-text="progress + '%'"></span></span><progress max="100" x-bind:value="progress"></progress></div>
    <main class="fmp-content" x-on:scroll="menu = null" x-on:dragover.prevent="dragging = true" x-on:dragleave.self="dragging = false"
        x-on:drop.prevent="dragging = false; if (! uploading) { $refs.upload.files = $event.dataTransfer.files; $refs.upload.dispatchEvent(new Event('change', { bubbles: true })); }"
        x-bind:class="{ 'fmp-dragging': dragging }">
        <div class="fmp-count"><span>{{ $search === '' ? '全部内容' : '搜索结果' }}</span><span>{{ $total }} 项</span></div>
        @if ($viewMode === 'list' && $items->isNotEmpty())
            <div class="fmp-list-heading"><span>名称</span><span>类型</span><span>大小</span><span>修改日期</span><span></span></div>
        @endif
        <div @class(['fmp-items', 'fmp-grid' => $viewMode === 'grid', 'fmp-list' => $viewMode === 'list'])>
            @foreach ($items as $item)
                @php
                    $folder = $item['type'] === 'directory';
                    $chosen = ! $folder && in_array($item['path'], $selectedPaths, true);
                    $type = $folder ? '文件夹' : strtoupper(pathinfo($item['name'], PATHINFO_EXTENSION));
                    $size = $folder ? '—' : \Illuminate\Support\Number::fileSize($item['size'] ?? 0);
                @endphp
                <article wire:key="media-{{ $item['path'] }}" @class(['fmp-item', 'fmp-selected' => $chosen])>
                    <button class="fmp-item-hit" type="button" wire:click="{{ $folder ? 'open' : 'select' }}(@js($item['path']))" aria-label="{{ $folder ? '打开文件夹' : '选择文件' }} {{ $item['name'] }}" @if(! $folder) aria-pressed="{{ $chosen ? 'true' : 'false' }}" @endif>
                        <span class="fmp-thumbnail">
                            @if ($folder)<x-filament::icon icon="heroicon-o-folder" class="fmp-folder-icon" />
                            @elseif ($this->isImage($item))<img src="{{ $this->thumbnailUrl($item['path']) }}" alt="" loading="lazy" />
                            @else<x-filament::icon icon="heroicon-o-document-text" class="fmp-file-icon" />@endif
                        </span>
                        <span class="fmp-item-info"><span class="fmp-name">{{ $item['name'] }}</span><span class="fmp-meta">{{ $folder ? (($item['mutable'] ?? false) ? '空文件夹' : '文件夹') : $type.' · '.$size }}</span></span>
                        <span class="fmp-column-type">{{ $type }}</span><span class="fmp-column-size">{{ $size }}</span><span class="fmp-column-date">{{ isset($item['modified_at']) ? date('Y-m-d', $item['modified_at']) : '—' }}</span>
                    </button>
                    @if ($folder && $this->canManageFolders())
                        <button class="fmp-icon-button fmp-item-menu" type="button" aria-label="{{ $item['name'] }}的操作" x-on:click="showMenu($event, @js($item['path']), @js($item['mutable']))"><x-filament::icon icon="heroicon-o-ellipsis-horizontal" /></button>
                    @elseif (! $folder)
                        <span @class(['fmp-selection-mark', 'fmp-checked' => $chosen]) aria-hidden="true">@if($chosen)<x-filament::icon icon="heroicon-m-check" />@endif</span>
                    @endif
                </article>
            @endforeach
        </div>
        @if ($items->isEmpty())
            <div class="fmp-empty"><x-filament::icon :icon="$search === '' ? 'heroicon-o-folder-open' : 'heroicon-o-magnifying-glass'" /><strong>{{ $search === '' ? '这个文件夹还是空的' : '没有匹配的内容' }}</strong><p>{{ $search === '' ? '上传文件，或新建一个文件夹' : '换个关键词试试' }}</p></div>
        @endif
        @if ($pages > 1)
            <nav class="fmp-pagination" aria-label="素材分页"><button class="fmp-button fmp-secondary" type="button" wire:click="setPage({{ $page - 1 }})" @disabled($page <= 1)>上一页</button><span>{{ $page }} / {{ $pages }}</span><button class="fmp-button fmp-secondary" type="button" wire:click="setPage({{ $page + 1 }})" @disabled($page >= $pages)>下一页</button></nav>
        @endif
    </main>
    <div class="fmp-errors" role="alert">@foreach (['folder', 'selection', 'uploads', 'uploads.*'] as $errorKey) @error($errorKey)<p>{{ $message }}</p>@enderror @endforeach</div>
    <footer class="fmp-footer"><span aria-live="polite">{{ count($selectedPaths) ? '已选择 '.count($selectedPaths).' 个文件' : '尚未选择文件' }}</span><button class="fmp-button fmp-secondary" type="button" x-on:click="$dispatch('fayne-media:close', { pickerId: @js($pickerId) })">取消</button><button class="fmp-button fmp-primary" type="button" wire:click="confirmSelection" wire:loading.attr="disabled" @disabled($selectedPaths === [])>使用所选{{ count($selectedPaths) ? ' ('.count($selectedPaths).')' : '' }}</button></footer>
    <div x-ref="menu" x-show="menu" x-cloak class="fmp-context-menu" x-bind:style="{ top: menuTop + 'px', left: menuLeft + 'px' }" role="menu" x-on:click.outside="menu = null" x-on:keydown.escape.stop="menu = null">
        <button type="button" role="menuitem" x-bind:disabled="!menu?.mutable" x-on:click="$wire.begin('rename', menu.path); menu = null"><x-filament::icon icon="heroicon-o-pencil" />重命名</button>
        <button type="button" role="menuitem" x-bind:disabled="!menu?.mutable" x-on:click="$wire.begin('move', menu.path); menu = null"><x-filament::icon icon="heroicon-o-folder-arrow-down" />移动到…</button>
        <button type="button" role="menuitem" class="fmp-danger-text" x-bind:disabled="!menu?.mutable" x-on:click="$wire.begin('delete', menu.path); menu = null"><x-filament::icon icon="heroicon-o-trash" />删除文件夹</button>
        <p x-show="!menu?.mutable">仅支持修改本地磁盘中的空文件夹</p>
    </div>
    <div class="fmp-toast" x-show="notice" x-text="notice" x-cloak role="status"></div>
    @if ($operation)
        <div class="fmp-action-overlay" wire:click.self="cancel" x-on:keydown.escape.stop.prevent="$wire.cancel()">
            <section class="fmp-action-dialog" role="dialog" aria-modal="true" aria-label="{{ match($operation) { 'create' => '新建文件夹', 'rename' => '重命名文件夹', 'move' => '移动文件夹', default => '删除文件夹' } }}" x-data x-trap.inert="true" x-init="$nextTick(() => $refs.folderName?.focus())">
                <h3>{{ match($operation) { 'create' => '新建文件夹', 'rename' => '重命名文件夹', 'move' => '移动文件夹', default => '删除文件夹' } }}</h3>
                @if (in_array($operation, ['create', 'rename'], true))
                    <p>{{ $operation === 'create' ? '创建在：'.($path ?: '素材库') : '为「'.basename($source ?? '').'」设置新名称' }}</p>
                    <label for="fmp-name-{{ $this->getId() }}">文件夹名称</label><input id="fmp-name-{{ $this->getId() }}" class="fmp-name-input" x-ref="folderName" wire:model="folderName" wire:keydown.enter.prevent="save" autocomplete="off" placeholder="例如：春季活动" />
                @elseif ($operation === 'move')
                    <p>将「{{ basename($source ?? '') }}」移动至</p>
                    <nav class="fmp-breadcrumbs" aria-label="目标目录">@foreach ($this->breadcrumbs($movePath) as $crumb)@if(! $loop->first)<x-filament::icon icon="heroicon-o-chevron-right" class="fmp-chevron" />@endif<button type="button" wire:click="openMoveDestination(@js($crumb['path']))">{{ $crumb['name'] }}</button>@endforeach</nav>
                    <div class="fmp-destinations">@forelse (array_filter($destinations, fn($folder) => $folder['path'] !== $source && ! str_starts_with($folder['path'], ($source ?? '').'/')) as $folder)<button type="button" wire:click="openMoveDestination(@js($folder['path']))"><x-filament::icon icon="heroicon-o-folder" /><span>{{ $folder['name'] }}</span><x-filament::icon icon="heroicon-o-chevron-right" /></button>@empty<p>此目录没有其他文件夹</p>@endforelse</div>
                @else
                    <p>确定删除空文件夹「{{ basename($source ?? '') }}」？此操作无法撤销。</p>
                @endif
                <div class="fmp-errors" role="alert">@error('folderName')<p>{{ $message }}</p>@enderror @error('folder')<p>{{ $message }}</p>@enderror</div>
                <div class="fmp-action-footer"><button class="fmp-button fmp-secondary" type="button" wire:click="cancel">取消</button><button @class(['fmp-button', 'fmp-danger' => $operation === 'delete', 'fmp-primary' => $operation !== 'delete']) type="button" wire:click="save" wire:loading.attr="disabled">{{ match($operation) { 'move' => '移动到此处', 'delete' => '删除文件夹', default => '保存' } }}</button></div>
            </section>
        </div>
    @endif
</div>
