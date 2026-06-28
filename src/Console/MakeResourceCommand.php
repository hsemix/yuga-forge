<?php

namespace Yuga\Forge\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Yuga\Console\Command;
use Yuga\Support\Inflect;

class MakeResourceCommand extends Command
{
    protected $name = 'make:resource';

    protected $description = 'Make a Yuga Forge Resource (Form + Table skeleton) from an Elegant model';

    protected const EXCLUDED_DEFAULTS = ['created_at', 'updated_at', 'deleted_at'];

    public function handle()
    {
        $appNamespace = env('APP_NAMESPACE', 'App');

        $name = trim((string) $this->argument('name'));
        $resourceName = str_ends_with($name, 'Resource') ? $name : $name . 'Resource';

        $modelOption = trim((string) $this->option('model'));
        $modelClass = $modelOption !== ''
            ? (str_contains($modelOption, '\\') ? $modelOption : $appNamespace . '\\Models\\' . $modelOption)
            : $appNamespace . '\\Models\\' . Inflect::singularize(preg_replace('/Resource$/', '', $name));

        if (!class_exists($modelClass)) {
            $this->error("Model [{$modelClass}] does not exist. Create it first (e.g. \"php yuga make:model " . class_base($modelClass) . "\"), or point at an existing one with --model=.");

            return;
        }

        $instance = new $modelClass();
        $recordKey = trim((string) $this->option('key')) ?: 'public_id';
        $excluded = array_merge(self::EXCLUDED_DEFAULTS, [$recordKey]);

        $columns = $this->readTableColumns($instance->getTable());

        if ($columns === []) {
            $this->error("Could not read columns for table [{$instance->getTable()}] — does it exist yet?");

            return;
        }

        $fieldClasses = [];
        $formLines = [];
        $tableLines = [];

        foreach ($columns as $column) {
            $columnName = $column->getName();

            if ($column->getIndex() === 'PRIMARY KEY' || in_array($columnName, $excluded, true)) {
                continue;
            }

            [$fieldExpr, $fieldClass] = $this->fieldExpression($columnName, $column);
            $fieldClasses[$fieldClass] = true;
            $formLines[] = '            ' . $fieldExpr . ',';

            $searchable = $this->isSearchableType((string) $column->getType());
            $tableLines[] = '            TextColumn::make(\'' . $columnName . '\')->sortable()' . ($searchable ? '->searchable()' : '') . ',';
        }

        $namespace = trim((string) $this->option('namespace')) ?: $appNamespace . '\\Live\\Admin';
        $directory = rtrim(trim((string) $this->option('dir')) ?: 'app/Live/Admin', '/');
        $liveName = 'admin.' . $this->kebab(preg_replace('/Resource$/', '', $resourceName)) . '-resource';

        $targetDir = path($directory);

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filePath = $targetDir . '/' . $resourceName . '.php';

        if (file_exists($filePath) && !$this->option('force')) {
            $this->error("{$resourceName}.php already exists in {$directory} — pass --force to overwrite.");

            return;
        }

        file_put_contents($filePath, $this->compile(
            $namespace,
            $resourceName,
            $modelClass,
            $recordKey,
            $liveName,
            array_keys($fieldClasses),
            $formLines,
            $tableLines,
        ));

        $this->info("{$resourceName} created at {$directory}/{$resourceName}.php");
        $this->line('Review the generated form/table — column types are inferred from the database schema and may need adjustment (e.g. swapping a column to a Select or BadgeColumn).');
        $this->line('Forge only generates the Resource class itself: wire up a route + controller + view embedding it (`<?= ylc(\'' . $liveName . '\') ?>`), the same way the other Live/Admin resources are wired.');
    }

    /**
     * @return array{0: string, 1: string} [PHP expression, field class short name]
     */
    protected function fieldExpression(string $columnName, $column): array
    {
        $type = strtolower((string) $column->getType());
        $required = !$column->getNullable() && $column->getDefaultValue() === null && !$column->getIncrement();

        if (str_starts_with($type, 'enum(')) {
            $options = [];

            preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $matches);

            foreach ($matches[1] ?? [] as $option) {
                $options[$option] = ucfirst(str_replace('_', ' ', $option));
            }

            $expr = "Select::make('{$columnName}')->options(" . $this->phpArrayLiteral($options) . ')';

            return [$required ? $expr . '->required()' : $expr, 'Select'];
        }

