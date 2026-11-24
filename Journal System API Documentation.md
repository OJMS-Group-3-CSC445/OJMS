# Peer-Review Journal Management System — API Documentation

**Version:** 1.0  
**Base URL:** `http://127.0.0.1:8000/api`  
**Authentication:** Laravel Sanctum (Bearer Token)

---

## 1. Frontend Integration Guide

### 1.1 Required Request Headers
Include the following headers on every request:
```http
Accept: application/json
Authorization: Bearer <your_access_token>
```
* **JSON Requests:** Include `Content-Type: application/json`.
* **File Upload Requests (`multipart/form-data`):** Do **not** manually set `Content-Type: multipart/form-data` in Axios or `fetch`—let the browser/HTTP client automatically attach the `boundary` parameter.

### 1.2 Standard Response Envelope
Every endpoint returns a uniform JSON structure:
```json
{
  "status": true,
  "message": "Descriptive human-readable message",
  "data": {}
}
```
* `status` (`boolean`): `true` for `2xx` responses, `false` for `4xx`/`5xx` errors.
* `message` (`string | object`): A summary string, or a field-keyed validation error object on `422` responses.
* `data` (`object | array | null`): The requested resource, paginated collection, or `null` on errors.

### 1.3 Paginated Response Structure
List endpoints (`GET /admin/users`, `GET /admin/articles`, `GET /my-articles`, etc.) return Laravel's paginator inside `data` (15 items per page):
```json
{
  "status": true,
  "message": "Articles retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [ ... ],
    "first_page_url": "http://127.0.0.1:8000/api/admin/articles?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "http://127.0.0.1:8000/api/admin/articles?page=1",
    "next_page_url": null,
    "path": "http://127.0.0.1:8000/api/admin/articles",
    "per_page": 15,
    "prev_page_url": null,
    "to": 2,
    "total": 2
  }
}
```
Pass `?page=2` as a query parameter to navigate pages.

### 1.4 System Enums & Constants
Use these exact string values when filtering or submitting payloads:
* **User Roles (`role`):** `portal_admin`, `editor_in_chief`, `reviewer`, `author`
* **Article Statuses (`status`):** `draft`, `pending_review`, `under_review`, `accepted`, `rejected`, `published`
* **Reviewer Assignment Statuses (`status`):** `Pending`, `Completed`
* **Peer-Review Recommendations (`recommendation`):** `accept`, `minor_revision`, `major_revision`, `reject`

### 1.5 Seeded Test Credentials
All seeded accounts share the password **`Password@123`** and have pre-verified emails:

| Role | Email | Notes |
| :--- | :--- | :--- |
| `portal_admin` | `admin@ojsm.com` | Primary Portal Admin |
| `portal_admin` | `ict.support@ojsm.com` | Secondary Portal Admin |
| `editor_in_chief` | `eic.main@ojsm.com` | Primary Editor-in-Chief |
| `editor_in_chief` | `eic.associate@ojsm.com` | Associate Editor-in-Chief |
| `reviewer` | `reviewer1@ojsm.com` – `reviewer5@ojsm.com` | 5 Peer Reviewers |
| `author` | `author1@ojsm.com` – `author5@ojsm.com` | 5 Authors |

---

## 2. Authentication Endpoints

### 2.1 Register User
Creates a new user account (defaults to the `author` role unless configured otherwise) and triggers an email verification link.
* **Endpoint:** `POST /register`
* **Auth Required:** No

**Request Body (`application/json`):**
| Field | Type | Required | Rules |
| :--- | :--- | :--- | :--- |
| `first_name` | string | Yes | Max 255 chars |
| `last_name` | string | Yes | Max 255 chars |
| `middle_name` | string | No | Max 255 chars |
| `email` | string | Yes | Valid email, unique in `users` |
| `phone` | string | Yes | Unique phone number |
| `password` | string | Yes | Min 8 chars, must match `password_confirmation` |
| `password_confirmation` | string | Yes | Must match `password` |

**Example Response (`201 Created`):**
```json
{
  "status": true,
  "message": "Registration successful. Please check your email to verify your account.",
  "data": {
    "user": {
      "id": 15,
      "first_name": "Ada",
      "last_name": "Lovelace",
      "email": "ada@ojsm.com",
      "phone": "08099998888",
      "role": "author",
      "created_at": "2026-11-21T20:00:00.000000Z"
    }
  }
}
```

---

### 2.2 Login
Authenticates a user and returns a Sanctum Bearer token along with the user profile (use `data.user.role` to determine which UI navigation/buttons to render).
* **Endpoint:** `POST /login`
* **Auth Required:** No

