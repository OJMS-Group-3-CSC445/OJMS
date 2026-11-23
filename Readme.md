# Role-Based Journal System (Frontend)

The frontend repository for the **Role-Based Journal System**, a web-based platform designed to facilitate journal publishing, article submissions, and editorial management.

The platform provides different interfaces and functionalities based on user roles, ensuring that users can only access features relevant to their responsibilities.

## 1. Project Overview

The system is designed to support the operations of a local academic journal by providing:

- Public access to journal information and published articles.
- Author registration and account verification.
- Online article submission.
- Submission tracking for authors.
- Article and reviewer management.
- Role-based dashboards for editorial personnel.

The frontend is responsible for providing the user interface through which visitors, authors, reviewers, editors, and administrators interact with the system.

## 2. User Roles

The application supports six primary user roles.

| Role | Responsibilities |
|---|---|
| Visitor | Browse journal information, editorial board, reviewers, current and archived issues. |
| Author | Register, verify account, submit articles, and monitor submissions. |
| Reviewer | View articles assigned for review. |
| Managing Editor | View and manage article submissions. |
| Editor-in-Chief | Oversee journal submissions and editorial activities. |
| Portal ICT Administrator | View submissions and assign reviewers. |

**Important:** Role-based access must be respected throughout the application. The frontend should display relevant pages and actions according to the authenticated user's role. Actual authorization must also be enforced by the backend.

## 3. Core Features and Pages

### Public Interface
- Homepage
- About/Journal Information
- Contact Us
- Editorial Board
- Reviewers
- Current Issue
- Previous Issues / Archives

### Authentication
- User Registration
- Account Verification
- Login
- Logout

Registration collects:
- Email Address
- Surname
- Other Name

### Author Dashboard
- View personal dashboard.
- Submit new articles.
- View submitted articles.
- Track submission status.

Article submission requires:
- Article Title
- Abstract
- Microsoft Word document containing the full article.

### Editorial and Administration Dashboards

**Reviewer**
- View assigned articles.
- Access relevant review information.

**Managing Editor**
- View submissions.
- Manage article submissions.

**Editor-in-Chief**
- View and oversee submissions.
- Monitor editorial activities.

**Portal ICT Administrator**
- View all submissions.
- Assign reviewers to submitted articles.

Refer to the approved SRS and development specifications for detailed workflows, fields, permissions, and expected interactions.

## 4. Technology Stack

The frontend implementation should follow the technologies and conventions agreed upon by the development team.

The confirmed framework, libraries, package manager, routing solution, and styling approach should be documented here.

Do not introduce additional dependencies or change the agreed stack without consulting the frontend lead.

## 5. Project Structure

The following is a suggested structure. The final implementation should follow the team's approved architecture.

```text
OJMS-Frontend/
│
├── admin/
│   
│
├── assets/
│   ├── assets/
|           ├──styles.css
|──auth/
|
|──author/
|
|
|──editor/
|
|
|──managing-editor/
|
|
|──pages/
|
├── README.md
└── LICENSE
```

### Directory Responsibilities

| Directory | Purpose |
|---|---|
| assets | Images, icons, fonts, and other static resources. |
| pages | Individual application pages. |

## 6. Getting Started

This author dashboard is a static HTML/CSS/JavaScript frontend and does not need a package install. Serve the repository with the development server used by the team; opening the HTML files directly may prevent API requests because of browser origin restrictions.

### Author API integration

The author pages use the API base URL `http://127.0.0.1:8000/api` by default. To change it, define `window.OJMS_API_BASE_URL` before loading `author/api.js`, or update the default in `author/api.js`.

The dashboard expects the sign-in flow to store the bearer access token in session storage using the key `ojms_access_token` (or call `OJMSApi.setToken(token)`). The login page is not part of this frontend section yet. Requests send the token in the `Authorization` header and use the documented `/my-articles`, `/my-articles/{article}`, `/articles`, and `/logout` routes. Manuscript uploads use `FormData` and leave the multipart content type boundary to the browser.

When the frontend and API use different origins, the API server must allow the frontend origin and the `Authorization`, `Accept`, and `Content-Type` headers through CORS.

Never commit API keys, passwords, tokens, or private environment configuration files.

## 7. Development Guidelines

All developers are expected to observe the following guidelines:

### Code Organization
- Write clean, readable, and maintainable code.
- Use reusable components wherever possible.
- Avoid unnecessary duplication.
- Follow the agreed naming conventions.
- Keep files organized according to the project structure.

### UI/UX Implementation
- Follow the approved UI/UX designs.
- Maintain consistency across application pages.
- Ensure the application is responsive.
- Provide appropriate loading, error, success, and empty states.
- Maintain accessibility and usability standards.

### Functionality
- Implement features based on the approved Software Requirements Specification (SRS).
- Follow the System Design Document.
- Follow the Development/Build Specification.
- Ensure forms have appropriate validation.
- Integrate frontend features with the agreed backend API.
- Do not invent API endpoints or request formats.

### Role-Based Access
- Display navigation items according to user roles.
- Restrict access to role-specific pages.
- Ensure users cannot interact with unauthorized features.
- Remember that backend authorization remains the ultimate security control.

## 8. Git Contribution Guidelines

All contributors should follow the repository's agreed collaboration workflow.

General guidelines:

1. Pull the latest changes before beginning work.
2. Work on assigned features using separate branches.
3. Avoid making direct changes to the main branch unless authorized.
4. Write meaningful commit messages.
5. Test your changes before pushing.
6. Communicate with the frontend lead before making major structural changes.
7. Resolve merge conflicts carefully.

### Suggested Branch Naming

```text
feature/feature-name
fix/issue-name
docs/documentation-name
```

### Suggested Commit Messages

```text
feat: implement author registration
feat: create reviewer dashboard
fix: resolve navigation issue
style: improve dashboard responsiveness
docs: update frontend documentation
```

## 9. Project Documentation

The following documents should guide frontend implementation:

| Document | Purpose |
|---|---|
| Software Requirements Specification (SRS) | Defines what the system should do. |
| System Design Document | Explains the system architecture and components. |
| UI/UX Design Document | Defines the visual interface and user experience. |
| Development/Build Specification | Describes what developers are expected to build. |

All implementation decisions should align with the latest approved versions of these documents.

If a requirement is unclear or conflicting, consult the relevant team lead before proceeding.

## 10. Contribution and Communication

Contributors should communicate progress, challenges, and blockers through the designated development communication channel.

Each developer is expected to:
- Understand their assigned responsibilities.
- Work within the agreed project scope.
- Submit completed work for review.
- Collaborate with other team members.
- Report blockers early.

---

## Project Information

**Project:** Role-Based Journal System  
**Repository:** Frontend  
**Project Type:** Web Application  
**Documentation Status:** Initial Draft

This README will be updated as the project architecture, technology stack, and implementation details are finalized.
