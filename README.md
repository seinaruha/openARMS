# openARMS

**Open-source Aid Resource Management System**

A professional inventory management system for shelters, designed for tracking donations, supplies, and personnel.

## 🚀 Features

- **Inventory Management** - Track items, quantities, and stock levels
- **Donation Tracking** - Record and manage incoming donations
- **Shelter Management** - Manage multiple shelter locations
- **Personnel System** - Staff and volunteer management
- **Supplier Database** - Vendor and supplier information
- **Movement Logging** - IN, OUT, ADJUST, and TRANSFER transactions
- **Dashboard** - Real-time overview with low-stock alerts
- **XAMPP Compatible** - Works out of the box with XAMPP stack

## 📁 Project Structure (Industry Standard)

```
openARMS/
├── public/                    # Web root (XAMPP htdocs points here)
│   ├── index.php             # Entry point / redirect
│   ├── login.php             # Authentication API endpoint
│   ├── login.html            # Login page
│   ├── .htaccess             # Apache configuration
│   └── assets/
│       ├── css/style.css     # Main stylesheet
│       ├── js/app.js         # JavaScript functionality
│       └── images/           # Logos and icons
│           ├── openARMS.png
│           └── openARMS.svg
├── pages/                    # Application pages
│   ├── dashboard.php         # Main dashboard
│   ├── inventory.php         # Item management
│   ├── donations.php         # Donation records
│   ├── shelters.php          # Shelter management
│   ├── personnel.php         # Staff management
│   ├── suppliers.php         # Supplier database
│   └── inventory_movements.php  # Stock movements
├── src/                      # Source code (not web-accessible)
│   ├── config/
│   │   └── database.php      # DB configuration & connection
│   └── includes/
│       ├── header.php        # HTML header + navigation
│       ├── footer.php        # HTML footer
│       └── functions.php     # Helper functions
├── database/
│   └── schema.sql            # Database schema
├── .env.example              # Environment variables template
└── README.md                 # This file
```

## ⚡ Quick Start with XAMPP (5 Minutes)

Get openARMS running locally in under 5 minutes using XAMPP.

### 📥 Prerequisites

1. **Download & Install XAMPP** (if not already installed)
   - Download from: https://www.apachefriends.org/download.html
   - Install with default settings (typically `C:\xampp`)
   - During installation, **check both Apache and MySQL** components

2. **Clone/Download openARMS**
   ```bash
   git clone https://github.com/nateponds/openARMS.git
   cd openARMS
   ```

---

### 🔧 Step-by-Step Startup Instructions

#### STEP 1: Launch XAMPP Control Panel

```
🖥️ Windows:
   → Start Menu → XAMPP → XAMPP Control Panel
   
💻 macOS:
   → Applications → XAMPP → manager-osx.app
```

**You should see the XAMPP Control Panel window with:**
- ✅ Apache (row with Start/Stop buttons)
- ✅ MySQL (row with Start/Stop buttons)

---

#### STEP 2: Start Apache Web Server

1. In XAMPP Control Panel, find the **Apache** row
2. Click the **"Start"** button next to Apache
3. Wait for it to turn **green** and show "Running"

✅ **Success Indicators:**
- Button changes from "Start" to "Stop"
- Status shows "Running" in green
- Port shows **80** (or your custom port)

❌ **If Apache fails to start:**
- **Port 80 already in use?** Change port:
  ```
  File → httpd.conf → Find "Listen 80"
  Change to: Listen 8080
  
  Then update .env file:
  APP_URL=http://localhost:8080
  ```
- **Skype/other apps using port 80?** Close them or change Apache port

---

#### STEP 3: Start MySQL Database Server

1. In XAMPP Control Panel, find the **MySQL** row
2. Click the **"Start"** button next to MySQL
3. Wait for it to turn **green** and show "Running"

✅ **Success Indicators:**
- Button changes from "Start" to "Stop"
- Status shows "Running" in green
- Port shows **3306**

❌ **If MySQL fails to start:**
- Check if another MySQL instance is running
- Look at error log: `mysql\data\*.err`
- Try: Stop → Start again

---

#### STEP 4: Verify XAMPP is Running

Open your browser and test:

| URL | Expected Result |
|-----|----------------|
| `http://localhost` | XAMPP dashboard page |
| `http://localhost/phpmyadmin` | phpMyAdmin database tool |

