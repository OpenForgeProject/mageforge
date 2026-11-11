import { createServer } from "node:http";
import { readFile, stat } from "node:fs/promises";
import { extname, join, normalize } from "node:path";
import { fileURLToPath } from "node:url";

const dist = fileURLToPath(new URL("../dist/", import.meta.url));
const port = Number(process.env.PORT || 4173);
const types = {
  ".html": "text/html",
  ".css": "text/css",
  ".js": "text/javascript",
  ".json": "application/json",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".xml": "application/xml",
  ".txt": "text/plain",
  ".webmanifest": "application/manifest+json",
};

const base = (process.env.BASE_PATH || "").replace(/\/$/, "");

createServer(async (req, res) => {
  let pathname;
  try {
    pathname = decodeURIComponent(new URL(req.url, "http://x").pathname);
  } catch {
    res.writeHead(400, { "Content-Type": "text/plain" });
    return res.end("Bad request");
  }
  if (base) {
    if (pathname !== base && !pathname.startsWith(`${base}/`))
      pathname = "/__outside__";
    else pathname = pathname.slice(base.length) || "/";
  }
  const path = normalize(pathname).replace(/^(\.\.[/\\])+/, "");
  let file = join(dist, path);
  try {
    if ((await stat(file)).isDirectory()) file = join(file, "index.html");
    res.writeHead(200, {
      "Content-Type": types[extname(file)] || "application/octet-stream",
    });
    res.end(await readFile(file));
  } catch {
    res.writeHead(404, { "Content-Type": "text/html" });
    res.end(await readFile(join(dist, "404.html")));
  }
}).listen(port, () => console.log(`http://localhost:${port}${base}/`));
