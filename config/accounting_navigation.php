<?php
/**
 * Accounting menu catalog: a single permission-aware source for Accounting tabs
 * and the main RMS sidebar. Do not reuse display labels as route names.
 */
return [
    'Setup' => [
        ['label' => 'Settings', 'route' => 'accounting.settings', 'permission' => 'accounting-settings'],
        ['label' => 'Chart of Accounts', 'route' => 'accounting.accounts', 'permission' => 'accounting-view'],
        ['label' => 'Fiscal Periods', 'route' => 'accounting.periods', 'permission' => 'accounting-view'],
        ['label' => 'Journal Types', 'route' => 'accounting.journal-types', 'permission' => 'accounting-view'],
        ['label' => 'Account Mappings', 'route' => 'accounting.mappings', 'permission' => 'accounting-mapping'],
        ['label' => 'Audit Log', 'route' => 'accounting.audit', 'permission' => 'accounting-view'],
    ],
    'Journals & Operations' => [
        ['label' => 'Journal Entries', 'route' => 'accounting.journals.index', 'permission' => 'accounting-view'],
        ['label' => 'New Journal', 'route' => 'accounting.journals.create', 'permission' => 'accounting-journal-create'],
        ['label' => 'Pending Events', 'route' => 'accounting.events', 'permission' => 'accounting-event-view'],
        ['label' => 'Receipt / Payment / Contra', 'route' => 'accounting.vouchers', 'permission' => 'accounting-view'],
        ['label' => 'Expenses', 'route' => 'accounting.expenses', 'permission' => 'accounting-view'],
        ['label' => 'Credits & Refunds', 'route' => 'accounting.credits', 'permission' => 'accounting-view'],
        ['label' => 'Inventory Cost', 'route' => 'accounting.costs', 'permission' => 'accounting-view'],
        ['label' => 'TDS / VDS', 'route' => 'accounting.withholdings', 'permission' => 'accounting-view'],
    ],
    'Cash & Settlements' => [
        ['label' => 'Card & MFS Clearing', 'route' => 'accounting.clearing', 'permission' => 'accounting-view'],
        ['label' => 'POS Cash Sessions', 'route' => 'accounting.sessions', 'permission' => 'accounting-view'],
        ['label' => 'Bank Reconciliation', 'route' => 'accounting.bank', 'permission' => 'accounting-view'],
        ['label' => 'Payment Terms', 'route' => 'accounting.due-terms', 'permission' => 'accounting-view'],
    ],
    'Extended Ledger' => [
        ['label' => 'Payroll & Staff Loans', 'route' => 'accounting.payroll', 'permission' => 'accounting-view'],
        ['label' => 'Cost Centers', 'route' => 'accounting.cost-centers', 'permission' => 'accounting-view'],
        ['label' => 'Deferrals & Prepayments', 'route' => 'accounting.deferrals', 'permission' => 'accounting-view'],
        ['label' => 'Fixed Assets', 'route' => 'accounting.assets', 'permission' => 'accounting-view'],
        ['label' => 'Company Loans', 'route' => 'accounting.loans', 'permission' => 'accounting-view'],
    ],
    'Reports & Closing' => [
        ['label' => 'Balance Sheet', 'route' => 'accounting.reports.balance-sheet', 'permission' => 'accounting-financial-reports'],
        ['label' => 'Profit & Loss', 'route' => 'accounting.reports.profit-loss', 'permission' => 'accounting-financial-reports'],
        ['label' => 'General Ledger', 'route' => 'accounting.reports.general-ledger', 'permission' => 'accounting-financial-reports'],
        ['label' => 'Cash Flow', 'route' => 'accounting.reports.cash-flow', 'permission' => 'accounting-financial-reports'],
        ['label' => 'Trial Balance', 'route' => 'accounting.reports.trial-balance', 'permission' => 'accounting-financial-reports'],
        ['label' => 'Tax Activity', 'route' => 'accounting.reports.tax', 'permission' => 'accounting-financial-reports'],
        ['label' => 'AR / AP Aging', 'route' => 'accounting.reports.aging', 'permission' => 'accounting-financial-reports'],
        ['label' => 'Cash Flow / Tax Rules', 'route' => 'accounting.report-rules', 'permission' => 'accounting-report-mapping'],
        ['label' => 'Budget vs Actual', 'route' => 'accounting.budgets', 'permission' => 'accounting-budget'],
        ['label' => 'Opening Balances', 'route' => 'accounting.cutover', 'permission' => 'accounting-cutover'],
        ['label' => 'Period / Year Closing', 'route' => 'accounting.closing', 'permission' => 'accounting-year-end'],
    ],
];