        if (str_starts_with($type, 'tinyint(1)')) {
            return ["Toggle::make('{$columnName}')", 'Toggle'];
        }

        if (str_contains($type, 'text')) {
            $expr = "Textarea::make('{$columnName}')";

            return [$required ? $expr . '->required()' : $expr, 'Textarea'];
        }

        if (str_contains($type, 'int') || str_starts_with($type, 'decimal') || str_starts_with($type, 'float') || str_starts_with($type, 'double')) {
            $expr = "TextInput::make('{$columnName}')->number()";

            return [$required ? $expr . '->required()' : $expr, 'TextInput'];
        }

        $expr = "TextInput::make('{$columnName}')";

        return [$required ? $expr . '->required()' : $expr, 'TextInput'];
    }

    protected function isSearchableType(string $type): bool
    {
        $type = strtolower($type);

        return str_starts_with($type, 'varchar') || str_starts_with($type, 'char') || str_contains($type, 'text') || str_starts_with($type, 'enum(');
    }

    protected function phpArrayLiteral(array $options): string
    {
        $pairs = [];

        foreach ($options as $key => $value) {
            $pairs[] = "'" . addslashes((string) $key) . "' => '" . addslashes((string) $value) . "'";
        }

        return '[' . implode(', ', $pairs) . ']';
    }

    protected function readTableColumns(string $table): array
    {
        $tableApiClass = '\\Yuga\\Database\\Migration\\Schema\\' . ucfirst((string) env('DATABASE_DRIVER', 'mysql')) . '\\Table';
        $schema = new $tableApiClass($table, true);

        return $schema->getColumns();
    }

    protected function kebab(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $value));
    }

    protected function compile(
        string $namespace,
        string $resourceName,
        string $modelClass,
        string $recordKey,
        string $liveName,
        array $fieldClasses,
        array $formLines,
        array $tableLines,
    ): string {
        $uses = ["use {$modelClass};"];

        foreach ($fieldClasses as $fieldClass) {
            $uses[] = "use Yuga\\Forge\\Fields\\{$fieldClass};";
        }

        $uses[] = 'use Yuga\\Forge\\Columns\\TextColumn;';
        $uses[] = 'use Yuga\\Forge\\Resource;';
        $uses[] = 'use Yuga\\Forge\\Schema\\Form;';
        $uses[] = 'use Yuga\\Forge\\Schema\\Table;';
        $uses[] = 'use Yuga\\Live\\Attributes\\Live;';

        sort($uses);

        $usesPhp = implode("\n", $uses);
        $formPhp = $formLines === [] ? '' : implode("\n", $formLines) . "\n        ";
        $tablePhp = implode("\n", $tableLines) . "\n        ";
        $modelShort = class_base($modelClass);

        return <<<PHP
        <?php

        namespace {$namespace};

        {$usesPhp}

        #[Live(name: '{$liveName}')]
        class {$resourceName} extends Resource
        {
            protected string \$model = {$modelShort}::class;
            protected string \$recordKey = '{$recordKey}';

            public static function form(Form \$form): Form
            {
                return \$form->schema([
        {$formPhp}]);
            }

            public static function table(Table \$table): Table
            {
                return \$table->columns([
        {$tablePhp}]);
            }
        }

        PHP;
    }

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The resource name, e.g. "Products" (becomes ProductsResource)'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['model', null, InputOption::VALUE_OPTIONAL, 'The Elegant model class to introspect (short name resolves against App\\Models\\, default: singularized resource name)'],
            ['key', null, InputOption::VALUE_OPTIONAL, 'The record-key column used in URLs/lookups', 'public_id'],
            ['namespace', null, InputOption::VALUE_OPTIONAL, 'Namespace for the generated class', null],
            ['dir', null, InputOption::VALUE_OPTIONAL, 'Directory to write the generated class into', 'app/Live/Admin'],
            ['force', null, InputOption::VALUE_NONE, 'Overwrite the file if it already exists'],
        ];
    }
}