**Request Body (`application/json`):**
```json
{
  "email": "author1@ojsm.com",
  "password": "Password@123"
}
```

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Login successful",
  "data": {
    "token": "1|xYz987654321AbCdEfGhIjKlMnOpQrStUvWxYz",
    "token_type": "Bearer",
    "user": {
      "id": 10,
      "first_name": "David",
      "last_name": "Oluwaseun",
      "middle_name": null,
      "email": "author1@ojsm.com",
      "phone": "08010000010",
      "role": "author",
      "email_verified_at": "2026-11-21T18:00:00.000000Z"
    }
  }
}
```

---

### 2.3 Logout
Revokes the caller's current Sanctum access token.
* **Endpoint:** `POST /logout`
* **Auth Required:** Yes (`Bearer <token>`)

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Logged out successfully",
  "data": null
}
```

---

### 2.4 Forgot Password
Sends a password reset token/link to the user's email address.
* **Endpoint:** `POST /forgot-password`
* **Auth Required:** No

**Request Body (`application/json`):**
```json
{
  "email": "author1@ojsm.com"
}
```

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Password reset link sent to your email.",
  "data": null
}
```

---

### 2.5 Reset Password
Resets the user's password using the token received via email.
* **Endpoint:** `POST /reset-password`
* **Auth Required:** No

**Request Body (`application/json`):**
```json
{
  "token": "reset_token_from_email",
  "email": "author1@ojsm.com",
  "password": "NewPassword@123",
  "password_confirmation": "NewPassword@123"
}
```

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Password has been reset successfully.",
  "data": null
}
```

---

### 2.6 Verify Email
Marks a user's email address as verified when they click the signed URL sent during registration.
* **Endpoint:** `GET /email/verify/{id}/{hash}`
* **Auth Required:** Signed URL (`expires` and `signature` query params)

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Email verified successfully.",
  "data": null
}
```

---

## 3. Author / Shared User Endpoints
*Authors share the main journal interface and unlock manuscript submission and tracking capabilities. All endpoints require `Authorization: Bearer <token>` with `role: author`.*

### 3.1 List My Submissions
Retrieves a paginated list of articles submitted by the currently logged-in author, including assignment and review counts.
* **Endpoint:** `GET /my-articles`
* **Auth Required:** Yes (`role: author`)

**Query Parameters:**
| Param | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `status` | string | No | Filter by status (`draft`, `pending_review`, `under_review`, `accepted`, `rejected`, `published`) |
| `page` | integer | No | Page number for pagination |

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Your articles retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "author_id": 10,
        "issue_id": null,
        "title": "Deep Learning Approaches in Medical Imaging",
        "authors": "David Oluwaseun, Grace Udo",
        "abstract": "A comparative study of convolutional architectures for diagnostic radiology.",
        "document_path": "articles/6cQIHM6Z1t2dsjWYoG58wi8bj1Nt88rUB0c7LYWJ.pdf",
        "document_url": "http://127.0.0.1:8000/storage/articles/6cQIHM6Z1t2dsjWYoG58wi8bj1Nt88rUB0c7LYWJ.pdf",
        "original_filename": "medical_imaging_v1.pdf",
        "file_size": 1048576,
        "status": "under_review",
        "submitted_at": "2026-11-21T19:14:23.000000Z",
        "decided_by": null,
        "decision_remarks": null,
        "decided_at": null,
        "created_at": "2026-11-21T19:14:23.000000Z",
        "updated_at": "2026-11-21T19:20:10.000000Z",
        "assignments_count": 2,
        "reviews_count": 1
      }
    ],
    "per_page": 15,
    "total": 1
  }
}
```

---

