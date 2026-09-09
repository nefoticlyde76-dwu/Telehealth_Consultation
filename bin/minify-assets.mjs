import { readdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import CleanCSS from "clean-css";
import { minify } from "terser";

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const cssDir = join(root, "public", "css");
const jsDir = join(root, "public", "js");

function sources(dir, extension) {
  return readdirSync(dir)
    .filter((name) => name.endsWith(extension) && !name.includes(".min."))
    .map((name) => join(dir, name));
}

const cssMinifier = new CleanCSS({ level: 1, compatibility: "*" });

for (const file of sources(cssDir, ".css")) {
  const input = readFileSync(file, "utf8");
  const result = cssMinifier.minify(input);
  if (result.errors.length > 0) {
    throw new Error(`${file}: ${result.errors.join("; ")}`);
  }
  const dest = file.replace(/\.css$/, ".min.css");
  writeFileSync(dest, result.styles);
  console.log(`css  ${file.replace(root + "\\", "").replace(root + "/", "")} -> ${dest.replace(root + "\\", "").replace(root + "/", "")} (${input.length} -> ${result.styles.length})`);
}

for (const file of sources(jsDir, ".js")) {
  const input = readFileSync(file, "utf8");
  const result = await minify(input, {
    compress: true,
    mangle: true,
    format: { comments: false },
  });
  if (!result.code) {
    throw new Error(`${file}: terser produced empty output`);
  }
  const dest = file.replace(/\.js$/, ".min.js");
  writeFileSync(dest, result.code);
  console.log(`js   ${file.replace(root + "\\", "").replace(root + "/", "")} -> ${dest.replace(root + "\\", "").replace(root + "/", "")} (${input.length} -> ${result.code.length})`);
}
