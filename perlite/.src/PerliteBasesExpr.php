<?php

/*!
 * Perlite-Auth - Obsidian Bases (.base) support
 */

namespace Perlite;

/**
 * Tokenizes, parses and evaluates the Obsidian Bases formula/filter expression
 * language subset used by this vault: property refs, file.* refs, string
 * literals, ! == != && || +, and method/function calls (if, startsWith,
 * endsWith, contains, isEmpty).
 */
class PerliteBasesExpr
{
    /** @var array<int, array{0:string,1:mixed}> */
    private array $tokens = [];
    private int $pos = 0;

    public static function evaluate(string $expression, array $row)
    {
        $parser = new self();
        $ast = $parser->parseExpression($expression);
        return self::evalNode($ast, $row);
    }

    /**
     * Evaluates an Obsidian Bases filter node, which is either:
     *  - a plain expression string
     *  - ['and' => [...]] / ['or' => [...]] with nested strings/groups
     *  - empty/missing (=> true)
     */
    public static function evaluateFilterGroup($filter, array $row): bool
    {
        if ($filter === null || $filter === '' || $filter === []) {
            return true;
        }

        if (is_string($filter)) {
            return self::truthy(self::evaluate($filter, $row));
        }

        if (is_array($filter)) {
            if (isset($filter['and'])) {
                foreach ((array) $filter['and'] as $item) {
                    if (!self::evaluateFilterGroup($item, $row)) {
                        return false;
                    }
                }
                return true;
            }
            if (isset($filter['or'])) {
                foreach ((array) $filter['or'] as $item) {
                    if (self::evaluateFilterGroup($item, $row)) {
                        return true;
                    }
                }
                return false;
            }
        }

        return true;
    }

    private static function truthy($value): bool
    {
        return $value !== null && $value !== '' && $value !== false;
    }

    private static function toStr($value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        if ($value === true) {
            return 'true';
        }
        return (string) $value;
    }

    private static function evalNode(array $node, array $row)
    {
        switch ($node[0]) {
            case 'str':
                return $node[1];

            case 'ident':
                if ($node[1] === 'file') {
                    return ['__file__' => true];
                }
                return $row['properties'][$node[1]] ?? null;

            case 'field':
                $base = self::evalNode($node[1], $row);
                if (is_array($base) && isset($base['__file__'])) {
                    return $row['file'][$node[2]] ?? null;
                }
                return null;

            case 'not':
                return !self::truthy(self::evalNode($node[1], $row));

            case 'bin':
                [, $op, $leftNode, $rightNode] = $node;
                if ($op === '+') {
                    return self::toStr(self::evalNode($leftNode, $row)) . self::toStr(self::evalNode($rightNode, $row));
                }
                if ($op === '==') {
                    return self::toStr(self::evalNode($leftNode, $row)) === self::toStr(self::evalNode($rightNode, $row));
                }
                if ($op === '!=') {
                    return self::toStr(self::evalNode($leftNode, $row)) !== self::toStr(self::evalNode($rightNode, $row));
                }
                if ($op === '&&') {
                    return self::truthy(self::evalNode($leftNode, $row)) && self::truthy(self::evalNode($rightNode, $row));
                }
                if ($op === '||') {
                    return self::truthy(self::evalNode($leftNode, $row)) || self::truthy(self::evalNode($rightNode, $row));
                }
                return null;

            case 'call':
                $args = $node[2];
                if ($node[1] === 'if' && count($args) === 3) {
                    $cond = self::truthy(self::evalNode($args[0], $row));
                    return $cond ? self::evalNode($args[1], $row) : self::evalNode($args[2], $row);
                }
                return null;

            case 'method':
                [, $baseNode, $method, $argNodes] = $node;
                $base = self::evalNode($baseNode, $row);
                $baseStr = self::toStr($base);
                switch ($method) {
                    case 'isEmpty':
                        return $base === null || $base === '' || (is_array($base) && count($base) === 0);
                    case 'startsWith':
                        return str_starts_with($baseStr, self::toStr(self::evalNode($argNodes[0], $row)));
                    case 'endsWith':
                        return str_ends_with($baseStr, self::toStr(self::evalNode($argNodes[0], $row)));
                    case 'contains':
                        return str_contains($baseStr, self::toStr(self::evalNode($argNodes[0], $row)));
                    default:
                        return null;
                }
        }

        return null;
    }

    private function parseExpression(string $expression): array
    {
        $this->tokens = $this->tokenize($expression);
        $this->pos = 0;
        $node = $this->parseOr();
        return $node;
    }

