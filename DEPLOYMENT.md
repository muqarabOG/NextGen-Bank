# 🚀 Deployment Guide: NextGen Bank

This project uses a hybrid architecture (**PHP** website + **Node.js** AI Service). To deploy it live, the diverse components must be hosted together.

**We recommend [Railway.app](https://railway.app/)** because it natively supports PHP, Node.js, and MySQL in a single project.

---

## 🛠️ Step 1: Prepare Your Repo
*(Already done by your Assistant)*
1.  **Codebase**: Pushed to GitHub.
2.  **Config**: `chatbot_api.php` is updated to look for `AI_PROXY_URL`.
3.  **Database**: `MASTER_DATABASE_SYNC.sql` is ready.

---

## ☁️ Step 2: Deploy to Railway (Recommended)

### 1. Create Project
1.  Sign up at [Railway.app](https://railway.app/).
2.  Click **"New Project"** -> **"Deploy from GitHub repo"**.
3.  Select `muqarabOG/NextGen-Bank`.

### 2. Configure PHP Service (The Website)
Railway will detect the `index.php` or `composer.json`.
1.  In the service settings, ensure the **Root Directory** is set to `/` (default).
2.  Railway creates a domain for you (e.g., `nextgenbank-production.up.railway.app`).

### 3. Add Database
1.  Click **"New"** -> **"Database"** -> **"MySQL"**.
2.  Click on the MySQL service -> **"Connect"** tab.
3.  Copy the connection variables (`MYSQLHOST`, `MYSQLUSER`, `MYSQLPORT`, etc.).
4.  **Important**: You need to update your PHP `db_config.php` to use these environment variables instead of `localhost`.
    *(See "Critical Upgrade" section below)*.
5.  Use a tool like **TablePlus** or **Railway's Web Query** tool to import `MASTER_DATABASE_SYNC.sql`.

### 4. Add AI Service (The Brain)
1.  Click **"New"** -> **"GitHub Repo"** again -> Select `NextGen-Bank` again.
2.  Go to **Settings** -> **Root Directory** -> Change to `/ai_service`.
3.  Go to **Variables**:
    *   Add `GROQ_API_KEY`: Paste your key (starts with `gsk_`).
    *   Add `PORT`: `3000`.
4.  Railway will give this service a unique URL (e.g., `ai-service-production.up.railway.app`).

### 5. Link the Two
1.  Go back to your **PHP Service** -> **Variables**.
2.  Add `AI_PROXY_URL`.
3.  Value: The URL of your AI service (e.g., `https://ai-service-production.up.railway.app/chat`).

---

## ⚠️ Critical Upgrade Required (Before Deploying)
Your `db_config.php` is currently hardcoded to XAMPP settings (`root`, empty password). For cloud deployment, it **must** read environment variables.

**Required Change in `includes/db_config.php`:**
```php
$servername = getenv('MYSQLHOST') ?: "localhost";
$username   = getenv('MYSQLUSER') ?: "root";
$password   = getenv('MYSQLPASSWORD') ?: "";
$dbname     = getenv('MYSQLDATABASE') ?: "nextgenbank";
$port       = getenv('MYSQLPORT') ?: 3306;

$conn = mysqli_connect($servername, $username, $password, $dbname, $port);
```
*(Your Assistant can apply this fix for you now if you wish!)* 