### 3.2 View Single Submission & Blinded Feedback
Retrieves full details of a specific article owned by the logged-in author, along with **blinded** reviewer feedback (`reviewer_id` and reviewer personal info are intentionally stripped to preserve blind peer review).
* **Endpoint:** `GET /my-articles/{article}`
* **Auth Required:** Yes (`role: author`)
* **Path Parameter:** `article` (integer — Article ID)

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Article details retrieved successfully",
  "data": {
    "id": 1,
    "author_id": 10,
    "issue_id": null,
    "title": "Deep Learning Approaches in Medical Imaging",
    "authors": "David Oluwaseun, Grace Udo",
    "abstract": "A comparative study of convolutional architectures for diagnostic radiology.",
    "document_path": "articles/6cQIHM6Z1t2dsjWYoG58wi8bj1Nt88rUB0c7LYWJ.pdf",
    "document_url": "http://127.0.0.1:8000/storage/articles/6cQIHM6Z1t2dsjWYoG58wi8bj1Nt88rUB0c7LYWJ.pdf",
    "original_filename": "medical_imaging_v1.pdf",
    "file_size": 1048576,
    "status": "accepted",
    "submitted_at": "2026-11-21T19:14:23.000000Z",
    "decided_by": 3,
    "decision_remarks": "Strong methodology and clear empirical results.",
    "decided_at": "2026-11-21T20:15:00.000000Z",
    "reviews": [
      {
        "id": 1,
        "article_id": 1,
        "comments": "Well-structured manuscript with solid empirical validation. Minor formatting adjustments recommended.",
        "recommendation": "accept",
        "created_at": "2026-11-21T20:05:00.000000Z"
      }
    ]
  }
}
```

---

### 3.3 Submit New Manuscript
Uploads a new manuscript document and creates an article record with `status: "pending_review"` and `submitted_at` set to the current timestamp.
* **Endpoint:** `POST /articles`
* **Auth Required:** Yes (`role: author`)
* **Content-Type:** `multipart/form-data`

**Form-Data Fields:**
| Field | Type | Required | Rules |
| :--- | :--- | :--- | :--- |
| `title` | string | Yes | Max 255 characters |
| `abstract` | string | Yes | Full text abstract |
| `authors` | string | No | Comma-separated co-authors string |
| `manuscript` | file | Yes | Allowed formats: `pdf`, `doc`, `docx`. Max size: `10MB` (`10240` KB) |

**Example Response (`201 Created`):**
```json
{
  "status": true,
  "message": "Manuscript submitted successfully",
  "data": {
    "id": 2,
    "author_id": 10,
    "title": "Transformer Architectures for Low-Resource Languages",
    "authors": "David Oluwaseun",
    "abstract": "Exploring tokenization efficiency across West African languages.",
    "document_path": "articles/9aBcDeFgHiJkLmNoPqRsTuVwXyZ123456.pdf",
    "document_url": "http://127.0.0.1:8000/storage/articles/9aBcDeFgHiJkLmNoPqRsTuVwXyZ123456.pdf",
    "original_filename": "transformer_paper.pdf",
    "file_size": 2150400,
    "status": "pending_review",
    "submitted_at": "2026-11-21T20:30:00.000000Z",
    "created_at": "2026-11-21T20:30:00.000000Z",
    "updated_at": "2026-11-21T20:30:00.000000Z"
  }
}
```

---

### 3.4 Upload Revised Manuscript
Replaces the manuscript file (deleting the old file from storage) and optionally updates metadata (`title`, `abstract`, `authors`). Only permitted while the article's status is `draft`, `pending_review`, or `under_review`.
* **Endpoint:** `POST /articles/{article}/revise`
* **Auth Required:** Yes (`role: author`)
* **Content-Type:** `multipart/form-data`
* **Path Parameter:** `article` (integer — Article ID)

**Form-Data Fields:**
| Field | Type | Required | Rules |
| :--- | :--- | :--- | :--- |
| `manuscript` | file | Yes | Allowed formats: `pdf`, `doc`, `docx`. Max size: `10MB` |
| `title` | string | No | Max 255 characters |
| `abstract` | string | No | Updated abstract |
| `authors` | string | No | Updated co-authors |

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Revised manuscript uploaded successfully",
  "data": {
    "id": 2,
    "author_id": 10,
    "title": "Transformer Architectures for Low-Resource Languages (Revised)",
    "authors": "David Oluwaseun",
    "abstract": "Exploring tokenization efficiency across West African languages.",
    "document_path": "articles/revised_998877665544332211.pdf",
    "document_url": "http://127.0.0.1:8000/storage/articles/revised_998877665544332211.pdf",
    "original_filename": "transformer_paper_v2.pdf",
    "file_size": 2210000,
    "status": "under_review",
    "submitted_at": "2026-11-21T20:30:00.000000Z",
    "updated_at": "2026-11-21T21:00:00.000000Z"
  }
}
```

---

## 4. Portal Admin Endpoints
*All endpoints require `Authorization: Bearer <token>` with `role: portal_admin`.*

