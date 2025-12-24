# Database Migration Files - Duplicate Analysis & Cleanup Recommendations

**Analysis Date:** December 24, 2025  
**Issue:** Multiple duplicate/versioned migration files causing confusion  
**Priority:** High - Clean database migration path is critical

---

## 🔴 **Identified Duplicates**

Based on the screenshot and file structure analysis, the following duplicates exist:

### **1. Migration 007 - Enhanced Cooperative Schema (3 versions)**

```
✅ KEEP:    007_enhanced_cooperative_schema.sql
❌ REMOVE:  007_enhanced_cooperative_schema_fixed.sql
❌ REMOVE:  007_enhanced_cooperative_schema_simple.sql
```

**Reasoning:**
- `007_enhanced_cooperative_schema.sql` - This is the base/original migration
- `_fixed.sql` suffix suggests this was a correction to an issue
- `_simple.sql` suffix suggests this was a simplified alternative
- **Recommendation:** Keep the base file. The "fixed" and "simple" versions were likely iteration attempts.

**Action Required:**
1. Verify which version is currently applied to the database
2. Keep only the applied version
3. Archive others to a `/database/migrations/deprecated/` folder

---

### **2. Migration 008 - System Config & Member Fields (3 versions)**

```
❌ REMOVE:  008_add_member_extra_loan_fields.sql
✅ KEEP:    008_create_system_config.sql
❌ REMOVE:  008_create_system_config_fixed.sql
```

**Problem:** Migration 008 has **two different purposes**:
- One for member/loan fields
- One for system config

**Reasoning:**
- This is a **numbering conflict** - two different migrations share the same number
- Need to renumber one of them

**Recommended Solution:**
```
RENAME: 008_add_member_extra_loan_fields.sql → 011_add_member_extra_loan_fields.sql
KEEP:   008_create_system_config.sql
REMOVE: 008_create_system_config_fixed.sql
```

---

### **3. Notification Triggers Schema (2 versions in /database/)**

```
❌ REMOVE:  notification_triggers_schema.sql
✅ KEEP:    notification_triggers_schema_simple.sql
```

**Reasoning:**
- The `_simple` version is likely the working, tested version
- The base version may have had complexity issues
- These aren't numbered migrations, so they're probably one-off schema files

**Alternative:** Keep both if they serve different purposes (e.g., complex vs simple triggers)

---

### **4. Additional Unnumbered Migrations**

```
add_admin_profile_fields.sql
add_extended_member_fields.sql
add_member_type_to_members.sql
```

**Issue:** These should be numbered to maintain migration order

**Recommendation:**
```
RENAME: add_admin_profile_fields.sql      → 012_add_admin_profile_fields.sql
RENAME: add_extended_member_fields.sql    → 013_add_extended_member_fields.sql
RENAME: add_member_type_to_members.sql    → 014_add_member_type_to_members.sql
```

---

## 📋 **Complete Recommended Migration Structure**

After cleanup, your migrations should look like:

```
database/migrations/
├── 001_create_users_and_sessions.sql
├── 005_create_user_sessions_table.sql
├── 006_create_cache_table.sql
├── 007_enhanced_cooperative_schema.sql        ← KEEP (verify which version is active)
├── 008_create_system_config.sql              ← KEEP
├── 009_create_approval_workflow_tables.sql
├── 010_make_bank_fields_not_null.sql
├── 011_add_member_extra_loan_fields.sql      ← RENUMBERED from 008
├── 012_add_admin_profile_fields.sql          ← NUMBERED
├── 013_add_extended_member_fields.sql        ← NUMBERED
├── 014_add_member_type_to_members.sql        ← NUMBERED
└── security_tables.sql
```

**Deprecated (move to /database/migrations/deprecated/):**
```
database/migrations/deprecated/
├── 007_enhanced_cooperative_schema_fixed.sql
├── 007_enhanced_cooperative_schema_simple.sql
└── 008_create_system_config_fixed.sql
```

