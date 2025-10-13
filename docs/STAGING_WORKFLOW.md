# 🚀 CSIMS Staging Workflow Guide

## 🎯 **Quick Start: Get Your Free Staging Site in 5 Minutes!**

### **Step 1: Choose Your Free Hosting** 🆓
**Recommended: Railway.app** (Easiest setup)
- Go to [railway.app](https://railway.app)
- Sign up with your GitHub account  
- Click "Deploy from GitHub repo"
- Select `adedoyinyusuf/CSIMS`
- Railway automatically detects PHP and sets up MySQL!

### **Step 2: Enable Auto-Deployment** ⚡
1. In Railway dashboard, go to your project settings
2. Copy your **Railway Token**
3. Go to GitHub: https://github.com/adedoyinyusuf/CSIMS/settings/secrets/actions
4. Click "New repository secret"
5. Name: `RAILWAY_TOKEN`
6. Value: Your Railway token

**That's it! Now every push to `staging` branch auto-deploys! 🎉**

---

## 🌿 **Branch Strategy**

```
main (Production)     ← Stable, live website
  ↑
staging (Testing)     ← Auto-deploys to Railway
  ↑  
feature/new-stuff     ← Development work
```

### **Typical Workflow:**
1. **Develop:** Work on `feature/` branches
2. **Test:** Merge to `staging` → Auto-deploy to Railway 
3. **Deploy:** Merge `staging` to `main` → Deploy to production

---

## 🚂 **Railway Setup (Detailed)**

### **Why Railway?**
- ✅ **$5/month free credits** (enough for staging)
- ✅ **Zero configuration** - detects PHP automatically  
- ✅ **Built-in MySQL database**
- ✅ **GitHub integration**
- ✅ **SSL certificates**
- ✅ **Custom domains**

### **Detailed Railway Setup:**

#### **1. Create Railway Account:**
- Visit [railway.app](https://railway.app)
- Click "Login with GitHub" 
- Authorize Railway to access your repositories

#### **2. Deploy CSIMS:**
- Click "Deploy from GitHub repo"
- Find and select `adedoyinyusuf/CSIMS`
- Railway automatically:
  - Detects it's a PHP app
  - Sets up MySQL database
  - Configures environment variables
  - Deploys your code

#### **3. Configure Database:**
```bash
# Railway automatically provides these environment variables:
MYSQL_HOST=containers-us-west-xxx.railway.app
MYSQL_PORT=6543
MYSQL_DATABASE=railway  
MYSQL_USER=root
MYSQL_PASSWORD=xxxxx-xxxxx-xxxxx
```

#### **4. Get Your Staging URL:**
Railway generates a URL like: `https://csims-production-xxxx.up.railway.app`

---

## ⚡ **Automatic Deployment Process**

### **What Happens When You Push to `staging`:**

1. **🔍 GitHub Actions Triggers**
   - Detects push to `staging` branch
   - Starts deployment workflow

2. **🧪 Pre-Deployment Checks**  
   - PHP syntax validation
   - Dependency installation
   - Basic security checks

3. **🚂 Deploy to Railway**
   - Uploads your code
   - Updates database if needed
   - Restarts the application

4. **📧 Notification**
   - Success/failure email
   - Deployment summary
   - Live staging URL

### **Commands to Deploy:**
```bash
# Make changes to your code
git add .
git commit -m "New feature: student dashboard"

# Deploy to staging
git push origin staging

# 🎉 Your staging site updates automatically in ~5 minutes!
```

---

## 🧪 **Staging Environment Features**

### **What's Different in Staging:**
- 🟢 **Debug mode enabled** - See detailed errors
- 📧 **Emails logged** - No real emails sent  
- 🧪 **Test database** - Separate from production
- 🔑 **Test user accounts** - Pre-created for testing
- 🚫 **No real payments** - Sandbox mode only
- 📊 **Enhanced logging** - More detailed logs

### **Test User Accounts (Auto-created):**
```
Administrator:
- Username: admin
- Password: staging123
- Email: admin@csims-staging.com

Teacher:
- Username: teacher  
- Password: staging123
- Email: teacher@csims-staging.com

Student:
- Username: student
- Password: staging123  
- Email: student@csims-staging.com
```

---

## 🔄 **Daily Development Workflow**

### **Option A: Feature Branch → Staging → Production**
```bash
# 1. Create feature branch
git checkout -b feature/new-dashboard
# ... make changes ...
git add . && git commit -m "Add new dashboard"

# 2. Merge to staging for testing
git checkout staging
git merge feature/new-dashboard
git push origin staging
# ✅ Auto-deploys to Railway staging

# 3. Test on staging site, if good:
git checkout main  
git merge staging
git push origin main
# ✅ Deploy to production
```

### **Option B: Direct Staging Development**
```bash
# Work directly on staging branch
git checkout staging
# ... make changes ...
git add . && git commit -m "Quick fix for login"
git push origin staging
# ✅ Auto-deploys to Railway staging

# If testing passes:
git checkout main
git merge staging
git push origin main
# ✅ Deploy to production
```

---

## 📊 **Monitoring Your Staging Site**

### **Check Deployment Status:**
- **GitHub Actions:** https://github.com/adedoyinyusuf/CSIMS/actions
- **Railway Dashboard:** https://railway.app/dashboard
- **Staging URL:** Check Railway for your specific URL

### **Common URLs:**
- **Application:** `https://your-app.up.railway.app`
- **Database:** Available via Railway dashboard
- **Logs:** Railway dashboard → Deployments → View logs

### **Health Checks:**
The staging environment includes automatic health checks:
- ✅ Database connectivity
- ✅ PHP version compatibility  
- ✅ Required extensions
- ✅ File permissions
- ✅ Configuration validity

---

## 🐛 **Troubleshooting**

### **Deployment Failed?**
1. **Check GitHub Actions logs:**
   - Go to: https://github.com/adedoyinyusuf/CSIMS/actions
   - Click on the failed workflow
   - Review error messages

2. **Common Issues:**
   - **Missing RAILWAY_TOKEN:** Add it to GitHub Secrets
   - **Database connection:** Check Railway database status
   - **PHP syntax error:** Check your code for errors
   - **Missing dependencies:** Ensure composer.json is correct

### **Staging Site Not Loading?**
1. **Check Railway Dashboard:**
   - Logs tab for error messages
   - Deployments tab for status
   - Settings tab for environment variables

2. **Database Issues:**
   - Railway may need time to provision database
   - Check environment variables are set correctly

### **Need to Reset Staging Database?**
```bash
# In Railway dashboard:
# 1. Go to Database service  
# 2. Click "Data" tab
# 3. Delete and recreate tables as needed
# 4. Redeploy your application
```

---

## 🎉 **Benefits of This Setup**

### **For Development:**
- ✅ **Safe testing** - Never break production
- ✅ **Real environment** - Test with real hosting
- ✅ **Easy sharing** - Send staging URL to others
- ✅ **Database testing** - Test with real database

### **For Team Collaboration:**
- ✅ **Code reviews** - Review changes on staging first
- ✅ **Client demos** - Show progress on staging site
- ✅ **Bug reporting** - Test and report issues safely
- ✅ **Performance testing** - Test under real conditions

### **For Quality Assurance:**
- ✅ **Automated testing** - CI/CD catches issues early
- ✅ **Security scanning** - Automated security checks
- ✅ **Deployment validation** - Ensure deployments work
- ✅ **Configuration testing** - Test environment-specific settings

---

## 🚀 **Next Steps**

1. **✅ Set up Railway account and deploy**
2. **✅ Add RAILWAY_TOKEN to GitHub secrets**  
3. **✅ Push to staging branch and watch it deploy**
4. **🧪 Test your staging site thoroughly**
5. **📱 Share staging URL with team/clients**
6. **🔄 Use staging for all future testing**

**Your free staging environment is now ready! 🎉**

---

## 💰 **Cost Summary**

| Service | Cost | What You Get |
|---------|------|--------------|
| **Railway** | FREE ($5 credits/month) | PHP hosting + MySQL database |
| **GitHub Actions** | FREE | Unlimited CI/CD for public repos |
| **SSL Certificate** | FREE | Automatic HTTPS |
| **Custom Domain** | FREE | Link your own domain |
| **Total** | **$0/month** | Professional staging environment |

**Perfect for testing, development, and client demos! 🎯**