import { Resvg } from "@resvg/resvg-js";
import { createRequire } from "node:module";
import { escapeHtml } from "./content.mjs";

const require = createRequire(import.meta.url);
const fontDir = require
  .resolve("dejavu-fonts-ttf/package.json")
  .replace("package.json", "ttf/");
const fontFiles = [
  "DejaVuSans.ttf",
  "DejaVuSans-Bold.ttf",
  "DejaVuSansMono.ttf",
  "DejaVuSansMono-Bold.ttf",
].map((f) => fontDir + f);

const LOGO =
  "M176 0 0 101.614V297l176 101.614L352 297V101.614zM39 275.5V124l37.24-22.386L101.5 162l25-88.56 38-21.94v295.439L126.5 325V188l-18 51H95l-18.76-51v109zm148.5 71.439V51.5L313 124v46h-37.5v-23.632l-50-28.868V188H280v38.5h-54.5V325z";

function wrap(text, max, maxLines) {
  const lines = [];
  let line = "";
  for (const word of text.split(/\s+/)) {
    if ((line + " " + word).trim().length > max && line) {
      lines.push(line);
      line = word;
    } else {
      line = (line + " " + word).trim();
    }
  }
  if (line) lines.push(line);
  if (lines.length > maxLines) {
    lines.length = maxLines;
    lines[maxLines - 1] = lines[maxLines - 1].replace(/\s*\S{0,3}$/, "") + "…";
  }
  return lines;
}

export function ogSvg({ eyebrow, title, subtitle, badge, mono = false, host }) {
  const titleSize = mono
    ? Math.min(54, Math.floor(1056 / (title.length * 0.602)))
    : 58;
  const titleLines = wrap(title, mono ? 30 : 28, 3);
  const subLines = wrap(subtitle || "", 60, 2);
  const font = mono ? "'DejaVu Sans Mono'" : "'DejaVu Sans'";
  const titleY = 262;
  const subY = titleY + titleLines.length * (titleSize + 8) + 10;

  return `<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
<defs>
  <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0f1115"/><stop offset="1" stop-color="#1c1410"/></linearGradient>
  <radialGradient id="glow" cx="1" cy="0" r="0.9"><stop offset="0" stop-color="#e5622a" stop-opacity="0.45"/><stop offset="1" stop-color="#e5622a" stop-opacity="0"/></radialGradient>
</defs>
<rect width="1200" height="630" fill="url(#bg)"/>
<rect width="1200" height="630" fill="url(#glow)"/>
<g transform="translate(72 64) scale(0.2)"><path fill="#e5622a" fill-rule="evenodd" d="${LOGO}"/></g>
<text x="160" y="112" font-family="'DejaVu Sans'" font-weight="700" font-size="34" fill="#f5efe9">MageForge</text>
${eyebrow ? `<text x="72" y="200" font-family="'DejaVu Sans'" font-weight="700" font-size="26" letter-spacing="4" fill="#ff8a55">${escapeHtml(eyebrow.toUpperCase())}</text>` : ""}
${titleLines
  .map(
    (l, i) =>
      `<text x="72" y="${titleY + i * (titleSize + 8)}" font-family="${font}" font-weight="700" font-size="${titleSize}" fill="#ffffff">${escapeHtml(l)}</text>`,
  )
  .join("\n")}
${subLines.map((l, i) => `<text x="72" y="${subY + i * 42}" font-family="'DejaVu Sans'" font-size="30" fill="#cfc6bd">${escapeHtml(l)}</text>`).join("\n")}
${badge ? `<rect x="72" y="500" rx="12" width="${badge.length * 18 + 40}" height="52" fill="#e5622a"/><text x="92" y="534" font-family="'DejaVu Sans'" font-weight="700" font-size="26" fill="#1a0f0a">${escapeHtml(badge)}</text>` : ""}
<text x="1128" y="566" text-anchor="end" font-family="'DejaVu Sans'" font-size="26" fill="#9b9188">${escapeHtml(host)}</text>
</svg>`;
}

export function renderPng(svg, width = 1200) {
  return new Resvg(svg, {
    fitTo: { mode: "width", value: width },
    font: {
      fontFiles,
      loadSystemFonts: false,
      defaultFontFamily: "DejaVu Sans",
    },
  })
    .render()
    .asPng();
}

export function iconPng(size) {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="-60 -40 472 479"><rect x="-60" y="-40" width="472" height="479" fill="#0f1115"/><path fill="#e5622a" fill-rule="evenodd" d="${LOGO}"/></svg>`;
  return renderPng(svg, size);
}

export { LOGO };
