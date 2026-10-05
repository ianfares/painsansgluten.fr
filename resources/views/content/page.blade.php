<x-layouts.app :title="$page->seo_title ?: $page->title">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <x-ui.breadcrumb :items="[['label' => $page->title]]" class="mb-6" />
        <h1 class="mb-6 text-2xl font-semibold text-ink">{{ $page->title }}</h1>
        <div class="prose prose-sm max-w-none">{!! $page->content !!}</div>
    </div>
</x-layouts.app>
