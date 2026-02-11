# WP VRT Goals

## Purpose
- Provide stable virtual pages for visual regression testing of block themes.
- Render blocks, patterns, templates, and template parts with full theme styling.

## Core Capabilities
- Virtual pages under `/wp-vrt/*` that output bare HTML with global styles.
- REST discovery endpoint for automation tooling.
- Support block style variations and custom scenarios via hooks.

## Rendering Principles
- Use WordPress core rendering functions and global styles.
- Avoid dynamic context by default; allow opt-in dynamic rendering.

## Tooling Integration
- Playwright/Pest snapshot support with discovery-driven test loops.
- Local dev compatibility with tools like wp-now.