**If you see these pages, XAMPP is working correctly!** ✅

---

#### STEP 5: Deploy openARMS Files

**Option A: Copy to htdocs (Simplest)**

```bash
# Open Command Prompt/Terminal
# Copy entire project folder to XAMPP's web directory:

Windows (Command Prompt):
xcopy /E /I openARMS C:\xampp\htdocs\openARMS\

macOS/Linux Terminal:
cp -r openARMS/ /Applications/XAMPP/htdocs/openARMS/

# Result: Files now accessible at http://localhost/openARMS/
```

**Option B: Symbolic Link (Recommended for Development)**

```bash
# Creates a reference without copying files
# Changes in your project folder appear immediately

Windows (Admin Command Prompt):
mklink /D C:\xampp\htdocs\openARMS "C:\path\to\your\openARMS"

macOS/Linux Terminal:
ln -s "/path/to/your/openARMS" /Applications/XAMPP/htdocs/openARMS
```

**Option C: Virtual Host (Advanced)**

Add to `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```apache
<VirtualHost *:80>
    DocumentRoot "C:/path/to/openARMS/public"
    ServerName openarms.local
</VirtualHost>
```

Then add to `C:\Windows\System32\drivers\etc\hosts`:
```
127.0.0.1 openarms.local
```

---

#### STEP 6: Create Database

**Method A: Using phpMyAdmin (GUI - Recommended)**

1. Open browser: `http://localhost/phpmyadmin`
2. Click **"New"** in left sidebar
3. Enter database name: `openARMS_db`
4. Select collation: `utf8mb4_unicode_ci`
5. Click **"Create"**

6. Select the new `openARMS_db` database
7. Click **"Import"** tab
8. Choose file: `database/schema.sql` (from your project)
9. Click **"Go"** / **"Import"**

✅ **Success:** You should see all tables listed (Shelters, Items, Personnel, etc.)

**Method B: Using Command Line**

```bash
# Navigate to your project directory
cd path/to/openARMS

# Import schema into MySQL (XAMPP default: no password)
C:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql

# Or if you set a password:
C:\xampp\mysql\bin\mysql.exe -u root -p < database/schema.sql
```

**Method c: Using MySQL Shell**

```bash
# Start MySQL shell
C:\xampp\mysql\bin\mysql.exe -u root

# Run these commands:
CREATE DATABASE IF NOT EXISTS openARMS_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE openARMS_db;
SOURCE database/schema.sql;
EXIT;
```

---

#### STEP 7: Configure Environment Variables (.env File) ⚙️

**This step is CRITICAL** — The `.env` file tells openARMS how to connect to your database and where your application is located. Even beginners MUST complete this step!

---

##### 📋 What is a `.env` File? (Beginner Explanation)

Think of the `.env` file as a **settings file** or **configuration card** for your application. It contains:

- **Database location** — Where is MySQL running?
- **Database credentials** — Username and password to access MySQL
- **Database name** — Which database contains openARMS data?
- **Application URL** — How do users reach your application in the browser?

> 💡 **Why use .env?** Keeping settings in a separate file means you can easily change configurations without modifying code. It also keeps sensitive information (like passwords) out of your main files.

---

##### 📝 Method A: Create .env from Template (Recommended)

**Step 7.1: Locate the Example File**

In your project folder (`openARMS/`), you should see a file called `.env.example`. This is a template that shows you what settings are available.

```
openARMS/
├── .env.example    ← This is the TEMPLATE (comes with the project)
├── .env            ← This is what YOU will CREATE (doesn't exist yet)
└── ...other files
```

**Step 7.2: Copy the Template to Create Your .env File**

Open Command Prompt (Windows) or Terminal (macOS/Linux) in your project folder:

```bash
# Windows (Command Prompt):
copy .env.example .env

# Windows (PowerShell):
Copy-Item .env.example -Destination .env

# macOS / Linux Terminal:
cp .env.example .env
```

✅ **After this command:** You now have a new file named `.env` in your project folder!

---

##### ✏️ Method B: Create .env Manually (If You Don't Have .env.example)

If the `.env.example` file doesn't exist, you can create `.env` from scratch:

**Step 7.1: Open Text Editor**
- **Windows**: Notepad, Notepad++, VS Code
- **macOS**: TextEdit, VS Code
- **Any**: VS Code (recommended)

