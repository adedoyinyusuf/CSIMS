# 🚀 CSIMS GitHub Actions Deployment Guide

## 📋 Overview

This guide explains how to set up and use the GitHub Actions workflows created specifically for your CSIMS Cooperative Society Management System. These workflows provide automated testing, security scanning, and deployment capabilities.

## 🔧 GitHub Actions Workflows Created

### 1. 🔄 **Continuous Integration (CI)** - `ci.yml`
**Triggers:** Push to `main`/`develop`, Pull Requests
**Purpose:** Automated testing and validation

**Features:**
- PHP syntax validation (PHP 7.4, 8.0, 8.1, 8.2)
- Security scanning for common vulnerabilities
- Code quality checks
- Database schema validation
- Configuration validation

### 2. 🚀 **Production Deployment** - `deploy-production.yml`
**Triggers:** Push to `main`, Manual dispatch, Tags
**Purpose:** Automated deployment to production servers

**Deployment Options:**
- **Shared Hosting** (cPanel/FTP)
- **VPS/Cloud Server** (SSH deployment)
- **Staging Environment**

### 3. 🛡️ **Security Monitoring** - `security-scan.yml`
**Triggers:** Daily schedule (2 AM UTC), Push, Manual dispatch
**Purpose:** Continuous security monitoring

**Security Checks:**
- Dependency vulnerability scanning
- Code security analysis (SQL injection, XSS, etc.)
- Configuration security audit
- Web security headers validation
- SSL/TLS configuration check

## ⚙️ Setup Instructions

### Step 1: Repository Secrets Configuration

Go to your GitHub repository → Settings → Secrets and variables → Actions

#### For Shared Hosting Deployment:
```
FTP_HOST=your-ftp-host.com
FTP_USER=your-ftp-username
FTP_PASSWORD=your-ftp-password
FTP_SERVER_DIR=/public_html/
SITE_URL=https://your-domain.com
```

#### For VPS/Cloud Server Deployment:
```
SERVER_HOST=your-server-ip-or-domain
SERVER_USER=your-ssh-username
SSH_PRIVATE_KEY=your-ssh-private-key
DEPLOY_PATH=/var/www/html/csims
SITE_URL=https://your-domain.com
```

#### For Staging Environment (optional):
```
STAGING_FTP_HOST=staging-server.com
STAGING_FTP_USER=staging-username
STAGING_FTP_PASSWORD=staging-password
```

### Step 2: Enable GitHub Actions

1. Go to your repository → Actions tab
2. Click "Enable workflows" if prompted
3. The workflows will be automatically available

### Step 3: Set Up Repository Environments (Recommended)

1. Go to Settings → Environments
2. Create environments: `production` and `staging`
3. Add protection rules for production:
   - Required reviewers
   - Restrict to main branch
   - Wait timer (optional)

## 🎮 How to Use the Workflows

### Automatic Triggers

#### Continuous Integration:
- **Automatic:** Runs on every push to `main` or `develop`
- **Automatic:** Runs on pull requests to `main`
- Provides immediate feedback on code quality and security

#### Security Scanning:
- **Automatic:** Runs daily at 2 AM UTC
- **Automatic:** Runs on pushes to monitor new code
- Sends alerts if critical vulnerabilities are found

#### Production Deployment:
- **Automatic:** Deploys when code is pushed to `main` branch
- **Automatic:** Deploys when creating version tags (v1.0.0, etc.)

### Manual Triggers

#### Deploy Specific Environment:
1. Go to Actions → Deploy to Production
2. Click "Run workflow"
3. Select environment (production/staging)
4. Click "Run workflow"

#### Run Security Scan:
1. Go to Actions → Security Monitoring
2. Click "Run workflow"
3. Select scan type (full/quick/dependency-only)
4. Click "Run workflow"

## 🔐 Security Secrets Management

### SSH Private Key Setup (for VPS deployment):

1. **Generate SSH key pair:**
```bash
ssh-keygen -t rsa -b 4096 -C "github-actions@your-domain.com"
```

2. **Add public key to your server:**
```bash
cat ~/.ssh/id_rsa.pub >> ~/.ssh/authorized_keys
```

