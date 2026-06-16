<?php
declare(strict_types=1);
namespace App\Middleware;

class TenantMiddleware
{
    public static function require(): void
    {
        if (!defined('TENANT_ID')) {
            http_response_code(500);
            die(json_encode(['success' => false, 'error' => 'Tenant não inicializado.']));
        }
    }

    public static function requireSuperAdmin(): void
    {
        self::require();
        if (empty($_SESSION['is_super_admin'])) {
            http_response_code(403);
            die(json_encode(['success' => false, 'error' => 'Acesso exclusivo para Super Admin.']));
        }
    }

    public static function getCssVariables(): string
    {
        $d         = $_SESSION['tenant_data'] ?? [];
        $primary   = $d['primary_color']   ?? '#1e3a5f';
        $secondary = $d['secondary_color'] ?? '#3b82f6';
        $accent    = $d['accent_color']    ?? '#10b981';
        $font      = $d['font_family']     ?? 'Segoe UI, Arial, sans-serif';
        return ":root{--color-primary:{$primary};--color-secondary:{$secondary};--color-accent:{$accent};--font-family:{$font};}";
    }
}
