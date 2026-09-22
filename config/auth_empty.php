<?php
declare(strict_types=1);

const AdminUsername = 'AnAccountNameToLoginWith';
const AdminPassword = 'VerySecurePassword';

function IsAdminLoggedIn(): bool
{
	return ($_SESSION['IsAdminLoggedIn'] ?? false) === true;
}

function LoginAdmin(string $Username, string $Password): bool
{
	if (!hash_equals(AdminUsername, $Username) || !hash_equals(AdminPassword, $Password)) {
		return false;
	}

	session_regenerate_id(true);
	$_SESSION['IsAdminLoggedIn'] = true;
	return true;
}

function LogoutAdmin(): void
{
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$CookieParameters = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $CookieParameters['path'], $CookieParameters['domain'], $CookieParameters['secure'], $CookieParameters['httponly']);
	}
	session_destroy();
}