    private function tokenize(string $text): array
    {
        $tokens = [];
        $len = strlen($text);
        $i = 0;

        while ($i < $len) {
            $ch = $text[$i];

            if (ctype_space($ch)) {
                $i++;
                continue;
            }

            if ($ch === '"') {
                $j = $i + 1;
                $value = '';
                while ($j < $len && $text[$j] !== '"') {
                    if ($text[$j] === '\\' && $j + 1 < $len) {
                        $next = $text[$j + 1];
                        $value .= match ($next) {
                            'n' => "\n",
                            't' => "\t",
                            default => $next,
                        };
                        $j += 2;
                        continue;
                    }
                    $value .= $text[$j];
                    $j++;
                }
                $tokens[] = ['str', $value];
                $i = $j + 1;
                continue;
            }

            if ($ch === '=' && ($text[$i + 1] ?? '') === '=') {
                $tokens[] = ['op', '==']; $i += 2; continue;
            }
            if ($ch === '!' && ($text[$i + 1] ?? '') === '=') {
                $tokens[] = ['op', '!=']; $i += 2; continue;
            }
            if ($ch === '&' && ($text[$i + 1] ?? '') === '&') {
                $tokens[] = ['op', '&&']; $i += 2; continue;
            }
            if ($ch === '|' && ($text[$i + 1] ?? '') === '|') {
                $tokens[] = ['op', '||']; $i += 2; continue;
            }
            if (in_array($ch, ['(', ')', ',', '.', '!', '+'], true)) {
                $tokens[] = ['punct', $ch]; $i++; continue;
            }

            if (preg_match('/[A-Za-z_]/u', $ch)) {
                $j = $i;
                while ($j < $len && preg_match('/[A-Za-z0-9_]/u', $text[$j])) {
                    $j++;
                }
                $word = substr($text, $i, $j - $i);
                if ($word === 'and') {
                    $tokens[] = ['op', '&&'];
                } elseif ($word === 'or') {
                    $tokens[] = ['op', '||'];
                } else {
                    $tokens[] = ['ident', $word];
                }
                $i = $j;
                continue;
            }

            // Unknown character: skip it rather than fail the whole expression
            $i++;
        }

        return $tokens;
    }

    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function next(): ?array
    {
        return $this->tokens[$this->pos++] ?? null;
    }

    private function isOp(string $op): bool
    {
        $t = $this->peek();
        return $t !== null && $t[0] === 'op' && $t[1] === $op;
    }

    private function isPunct(string $p): bool
    {
        $t = $this->peek();
        return $t !== null && $t[0] === 'punct' && $t[1] === $p;
    }

    private function parseOr(): array
    {
        $left = $this->parseAnd();
        while ($this->isOp('||')) {
            $this->next();
            $right = $this->parseAnd();
            $left = ['bin', '||', $left, $right];
        }
        return $left;
    }

    private function parseAnd(): array
    {
        $left = $this->parseEquality();
        while ($this->isOp('&&')) {
            $this->next();
            $right = $this->parseEquality();
            $left = ['bin', '&&', $left, $right];
        }
        return $left;
    }

    private function parseEquality(): array
    {
        $left = $this->parseConcat();
        while ($this->isOp('==') || $this->isOp('!=')) {
            $op = $this->next()[1];
            $right = $this->parseConcat();
            $left = ['bin', $op, $left, $right];
        }
        return $left;
    }

    private function parseConcat(): array
    {
        $left = $this->parseUnary();
        while ($this->isPunct('+')) {
            $this->next();
            $right = $this->parseUnary();
            $left = ['bin', '+', $left, $right];
        }
        return $left;
    }

    private function parseUnary(): array
    {
        if ($this->isPunct('!')) {
            $this->next();
            return ['not', $this->parseUnary()];
        }
        return $this->parsePostfix();
    }

    private function parsePostfix(): array
    {
        $node = $this->parsePrimary();

        while ($this->isPunct('.')) {
            $this->next();
            $name = $this->next();
            if ($name === null || $name[0] !== 'ident') {
                break;
            }
            if ($this->isPunct('(')) {
                $args = $this->parseArgs();
                $node = ['method', $node, $name[1], $args];
            } else {
                $node = ['field', $node, $name[1]];
            }
        }

        return $node;
    }

    private function parsePrimary(): array
    {
        $t = $this->next();

        if ($t === null) {
            return ['str', ''];
        }

        if ($t[0] === 'str') {
            return ['str', $t[1]];
        }

        if ($t[0] === 'ident') {
            if ($this->isPunct('(')) {
                $args = $this->parseArgs();
                return ['call', $t[1], $args];
            }
            return ['ident', $t[1]];
        }

        if ($t[0] === 'punct' && $t[1] === '(') {
            $expr = $this->parseOr();
            if ($this->isPunct(')')) {
                $this->next();
            }
            return $expr;
        }

        return ['str', ''];
    }

    private function parseArgs(): array
    {
        $args = [];
        $this->next(); // consume '('

        if ($this->isPunct(')')) {
            $this->next();
            return $args;
        }

        $args[] = $this->parseOr();
        while ($this->isPunct(',')) {
            $this->next();
            $args[] = $this->parseOr();
        }

        if ($this->isPunct(')')) {
            $this->next();
        }

        return $args;
    }
}
