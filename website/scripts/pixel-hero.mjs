// Generates src/assets/hero-pixel.svg – a pixel-art forge scene.
// Run: node scripts/pixel-hero.mjs   (output is committed, so builds don't depend on it)
import { Resvg } from "@resvg/resvg-js";
import { writeFile, mkdir } from "node:fs/promises";
import { LOGO } from "../lib/og.mjs";

const W = 96;
const H = 54;
const grid = Array.from({ length: H }, () => Array(W).fill(null));
const put = (x, y, c) => {
  x = Math.round(x);
  y = Math.round(y);
  if (x >= 0 && x < W && y >= 0 && y < H) grid[y][x] = c;
};

let seed = 1337;
const rnd = () => (seed = (seed * 1664525 + 1013904223) >>> 0) / 2 ** 32;
const bayer = [
  [0, 2],
  [3, 1],
];

// Sky: vertical colour bands with dithered transitions
const sky = [
  "#0b0f15",
  "#0e131b",
  "#111721",
  "#151a24",
  "#1a1c26",
  "#211d27",
  "#2b1e26",
  "#3a2025",
  "#4d2222",
];
for (let y = 0; y < H; y++) {
  const t = (y / 40) * (sky.length - 1);
  const i = Math.min(sky.length - 2, Math.floor(t));
  const f = t - i;
  for (let x = 0; x < W; x++) {
    const threshold = (bayer[y % 2][x % 2] + 0.5) / 4;
    put(x, y, sky[f > threshold ? i + 1 : i]);
  }
}

// Stars
for (let n = 0; n < 70; n++) {
  const x = Math.floor(rnd() * W);
  const y = Math.floor(rnd() * 26);
  put(x, y, rnd() > 0.8 ? "#e6edf3" : "#6f7d8f");
}

// Moon
const MX = 80;
const MY = 9;
for (let y = -4; y <= 4; y++)
  for (let x = -4; x <= 4; x++) {
    const d = Math.hypot(x, y);
    if (d <= 4)
      put(
        MX + x,
        MY + y,
        Math.hypot(x + 2, y - 1) < 3.6 ? "#c9d3df" : "#eef2f7",
      );
  }

// Hills
const hill = (base, amp, freq, phase, colour) => {
  for (let x = 0; x < W; x++) {
    const top = Math.round(
      base +
        Math.sin(x * freq + phase) * amp +
        Math.sin(x * freq * 2.3 + phase * 2) * amp * 0.4,
    );
    for (let y = top; y < H; y++) put(x, y, colour);
  }
};
hill(34, 3.5, 0.09, 1, "#141a23");
hill(39, 3, 0.12, 4, "#0f141b");

// Tower with lit windows
const tower = (x0, y0, w, h) => {
  for (let y = y0; y < y0 + h; y++)
    for (let x = x0; x < x0 + w; x++) put(x, y, "#0a0e13");
  for (let x = x0 - 1; x < x0 + w + 1; x += 2) put(x, y0 - 1, "#0a0e13");
  put(x0 + 1, y0 + 3, "#ffb347");
  put(x0 + w - 2, y0 + 7, "#ff8a3d");
};
tower(10, 27, 5, 14);
tower(17, 31, 4, 10);
tower(76, 30, 5, 11);

// Pine trees
const pine = (x, y, h) => {
  for (let i = 0; i < h; i++)
    for (let k = -Math.floor(i / 2); k <= Math.floor(i / 2); k++)
      put(x + k, y + i, "#0a0e13");
  put(x, y + h, "#0a0e13");
  put(x, y + h + 1, "#0a0e13");
};
[
  [3, 31, 8],
  [6, 34, 7],
  [88, 29, 9],
  [92, 33, 7],
  [70, 35, 6],
  [27, 36, 6],
].forEach((p) => pine(...p));

