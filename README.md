# 10up WP Scaffold Setup

This WordPress installation has been configured with the 10up WP Scaffold.

## Structure

- **`themes/10up-theme/`** - The main theme with 10up Toolkit integration
- **`mu-plugins/10up-plugin/`** - Must-use plugin for site functionality
- **`mu-plugins/10up-plugin-loader.php`** - Loader for the must-use plugin

## Development

### Prerequisites

- Node.js >= 20
- NPM >= 9
- PHP and Composer

### Getting Started

1. Install dependencies (from wp-content directory):
   ```bash
   npm install
   ```

2. For development with Hot Module Reload:
   ```bash
   npm run watch
   ```
   This will watch both the theme and plugin simultaneously.

3. To build for production:
   ```bash
   npm run build
   ```

### Configuration

- **Site URL**: Update `devURL` in `themes/10up-theme/package.json` and `mu-plugins/10up-plugin/package.json` to match your local development URL (currently set to `http://marg-art.local`)
- **SCRIPT_DEBUG**: Already enabled in `wp-config.php` for Hot Module Reload

### Available Scripts

From the `wp-content` directory:

- `npm run build` - Build all workspaces for production
- `npm run watch` - Start development server with HMR for theme and plugin
- `npm run lint-js` - Lint JavaScript files
- `npm run format-js` - Format JavaScript files
- `npm run lint-style` - Lint CSS files
- `npm run test` - Run tests
- `npm run clean-dist` - Clean distribution files

### Individual Workspace Commands

You can also work on individual packages:

**Theme** (from `themes/10up-theme/`):
- `npm run start` or `npm run watch` - Development with HMR (port 5000)
- `npm run build` - Production build
- `npm run scaffold:block` - Create a new Gutenberg block

**Plugin** (from `mu-plugins/10up-plugin/`):
- `npm run start` or `npm run watch` - Development with HMR (port 5010)
- `npm run build` - Production build

### Code Quality

The project includes:
- ESLint for JavaScript linting
- Stylelint for CSS linting
- PHPCS for PHP coding standards
- PHPStan for PHP static analysis
- Husky and lint-staged for pre-commit hooks

### Documentation

For more detailed information, refer to:
- [10up Toolkit Documentation](https://github.com/10up/10up-toolkit)
- [10up WP Scaffold Documentation](https://github.com/10up/wp-scaffold)