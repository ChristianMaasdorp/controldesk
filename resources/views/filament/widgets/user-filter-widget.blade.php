{{-- resources/views/filament/widgets/user-filter-widget.blade.php --}}
<div class="p-4 bg-white rounded-lg shadow mb-4">
    <div class="flex items-center space-x-4">
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('View Data For User') }}:
        </h2>

        <div class="w-64">
            <select
                onchange="Livewire.emit('userSelected', this.value)"
                class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
            >
                @foreach($this->getUsers() as $userId => $userName)
                    <option value="{{ $userId }}">{{ $userName }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
