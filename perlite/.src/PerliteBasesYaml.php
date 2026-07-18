<?php

/*!
 * Perlite-Auth - Obsidian Bases (.base) support
 */

namespace Perlite;

class PerliteBasesYaml
{
    /**
     * Parses the YAML subset used by Obsidian .base files: nested maps/lists via
     * indentation, quoted scalars, block scalars (| and |-), and inline [].
     */
    public static function parse(string $text): array
    {
        $lines = self::tokenize($text);
        $pos = 0;
        if (empty($lines)) {
            return [];
        }
        $value = self::parseBlock($lines, $pos, 0);
        return is_array($value) ? $value : [];
    }

    private static function tokenize(string $text): array
    {
        $raw = preg_split('/\R/', $text);
        $lines = [];
        foreach ($raw as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || $trimmed[0] === '#') {
                continue;
            }
            $indent = strlen($line) - strlen($trimmed);
            $lines[] = [$indent, rtrim($trimmed)];
        }
        return $lines;
    }

    private static function isSequenceLine(string $text): bool
    {
        return $text === '-' || (strlen($text) >= 2 && $text[0] === '-' && $text[1] === ' ');
    }

    /** @param array<int, array{0:int,1:string}> $lines */
    private static function parseBlock(array $lines, int &$pos, int $minIndent)
    {
        if ($pos >= count($lines) || $lines[$pos][0] < $minIndent) {
            return '';
        }
        $indent = $lines[$pos][0];
        if (self::isSequenceLine($lines[$pos][1])) {
            return self::parseSequence($lines, $pos, $indent);
        }
        return self::parseMapping($lines, $pos, $indent);
    }

    private static function parseSequence(array $lines, int &$pos, int $indent): array
    {
        $result = [];
        $count = count($lines);

        while ($pos < $count && $lines[$pos][0] === $indent && self::isSequenceLine($lines[$pos][1])) {
            $text = $lines[$pos][1];
            $itemText = $text === '-' ? '' : ltrim(substr($text, 1));
            $contentIndent = $indent + 2;
            $pos++;

            if ($itemText === '') {
                $result[] = self::parseBlock($lines, $pos, $contentIndent);
                continue;
            }

            [$key, $val] = self::splitKeyValue($itemText);

            if ($key === null) {
                $result[] = self::parseScalar($itemText);
                continue;
            }

            // Sequence item is a map: "- key: value" followed by sibling keys at $contentIndent
            $map = [];
            $map[$key] = self::resolveValue($lines, $pos, $contentIndent, $val);
            while ($pos < $count && $lines[$pos][0] === $contentIndent && !self::isSequenceLine($lines[$pos][1])) {
                [$k2, $v2] = self::splitKeyValue($lines[$pos][1]);
                if ($k2 === null) {
                    break;
                }
                $pos++;
                $map[$k2] = self::resolveValue($lines, $pos, $contentIndent, $v2);
            }
            $result[] = $map;
        }

        return $result;
    }

    private static function parseMapping(array $lines, int &$pos, int $indent): array
    {
        $result = [];
        $count = count($lines);

        while ($pos < $count && $lines[$pos][0] === $indent && !self::isSequenceLine($lines[$pos][1])) {
            [$key, $val] = self::splitKeyValue($lines[$pos][1]);
            if ($key === null) {
                break;
            }
            $pos++;
            $result[$key] = self::resolveValue($lines, $pos, $indent, $val);
        }

        return $result;
    }

    /** Resolves a map/list value: nested block, block scalar, or inline scalar. */
    private static function resolveValue(array $lines, int &$pos, int $parentIndent, ?string $val)
    {
        $count = count($lines);

        if ($val === '|' || $val === '|-' || $val === '|+') {
            $blockLines = [];
            while ($pos < $count && $lines[$pos][0] > $parentIndent) {
                $blockLines[] = $lines[$pos][1];
                $pos++;
            }
            $joined = implode("\n", $blockLines);
            if ($val === '|') {
                $joined .= "\n";
            }
            return $joined;
        }

        if ($val === null) {
            if ($pos < $count && $lines[$pos][0] > $parentIndent) {
                return self::parseBlock($lines, $pos, $lines[$pos][0]);
            }
            return '';
        }

        return self::parseScalar($val);
    }

    /**
     * Splits "key: value" on the first colon that is outside of quotes.
     * @return array{0: ?string, 1: ?string}
     */
    private static function splitKeyValue(string $text): array
    {
        $len = strlen($text);
        $inSingle = false;
        $inDouble = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];

            if ($inSingle) {
                if ($ch === "'") {
                    $inSingle = false;
                }
                continue;
            }

            if ($inDouble) {
                if ($ch === '\\') {
                    $i++;
                } elseif ($ch === '"') {
                    $inDouble = false;
                }
                continue;
            }

            if ($ch === "'") {
                $inSingle = true;
                continue;
            }
            if ($ch === '"') {
                $inDouble = true;
                continue;
            }
            if ($ch === ':' && ($i + 1 === $len || $text[$i + 1] === ' ')) {
                $key = trim(substr($text, 0, $i));
                $rest = trim(substr($text, $i + 1));
                return [$key, $rest === '' ? null : $rest];
            }
        }

        return [null, null];
    }

    private static function parseScalar(string $text)
    {
        $text = trim($text);

        if ($text === '' || $text === '~' || strtolower($text) === 'null') {
            return null;
        }
        if ($text === '[]') {
            return [];
        }

        $first = $text[0];
        $last = $text[strlen($text) - 1];

        if ($first === '"' && $last === '"' && strlen($text) >= 2) {
            $inner = substr($text, 1, -1);
            return str_replace(['\\"', '\\\\', '\\n', '\\t'], ['"', '\\', "\n", "\t"], $inner);
        }

        if ($first === "'" && $last === "'" && strlen($text) >= 2) {
            $inner = substr($text, 1, -1);
            return str_replace("''", "'", $inner);
        }

        if (preg_match('/^-?\d+$/', $text)) {
            return (int) $text;
        }

        if (strtolower($text) === 'true') {
            return true;
        }
        if (strtolower($text) === 'false') {
            return false;
        }

        return $text;
    }
}
