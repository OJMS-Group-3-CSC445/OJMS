# Online Journal Management System (OJMS)

A full-stack **PHP & MySQL** web platform built for the **CSC 445** project to manage academic journal publishing, article submissions, peer review, and editorial workflows.

The system provides role-based interfaces and server-side access controls so visitors, authors, reviewers, editors, and administrators only interact with features relevant to their responsibilities.

---

## 1. Project Overview

OJMS supports the end-to-end operations of a local academic journal by providing:

- Public access to journal information, editorial board listings, current issues, and archived publications.
- Author registration, email/account verification, and session-authenticated login.
- Online manuscript submission and real-time submission status tracking for authors.
- Reviewer assignment and manuscript evaluation workflows.
- Role-based dashboards for editorial personnel and portal administrators.

---

## 2. User Roles

The application supports six primary user roles enforced through PHP sessions and role guards:

| Role | Responsibilities |
| :--- | :--- |
| **Visitor** | Browse journal information, editorial board, reviewers, current issue, and archived issues. |
| **Author** | Register, verify account, submit articles, and monitor submission statuses. |
| **Reviewer** | View assigned manuscripts and submit review evaluations. |
| **Managing Editor** | View and manage incoming article submissions. |
| **Editor-in-Chief** | Oversee journal submissions, editorial decisions, and publishing activities. |
| **Portal ICT Administrator** | Manage users, reviewers, system logs, settings, and reviewer assignments. |

> **Security Note:** Role-based navigation and page guards are enforced on the server via PHP sessions (`$_SESSION`), `password_verify()`, and CSRF token verification.

---

## 3. Core Features and Pages

### Public Interface (`/` root pages)
- **Homepage** (`index.php`)
- **About / Editorial Board / Reviewers** (`about.php`)
- **Contact Us** (`contact.php`)
- **Current Issue** (`current-issue.php` & `api/current-issue.php`)
- **Previous Issues / Archives** (`archives.php`)
- **Article Download** (`download.php`)

### Authentication (`auth/`)
- User Registration, Account Verification, Login (`auth/login.php`), and Logout (`auth/logout.php`).
- Registration collects:
  - Email Address
  - Surname
  - Other Name

### Role Portals
- **Author (`author/`):** View personal dashboard, submit new manuscripts (Title, Abstract, and `.doc`/`.docx` full manuscript upload), and track submission status.
- **Reviewer (`reviewer/`):** View assigned manuscripts and access review details.
- **Editor / Managing Editor (`editor/`):** View, manage, and oversee submissions and editorial activities.
- **Portal ICT Administrator (`admin/`):** Manage platform users, reviewers, system logs, portal settings, and submission assignments.

---

## 4. Technology Stack

- **Backend:** Vanilla PHP (8.x+) with **PDO** (`PDO::ERRMODE_EXCEPTION`) for prepared SQL queries
- **Database:** MySQL / MariaDB
- **Frontend:** Server-rendered PHP views, HTML5, CSS3, and Vanilla JavaScript
- **Authentication & Security:** PHP Native Sessions (`session_regenerate_id`), `password_hash` / `password_verify`, and CSRF tokens

---

## 5. Project Structure

```text
OJMS/
├── admin/                  # Portal ICT Administrator dashboard & management pages
├── api/                    # JSON endpoints (e.g., current-issue.php)
├── assets/                 # Stylesheets (assets/css/), scripts (assets/js/), and media
├── auth/                   # Login, registration, verification, and logout handlers
├── author/                 # Author dashboard and manuscript submission views
├── config/                 # Database connection configuration (database.php)
├── editor/                 # Editor-in-Chief and Managing Editor dashboards
├── includes/               # Shared layout templates, auth guards, and helper functions
├── reviewer/               # Reviewer dashboard and assigned article views
├── about.php               # About the journal, editorial board, and reviewers
├── archives.php            # Archived journal issues
├── contact.php             # Contact page
├── current-issue.php       # Current published issue view
├── download.php            # Secure manuscript/article download handler
├── index.php               # Public landing page
├── Journal System API Documentation.md
├── LICENSE
└── Readme.md