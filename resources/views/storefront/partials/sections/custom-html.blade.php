<div class="prose dark:prose-invert max-w-none">
    {!! \Mews\Purifier\Facades\Purifier::clean($section->config['html'] ?? '') !!}
</div>
