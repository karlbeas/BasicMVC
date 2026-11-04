<?php

declare(strict_types=1);

namespace App\Services\Collect;

/**
 * Safe evaluator for the XLSForm expression subset used by relevant, constraint
 * and calculation (never eval). Mirrored in public/assets/js/f2s-expr.js; both
 * are checked against tests/fixtures/expressions.json.
 *
 * Supported: ${name}, "." (current value), numbers, 'strings', = != < <= > >=,
 * + - * div mod, and or, parentheses, selected(), count-selected(),
 * string-length(), not(), if(), coalesce(), true(), false(), number(), concat().
 * Answers are strings; select_multiple answers are space-separated codes.
 * Like XPath, comparing an empty answer with a number is false (NaN).
 */
final class ExpressionEvaluator
{
    private const FUNCTIONS = ['selected', 'count-selected', 'string-length', 'not', 'if', 'coalesce', 'true', 'false', 'number', 'concat'];

    /** @var list<array{t: string, v: string}> */
    private array $tokens = [];
    private int $pos = 0;
    /** @var array<string, string> */
    private array $values = [];
    private string $current = '';

    /**
     * @param array<string, string> $values answers by question name
     *
     * @throws UnsupportedExpressionException
     */
    public function evaluate(string $expression, array $values, string $current = ''): mixed
    {
        $this->tokens = self::tokenize($expression);
        $this->pos = 0;
        $this->values = $values;
        $this->current = $current;
        $result = $this->parseOr();
        if ($this->pos < count($this->tokens)) {
            throw new UnsupportedExpressionException('Unexpected token ' . $this->tokens[$this->pos]['v']);
        }

        return $result;
    }

    /** @param array<string, string> $values */
    public function isTrue(string $expression, array $values, string $current = ''): bool
    {
        return self::truthy($this->evaluate($expression, $values, $current));
    }

    public static function truthy(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_float($v) || is_int($v)) {
            return $v != 0 && !is_nan((float) $v);
        }