**Step 7.2: Create New File**
- File → New → Save As
- Navigate to your `openARMS/` folder
- Filename: `.env` (yes, include the dot!)
- Save as type: "All Files" (not "Text Documents")

**Step 7.3: Paste the Following Content**

Copy this EXACTLY into your new `.env` file:

```env
# ===========================================
# openARMS Environment Configuration
# ===========================================
# This file contains settings for connecting
# to your database and configuring the app.
#
# DO NOT share this file publicly! It contains
# sensitive information like passwords.
# ===========================================

# ---- Application Settings ----

# Environment mode: "development" or "production"
# - development: Shows detailed errors (use while building/testing)
# - production: Hides errors from users (use when live)
OPENARMS_ENV=development

# The URL where users access openARMS in their browser
# Examples:
#   http://localhost/openARMS          (standard XAMPP)
#   http://localhost:8080/openARMS     (if Apache uses port 8080)
#   http://openarms.local              (if using virtual host)
APP_URL=http://localhost/openARMS

# ---- Database Connection Settings ----

# Database server address
# - localhost = this computer (standard for local development)
# - 127.0.0.1 = same as localhost (alternative)
# - Use actual IP address if MySQL is on another computer
DB_HOST=localhost

# Database server port number
# - 3306 = default MySQL/MariaDB port (XAMPP standard)
# - 3307 = alternative if 3306 is already used
# - Check XAMPP Control Panel → MySQL row to see which port
DB_PORT=3306

# MySQL username
# - root = default XAMPP MySQL username (has full access)
# - Create limited users in phpMyAdmin for better security
DB_USERNAME=root

# MySQL password
# - Empty (nothing after =) = no password (XAMPP default)
# - If you set a MySQL root password, put it here
# - Example: DB_PASSWORD=mysecretpassword
DB_PASSWORD=

# Database name
# - This must match the name you created in STEP 6
# - Default: openARMS_db
# - If you named it differently during creation, update here!
DB_NAME=openARMS_db
```

**Step 7.4: Save the File**
- Press `Ctrl+S` (or `Cmd+S` on Mac) to save
- Make sure it's saved as `.env` (not `.env.txt`!)

---

##### 🔍 Understanding Each Setting (Detailed Breakdown)

| Variable | What It Does | Default Value | When to Change It |
|----------|--------------|---------------|-------------------|
| `OPENARMS_ENV` | Controls error display | `development` | Set to `production` when deploying live |
| `APP_URL` | Your app's web address | `http://localhost/openARMS` | Changed ports or using custom domain |
| `DB_HOST` | Where MySQL lives | `localhost` | MySQL on different computer |
| `DB_PORT` | MySQL's door number | `3306` | Port conflict (see Troubleshooting) |
| `DB_USERNAME` | Who connects to MySQL | `root` | Created specific database user |
| `DB_PASSWORD` | Password for MySQL user | *(empty)* | You set a root password |
| `DB_NAME` | Which database to use | `openARMS_db` | Named database differently |

---

##### ✅ Step 7.5: Verify Your .env File

**Check that your `.env` file exists and looks correct:**

```bash
# In your project folder, list files to see .env:
dir .env          # Windows
ls -la .env       # macOS/Linux
```

**Your `.env` file should contain at minimum:**
```env
OPENARMS_ENV=development
APP_URL=http://localhost/openARMS
DB_HOST=localhost
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=
DB_NAME=openARMS_db
```

---

##### 🛠️ Common .env Scenarios & Configurations

**Scenario 1: Fresh XAMPP Installation (Most Beginners)**

You just installed XAMPP with all defaults. Use these exact values:
```env
OPENARMS_ENV=development
APP_URL=http://localhost/openARMS
DB_HOST=localhost
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=
DB_NAME=openARMS_db
```
*No changes needed! These are the defaults.*

---

**Scenario 2: You Changed Apache to Port 8080**

If port 80 was occupied (by Skype, IIS, etc.) and you changed Apache:
```env
# ONLY change APP_URL - everything else stays the same!
APP_URL=http://localhost:8080/openARMS
```

---

**Scenario 3: You Set a MySQL Root Password**

If you added security by setting a password for MySQL root user:
```env
# ONLY change DB_PASSWORD - add your password after the =
DB_PASSWORD=your_password_here
```