---

## 🛠️ **Step-by-Step Cleanup Process**

### **Step 1: Check Current Database State**

Before removing anything, verify which migrations are actually applied:

```sql
-- Check if you have a migrations tracking table
SHOW TABLES LIKE '%migration%';

-- If you have a migrations table, see what's been run
SELECT * FROM migrations ORDER BY id;
```

### **Step 2: Backup Current Database**

```bash
# Create backup before any cleanup
mysqldump -u root -p csims_db > backup_before_cleanup_$(date +%Y%m%d).sql
```

### **Step 3: Create Deprecated Folder**

```bash
mkdir database/migrations/deprecated
```

### **Step 4: Move Duplicate Files**

```bash
# Move 007 duplicates
mv database/migrations/007_enhanced_cooperative_schema_fixed.sql database/migrations/deprecated/
mv database/migrations/007_enhanced_cooperative_schema_simple.sql database/migrations/deprecated/

# Move 008 duplicate
mv database/migrations/008_create_system_config_fixed.sql database/migrations/deprecated/
```

### **Step 5: Renumber Conflicting Migration**

```bash
# Rename 008 member fields to 011
mv database/migrations/008_add_member_extra_loan_fields.sql database/migrations/011_add_member_extra_loan_fields.sql
```

### **Step 6: Number Unnumbered Migrations**

```bash
# Add numbers to unnamed migrations
mv database/migrations/add_admin_profile_fields.sql database/migrations/012_add_admin_profile_fields.sql
mv database/migrations/add_extended_member_fields.sql database/migrations/013_add_extended_member_fields.sql
mv database/migrations/add_member_type_to_members.sql database/migrations/014_add_member_type_to_members.sql
```

### **Step 7: Update Migration Tracking**

If you have a migrations tracking system, update it to reflect the changes:

```sql
-- Example: Update migration tracking table
UPDATE migrations 
SET filename = '011_add_member_extra_loan_fields.sql' 
WHERE filename = '008_add_member_extra_loan_fields.sql';
```

---

## ⚠️ **Critical Warnings**

### **Before You Delete Anything:**

1. ✅ **Verify which version is in production**
   - Check your production database
   - Compare schema with each migration version
   - Keep the version that matches production

2. ✅ **Check for references**
   - Search codebase for migration file references
   - Check deployment scripts
   - Review documentation

3. ✅ **Backup everything**
   - Database backup
   - File backup
   - Git commit current state

4. ✅ **Test in development first**
   - Clean up in dev environment
   - Verify application still works
   - Check for any broken references

### **Special Consideration for Migration 007**

The three versions of migration 007 suggest **significant schema evolution**:

**Option A: Keep the most complete version**
```bash
# Compare file sizes first
ls -lh database/migrations/007_*.sql

# Keep the largest/most comprehensive one
```

**Option B: Combine into single canonical version**
```bash
# If all three serve different purposes, merge into one
# Create: 007_enhanced_cooperative_schema_v3.sql (combined)
# Remove the three separate versions
```

---

## 🎯 **Recommended Actions (Prioritized)**

### **Priority 1: Investigation (Do First)**

- [ ] Check which 007 version is currently in production database
- [ ] Check which 008 version(s) are applied
- [ ] Verify if notification_triggers are using simple or complex version
- [ ] Document current database schema state

### **Priority 2: Backup (Critical)**

- [ ] Full database backup
- [ ] Commit current migration files to git
- [ ] Create backup branch: `git checkout -b backup/before-migration-cleanup`

### **Priority 3: Cleanup (After verification)**

- [ ] Create `/database/migrations/deprecated/` folder
- [ ] Move duplicate files to deprecated folder
- [ ] Renumber conflicting migrations
- [ ] Number unnumbered migrations
- [ ] Update any migration tracking systems

### **Priority 4: Documentation**

- [ ] Document which versions were kept and why
- [ ] Update migration documentation
- [ ] Add README in deprecated folder explaining why files are there