### 4.1 Admin Dashboard Overview
Returns system-wide statistics across users, articles, and peer reviews, plus the 5 most recent submissions and 5 most recently registered users.
* **Endpoint:** `GET /admin/dashboard`
* **Auth Required:** Yes (`role: portal_admin`)

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Admin dashboard data retrieved",
  "data": {
    "stats": {
      "users": {
        "total_users": 14,
        "verified_users": 14,
        "authors": 5,
        "reviewers": 5,
        "editors_in_chief": 2,
        "portal_admins": 2
      },
      "articles": {
        "total": 2,
        "draft": 0,
        "pending_review": 1,
        "under_review": 1,
        "accepted": 0,
        "published": 0,
        "rejected": 0
      },
      "reviews_and_assignments": {
        "pending_assignments": 1,
        "completed_assignments": 0,
        "total_reviews": 0
      }
    },
    "recent_submissions": [
      {
        "id": 2,
        "author_id": 10,
        "title": "Transformer Architectures for Low-Resource Languages",
        "status": "pending_review",
        "created_at": "2026-11-21T20:30:00.000000Z",
        "author": {
          "id": 10,
          "first_name": "David",
          "last_name": "Oluwaseun",
          "email": "author1@ojsm.com"
        }
      }
    ],
    "recent_users": [
      {
        "id": 14,
        "first_name": "Samuel",
        "last_name": "Mensah",
        "email": "author5@ojsm.com",
        "role": "author",
        "email_verified_at": "2026-11-21T18:00:00.000000Z",
        "created_at": "2026-11-21T18:00:00.000000Z"
      }
    ]
  }
}
```

---

### 4.2 List Users
Returns a paginated directory of all users on the platform.
* **Endpoint:** `GET /admin/users`
* **Auth Required:** Yes (`role: portal_admin`)

**Query Parameters:**
| Param | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `role` | string | No | Filter by role (`portal_admin`, `editor_in_chief`, `reviewer`, `author`) |
| `search` | string | No | Search by `first_name`, `last_name`, or `email` |
| `page` | integer | No | Page number |

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Users retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 5,
        "first_name": "Ngozi",
        "last_name": "Eze",
        "middle_name": null,
        "email": "reviewer1@ojsm.com",
        "phone": "08010000005",
        "role": "reviewer",
        "email_verified_at": "2026-11-21T18:00:00.000000Z",
        "created_at": "2026-11-21T18:00:00.000000Z"
      }
    ],
    "per_page": 15,
    "total": 5
  }
}
```

---

### 4.3 Create User
Allows a Portal Admin to create a pre-verified user account with a specific role (e.g., onboarding a new `reviewer` or `editor_in_chief`).
* **Endpoint:** `POST /admin/users`
* **Auth Required:** Yes (`role: portal_admin`)

**Request Body (`application/json`):**
```json
{
  "first_name": "Chika",
  "last_name": "Obi",
  "middle_name": "M.",
  "email": "chika.obi@ojsm.com",
  "phone": "08022223333",
  "password": "Password@123",
  "role": "reviewer"
}
```

**Example Response (`201 Created`):**
```json
{
  "status": true,
  "message": "User created successfully",
  "data": {
    "id": 16,
    "first_name": "Chika",
    "last_name": "Obi",
    "middle_name": "M.",
    "email": "chika.obi@ojsm.com",
    "phone": "08022223333",
    "role": "reviewer",
    "email_verified_at": "2026-11-21T20:45:00.000000Z",
    "created_at": "2026-11-21T20:45:00.000000Z"
  }
}
```

---

### 4.4 Update User Role
Promotes or reassigns a user's system role.
* **Endpoint:** `PATCH /admin/users/{user}/role`
* **Auth Required:** Yes (`role: portal_admin`)
* **Path Parameter:** `user` (integer — User ID)

**Request Body (`application/json`):**
```json
{
  "role": "editor_in_chief"
}
```

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "User role updated successfully",
  "data": {
    "id": 16,
    "first_name": "Chika",
    "last_name": "Obi",
    "email": "chika.obi@ojsm.com",
    "role": "editor_in_chief"
  }
}
```

---

### 4.5 List All Articles (Admin Oversight)
Retrieves a paginated list of all manuscripts across all statuses, including author details and counts of reviewer assignments and submitted reviews.
* **Endpoint:** `GET /admin/articles`
* **Auth Required:** Yes (`role: portal_admin`)

**Query Parameters:**
| Param | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `status` | string | No | Filter by article status (`pending_review`, `under_review`, `accepted`, etc.) |
| `search` | string | No | Search by article `title` or author `first_name`, `last_name`, `email` |
| `page` | integer | No | Page number |

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Articles retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "author_id": 10,
        "title": "Deep Learning Approaches in Medical Imaging",
        "abstract": "A comparative study of convolutional architectures...",
        "document_path": "articles/6cQIHM6Z1t2dsjWYoG58wi8bj1Nt88rUB0c7LYWJ.pdf",
        "document_url": "http://127.0.0.1:8000/storage/articles/6cQIHM6Z1t2dsjWYoG58wi8bj1Nt88rUB0c7LYWJ.pdf",
        "original_filename": "medical_imaging_v1.pdf",
        "file_size": 1048576,
        "status": "under_review",
        "submitted_at": "2026-11-21T19:14:23.000000Z",
        "assignments_count": 1,
        "reviews_count": 0,
        "author": {
          "id": 10,
          "first_name": "David",
          "last_name": "Oluwaseun",
          "email": "author1@ojsm.com"
        }
      }
    ],
    "per_page": 15,
    "total": 1
  }
}
```

---

