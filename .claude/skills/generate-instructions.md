---
name: Generate Project Instructions
description: Empties Human-Instructions.md, analyzes the project's TODO list, and generates step-by-step instructions.
invocation: USER # This means the user invokes it manually with /generate-instructions
model: claude-3-opus # Use Opus for best reasoning capabilities
---

# Skill: Generate Project Instructions

You are an expert project manager and technical writer. Your task is to update the `Human-Instructions.md` file for this WordPress plugin project.

**Workflow Steps:**

1.  **Empty the current `Human-Instructions.md` file.** Ensure it is blank before writing any new content.
2.  **Scan the entire project for all TODO items.** Look for `TODO:` comments within all code files (`.php`, `.js`, `.css`, etc.).
3.  **Analyze the collected TODO list.** Group related items and prioritize them into logical development phases.
4.  **Generate a comprehensive, step-by-step guide in `Human-Instructions.md`** that a human developer can follow to complete the tasks.
    *   The instructions must be clear, concise, and actionable.
    *   Use numbered lists for sequential steps.
    *   Include relevant file references using `@` mentions (e.g., `@path/to/file.php`) to provide context.
    *   Do not include any other commentary in the output, only the content for `Human-Instructions.md`.

**Files for Analysis (provide relevant project files as context when running the command):**

*   `Human-Instructions.md`
*   `functions.php` (example, include your main plugin files)
*   `your-plugin-name.php` (main plugin file)
*   *Add any other relevant project files here or use the @-mention feature when invoking the skill.*

Begin the process now, operating in auto-accept mode for file edits to complete the task efficiently.
