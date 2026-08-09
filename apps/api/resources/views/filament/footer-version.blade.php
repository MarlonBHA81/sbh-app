{{-- Version stamp in the admin panel footer — how you confirm what's deployed. --}}
<div class="fi-footer-version px-6 py-3 text-center text-xs text-gray-400 dark:text-gray-500">
    {{ config('version.name', 'SBH Community') }}
    &middot; v{{ config('version.number') }}
    &middot; released {{ config('version.released') }}
</div>
