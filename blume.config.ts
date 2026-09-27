import { defineConfig } from "blume";

export default defineConfig({
  title: "Pest Flow",
  description: "Executable behaviour specifications for Pest 5.",
  content: {
    exclude: ["**/_*", "**/.*", "PRD.md"],
  },
  deployment: {
    site: "https://maxiviper117.github.io",
    base: "/pest-flow",
  },
});
