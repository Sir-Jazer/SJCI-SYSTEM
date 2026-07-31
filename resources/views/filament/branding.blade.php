{{-- SJCI brand theming: navy sidebar to match the church colour scheme. --}}
<style>
    :root {
        --sjci-navy: #1e2a4a;
        --sjci-navy-deep: #16203a;
        --sjci-navy-line: rgba(255, 255, 255, 0.08);
        --sjci-sidebar-text: #cbd5e1;
        --sjci-sidebar-muted: #7f8db0;
    }

    /* --- Navy sidebar surface --- */
    .fi-sidebar,
    .fi-sidebar .fi-sidebar-nav,
    .fi-sidebar-header {
        background-color: var(--sjci-navy);
        border-color: var(--sjci-navy-line);
    }

    .fi-sidebar-header {
        border-bottom: 1px solid var(--sjci-navy-line);
    }

    /* Light text + icons on the navy ground (SVG icons inherit currentColor). */
    .fi-sidebar,
    .fi-sidebar .fi-sidebar-item-btn,
    .fi-sidebar .fi-sidebar-item-label,
    .fi-sidebar-header .fi-logo {
        color: var(--sjci-sidebar-text);
    }

    .fi-sidebar .fi-sidebar-group-label {
        color: var(--sjci-sidebar-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* Hover state */
    .fi-sidebar .fi-sidebar-item-btn:hover {
        background-color: rgba(255, 255, 255, 0.06);
        color: #ffffff;
    }

    /* Active item — church blue pill */
    .fi-sidebar .fi-sidebar-item-btn.fi-active,
    .fi-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-btn {
        background-color: rgba(37, 99, 235, 0.92);
        color: #ffffff;
    }

    /* Deepen the navy slightly in dark mode. */
    .dark .fi-sidebar,
    .dark .fi-sidebar .fi-sidebar-nav,
    .dark .fi-sidebar-header {
        background-color: var(--sjci-navy-deep);
    }

    /* --- Navy topbar: unified dark chrome with the sidebar --- */
    .fi-topbar {
        background-color: var(--sjci-navy);
        border-bottom: 1px solid var(--sjci-navy-line);
        color: var(--sjci-sidebar-text);
    }

    .dark .fi-topbar {
        background-color: var(--sjci-navy-deep);
    }

    /* Topbar icon buttons (sidebar toggle, theme switch, etc.) */
    .fi-topbar .fi-icon-btn {
        color: var(--sjci-sidebar-text);
    }

    .fi-topbar .fi-icon-btn:hover {
        color: #ffffff;
    }

    /* Global search field — legible on the navy ground */
    .fi-topbar .fi-input-wrp {
        background-color: rgba(255, 255, 255, 0.10);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
    }

    .fi-topbar .fi-input {
        color: #ffffff;
    }

    .fi-topbar .fi-input::placeholder {
        color: rgba(226, 232, 240, 0.65);
    }

    .fi-topbar .fi-input-wrp svg {
        color: rgba(226, 232, 240, 0.75);
    }

    /* --- Brand name adapts to its background --- */
    .sjci-brand-name {
        color: #1b2a4a; /* dark by default — readable on the white login card */
    }

    .fi-sidebar .sjci-brand-name,
    .fi-sidebar-header .sjci-brand-name,
    .fi-topbar .sjci-brand-name {
        color: #eef2fb; /* light on the navy chrome */
    }

    /* --- Branded login screen: white card on a navy field --- */
    .fi-simple-layout {
        background-color: var(--sjci-navy);
        background-image: linear-gradient(160deg, #1e2a4a 0%, #263457 55%, #19233d 100%);
    }

    .fi-simple-main {
        box-shadow: 0 20px 45px -22px rgba(8, 15, 30, 0.65);
        border: 1px solid rgba(15, 23, 42, 0.06);
    }
</style>