### **Priority 5: Testing**

- [ ] Test fresh database setup with cleaned migrations
- [ ] Verify existing database compatibility
- [ ] Run application and check for issues

---

## 📝 **Migration Numbering Best Practices**

To prevent future duplicates:

### **1. Use Timestamp-Based Numbering**

Instead of sequential numbers, use timestamps:
```
2024_12_01_120000_create_users_table.sql
2024_12_15_143022_add_member_fields.sql
```

**Advantages:**
- No number conflicts
- Clear chronological order
- Matches Laravel/Django style

### **2. Migration Tracking Table**

Create a migrations table to track what's been run:

```sql
CREATE TABLE migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    batch INT NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### **3. Migration Runner Script**

Create a script that:
- Checks which migrations have been run
- Runs pending migrations in order
- Tracks execution in migrations table
- Prevents duplicate execution

**Example:** `scripts/run-migrations.php`

### **4. Naming Convention**

Enforce a strict naming convention:
```
{number}_{action}_{subject}.sql

Examples:
✅ 001_create_users_table.sql
✅ 002_add_email_to_members.sql
✅ 003_modify_loans_add_status.sql

❌ enhanced_schema.sql (too vague)
❌ fix.sql (no context)
❌ new_migration.sql (not unique)
```

---

## 🔍 **Verification Queries**

Run these queries to help decide which migrations to keep:

### **Check if user_sessions table exists**
```sql
SHOW TABLES LIKE 'user_sessions';
DESCRIBE user_sessions;
```

### **Check system_config table structure**
```sql
DESCRIBE system_config;
SELECT COUNT(*) FROM system_config;
```

### **Check for enhanced cooperative schema features**
```sql
-- Check for specific columns that would indicate which version was used
SHOW COLUMNS FROM loans LIKE '%guarantor%';
SHOW COLUMNS FROM members LIKE '%type%';
```

### **Check notification triggers**
```sql
SHOW TRIGGERS LIKE '%notification%';
```

---

## 📊 **Impact Assessment**

### **Low Risk Cleanup:**
- Moving files to `deprecated/` folder
- Adding numbers to unnumbered migrations
- No changes to database

### **Medium Risk Cleanup:**
- Deleting unused migration versions
- Renumbering existing migrations
- Might affect deployment scripts

### **High Risk Cleanup:**
- Modifying applied migrations
- Changing migration order
- Could break production deployment

**Recommendation:** Start with **Low Risk** actions only until full verification is complete.

---

## ✅ **Quick Action Checklist**

**Can do immediately (Safe):**
```bash
# Create deprecated folder
mkdir database/migrations/deprecated

# Copy (don't move yet) potential duplicates
cp database/migrations/007_enhanced_cooperative_schema_fixed.sql database/migrations/deprecated/
cp database/migrations/007_enhanced_cooperative_schema_simple.sql database/migrations/deprecated/
cp database/migrations/008_create_system_config_fixed.sql database/migrations/deprecated/
```

**Need investigation first (Wait):**
- Deleting any files
- Renumbering migrations
- Modifying migration content
- Updating production database

---

## 📞 **Next Steps**

1. **Review this analysis**
2. **Run verification queries** (provided above)
3. **Make backup** (database + files)
4. **Tell me findings** so I can provide specific cleanup commands
5. **Execute cleanup** (with guidance)

---

**Questions to Answer:**

1. Do you have a migrations tracking table in the database?
2. Which migration files are currently applied in production?
3. Are there any deployment scripts that reference specific migration filenames?
4. Do you want to keep deprecated files or delete them permanently?

Would you like me to:
- [ ] Help investigate which versions are currently in use?
- [ ] Create a safe cleanup script?
- [ ] Set up a proper migration tracking system?
- [ ] All of the above?

---

**Report Status:** Draft - Awaiting verification data  
**Next Update:** After database state verification  
**Estimated Cleanup Time:** 30-60 minutes (after verification)