> ⚠️ **Security Note:** For local development, leaving password empty is fine. Never deploy to internet without setting strong passwords!

---

**Scenario 4: Using Custom Database Name**

If you created your database with a different name in phpMyAdmin:
```env
# Change DB_NAME to match exactly what you created
DB_NAME=my_custom_database_name
```

---

**Scenario 5: MySQL Running on Different Port**

If you see error "Can't connect to MySQL server on port 3306":
```env
# Change to whatever port MySQL is actually using
DB_PORT=3307
```

To find your MySQL port:
1. Open XAMPP Control Panel
2. Look at MySQL row
3. The port number is displayed there (usually `3306`)
4. Or check: `C:\xampp\mysql\bin\my.ini` → search for `port=`

---

##### 🐛 .env Troubleshooting

| Problem | Cause | Solution |
|---------|-------|----------|
| **"File not found" when opening .env** | File doesn't exist | Run copy command again (Method A) |
| **".env" has ".txt" extension** | Text editor added extension | Rename file, save as "All Files" type |
| **Database connection refused** | Wrong DB_HOST/PORT | Verify XAMPP is running, check port numbers |
| **Access denied for user** | Wrong credentials | Check DB_USERNAME and DB_PASSWORD |
| **Unknown database 'openARMS_db'** | Database not created | Go back to STEP 6 and create database first |
| **Changes to .env not working** | Old cached config | Restart Apache in XAMPP Control Panel |

---

##### 💡 Pro Tips for .env Management

1. **Never commit .env to Git** — It's already in `.gitignore` (contains secrets!)
2. **One environment per computer** — Development .env differs from Production .env
3. **Comment your changes** — Add notes in .env explaining why you changed something
4. **Restart after changes** — After editing .env, restart Apache for changes to take effect
5. **Keep .env.example updated** — When adding new variables, update the template too

---

#### STEP 8: Access Application! 🎉

**Open your browser and navigate to:**

```
http://localhost/openARMS/login.html
```

**Or if you're using custom ports:**
```
http://localhost:8080/openARMS/login.html
```

### ✅ Login Credentials

After importing `schema.sql`, you can use:

| Username | Password | Role |
|----------|----------|------|
| `admin` | `admin123` | Administrator |
| `staff` | `staff123` | Staff User |

*(These are sample accounts for testing. Change immediately in production!)*

---

### 🚀 You're Running! What's Next?

Once logged in, you'll see the **Dashboard** with:

1. **Statistics Cards** - Total items, shelters, personnel, donations
2. **Recent Movements** - Latest inventory transactions
3. **Low Stock Alerts** - Items below minimum threshold
4. **Quick Actions** - Add items, record donations, manage movements

**Navigate using the top menu bar:**
- 📦 **Inventory** - Manage items and stock levels
- ❤️ **Donations** - Record incoming donations
- 🏠 **Shelters** - Manage shelter locations
- 👥 **Personnel** - Staff management
- 🚚 **Suppliers** - Vendor database
- 🔄 **Movements** - Stock IN/OUT/TRANSFER logs

---

## 🛠️ Advanced XAMPP Configuration

### Changing Default Ports

**If port 80 or 3306 is already in use:**

**Change Apache Port:**
1. Edit `C:\xampp\apache\conf\httpd.conf`
2. Find: `Listen 80`
3. Change to: `Listen 8080`
4. Restart Apache in XAMPP Control Panel

**Change MySQL Port:**
1. Edit `C:\xampp\mysql\bin\my.ini`
2. Find: `port=3306`
3. Change to: `port=3307`
4. Restart MySQL in XAMPP Control Panel

**Update your `.env` file:**
```env
APP_URL=http://localhost:8080
DB_HOST=localhost
DB_PORT=3307
```

### Setting Up Virtual Host (Professional Setup)

For cleaner URLs like `http://openarms.local` instead of `http://localhost/openARMS`:

1. **Edit hosts file** (`C:\Windows\System32\drivers\etc\hosts` as Administrator):
   ```
   127.0.0.1 openarms.local
   ```

