# Swami Dayananda Educational Cost Management System (SECMS)
## 🇮🇳 Thanglish Setup Guide (Tamil in English letters)

Idhu **Swami Dayanandha Educational Institutions, Manjakkudi** oda finance
manage panna oru full web application — Budgets, Sanctions, Purchase
Requests, Purchase Orders, Reports, Notifications ellame idhula irukku.
Apple style premium design, Light/Dark mode, dashboard la departments
suthura (rotate aagura) animation ellam irukku.

Indha guide **PHP pathi onnume theriyadhavangalukkaga** ezhudhirukken.
Mela irundhu keela step by step follow pannunga — **edhuvum skip pannadheenga**.
Ellame **Windows** computer ku dhan.

> 💡 English la innum detail-a venumna [README.md](README.md) paarunga —
> rendum same steps dhan.

---

## Ulla Enna Irukku (Table of Contents)

1. [Indha app la enna enna irukku](#1-indha-app-la-enna-enna-irukku)
2. [XAMPP install pannuvom (PHP + MySQL onnula)](#2-xampp-install-pannuvom)
3. [Servers start pannuvom](#3-servers-start-pannuvom)
4. [Project folder ah correct place la vaikanum](#4-project-folder-ah-correct-place-la-vaikanum)
5. [Database create pannuvom](#5-database-create-pannuvom)
6. [App ah configure pannuvom](#6-app-ah-configure-pannuvom)
7. [App open panni login pannuvom](#7-app-open-panni-login-pannuvom)
8. [Default password ah maathunga (romba mukkiyam!)](#8-default-password-ah-maathunga-romba-mukkiyam)
9. [Computer restart pannina apparam eppadi start pannanum](#9-computer-restart-pannina-apparam)
10. [App ah eppadi use pannanum — chinna tour](#10-app-ah-eppadi-use-pannanum--chinna-tour)
11. [Problem vandha enna pannanum (Troubleshooting)](#11-problem-vandha-enna-pannanum-troubleshooting)
12. [Internet la host panna (real hosting)](#12-internet-la-host-panna)
13. [Login accounts list](#13-login-accounts-list)

---

## 1. Indha app la enna enna irukku

Deep ah puriya venam, aana indha names theriyanum:

| Name | Idhu enna | Edhukku venum |
| --- | --- | --- |
| **PHP** | Server la run aagura programming language. App oda full logic idhula dhan ezhudhirukku. | PHP illama code files summa text dhan — onnume run aagadhu. |
| **MySQL** | **Database** — ungaloda ella data vum (users, budgets, sanctions…) permanent ah table format la save pannura program. | Idhu illama edhuvum save aagadhu. |
| **Apache** | **Web server** — browser la irundhu vara requests ah vaangi PHP kitta kudukkura program. | `http://localhost/...` velai seiya idhu dhan kaaranam. |
| **XAMPP** | **Apache + PHP + MySQL** — moonaiyum sethu oru single installer la kudukkura free software. Simple control panel um irukku. | Moonu thing ah thani thani install panna vendam — onnu podhum. |
| **localhost** | "Indha computer" nu artham. `http://localhost/...` na unga own PC la odura website ah open pannudhu. | Test panna unga app inga dhan irukkum. |

> **Short ah sollanumna:** XAMPP install pannunga → project folder ah XAMPP
> oda web folder kulla podunga → database create pannunga → browser la site
> open pannunga. Ivlo dhan plan!

---

## 2. XAMPP install pannuvom

### Step 2.1 — Download

1. Browser open panni indha site ku ponga: **https://www.apachefriends.org**
2. **"XAMPP for Windows"** nu periya download button irukkum — click pannunga.
   - Latest version ah select pannunga (PHP **8.2 illa adhukku mela** —
     endha current version um indha project ku work aagum).
3. `xampp-windows-x64-8.2.12-0-VS16-installer.exe` madhiri oru file download
   aagum (around 150 MB). Mudiyara varaikkum wait pannunga.

### Step 2.2 — Installer ah run pannunga

1. **Downloads** folder open panni installer file ah **double-click** pannunga.
2. Windows *"Do you want to allow this app to make changes?"* nu ketta →
   **Yes** click pannunga.
3. Antivirus / UAC pathi warning vandha → **OK** click pannunga — idhu normal dhan.
4. **Next** click pannunga.
5. **Select Components** screen la ellathayum tick pannirukalam, aana
   kandippa venumnadhu idhu dhan:
   - ✅ **Apache**
   - ✅ **MySQL**
   - ✅ **PHP**
   - ✅ **phpMyAdmin**
   Apparam **Next**.
6. **Installation folder:** default **`C:\xampp`** ah appadiye vidunga.
   ⚠️ `C:\Program Files\...` ku **maatha vendam** — andha folder la
   permission problem varum.
   **Next** click pannunga.
7. Language: **English** select panni **Next**.
8. "Learn more about Bitnami" checkbox ah untick pannunga (thevai illa),
   **Next**, apparam innoru **Next** — install start aagum.
9. Green progress bar mudiyara varaikkum wait pannunga (konjam minutes aagum).
10. Last screen la **"Do you want to start the Control Panel now?"** tick
    pannirukatum — **Finish** click pannunga.

Ippo PHP, Apache, MySQL — moonum unga computer la install aagiduchu! 🎉

---

## 3. Servers start pannuvom

**XAMPP Control Panel** dhan andha Start/Stop buttons irukura chinna window.
(Adhu open aagala na: **Windows key** press panni, `xampp` nu type panni,
**Enter** adinga.)

1. **Apache** row la **Start** click pannunga.
   - First time **Windows Firewall** popup varum → checkbox ellam tick panni
     **"Allow access"** click pannunga.
   - *Apache* nu irukura idam **green** aaganum, port numbers `80, 443` nu
     kaatanum.
2. **MySQL** row la **Start** click pannunga.
   - Firewall kettadhuna adhukum allow pannunga.
   - Idhuvum **green** aaganum, port `3306` kaatanum.

**Work aaguthanu test pannunga:**

- Browser la **http://localhost** open pannunga — XAMPP welcome page
  theriyanum.
- Apparam **http://localhost/phpmyadmin** open pannunga — **phpMyAdmin**
  nu oru page theriyanum (database manage panra web page idhu).
  Step 5 la idha use pannuvom.

> ❌ **Apache start aagala / red ah maariduchu?**
> [Troubleshooting](#11-problem-vandha-enna-pannanum-troubleshooting) section
> paarunga — 90% time Skype/IIS madhiri vera program port 80 ah pudichirukum.

---

## 4. Project folder ah correct place la vaikanum

Apache **`C:\xampp\htdocs`** folder kulla irukura websites ah mattum dhan
serve pannum. So project ah anga copy pannanum.

1. **File Explorer** open pannunga (Windows key + E).
2. Indha project irukura folder ku ponga (example: `D:\Dev\SDC` —
   ulla `README.md`, `app`, `public`, `database` ellam irukura folder).
3. **Oru level mela** poi, **`SDC` folder mela right-click → Copy**.
4. **`C:\xampp\htdocs`** ku navigate pannunga.
5. **Empty space la right-click → Paste** pannunga.

Ippo indha madhiri structure irukanum (check pannunga!):

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

> ⚠️ `C:\xampp\htdocs\SDC\app` nu dhan irukanum —
> `C:\xampp\htdocs\SDC\SDC\app` nu **irukka koodadhu** (folder kulla
> innoru folder — copy paste double ah aana ippadi aagum, jaakiradhai).

---

## 5. Database create pannuvom

Project kooda rendu ready-made database files varudhu:

- `database/schema.sql` — database um adhoda empty tables um create pannum
- `database/seed.sql` — system start aaga minimum theva vishayangal mattum:
  21 departments, current financial year, basic settings, apparam **oru
  administrator account** (demo data edhuvum illa)

**phpMyAdmin** use panni load pannuvom (full ah clicking dhan, typing venam):

### Step 5.1 — Schema import pannunga

1. Browser la **http://localhost/phpmyadmin** open pannunga.
2. Top menu la **Import** tab click pannunga.
3. *"File to import"* keela **Choose File** (illa **Browse**) click pannunga.
4. **`C:\xampp\htdocs\SDC\database`** folder ku poi **`schema.sql`** select
   panni **Open** click pannunga.
5. Page bottom varaikkum scroll panni **Import** button (palaya versions la
   **Go** nu irukkum) click pannunga.
6. *"Import has been successfully finished"* nu green message varanum.
   Left sidebar la **`secms`** nu pudhu database theriyum.

### Step 5.2 — Starting data import pannunga

1. Thirumbavum top menu la **Import** tab click pannunga.
2. **Choose File** click panni, indha thadava adhe folder la irundhu
   **`seed.sql`** select pannunga. **Open** click pannunga.
3. Bottom la **Import** (illa **Go**) click pannunga.
4. Green success message vandhuchuna → mudinjidhu!

**Quick check:** left sidebar la **secms** click pannunga → **departments**
table click pannunga → 21 rows theriyanum (College, School, Farming, Temple,
Goshala…). Theriyudhuna unga database perfect. 👌

---

## 6. App ah configure pannuvom

App ku rendu vishayam theriyanum: **database ah eppadi reach panradhu**,
**adhoda web address enna**. Rendum oru chinna file la dhan irukku — adha
ippo create pannuvom.

1. File Explorer la **`C:\xampp\htdocs\SDC\config`** open pannunga.
2. Anga **`database.php.example`** nu oru file irukkum. Adha **Copy**
   pannunga (right-click → Copy, apparam adhe folder la right-click → Paste).
3. Copy file oda pera exact ah idhukku maathunga: **`database.php`**
   - File extension theriyala na: File Explorer la **View → Show →
     File name extensions** click pannunga — full name theriyum.
4. **`database.php` mela right-click → Open with → Notepad** panni, ulla
   exact ah ippadi irukka maathunga:

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

   Ovvoru line um enna artham:
   - `host` / `port` — MySQL enga odudhunu. XAMPP unga own PC la dhan
     odudhu, so idha appadiye vidunga.
   - `database` — neenga import panna database oda peru (`secms`).
   - `username` / `password` — XAMPP oda MySQL account `root`, password
     **kaali (empty)** dhan default. So `''` ah appadiye vidunga.
   - `base_url` — site address oda path part. Project `htdocs` kulla `SDC`
     folder la irukku, entry point `public` folder — so path
     `/SDC/public`. **Idha dhan beginners miss panranga — skip pannadheenga!**

5. File ah **Save** pannunga (Ctrl + S), Notepad close pannunga.

---

## 7. App open panni login pannuvom

1. XAMPP Control Panel la **Apache** um **MySQL** um rendu green ah irukanu
   paarunga.
2. Browser la indha address ku ponga:

   ### 👉 http://localhost/SDC/public

3. Golden logo oda **Sign in** page theriyanum.
4. Administrator account la login pannunga:

   | Field | Value |
   | --- | --- |
   | Email | `admin@sdc.edu.in` |
   | Password | `Admin@123` |

5. **Dashboard** varum — animated statistics, charts, 21 departments
   suthura circle animation. Congrats, unga app odudhu! 🎉

   Ellame **₹0.00** nu kaatum — adhu correct dhan: system full fresh ah
   start aagudhu. Ellathayum neenga dhan create pannanum
   ([first steps](#10-app-ah-eppadi-use-pannanum--chinna-tour) paarunga).

---

## 8. Default password ah maathunga (romba mukkiyam!)

Indha README padikkura ellarukum default password theriyum — so odane
maathunga:

1. Administrator ah login pannunga.
2. Top-right corner la unga pera click pannunga → **Profile & Settings**.
3. **Change Password** box la: current password (`Admin@123`) type pannunga,
   apparam pudhu password rendu thadava type pannunga → **Change Password**
   click pannunga.

Vera accounts edhuvum illa — administrator mattum dhan. Matha users ah
neenga **User Management** la create pannikonga.

---

## 9. Computer restart pannina apparam

XAMPP Windows kooda automatic ah start **aagadhu**. Ovvoru restart apparam:

1. **Windows key** press panni `xampp` type panni **Enter** adinga
   (Control Panel open aagum).
2. **Apache** pakkathula **Start** click pannunga.
3. **MySQL** pakkathula **Start** click pannunga.
4. Browser la **http://localhost/SDC/public** open pannunga.

Ivlo dhan — unga data ellam database la safe ah irukkum, bayam venam.

> 💡 Optional: XAMPP Control Panel la Apache/MySQL pakkathula "Service"
> column la irukura **red X** icons ah click panna, adhu Windows service ah
> install aagum — appuram Windows start aagum bodhe adhuvum automatic ah
> start aagum.

---

## 10. App ah eppadi use pannanum — chinna tour

System **full ah empty** ah start aagum — administrator account, 21
departments, current financial year — ivlo dhan irukkum. First steps
recommended order:

1. **Settings** → admin password maathunga (Section 8).
2. **User Management** → unga staff ku accounts create pannunga (Principal,
   Accounts, Department Heads, Viewers).
3. **Financial Year Management** → active financial year correct ah nu
   check pannunga (2026-27 already create aagi active ah irukkum).
4. **Budget Allocation** → ovvoru department kum budget allocate panni
   approve pannunga.
5. Apparam daily flow: department heads **Purchase Requests** create
   pannuvanga → neenga approve pannuveenga → Accounts **Purchase Orders**
   create pannuvanga → *paid* nu mark panna expenses automatic ah record
   aagum → **Reports** la ellam theriyum.

| Sidebar item | Enga enna panalam |
| --- | --- |
| **Dashboard** | Totals, charts, alerts, suthura department showcase. Department card mela mouse vachcha animation pause aagi details kaatum; click panna andha department budget page open aagum. |
| **Budget Allocation** | Ovvoru department kum varusha budget kudunga. Principal/Administrator approve pannuvanga. Selavu aaga aaga utilization bar niraiyum. |
| **Sanction Amount** | Official ah sanction aana amounts record pannunga. Ovvonnukum automatic ah permanent number varum — `COL-2026-001` madhiri (department code + year + counter). Printer icon click panna formal printable sanction order varum. |
| **Purchase Request** | Department heads edhavadhu vaanga permission kekkuranga. Flow: create (draft) → submit → approve/reject. **Amount department oda remaining budget ah vida jaasthi na, submit automatic ah block aagum**, administrator ku notification pogum. |
| **Purchase Order** | Accounts vendor ku official order create pannuvanga: line items, GST %, invoice number. Status: draft → issued → received → **paid**. *Paid* nu mark panna expense automatic ah record aagi department budget la irundhu kammi aagum. |
| **Reports** | Monthly / quarterly / half-yearly / annual / budget-vs-actual innum niraya — ovvonnukum chart um **CSV / Excel / PDF** export buttons um irukku. |
| **Financial Year Management** | Adutha varusham (2026-27) create panni, pudhu varusham aarambichadhum activate pannunga. |
| **Notifications** | Ungaloda attention theva ellam inga. Header la irukura bell 30 seconds ku oru thadava update aagum. |
| **User Management** | Staff accounts create panni role kudunga ([roles list](#13-login-accounts-list) paarunga). |
| **Audit Logs** | Yaaru enna eppo panninanga nu permanent record — ella create, edit, delete, approval, login um. |
| **Settings** | Unga profile, password, Light/Dark theme, notification preferences. |

Header la **moon/sun icon** dark mode maathum. **Search box** screen la
irukura table ah filter pannum. Printable pages la (sanction / purchase
order) browser oda **Print → Save as PDF** use panni PDF create pannalam.

---

## 11. Problem vandha enna pannanum (Troubleshooting)

### XAMPP la "Apache" start aagala (red aagudhu / odane stop aagudhu)

Vera edho program **port 80** ah use pannudhu. Common culprits: Skype, IIS,
VMware, illa vera web server.

**Easy fix — Apache ah port 8080 ku maathunga:**

1. XAMPP Control Panel la Apache row la **Config → Apache (httpd.conf)**
   click pannunga. Notepad la open aagum.
2. **Ctrl + H** press pannunga (replace). `Listen 80` ah `Listen 8080` ku
   replace pannunga. `ServerName localhost:80` ah um
   `ServerName localhost:8080` ku replace pannunga.
3. Save pannunga (Ctrl + S), Notepad close panni, thirumba **Start** click
   pannunga.
4. Ini ella address layum `:8080` serunga:
   **http://localhost:8080/SDC/public** and
   **http://localhost:8080/phpmyadmin**

### "MySQL" start aagala

- Vera oru MySQL/MariaDB already odudhu. **Ctrl + Shift + Esc** press
  pannunga (Task Manager) → **Details** tab → `mysqld.exe` ah thedunga →
  right-click → **End task** → XAMPP la thirumba Start click pannunga.
- Innum start aagala na, MySQL row la **Config → my.ini** click panni
  `port=3306` ah `port=3307` ku maathunga — apparam
  `C:\xampp\htdocs\SDC\config\database.php` la um `'port' => 3306` ah
  `'port' => 3307` ku maathanum.

### Page la **"Database connection failed"** nu varudhu

Idhula edhavadhu onnu dhan kaaranam:

- MySQL odala → XAMPP Control Panel la start pannunga.
- Database import pannala → [Step 5](#5-database-create-pannuvom) pannunga.
- `config/database.php` la thappa name/username/password →
  [Step 6](#6-app-ah-configure-pannuvom) thirumba check pannunga (XAMPP
  default: user `root`, password kaali).

### White/blank page illa "404 Not Found" varudhu

- Address ah check pannunga — exact ah
  **http://localhost/SDC/public** nu irukanum (kadaisila `/public` irukanum).
- Folder correct ah irukka nu paarunga:
  `C:\xampp\htdocs\SDC\public\index.php` (double nested illa nu — Step 4
  paarunga).
- `config/database.php` la `base_url` `'/SDC/public'` nu irukka check
  pannunga.

### Page la PHP code text ah theriyudhu

Neenga file ah direct ah open pannirukeenga (address `file:///C:/...` nu
start aagudhu). PHP web server vazhiya dhan odum — eppovum
`http://localhost/...` nu start aagura address dhan use pannanum.

### Design/icons ellam break aagi plain text ah theriyudhu

Design libraries (Bootstrap, icons, charts) internet la irundhu (CDN) load
aagudhu. First time app open panna computer ku internet connection irukanum.

### Save panna "Invalid or missing CSRF token" varudhu

Unga login session expire aagiduchu (30 minutes onnum pannalana). Page ah
refresh panni, thirumba login panni, retry pannunga.

### Login la "Invalid credentials" varudhu

- Email exact ah type pannunga: `admin@sdc.edu.in`, password exact ah:
  `Admin@123` — capital `A`, apparam `dmin`, apparam `@` symbol, apparam
  `123`. Password case-sensitive.
- `seed.sql` import pannalana users e irukkadhu →
  [Step 5.2](#step-52--starting-data-import-pannunga) pannunga.

### Error logs enga irukku?

- Application errors: `C:\xampp\htdocs\SDC\storage\logs\php-error.log`
- Apache errors: `C:\xampp\apache\logs\error.log`
- Notepad la open panni last lines padinga — problem enna nu adhula
  clear ah irukkum.

### Fresh ah clean database venum

`schema.sql` apparam `seed.sql` ah thirumba import pannunga (Step 5).
Schema file ellathayum azhichitu pudhusa create pannum.

---

## 12. Internet la host panna

Vera aalunga (office staff, vera computers) use pannanumna rendu options
irukku.

### Option A — Indha PC layae vachu, local network la share pannunga (free, office use)

Ellarum same building/network la irundha:

1. Unga PC oda IP address ah kandupidinga: **Windows key** press panni,
   `cmd` type panni Enter, apparam `ipconfig` type panni Enter. **IPv4
   Address** ah paarunga — example `192.168.1.25`.
2. Apache firewall la allow aagirukanum (Step 3 la already pannitinga).
3. Same Wi-Fi/network la irukura vera endha device layum idha open pannunga:
   `http://192.168.1.25/SDC/public`
4. Aalunga app use panna theva pattaal XAMPP PC on la irukanum.

### Option B — Web hosting rent pannunga (enga irundhalum work aagum)

**PHP 8.1+ um MySQL um** support panra edhavadhu cheap **shared hosting**
plan vaangunga (almost ellam support pannum — example: Hostinger, HostGator,
GoDaddy, MilesWeb; ₹100–₹250/month range la kidaikkum). Apparam:

1. **Database create pannunga** — hosting control panel la (usually
   **cPanel**) **MySQL Databases** open pannunga:
   - Oru database create pannunga (host prefix serpaanga, example
     `youruser_secms`).
   - Strong password oda oru database **user** create pannunga.
   - Andha **user ah database kooda add pannunga** — *All Privileges* oda.
   - Ezhudhi vachukonga: database name, username, password.
2. **Data import pannunga** — cPanel la **phpMyAdmin** open pannunga, unga
   pudhu database click pannunga, **Import** click panni
   `database/schema.sql` upload pannunga.
   ⚠️ Host `CREATE DATABASE` allow pannala na, mudhalla `schema.sql` ah
   Notepad la open panni `CREATE DATABASE` um `USE secms;` um irukura rendu
   lines ah delete pannunga — apparam left sidebar la unga database select
   aagirukka nu paarthu import pannunga. Apparam `seed.sql` ah um same
   madhiri import pannunga.
3. **Files upload pannunga** — cPanel la **File Manager** open pannunga:
   - Local `SDC` folder ah zip pannunga (right-click → *Compress to ZIP*),
     zip ah upload pannunga, File Manager la *Extract* pannunga.
   - Best practice: domain oda **document root** ah `public` folder ku
     point pannunga (cPanel → Domains → document root ah
     `/home/youruser/SDC/public` nu maathunga). Appo `base_url` `''` ah
     irukkalam.
   - Document root maatha mudiyala na, `SDC` ah `public_html` kulla upload
     panni `'base_url' => '/SDC/public'` set pannunga — site address
     `https://yourdomain.com/SDC/public` aagum.
4. **Configure pannunga** — server la `config/database.php` ah step 1 la
   ezhudhi vacha database name, username, password oda edit pannunga.
5. **Debug messages off pannunga** — `config/config.php` la
   `'debug' => true,` ah `'debug' => false,` ku maathunga (visitors ku
   technical error details theriya koodadhu).
6. **HTTPS use pannunga** — cPanel la free SSL/Let's Encrypt certificate
   enable pannunga (usually one click), eppovum `https://` address dhan
   use pannunga.
7. **Ella default passwords um maathunga** (Section 8) — ippo idhu public
   internet la irukku, romba mukkiyam!

---

## 13. Login accounts list

Fresh install apparam **onnu mattum dhan** account irukkum:

| Email | Password | Role |
| --- | --- | --- |
| `admin@sdc.edu.in` | `Admin@123` | **Administrator** |

First login apparam odane password maathunga (Section 8). Apparam unga team
ah **User Management** la create pannunga — ovvoruthanukkum indha roles la
onnu select pannunga:

| Role | Enna panna mudiyum |
| --- | --- |
| **Administrator** | Ellame: users, budgets, sanctions, deletes, settings |
| **Principal** | Ella departments um paarkalam, sanctions/budgets/requests approve pannalam, reports — aana delete panna mudiyadhu |
| **Accounts Department** | Budgets manage, sanctions create/verify, purchase orders process, reports |
| **Department Head** | Avanga department mattum: purchase requests create, adhoda budget/sanctions/reports paarkalam (department assign pannanum) |
| **Viewer** | Ellathayum paarkalam, aana edhuvum maatha mudiyadhu (read-only) |

---

## Kadaisiya oru vaarthai 🙏

Setup la enga maatikittalum bayapada venam — 90% problems
[Troubleshooting](#11-problem-vandha-enna-pannanum-troubleshooting) section
la irukura fixes la solve aagidum. Error message ah copy panni Google la
search pannalum niraya help kidaikkum.

Developer reference (REST API details) venumna [README.md](README.md) oda
last section paarunga.

**All the best! Unga institution oda finance management ippo unga kaiyila.** ✨
