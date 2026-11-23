import React, { useMemo, useState } from 'react';
import {
  Calculator as CalcIcon,
  Copy,
  Check,
  Minimize2,
  Network,
  RotateCcw,
  Sparkles,
  X,
} from 'lucide-react';

/* ---------------------------------------------------------------------------
 * Mathematical Tokenizer and Parser
 * ------------------------------------------------------------------------- */
function tokenise(expression) {
  const tokens = [];
  const cleaned = expression.replace(/\s+/g, '');
  let index = 0;
  while (index < cleaned.length) {
    const char = cleaned[index];
    if ('+-*/%()^'.includes(char)) {
      tokens.push({ type: 'op', value: char });
      index += 1;
    } else if (/[0-9.]/.test(char)) {
      let end = index;
      while (end < cleaned.length && /[0-9.]/.test(cleaned[end])) end += 1;
      const literal = cleaned.slice(index, end);
      if (Number.isNaN(Number(literal))) return null;
      tokens.push({ type: 'num', value: parseFloat(literal) });
      index = end;
    } else if (/[a-z]/.test(char)) {
      let end = index;
      while (end < cleaned.length && /[a-z0-9]/i.test(cleaned[end])) end += 1;
      const fnName = cleaned.slice(index, end).toLowerCase();
      tokens.push({ type: 'fn', value: fnName });
      index = end;
    } else {
      return null;
    }
  }
  return tokens;
}

