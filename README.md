# TransactiWar

> A secure financial transaction web application built for the Network Security course (Team 7)
---
## Git Hub Link
> https://github.com/cs25mtech14003-ux/Team-7
---

## Team Members

| Name | Roll No. | Contribution |
|------|------| ------ |
| Atish Kadam | CS25MTECH14003 | Implemented User Authentication (Login and Register) and Session Management.  |
| Akarsh Dubey | CS25MTECH14001 |Designed Database, Configured Docker, Implemented dashboard and Added logging feature |
| Atharva Kale | CS25MTECH11024 | Implemented transaction, Removed Directory traversal vulnerability |
| Prashant Kumar Dubey | CS25MTECH14011 |Implemented transaction history |
| Debdip Choudhuri | CS25MTECH11025|Implemented Profile Management|

----

## Project Overview

TransactiWar is a secure web application that simulates a financial transaction platform. It is built entirely from scratch using **PHP + MySQL** without any security frameworks, demonstrating hands-on implementation of web application security principles.

### What Users Can Do
- Register and receive Rs. 100 as starting balance
- Login and logout securely
- Manage their profile and upload a profile picture
- Search for other users by username or ID
- Transfer money to other users
- View full transaction history with comments

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, JavaScript, Bootstrap 5 |
| Backend | PHP 8.2 |
| Database | MySQL 8.0 |
| Server | Apache (via Docker) |
| Dev Environment | Docker + Docker Compose |
| DB Management | phpMyAdmin |

> No security frameworks were used. All security logic was written from scratch as per project requirements.

---

##  Project Structure
```
transactiwar/
│
├── index.php             # Landing page (Sign In / Create Account)
│
├── public/               # User-facing pages
│   ├── login.php         # User login
│   ├── register.php      # User registration
│   ├── logout.php        # Session termination
│   ├── dashboard.php     # Main dashboard (shows Rs. 100 balance)
│   ├── profile.php       # Profile management
│   ├── server_img.php
│   ├── transfer.php      # Money transfer
│   └── history.php       # Transaction history
│   ├── assets
|       ├── default_avatar.png
│   └── handlers   
|       ├── update_profile.php      
│       └── update_profile_image.php    
│
├── includes/             # Backend logic
│   ├── config.php        # Global config, session init, INITIAL_BALANCE = 100
│   ├── db.php            # PDO database connection
│   ├── functions.php     # Sanitization, CSRF, flash messages, helpers
│   ├── auth.php          # Register, login, logout, session management
│   └── logger.php        # Activity logging (page | user | timestamp | IP)
│
├── uploads/              # Profile image storage
├── logs/
│   └── activity.log      # Auto-generated activity log
│
├── docker/
│   ├── Dockerfile        # PHP 8.2 + Apache image
│   ├── apache.conf       # Apache virtual host config
│   └── init.sql          # DB schema — auto-runs on first container start
│
├── docker-compose.yml    # App + MySQL + phpMyAdmin containers
└── README.md
├── .gitignore    
└── .env
├── run.sh
└── stop.sh
├── add_users.sh
└── script.php
```

## Getting Started

### Prerequisites
- Docker
- Docker Compose

### Installation

```bash
# 1. Clone the repository
git clone <repo-url>
cd Team-7 # Please ensure working directory is Team-7
```
### Running

```bash
# 1. Build and start all containers
./run.sh 
will execute (docker-compose up --build)
if this command doesnt run use (sudo docker compose up --build)

# 2. To stop (Nothing from database will be erased)
./stop.sh 
will execute (docker-compose down)
if this command doesnt run use (sudo docker compose down)

# 3. To stop and reset the database
docker-compose down -v
if this command doesnt run use (sudo docker compose down -v)

# 4. To create 100 users automatically
./add_users.sh
This will create 100 users with usernames as user1, user2, ... and all users have same password i.e. password123. 
```

### Access Points

| Service | URL |
|---------|-----|
| Web App | http://localhost:8080 |

---

## Implemented Features 

### 1. User Authentication & Session Management

#### Registration
- Unique username (3–30 chars, alphanumeric + underscore)
- Valid email validation
- Password: min 8 chars, must contain letters and numbers
- Rs. 100 automatically credited on registration
- Duplicate username or email detection

#### Login
- Credential verification against passwords
- Generic error messages to prevent **username enumeration**
- Secure session creation on successful login

#### Session Management
- Session Fixation Prevention 
- Session Hijacking Protection
- Session Timeout
- Secure Cookie Flags
- Full session destruction on logout (server + cookie)

#### Access Control
- guards all protected pages
- Automatic redirect to login if unauthenticated

### 2. Profile Management

- can update personal details (except usernames).
- Ability to upload and store long content (e.g., biography).
- Ability to upload a profile image (with secure storage).

### 3. User Search & Money Transfer
#### Search Users: Search by username or user ID.
#### Money Transfer:
- Transfer money to another user by user ID.
- Prevent negative balance transactions.
- Display transaction history.
#### Comments
- Users can put an optional comment along with the money transfer
- The comment should be visible to the receiver


##  Activity Logging

Every sensitive action is logged to `logs/activity.log` in the format:

```
[YYYY-MM-DD HH:MM:SS] | Page: <page> | User: <username> | IP: <client_ip>
```

Example:
```
[2026-03-03 14:22:10] | Page: login.php | User: alice | IP: 172.18.0.1
[2026-03-03 14:22:45] | Page: transfer.php | User: alice | IP: 172.18.0.1
[2026-03-03 14:23:01] | Page: logout.php | User: alice | IP: 172.18.0.1
```


---
