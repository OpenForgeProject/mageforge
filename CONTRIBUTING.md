# Contributing to MageForge

We appreciate your interest in contributing to MageForge! Please follow the guidelines below to ensure a smooth and effective contribution process.

## Submitting a Pull Request

1. **Create an Issue**: Start by [creating an issue](https://github.com/OpenForgeProject/mageforge/issues) to describe your feature request or bug report. Be sure to include all relevant details.
2. **Fork the Repository**: Fork this repository and create a new branch for your work that corresponds to the issue you opened.
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
- **Linting**: Run `trunk check` to lint your code before submission.

---

## VSCode Users

If you use VSCode, our workspace settings, located in `.vscode/settings.json`, will be applied automatically. We recommend using the [Git Commit Message Helper](https://marketplace.visualstudio.com/items?itemName=D3skdev.git-commit-message-helper) to format your commit messages with the appropriate prefixes based on your GitHub branch name and issue ID.

For example, use: `#123 - Commit Message ...`

![Git Commit Message Helper Demo](https://github.com/d3skdev/git-prefix/raw/master/images/demo.gif)

---

## Best Practices

- **Comments**: Write clear and concise comments.
- **Documentation**: Provide descriptions for functions, classes, and parameters.
- **Testing**: Thoroughly test your code before submitting it.

---

## Code Review Process

1. **Submit for Review**: Once your pull request is ready, submit it for review.
2. **Request a Review**: Request a code review from a maintainer.
3. **Address Feedback**: Respond to any feedback or requested changes promptly.
4. **Ensure Tests Pass**: Make sure all tests pass before your pull request is merged.

Thank you for contributing to MageForge! Your efforts help make this project better for everyone.