        return (string) $v !== '';
    }

    /** @return list<array{t: string, v: string}> */
    public static function tokenize(string $s): array
    {
        $tokens = [];
        $i = 0;
        $n = strlen($s);
        while ($i < $n) {
            $c = $s[$i];
            if (ctype_space($c)) {
                ++$i;
                continue;
            }
            if ($c === '$' && ($s[$i + 1] ?? '') === '{') {
                $end = strpos($s, '}', $i);
                if ($end === false) {
                    throw new UnsupportedExpressionException('Unclosed ${');
                }
                $tokens[] = ['t' => 'ref', 'v' => substr($s, $i + 2, $end - $i - 2)];
                $i = $end + 1;
                continue;
            }
            if ($c === "'" || $c === '"') {
                $end = strpos($s, $c, $i + 1);
                if ($end === false) {
                    throw new UnsupportedExpressionException('Unclosed string');
                }
                $tokens[] = ['t' => 'str', 'v' => substr($s, $i + 1, $end - $i - 1)];
                $i = $end + 1;
                continue;
            }
            if (ctype_digit($c) || ($c === '.' && ctype_digit($s[$i + 1] ?? ''))) {
                preg_match('/\G\d*\.?\d+(?:[eE][-+]?\d+)?|\G\d+\.?/', $s, $m, 0, $i);
                $tokens[] = ['t' => 'num', 'v' => $m[0]];
                $i += strlen($m[0]);
                continue;
            }
            if ($c === '.') {
                $tokens[] = ['t' => 'self', 'v' => '.'];
                ++$i;
                continue;
            }
            foreach (['!=', '<=', '>=', '=', '<', '>', '+', '-', '*', '(', ')', ','] as $op) {
                if (substr($s, $i, strlen($op)) === $op) {
                    $tokens[] = ['t' => 'op', 'v' => $op];
                    $i += strlen($op);
                    continue 2;
                }
            }
            if (preg_match('/\G[A-Za-z_][A-Za-z0-9_-]*/', $s, $m, 0, $i)) {
                $tokens[] = ['t' => 'word', 'v' => $m[0]];
                $i += strlen($m[0]);
                continue;
            }
            throw new UnsupportedExpressionException('Unexpected character ' . $c);
        }

        return $tokens;
    }

    private function peek(string $value): bool
    {
        $t = $this->tokens[$this->pos] ?? null;

        return $t !== null && ($t['t'] === 'op' || $t['t'] === 'word') && $t['v'] === $value;
    }

    private function expect(string $value): void
    {
        if (!$this->peek($value)) {
            throw new UnsupportedExpressionException('Expected ' . $value);
        }
        ++$this->pos;
    }

    private function parseOr(): mixed
    {
        $left = $this->parseAnd();
        while ($this->peek('or')) {
            ++$this->pos;
            $right = $this->parseAnd();
            $left = self::truthy($left) || self::truthy($right);
        }

        return $left;
    }

    private function parseAnd(): mixed
    {
        $left = $this->parseComparison();
        while ($this->peek('and')) {
            ++$this->pos;
            $right = $this->parseComparison();
            $left = self::truthy($left) && self::truthy($right);
        }

        return $left;
    }

    private function parseComparison(): mixed
    {
        $left = $this->parseAdditive();
        foreach (['!=', '<=', '>=', '=', '<', '>'] as $op) {
            if ($this->peek($op)) {
                ++$this->pos;

                return self::compare($left, $op, $this->parseAdditive());
            }
        }

        return $left;
    }

    private function parseAdditive(): mixed
    {
        $left = $this->parseMultiplicative();
        while ($this->peek('+') || $this->peek('-')) {
            $op = $this->tokens[$this->pos++]['v'];
            $right = $this->parseMultiplicative();
            $left = $op === '+' ? self::num($left) + self::num($right) : self::num($left) - self::num($right);
        }

        return $left;
    }

    private function parseMultiplicative(): mixed
    {
        $left = $this->parseUnary();
        while ($this->peek('*') || $this->peek('div') || $this->peek('mod')) {
            $op = $this->tokens[$this->pos++]['v'];
            $a = self::num($left);
            $b = self::num($this->parseUnary());
            $left = match ($op) {
                '*' => $a * $b,
                'div' => $b == 0 ? NAN : $a / $b,
                default => $b == 0 ? NAN : fmod($a, $b),
            };
        }

        return $left;
    }

    private function parseUnary(): mixed
    {
        if ($this->peek('-')) {
            ++$this->pos;

            return -self::num($this->parseUnary());
        }

        return $this->parsePrimary();
    }

    private function parsePrimary(): mixed
    {
        $t = $this->tokens[$this->pos] ?? null;
        if ($t === null) {
            throw new UnsupportedExpressionException('Unexpected end of expression');
        }
        ++$this->pos;
        switch ($t['t']) {
            case 'num':
                return (float) $t['v'];
            case 'str':
                return $t['v'];
            case 'ref':
                return $this->values[$t['v']] ?? '';
            case 'self':
                return $this->current;
            case 'op':
                if ($t['v'] === '(') {
                    $v = $this->parseOr();
                    $this->expect(')');

                    return $v;
                }
                break;
            case 'word':
                if ($this->peek('(')) {
                    return $this->call($t['v']);
                }
                break;
        }

        throw new UnsupportedExpressionException('Unexpected token ' . $t['v']);
    }

    private function call(string $name): mixed
    {
        if (!in_array($name, self::FUNCTIONS, true)) {
            throw new UnsupportedExpressionException('Unsupported function ' . $name . '()');
        }
        $this->expect('(');
        $args = [];
        if (!$this->peek(')')) {
            $args[] = $this->parseOr();
            while ($this->peek(',')) {
                ++$this->pos;
                $args[] = $this->parseOr();
            }
        }
        $this->expect(')');
        $str = static fn(int $i): string => self::str($args[$i] ?? '');

        return match ($name) {
            'selected' => in_array($str(1), self::split($str(0)), true),
            'count-selected' => (float) count(self::split($str(0))),
            'string-length' => (float) mb_strlen($str(0)),
            'not' => !self::truthy($args[0] ?? false),
            'if' => self::truthy($args[0] ?? false) ? ($args[1] ?? '') : ($args[2] ?? ''),
            'coalesce' => $str(0) !== '' ? ($args[0] ?? '') : ($args[1] ?? ''),
            'true' => true,
            'false' => false,
            'number' => self::num($args[0] ?? ''),
            'concat' => implode('', array_map([self::class, 'str'], $args)),
        };
    }

    /** @return list<string> */
    private static function split(string $s): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim($s)) ?: [], static fn(string $x): bool => $x !== ''));
    }

    private static function num(mixed $v): float
    {
        if (is_bool($v)) {
            return $v ? 1.0 : 0.0;
        }
        if (is_float($v) || is_int($v)) {
            return (float) $v;
        }
        $s = trim((string) $v);

        return is_numeric($s) ? (float) $s : NAN;
    }

    private static function str(mixed $v): string
    {
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_float($v)) {
            if (is_nan($v)) {
                return 'NaN';
            }

            return floor($v) === $v && abs($v) < 1e15 ? (string) (int) $v : (string) $v;
        }

        return is_scalar($v) ? (string) $v : '';
    }

    private static function compare(mixed $a, string $op, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            $x = self::truthy($a);
            $y = self::truthy($b);

            return match ($op) {
                '=' => $x === $y,
                '!=' => $x !== $y,
                default => self::numericCompare((float) $x, $op, (float) $y),
            };
        }
        $numeric = is_float($a) || is_float($b) || in_array($op, ['<', '<=', '>', '>='], true);
        if ($numeric) {
            return self::numericCompare(self::num($a), $op, self::num($b));
        }

        return $op === '=' ? self::str($a) === self::str($b) : ($op === '!=' ? self::str($a) !== self::str($b) : false);
    }

    private static function numericCompare(float $x, string $op, float $y): bool
    {
        if (is_nan($x) || is_nan($y)) {
            return $op === '!=';
        }

        return match ($op) {
            '=' => $x == $y,
            '!=' => $x != $y,
            '<' => $x < $y,
            '<=' => $x <= $y,
            '>' => $x > $y,
            default => $x >= $y,
        };
    }
}
