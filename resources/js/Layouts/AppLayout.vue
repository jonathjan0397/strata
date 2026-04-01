<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page       = usePage();
const user       = computed(() => page.props.auth.user);
const flash      = computed(() => page.props.flash);
const appVersion = computed(() => page.props.appVersion ?? null);

const sidebarOpen = ref(false);

const isAdmin          = computed(() => user.value?.roles?.some(r => ['super-admin','admin','staff'].includes(r.name)));
const twoFactorWarning = computed(() => page.props.twoFactorWarning);
const license          = computed(() => page.props.license ?? { managed: false, active: true, features: [] });

function hasFeature(key) {
    if (!license.value.managed) return true;
    return license.value.features?.includes(key) ?? false;
}

// ── Admin nav groups ──────────────────────────────────────────────────────────
const adminNavGroups = [
    {
        key: 'clients',
        label: 'Clients & Billing',
        icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        items: [
            { name: 'Clients',       href: route('admin.clients.index'),       icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
            { name: 'Client Groups', href: route('admin.client-groups.index'), icon: 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z' },
            { name: 'Invoices',      href: route('admin.invoices.index'),      icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
            { name: 'Tax Rates',     href: route('admin.tax-rates.index'),     icon: 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z' },
            { name: 'Promo Codes',   href: route('admin.promo-codes.index'),   icon: 'M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z' },
            { name: 'Quotes',        href: route('admin.quotes.index'),        icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z' },
            { name: 'Orders',        href: route('admin.orders.index'),        icon: 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z' },
        ],
    },
    {
        key: 'products',
        label: 'Products & Services',
        icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        items: [
            { name: 'Products', href: route('admin.products.index'), icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
            { name: 'Services', href: route('admin.services.index'), icon: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2' },
            { name: 'Addons',   href: route('admin.addons.index'),   icon: 'M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z' },
        ],
    },
    {
        key: 'support',
        label: 'Support',
        icon: 'M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z',
        items: [
            { name: 'Tickets',       href: route('admin.support.index'),       icon: 'M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z' },
            { name: 'Mail Pipes',    href: route('admin.mail-pipes.index'),    icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
            { name: 'Announcements', href: route('admin.announcements.index'), icon: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z' },
        ],
    },
    {
        key: 'infra',
        label: 'Infrastructure',
        icon: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01',
        items: [
            { name: 'Servers',      href: route('admin.modules.index'),     icon: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01' },
            { name: 'Domains',      href: route('admin.domains.index'),     icon: 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9' },
            { name: 'TLD Pricing',  href: route('admin.tld-pricing.index'), icon: 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
        ],
    },
    {
        key: 'comms',
        label: 'Communications',
        icon: 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75',
        items: [
            { name: 'Email Templates', href: route('admin.email-templates.index'), icon: 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75' },
            { name: 'Email Log',       href: route('admin.email-log.index'),       icon: 'M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 001.183 1.981l6.478 3.488m8.839 2.51l-4.66-2.51m0 0l-1.023-.55a2.25 2.25 0 00-2.134 0l-1.022.55m0 0l-4.661 2.51m16.5 1.615a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V8.844a2.25 2.25 0 011.183-1.98l7.5-4.04a2.25 2.25 0 012.134 0l7.5 4.04a2.25 2.25 0 011.183 1.98V19.5z' },
        ],
    },
    {
        key: 'content',
        label: 'Content',
        icon: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0118 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
        items: [
            { name: 'Knowledge Base', href: route('admin.kb.index'), icon: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0118 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25' },
        ],
    },
    {
        key: 'widgets',
        label: 'Widgets',
        icon: 'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5',
        items: [
            { name: 'Widget Snippets', href: route('admin.widgets.index'), icon: 'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5' },
        ],
    },
    {
        key: 'analytics',
        label: 'Analytics',
        icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
        items: [
            { name: 'Reports',    href: route('admin.reports.index'),   icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z' },
            { name: 'Workflows',  href: route('admin.workflows.index'), icon: 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z', premium: 'workflows' },
            { name: 'Audit Log',  href: route('admin.audit-log.index'), icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z' },
        ],
    },
    {
        key: 'team',
        label: 'Administration',
        icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
        items: [
            { name: 'Staff',      href: route('admin.staff.index'),      icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z' },
            { name: 'Affiliates', href: route('admin.affiliates.index'), icon: 'M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244', premium: 'affiliates' },
        ],
    },
];

// All groups default collapsed; the active group opens automatically via groupIsOpen()
const groupOpen = ref(Object.fromEntries(adminNavGroups.map(g => [g.key, false])));

function toggleGroup(key) {
    // Never collapse a group that contains the active page
    const group = adminNavGroups.find(g => g.key === key);
    const hasActive = group?.items.some(item => isActiveHref(item.href));
    if (!hasActive) groupOpen.value[key] = !groupOpen.value[key];
}

function isActiveHref(href) {
    try {
        const p = new URL(href).pathname;
        return page.url === p || page.url.startsWith(p + '/');
    } catch {
        return false;
    }
}

function groupIsOpen(key) {
    const group = adminNavGroups.find(g => g.key === key);
    if (group?.items.some(item => isActiveHref(item.href))) return true;
    return groupOpen.value[key] ?? false;
}

// ── Client nav ────────────────────────────────────────────────────────────────
const clientNav = [
    { name: 'Dashboard',       href: route('client.dashboard'),             icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: 'Order',           href: route('client.order.catalog'),         icon: 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z' },
    { name: 'Services',        href: route('client.services.index'),        icon: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2' },
    { name: 'Invoices',        href: route('client.invoices.index'),        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
    { name: 'Quotes',          href: route('client.quotes.index'),          icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z' },
    { name: 'Support',         href: route('client.support.index'),         icon: 'M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z' },
    { name: 'Announcements',   href: route('client.announcements'),         icon: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z' },
    { name: 'Domains',         href: route('client.domains.index'),         icon: 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9' },
    { name: 'Transfer Domain', href: route('client.domain-transfer.search'), icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
    { name: 'Help Center',     href: route('client.kb.index'),              icon: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0118 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25' },
    { name: 'Payment Methods', href: route('client.payment-methods.index'), icon: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z' },
    { name: 'Affiliate',       href: route('client.affiliate.dashboard'),   icon: 'M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244' },
];

// ── Profile / settings nav (shared) ──────────────────────────────────────────
const settingsNav = [
    { name: 'Profile',  href: route('profile.edit'),     icon: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z' },
    { name: 'Security', href: route('profile.security'), icon: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z' },
    { name: 'Sessions', href: route('profile.sessions'), icon: 'M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3' },
];

// ── Dark mode ─────────────────────────────────────────────────────────────────
const darkMode = ref(false)

onMounted(() => {
    darkMode.value = localStorage.getItem('adminDark') === '1'
    document.documentElement.classList.toggle('dark', darkMode.value)
})

function toggleDark() {
    darkMode.value = !darkMode.value
    localStorage.setItem('adminDark', darkMode.value ? '1' : '0')
    document.documentElement.classList.toggle('dark', darkMode.value)
}

// Icons
const ICON_GEAR   = 'M9.594 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.11v1.093c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.11v-1.094c0-.55.398-1.019.94-1.11l.894-.148c.424-.071.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z M15 12a3 3 0 11-6 0 3 3 0 016 0z';
const ICON_HOME   = 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6';
const ICON_CHEVRON = 'M8.25 4.5l7.5 7.5-7.5 7.5';
</script>

<template>
    <div class="min-h-full bg-gradient-to-br from-slate-100 via-blue-50 to-indigo-100 dark:from-slate-900 dark:via-slate-800 dark:to-slate-900">

        <!-- Mobile sidebar overlay -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false" />

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-gradient-to-b from-blue-950 to-slate-900 border-r border-blue-800/30 shadow-2xl transition-transform duration-200"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <!-- Logo -->
            <div class="flex h-16 shrink-0 items-center gap-2.5 px-5 border-b border-blue-800/40">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500 shadow-lg shadow-blue-500/30 text-white font-bold text-sm">S</div>
                <span class="text-white font-semibold text-lg tracking-tight">Strata</span>
                <span class="ml-auto text-xs text-blue-400/60 font-mono">{{ appVersion ?? '…' }}</span>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-3 px-2.5 flex flex-col gap-1 scrollbar-thin">

                <!-- Admin grouped nav -->
                <template v-if="isAdmin">
                    <!-- Dashboard -->
                    <Link
                        :href="route('admin.dashboard')"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                        :class="isActiveHref(route('admin.dashboard'))
                            ? 'bg-blue-500/30 text-white border-l-2 border-blue-400'
                            : 'text-slate-300 hover:bg-blue-700/25 hover:text-white'"
                    >
                        <svg class="h-4.5 w-4.5 shrink-0" :class="isActiveHref(route('admin.dashboard')) ? 'text-blue-300' : 'text-slate-400 group-hover:text-blue-300'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="ICON_HOME" />
                        </svg>
                        Dashboard
                    </Link>

                    <!-- Groups -->
                    <div v-for="group in adminNavGroups" :key="group.key" class="mt-0.5">
                        <!-- Group header -->
                        <button
                            type="button"
                            class="w-full flex items-center gap-2.5 rounded-lg px-3 py-1.5 text-xs font-semibold uppercase tracking-wider transition-colors"
                            :class="group.items.some(i => isActiveHref(i.href))
                                ? 'text-blue-300'
                                : 'text-blue-400/60 hover:text-blue-300/80'"
                            @click="toggleGroup(group.key)"
                        >
                            <svg class="h-3.5 w-3.5 shrink-0 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="group.icon" />
                            </svg>
                            <span class="flex-1 text-left">{{ group.label }}</span>
                            <svg
                                class="h-3.5 w-3.5 shrink-0 transition-transform duration-200"
                                :class="groupIsOpen(group.key) ? 'rotate-90' : ''"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" :d="ICON_CHEVRON" />
                            </svg>
                        </button>

                        <!-- Group items -->
                        <ul v-show="groupIsOpen(group.key)" class="mt-0.5 ml-2 pl-2 border-l border-blue-800/40 space-y-0.5">
                            <li v-for="item in group.items" :key="item.name">
                                <Link
                                    :href="item.href"
                                    class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm font-medium transition-colors"
                                    :class="isActiveHref(item.href)
                                        ? 'bg-blue-500/25 text-white'
                                        : 'text-slate-400 hover:bg-blue-700/20 hover:text-slate-100'"
                                >
                                    <svg class="h-4 w-4 shrink-0" :class="isActiveHref(item.href) ? 'text-blue-300' : 'text-slate-500 group-hover:text-blue-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                                    </svg>
                                    <span class="flex-1">{{ item.name }}</span>
                                    <svg
                                        v-if="item.premium && !hasFeature(item.premium)"
                                        class="h-3.5 w-3.5 shrink-0 text-amber-500/70"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                        title="Premium feature"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                    </svg>
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Settings (standalone) -->
                    <div class="mt-1 pt-1 border-t border-blue-800/30">
                        <Link
                            :href="route('admin.settings.index')"
                            class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                            :class="isActiveHref(route('admin.settings.index'))
                                ? 'bg-blue-500/30 text-white border-l-2 border-blue-400'
                                : 'text-slate-300 hover:bg-blue-700/25 hover:text-white'"
                        >
                            <svg class="h-4.5 w-4.5 shrink-0" :class="isActiveHref(route('admin.settings.index')) ? 'text-blue-300' : 'text-slate-400 group-hover:text-blue-300'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="ICON_GEAR" />
                            </svg>
                            Settings
                        </Link>
                    </div>
                </template>

                <!-- Client flat nav -->
                <template v-else>
                    <ul class="space-y-0.5">
                        <li v-for="item in clientNav" :key="item.name">
                            <Link
                                :href="item.href"
                                class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                :class="isActiveHref(item.href)
                                    ? 'bg-blue-500/30 text-white border-l-2 border-blue-400'
                                    : 'text-slate-300 hover:bg-blue-700/25 hover:text-white'"
                            >
                                <svg class="h-4.5 w-4.5 shrink-0" :class="isActiveHref(item.href) ? 'text-blue-300' : 'text-slate-400 group-hover:text-blue-300'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                                </svg>
                                {{ item.name }}
                            </Link>
                        </li>
                    </ul>
                </template>

                <!-- Account section (bottom) -->
                <div class="mt-auto pt-3 border-t border-blue-800/30">
                    <p class="px-3 mb-1 text-xs font-semibold uppercase tracking-wider text-blue-400/50">Account</p>
                    <ul class="space-y-0.5">
                        <li v-for="item in settingsNav" :key="item.name">
                            <Link
                                :href="item.href"
                                class="group flex items-center gap-2.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                                :class="isActiveHref(item.href)
                                    ? 'bg-blue-500/25 text-white'
                                    : 'text-slate-400 hover:bg-blue-700/20 hover:text-slate-100'"
                            >
                                <svg class="h-4 w-4 shrink-0" :class="isActiveHref(item.href) ? 'text-blue-300' : 'text-slate-500 group-hover:text-blue-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                                </svg>
                                {{ item.name }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- User footer -->
            <div class="shrink-0 border-t border-blue-800/40 bg-blue-950/60 px-4 py-3 flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-500 shadow-md shadow-blue-500/30 text-white text-xs font-semibold uppercase">
                    {{ user?.name?.charAt(0) ?? 'A' }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-white truncate">{{ user?.name ?? 'Admin' }}</p>
                    <p class="text-xs text-blue-300/60 truncate">{{ user?.email ?? '' }}</p>
                </div>
                <button
                    title="Sign out"
                    class="text-slate-400 hover:text-white transition-colors"
                    @click="router.post(route('logout'))"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                    </svg>
                </button>
            </div>
        </aside>

        <!-- Main content area -->
        <div class="lg:pl-64 flex flex-col min-h-screen">

            <!-- Top bar -->
            <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-blue-200/40 dark:border-slate-700/50 bg-white/75 dark:bg-slate-900/90 backdrop-blur-md px-4 sm:px-6 shadow-sm">
                <button class="lg:hidden -m-2 p-2 text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200" @click="sidebarOpen = true">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="flex-1">
                    <slot name="header" />
                </div>
                <!-- Dark mode toggle -->
                <button
                    type="button"
                    @click="toggleDark"
                    :title="darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
                    class="rounded-lg p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-colors"
                >
                    <!-- Sun icon (shown in dark mode) -->
                    <svg v-if="darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>
                    <!-- Moon icon (shown in light mode) -->
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                </button>
            </header>

            <!-- 2FA warning banner -->
            <div v-if="twoFactorWarning" class="bg-amber-50/90 dark:bg-amber-900/20 backdrop-blur-sm border-b border-amber-200/60 dark:border-amber-700/40 px-4 sm:px-6 py-2.5 flex items-center justify-between gap-4">
                <p class="text-sm text-amber-800">
                    <strong>Recommended:</strong> Your account does not have two-factor authentication enabled.
                    Enabling 2FA protects the admin panel from unauthorized access.
                </p>
                <Link :href="route('profile.security')" class="shrink-0 text-sm font-medium text-amber-900 underline hover:text-amber-700">
                    Enable 2FA
                </Link>
            </div>

            <!-- Flash messages -->
            <div v-if="flash?.success || flash?.error" class="px-4 sm:px-6 pt-4">
                <div v-if="flash.success" class="rounded-xl bg-green-50/80 dark:bg-green-900/30 backdrop-blur-sm border border-green-200/60 dark:border-green-700/40 px-4 py-3 text-sm text-green-800 dark:text-green-300 shadow-sm">
                    {{ flash.success }}
                </div>
                <div v-if="flash.error" class="rounded-xl bg-red-50/80 dark:bg-red-900/30 backdrop-blur-sm border border-red-200/60 dark:border-red-700/40 px-4 py-3 text-sm text-red-800 dark:text-red-300 shadow-sm">
                    {{ flash.error }}
                </div>
            </div>

            <!-- Page content -->
            <main class="flex-1 px-4 sm:px-6 py-6">
                <slot />
            </main>
            <div class="px-4 sm:px-6 pb-4 text-center">
                <a href="https://buymeacoffee.com/jonathan0397" target="_blank" rel="noopener noreferrer"
                    class="text-sm text-gray-400/60 hover:text-gray-500 dark:text-gray-600/60 dark:hover:text-gray-400 transition-colors">
                    ☕ Buy me a coffee
                </a>
            </div>
        </div>
    </div>
</template>