// Floor
for (let y = 47; y < H; y++)
  for (let x = 0; x < W; x++) {
    const brick =
      (Math.floor(x / 8) + (Math.floor(y / 3) % 2 ? 0 : 4)) % 8 === 0 ||
      y % 3 === 0;
    put(x, y, brick ? "#0b1016" : y < 49 ? "#161d27" : "#121821");
  }

// Halo
const CX = 48;
const CY = 20;
const halo = ["#1d1620", "#2a1a1e", "#3d1f14", "#552812", "#732f14"];
for (let y = 0; y < 48; y++)
  for (let x = 0; x < W; x++) {
    const d = Math.hypot((x - CX) * 0.9, y - CY);
    const v = Math.max(0, 1 - d / 30) ** 1.6;
    const level = v * halo.length;
    if (level > 0.4) {
      const idx = Math.min(
        halo.length - 1,
        Math.floor(level - (bayer[y % 2][x % 2] + 0.5) / 4 + 0.5),
      );
      if (idx >= 0) put(x, y, halo[idx]);
    }
  }

// Logo, rasterised from the real SVG and thresholded into pixels
const LH = 31;
const LW = Math.round((LH * 352) / 399);
const logoSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="352" height="399" viewBox="0 0 352 399"><path fill="#fff" fill-rule="evenodd" d="${LOGO}"/></svg>`;
const img = new Resvg(logoSvg, {
  fitTo: { mode: "height", value: LH },
}).render();
const lx0 = CX - Math.floor(img.width / 2);
const ly0 = 3;
for (let y = 0; y < img.height; y++)
  for (let x = 0; x < img.width; x++) {
    const a = img.pixels[(y * img.width + x) * 4 + 3];
    if (a > 110) {
      const shade =
        y < img.height * 0.35
          ? "#ff8a4a"
          : y < img.height * 0.7
            ? "#e5622a"
            : "#c24e1d";
      put(lx0 + x, ly0 + y, shade);
    }
  }
// Hex highlight edge (left-top)
for (let y = 0; y < img.height; y++)
  for (let x = 0; x < img.width; x++) {
    const a = img.pixels[(y * img.width + x) * 4 + 3];
    const left = x > 0 ? img.pixels[(y * img.width + x - 1) * 4 + 3] : 0;
    const up = y > 0 ? img.pixels[((y - 1) * img.width + x) * 4 + 3] : 0;
    if (a > 110 && (left <= 110 || up <= 110) && y < img.height * 0.6)
      put(lx0 + x, ly0 + y, "#ffc08f");
  }

// Anvil
const pal = {
  h: "#a9b8cb",
  l: "#76859a",
  m: "#4d596a",
  d: "#2f3845",
  k: "#1b212b",
};
const anvil = [
  "..hhhhhhhhhhhhhhhhhhhhhhhhhhhh..",
  "hhhllllllllllllllllllllllllllmm.",
  ".hhllllllllllllllllllllllllmmmd.",
  "..mmmllllllllllllllllllmmmmmdd..",
  "....dmmmmmmmmmmmmmmmmmmmddd.....",
  "......dmmmmmmmmmmmmmmmdd........",
  ".......dmmmmmmmmmmmmmdd.........",
  ".......dmmmmmmmmmmmmmdd.........",
  "......ddmmmmmmmmmmmmmmdd........",
  "....ddmmmmmmmmmmmmmmmmmmdd......",
  "...ddmmmmmmmmmmmmmmmmmmmmmdd....",
  "...kddddddddddddddddddddddddk...",
];
const AX = 32;
const AY = 38;
anvil.forEach((row, y) =>
  [...row].forEach((ch, x) => ch !== "." && put(AX + x, AY + y, pal[ch])),
);
// Anvil reflection glow
for (let x = 36; x < 60; x++) put(x, 50, "#3a1d12");

// Glowing bar on the anvil
for (let x = 40; x < 56; x++) {
  put(x, 36, x > 43 && x < 52 ? "#fff1c7" : "#ffc15a");
  put(x, 37, x > 41 && x < 54 ? "#ff9a3c" : "#e5622a");
}

// Braziers
const brazier = (x, y) => {
  for (let i = 0; i < 4; i++)
    for (let k = 0; k < 6 - i; k++)
      put(x + i / 2 + k - 2.5 + i * 0.5, y + 6 + i, "#2f3845");
  for (let k = 0; k < 6; k++) put(x + k - 3, y + 5, "#4d596a");
  [
    [0, 0, "#ffe08a"],
    [-1, 1, "#ffb347"],
    [0, 1, "#ffb347"],
    [1, 1, "#ffb347"],
    [-1, 2, "#ff8a3d"],
    [0, 2, "#ff8a3d"],
    [1, 2, "#ff8a3d"],
    [-2, 3, "#e5622a"],
    [-1, 3, "#e5622a"],
    [0, 3, "#e5622a"],
    [1, 3, "#e5622a"],
    [2, 3, "#e5622a"],
    [-2, 4, "#c24e1d"],
    [-1, 4, "#c24e1d"],
    [0, 4, "#c24e1d"],
    [1, 4, "#c24e1d"],
    [2, 4, "#c24e1d"],
  ].forEach(([dx, dy, c]) => put(x + dx, y + dy, c));
};
brazier(14, 41);
brazier(82, 41);

// Merge into horizontal runs
const runs = new Map();
for (let y = 0; y < H; y++) {
  let x = 0;
  while (x < W) {
    const c = grid[y][x];
    let n = 1;
    while (x + n < W && grid[y][x + n] === c) n++;
    if (c) {
      if (!runs.has(c)) runs.set(c, []);
      runs.get(c).push(`M${x} ${y}h${n}v1h-${n}z`);
    }
    x += n;
  }
}
const paths = [...runs]
  .map(([c, d]) => `<path fill="${c}" d="${d.join("")}"/>`)
  .join("\n");

// Animated sparks
const sparkColours = ["#fff1c7", "#ffc15a", "#ff9a3c", "#e5622a"];
let sparks = "";
for (let i = 0; i < 26; i++) {
  const x = 42 + Math.floor(rnd() * 12);
  const dx = Math.round((rnd() - 0.5) * 40);
  const dy = -Math.round(10 + rnd() * 28);
  const delay = (rnd() * 2.6).toFixed(2);
  const dur = (1.6 + rnd() * 1.6).toFixed(2);
  sparks += `<rect class="sp" x="${x}" y="35" width="1" height="1" fill="${sparkColours[i % 4]}" style="--dx:${dx}px;--dy:${dy}px;animation-delay:${delay}s;animation-duration:${dur}s"/>\n`;
}

const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" shape-rendering="crispEdges" role="img" aria-label="Pixel art: a glowing MageForge emblem above a blacksmith's anvil with sparks flying">
<style>
.sp{opacity:0;animation:rise 2.4s ease-out infinite}
.fl{animation:flick .6s steps(2) infinite alternate}
@keyframes rise{0%{transform:translate(0,0);opacity:1}70%{opacity:1}100%{transform:translate(var(--dx),var(--dy));opacity:0}}
@keyframes flick{from{opacity:1}to{opacity:.55}}
@media (prefers-reduced-motion:reduce){.sp{animation:none;opacity:.9}.fl{animation:none}}
</style>
${paths}
<g class="fl"><rect x="13" y="42" width="3" height="2" fill="#ffe08a"/><rect x="81" y="42" width="3" height="2" fill="#ffe08a"/></g>
${sparks}</svg>
`;

await mkdir(new URL("../src/assets/", import.meta.url), { recursive: true });
await writeFile(new URL("../src/assets/hero-pixel.svg", import.meta.url), svg);
await writeFile(
  "/tmp/hero-pixel-preview.png",
  new Resvg(svg, { fitTo: { mode: "width", value: 960 } }).render().asPng(),
);
console.log("hero-pixel.svg", (svg.length / 1024).toFixed(1), "KB");
