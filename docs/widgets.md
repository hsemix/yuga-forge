---
layout: default
title: Widgets
nav_order: 9
---

# Widgets

Widgets are self-contained HTML components for dashboards — stat cards, bar charts, donut charts. They're plain PHP classes (not Live Components); interactivity belongs to whatever Live Component embeds them.

```php
use Yuga\Forge\Widgets\StatsWidget;
use Yuga\Forge\Widgets\BarChartWidget;
use Yuga\Forge\Widgets\DonutChartWidget;
```

## StatsWidget

A row of stat cards, each with a label, a big value, an optional change note, and an icon.

```php
class DashboardStats extends StatsWidget
{
    protected function stats(): array
    {
        return [
            [
                'label'  => 'Total customers',
                'value'  => (string) Customer::count(),
                'change' => '+12%',
                'tone'   => 'success',
                'icon'   => '👥',
            ],
            [
                'label' => 'Revenue',
                'value' => '$' . number_format(Order::sum('amount')),
                'tone'  => 'info',
                'icon'  => '💰',
            ],
        ];
    }
}
```

### Stat entry shape

| Key | Required | Description |
|-----|----------|-------------|
| `label` | Yes | Small label above the value |
| `value` | Yes | Large displayed number/text |
| `change` | No | Change note appended with "from previous period" |
| `tone` | No | `'success'`, `'info'`, `'warning'`, `'primary'` (default: `'primary'`) |
| `icon` | No | Emoji or Unicode symbol for the icon badge |

### Tone colours

| Tone | Colour |
|------|--------|
| `success` | Emerald |
| `info` | Azure (blue) |
| `warning` | Amber |
| `primary` | Azure (blue) |

## BarChartWidget

A vertical bar chart computed from a `label => value` map.

```php
class RevenueChart extends BarChartWidget
{
    protected function eyebrow(): ?string { return 'Revenue'; }
    protected function heading(): ?string { return 'Last 7 days'; }
    protected function badge(): ?string   { return 'This week'; }

    protected function data(): array
    {
        return [
            'Mon' => 1200,
            'Tue' => 980,
            'Wed' => 1540,
            'Thu' => 720,
            'Fri' => 1890,
            'Sat' => 640,
            'Sun' => 410,
        ];
    }
}
```

Bar heights are relative (the tallest bar = 95%). Bars shorter than 25% of the max are clamped to 25% so they remain visible.

### Overridable hooks

| Hook | Default | Purpose |
|------|---------|---------|
| `data(): array` | _(required)_ | `label => numeric value` map |
| `eyebrow(): ?string` | `null` | Small uppercase label above the heading |
| `heading(): ?string` | `null` | Chart title |
| `badge(): ?string` | `null` | Trailing pill badge in the header row (e.g. date range) |
| `gradientClass` (property) | azure gradient | Tailwind classes for bar fill |

## DonutChartWidget

A donut/pie chart — see `DonutChartWidget.php` for the full interface (same `data()` / `heading()` / `eyebrow()` pattern as `BarChartWidget`).

## Rendering a widget

Call `render()` inside any view or Live Component:

```php
echo DashboardStats::make()->render();
echo RevenueChart::make()->render();
```

Or, if the widget needs constructor arguments, instantiate directly before calling `make()` / `render()`:

```php
echo (new RevenueChart($startDate, $endDate))->render();
```

## Notes

- Widgets have no CSS class dependency beyond Tailwind. All colours are fixed literal class names, not runtime-computed strings — this is intentional to ensure Tailwind's build scanner can see them.
- Icons are plain strings (emoji / Unicode). There is no icon library in this stack.
