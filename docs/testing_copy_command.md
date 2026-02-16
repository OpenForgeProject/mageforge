# Testing Guide: Copy From Vendor Command

This guide describes how to test the `mageforge:theme:copy-from-vendor` command with different theme types and scenarios.

## Automated Tests

Run unit tests:

```bash
ddev magento dev:tests:run unit vendor/openforgeproject/mageforge/Test/Unit/Service/VendorFileMapperTest.php
```

## Manual Testing Scenarios

### Prerequisites

```bash
ddev start
ddev magento cache:clean
ddev magento setup:upgrade
```

### 1. Frontend Theme Tests

#### Test 1.1: Copy to Magento/luma (Standard Frontend Theme)

```bash
# Test with view/frontend file
ddev magento mageforge:theme:copy-from-vendor \
  vendor/magento/module-catalog/view/frontend/templates/product/list.phtml \
  Magento/luma \
  --dry-run

# Expected: app/design/frontend/Magento/luma/Magento_Catalog/templates/product/list.phtml
```

#### Test 1.2: Copy to Magento/blank (Standard Frontend Theme)

```bash
# Test with view/base file (should work with frontend theme)
ddev magento mageforge:theme:copy-from-vendor \
  vendor/magento/module-theme/view/base/web/css/print.css \
  Magento/blank \
  --dry-run

# Expected: app/design/frontend/Magento/blank/Magento_Theme/web/css/print.css
```

#### Test 1.3: Copy to Hyvä Theme (if available)

```bash
# Test with Hyvä-specific file
ddev magento mageforge:theme:copy-from-vendor \
  vendor/hyva-themes/magento2-default-theme/Magento_Catalog/templates/product/list/item.phtml \
  Hyva/default \
  --dry-run

# Expected: app/design/frontend/Hyva/default/Magento_Catalog/templates/product/list/item.phtml
```

### 2. Adminhtml Theme Tests

#### Test 2.1: Copy to Adminhtml Theme

```bash
# Test with view/adminhtml file
ddev magento mageforge:theme:copy-from-vendor \
  vendor/magento/module-backend/view/adminhtml/templates/page/header.phtml \
  Magento/backend \
  --dry-run

# Expected: app/design/adminhtml/Magento/backend/Magento_Backend/templates/page/header.phtml
```

#### Test 2.2: Copy base file to Adminhtml Theme

```bash
# Test with view/base file (should work with adminhtml theme)
ddev magento mageforge:theme:copy-from-vendor \
  vendor/magento/module-ui/view/base/web/js/grid/columns/column.js \
  Magento/backend \
  --dry-run

# Expected: app/design/adminhtml/Magento/backend/Magento_Ui/web/js/grid/columns/column.js
```

### 3. Negative Tests (Should Fail)

#### Test 3.1: Cross-Area Mapping (Frontend → Adminhtml)

```bash
# This should FAIL with clear error message
ddev magento mageforge:theme:copy-from-vendor \
  vendor/magento/module-catalog/view/frontend/templates/product/list.phtml \
  Magento/backend \
  --dry-run

# Expected: RuntimeException - "Cannot map file from area 'frontend' to adminhtml theme"
```

#### Test 3.2: Cross-Area Mapping (Adminhtml → Frontend)

```bash
# This should FAIL with clear error message
ddev magento mageforge:theme:copy-from-vendor \
  vendor/magento/module-backend/view/adminhtml/templates/dashboard.phtml \
  Magento/luma \
  --dry-run

# Expected: RuntimeException - "Cannot map file from area 'adminhtml' to frontend theme"
```

#### Test 3.3: Non-View File

```bash
# This should FAIL with clear error message
ddev magento mageforge:theme:copy-from-vendor \
  vendor/magento/module-catalog/etc/di.xml \
  Magento/luma \
  --dry-run

# Expected: RuntimeException - "File is not under a view/ directory"
```

#### Test 3.4: Non-Existent File

```bash
# This should FAIL with clear error message
ddev magento mageforge:theme:copy-from-vendor \
  vendor/magento/module-catalog/view/frontend/templates/nonexistent.phtml \
  Magento/luma \
  --dry-run

# Expected: RuntimeException - "Source file not found"
```

### 4. Interactive Mode Tests

#### Test 4.1: Theme Selection Prompt

