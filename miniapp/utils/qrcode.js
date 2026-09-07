/**
 * utils/qrcode.js —— 自包含 QR Code 编码器（无第三方依赖）
 * 支持：byte 模式、纠错等级 M、自动选择最小版本（1~10）
 * 对外暴露：QRCode.encode(text) -> { size, modules: boolean[][] }
 *   size = 模块边长（如 29 表示 29x29）
 *   modules[y][x] = true 表示黑色模块
 *
 * 算法参考 ISO/IEC 18004；为控制体积仅实现 byte 模式 + M 级。
 */

// ===== GF(256) 算术（本原多项式 0x11d）=====
const EXP = new Int32Array(256);
const LOG = new Int32Array(256);
(function initGF() {
  let x = 1;
  for (let i = 0; i < 255; i++) {
    EXP[i] = x;
    LOG[x] = i;
    x <<= 1;
    if (x & 0x100) x ^= 0x11d;
  }
  EXP[255] = EXP[0];
})();
const gfMul = (a, b) => (a === 0 || b === 0 ? 0 : EXP[(LOG[a] + LOG[b]) % 255]);

// ===== 生成 Reed-Solomon 纠错码多项式 =====
function rsGeneratorPoly(degree) {
  let poly = [1];
  for (let i = 0; i < degree; i++) {
    const next = new Array(poly.length + 1).fill(0);
    for (let j = 0; j < poly.length; j++) {
      next[j] ^= gfMul(poly[j], EXP[i]);
      next[j + 1] ^= poly[j];
    }
    poly = next;
  }
  return poly;
}

function rsEncode(data, ecLen) {
  const gen = rsGeneratorPoly(ecLen);
  const res = new Array(ecLen).fill(0);
  for (const b of data) {
    const factor = b ^ res.shift();
    res.push(0);
    if (factor !== 0) {
      for (let i = 0; i < ecLen; i++) {
        res[i] ^= gfMul(gen[i + 1], factor);
      }
    }
  }
  return res;
}

// ===== 各版本的容量表（ECC M，byte 模式可容纳字节数）=====
// 索引 = 版本号（1~10）
const BYTE_CAPACITY_M = [0, 14, 26, 42, 62, 84, 106, 122, 152, 180, 187];

// 各版本 ECC 码块配置（M 级）：数组，每项 { data, ec, count }
// 部分版本存在两种数据长度的块（短块 + 长块）
const ECC_CONFIG_M = {
  1:  [{ data: 16, ec: 10, count: 1 }],
  2:  [{ data: 28, ec: 16, count: 1 }],
  3:  [{ data: 44, ec: 26, count: 1 }],
  4:  [{ data: 32, ec: 18, count: 2 }],
  5:  [{ data: 43, ec: 24, count: 2 }],
  6:  [{ data: 27, ec: 16, count: 4 }],
  7:  [{ data: 31, ec: 18, count: 4 }],
  8:  [{ data: 38, ec: 22, count: 2 }, { data: 39, ec: 22, count: 2 }],
  9:  [{ data: 36, ec: 22, count: 3 }, { data: 37, ec: 22, count: 2 }],
  10: [{ data: 31, ec: 26, count: 2 }, { data: 32, ec: 26, count: 4 }],
};

function chooseVersion(byteLen) {
  for (let v = 1; v <= 10; v++) {
    if (byteLen <= BYTE_CAPACITY_M[v]) return v;
  }
  throw new Error('数据过长，无法编码为 QR 码（最大 213 字节）');
}

// ===== 位流写入工具 =====
class BitBuffer {
  constructor() { this.bits = []; }
  put(value, len) {
    for (let i = len - 1; i >= 0; i--) {
      this.bits.push((value >> i) & 1);
    }
  }
  get length() { return this.bits.length; }
  toBytes() {
    const out = [];
    for (let i = 0; i < this.bits.length; i += 8) {
      let b = 0;
      for (let j = 0; j < 8; j++) {
        b = (b << 1) | (this.bits[i + j] || 0);
      }
      out.push(b);
    }
    return out;
  }
}