### 4.6 View Single Article Details (Admin)
Retrieves full manuscript details, author info, all reviewer assignments (including who assigned them), and all submitted reviews.
* **Endpoint:** `GET /admin/articles/{article}`
* **Auth Required:** Yes (`role: portal_admin`)
* **Path Parameter:** `article` (integer — Article ID)

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Article details retrieved successfully",
  "data": {
    "id": 1,
    "author_id": 10,
    "title": "Deep Learning Approaches in Medical Imaging",
    "abstract": "A comparative study of convolutional architectures...",
    "document_path": "articles/6cQIHM6Z1t2dsjWYoG58wi8bj1Nt88rUB0c7LYWJ.pdf",
    "document_url": "http://127.0.0.1:8000/storage/articles/6cQIHM6Z1t2dsjWYoG58wi8bj1Nt88rUB0c7LYWJ.pdf",
    "original_filename": "medical_imaging_v1.pdf",
    "file_size": 1048576,
    "status": "under_review",
    "submitted_at": "2026-11-21T19:14:23.000000Z",
    "author": {
      "id": 10,
      "first_name": "David",
      "last_name": "Oluwaseun",
      "middle_name": null,
      "email": "author1@ojsm.com",
      "phone": "08010000010"
    },
    "assignments": [
      {
        "id": 1,
        "article_id": 1,
        "reviewer_id": 5,
        "assigned_by": 1,
        "status": "Pending",
        "assigned_at": "2026-11-21T19:30:00.000000Z",
        "reviewer": {
          "id": 5,
          "first_name": "Ngozi",
          "last_name": "Eze",
          "email": "reviewer1@ojsm.com"
        },
        "admin": {
          "id": 1,
          "first_name": "Ojsm",
          "last_name": "Admin",
          "role": "portal_admin"
        }
      }
    ],
    "reviews": []
  }
}
```

---

### 4.7 Assign Reviewer to Article (Admin)
Assigns a user with `role: reviewer` to evaluate a manuscript.
* **Endpoint:** `POST /admin/articles/{article}/assign-reviewer`
* **Auth Required:** Yes (`role: portal_admin`)
* **Path Parameter:** `article` (integer — Article ID)
* **Side Effects:**
  * Creates a `ReviewerAssignment` with `status: "Pending"`.
  * Automatically updates the article's status from `pending_review` to `under_review`.
  * Rejects if the target user is not a `reviewer` (`422`), is the article's own author (`422`), or is already assigned to the article (`409 Conflict`).

**Request Body (`application/json`):**
```json
{
  "reviewer_id": 5
}
```

**Example Response (`201 Created`):**
```json
{
  "status": true,
  "message": "Reviewer assigned successfully",
  "data": {
    "id": 1,
    "article_id": 1,
    "reviewer_id": 5,
    "assigned_by": 1,
    "status": "Pending",
    "assigned_at": "2026-11-21T19:30:00.000000Z",
    "article": {
      "id": 1,
      "title": "Deep Learning Approaches in Medical Imaging",
      "status": "under_review"
    },
    "reviewer": {
      "id": 5,
      "first_name": "Ngozi",
      "last_name": "Eze",
      "email": "reviewer1@ojsm.com"
    },
    "admin": {
      "id": 1,
      "first_name": "Ojsm",
      "last_name": "Admin",
      "role": "portal_admin"
    }
  }
}
```

---

### 4.8 Monitor Reviewer Activities Feed
Returns a paginated audit feed of all reviewer assignments across the system.
* **Endpoint:** `GET /admin/reviewer-activities`
* **Auth Required:** Yes (`role: portal_admin`)

**Query Parameters:**
| Param | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `status` | string | No | Filter by assignment status (`Pending` or `Completed`) |
| `reviewer_id` | integer | No | Filter assignments for a specific reviewer ID |
| `page` | integer | No | Page number |

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Reviewer activities retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "article_id": 1,
        "reviewer_id": 5,
        "assigned_by": 1,
        "status": "Pending",
        "assigned_at": "2026-11-21T19:30:00.000000Z",
        "article": {
          "id": 1,
          "title": "Deep Learning Approaches in Medical Imaging",
          "status": "under_review",
          "author_id": 10,
          "author": {
            "id": 10,
            "first_name": "David",
            "last_name": "Oluwaseun"
          }
        },
        "reviewer": {
          "id": 5,
          "first_name": "Ngozi",
          "last_name": "Eze",
          "email": "reviewer1@ojsm.com"
        },
        "admin": {
          "id": 1,
          "first_name": "Ojsm",
          "last_name": "Admin",
          "role": "portal_admin"
        }
      }
    ],
    "per_page": 15,
    "total": 1
  }
}
```

---

## 5. Editor-in-Chief Endpoints
*All endpoints require `Authorization: Bearer <token>` with `role: editor_in_chief`.*