function evaluateMath(expression) {
  try {
    const tokens = tokenise(expression);
    if (!tokens || tokens.length === 0) return null;

    // Minimal evaluator for basic math
    const sanitized = expression
      .replace(/sqrt\(/g, 'Math.sqrt(')
      .replace(/log\(/g, 'Math.log10(')
      .replace(/ln\(/g, 'Math.log(')
      .replace(/sin\(/g, 'Math.sin(')
      .replace(/cos\(/g, 'Math.cos(')
      .replace(/tan\(/g, 'Math.tan(')
      .replace(/pi/g, 'Math.PI')
      .replace(/e\b/g, 'Math.E')
      .replace(/\^/g, '**');

    if (/[^0-9.+\-*/%() Math.sqrtlogePIEsincta,]/.test(sanitized)) {
      return null;
    }

    // Safe mathematical evaluation
    const fn = new Function(`return (${sanitized});`);
    const res = fn();
    return typeof res === 'number' && !Number.isNaN(res) ? Math.round(res * 1e9) / 1e9 : null;
  } catch {
    return null;
  }
}

/* ---------------------------------------------------------------------------
 * IPv4 Subnet Calculator Helpers
 * ------------------------------------------------------------------------- */
function parseIp(ipStr) {
  const parts = ipStr.trim().split('.');
  if (parts.length !== 4) return null;
  const octets = [];
  for (const part of parts) {
    if (!/^\d+$/.test(part)) return null;
    const n = parseInt(part, 10);
    if (n < 0 || n > 255) return null;
    octets.push(n);
  }
  return octets;
}

function calculateSubnet(ipStr, cidrPrefix) {
  const octets = parseIp(ipStr);
  const prefix = parseInt(cidrPrefix, 10);
  if (!octets || Number.isNaN(prefix) || prefix < 0 || prefix > 32) return null;

  const ipInt = (octets[0] << 24) | (octets[1] << 16) | (octets[2] << 8) | octets[3];
  const maskInt = prefix === 0 ? 0 : (~0 << (32 - prefix)) >>> 0;
  const netInt = (ipInt & maskInt) >>> 0;
  const bcastInt = (netInt | ~maskInt) >>> 0;

  const intToIp = (val) => [
    (val >>> 24) & 255,
    (val >>> 16) & 255,
    (val >>> 8) & 255,
    val & 255,
  ].join('.');

  const totalHosts = Math.pow(2, 32 - prefix);
  const usableHosts = prefix >= 31 ? (prefix === 31 ? 2 : 1) : Math.max(0, totalHosts - 2);

  const firstHost = prefix >= 31 ? netInt : netInt + 1;
  const lastHost = prefix >= 31 ? bcastInt : Math.max(firstHost, bcastInt - 1);

  return {
    ip: octets.join('.'),
    prefix,
    subnetMask: intToIp(maskInt),
    wildcardMask: intToIp(~maskInt >>> 0),
    networkAddress: intToIp(netInt),
    broadcastAddress: intToIp(bcastInt),
    usableRange: `${intToIp(firstHost)} - ${intToIp(lastHost)}`,
    totalHosts,
    usableHosts,
  };
}

export default function AdvancedCalculator({ isOpen, onClose }) {
  const [activeTab, setActiveTab] = useState('math'); // 'math' | 'subnet' | 'bitwise'
  const [expression, setExpression] = useState('');
  const [copied, setCopied] = useState(false);

  // Subnet state
  const [ipInput, setIpInput] = useState('192.168.1.50');
  const [cidrInput, setCidrInput] = useState('24');

  // Bitwise state
  const [bitA, setBitA] = useState('192');
  const [bitB, setBitB] = useState('255');
  const [bitOp, setBitOp] = useState('AND');

  const mathResult = useMemo(() => (expression ? evaluateMath(expression) : null), [expression]);

  const subnetResult = useMemo(() => calculateSubnet(ipInput, cidrInput), [ipInput, cidrInput]);

  const bitwiseResult = useMemo(() => {
    const a = parseInt(bitA, 10);
    const b = parseInt(bitB, 10);
    if (Number.isNaN(a)) return null;

    let res = 0;
    switch (bitOp) {
      case 'AND':
        res = a & (Number.isNaN(b) ? 0 : b);
        break;
      case 'OR':
        res = a | (Number.isNaN(b) ? 0 : b);
        break;
      case 'XOR':
        res = a ^ (Number.isNaN(b) ? 0 : b);
        break;
      case 'NOT':
        res = ~a;
        break;
      case 'SHL':
        res = a << 1;
        break;
      case 'SHR':
        res = a >> 1;
        break;
      default:
        res = 0;
    }

    return {
      dec: res,
      bin: (res >>> 0).toString(2).padStart(8, '0'),
      hex: '0x' + (res >>> 0).toString(16).toUpperCase(),
    };
  }, [bitA, bitB, bitOp]);

  const handleAppend = (char) => setExpression((prev) => `${prev}${char}`);
  const handleClear = () => setExpression('');
  const handleBackspace = () => setExpression((prev) => prev.slice(0, -1));

  const copyToClipboard = (text) => {
    navigator.clipboard?.writeText(String(text));
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
      <div className="relative w-full max-w-lg rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-slate-800 bg-slate-950 px-5 py-3">
          <div className="flex items-center gap-2">
            <CalcIcon className="h-4 w-4 text-brand-300" />
            <h3 className="text-sm font-bold text-slate-100">Advanced Assessment Calculator</h3>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1 text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Tab Strip */}
        <div className="flex border-b border-slate-800 bg-slate-950/60 px-4 pt-2">
          <button
            type="button"
            onClick={() => setActiveTab('math')}
            className={`px-4 py-2 text-xs font-semibold border-b-2 transition ${
              activeTab === 'math'
                ? 'border-brand-500 text-brand-300'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            Scientific Math
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('subnet')}
            className={`px-4 py-2 text-xs font-semibold border-b-2 transition ${
              activeTab === 'subnet'
                ? 'border-brand-500 text-brand-300'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            IPv4 Subnetting
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('bitwise')}
            className={`px-4 py-2 text-xs font-semibold border-b-2 transition ${
              activeTab === 'bitwise'
                ? 'border-brand-500 text-brand-300'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            Bitwise / Radix
          </button>
        </div>

        {/* Tab Content */}
        <div className="p-5">
          {activeTab === 'math' && (
            <div className="space-y-4">
              {/* Display */}
              <div className="rounded-xl border border-slate-800 bg-slate-950 p-4 text-right">
                <p className="font-mono text-xs text-slate-400 min-h-4 overflow-x-auto">
                  {expression || '0'}
                </p>
                <p className="mt-1 font-mono text-2xl font-bold text-brand-300">
                  {mathResult !== null ? mathResult : expression ? '...' : '0'}
                </p>
              </div>

              {/* Keypad Grid */}
              <div className="grid grid-cols-5 gap-2">
                {/* Row 1: Scientific */}
                <button
                  type="button"
                  onClick={() => handleAppend('sqrt(')}
                  className="rounded-lg bg-slate-800 p-2 text-xs font-semibold text-slate-300 hover:bg-slate-700"
                >
                  √
                </button>
                <button
                  type="button"
                  onClick={() => handleAppend('^2')}
                  className="rounded-lg bg-slate-800 p-2 text-xs font-semibold text-slate-300 hover:bg-slate-700"
                >
                  x²
                </button>
                <button
                  type="button"
                  onClick={() => handleAppend('^')}
                  className="rounded-lg bg-slate-800 p-2 text-xs font-semibold text-slate-300 hover:bg-slate-700"
                >
                  xʸ
                </button>
                <button
                  type="button"
                  onClick={() => handleAppend('log(')}
                  className="rounded-lg bg-slate-800 p-2 text-xs font-semibold text-slate-300 hover:bg-slate-700"
                >
                  log
                </button>
                <button
                  type="button"
                  onClick={() => handleAppend('ln(')}
                  className="rounded-lg bg-slate-800 p-2 text-xs font-semibold text-slate-300 hover:bg-slate-700"
                >
                  ln
                </button>

                {/* Row 2 */}
                <button
                  type="button"
                  onClick={() => handleAppend('(')}
                  className="rounded-lg bg-slate-800 p-2 text-xs font-semibold text-slate-300 hover:bg-slate-700"
                >
                  (
                </button>
                <button
                  type="button"
                  onClick={() => handleAppend(')')}
                  className="rounded-lg bg-slate-800 p-2 text-xs font-semibold text-slate-300 hover:bg-slate-700"
                >
                  )
                </button>
                <button
                  type="button"
                  onClick={() => handleAppend('%')}
                  className="rounded-lg bg-slate-800 p-2 text-xs font-semibold text-slate-300 hover:bg-slate-700"
                >
                  %
                </button>
                <button
                  type="button"
                  onClick={handleBackspace}
                  className="rounded-lg bg-slate-800 p-2 text-xs font-semibold text-orange-300 hover:bg-slate-700"
                >
                  DEL
                </button>
                <button
                  type="button"
                  onClick={handleClear}
                  className="rounded-lg bg-red-500/20 p-2 text-xs font-semibold text-red-300 hover:bg-red-500/30"
                >
                  CLR
                </button>

                {/* Numbers & Operators */}
                {['7', '8', '9', '/', 'pi'].map((k) => (
                  <button
                    key={k}
                    type="button"
                    onClick={() => handleAppend(k)}
                    className={`rounded-lg p-2.5 text-sm font-semibold transition ${
                      '+-*/%'.includes(k)
                        ? 'bg-brand-500/15 text-brand-300 hover:bg-brand-500/25'
                        : 'bg-slate-800 text-slate-100 hover:bg-slate-700'
                    }`}
                  >
                    {k}
                  </button>
                ))}
                {['4', '5', '6', '*', 'sin('].map((k) => (
                  <button
                    key={k}
                    type="button"
                    onClick={() => handleAppend(k)}
                    className={`rounded-lg p-2.5 text-sm font-semibold transition ${
                      '+-*/%'.includes(k)
                        ? 'bg-brand-500/15 text-brand-300 hover:bg-brand-500/25'
                        : 'bg-slate-800 text-slate-100 hover:bg-slate-700'
                    }`}
                  >
                    {k}
                  </button>
                ))}
                {['1', '2', '3', '-', 'cos('].map((k) => (
                  <button
                    key={k}
                    type="button"
                    onClick={() => handleAppend(k)}
                    className={`rounded-lg p-2.5 text-sm font-semibold transition ${
                      '+-*/%'.includes(k)
                        ? 'bg-brand-500/15 text-brand-300 hover:bg-brand-500/25'
                        : 'bg-slate-800 text-slate-100 hover:bg-slate-700'
                    }`}
                  >
                    {k}
                  </button>
                ))}
                {['0', '.', '+', '=', 'tan('].map((k) => (
                  <button
                    key={k}
                    type="button"
                    onClick={() => {
                      if (k === '=') {
                        if (mathResult !== null) setExpression(String(mathResult));
                      } else {
                        handleAppend(k);
                      }
                    }}
                    className={`rounded-lg p-2.5 text-sm font-semibold transition ${
                      k === '='
                        ? 'bg-brand-500 text-slate-950 hover:bg-brand-400 font-bold'
                        : '+-*/%'.includes(k)
                        ? 'bg-brand-500/15 text-brand-300 hover:bg-brand-500/25'
                        : 'bg-slate-800 text-slate-100 hover:bg-slate-700'
                    }`}
                  >
                    {k}
                  </button>
                ))}
              </div>
            </div>
          )}

          {activeTab === 'subnet' && (
            <div className="space-y-4">
              <div className="grid grid-cols-3 gap-3">
                <div className="col-span-2">
                  <label className="text-xs font-semibold text-slate-400">IP Address</label>
                  <input
                    type="text"
                    value={ipInput}
                    onChange={(e) => setIpInput(e.target.value)}
                    placeholder="192.168.1.1"
                    className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm font-mono text-slate-100 focus:border-brand-500 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400">Prefix (/)</label>
                  <select
                    value={cidrInput}
                    onChange={(e) => setCidrInput(e.target.value)}
                    className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm font-mono text-slate-100 focus:border-brand-500 focus:outline-none"
                  >
                    {Array.from({ length: 33 }, (_, i) => (
                      <option key={i} value={i}>
                        /{i}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {subnetResult ? (
                <div className="rounded-xl border border-slate-800 bg-slate-950 p-4 space-y-2 text-xs font-mono">
                  <div className="flex justify-between border-b border-slate-850 pb-1.5">
                    <span className="text-slate-400">Network Address:</span>
                    <span className="font-bold text-brand-300">{subnetResult.networkAddress}</span>
                  </div>
                  <div className="flex justify-between border-b border-slate-850 pb-1.5">
                    <span className="text-slate-400">Subnet Mask:</span>
                    <span className="text-slate-200">{subnetResult.subnetMask}</span>
                  </div>
                  <div className="flex justify-between border-b border-slate-850 pb-1.5">
                    <span className="text-slate-400">Broadcast Address:</span>
                    <span className="text-slate-200">{subnetResult.broadcastAddress}</span>
                  </div>
                  <div className="flex justify-between border-b border-slate-850 pb-1.5">
                    <span className="text-slate-400">Usable Range:</span>
                    <span className="text-slate-200">{subnetResult.usableRange}</span>
                  </div>
                  <div className="flex justify-between border-b border-slate-850 pb-1.5">
                    <span className="text-slate-400">Usable Hosts:</span>
                    <span className="font-bold text-brand-300">
                      {subnetResult.usableHosts.toLocaleString()}
                    </span>
                  </div>
                  <div className="flex justify-between pt-1">
                    <span className="text-slate-400">Wildcard Mask:</span>
                    <span className="text-slate-400">{subnetResult.wildcardMask}</span>
                  </div>

                  <button
                    type="button"
                    onClick={() =>
                      copyToClipboard(
                        `Network: ${subnetResult.networkAddress}, Mask: ${subnetResult.subnetMask}, Usable: ${subnetResult.usableRange}`
                      )
                    }
                    className="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 py-1.5 text-xs text-slate-300 hover:bg-slate-700 transition"
                  >
                    {copied ? <Check className="h-3.5 w-3.5 text-brand-300" /> : <Copy className="h-3.5 w-3.5" />}
                    <span>{copied ? 'Copied' : 'Copy Subnet Details'}</span>
                  </button>
                </div>
              ) : (
                <p className="rounded-lg border border-dashed border-red-800/40 p-4 text-center text-xs text-red-400">
                  Invalid IPv4 address format. E.g. 192.168.1.1
                </p>
              )}
            </div>
          )}

          {activeTab === 'bitwise' && (
            <div className="space-y-4">
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="text-xs font-semibold text-slate-400">Operand A (Dec)</label>
                  <input
                    type="number"
                    value={bitA}
                    onChange={(e) => setBitA(e.target.value)}
                    className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm font-mono text-slate-100"
                  />
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400">Operation</label>
                  <select
                    value={bitOp}
                    onChange={(e) => setBitOp(e.target.value)}
                    className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm font-mono text-slate-100"
                  >
                    <option value="AND">AND (&)</option>
                    <option value="OR">OR (|)</option>
                    <option value="XOR">XOR (^)</option>
                    <option value="NOT">NOT (~)</option>
                    <option value="SHL">Shift Left (&lt;&lt;)</option>
                    <option value="SHR">Shift Right (&gt;&gt;)</option>
                  </select>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400">Operand B (Dec)</label>
                  <input
                    type="number"
                    value={bitB}
                    disabled={bitOp === 'NOT' || bitOp === 'SHL' || bitOp === 'SHR'}
                    onChange={(e) => setBitB(e.target.value)}
                    className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm font-mono text-slate-100 disabled:opacity-30"
                  />
                </div>
              </div>

              {bitwiseResult && (
                <div className="rounded-xl border border-slate-800 bg-slate-950 p-4 space-y-2 text-xs font-mono">
                  <div className="flex justify-between border-b border-slate-850 pb-1.5">
                    <span className="text-slate-400">Decimal:</span>
                    <span className="font-bold text-brand-300">{bitwiseResult.dec}</span>
                  </div>
                  <div className="flex justify-between border-b border-slate-850 pb-1.5">
                    <span className="text-slate-400">Hexadecimal:</span>
                    <span className="text-slate-200">{bitwiseResult.hex}</span>
                  </div>
                  <div className="flex justify-between pt-1">
                    <span className="text-slate-400">Binary (32-bit):</span>
                    <span className="text-slate-300">{bitwiseResult.bin}</span>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
