<?php

use Illuminate\Support\Facades\Route;

// This is an internal tool — send visitors straight to the panel (which routes
// on to the login page when signed out). Also avoids shipping the default
// Laravel landing page and its Vite-built assets to production.
Route::get('/', fn () => redirect('/admin'));
