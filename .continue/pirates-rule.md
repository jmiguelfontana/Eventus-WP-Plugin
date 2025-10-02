# Project Architecture (Pirates Rule)

This project is a **WordPress Plugin**.

## Structure
- `/assets/` → CSS, JS, images. Enqueue with `wp_enqueue_*`.
- `/includes/` → PHP classes (organized by responsibility).
- `buscador-api.php` → Main plugin file, only for registration & bootstrap.

## Guidelines
- Follow WordPress coding standards (PHP, JS, CSS).
- Use namespaces in PHP (`PluginName\Includes`).
- Use `$wpdb` with prepared statements for DB queries.
- Prefer WordPress APIs (Options, Transients, REST).

## Development Rules
- Code changes must be **optimized**.  
- Code changes must be **strictly focused on the requested modifications**.  
- Do **not** add extra features or refactor outside scope.  
- Preserve plugin stability and backward compatibility.  
