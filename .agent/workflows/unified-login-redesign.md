---
description: Single Unified Login - Reduced Attack Surface
---

# Single Unified Login System

## Objective
Create ONE login page that authenticates both admins and members, reducing attack surface and improving security.

## Security Benefits

### Attack Surface Reduction
- ✅ Single entry point to secure
- ✅ One set of security measures to maintain
- ✅ Unified rate limiting across all users
- ✅ Single monitoring point
- ✅ No duplicate code = fewer vulnerabilities
- ✅ Easier to audit and pen-test

### Current Issues (2 Login Pages)
- ❌ Two attack vectors
- ❌ Security measures must be duplicated
- ❌ Rate limiting separate (can bypass by switching pages)
- ❌ Monitoring split across two endpoints
- ❌ Code duplication = double maintenance

## Implementation Plan

### 1. Create Unified Login (`login.php`)
**Location**: `c:\xampp\htdocs\CSIMS\login.php`

**Features**:
- Auto-detect user type (admin or member)
- Check both tables with single form
- Unified security (CSRF, rate limiting, logging)
- Redirect to appropriate dashboard
- Premium Tailwind design

### 2. Authentication Flow
```
User enters credentials
    ↓
CSRF validation
    ↓
Rate limit check (username + IP)
    ↓
Try admin authentication first
    ↓ (if fails)
Try member authentication
    ↓ (if fails)
Log failed attempt
    ↓
Return error
```

### 3. Update All Links
- `index.php` → redirect to `login.php`
- `views/member_login.php` → redirect to `login.php`
- All navigation links point to `login.php`
- Logout redirects to `login.php`

### 4. Security Features
- ✅ CSRF protection (single token)
- ✅ Rate limiting (per username + IP combo)
- ✅ Security logging (all attempts)
- ✅ Account lockout (unified)
- ✅ 2FA support (both types)
- ✅ Session security
- ✅ Password hashing
- ✅ XSS protection

## Success Criteria
- ✅ One login page
- ✅ Handles both admin & member
- ✅ All security features working
- ✅ Correct dashboard redirects
- ✅ No broken links
- ✅ Mobile responsive
- ✅ Premium design