2. **Create virtual host config** (`C:\xampp\apache\conf\extra\httpd-vhosts.conf`):
   ```apache
   <VirtualHost *:80>
       DocumentRoot "C:/Projects/openARMS/public"
       ServerName openarms.local
       
       <Directory "C:/Projects/openARMS/public">
           Options Indexes FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

3. **Restart Apache** in XAMPP Control Panel

4. **Access**: `http://openarms.local`

### XAMPP Autostart (Optional)

To automatically start Apache + MySQL when Windows boots:

1. In XAMPP Control Panel, click **"Config"** (top-right)
2. Check **"Service Module"** for Apache and/or MySQL
3. Click **"Install"** for each service
4. Services will now auto-start on boot

⚠️ **Note:** This uses system resources even when not developing. Use only if you frequently use XAMPP.

---

## 🔧 Configuration Reference

### XAMPP Port Settings (Default)
| Service | Default Port | Config File |
|---------|-------------|-------------|
| Apache HTTP | 80 | `xampp/apache/conf/httpd.conf` |
| Apache HTTPS | 443 | `xampp/apache/conf/httpd.conf` |
| MySQL | 3306 | `xampp/mysql/bin/my.ini` |
| phpMyAdmin | 80 (via Apache) | N/A |

### Environment Variables
Copy `.env.example` to `.env` and configure:

```env
OPENARMS_ENV=development    # development | production
DB_HOST=localhost           # Database host
DB_PORT=3306               # MySQL port
DB_USERNAME=root            # MySQL username
DB_PASSWORD=                # MySQL password (empty for XAMPP default)
DB_NAME=openARMS_db         # Database name
APP_URL=http://localhost    # Application URL
```

## 🔧 Configuration

### XAMPP Port Settings (Default)
| Service | Default Port | Config File |
|---------|-------------|-------------|
| Apache | 80 | `xampp/apache/conf/httpd.conf` |
| MySQL | 3306 | `xampp/mysql/bin/my.ini` |

If you're using custom ports, update `.env`:
```env
APP_URL=http://localhost:8080
DB_PORT=3307
```

### Environment Variables
Copy `.env.example` to `.env` and configure:

```env
OPENARMS_ENV=development    # development | production
DB_HOST=localhost           # Database host
DB_PORT=3306               # MySQL port
DB_USERNAME=root            # MySQL username
DB_PASSWORD=                # MySQL password (empty for XAMPP default)
DB_NAME=openARMS_db         # Database name
APP_URL=http://localhost    # Application URL
```

## 🔒 Security Notes

**For Development:** Current setup is suitable for local development.

**For Production Deployment:**
1. Change default passwords immediately
2. Enable HTTPS
3. Move `.env` outside web root
4. Set `OPENARMS_ENV=production`
5. Implement password hashing (`password_hash()`)
6. Add CSRF protection (functions included, enable as needed)
7. Restrict directory access via `.htaccess`
8. Regular backups of database

## 📊 Database Schema

Core tables:
- **Shelters** - Shelter locations and info
- **Items** - Inventory items with quantities
- **Suppliers** - Vendor/supplier database
- **Personnel** - Staff and user accounts
- **Donations** - Donation headers
- **DonationLines** - Donation line items
- **InventoryLogs** - Transaction history
- **ShelterInventory** - Minimum stock settings

## 🌐 Browser Compatibility

Tested and working on:
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

Mobile responsive design included.

## 🐛 Troubleshooting

### "Database connection failed"
- Ensure MySQL is running in XAMPP
- Check credentials in `.env` or `src/config/database.php`
- Verify database `openARMS_db` exists

### Blank pages / errors
- Check Apache error logs: `xampp/apache/logs/error.log`
- Ensure PHP errors are enabled (development mode)
- Verify file permissions

### Login not working
- Import sample data from `schema.sql` for test accounts
- Check browser console for JavaScript errors
- Verify API endpoint is accessible

### Port conflicts
- Change Apache port in `httpd.conf`: `Listen 80` → `Listen 8080`
- Update `APP_URL` in `.env` accordingly

## 🤝 Contributing

1. Fork the repository
2. Create feature branch: `git checkout -b feature-name`
3. Commit changes: `git commit -m 'Add feature'`
4. Push to branch: `git push origin feature-name`
5. Submit Pull Request

## 📝 License

MIT License - see LICENSE file for details.

## 👥 Support

For issues and feature requests, please use GitHub Issues.

---

**Built with ❤️ for disaster relief and shelter management**