// ===== 编码数据码字 =====
function encodeData(text, version) {
  const buf = new BitBuffer();
  // 模式指示符：byte = 0100
  buf.put(0b0100, 4);
  // 字符数指示符（版本 1-9: 8 bits；10+: 16 bits）
  const len = text.length;
  buf.put(len, version <= 9 ? 8 : 16);
  // 数据字节（UTF-8）
  for (let i = 0; i < len; i++) {
    buf.put(text.charCodeAt(i), 8);
  }

  const groups = ECC_CONFIG_M[version];
  // 计算总数据码字数
  let totalData = 0;
  const maxDataPerBlock = Math.max(...groups.map(g => g.data));
  for (const g of groups) totalData += g.data * g.count;

  const totalBits = totalData * 8;

  // 终止符（最多 4 个 0）
  const termLen = Math.min(4, totalBits - buf.length);
  buf.put(0, termLen);
  // 补齐到 8 的倍数
  while (buf.length % 8 !== 0) buf.put(0, 1);
  // 填充字节 0xEC / 0x11 交替
  let pad = 0xec;
  const bytes = buf.toBytes();
  while (bytes.length < totalData) {
    bytes.push(pad);
    pad = pad === 0xec ? 0x11 : 0xec;
  }

  // 分块（短块 + 长块）+ RS 编码
  const blocks = []; // { data, ec, size }
  let offset = 0;
  for (const g of groups) {
    for (let i = 0; i < g.count; i++) {
      const block = bytes.slice(offset, offset + g.data);
      offset += g.data;
      const ec = rsEncode(block, g.ec);
      blocks.push({ data: block, ec, size: g.data, ecLen: g.ec });
    }
  }

  // 交织数据（按最大块长逐列取）
  const result = [];
  for (let i = 0; i < maxDataPerBlock; i++) {
    for (const blk of blocks) {
      if (i < blk.data.length) result.push(blk.data[i]);
    }
  }
  // 交织 ECC（所有块 ecLen 相同）
  const ecLen = blocks[0].ecLen;
  for (let i = 0; i < ecLen; i++) {
    for (const blk of blocks) result.push(blk.ec[i]);
  }
  return result;
}

// ===== 构建矩阵 =====
const ALIGN_POS = {
  1: [], 2: [6, 18], 3: [6, 22], 4: [6, 26], 5: [6, 30],
  6: [6, 34], 7: [6, 22, 38], 8: [6, 24, 42], 9: [6, 26, 46], 10: [6, 28, 50],
};

function buildMatrix(version, codewords) {
  const size = 17 + version * 4;
  const m = Array.from({ length: size }, () => new Array(size).fill(null));
  const reserved = Array.from({ length: size }, () => new Array(size).fill(false));

  const set = (x, y, v) => { m[y][x] = v; reserved[y][x] = true; };

  // Finder patterns (3 corners)
  const drawFinder = (ox, oy) => {
    for (let dy = -1; dy <= 7; dy++) {
      for (let dx = -1; dx <= 7; dx++) {
        const x = ox + dx, y = oy + dy;
        if (x < 0 || y < 0 || x >= size || y >= size) continue;
        const inOuter = (dx >= 0 && dx <= 6 && (dy === 0 || dy === 6)) ||
                        (dy >= 0 && dy <= 6 && (dx === 0 || dx === 6));
        const inInner = dx >= 2 && dx <= 4 && dy >= 2 && dy <= 4;
        set(x, y, inOuter || inInner);
      }
    }
  };
  drawFinder(0, 0);
  drawFinder(size - 7, 0);
  drawFinder(0, size - 7);

  // Timing patterns
  for (let i = 8; i < size - 8; i++) {
    set(i, 6, i % 2 === 0);
    set(6, i, i % 2 === 0);
  }

  // Dark module
  set(8, size - 8, true);

  // Alignment patterns
  const ap = ALIGN_POS[version];
  for (const ay of ap) {
    for (const ax of ap) {
      // 跳过与 finder 重叠的
      if ((ax === 6 && ay === 6) || (ax === 6 && ay === size - 7) || (ax === size - 7 && ay === 6)) continue;
      for (let dy = -2; dy <= 2; dy++) {
        for (let dx = -2; dx <= 2; dx++) {
          const dist = Math.max(Math.abs(dx), Math.abs(dy));
          set(ax + dx, ay + dy, dist !== 1);
        }
      }
    }
  }

  // Version info (version >= 7 only; versions 1-6 无 version info)
  if (version >= 7) {
    const viBits = computeVersionInfo(version);
    for (let i = 0; i < 18; i++) {
      const bit = (viBits >> i) & 1;
      const a = Math.floor(i / 3);
      const b = (i % 3) + size - 8 - 3;
      // 只放一个位置即可（简化：右上）
      set(size - 11 + (i % 3), Math.floor(i / 3), !!bit);
      set(Math.floor(i / 3), size - 11 + (i % 3), !!bit);
    }
  }

  // 数据填充（蛇形）
  let bitIdx = 0;
  let upward = true;
  for (let col = size - 1; col > 0; col -= 2) {
    if (col === 6) col--; // 跳过 timing 列
    for (let i = 0; i < size; i++) {
      const y = upward ? size - 1 - i : i;
      for (let c = 0; c < 2; c++) {
        const x = col - c;
        if (reserved[y][x]) continue;
        if (bitIdx < codewords.length * 8) {
          const byte = codewords[bitIdx >> 3];
          const bit = (byte >> (7 - (bitIdx & 7))) & 1;
          m[y][x] = !!bit;
        } else {
          m[y][x] = false;
        }
        bitIdx++;
      }
    }
    upward = !upward;
  }

  return { m, reserved, size };
}

// 版本信息 BCH 编码 (18 位)
function computeVersionInfo(version) {
  let data = version << 12;
  const poly = 0x1f25;
  for (let i = 17; i >= 12; i--) {
    if ((data >> i) & 1) data ^= poly << (i - 12);
  }
  return (version << 12) | data;
}

