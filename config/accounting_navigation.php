<?php

/**
 * Accounting V2.1 navigation, Phases 1–5.
 * One company-wide ledger: no restaurant branch selector or inter-branch entries.
 * Each item points to a registered Accounting GET route and has the matching
 * permission so both the RMS sidebar and Accounting pages can render it.
 */
return [
    'Setup' => [
        ['route' => 'accounting.settings', 'label' => 'Accounting Settings', 'permission' => 'accounting-settings'],
        ['route' => 'accounting.accounts', 'label' => 'Chart of Accounts', 'permission' => 'accounting-view'],
        ['route' => 'accounting.periods', 'label' => 'Fiscal Periods', 'permission' => 'accounting-view'],
        ['route' => 'accounting.journal-types', 'label' => 'Journal Types', 'permission' => 'accounting-view'],
        ['route' => 'accounting.mappings', 'label' => 'Source Mappings', 'permission' => 'accounting-view'],
    ],
    'Journals & Operations' => [
        ['route' => 'accounting.journals.index', 'label' => 'Journal Entries', 'permission' => 'accounting-view'],
        ['route' => 'accounting.journals.create', 'label' => 'New Journal', 'permission' => 'accounting-journal-create'],
        ['route' => 'accounting.events', 'label' => 'Pending Source Events', 'permission' => 'accounting-event-view'],
        ['route' => 'accounting.expenses', 'label' => 'Expenses', 'permission' => 'accounting-view'],
        ['route' => 'accounting.vouchers', 'label' => 'Payment Vouchers', 'permission' => 'accounting-view'],
        ['route' => 'accounting.credits', 'label' => 'Credit Notes', 'permission' => 'accounting-view'],
        ['route' => 'accounting.costs', 'label' => 'Inventory Cost Review', 'permission' => 'accounting-view'],
    ],
    'Cash & Settlements' => [
        ['route' => 'accounting.sessions', 'label' => 'POS Cash Reconciliation', 'permission' => 'accounting-view'],
        ['route' => 'accounting.clearing', 'label' => 'Card / MFS Clearing', 'permission' => 'accounting-view'],
        ['route' => 'accounting.bank', 'label' => 'Bank Reconciliation', 'permission' => 'accounting-view'],
        ['route' => 'accounting.withholdings', 'label' => 'Withholding Register', 'permission' => 'accounting-view'],
        ['route' => 'accounting.due-terms', 'label' => 'Payment Terms', 'permission' => 'accounting-view'],
    ],
    'Extended Ledger' => [
        ['route' => 'accounting.payroll', 'label' => 'Payroll Ledger', 'permission' => 'accounting-view'],
        ['route' => 'accounting.cost-centers', 'label' => 'Cost Centers', 'permission' => 'accounting-view'],
        ['route' => 'accounting.deferrals', 'label' => 'Accruals & Deferrals', 'permission' => 'accounting-view'],
        ['route' => 'accounting.assets', 'label' => 'Fixed Assets', 'permission' => 'accounting-view'],
        ['route' => 'accounting.loans', 'label' => 'Company Loans', 'permission' => 'accounting-view'],
    ],
    'Reports & Closing' => [
        ['route' => 'accounting.reports', 'label' => 'Financial Reports', 'permission' => 'accounting-financial-reports'],
        ['route' => 'accounting.trial-balance', 'label' => 'Trial Balance', 'permission' => 'accounting-view'],
        ['route' => 'accounting.report-rules', 'label' => 'Report Mapping Rules', 'permission' => 'accounting-view'],
        ['route' => 'accounting.cutover', 'label' => 'Opening Cutover', 'permission' => 'accounting-view'],
        ['route' => 'accounting.budgets', 'label' => 'Budgets', 'permission' => 'accounting-view'],
        ['route' => 'accounting.closing', 'label' => 'Period / Year Closing', 'permission' => 'accounting-view'],
        ['route' => 'accounting.audit', 'label' => 'Accounting Audit Log', 'permission' => 'accounting-view'],
    ],
];
