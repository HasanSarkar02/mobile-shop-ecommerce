<x-ui.select name="sort"
    onchange="const url = new URL(window.location); url.searchParams.set('sort', this.value); url.searchParams.delete('page'); window.location = url;"
    class="w-auto">
    @foreach (\App\Enums\ProductSortOption::cases() as $option)
        <option value="{{ $option->value }}" {{ $filters->sort === $option->value ? 'selected' : '' }}>
            {{ $option->label() }}</option>
    @endforeach
</x-ui.select>
