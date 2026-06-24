import { buildMethods } from "./ui/build.js";
import { scoreMethods } from "./ui/score.js";
import { itemMethods } from "./ui/items.js";
import { controls } from "./ui/controls.js";

export const uiMethods = {
  ...buildMethods,
  ...scoreMethods,
  ...itemMethods,
  ...controls,
};
