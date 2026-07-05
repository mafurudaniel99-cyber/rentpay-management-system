<?php
/**
 * shared/middleware/auth_middleware.php
 * Include at the top of any protected page/endpoint:
 *   require_once __DIR__ . '/../../shared/middleware/auth_middleware.php';
 *   requireRole('LANDLORD');
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header("Location: /authentication/login.php");
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();
    if (($_SESSION['role'] ?? '') !== strtoupper($role)) {
        http_response_code(403);
        die("Access denied. This page is restricted to {$role} accounts.");
    }
}

function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function currentRole(): ?string
{
    return $_SESSION['role'] ?? null;
}
