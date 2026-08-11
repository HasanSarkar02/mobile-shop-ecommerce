<div class="prose dark:prose-invert max-w-none">
    {!! \App\Support\Purifier\StorefrontPurifier::clean($section->config['html'] ?? '') !!}
</div>
