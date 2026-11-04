/* F2S Pro — XLSForm expression subset evaluator (browser twin of
 * app/Services/Collect/ExpressionEvaluator.php; both are checked against
 * tests/fixtures/expressions.json). Never uses eval(). */
(function (root) {
    'use strict';

    var FUNCTIONS = ['selected', 'count-selected', 'string-length', 'not', 'if', 'coalesce', 'true', 'false', 'number', 'concat'];
    var OPS = ['!=', '<=', '>=', '=', '<', '>', '+', '-', '*', '(', ')', ','];

    function Unsupported(msg) { this.message = msg; this.name = 'UnsupportedExpression'; }

    function tokenize(s) {
        var tokens = [], i = 0, n = s.length, m;
        while (i < n) {
            var c = s.charAt(i);
            if (/\s/.test(c)) { i++; continue; }
            if (c === '$' && s.charAt(i + 1) === '{') {
                var end = s.indexOf('}', i);
                if (end < 0) { throw new Unsupported('Unclosed ${'); }
                tokens.push({ t: 'ref', v: s.substring(i + 2, end) }); i = end + 1; continue;
            }
            if (c === "'" || c === '"') {
                var close = s.indexOf(c, i + 1);
                if (close < 0) { throw new Unsupported('Unclosed string'); }
                tokens.push({ t: 'str', v: s.substring(i + 1, close) }); i = close + 1; continue;
            }
            if (/[0-9]/.test(c) || (c === '.' && /[0-9]/.test(s.charAt(i + 1)))) {
                m = /^(\d*\.?\d+(?:[eE][-+]?\d+)?|\d+\.?)/.exec(s.substring(i));
                tokens.push({ t: 'num', v: m[0] }); i += m[0].length; continue;
            }
            if (c === '.') { tokens.push({ t: 'self', v: '.' }); i++; continue; }
            var matched = false;
            for (var k = 0; k < OPS.length; k++) {
                if (s.substr(i, OPS[k].length) === OPS[k]) { tokens.push({ t: 'op', v: OPS[k] }); i += OPS[k].length; matched = true; break; }
            }
            if (matched) { continue; }
            m = /^[A-Za-z_][A-Za-z0-9_-]*/.exec(s.substring(i));
            if (m) { tokens.push({ t: 'word', v: m[0] }); i += m[0].length; continue; }
            throw new Unsupported('Unexpected character ' + c);
        }
        return tokens;
    }

    function truthy(v) {
        if (typeof v === 'boolean') { return v; }
        if (typeof v === 'number') { return v !== 0 && !isNaN(v); }
        return String(v) !== '';
    }
    function num(v) {
        if (typeof v === 'boolean') { return v ? 1 : 0; }
        if (typeof v === 'number') { return v; }
        var s = String(v).trim();
        return s !== '' && !isNaN(Number(s)) ? Number(s) : NaN;
    }
    function str(v) {
        if (typeof v === 'boolean') { return v ? 'true' : 'false'; }
        if (typeof v === 'number') { return isNaN(v) ? 'NaN' : String(v); }
        return v == null ? '' : String(v);
    }
    function split(s) { return String(s).trim().split(/\s+/).filter(function (x) { return x !== ''; }); }
    function numericCompare(x, op, y) {
        if (isNaN(x) || isNaN(y)) { return op === '!='; }
        switch (op) {
            case '=': return x === y; case '!=': return x !== y; case '<': return x < y;
            case '<=': return x <= y; case '>': return x > y; default: return x >= y;
        }
    }
    function compare(a, op, b) {
        if (typeof a === 'boolean' || typeof b === 'boolean') {
            var x = truthy(a), y = truthy(b);
            if (op === '=') { return x === y; }
            if (op === '!=') { return x !== y; }
            return numericCompare(x ? 1 : 0, op, y ? 1 : 0);
        }
        if (typeof a === 'number' || typeof b === 'number' || ['<', '<=', '>', '>='].indexOf(op) >= 0) {
            return numericCompare(num(a), op, num(b));
        }
        if (op === '=') { return str(a) === str(b); }
        if (op === '!=') { return str(a) !== str(b); }
        return false;
    }

    function evaluate(expression, values, current) {
        var tokens = tokenize(expression), pos = 0;
        values = values || {}; current = current == null ? '' : String(current);

        function peek(v) { var t = tokens[pos]; return !!t && (t.t === 'op' || t.t === 'word') && t.v === v; }
        function expect(v) { if (!peek(v)) { throw new Unsupported('Expected ' + v); } pos++; }
        function parseOr() { var l = parseAnd(); while (peek('or')) { pos++; var r = parseAnd(); l = truthy(l) || truthy(r); } return l; }
        function parseAnd() { var l = parseCmp(); while (peek('and')) { pos++; var r = parseCmp(); l = truthy(l) && truthy(r); } return l; }
        function parseCmp() {
            var l = parseAdd(), ops = ['!=', '<=', '>=', '=', '<', '>'];
            for (var i = 0; i < ops.length; i++) { if (peek(ops[i])) { pos++; return compare(l, ops[i], parseAdd()); } }
            return l;
        }
        function parseAdd() {
            var l = parseMul();
            while (peek('+') || peek('-')) { var op = tokens[pos++].v, r = parseMul(); l = op === '+' ? num(l) + num(r) : num(l) - num(r); }
            return l;
        }
        function parseMul() {
            var l = parseUnary();
            while (peek('*') || peek('div') || peek('mod')) {
                var op = tokens[pos++].v, a = num(l), b = num(parseUnary());
                l = op === '*' ? a * b : (b === 0 ? NaN : (op === 'div' ? a / b : a % b));
            }
            return l;
        }
        function parseUnary() { if (peek('-')) { pos++; return -num(parseUnary()); } return parsePrimary(); }
        function parsePrimary() {
            var t = tokens[pos];
            if (!t) { throw new Unsupported('Unexpected end of expression'); }
            pos++;
            if (t.t === 'num') { return Number(t.v); }
            if (t.t === 'str') { return t.v; }
            if (t.t === 'ref') { return Object.prototype.hasOwnProperty.call(values, t.v) ? String(values[t.v]) : ''; }
            if (t.t === 'self') { return current; }
            if (t.t === 'op' && t.v === '(') { var v = parseOr(); expect(')'); return v; }
            if (t.t === 'word' && peek('(')) { return call(t.v); }
            throw new Unsupported('Unexpected token ' + t.v);
        }
        function call(name) {
            if (FUNCTIONS.indexOf(name) < 0) { throw new Unsupported('Unsupported function ' + name + '()'); }
            expect('(');
            var args = [];
            if (!peek(')')) { args.push(parseOr()); while (peek(',')) { pos++; args.push(parseOr()); } }
            expect(')');
            var s = function (i) { return str(args[i] === undefined ? '' : args[i]); };
            switch (name) {
                case 'selected': return split(s(0)).indexOf(s(1)) >= 0;
                case 'count-selected': return split(s(0)).length;
                case 'string-length': return Array.from(s(0)).length;
                case 'not': return !truthy(args[0] === undefined ? false : args[0]);
                case 'if': return truthy(args[0] === undefined ? false : args[0]) ? (args[1] === undefined ? '' : args[1]) : (args[2] === undefined ? '' : args[2]);
                case 'coalesce': return s(0) !== '' ? args[0] : (args[1] === undefined ? '' : args[1]);
                case 'true': return true;
                case 'false': return false;
                case 'number': return num(args[0] === undefined ? '' : args[0]);
                default: return args.map(str).join('');
            }
        }

        var result = parseOr();
        if (pos < tokens.length) { throw new Unsupported('Unexpected token ' + tokens[pos].v); }
        return result;
    }

    var api = { evaluate: evaluate, truthy: truthy, isTrue: function (e, v, c) { return truthy(evaluate(e, v, c)); } };
    if (typeof module !== 'undefined' && module.exports) { module.exports = api; } else { root.F2SExpr = api; }
})(this);
