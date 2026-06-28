<?php

namespace Yuga\Forge\Fields;

/**
 * A markdown-flavoured rich text field: a plain <textarea> (so the stored
 * value is always plain text, easy to sanitize/render downstream) plus a
 * small toolbar that inserts markdown tokens around the current selection.
 * Deliberately not a contenteditable/execCommand WYSIWYG - no JS dependency
 * beyond what's already loaded, and the stored value stays predictable.
 *
 * The toolbar buttons mutate the textarea's value directly, then dispatch a
 * real "input" event so YLC's existing ylc:model listener (which only cares
 * about the DOM event, not how the value changed) picks it up and syncs to
 * the server through the normal debounced flow - no new wiring needed.
 */
class RichEditor extends Field
{
    protected int $rows = 6;

    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function renderInput(mixed $value, ?string $error): string
    {
        $toolbarButtonClass = 'h-8 w-8 rounded-md text-sm font-bold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800';

        $wrap = <<<JS
            (function (button, before, after) {
                var wrapper = button.closest('[data-rich-editor]');
                var textarea = wrapper.querySelector('textarea');
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                var selected = textarea.value.slice(start, end);
                var replacement = before + (selected || 'text') + after;
                textarea.value = textarea.value.slice(0, start) + replacement + textarea.value.slice(end);
                textarea.focus();
                textarea.selectionStart = start + before.length;
                textarea.selectionEnd = start + before.length + (selected || 'text').length;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            })(this, ARGS)
            JS;

        $bold = str_replace('ARGS', "'**', '**'", $wrap);
        $italic = str_replace('ARGS', "'*', '*'", $wrap);
        $list = str_replace('ARGS', "'- ', ''", $wrap);
        $link = str_replace('ARGS', "'[', '](https://)'", $wrap);

        return '<div data-rich-editor class="grid gap-1.5">'
            . '<div class="flex gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800">'
            . '<button type="button" class="' . $toolbarButtonClass . '" title="Bold" onclick="' . $this->escape($bold) . '"><strong>B</strong></button>'
            . '<button type="button" class="' . $toolbarButtonClass . '" title="Italic" onclick="' . $this->escape($italic) . '"><em>I</em></button>'
            . '<button type="button" class="' . $toolbarButtonClass . '" title="List item" onclick="' . $this->escape($list) . '">&bull;</button>'
            . '<button type="button" class="' . $toolbarButtonClass . '" title="Link" onclick="' . $this->escape($link) . '">&#128279;</button>'
            . '</div>'
            . '<textarea rows="' . $this->rows . '" class="' . static::inputClass() . '" ylc:model="' . $this->modelAttr() . '">' . $this->escape($value) . '</textarea>'
            . '</div>';
    }

    protected function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
