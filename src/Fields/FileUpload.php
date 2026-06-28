<?php

namespace Yuga\Forge\Fields;

/**
 * A file input wired to YLC's existing upload mechanism: ylc:model on a
 * `type="file"` input already POSTs to /ylc/upload and sets the bound
 * property to the response shape ({token, name, type, size, preview}, or an
 * array of those when `multiple()`) on change - see uploadFiles() in
 * public/plugins/ylc-live-plugin.js. This field just renders that input
 * correctly and previews whatever's currently bound: either a freshly
 * uploaded temp file (the response shape above) or an already-committed
 * value from a previous save (a plain stored path string, or array of them).
 *
 * A temp upload is committed (moved out of the temp upload cache into
 * permanent storage under public/uploads/{directory}) automatically on
 * save, via dehydrate() - see commit() below. Call commit() directly
 * instead if you need to commit somewhere Resource::save() doesn't already
 * reach (e.g. a bulk action).
 */
class FileUpload extends Field
{
    protected ?string $accept = null;
    protected bool $multiple = false;
    protected string $directory = 'uploads';

    public function accept(string $accept): static
    {
        $this->accept = $accept;

        return $this;
    }

    public function multiple(): static
    {
        $this->multiple = true;

        return $this;
    }

    public function directory(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function dehydrate(mixed $value): mixed
    {
        return parent::dehydrate(static::commit($value, $this->directory));
    }

    public function renderInput(mixed $value, ?string $error): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $accept = $this->accept ? ' accept="' . $escape($this->accept) . '"' : '';
        $multiple = $this->multiple ? ' multiple' : '';

        $input = '<input type="file" class="h-10 ' . static::inputClass() . '"' . $accept . $multiple . ' ylc:model="' . $this->modelAttr() . '">';

        $preview = $this->renderPreview($value);

        return $preview !== '' ? $input . $preview : $input;
    }

    public function renderDisplay(mixed $value): string
    {
        $preview = $this->renderPreview($value);

        return $preview === '' ? '&mdash;' : $preview;
    }

    protected function renderPreview(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        $entries = is_array($value) && array_is_list($value) ? $value : [$value];
        $html = '';

        foreach ($entries as $entry) {
            $html .= $this->renderOnePreview($entry);
        }

        return $html === '' ? '' : '<div class="mt-2 flex flex-wrap gap-2">' . $html . '</div>';
    }

    protected function renderOnePreview(mixed $entry): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        if (is_array($entry)) {
            // A freshly uploaded temp file, not yet committed: {token, name,
            // type, size, preview} - "preview" is /ylc/temp-upload/{token},
            // which serves the file with the right Content-Type already.
            $name = $entry['name'] ?? '';
            $url = $entry['preview'] ?? null;
            $isImage = str_starts_with((string) ($entry['type'] ?? ''), 'image/');
        } else {
            // An already-committed stored path, e.g.
            // "/uploads/products/{token}_{original-name}.png".
            $url = (string) $entry;
            $name = $this->displayName($url);
            $isImage = (bool) preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $url);
        }

        if ($name === '') {
            return '';
        }

        if ($isImage && $url) {
            return '<span class="inline-flex items-center gap-2 rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">'
                . '<img src="' . $escape($url) . '" alt="" class="h-8 w-8 rounded object-cover">'
                . $escape($name) . '</span>';
        }

        return '<span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">' . $escape($name) . '</span>';
    }

    /**
     * A committed file's basename is "{40-char-hex-token}_{original-name}"
     * (see commitOne()) - strip that prefix back off for display so the
     * preview shows the name the user actually uploaded, not the storage key.
     */
    protected function displayName(string $path): string
    {
        return (string) preg_replace('/^[a-f0-9]{32,64}_/i', '', basename($path));
    }

    /**
     * Moves a temp upload (or array of them) into permanent storage under
     * public/uploads/{$directory}, returning the stored path(s) to persist.
     * A value that isn't a fresh-upload shape (e.g. unchanged on edit, so
     * it's still the previously stored path string) passes through as-is.
     */
    public static function commit(mixed $value, string $directory = 'uploads'): mixed
    {
        if (is_array($value) && array_is_list($value)) {
            return array_map(fn ($entry) => static::commitOne($entry, $directory), $value);
        }

        return static::commitOne($value, $directory);
    }

    protected static function commitOne(mixed $value, string $directory): mixed
    {
        if (!is_array($value) || !isset($value['token'], $value['name'])) {
            return $value;
        }

        $meta = app()->get('cache')->get("ylc-upload:{$value['token']}");

        if (!$meta || !file_exists($meta['path'])) {
            return $value;
        }

        $targetDir = path('public/uploads/' . trim($directory, '/'));

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = $value['token'] . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $value['name']);

        copy($meta['path'], $targetDir . '/' . $filename);

        return '/uploads/' . trim($directory, '/') . '/' . $filename;
    }
}
