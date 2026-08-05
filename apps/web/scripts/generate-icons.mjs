/**
 * Generates placeholder PWA icons (public/icons/*.png) with zero dependencies:
 * a brand-colored rounded square (or full-bleed square for the maskable icon)
 * with a blocky "SBH" wordmark, encoded as PNG via node:zlib.
 *
 * Usage: node scripts/generate-icons.mjs
 */
import { deflateSync } from "node:zlib";
import { mkdirSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const OUT_DIR = join(dirname(fileURLToPath(import.meta.url)), "..", "public", "icons");

// Brand colors.
const BG = [78, 138, 136, 255]; // SBH Muted Teal #4e8a88
const FG = [255, 255, 255, 255]; // white

// 5x7 pixel font for S, B, H.
const GLYPHS = {
  S: [".####", "#....", "#....", ".###.", "....#", "....#", "####."],
  B: ["####.", "#...#", "#...#", "####.", "#...#", "#...#", "####."],
  H: ["#...#", "#...#", "#...#", "#####", "#...#", "#...#", "#...#"],
};
const WORD = ["S", "B", "H"];
const GLYPH_W = 5;
const GLYPH_H = 7;
const GLYPH_GAP = 1;
const WORD_W = WORD.length * GLYPH_W + (WORD.length - 1) * GLYPH_GAP; // 17
const WORD_H = GLYPH_H;

// ---- PNG encoding -----------------------------------------------------------

const CRC_TABLE = new Int32Array(256).map((_, n) => {
  let c = n;
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  return c;
});

function crc32(buf) {
  let c = 0xffffffff;
  for (const byte of buf) c = CRC_TABLE[(c ^ byte) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}

function chunk(type, data) {
  const len = Buffer.alloc(4);
  len.writeUInt32BE(data.length);
  const typeAndData = Buffer.concat([Buffer.from(type, "ascii"), data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(typeAndData));
  return Buffer.concat([len, typeAndData, crc]);
}

function encodePng(width, height, rgba) {
  const signature = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(width, 0);
  ihdr.writeUInt32BE(height, 4);
  ihdr[8] = 8; // bit depth
  ihdr[9] = 6; // color type: RGBA
  // compression (0), filter (0), interlace (0) already zeroed.

  // Prefix each scanline with filter byte 0.
  const stride = width * 4;
  const raw = Buffer.alloc((stride + 1) * height);
  for (let y = 0; y < height; y++) {
    raw[y * (stride + 1)] = 0;
    rgba.copy(raw, y * (stride + 1) + 1, y * stride, (y + 1) * stride);
  }

  return Buffer.concat([
    signature,
    chunk("IHDR", ihdr),
    chunk("IDAT", deflateSync(raw, { level: 9 })),
    chunk("IEND", Buffer.alloc(0)),
  ]);
}

// ---- Rendering ---------------------------------------------------------------

function render(size, { maskable }) {
  const rgba = Buffer.alloc(size * size * 4); // transparent

  // Background: full-bleed for maskable, rounded square otherwise.
  const radius = maskable ? 0 : Math.round(size * 0.2);
  const inRoundedRect = (x, y) => {
    if (radius === 0) return true;
    const rx = Math.max(radius - x, x - (size - 1 - radius), 0);
    const ry = Math.max(radius - y, y - (size - 1 - radius), 0);
    return rx * rx + ry * ry <= radius * radius;
  };

  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      if (inRoundedRect(x, y)) {
        rgba.set(BG, (y * size + x) * 4);
      }
    }
  }

  // Wordmark: for maskable icons keep within the 80% safe zone.
  const usable = maskable ? 0.5 : 0.62;
  const scale = Math.max(1, Math.floor((size * usable) / WORD_W));
  const wordW = WORD_W * scale;
  const wordH = WORD_H * scale;
  const originX = Math.floor((size - wordW) / 2);
  const originY = Math.floor((size - wordH) / 2);

  WORD.forEach((letter, index) => {
    const glyph = GLYPHS[letter];
    const glyphX = originX + index * (GLYPH_W + GLYPH_GAP) * scale;
    for (let gy = 0; gy < GLYPH_H; gy++) {
      for (let gx = 0; gx < GLYPH_W; gx++) {
        if (glyph[gy][gx] !== "#") continue;
        for (let sy = 0; sy < scale; sy++) {
          for (let sx = 0; sx < scale; sx++) {
            const px = glyphX + gx * scale + sx;
            const py = originY + gy * scale + sy;
            rgba.set(FG, (py * size + px) * 4);
          }
        }
      }
    }
  });

  return encodePng(size, size, rgba);
}

mkdirSync(OUT_DIR, { recursive: true });

const targets = [
  ["icon-192.png", 192, { maskable: false }],
  ["icon-512.png", 512, { maskable: false }],
  ["maskable-512.png", 512, { maskable: true }],
];

for (const [name, size, opts] of targets) {
  const png = render(size, opts);
  writeFileSync(join(OUT_DIR, name), png);
  console.log(`wrote public/icons/${name} (${size}x${size}, ${png.length} bytes)`);
}
