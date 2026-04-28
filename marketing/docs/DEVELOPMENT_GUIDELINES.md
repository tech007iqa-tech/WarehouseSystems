# Development Guidelines for AI Agents

## Objective
To build a high-performance, modular marketing application with minimal friction.

## Workflow for New Features
1. **Define the Scope**: Create a file `modules/[module_name]/PLAN.md` outlining the feature.
2. **Setup Data**: Define the SQLite table structure in `data/schema.sql`.
3. **Build Backend Logic**: Create `modules/[module_name]/functions.php` for logic.
4. **Create UI**: Build `modules/[module_name]/index.php` for the presentation layer.
5. **Add Assets**: Place module-specific CSS/JS in `assets/css/` and `assets/js/`.

## Coding Standards
- **Naming**: Use camelCase for JS variables, snake_case for PHP variables and database columns.
- **Modularity**: Never hardcode configuration. Use a central `config.php`.
- **Comments**: Write meaningful comments explaining *why* something is done, not just *what*.
- **DRY (Don't Repeat Yourself)**: If a piece of logic is used in two places, move it to `includes/utils.php`.

## UI/UX Goals
- **Responsiveness**: All pages must work on mobile and desktop.
- **Aesthetics**: Use the design tokens defined in `assets/css/main.css`.
- **Feedback**: Provide clear success/error messages for all user actions.
