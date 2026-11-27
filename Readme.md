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

## 6. Getting Started

### Prerequisites
- PHP **8.0+** with the `pdo_mysql` extension enabled
- MySQL **5.7+** or MariaDB **10.3+**
- A local web server stack (e.g., **XAMPP**, **Laragon**, **WAMP**, or PHP's built-in server)

### Local Setup
1. **Clone the repository** into your web server's root directory (e.g., `htdocs/OJMS` or `www/OJMS`):
   ```bash
   git clone [https://github.com/OJMS-Group-3-CSC445/OJMS.git](https://github.com/OJMS-Group-3-CSC445/OJMS.git)
   cd OJMS
   ```
2. **Create the database** in MySQL and import the project SQL schema.
3. **Configure database credentials** in `config/database.php` (ensure `$pdo` points to your local MySQL host, database name, username, and password).
4. **Run the application** via Apache/Nginx or PHP's built-in development server:
   ```bash
   php -S localhost:8000
   ```
5. Open `http://localhost:8000` in your browser.

> **Important:** Never commit production database passwords, API keys, or private environment configuration files to the repository.

---

## 7. Development Guidelines

### Code Organization
- Write clean, readable, and maintainable PHP, CSS, and JavaScript.
- Place reusable headers, footers, session guards, and CSRF helpers inside `includes/`.
- Keep page-specific styles in `assets/css/` and scripts in `assets/js/`.
- Use prepared PDO statements for all database queries—never interpolate raw user input into SQL.

### UI/UX & Accessibility
- Follow the approved UI/UX designs and maintain visual consistency across portals.
- Ensure responsive layouts across desktop and mobile viewports.
- Provide clear loading, validation, error, success, and empty states.

### Role-Based Access
- Always enforce session authentication and role checks at the top of protected PHP pages before rendering output or processing `POST` requests.
- Validate CSRF tokens on all state-changing forms.

---

## 8. Git Contribution Guidelines

1. Pull the latest changes from `main` before starting new work.
2. Create a dedicated branch for every feature or fix:
   - `feature/feature-name`
   - `fix/issue-name`
   - `docs/documentation-name`
3. Use clear, conventional commit messages:
   - `feat: implement manuscript upload validation`
   - `fix: resolve reviewer assignment query`
   - `style: improve admin dashboard table responsiveness`
   - `docs: update README setup instructions`
4. Test all database queries, session redirects, and form submissions locally before opening a Pull Request.

---

## 9. Project Documentation

All implementation decisions should align with the approved project documents:

| Document | Purpose |
| :--- | :--- |
| **Software Requirements Specification (SRS)** | Defines functional and non-functional system requirements. |
| **System Design Document** | Explains the system architecture, database schema, and components. |
| **UI/UX Design Document** | Defines the visual interface and user experience. |
| **Development / Build Specification** | Describes implementation standards and build workflows. |

---

## 10. Project Information

- **Project:** Online Journal Management System (OJMS) — CSC 445 Group 3
- **Repository:** Full-Stack Web Application (`PHP` / `MySQL` / `HTML` / `CSS` / `JS`)
- **Live Demo:** [ojms.vtenergies.com](https://ojms.vtenergies.com/)
- **License:** [MIT License](https://github.com/OJMS-Group-3-CSC445/OJMS#MIT-1-ov-file)

### Summary of What Was Updated
- **Replaced outdated frontend-only & Laravel Sanctum sections:** Removed references to `http://127.0.0.1:8000/api`, `author/api.js`, `sessionStorage` bearer tokens, and CORS setup, replacing them with your new **PHP + PDO + MySQL** session and CSRF architecture.
- **Synced the Project Structure:** Updated the directory tree to match your actual repository layout (`admin/`, `api/`, `assets/`, `auth/`, `author/`, `config/`, `editor/`, `includes/`, `reviewer/`, and root `.php` entry points).
- **Added PHP/MySQL Local Setup Instructions:** Replaced the static HTML instructions with database configuration (`config/database.php`) and PHP server setup steps.