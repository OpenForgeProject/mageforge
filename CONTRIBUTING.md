# Contributing to MageForge

We appreciate your interest in contributing to MageForge! Please follow the guidelines below to ensure a smooth and effective contribution process.

> **New here?** Start with the [Development Guide](./docs/development.md) to set up your local DDEV environment before making any changes.

## Submitting a Pull Request

1. **Create an Issue**: Start by [creating an issue](https://github.com/OpenForgeProject/mageforge/issues) to describe your feature request or bug report. Be sure to include all relevant details.
2. **Fork & Branch**: Fork this repository and create a branch named `fix/<issue-description>` for bug fixes or `feature/<issue-description>` for new features. The branch should correspond to the issue you opened.
3. **Commit Your Changes**: Make your changes in the new branch and commit them with clear, descriptive messages.
4. **Open a Pull Request**: Submit a pull request to merge your changes into the `main` branch of this repository.

---

## Commit Message Guidelines

We use [Conventional Commits](https://www.conventionalcommits.org/) for automated changelog generation and semantic versioning via [Release Please](https://github.com/googleapis/release-please).

### For Contributors (Pull Requests)

**PR Title Format** (Required): Your PR title **must** follow Conventional Commits format:

```text
<type>: <description>

Examples:
✅ feat: add Hyvä compatibility check command
✅ fix: resolve npm installation issue
✅ docs: update README with new examples
✅ refactor: simplify theme builder logic
✅ perf: optimize static file cleaning

❌ Add new feature (missing type)
❌ Fixed bug (missing colon)
```

**Commit Types**:

- `feat:` - New feature (minor version bump: 0.3.0 → 0.4.0)
- `fix:` - Bug fix (patch version bump: 0.3.0 → 0.3.1)
- `refactor:` - Code refactoring (no version bump by default)
- `docs:` - Documentation updates (patch version bump)
- `perf:` - Performance improvements (patch version bump)
- `style:` - Code style changes (no version bump)
- `test:` - Test updates (no version bump)
- `chore:` - Maintenance tasks (no version bump)

**Breaking Changes**: Add `!` after the type for major version bumps:

```text
feat!: remove legacy theme builder API
fix!: change command argument order
```

**Individual Commits**: Your individual commits within the PR can use any format you prefer. We use **squash-merge**, so only the PR title becomes the commit message in the `main` branch.

### For Maintainers (Direct Commits)

When committing directly to `main` (e.g., hotfixes, urgent documentation updates), **all commits must follow Conventional Commits format**:

```bash
# Feature example
git commit -m "feat: add new command for theme token generation"

# Hotfix example
git commit -m "fix: resolve critical security vulnerability in npm dependencies"

# Documentation update
git commit -m "docs: add troubleshooting section to releases.md"

# Chore example
git commit -m "chore: update GitHub Actions to latest versions"
```

### Merge Strategy

All pull requests are merged using **squash-merge** to maintain a clean, linear git history. This means:

- ✅ Only one commit per PR in `main` branch
- ✅ PR title becomes the commit message
- ✅ All PR commits are squashed into a single commit
- ✅ Easier to follow project history and revert changes if needed

---

## Coding Standards

- **Magento Coding Standards**: Adhere to the Magento Coding Standards throughout your code.
- **PHPStan Level 9**: All PHP code must pass static analysis at the highest strictness level (`phpstan: max`). Run `ddev phpstan` locally before submitting. No `@phpstan-ignore*` comments allowed without explicit maintainer approval.
- **Code Validation**: Make sure your code is free of errors and warnings.
- **GitHub Actions**: Our pipeline will automatically check coding standards using GitHub Actions.

## Documentation Guidelines

- **Markdown Syntax**: Use [Markdown syntax](https://www.markdownguide.org/basic-syntax/) for all documentation files.

## Licensing

- **License Information**: Review the [LICENSE](./LICENSE) file for detailed licensing information.
- **Contribution License**: By contributing, you agree that your work will be licensed under the GPL-3.0 license.

---

## Code Formatting

- **Indentation**: Use 4 spaces for indentation.
- **Naming Conventions**: Choose meaningful names for variables and functions.
- **Line Length**: Keep lines under 80 characters wherever possible.
- **Linting**: Run `trunk check` (non-PHP files) and `ddev mago lint` (PHP) before submission.
- **Formatting**: Run `trunk fmt` (non-PHP files) and `ddev mago fmt` (PHP) to auto-format.

---

---

## Best Practices

### PHP & Architecture

- **PHPDoc**: All `public` methods must have complete PHPDoc blocks with `@return`, `@throws`, and parameter descriptions.
- **Soft Dependencies**: MageForge must work **with and without** Hyvä installed. Never hard-type-hint optional module classes in constructors — use `mixed` + `class_exists()` checks instead.
- **Builder Registration**: New theme builders must be registered in `src/etc/di.xml` under `<item name="BuilderPool" ...>`. The `detect()` method must be unique per builder (BuilderPool picks the first match).

### Code Quality

- **Comments**: Write clear and concise comments only for non-obvious logic. Do not document what is already obvious from the code.
- **Documentation**: Provide descriptions for functions, classes, and parameters in PHPDoc. Update `docs/` files when adding new commands or features.
- **Testing**: Thoroughly test your code before submitting. CI covers Magento 2.4.7-p10 (PHP 8.3), 2.4.8-p5 (PHP 8.4), and 2.4.9 (PHP 8.5) with OpenSearch.

### Frontend & Admin Settings

- **CSS Variables**: Never use hardcoded `rgba()` or color values — always use `--mageforge-*` CSS variables for theming consistency.
- **Admin Config Fields**: When adding a new admin config field, complete all steps together: `system.xml`, `config.xml`, config model constant, block getter, template data-attribute, JS getter, and **all locale CSV files** in `src/i18n/`.

---

## Code Review Process

1. **Submit for Review**: Once your pull request is ready, submit it for review.
2. **Request a Review**: Request a code review from a maintainer.
3. **Address Feedback**: Respond to any feedback or requested changes promptly.
4. **Ensure Checks Pass**: Make sure all CI checks pass (PHPStan Level 9, PHPCS, trunk) before your pull request is merged.

Thank you for contributing to MageForge! Your efforts help make this project better for everyone.