### 5.1 Editor Dashboard Overview
Returns editorial workflow metrics and up to 10 active manuscripts currently in `pending_review` or `under_review` requiring editorial action.
* **Endpoint:** `GET /editor/dashboard`
* **Auth Required:** Yes (`role: editor_in_chief`)

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Editor dashboard data retrieved successfully",
  "data": {
    "stats": {
      "articles": {
        "total": 2,
        "pending_review": 1,
        "under_review": 1,
        "accepted": 0,
        "rejected": 0,
        "published": 0
      },
      "peer_review": {
        "pending_assignments": 1,
        "completed_assignments": 0,
        "total_reviews": 0
      }
    },
    "action_queue": [
      {
        "id": 1,
        "author_id": 10,
        "title": "Deep Learning Approaches in Medical Imaging",
        "status": "under_review",
        "created_at": "2026-11-21T19:14:23.000000Z",
        "assignments_count": 1,
        "reviews_count": 0,
        "author": {
          "id": 10,
          "first_name": "David",
          "last_name": "Oluwaseun",
          "email": "author1@ojsm.com"
        }
      }
    ]
  }
}
```

---

### 5.2 List All Articles (Editorial Queue)
Lists all submissions with optional status and search filtering.
* **Endpoint:** `GET /editor/articles`
* **Auth Required:** Yes (`role: editor_in_chief`)

**Query Parameters:**
| Param | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `status` | string | No | Filter by status (`pending_review`, `under_review`, `accepted`, `rejected`, `published`) |
| `search` | string | No | Search by article `title` or author name/email |
| `page` | integer | No | Page number |

**Response:** Same paginated article structure as `GET /admin/articles`.

---

### 5.3 View Single Article Details (Unblinded)
Retrieves complete article metadata, assigned reviewers, decision audit info (`decider`), and **unblinded** peer reviews (including reviewer identity).
* **Endpoint:** `GET /editor/articles/{article}`
* **Auth Required:** Yes (`role: editor_in_chief`)
* **Path Parameter:** `article` (integer — Article ID)

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Article details retrieved successfully",
  "data": {
    "id": 1,
    "author_id": 10,
    "title": "Deep Learning Approaches in Medical Imaging",
    "abstract": "A comparative study of convolutional architectures...",
    "document_path": "articles/6cQIHM6Z1t2dsjWYoG58wi8bj1Nt88rUB0c7LYWJ.pdf",
    "document_url": "http://127.0.0.1:8000/storage/articles/6cQIHM6Z1t2dsjWYoG58wi8bj1Nt88rUB0c7LYWJ.pdf",
    "status": "under_review",
    "decided_by": null,
    "decision_remarks": null,
    "decided_at": null,
    "author": {
      "id": 10,
      "first_name": "David",
      "last_name": "Oluwaseun",
      "middle_name": null,
      "email": "author1@ojsm.com",
      "phone": "08010000010"
    },
    "decider": null,
    "assignments": [
      {
        "id": 1,
        "article_id": 1,
        "reviewer_id": 5,
        "assigned_by": 3,
        "status": "Completed",
        "assigned_at": "2026-11-21T19:30:00.000000Z",
        "reviewer": {
          "id": 5,
          "first_name": "Ngozi",
          "last_name": "Eze",
          "email": "reviewer1@ojsm.com"
        },
        "admin": {
          "id": 3,
          "first_name": "Amina",
          "last_name": "Yusuf",
          "role": "editor_in_chief"
        }
      }
    ],
    "reviews": [
      {
        "id": 1,
        "article_id": 1,
        "reviewer_id": 5,
        "comments": "Well-structured manuscript with solid empirical validation.",
        "recommendation": "accept",
        "reviewed_at": "2026-11-21T20:05:00.000000Z",
        "reviewer": {
          "id": 5,
          "first_name": "Ngozi",
          "last_name": "Eze",
          "email": "reviewer1@ojsm.com"
        }
      }
    ]
  }
}
```

---

### 5.4 Assign Reviewer (Editor-in-Chief)
Assigns an additional or replacement reviewer to a manuscript. Blocked if the article already has a final status (`accepted`, `rejected`, or `published`).
* **Endpoint:** `POST /editor/articles/{article}/assign-reviewer`
* **Auth Required:** Yes (`role: editor_in_chief`)
* **Path Parameter:** `article` (integer — Article ID)

**Request Body (`application/json`):**
```json
{
  "reviewer_id": 6
}
```

**Response (`201 Created`):** Identical structure to `POST /admin/articles/{article}/assign-reviewer`.

---

