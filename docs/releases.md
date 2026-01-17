# Release Management

MageForge uses [Release Please](https://github.com/googleapis/release-please) to automate releases based on [Conventional Commits](https://www.conventionalcommits.org/).

---

## Overview

Release Please automatically:
- **Generates CHANGELOG** from commit messages
- **Calculates version numbers** using Semantic Versioning
- **Creates Release PRs** with all changes since last release
- **Creates GitHub Releases** when Release PR is merged
- **Updates version** in `composer.json`

---

## How It Works

### 1. Development Workflow

**For Contributors (via Pull Request)**:
```bash
# Create feature branch
git checkout -b feat/new-theme-builder

# Make commits (any format)
git commit -m "WIP: working on builder"
git commit -m "add tests"
git commit -m "fix typo"

# Open PR with Conventional Commit title
# PR Title: feat: add TailwindCSS theme builder
```

**For Maintainers (direct commits)**:
```bash
# Hotfix example
git commit -m "fix: resolve critical security issue in npm dependencies"
git push origin main

# Documentation update
git commit -m "docs: add troubleshooting guide for DDEV setup"
git push origin main
```

### 2. Release Please Creates Release PR

When commits are pushed to `main`, Release Please:
1. Parses commit messages (from PR titles or direct commits)
2. Opens/updates a **Release PR** with:
   - Updated `CHANGELOG.md`
   - Updated `composer.json` version
   - Calculated version number based on commit types

**Example Release PR**:
```
Title: chore: release 0.4.0

Changes:
- feat: add TailwindCSS theme builder → minor bump (0.3.1 → 0.4.0)
- fix: resolve npm installation issue → patch bump (included)
- docs: update README examples → patch bump (included)
```

### 3. Merge Release PR

When a maintainer merges the Release PR:
1. Release Please creates a **Git tag** (e.g., `0.4.0`)
2. Release Please creates a **GitHub Release** automatically
3. CHANGELOG content is extracted and added to release notes

---

## Conventional Commits Guide

### Commit Types

| Type | Description | Version Bump | In CHANGELOG |
|------|-------------|--------------|--------------|
| `feat:` | New feature | Minor (0.3.0 → 0.4.0) | ✅ Added |
| `fix:` | Bug fix | Patch (0.3.0 → 0.3.1) | ✅ Fixed |
| `refactor:` | Code refactoring | Patch | ✅ Changed |
| `perf:` | Performance improvement | Patch | ✅ Performance |
| `docs:` | Documentation | Patch | ✅ Documentation |
| `style:` | Code style (formatting) | None | ❌ Hidden |
| `test:` | Tests | None | ❌ Hidden |
| `chore:` | Maintenance | None | ❌ Hidden |
| `build:` | Build system | None | ❌ Hidden |
| `ci:` | CI/CD changes | None | ❌ Hidden |

### Breaking Changes

Add `!` after the type for **major version bumps**:

```bash
feat!: remove deprecated theme builder API
# Version: 0.3.0 → 1.0.0

fix!: change command argument order
# Version: 0.3.0 → 1.0.0
```

Or use `BREAKING CHANGE:` footer:

```bash
feat: add new theme builder

BREAKING CHANGE: The old builder API has been removed.
Use the new ThemeBuilderInterface instead.
```

### Scopes (Optional)

Add scope for more context:

```bash
feat(hyva): add Hyvä compatibility checker
fix(ddev): resolve MySQL connection issue
docs(readme): update installation instructions
```

### Examples

**Good PR Titles**:
```
✅ feat: add static file cleaning command
✅ fix: resolve theme detection for Hyvä themes
✅ docs: add custom theme builder guide
✅ refactor: simplify BuildCommand complexity
✅ perf: optimize npm package installation
```

**Bad PR Titles**:
```
❌ Add new feature (missing type)
❌ Fixed bug (missing colon)
❌ Update docs (not descriptive enough)
❌ WIP (not a conventional commit)
```

---

## Release Workflow

### Standard Release

1. **Developer**: Creates PR with conventional commit title
2. **Maintainer**: Reviews and merges PR (squash-merge)
3. **Release Please**: Opens/updates Release PR automatically
4. **Maintainer**: Reviews Release PR, merges when ready
5. **Release Please**: Creates tag and GitHub Release

### Hotfix Release

For urgent fixes that need immediate release:

```bash
# Make hotfix on main branch
git checkout main
git pull

# Commit with conventional commit format
git commit -m "fix: resolve critical security vulnerability CVE-2024-12345"
git push origin main

# Release Please creates Release PR immediately
# Merge Release PR to create hotfix release
```

### Pre-releases

For alpha/beta/rc releases, use version suffixes:

```bash
# Manual tag for pre-release
git tag 0.4.0-beta.1
git push origin 0.4.0-beta.1

# Workflow creates pre-release on GitHub
```

---

## GitHub Repository Settings

### Branch Protection Rules

**Recommended settings for `main` branch**:

1. **Require pull request reviews**
   - ✅ Require approvals: 1
   - ✅ Dismiss stale reviews when new commits are pushed

2. **Require status checks**
   - ✅ Require branches to be up to date
   - ✅ Status checks: `test-elasticsearch`, `test-opensearch`

3. **Merge options**
   - ✅ **Allow squash merging** (ONLY this one enabled)
   - ❌ Allow merge commits (disabled)
   - ❌ Allow rebase merging (disabled)
   - ✅ Default to PR title for squash merge commits

4. **Exceptions for maintainers** (optional)
   - ✅ Allow specified actors to bypass required pull requests
   - Add maintainer usernames (e.g., `dermatz`)

### Configuring Branch Protection

```bash
# Via GitHub UI:
Settings → Branches → Add rule

# Or via GitHub CLI:
gh api repos/OpenForgeProject/mageforge/branches/main/protection \
  --method PUT \
  --field required_pull_request_reviews[required_approving_review_count]=1 \
  --field required_status_checks[strict]=true \
  --field allow_squash_merge=true \
  --field allow_merge_commit=false \
  --field allow_rebase_merge=false
```

---

## Troubleshooting

### Release PR Not Created

**Problem**: Release Please doesn't create a Release PR after merging commits.

**Solutions**:

1. **Check commit format**: Ensure commits follow Conventional Commits
   ```bash
   # View recent commits
   git log --oneline main -10

   # Must have format: feat:, fix:, etc.
   ```

2. **Check for existing Release PR**: Only one Release PR can be open at a time
   ```bash
   # Search for open Release PRs
   gh pr list --label "autorelease: pending"
   ```

3. **Re-run Release Please**: Add label to trigger manual run
   ```bash
   # On any merged PR that should trigger a release
   gh pr edit <PR_NUMBER> --add-label "release-please:force-run"
   ```

### Version Number Incorrect

**Problem**: Release Please calculated wrong version number.

**Solutions**:

1. **Check commit types**: Verify commit messages have correct types
   - `feat:` → minor bump
   - `fix:` → patch bump
   - `feat!:` → major bump

2. **Manual version override**: Use `Release-As` footer
   ```bash
   git commit -m "feat: add new feature

   Release-As: 1.0.0"
   ```

### CHANGELOG Not Generated

**Problem**: CHANGELOG.md is not updated by Release Please.

**Solutions**:

1. **Check file location**: Must be at `src/CHANGELOG.md` (configured in `release-please-config.json`)

2. **Check format**: Release Please expects specific format
   ```markdown
   # Changelog for MageForge

   ## [0.3.1] - 2026-01-12
   ### Fixed
   - fix: something
   ```

3. **Bootstrap manifest**: Initialize Release Please
   ```bash
   # Check current manifest
   cat .release-please-manifest.json

   # Should show: {"src": "0.3.1"}
   ```

### Release Workflow Failed
Not Created

**Problem**: GitHub Release is not created after merging Release PR.

**Solutions**:

1. **Check Release Please workflow**: Verify workflow ran successfully
   ```bash
   gh run list --workflow=release-please.yml
   gh run view <RUN_ID> --log
   ```

2. **Check permissions**: Workflow needs `contents: write` and `pull-requests: write` permissions

3. **Verify tag was created**:
   ```bash
   # List recent tags
   git tag -l --sort=-version:refname | head -5

---

## Manual Release Process (Fallback)

If Release Please is unavailable, you can create releases manually:

```bash
# 1. Update version in composer.json
vim src/composer.json

# 2. Update CHANGELOG.md
vim src/CHANGELOG.md

# 3. Commit changes
git add src/composer.json src/CHANGELOG.md
git commit -m "chore: release 0.4.0"

# 4. Create tag
git tag 0.4.0
git push origin main --tags

# 5. GitHub workflow creates release automatically
```

---

## References

- [Release Please Documentation](https://github.com/googleapis/release-please)
- [Conventional Commits Specification](https://www.conventionalcommits.org/)
- [Semantic Versioning](https://semver.org/)
- [MageForge Contributing Guide](../CONTRIBUTING.md)
