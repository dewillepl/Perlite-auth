<?php

/*!
 * Perlite-Auth - Obsidian Bases (.base) support
 */

namespace Perlite;

/**
 * Loads a .base config file, collects matching vault notes as rows, and
 * builds the resolved columns/rows/sort/width data needed to render each
 * of its table views.
 */
class PerliteBasesRenderer
{
    public static function loadBaseFile(string $absolutePath): array
    {
        $text = file_get_contents($absolutePath);
        if ($text === false) {
            return ['filters' => [], 'formulas' => [], 'properties' => [], 'views' => []];
        }
        $config = PerliteBasesYaml::parse($text);

        return [
            'filters' => $config['filters'] ?? [],
            'formulas' => $config['formulas'] ?? [],
            'properties' => $config['properties'] ?? [],
            'views' => $config['views'] ?? [],
        ];
    }

    /** Recursively collects every markdown note in the vault as a row (file info + frontmatter). */
    public static function collectRows(string $rootDir): array
    {
        $rows = [];
        self::walk($rootDir, '', $rows);
        return $rows;
    }

    private static function walk(string $rootDir, string $relFolder, array &$rows): void
    {
        $dir = $relFolder === '' ? $rootDir : $rootDir . '/' . $relFolder;
        $entries = @scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                continue;
            }

            $full = $dir . '/' . $entry;

            if (is_dir($full)) {
                $nextRel = $relFolder === '' ? $entry : $relFolder . '/' . $entry;
                self::walk($rootDir, $nextRel, $rows);
                continue;
            }

            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'md') {
                continue;
            }

            $basename = substr($entry, 0, -3);
            $rows[] = [
                'file' => [
                    'folder' => $relFolder,
                    'name' => $entry,
                    'basename' => $basename,
                    'path' => $relFolder === '' ? $entry : $relFolder . '/' . $entry,
                ],
                'properties' => self::readFrontmatter($full),
            ];
        }
    }

    private static function readFrontmatter(string $file): array
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return [];
        }
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $m)) {
            return [];
        }
        return PerliteBasesYaml::parse($m[1]);
    }

    /**
     * Builds the render-ready data for one view: filtered+sorted rows, resolved
     * columns (key, displayName, width), and display options.
     */
    public static function buildView(array $baseConfig, array $view, array $allRows): array
    {
        $combinedFilter = ['and' => [$baseConfig['filters'], $view['filters'] ?? []]];

        $matched = [];
        foreach ($allRows as $row) {
            if (!PerliteBasesExpr::evaluateFilterGroup($combinedFilter, $row)) {
                continue;
            }
            $row['formulas'] = self::computeFormulas($baseConfig['formulas'], $row);
            $matched[] = $row;
        }

        $order = $view['order'] ?? [];
        if (empty($order)) {
            $seen = [];
            foreach ($matched as $row) {
                foreach (array_keys($row['properties']) as $prop) {
                    $seen[$prop] = true;
                }
            }
            $order = array_keys($seen);
            sort($order);
        }

        $columns = [];
        foreach ($order as $key) {
            $isFormula = str_starts_with($key, 'formula.');
            $name = $isFormula ? substr($key, strlen('formula.')) : $key;
            $lookupKey = $isFormula ? $key : 'note.' . $key;

            $columns[] = [
                'key' => $key,
                'name' => $name,
                'isFormula' => $isFormula,
                'displayName' => $baseConfig['properties'][$lookupKey]['displayName'] ?? $name,
                'width' => $view['columnSize'][$lookupKey] ?? null,
            ];
        }

        $sortSpec = $view['sort'] ?? [];
        if (!empty($sortSpec)) {
            usort($matched, function ($a, $b) use ($sortSpec) {
                foreach ($sortSpec as $s) {
                    $prop = $s['property'] ?? '';
                    $dir = strtoupper($s['direction'] ?? 'ASC') === 'DESC' ? -1 : 1;
                    $va = self::sortValue($a, $prop);
                    $vb = self::sortValue($b, $prop);
                    $cmp = strnatcasecmp((string) $va, (string) $vb);
                    if ($cmp !== 0) {
                        return $cmp * $dir;
                    }
                }
                return 0;
            });
        }

        return [
            'name' => $view['name'] ?? '',
            'columns' => $columns,
            'rows' => $matched,
            'rowHeight' => $view['rowHeight'] ?? 'default',
        ];
    }

    private static function sortValue(array $row, string $prop)
    {
        if (str_starts_with($prop, 'file.')) {
            return $row['file'][substr($prop, 5)] ?? '';
        }
        if (str_starts_with($prop, 'formula.')) {
            return $row['formulas'][substr($prop, 8)] ?? '';
        }
        return $row['properties'][$prop] ?? '';
    }

    private static function computeFormulas(array $formulas, array $row): array
    {
        $computed = [];
        foreach ($formulas as $name => $expression) {
            if (!is_string($expression) || trim($expression) === '') {
                $computed[$name] = '';
                continue;
            }
            $computed[$name] = PerliteBasesExpr::evaluate(trim($expression), $row);
        }
        return $computed;
    }
}