```bash
# Test interactive theme selection (omit theme argument)
ddev magento mageforge:theme:copy-from-vendor \
  vendor/magento/module-catalog/view/frontend/templates/product/view.phtml \
  --dry-run

# Expected: Interactive prompt to select theme
# Verify all available themes are listed
# Verify search functionality works
```

### 5. Real Copy Tests (Without --dry-run)

**⚠️ Warning: These tests will actually modify files**

#### Test 5.1: Create New File

```bash
# Copy a file that doesn't exist in theme yet
ddev magento mageforge:theme:copy-from-vendor \
  vendor/magento/module-catalog/view/frontend/templates/product/list/toolbar.phtml \
  Magento/luma

# Verify:
# 1. File created at correct location
# 2. Directory structure created if needed
# 3. Success message displayed
```

#### Test 5.2: Overwrite Existing File

```bash
# Copy to same location again
ddev magento mageforge:theme:copy-from-vendor \
  vendor/magento/module-catalog/view/frontend/templates/product/list/toolbar.phtml \
  Magento/luma

# Verify:
# 1. Warning about existing file
# 2. Confirmation prompt appears
# 3. File overwritten only if confirmed
```

#### Test 5.3: Cleanup After Tests

```bash
# Remove test files
rm -f app/design/frontend/Magento/luma/Magento_Catalog/templates/product/list/toolbar.phtml

# Clear cache
ddev magento cache:clean
```

### 6. Theme Type Verification

#### Test 6.1: Verify Theme Types are Correctly Identified

```bash
# List all themes with their types
ddev magento mageforge:theme:list

# Expected output should show:
# - Theme code
# - Area (frontend/adminhtml)
# - Path
# - Builder type (if shown)
```

#### Test 6.2: Verify Theme Path Resolution

```bash
# Check system info
ddev magento mageforge:system:check

# Verify theme registration is working correctly
ddev magento theme:list
```

## Test Matrix

| Source Area | Target Theme Area | Expected Result |
|-------------|-------------------|-----------------|
| frontend    | frontend          | ✅ Success      |
| frontend    | adminhtml         | ❌ Exception    |
| adminhtml   | frontend          | ❌ Exception    |
| adminhtml   | adminhtml         | ✅ Success      |
| base        | frontend          | ✅ Success      |
| base        | adminhtml         | ✅ Success      |
| etc/        | frontend          | ❌ Exception    |
| etc/        | adminhtml         | ❌ Exception    |

## CI/CD Integration

The command should be tested in CI/CD pipeline:

```yaml
# Add to .github/workflows/magento-compatibility.yml
- name: Test Copy Command
  run: |
    # Test basic functionality
    bin/magento mageforge:theme:copy-from-vendor --help
    
    # Test dry-run mode
    bin/magento mageforge:theme:copy-from-vendor \
      vendor/magento/module-catalog/view/frontend/templates/product/list.phtml \
      Magento/luma \
      --dry-run
    
    # Test error handling
    if bin/magento mageforge:theme:copy-from-vendor \
      vendor/magento/module-catalog/etc/di.xml \
      Magento/luma \
      --dry-run 2>&1 | grep -q "not under a view"; then
      echo "✓ Non-view file correctly rejected"
    else
      echo "✗ Non-view file validation failed"
      exit 1
    fi
```

## Troubleshooting

### Issue: Theme not found

**Solution**: Verify theme is registered:
```bash
ddev magento theme:list
ddev magento mageforge:theme:list
```

### Issue: Wrong path mapping

**Solution**: Check VendorFileMapper logic with verbose output or unit tests

### Issue: Permission denied

**Solution**: Check file permissions:
```bash
ddev exec chmod -R 775 app/design/
```

## Performance Testing

For large files or batch operations:

```bash
# Time the operation
time ddev magento mageforge:theme:copy-from-vendor \
  vendor/magento/module-catalog/view/frontend/layout/catalog_product_view.xml \
  Magento/luma \
  --dry-run

# Verify memory usage is reasonable
```

## Continuous Validation

After each deployment or environment update:

```bash
# Run automated tests
ddev magento dev:tests:run unit vendor/openforgeproject/mageforge/Test/

# Run smoke test
ddev magento mageforge:theme:copy-from-vendor \
  vendor/magento/module-theme/view/frontend/templates/page/copyright.phtml \
  Magento/luma \
  --dry-run
```
