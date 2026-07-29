# Swami Dayananda Educational Cost Management System (SECMS)

A complete web application for managing the finances of **Swami Dayanandha
Educational Institutions, Manjakkudi** — budgets, sanctions, purchase
requests, purchase orders, expenses, reports, notifications and users — with a
premium Apple-inspired design (light & dark mode, animated dashboard, rotating
department showcase).

This guide assumes you have **never used PHP before**. Follow it from top to
bottom and don't skip steps. Everything is written for **Windows**.

> 🇮🇳 **Tamil la padikanuma?** Indha same guide Thanglish la
> [README2.md](README2.md) la irukku.

---

## Table of Contents

1. [What this application is made of](#1-what-this-application-is-made-of)
2. [Install XAMPP (PHP + MySQL in one installer)](#2-install-xampp-php--mysql-in-one-installer)
3. [Start the servers](#3-start-the-servers)
4. [Put the project in the right folder](#4-put-the-project-in-the-right-folder)
5. [Create the database](#5-create-the-database)
6. [Configure the application](#6-configure-the-application)
7. [Open the application and log in](#7-open-the-application-and-log-in)
8. [Change the default passwords (important!)](#8-change-the-default-passwords-important)
9. [Starting it again after a restart](#9-starting-it-again-after-a-restart)
10. [A quick tour of the application](#10-a-quick-tour-of-the-application)
11. [Troubleshooting — when something goes wrong](#11-troubleshooting--when-something-goes-wrong)
12. [Putting it on the internet (real hosting)](#12-putting-it-on-the-internet-real-hosting)
13. [Project folder structure](#13-project-folder-structure)
14. [Login accounts that come pre-installed](#14-login-accounts-that-come-pre-installed)
15. [For developers: REST API reference](#15-for-developers-rest-api-reference)

---

## 1. What this application is made of

You don't need to understand these deeply, but it helps to know the names:

| Thing | What it is | Why we need it |
| --- | --- | --- |
| **PHP** | A programming language that runs on a server. All the application logic is written in it. | Without PHP the code files are just text — nothing runs. |
| **MySQL** (we'll use **MariaDB**, which is the same thing) | A **database** — the program that permanently stores all your data (users, budgets, sanctions…) in organised tables. | Without it there is nowhere to save anything. |
| **Apache** | A **web server** — the program that receives requests from your browser and passes them to PHP. | It's what makes `http://localhost/...` work. |
| **XAMPP** | A free installer that gives you **Apache + PHP + MySQL together**, with a simple control panel. | So you install one thing instead of three. |
| **localhost** | A special address that means "this computer". `http://localhost/...` opens websites running on your own PC. | Your app will live here while you test it. |

> **In short:** install XAMPP → put the project folder inside XAMPP's web
> folder → create the database → open the site in your browser. That's the
> whole plan.

---

## 2. Install XAMPP (PHP + MySQL in one installer)

### Step 2.1 — Download

1. Open your browser and go to: **https://www.apachefriends.org**
2. Click the big **"XAMPP for Windows"** download button.
   - Pick the newest version offered (PHP **8.2 or newer** — any current
     version works with this project).
3. A file like `xampp-windows-x64-8.2.12-0-VS16-installer.exe` will download
   (about 150 MB). Wait for it to finish.

### Step 2.2 — Run the installer

1. Open your **Downloads** folder and **double-click** the installer file.
2. Windows may show *"Do you want to allow this app to make changes?"* →
   click **Yes**.
3. The installer may warn about antivirus or about UAC (User Account
   Control). Just click **OK** — this is normal.
4. Click **Next**.
5. **Select Components** screen: you can leave everything ticked, but the
   only ones you truly need are:
   - ✅ **Apache**
   - ✅ **MySQL**
   - ✅ **PHP**
   - ✅ **phpMyAdmin**
   Click **Next**.
6. **Installation folder:** keep the default **`C:\xampp`**.
   ⚠️ Do **not** change it to `C:\Program Files\...` — that folder has
   permission restrictions that cause problems.
   Click **Next**.
7. Language: pick **English**, click **Next**.
8. Untick "Learn more about Bitnami" (not needed), click **Next**, then
   **Next** again to start installing.
9. Wait for the green progress bar to finish (a few minutes).
10. On the last screen leave **"Do you want to start the Control Panel now?"**
    ticked and click **Finish**.

You now have PHP, Apache and MySQL installed. 🎉

---

## 3. Start the servers

The **XAMPP Control Panel** is the little window with Start/Stop buttons.
(If it's not open: press the **Windows key**, type `xampp`, press **Enter**.)

1. In the row that says **Apache**, click **Start**.
   - The first time, **Windows Firewall** will pop up asking for permission →
     tick both checkboxes if shown and click **"Allow access"**.
   - The word *Apache* should get a **green background** and show port
     numbers like `80, 443`.
2. In the row that says **MySQL**, click **Start**.
   - Allow it through the firewall too if asked.
   - It should also turn **green** and show port `3306`.

**Quick test that it works:**

- Open your browser and go to **http://localhost** — you should see the
  XAMPP welcome page.
- Now go to **http://localhost/phpmyadmin** — you should see **phpMyAdmin**,
  a web page for managing databases. We'll use it in Step 5.

> ❌ **Apache won't start / instantly turns red?** Jump to
> [Troubleshooting](#11-troubleshooting--when-something-goes-wrong) — usually
> another program (Skype, IIS, VMware) is squatting on port 80.

---

## 4. Put the project in the right folder

Apache only serves websites that live inside **`C:\xampp\htdocs`**. So we
copy the project there.

1. Open **File Explorer** (Windows key + E).
2. Go to the folder that contains this project (for example `D:\Dev\SDC` —
   the folder that has `README.md`, `app`, `public`, `database` inside it).
3. Go **up one level**, **right-click the `SDC` folder → Copy**.
4. Navigate to **`C:\xampp\htdocs`**.
5. **Right-click empty space → Paste.**

You should now have this structure (check it!):

```
C:\xampp\htdocs\SDC\
├── app\
├── config\
├── database\
├── public\
├── storage\
├── bootstrap.php
└── README.md
```

> ⚠️ Make sure it is `C:\xampp\htdocs\SDC\app` and **not**
> `C:\xampp\htdocs\SDC\SDC\app` (a folder inside a folder — this happens if
> you paste a copy inside another copy).

---

## 5. Create the database

The project ships with two ready-made database files:

- `database/schema.sql` — creates the database and all its empty tables
- `database/seed.sql` — fills in only what the system needs to start fresh:
  the 21 departments, the current financial year, basic settings, and **one
  administrator account** (no demo data)

We'll load them using **phpMyAdmin** (all clicking, no typing):

### Step 5.1 — Import the schema

1. In your browser open **http://localhost/phpmyadmin**
2. Click the **Import** tab in the top menu bar.
3. Under *"File to import"* click **Choose File** (or **Browse**).
4. Navigate to **`C:\xampp\htdocs\SDC\database`** and select
   **`schema.sql`**. Click **Open**.
5. Scroll to the bottom and click the **Import** button (older versions call
   it **Go**).
6. You should see a green message like *"Import has been successfully
   finished"*. In the left sidebar a new database called **`secms`** appears.

### Step 5.2 — Import the starting data

1. Click the **Import** tab again (top menu).
2. Click **Choose File**, and this time select **`seed.sql`** from the same
   folder. Click **Open**.
3. Click **Import** (or **Go**) at the bottom.
4. Green success message again → done.

**Quick check:** in the left sidebar click **secms** → click the table
**departments** → you should see 21 rows (College, School, Farming, Temple,
Goshala…). If you do, your database is perfect.

> 💡 **Prefer the command line?** (optional, same result):
> ```
> cd C:\xampp\mysql\bin
> mysql -u root < C:\xampp\htdocs\SDC\database\schema.sql
> mysql -u root secms < C:\xampp\htdocs\SDC\database\seed.sql
> ```

---

## 6. Configure the application

The app needs to know two things: **how to reach the database** and **what
its web address is**. Both live in one small file you'll create now.

1. In File Explorer open **`C:\xampp\htdocs\SDC\config`**.
2. You'll see a file called **`database.php.example`**. **Copy** it
   (right-click → Copy, then right-click → Paste in the same folder).
3. Rename the copy to exactly: **`database.php`**
   - If Windows hides file extensions and you're not sure: in File Explorer
     click **View → Show → File name extensions** so you can see the full
     names.
4. **Right-click `database.php` → Open with → Notepad** and make it look
   exactly like this:

   ```php
   <?php

   return [
       'host'     => '127.0.0.1',
       'port'     => 3306,
       'database' => 'secms',
       'username' => 'root',
       'password' => '',
       'base_url' => '/SDC/public',
   ];
   ```

   What each line means:
   - `host` / `port` — where MySQL is running. XAMPP runs it on your own PC,
     so leave these as they are.
   - `database` — the name of the database you imported (`secms`).
   - `username` / `password` — XAMPP's MySQL account is `root` with an
     **empty password** by default, so leave the password as `''`.
   - `base_url` — the path part of the site's address. Because the project
     sits in the `SDC` folder inside `htdocs` and its entry point is the
     `public` folder, the address path is `/SDC/public`. **This line is the
     one beginners forget — don't skip it.**

5. **Save** the file (Ctrl + S) and close Notepad.

---

## 7. Open the application and log in

1. Make sure **Apache** and **MySQL** are both green in the XAMPP Control
   Panel.
2. Open your browser and go to:

   ### 👉 http://localhost/SDC/public

3. You should see the **Sign in** page with the golden institution logo.
4. Log in with the administrator account:

   | Field | Value |
   | --- | --- |
   | Email | `admin@sdc.edu.in` |
   | Password | `Admin@123` |

5. You'll land on the **Dashboard** — animated statistics, charts, and the
   rotating circle of all 21 departments. Congratulations, it's running! 🎉

   Everything shows **₹0.00** right now — that's correct: the system starts
   completely fresh. You create everything yourself (see the
   [first-steps checklist](#10-a-quick-tour-of-the-application)).

---

## 8. Change the default passwords (important!)

Everyone who reads this README knows the default password, so change it
immediately:

1. Log in as the administrator.
2. Click your name (top-right corner) → **Profile & Settings**.
3. In the **Change Password** box: type the current password
   (`Admin@123`), then your new password twice → click **Change Password**.

There are no other accounts — the administrator is the only user until you
create more in **User Management**.

---

## 9. Starting it again after a restart

XAMPP does **not** start automatically when Windows boots. After every
restart:

1. Press **Windows key**, type `xampp`, press **Enter** (opens the Control
   Panel).
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Open **http://localhost/SDC/public** in your browser.

That's all — your data is safe in the database between restarts.

> 💡 Optional: in the XAMPP Control Panel click the **red X** icons in the
> "Service" column next to Apache and MySQL to install them as Windows
> services — then they start automatically with Windows.

---

## 10. A quick tour of the application

The system starts **completely empty** — only the administrator account, the
21 departments and the current financial year exist. Recommended first steps,
in order:

1. **Settings** → change the admin password (Section 8).
2. **User Management** → create accounts for your staff (Principal,
   Accounts, Department Heads, Viewers).
3. **Financial Year Management** → confirm the active financial year is the
   one you want (2026-27 comes pre-created and active).
4. **Budget Allocation** → allocate a budget to each department, then
   approve them.
5. From then on the daily flow is: department heads raise **Purchase
   Requests** → you approve → Accounts creates **Purchase Orders** → marking
   them *paid* records expenses automatically → **Reports** show everything.

| Sidebar item | What you do there |
| --- | --- |
| **Dashboard** | See totals, charts, alerts, and the rotating department showcase. Hover a department card to pause it and see details; click it to open that department's budget. |
| **Budget Allocation** | Give each department a yearly budget. The Principal/Administrator approves it. Watch the utilization bar fill as money is spent. |
| **Sanction Amount** | Record officially sanctioned amounts. Each one automatically gets a permanent number like `COL-2026-001` (department code + year + counter). The printer icon makes a formal printable sanction order. |
| **Purchase Request** | Department heads ask permission to buy something. Flow: create (draft) → submit → approve/reject. **If the amount is more than the department's remaining budget, submission is blocked automatically** and the administrator is notified. |
| **Purchase Order** | Accounts creates the official order to a vendor: line items, GST %, invoice number. Move it draft → issued → received → **paid**. Marking it *paid* automatically records the expense and reduces the department's remaining budget. |
| **Reports** | Monthly / quarterly / half-yearly / annual / budget-vs-actual and more, each with a chart and **CSV / Excel / PDF** export buttons. |
| **Financial Year Management** | Create next year's financial year (e.g. 2026-27) and activate it when the new year starts. |
| **Notifications** | Everything that needs your attention. The bell in the header updates every 30 seconds. |
| **User Management** | Create staff accounts and give each a role (see [roles](#14-login-accounts-that-come-pre-installed)). |
| **Audit Logs** | A permanent record of who did what and when — every create, edit, delete, approval and login. |
| **Settings** | Your profile, password, Light/Dark theme, notification preferences. |

The **moon/sun icon** in the header switches dark mode. The **search box**
filters whatever table is on the screen. On the printable pages (sanction /
purchase order), use the browser's **Print → Save as PDF** to make a PDF.

---

## 11. Troubleshooting — when something goes wrong

### "Apache" won't start in XAMPP (turns red / stops immediately)

Something else is using **port 80**. Most common culprits: Skype, IIS,
VMware, or another web server.

**Easiest fix — move Apache to port 8080:**

1. In the XAMPP Control Panel, on the Apache row, click **Config →
   Apache (httpd.conf)**. It opens in Notepad.
2. Press **Ctrl + H** (replace). Replace `Listen 80` with `Listen 8080`.
   Also replace `ServerName localhost:80` with `ServerName localhost:8080`.
3. Save (Ctrl + S), close Notepad, click **Start** again.
4. From now on add `:8080` to every address:
   **http://localhost:8080/SDC/public** and
   **http://localhost:8080/phpmyadmin**

### "MySQL" won't start

- Another MySQL/MariaDB is already running. Press **Ctrl + Shift + Esc**
  (Task Manager) → **Details** tab → look for `mysqld.exe` → right-click →
  **End task**, then click Start in XAMPP again.
- If it still fails, click **Config → my.ini** on the MySQL row and change
  `port=3306` to `port=3307` — and then also change `'port' => 3306` to
  `'port' => 3307` in `C:\xampp\htdocs\SDC\config\database.php`.

### The page says **"Database connection failed"**

One of these is true:

- MySQL is not running → start it in the XAMPP Control Panel.
- You didn't import the database → do [Step 5](#5-create-the-database).
- Wrong name/username/password in `config/database.php` → re-check
  [Step 6](#6-configure-the-application) (XAMPP default is user `root`,
  password empty).

### I see a white/blank page or "404 Not Found"

- Check the address — it must be exactly
  **http://localhost/SDC/public** (with `/public` at the end).
- Check the folder is really `C:\xampp\htdocs\SDC\public\index.php`
  (not nested twice, see Step 4).
- Check `base_url` in `config/database.php` is `'/SDC/public'`.

### The page shows raw PHP code as text

You opened the file directly (address starts with `file:///C:/...`).
PHP only runs through the web server — always use an address that starts
with `http://localhost/...`.

### Styles/icons look broken (plain text page)

The design libraries (Bootstrap, icons, charts) load from the internet
(CDN). Make sure the computer has an internet connection the first time you
open the app.

### "Invalid or missing CSRF token" when saving

Your login session expired (30 minutes of inactivity). Refresh the page,
log in again, and retry.

### Login says "Invalid credentials"

- Type the email exactly: `admin@sdc.edu.in` and the password exactly:
  `Admin@123` — that's a capital `A`, then `dmin`, then the `@` symbol,
  then `123`. Passwords are case-sensitive.
- If you never imported `seed.sql` there are no users at all → do
  [Step 5.2](#step-52--import-the-starting-data).

### Where are the error logs?

- Application errors: `C:\xampp\htdocs\SDC\storage\logs\php-error.log`
- Apache errors: `C:\xampp\apache\logs\error.log`
- Open them in Notepad and read the last lines — the message usually says
  exactly what's wrong.

### I want to start over with a clean database

Re-import `schema.sql` then `seed.sql` (Step 5). The schema file wipes and
recreates everything.

---

## 12. Putting it on the internet (real hosting)

When you're ready for other people (staff on other computers) to use it, you
have two options.

### Option A — Keep it on this PC, share on your local network (free, office use)

If everyone is in the same building/network:

1. Find your PC's IP address: press **Windows key**, type `cmd`, Enter, then
   type `ipconfig` and press Enter. Look for **IPv4 Address**, e.g.
   `192.168.1.25`.
2. Allow Apache through the firewall (you already did during Step 3).
3. On any other device on the same Wi-Fi/network, open:
   `http://192.168.1.25/SDC/public`
4. Keep the XAMPP PC switched on whenever people need the app.

### Option B — Rent web hosting (works from anywhere)

Buy any cheap **shared hosting** plan that supports **PHP 8.1+ and MySQL**
(almost all do — e.g. Hostinger, HostGator, GoDaddy, MilesWeb; typically
₹100–₹250/month). Then:

1. **Create the database** — in your hosting control panel (usually
   **cPanel**) open **MySQL Databases**:
   - Create a database (the host will prefix it, e.g. `youruser_secms`).
   - Create a database **user** with a strong password.
   - **Add the user to the database** with *All Privileges*.
   - Write down: database name, username, password.
2. **Import the data** — open **phpMyAdmin** in cPanel, click your new
   database, click **Import**, upload `database/schema.sql`.
   ⚠️ If the host doesn't allow `CREATE DATABASE`, first open
   `schema.sql` in Notepad and delete the two lines starting with
   `CREATE DATABASE` and `USE secms;`, and make sure your new database is
   selected in the left sidebar before importing. Then import `seed.sql`
   the same way.
3. **Upload the files** — in cPanel open **File Manager**:
   - Zip your local `SDC` folder (right-click → *Compress to ZIP*), upload
     the zip, then use *Extract* in File Manager.
   - Best practice: point the domain's **document root** to the `public`
     folder (cPanel → Domains → change document root to
     `/home/youruser/SDC/public`). Then `base_url` stays `''`.
   - If you can't change the document root, upload `SDC` inside
     `public_html` and set `'base_url' => '/SDC/public'` — the site address
     becomes `https://yourdomain.com/SDC/public`.
4. **Configure** — edit `config/database.php` on the server with the
   database name, username and password from step 1.
5. **Turn off debug messages** — in `config/config.php` change
   `'debug' => true,` to `'debug' => false,` (so visitors never see
   technical error details).
6. **Use HTTPS** — enable the free SSL/Let's Encrypt certificate in cPanel
   (usually one click), and always use the `https://` address.
7. **Change every default password** (Section 8) — this is now on the
   public internet.

---

## 13. Project folder structure

```
SDC/
├── public/              ← the ONLY folder the browser touches
│   ├── index.php        ← entry point; every request starts here
│   ├── .htaccess        ← Apache rule that routes all URLs to index.php
│   └── assets/          ← CSS, JavaScript, logo image
├── app/
│   ├── Core/            ← the tiny framework: Router, Database (PDO),
│   │                       Auth (roles/permissions), CSRF, Session,
│   │                       Validator, Request, Response
│   ├── Middleware/      ← guards that run before pages (login required,
│   │                       permission required, CSRF check)
│   ├── Controllers/     ← one file per module; receives requests,
│   │                       talks to models, returns pages or JSON
│   ├── Models/          ← all database queries (User, Budget, Sanction…)
│   ├── Services/        ← shared logic: document numbering, notifications,
│   │                       audit logging, report building, file uploads
│   ├── Views/           ← the HTML pages (layouts, dashboard, modules,
│   │                       printable documents)
│   ├── Helpers/         ← small global functions (money formatting, etc.)
│   └── routes.php       ← the list of every URL the app answers to
├── config/
│   ├── config.php       ← main settings (app name, debug, uploads)
│   └── database.php     ← YOUR local settings (you created this in Step 6;
│                           never share it or upload it to GitHub)
├── database/
│   ├── schema.sql       ← creates all tables
│   └── seed.sql         ← sample data + default users
└── storage/
    ├── logs/            ← error log files
    └── uploads/         ← files attached to purchase requests
```

---

## 14. Login accounts that come pre-installed

Only **one** account exists after a fresh install:

| Email | Password | Role |
| --- | --- | --- |
| `admin@sdc.edu.in` | `Admin@123` | **Administrator** |

Change the password right after the first login (Section 8), then create the
rest of your team in **User Management**, choosing one of these roles for
each person:

| Role | What the role can do |
| --- | --- |
| **Administrator** | Everything: users, budgets, sanctions, deletes, settings |
| **Principal** | See all departments, approve sanctions/budgets/requests, reports — cannot delete records |
| **Accounts Department** | Manage budgets, create/verify sanctions, process purchase orders, reports |
| **Department Head** | Only their own department: create purchase requests, view its budget/sanctions/reports (must be assigned a department) |
| **Viewer** | Read-only everywhere |

---

## 15. For developers: REST API reference

<details>
<summary>Click to expand (you don't need this to use the app)</summary>

All endpoints return `{success, message, data, meta}`. Log in first via
`POST /api/login`; send the `X-CSRF-Token` header (from the
`<meta name="csrf-token">` tag) on every POST/PUT/DELETE. List endpoints
accept `?page=&per_page=&search=`.

```
POST   /api/login                        {email, password}
GET    /api/dashboard
GET    /api/departments

GET    /api/budget                       ?financial_year_id=&department_id=&approval_status=
POST   /api/budget                       {department_id, financial_year_id, allocated_amount, remarks}
PUT    /api/budget/{id}
DELETE /api/budget/{id}
POST   /api/budget/{id}/approve          {decision: approved|rejected}
GET    /api/budget/export?format=csv|excel

GET    /api/sanctions                    ?status=&department_id=&financial_year_id=
POST   /api/sanctions                    {department_id, amount, purpose, remarks}
PUT    /api/sanctions/{id}
DELETE /api/sanctions/{id}
POST   /api/sanctions/{id}/verify | /approve | /reject
GET    /api/sanctions/export?format=csv|excel

GET    /api/purchase-requests            ?status=…
POST   /api/purchase-requests            JSON or multipart {title, amount, description, remarks, attachment}
PUT    /api/purchase-requests/{id}
DELETE /api/purchase-requests/{id}
POST   /api/purchase-requests/{id}/submit | /approve | /reject
GET    /api/purchase-requests/export?format=csv|excel

GET    /api/purchase-orders              ?status=…
POST   /api/purchase-orders              {department_id, vendor_name, gst_percent, items:[{item_name, quantity, unit_price}…]}
GET    /api/purchase-orders/{id}         includes items[] + history[]
PUT    /api/purchase-orders/{id}
DELETE /api/purchase-orders/{id}
POST   /api/purchase-orders/{id}/status  {status: issued|received|paid|cancelled}
GET    /api/purchase-orders/export?format=csv|excel

GET    /api/reports?type=monthly|quarterly|half_yearly|annual|budget_vs_actual|department|sanction|purchase|expense_analysis
GET    /api/reports/export?type=…&format=csv|excel

GET    /api/financial-years
POST   /api/financial-years              {label, year_code, start_date, end_date}
PUT    /api/financial-years/{id}
POST   /api/financial-years/{id}/activate

GET    /api/notifications?limit=&unread=1
POST   /api/notifications/{id}/read
POST   /api/notifications/read-all

GET    /api/users   POST /api/users   PUT/DELETE /api/users/{id}
POST   /api/users/{id}/reset-password | /toggle
GET    /api/users/{id}/login-history
GET    /api/audit-logs

POST   /api/settings/profile | /password | /preferences
```

**Security built in:** bcrypt password hashing, automatic CSRF verification
on all writes, prepared statements everywhere (SQL-injection safe), output
escaping (XSS safe), HttpOnly/SameSite session cookies with 30-minute idle
timeout, role-based permission middleware with department scoping, upload
whitelist stored outside the web root, and a full audit trail.

</details>