3. **Add private key to GitHub secrets:**
   - Copy entire content of `~/.ssh/id_rsa`
   - Add as `SSH_PRIVATE_KEY` secret in GitHub

### FTP Credentials Security:
- Use strong, unique passwords
- Consider creating dedicated FTP accounts for deployment
- Restrict FTP access to deployment directories only

## 📊 Monitoring and Notifications

### Build Status Monitoring:
- Check Actions tab for workflow status
- Green checkmarks = successful runs
- Red X marks = failed runs requiring attention

### Security Alert Notifications:
- Critical security issues trigger error notifications
- Warnings appear for potential issues
- Daily security reports provide ongoing monitoring

### Deployment Notifications:
- Success/failure notifications for each deployment
- Health checks verify site accessibility post-deployment
- Rollback procedures available if needed

## 🛠️ Customization Options

### Modify PHP Versions:
Edit `.github/workflows/ci.yml`, line 17:
```yaml
php-version: [7.4, 8.0, 8.1, 8.2, 8.3]  # Add/remove versions
```

### Change Security Scan Schedule:
Edit `.github/workflows/security-scan.yml`, line 6:
```yaml
- cron: '0 2 * * *'  # Daily at 2 AM UTC
```

### Customize Deployment Paths:
Edit deployment workflow variables:
```yaml
FTP_SERVER_DIR: /public_html/your-app/
DEPLOY_PATH: /var/www/html/your-path/
```

### Add Custom Security Checks:
Add your own security rules in `security-scan.yml`:
```bash
# Custom security check example
if grep -r "your-security-pattern" --include="*.php" .; then
  echo "Custom security issue detected"
fi
```

## 🚨 Troubleshooting Common Issues

### Deployment Fails:
1. **Check secrets:** Ensure all required secrets are set
2. **Verify credentials:** Test FTP/SSH access manually
3. **Check permissions:** Ensure deployment user has write access
4. **Review logs:** Check Actions tab for detailed error messages

### Security Scans Fail:
1. **Review warnings:** Check for actual security issues
2. **Update code:** Fix identified vulnerabilities
3. **Whitelist false positives:** Modify scan patterns if needed

### CI/CD Pipeline Fails:
1. **PHP syntax errors:** Fix code syntax issues
2. **Missing dependencies:** Ensure all required files are committed
3. **Database issues:** Verify database configuration files

## 🎯 Best Practices

### Security:
- Regularly rotate deployment credentials
- Monitor security scan results
- Keep dependencies updated
- Use environment-specific configurations

### Deployment:
- Test in staging before production
- Maintain backup procedures
- Monitor post-deployment health checks
- Use version tags for releases

### Development:
- Use feature branches for development
- Create pull requests for code review
- Wait for CI checks before merging
- Follow semantic versioning

## 📈 Advanced Features

### Custom Deployment Environments:
Add new environments by:
1. Creating new workflow jobs
2. Adding environment-specific secrets
3. Configuring deployment logic

### Integration with External Services:
- Add Slack/Discord notifications
- Integrate with monitoring tools
- Connect with project management systems
- Set up custom webhooks

### Performance Monitoring:
- Add performance testing jobs
- Monitor deployment metrics
- Track application performance
- Set up alerts for performance degradation

## 🎉 Benefits of This Setup

### For Development:
- ✅ Automated testing on every change
- ✅ Immediate feedback on code quality
- ✅ Consistent deployment process
- ✅ Reduced manual errors

### For Security:
- ✅ Continuous security monitoring
- ✅ Early detection of vulnerabilities
- ✅ Automated compliance checking
- ✅ Security best practices enforcement

### For Operations:
- ✅ Zero-downtime deployments
- ✅ Automated backup creation
- ✅ Health monitoring
- ✅ Quick rollback capabilities

---

## 🆘 Support & Help

If you encounter issues with the GitHub Actions setup:

1. **Check the logs:** Actions tab → Failed workflow → View details
2. **Review this guide:** Ensure all setup steps are completed
3. **Test manually:** Verify credentials and access work outside GitHub
4. **Update configurations:** Modify workflows as needed for your environment

Your CSIMS project is now equipped with professional-grade CI/CD and security automation! 🚀