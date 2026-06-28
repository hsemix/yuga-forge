<?php

namespace Yuga\Forge\Fields;

/**
 * Free-form tag entry (type a value, press Enter or comma to add a chip,
 * click a chip's x to remove it) - distinct from MultiSelect, which only
 * lets you pick from a fixed option list. Stores a JSON-encoded array in a
 * plain TEXT column, the same convention MultiSelect already established
 * (Yuga's column builder has no JSON column type).
 *
 * No JS dependency: chip add/remove is plain inline event-attribute
 * handlers (onkeydown/onclick), not a <script> tag - YLC's DOM morphing
 * replaces innerHTML, and an injected <script> tag doesn't execute that
 * way, but attributes set via setAttribute/onX assignment still fire
 * normally (the same reason RichEditor's toolbar avoided <script> too).
 */
class TagsInput extends Field
{
    protected string $placeholder = 'Add a tag...';

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function renderInput(mixed $value, ?string $error): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $tags = $this->normalize($value);

        $removeJs = "var root=this.closest('[data-tags-input]');this.closest('[data-tag]').remove();"
            . "var hidden=root.querySelector('input[type=hidden]');"
            . "hidden.value=JSON.stringify(Array.from(root.querySelectorAll('[data-tag]')).map(function(el){return el.dataset.tag}));"
            . "hidden.dispatchEvent(new Event('input',{bubbles:true}));";

        $chips = '';

        foreach ($tags as $tag) {
            $chips .= '<span data-tag="' . $escape($tag) . '" class="inline-flex items-center gap-1 rounded-md bg-azure-50 px-2 py-1 text-xs font-bold text-azure-700 dark:bg-azure-500/10 dark:text-azure-200">'
                . '<span>' . $escape($tag) . '</span>'
                . '<button type="button" class="text-azure-500 hover:text-azure-700" onclick="' . $escape($removeJs) . '">&times;</button></span>';
        }

        $addJs = <<<'JS'
            if (event.key !== 'Enter' && event.key !== ',') { return }
            event.preventDefault();
            var value = this.value.trim().replace(/,$/, '');
            if (!value) { return }
            var root = this.closest('[data-tags-input]');
            var hidden = root.querySelector('input[type=hidden]');
            function sync() {
                hidden.value = JSON.stringify(Array.from(root.querySelectorAll('[data-tag]')).map(function (el) { return el.dataset.tag; }));
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
            }
            var chip = document.createElement('span');
            chip.dataset.tag = value;
            chip.className = 'inline-flex items-center gap-1 rounded-md bg-azure-50 px-2 py-1 text-xs font-bold text-azure-700 dark:bg-azure-500/10 dark:text-azure-200';
            var label = document.createElement('span');
            label.textContent = value;
            chip.appendChild(label);
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'text-azure-500 hover:text-azure-700';
            btn.textContent = '×';
            btn.onclick = function () { chip.remove(); sync(); };
            chip.appendChild(btn);
            this.before(chip);
            this.value = '';
            sync();
            JS;

        return '<div data-tags-input class="flex flex-wrap items-center gap-1.5 rounded-lg border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-950">'
            . $chips
            . '<input type="text" class="min-w-24 flex-1 border-0 bg-transparent p-1 text-sm text-slate-950 outline-none dark:text-slate-100" placeholder="' . $escape($this->placeholder) . '" onkeydown="' . $escape($addJs) . '">'
            . '<input type="hidden" ylc:model="' . $this->modelAttr() . '" value="' . $escape(json_encode($tags)) . '">'
            . '</div>';
    }

    public function dehydrate(mixed $value): mixed
    {
        return parent::dehydrate(json_encode($this->normalize($value)));
    }

    public function hydrate(mixed $value): mixed
    {
        return parent::hydrate($this->normalize($value));
    }

    public function renderDisplay(mixed $value): string
    {
        $tags = $this->normalize($value);

        return $tags === [] ? '&mdash;' : htmlspecialchars(implode(', ', $tags), ENT_QUOTES, 'UTF-8');
    }

    /**
     * $value may already be a real array (set during this session, e.g. via
     * a live update sending the hidden input's current string straight
     * through) or a JSON-encoded string (freshly hydrated from storage, or
     * the hidden input's own client-side value) - accept either.
     */
    protected function normalize(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