// ===== 掩码 =====
const MASK_FNS = [
  (x, y) => (x + y) % 2 === 0,
  (x, y) => y % 2 === 0,
  (x, y) => x % 3 === 0,
  (x, y) => (x + y) % 3 === 0,
  (x, y) => (Math.floor(y / 2) + Math.floor(x / 3)) % 2 === 0,
  (x, y) => ((x * y) % 2) + ((x * y) % 3) === 0,
  (x, y) => (((x * y) % 2) + ((x * y) % 3)) % 2 === 0,
  (x, y) => (((x + y) % 2) + ((x * y) % 3)) % 2 === 0,
];

function applyMask(m, reserved, size, maskFn) {
  const out = Array.from({ length: size }, (_, y) =>
    Array.from({ length: size }, (_, x) => {
      if (reserved[y][x]) return m[y][x];
      return m[y][x] !== maskFn(x, y);
    })
  );
  return out;
}

// 格式信息（含掩码位 + ECC 等级 M=0b00）
function computeFormatInfo(mask) {
  const data = (0b00 << 3) | mask; // ECC level M
  let d = data << 10;
  const poly = 0x537;
  for (let i = 14; i >= 10; i--) {
    if ((d >> i) & 1) d ^= poly << (i - 10);
  }
  const bits = ((data << 10) | d) ^ 0x5412;
  return bits;
}

function drawFormatInfo(matrix, size, mask) {
  const bits = computeFormatInfo(mask);
  // 左上
  for (let i = 0; i < 15; i++) {
    const bit = (bits >> i) & 1;
    let x, y;
    if (i < 6) { x = i; y = 8; }
    else if (i === 6) { x = 7; y = 8; }
    else if (i === 7) { x = 8; y = 8; }
    else if (i === 8) { x = 8; y = 7; }
    else { x = 8; y = 14 - i; }
    matrix[y][x] = !!bit;
  }
  // 右下 + 右上/左下
  for (let i = 0; i < 15; i++) {
    const bit = (bits >> i) & 1;
    let x, y;
    if (i < 8) { x = size - 1 - i; y = 8; }
    else { x = 8; y = size - 15 + i; }
    matrix[y][x] = !!bit;
  }
}

// 惩罚评分（简化版：统计 4 个规则）
function penaltyScore(matrix, size) {
  let score = 0;
  // 规则 1：同行/列连续同色 ≥5
  for (let y = 0; y < size; y++) {
    let run = 1;
    for (let x = 1; x < size; x++) {
      if (matrix[y][x] === matrix[y][x - 1]) { run++; if (run === 5) score += 3; else if (run > 5) score++; }
      else run = 1;
    }
  }
  for (let x = 0; x < size; x++) {
    let run = 1;
    for (let y = 1; y < size; y++) {
      if (matrix[y][x] === matrix[y - 1][x]) { run++; if (run === 5) score += 3; else if (run > 5) score++; }
      else run = 1;
    }
  }
  // 规则 2：2x2 同色块
  for (let y = 0; y < size - 1; y++) {
    for (let x = 0; x < size - 1; x++) {
      const v = matrix[y][x];
      if (matrix[y][x + 1] === v && matrix[y + 1][x] === v && matrix[y + 1][x + 1] === v) score += 3;
    }
  }
  // 规则 3：类 finder 图案 (1:1:3:1:1 比例，前后有 4 白)
  const patDark = [true, false, true, true, true, false, true, false, false, false, false];
  const checkPat = (row) => {
    for (let i = 0; i <= row.length - 11; i++) {
      let ok = true;
      for (let j = 0; j < 11; j++) if (row[i + j] !== patDark[j]) { ok = false; break; }
      if (ok) score += 40;
    }
  };
  for (let y = 0; y < size; y++) checkPat(matrix[y]);
  for (let x = 0; x < size; x++) {
    const col = [];
    for (let y = 0; y < size; y++) col.push(matrix[y][x]);
    checkPat(col);
  }
  // 规则 4：黑白比例
  let dark = 0;
  for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) if (matrix[y][x]) dark++;
  const ratio = dark / (size * size);
  const k = Math.floor(Math.abs(ratio - 0.5) * 20);
  score += k * 10;
  return score;
}

// ===== 对外 API =====
const QRCode = {
  /**
   * 编码文本为 QR 矩阵
   * @param {string} text
   * @returns {{size:number, modules:boolean[][]}}
   */
  encode(text) {
    // 转为 UTF-8 字节
    const bytes = unescape(encodeURIComponent(text)).split('').map(c => c.charCodeAt(0));
    const version = chooseVersion(bytes.length);
    const codewords = encodeData(bytes, version);

    const { m, reserved, size } = buildMatrix(version, codewords);

    // 尝试 8 种掩码，选惩罚分最低的
    let bestMatrix = null;
    let bestScore = Infinity;
    let bestMask = 0;
    for (let mask = 0; mask < 8; mask++) {
      const masked = applyMask(m, reserved, size, MASK_FNS[mask]);
      drawFormatInfo(masked, size, mask);
      const s = penaltyScore(masked, size);
      if (s < bestScore) {
        bestScore = s;
        bestMatrix = masked;
        bestMask = mask;
      }
    }
    return { size, modules: bestMatrix, mask: bestMask };
  },
};

module.exports = QRCode;
