# WEEK 3 REPORT

## Project Information

- Project Title: MBPHA TeleHealth Consultation System
- Week: Week 3
- Scope Covered in This Report: Day 1 Administrator User Management Foundation

## Week 3 Day 1 Objective

The objective of this implementation was to establish the administrator user management foundation without changing the existing authentication flow, dashboard shell, or broader application functionality.

The work completed today focused only on:

- Administrator dashboard enhancements
- User Management page
- View all users
- Search users
- Filter users by role
- Filter users by account status
- View user details
- Responsive Bootstrap table
- Dashboard statistics
- Pagination for user listing

## Completed Work

### 1. Administrator Dashboard Enhancements

The administrator dashboard was upgraded from placeholder-only content to a Week 3 Day 1 foundation that now includes:

- Live user statistics sourced from the database
- Latest visible user snapshot
- User-management-oriented quick actions
- Updated administrator sidebar navigation
- Week 3 platform status messaging

### 2. Administrator User Management Foundation

An administrator-only user management module was added with:

- `/admin/users`
- `/admin/users/{id}`

This foundation supports:

- Viewing all users from the `users` table joined to `roles`
- Searching by full name or email
- Filtering by role
- Filtering by account status
- Viewing role-aware user details
- Responsive table presentation
- Pagination support

### 3. Security and Access Control

The implementation continues using the existing security foundation:

- Administrator-only route protection through `RoleMiddleware`
- Existing shared authentication flow
- Existing secure session handling
- PDO prepared statements
- Escaped output in views

No new authentication mechanism was introduced.

No business logic outside the administrator user visibility foundation was modified.

## Files Created

- `app/Services/AdminUserService.php`
- `app/Views/admin/users/index.php`
- `app/Views/admin/users/show.php`
- `WEEK3_REPORT.md`

## Files Modified

- `app/Controllers/AdminController.php`
- `app/Core/Router.php`
- `app/Models/User.php`
- `app/Views/admin/dashboard.php`
- `app/Views/partials/dashboard/sidebar.php`
- `public/css/style.css`
- `routes/web.php`
- `README.md`

## Architecture Notes

### Controller Layer

`AdminController` remains thin and now coordinates:

- administrator dashboard rendering
- user management listing
- user detail rendering

### Service Layer

`AdminUserService` was introduced to centralize:

- dashboard user statistics
- latest visible users
- user listing filters
- pagination preparation
- user detail retrieval

### Model Layer

`User` was extended with repository-style query methods to support:

- summary counts
- latest user lookup
- filtered user counts
- filtered paginated listing
- detailed user retrieval

### View Layer

New administrator views were added under `app/Views/admin/users/` and reuse the shared dashboard layout and design system.

## Testing Performed

The following was verified in the browser using the seeded administrator account:

- Administrator login succeeds
- Administrator dashboard loads correctly
- Administrator dashboard shows live user statistics
- `/admin/users` loads correctly
- Search by email works
- Role filter works
- Status filter works
- Empty-state handling works when no results match
- User detail page loads correctly
- Responsive table layout remains usable

## Issues Encountered

### 1. Search Filter Query Error

Issue:

- Searching with filters initially triggered `SQLSTATE[HY093]: Invalid parameter number`

Cause:

- The same named PDO placeholder was reused twice in one search condition while native prepares were enabled

Resolution:

- Separate placeholders were used for the full-name and email search expressions

### 2. User Detail Route Dispatch Error

Issue:

- `/admin/users/{id}` initially produced a fatal dispatch error

Cause:

- Named route captures were being passed directly into `call_user_func_array()`

Resolution:

- Router dispatch was updated to pass ordered parameter values safely to controller methods

## Current Status

Week 3 Day 1 is now complete for the administrator user management foundation.

The system currently supports:

- administrator user visibility
- search and filtering
- detail inspection
- administrator dashboard user statistics

The following are intentionally not included in today’s scope:

- doctor creation
- user creation
- user editing
- status mutation actions
- deletion workflows

## Preparation for Next Work

This foundation is now ready for future controlled administrator functions such as:

- user account editing
- account activation and deactivation controls
- role-specific management workflows
- expanded audit and governance features

## Git

- Recommended commit:
  - `feat(week3-day1): administrator user management foundation`
