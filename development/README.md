# Development & Testing Files

This folder contains development, testing, and debugging files that should **NOT** be deployed to production.

## Contents

### Debug Files
- `check_admin.php` - Admin functionality verification
- `check_loans.php` - Loan system checks
- `check_password.php` - Password hashing verification
- `debug_loans.php` - Loan debugging utility

### Test Files
- `test_loans_display.php` - Test loan display functionality
- `test_login.php` - Login system testing
- `test_section_visibility.php` - UI section visibility tests

## Usage

These files are for development use only:
- ✅ Testing specific features
- ✅ Debugging issues
- ✅ Verifying database states
- ✅ Manual QA

## Important

⚠️ **DO NOT deploy this folder to production!**

### Recommended .gitignore Entry
```
# Development and testing files
/development/
```

## Note

For automated testing, use the `tests/` directory with PHPUnit instead of these manual test files.

Once PHPUnit is set up, these manual test files can be migrated to proper unit/integration tests.

---

**Moved to development/:** December 24, 2025  
**Reason:** Cleanup for production readiness