### 5.5 Unassign Pending Reviewer
Removes a `Pending` reviewer assignment from an article.
* **Endpoint:** `DELETE /editor/reviewer-assignments/{assignment}`
* **Auth Required:** Yes (`role: editor_in_chief`)
* **Path Parameter:** `assignment` (integer — `ReviewerAssignment` ID)
* **Side Effects:**
  * Returns `422` if the assignment status is already `Completed`.
  * If no other assignments remain on the article after deletion, the article's status automatically reverts from `under_review` back to `pending_review`.

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Reviewer unassigned successfully",
  "data": null
}
```

---

### 5.6 Issue Final Editorial Decision
Records the Editor-in-Chief's verdict (`accepted`, `rejected`, or `published`) along with optional remarks and audit metadata (`decided_by`, `decided_at`).
* **Endpoint:** `PATCH /editor/articles/{article}/decision`
* **Auth Required:** Yes (`role: editor_in_chief`)
* **Path Parameter:** `article` (integer — Article ID)

**Request Body (`application/json`):**
| Field | Type | Required | Rules |
| :--- | :--- | :--- | :--- |
| `status` | string | Yes | Must be one of: `accepted`, `rejected`, `published` |
| `decision_remarks` | string | No | Editorial notes visible to the author and audit trail |

**Example Payload:**
```json
{
  "status": "published",
  "decision_remarks": "Accepted by peer reviewers and approved for immediate publication."
}
```

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Article marked as published successfully",
  "data": {
    "id": 1,
    "author_id": 10,
    "title": "Deep Learning Approaches in Medical Imaging",
    "status": "published",
    "decided_by": 3,
    "decision_remarks": "Accepted by peer reviewers and approved for immediate publication.",
    "decided_at": "2026-11-21T20:50:00.000000Z",
    "author": {
      "id": 10,
      "first_name": "David",
      "last_name": "Oluwaseun",
      "email": "author1@ojsm.com"
    },
    "decider": {
      "id": 3,
      "first_name": "Amina",
      "last_name": "Yusuf",
      "email": "eic.main@ojsm.com"
    }
  }
}
```

---

## 6. Reviewer Endpoints
*All endpoints require `Authorization: Bearer <token>` with `role: reviewer`. Reviewers can only view and submit feedback on assignments belonging to their own user ID.*

### 6.1 Reviewer Dashboard Overview
Returns counts of `Pending` vs. `Completed` assignments for the logged-in reviewer and up to 5 pending manuscripts awaiting review.
* **Endpoint:** `GET /reviewer/dashboard`
* **Auth Required:** Yes (`role: reviewer`)

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Reviewer dashboard retrieved successfully",
  "data": {
    "stats": {
      "total_assigned": 2,
      "pending": 1,
      "completed": 1
    },
    "pending_assignments": [
      {
        "id": 2,
        "article_id": 2,
        "reviewer_id": 5,
        "assigned_by": 3,
        "status": "Pending",
        "assigned_at": "2026-11-21T20:10:00.000000Z",
        "article": {
          "id": 2,
          "title": "Transformer Architectures for Low-Resource Languages",
          "abstract": "Exploring tokenization efficiency across West African languages.",
          "status": "under_review",
          "submitted_at": "2026-11-21T20:30:00.000000Z"
        },
        "admin": {
          "id": 3,
          "first_name": "Amina",
          "last_name": "Yusuf",
          "role": "editor_in_chief"
        }
      }
    ]
  }
}
```

---

### 6.2 List Assigned Manuscripts
Retrieves a paginated list of all manuscripts assigned to the logged-in reviewer, including `document_url` for downloading/viewing the manuscript file.
* **Endpoint:** `GET /reviewer/assignments`
* **Auth Required:** Yes (`role: reviewer`)

**Query Parameters:**
| Param | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `status` | string | No | Filter by `Pending` or `Completed` |
| `page` | integer | No | Page number |

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Assigned manuscripts retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 2,
        "article_id": 2,
        "reviewer_id": 5,
        "assigned_by": 3,
        "status": "Pending",
        "assigned_at": "2026-11-21T20:10:00.000000Z",
        "article": {
          "id": 2,
          "title": "Transformer Architectures for Low-Resource Languages",
          "abstract": "Exploring tokenization efficiency across West African languages.",
          "document_path": "articles/9aBcDeFgHiJkLmNoPqRsTuVwXyZ123456.pdf",
          "document_url": "http://127.0.0.1:8000/storage/articles/9aBcDeFgHiJkLmNoPqRsTuVwXyZ123456.pdf",
          "original_filename": "transformer_paper.pdf",
          "file_size": 2150400,
          "status": "under_review",
          "submitted_at": "2026-11-21T20:30:00.000000Z"
        },
        "admin": {
          "id": 3,
          "first_name": "Amina",
          "last_name": "Yusuf",
          "role": "editor_in_chief"
        }
      }
    ],
    "per_page": 15,
    "total": 1
  }
}
```

---

### 6.3 View Single Assignment & Existing Review
Retrieves the assigned manuscript's full details, downloadable document URL, and any review previously submitted by this reviewer (`review` is `null` if still `Pending`).
* **Endpoint:** `GET /reviewer/assignments/{assignment}`
* **Auth Required:** Yes (`role: reviewer`)
* **Path Parameter:** `assignment` (integer — `ReviewerAssignment` ID)

