# Project Updates and Fixes

This README documents the changes and fixes applied to the project.

## 1. Database Setup

To get the application running, you need to set up the database.

**Database Name:** `gestion_flotte`

**Instructions:**
1.  Ensure you have a MySQL server running (e.g., via XAMPP).
2.  Create a database named `gestion_flotte`.
3.  Import the `database.sql` file into your `gestion_flotte` database. This file contains all the necessary `CREATE TABLE` statements and initial data for lookup tables.

    *Example command for MySQL CLI:*
    ```bash
    mysql -u your_username -p gestion_flotte < database.sql
    ```
    *(Replace `your_username` with your MySQL username. You will be prompted for the password.)*

## 2. Backend Fixes

### 2.1. Database Connection
-   **`include/database.php`**: The database connection password for the `root` user has been set to an empty string (`$password = '';`) to resolve "Access denied" errors, common in local development environments.

### 2.2. Login Functionality
-   **`index.php`**:
    -   The `require_once 'include/password_utils.php';` and `verify_password()` function calls have been replaced with PHP's built-in `password_verify()` for secure password checking.
    -   The user's `fonction` (function/role) is now correctly retrieved from the `users` table and stored in the `$_SESSION['utilisateur']` array upon successful login.
    -   Error handling in the login process has been improved to display detailed `PDOException` messages for easier debugging.

### 2.3. Logout Functionality
-   **`deconnection.php`**: The redirection after logout has been corrected from `connection.php` (which did not exist) to `index.php` to ensure users are properly returned to the login page.

### 2.4. Missing Files and Placeholders
-   **`include/nav.php`**: A basic navigation bar file has been created to resolve "Failed to open stream" errors. This provides essential navigation links to various parts of the application.
-   **`get_flotte.php`**: A new file has been created to handle AJAX requests for fetching individual 'flotte' (fleet) data, returning it as JSON. (Note: `modifier_flotte.php` and further `flotte.php` modal improvements are still pending).

## 3. Admin Dashboard Customization

### 3.1. Conditional Display of "Utilisateurs" Card
-   **`admin.php`**: The "Utilisateurs" card on the admin dashboard is now conditionally displayed. It will be hidden for admin users whose `fonction` is set to 'AREF'. This allows for role-based visibility of certain features.

## 4. Pending Tasks

-   Complete the implementation of `modifier_flotte.php`.
-   Improve the "Ajouter une flotte" modal in `flotte.php` to use dropdowns populated from the database for "Type abonnement" and "Direction".
-   Address styling and animation improvements (as per user request, but pending functional completion).
