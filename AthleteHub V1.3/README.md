# 🏃 AthleteHub — Sports Networking Platform

> A LinkedIn-style web platform connecting athletes, coaches, recruiters, and sports clubs in one digital ecosystem.

![Version](https://img.shields.io/badge/Version-1.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.x-purple)
![MySQL](https://img.shields.io/badge/MySQL-8.x-orange)
![License](https://img.shields.io/badge/License-Academic-green)

---

## 📌 About The Project

**AthleteHub** is a full-stack web application built as a college project. It provides a centralized platform where:

- 🏃 **Athletes** can showcase achievements and get recruited
- 🎯 **Coaches** can discover and connect with talented players
- 📋 **Recruiters** can post opportunities and find athletes
- 🏟 **Clubs** can organize tournaments and run recruitment drives
- 🔧 **Admins** can manage and moderate the entire platform

---

## ✨ Features

| Module         | Description                                          |
| -------------- | ---------------------------------------------------- |
| 🔐 Auth        | Login/Register with role selection, bcrypt passwords |
| 📱 Feed        | Social posts, likes, comments, follow system         |
| 👤 Profile     | Athlete profiles with verification badge system      |
| 💼 Recruitment | Job/scholarship/tryout listings with applications    |
| 🏆 Tournaments | Magazine-style tournament browser + registration     |
| 📰 News        | Full-width magazine news with category filters       |
| 💬 Messages    | Real-time-style inbox and chat system                |
| 🔧 Admin       | Complete admin panel with stats, CRUD, moderation    |

---

## 🛠 Tech Stack

```
Frontend:  HTML5 · CSS3 · JavaScript (Vanilla)
Backend:   PHP 8.x
Database:  MySQL 8.x
Server:    Apache (XAMPP)
Icons:     Iconify (solar: set)
UI Style:  Glassmorphism + Light Blue Theme
Version:   Git + GitHub
```

---

## 🚀 Getting Started

### Prerequisites

- [XAMPP](https://www.apachefriends.org) (PHP 8.x + MySQL)
- [Git](https://git-scm.com)
- [VS Code](https://code.visualstudio.com) (recommended)

### Installation

**1. Clone the repository**

```bash
cd C:/xampp/htdocs
git clone https://github.com/AdityaDesai226010/athletehub.git
cd athletehub
```

**2. Start XAMPP**

- Open XAMPP Control Panel
- Start **Apache** and **MySQL**

**3. Import the database**

```bash
# Option A — MySQL CLI
cd C:/xampp/mysql/bin
mysql.exe -u root -e "source C:/xampp/htdocs/athletehub/database/athletehub.sql"

# Option B — phpMyAdmin
# Go to http://localhost/phpmyadmin
# Create database "athletehub"
# Import database/athletehub.sql
```

**4. Open in browser**

```
http://localhost/athletehub/
```

---

## 🔑 Default Login Credentials

| Role    | Email                | Password  |
| ------- | -------------------- | --------- |
| Admin   | admin@athletehub.com | Admin@123 |
| Athlete | athlete1@test.com    | Test@123  |
| Coach   | coach@test.com       | Test@123  |
| Club    | club@test.com        | Test@123  |

---

## 📁 Project Structure

```
athletehub/
├── index.php                  ← Landing / Login / Register
├── config/
│   └── db.php                 ← PDO MySQL connection
├── includes/
│   ├── header.php             ← Glass navigation bar
│   ├── footer.php             ← Footer + JS links
│   ├── session.php            ← Auth guard
│   ├── admin_guard.php        ← Admin-only guard
│   └── admin_sidebar.php      ← Admin dark sidebar
├── pages/
│   ├── feed.php               ← Home feed
│   ├── profile.php            ← Athlete profile
│   ├── edit-profile.php       ← Edit profile
│   ├── recruitment.php        ← Job listings
│   ├── tournaments.php        ← Tournament browser
│   ├── messages.php           ← Chat / inbox
│   └── news.php               ← Sports news
├── admin/
│   ├── dashboard.php          ← Admin overview
│   ├── users.php              ← User management
│   ├── posts.php              ← Content moderation
│   ├── verifications.php      ← Verify requests
│   ├── recruitment.php        ← Manage listings
│   ├── tournaments.php        ← CRUD tournaments
│   └── news.php               ← Manage articles
├── api/
│   ├── post.php               ← Create/like/comment
│   ├── follow.php             ← Follow/unfollow
│   ├── message.php            ← Send messages
│   ├── recruitment.php        ← Apply for jobs
│   └── tournament.php         ← Register for events
├── assets/
│   ├── css/
│   │   ├── main.css           ← CSS variables + reset
│   │   ├── glass.css          ← Glassmorphism components
│   │   ├── feed.css
│   │   ├── auth.css
│   │   ├── profile.css
│   │   ├── recruitment.css
│   │   ├── tournaments.css
│   │   ├── news.css
│   │   ├── messages.css
│   │   ├── admin.css
│   │   └── responsive.css
│   └── js/
│       ├── main.js
│       ├── feed.js
│       ├── auth.js
│       ├── profile.js
│       ├── recruitment.js
│       ├── tournaments.js
│       ├── news.js
│       ├── messages.js
│       └── admin.js
├── uploads/
│   └── profile_pics/
└── database/
    └── athletehub.sql         ← Full schema + sample data
```

---

## 🗄 Database Schema

**13 Tables:**

| Table                    | Purpose                      |
| ------------------------ | ---------------------------- |
| users                    | All user accounts with roles |
| posts                    | Social feed posts            |
| post_likes               | Like junction table          |
| post_comments            | Comments on posts            |
| follows                  | Follow relationships         |
| messages                 | Direct messages              |
| recruitment              | Job/scholarship listings     |
| recruitment_applications | Applications tracking        |
| tournaments              | Sports events                |
| tournament_registrations | Event registrations          |
| news                     | Sports articles              |
| verification_requests    | Badge verification queue     |

---

## 👥 User Roles

| Role        | Badge | Key Permissions                  |
| ----------- | ----- | -------------------------------- |
| Athlete     | 🏃    | Post, follow, apply, register    |
| Coach       | 🎯    | Search athletes, contact, follow |
| Recruiter   | 📋    | Post jobs, search, contact       |
| Sports Club | 🏟    | Post jobs, organize tournaments  |
| Admin       | 🔧    | Full platform control            |

---

## 🔐 Security

- ✅ PDO prepared statements (SQL injection prevention)
- ✅ bcrypt password hashing
- ✅ PHP session-based authentication
- ✅ Role-based access control
- ✅ htmlspecialchars() on all output (XSS prevention)
- ✅ Admin middleware guard on all admin pages
- ✅ POST-only destructive actions (no GET deletes)

---

## 🗺 Roadmap

### V1 — College Project ✅ (Current)

- Core pages with PHP + MySQL
- All 5 user roles
- Admin panel with full CRUD
- Glassmorphism UI

### V2 — Competition Ready 🔜

- Full verification system
- Polished UI with animations
- Advanced messaging
- Performance optimizations

### V3 — Production 🔮

- WebSocket real-time chat
- Cloud hosting (AWS/DigitalOcean)
- Email notifications
- AI athlete recommendations
- iOS / Android mobile app

---

## 👨‍💻 Team

| Member   | Role                    |
| -------- | ----------------------- |
| Member 1 | Database + Backend Lead |
| Member 2 | Frontend + UI Lead      |
| Member 3 | Admin + Modules Lead    |

---

## 📄 License

This project is built for academic purposes as a college project submission.

---

<p align="center">
  Built for the sports community · AthleteHub 2026
</p>