**Example Response (`200 OK`):**
```json
{
  "status": true,
  "message": "Assignment details retrieved successfully",
  "data": {
    "assignment": {
      "id": 2,
      "article_id": 2,
      "reviewer_id": 5,
      "assigned_by": 3,
      "status": "Pending",
      "assigned_at": "2026-11-21T20:10:00.000000Z",
      "article": {
        "id": 2,
        "title": "Transformer Architectures for Low-Resource Languages",
        "authors": "David Oluwaseun",
        "abstract": "Exploring tokenization efficiency across West African languages.",
        "document_path": "articles/9aBcDeFgHiJkLmNoPqRsTuVwXyZ123456.pdf",
        "document_url": "http://127.0.0.1:8000/storage/articles/9aBcDeFgHiJkLmNoPqRsTuVwXyZ123456.pdf",
        "original_filename": "transformer_paper.pdf",
        "file_size": 2150400,
        "status": "under_review",
        "submitted_at": "2026-11-21T20:30:00.000000Z"
      },
      "admin": {
        "id": 3,
        "first_name": "Amina",
        "last_name": "Yusuf",
        "role": "editor_in_chief"
      }
    },
    "review": null
  }
}
```

---

### 6.4 Submit Peer Review
Submits the reviewer's evaluation comments and recommendation, marking the assignment as `Completed`.
* **Endpoint:** `POST /reviewer/assignments/{assignment}/review`
* **Auth Required:** Yes (`role: reviewer`)
* **Path Parameter:** `assignment` (integer — `ReviewerAssignment` ID)
* **Side Effects:**
  * Creates/updates the record in `reviewer_reviews` with `reviewed_at`.
  * Updates the `ReviewerAssignment` status to `Completed`.
  * Returns `409 Conflict` if the assignment has already been completed.

**Request Body (`application/json`):**
| Field | Type | Required | Rules |
| :--- | :--- | :--- | :--- |
| `comments` | string | Yes | Min 10 characters |
| `recommendation` | string | Yes | Must be one of: `accept`, `minor_revision`, `major_revision`, `reject` |

**Example Payload:**
```json
{
  "comments": "Well-structured manuscript with solid empirical validation. Minor formatting adjustments recommended in Section 3.",
  "recommendation": "accept"
}
```

**Example Response (`201 Created`):**
```json
{
  "status": true,
  "message": "Peer review submitted successfully",
  "data": {
    "assignment": {
      "id": 2,
      "article_id": 2,
      "reviewer_id": 5,
      "assigned_by": 3,
      "status": "Completed",
      "assigned_at": "2026-11-21T20:10:00.000000Z",
      "updated_at": "2026-11-21T21:10:00.000000Z"
    },
    "review": {
      "id": 2,
      "article_id": 2,
      "reviewer_id": 5,
      "assigned_by": 3,
      "assigned_at": "2026-11-21T20:10:00.000000Z",
      "comments": "Well-structured manuscript with solid empirical validation. Minor formatting adjustments recommended in Section 3.",
      "recommendation": "accept",
      "reviewed_at": "2026-11-21T21:10:00.000000Z",
      "created_at": "2026-11-21T21:10:00.000000Z",
      "updated_at": "2026-11-21T21:10:00.000000Z"
    }
  }
}
```

---

## 7. Error Responses & HTTP Status Codes

| Status Code | Meaning | Example Response |
| :--- | :--- | :--- |
| **`401 Unauthorized`** | Missing, expired, or invalid Sanctum Bearer token. | `{"message": "Unauthenticated."}` |
| **`403 Forbidden`** | User lacks the required role or does not own the requested resource. | `{"status": false, "message": "Unauthorized access to this assignment.", "data": null}` |
| **`404 Not Found`** | The requested ID (`article`, `user`, `assignment`) does not exist. | `{"status": false, "message": "The requested resource was not found.", "data": null}` |
| **`409 Conflict`** | Duplicate action (e.g., assigning the same reviewer twice or re-submitting a completed review). | `{"status": false, "message": "This reviewer is already assigned to this article.", "data": null}` |
| **`422 Unprocessable Entity`** | Validation failure or invalid state transition. | See validation example below |

**Example `422` Validation Error Response:**
```json
{
  "status": false,
  "message": {
    "title": [
      "The title field is required."
    ],
    "manuscript": [
      "The manuscript field must be a file of type: pdf, doc, docx."
    ]
  },
  "data": null
}
```

---

## 8. Planned Upcoming Endpoints (Public Journal Reader Feed)
*Currently deferred until core role testing completes:*
* `GET /api/articles` — Public paginated feed of articles with `status: "published"`.
* `GET /api/articles/{article}` — Public single published article view.