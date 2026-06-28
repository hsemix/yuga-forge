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

        $input = '<input type="file" class="' . static::inputClass() . '"' . $accept . $multiple . ' ylc:model="' . $this->modelAttr() . '">';

        $preview = $this->renderPreview($value);

        return $preview !== '' ? $input . $preview : $input;
    }

    protected function renderPreview(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        $entries = is_array($value) && array_is_list($value) ? $value : [$value];
        $html = '';

        foreach ($entries as $entry) {
            $name = is_array($entry) ? ($entry['name'] ?? '') : basename((string) $entry);

            if ($name === '') {
                continue;
            }

            $html .= '<span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span> ';
        }

        return $html === '' ? '' : '<div class="mt-2 flex flex-wrap gap-1.5">' . $html . '</div>';
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
