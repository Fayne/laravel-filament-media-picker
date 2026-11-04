<details class="ufm-folder-manager" style="padding: 12px 16px; border-bottom: 1px solid #8884;">
    <summary style="cursor: pointer; font-weight: 600;">管理文件夹</summary>
    <p style="margin: 8px 0;">仅支持新建，以及重命名、移动、删除空文件夹。文件和非空文件夹不受影响。</p>
    <div style="display: flex; align-items: center; gap: 12px; margin: 8px 0;">
        @if ($path !== $directory)
            <x-filament::button size="sm" color="gray" wire:click="up" type="button">上一级</x-filament::button>
        @endif
        <span>{{ $path === '' ? '根目录' : $path }}</span>
        @if ($operation !== 'move')
            <x-filament::button size="sm" wire:click="begin('create')" type="button">新建文件夹</x-filament::button>
        @endif
    </div>
    <div style="max-height: 200px; overflow: auto;">
        @forelse ($folders as $folder)
            <div wire:key="folder-{{ $folder['path'] }}" style="display: flex; align-items: center; gap: 12px; padding: 6px 0;">
                <button type="button" wire:click="open(@js($folder['path']))" style="flex: 1; text-align: left;">📁 {{ $folder['name'] }}</button>
                @if ($operation !== 'move')
                    <x-filament::button size="sm" color="gray" type="button" wire:click="begin('rename', @js($folder['path']))">重命名</x-filament::button>
                    <x-filament::button size="sm" color="gray" type="button" wire:click="begin('move', @js($folder['path']))">移动</x-filament::button>
                    <x-filament::button size="sm" color="danger" type="button" wire:click="begin('delete', @js($folder['path']))">删除</x-filament::button>
                @endif
            </div>
        @empty
            <p>当前目录没有子文件夹。</p>
        @endforelse
    </div>
    @if ($operation)
        <div style="margin-top: 12px; padding: 12px; border: 1px solid #8884; border-radius: 8px;">
            @if (in_array($operation, ['create', 'rename'], true))
                <label>{{ $operation === 'create' ? '新文件夹名称' : '新名称' }}
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="folderName" wire:keydown.enter.prevent="save" aria-label="文件夹名称" />
                    </x-filament::input.wrapper>
                </label>
            @elseif ($operation === 'move')
                <p>正在移动：{{ $source }}。打开上方文件夹选择目标位置，再确认。</p>
            @else
                <p>确认删除空文件夹「{{ $source }}」？此操作无法撤销。</p>
            @endif
            <div style="display: flex; gap: 8px; margin-top: 8px;">
                <x-filament::button size="sm" type="button" wire:click="save" wire:loading.attr="disabled">{{ $operation === 'move' ? '移动到当前文件夹' : '确认' }}</x-filament::button>
                <x-filament::button size="sm" color="gray" type="button" wire:click="cancel">取消</x-filament::button>
            </div>
        </div>
    @endif
    @error('folderName') <p role="alert">{{ $message }}</p> @enderror
    @error('folder') <p role="alert">{{ $message }}</p> @enderror
</details>
