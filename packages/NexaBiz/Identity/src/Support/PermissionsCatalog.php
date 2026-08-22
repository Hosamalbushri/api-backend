<?php

namespace NexaBiz\Identity\Support;

/**
 * Stable permission codes used by authorization checks.
 * Port of backend/app/auth/permissions_catalog.py — do not diverge.
 */
final class PermissionsCatalog
{
    public const PLATFORM_COMPANIES_MANAGE = 'platform.companies.manage';

    public const PLATFORM_USERS_MANAGE = 'platform.users.manage';

    public const USERS_VIEW = 'users.view';

    public const USERS_CREATE = 'users.create';

    public const USERS_UPDATE = 'users.update';

    public const USERS_DELETE = 'users.delete';

    public const USERS_MANAGE = 'users.manage';

    public const ROLES_VIEW = 'roles.view';

    public const ROLES_CREATE = 'roles.create';

    public const ROLES_UPDATE = 'roles.update';

    public const ROLES_DELETE = 'roles.delete';

    public const ROLES_MANAGE = 'roles.manage';

    public const PERMISSIONS_MANAGE = 'permissions.manage';

    public const COMPANIES_VIEW = 'companies.view';

    public const COMPANIES_UPDATE = 'companies.update';

    public const PRODUCTS_VIEW = 'products.view';

    public const PRODUCTS_CREATE = 'products.create';

    public const PRODUCTS_UPDATE = 'products.update';

    public const PRODUCTS_DELETE = 'products.delete';

    public const INVENTORY_VIEW = 'inventory.view';

    public const INVENTORY_CREATE = 'inventory.create';

    public const INVENTORY_UPDATE = 'inventory.update';

    public const INVENTORY_DELETE = 'inventory.delete';

    public const INVENTORY_ADJUST = 'inventory.adjust';

    public const INVENTORY_STOCK_COUNT_VIEW = 'inventory.stock_count.view';

    public const INVENTORY_STOCK_COUNT_ADJUST = 'inventory.stock_count.adjust';

    public const INVENTORY_STOCK_COUNT_IMPORT = 'inventory.stock_count.import';

    public const INVENTORY_STOCK_COUNT_EXPORT = 'inventory.stock_count.export';

    public const INVENTORY_STOCK_COUNT_CLEAR = 'inventory.stock_count.clear';

    public const INVENTORY_PRODUCTS_VIEW = 'inventory.products.view';

    public const INVENTORY_PRODUCTS_CREATE = 'inventory.products.create';

    public const INVENTORY_PRODUCTS_UPDATE = 'inventory.products.update';

    public const INVENTORY_PRODUCTS_DELETE = 'inventory.products.delete';

    public const INVENTORY_PRODUCTS_IMPORT = 'inventory.products.import';

    public const INVENTORY_PRODUCTS_BARCODE = 'inventory.products.barcode';

    public const SALES_VIEW = 'sales.view';

    public const SALES_CREATE = 'sales.create';

    public const SALES_UPDATE = 'sales.update';

    public const SALES_DELETE = 'sales.delete';

    public const SALES_POST = 'sales.post';

    public const SALES_CANCEL = 'sales.cancel';

    public const SALES_DOCUMENTS_VIEW = 'sales.documents.view';

    public const SALES_DOCUMENTS_CREATE = 'sales.documents.create';

    public const SALES_DOCUMENTS_UPDATE = 'sales.documents.update';

    public const SALES_DOCUMENTS_DELETE = 'sales.documents.delete';

    public const SALES_DOCUMENTS_POST = 'sales.documents.post';

    public const SALES_DOCUMENTS_CANCEL = 'sales.documents.cancel';

    public const SALES_DOCUMENTS_DUPLICATE = 'sales.documents.duplicate';

    public const SALES_DOCUMENTS_EXPORT = 'sales.documents.export';

    public const CUSTOMERS_VIEW = 'customers.view';

    public const CUSTOMERS_CREATE = 'customers.create';

    public const CUSTOMERS_UPDATE = 'customers.update';

    public const CUSTOMERS_DELETE = 'customers.delete';

    public const CUSTOMERS_MASTER_VIEW = 'customers.master.view';

    public const CUSTOMERS_MASTER_CREATE = 'customers.master.create';

    public const CUSTOMERS_MASTER_UPDATE = 'customers.master.update';

    public const CUSTOMERS_MASTER_DELETE = 'customers.master.delete';

    public const CUSTOMERS_MASTER_IMPORT = 'customers.master.import';

    public const CUSTOMERS_ACCOUNTS_VIEW = 'customers.accounts.view';

    public const CUSTOMERS_SETTINGS_VIEW = 'customers.settings.view';

    public const CUSTOMERS_SETTINGS_UPDATE = 'customers.settings.update';

    public const ACCOUNTING_VIEW = 'accounting.view';

    public const ACCOUNTING_ACCOUNTS_VIEW = 'accounting.accounts.view';

    public const ACCOUNTING_ACCOUNTS_CREATE = 'accounting.accounts.create';

    public const ACCOUNTING_ACCOUNTS_UPDATE = 'accounting.accounts.update';

    public const ACCOUNTING_ACCOUNTS_DELETE = 'accounting.accounts.delete';

    public const ACCOUNTING_JOURNALS_VIEW = 'accounting.journals.view';

    public const ACCOUNTING_JOURNALS_CREATE = 'accounting.journals.create';

    public const ACCOUNTING_JOURNALS_UPDATE = 'accounting.journals.update';

    public const ACCOUNTING_JOURNALS_DELETE = 'accounting.journals.delete';

    public const ACCOUNTING_CURRENCY_RATES_VIEW = 'accounting.currency_rates.view';

    public const ACCOUNTING_CURRENCY_RATES_CREATE = 'accounting.currency_rates.create';

    public const ACCOUNTING_CURRENCY_RATES_UPDATE = 'accounting.currency_rates.update';

    public const ACCOUNTING_CURRENCY_RATES_DELETE = 'accounting.currency_rates.delete';

    public const ACCOUNTING_FISCAL_YEARS_VIEW = 'accounting.fiscal_years.view';

    public const ACCOUNTING_FISCAL_YEARS_CREATE = 'accounting.fiscal_years.create';

    public const ACCOUNTING_FISCAL_YEARS_UPDATE = 'accounting.fiscal_years.update';

    public const ACCOUNTING_FISCAL_YEARS_OPEN_PERIOD = 'accounting.fiscal_years.open_period';

    public const ACCOUNTING_FISCAL_YEARS_CLOSE_PERIOD = 'accounting.fiscal_years.close_period';

    public const ACCOUNTING_FISCAL_YEARS_REOPEN_PERIOD = 'accounting.fiscal_years.reopen_period';

    public const ACCOUNTING_VOUCHER_BOOKS_VIEW = 'accounting.voucher_books.view';

    public const ACCOUNTING_VOUCHER_BOOKS_CREATE = 'accounting.voucher_books.create';

    public const ACCOUNTING_VOUCHER_BOOKS_UPDATE = 'accounting.voucher_books.update';

    public const ACCOUNTING_VOUCHER_BOOKS_DELETE = 'accounting.voucher_books.delete';

    public const ACCOUNTING_REPORTS_VIEW = 'accounting.reports.view';

    public const ACCOUNTING_TRANSFERS_VIEW = 'accounting.transfers.view';

    public const ACCOUNTING_TRANSFERS_CREATE = 'accounting.transfers.create';

    public const ACCOUNTING_TRANSFERS_UPDATE = 'accounting.transfers.update';

    public const ACCOUNTING_TRANSFERS_DELETE = 'accounting.transfers.delete';

    public const ACCOUNTING_TRANSFERS_POST = 'accounting.transfers.post';

    public const ACCOUNTING_TRANSFERS_CANCEL = 'accounting.transfers.cancel';

    public const ACCOUNTING_CURRENCY_CONVERSIONS_VIEW = 'accounting.currency_conversions.view';

    public const ACCOUNTING_CURRENCY_CONVERSIONS_CREATE = 'accounting.currency_conversions.create';

    public const ACCOUNTING_CURRENCY_CONVERSIONS_UPDATE = 'accounting.currency_conversions.update';

    public const ACCOUNTING_CURRENCY_CONVERSIONS_DELETE = 'accounting.currency_conversions.delete';

    public const ACCOUNTING_CURRENCY_CONVERSIONS_POST = 'accounting.currency_conversions.post';

    public const ACCOUNTING_CURRENCY_CONVERSIONS_CANCEL = 'accounting.currency_conversions.cancel';

    public const RECEIPTS_VIEW = 'receipts.view';

    public const RECEIPTS_CREATE = 'receipts.create';

    public const RECEIPTS_UPDATE = 'receipts.update';

    public const RECEIPTS_POST = 'receipts.post';

    public const RECEIPTS_CANCEL = 'receipts.cancel';

    public const PAYMENTS_VIEW = 'payments.view';

    public const PAYMENTS_CREATE = 'payments.create';

    public const PAYMENTS_UPDATE = 'payments.update';

    public const PAYMENTS_POST = 'payments.post';

    public const PAYMENTS_CANCEL = 'payments.cancel';

    public const RECEIPTS_PAYMENTS_REPORTS_VIEW = 'receipts_payments.reports.view';

    public const RECEIPTS_PAYMENTS_REPORTS_EXPORT = 'receipts_payments.reports.export';

    public const RECEIPTS_PAYMENTS_SYNC = 'receipts_payments.sync';

    public const REPORTS_VIEW = 'reports.view';

    public const REPORTS_SALES_PERIOD_VIEW = 'reports.sales_period.view';

    public const REPORTS_SALES_PERIOD_EXPORT = 'reports.sales_period.export';

    public const REPORTS_ACCOUNT_STATEMENT_VIEW = 'reports.account_statement.view';

    public const REPORTS_ACCOUNT_STATEMENT_EXPORT = 'reports.account_statement.export';

    public const REPORTS_TRIAL_BALANCE_VIEW = 'reports.trial_balance.view';

    public const REPORTS_TRIAL_BALANCE_EXPORT = 'reports.trial_balance.export';

    public const REPORTS_JOURNAL_BOOK_VIEW = 'reports.journal_book.view';

    public const REPORTS_JOURNAL_BOOK_EXPORT = 'reports.journal_book.export';

    public const SETTINGS_VIEW = 'settings.view';

    public const SETTINGS_UPDATE = 'settings.update';

    public const SYNC_VIEW = 'sync.view';

    public const SYNC_EXECUTE = 'sync.execute';

    public const DEVICES_VIEW = 'devices.view';

    public const DEVICES_REVOKE = 'devices.revoke';

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function allPermissions(): array
    {
        return [
            [self::PLATFORM_COMPANIES_MANAGE, 'Manage companies at platform level'],
            [self::PLATFORM_USERS_MANAGE, 'Manage users across companies'],
            [self::USERS_VIEW, 'View users'],
            [self::USERS_CREATE, 'Create users'],
            [self::USERS_UPDATE, 'Update users'],
            [self::USERS_DELETE, 'Delete users'],
            [self::USERS_MANAGE, 'Manage users'],
            [self::ROLES_VIEW, 'View roles'],
            [self::ROLES_CREATE, 'Create roles'],
            [self::ROLES_UPDATE, 'Update roles'],
            [self::ROLES_DELETE, 'Delete roles'],
            [self::ROLES_MANAGE, 'Manage roles'],
            [self::PERMISSIONS_MANAGE, 'Manage permissions'],
            [self::COMPANIES_VIEW, 'View company'],
            [self::COMPANIES_UPDATE, 'Update company'],
            [self::PRODUCTS_VIEW, 'View products (legacy)'],
            [self::PRODUCTS_CREATE, 'Create products (legacy)'],
            [self::PRODUCTS_UPDATE, 'Update products (legacy)'],
            [self::PRODUCTS_DELETE, 'Delete products (legacy)'],
            [self::INVENTORY_VIEW, 'View inventory (legacy)'],
            [self::INVENTORY_CREATE, 'Create inventory items (legacy)'],
            [self::INVENTORY_UPDATE, 'Update inventory items (legacy)'],
            [self::INVENTORY_DELETE, 'Delete inventory items (legacy)'],
            [self::INVENTORY_ADJUST, 'Adjust inventory quantities (legacy)'],
            [self::INVENTORY_STOCK_COUNT_VIEW, 'View stock count service'],
            [self::INVENTORY_STOCK_COUNT_ADJUST, 'Perform stock count adjustments'],
            [self::INVENTORY_STOCK_COUNT_IMPORT, 'Import stock count data'],
            [self::INVENTORY_STOCK_COUNT_EXPORT, 'Export / print stock count reports'],
            [self::INVENTORY_STOCK_COUNT_CLEAR, 'Clear stock count data'],
            [self::INVENTORY_PRODUCTS_VIEW, 'View products service'],
            [self::INVENTORY_PRODUCTS_CREATE, 'Create products'],
            [self::INVENTORY_PRODUCTS_UPDATE, 'Update products'],
            [self::INVENTORY_PRODUCTS_DELETE, 'Delete products'],
            [self::INVENTORY_PRODUCTS_IMPORT, 'Import products from Excel'],
            [self::INVENTORY_PRODUCTS_BARCODE, 'Generate and print barcodes'],
            [self::SALES_VIEW, 'View sales (legacy)'],
            [self::SALES_CREATE, 'Create sales (legacy)'],
            [self::SALES_UPDATE, 'Update sales (legacy)'],
            [self::SALES_DELETE, 'Delete sales (legacy)'],
            [self::SALES_POST, 'Post sales (legacy)'],
            [self::SALES_CANCEL, 'Cancel sales (legacy)'],
            [self::SALES_DOCUMENTS_VIEW, 'View sales documents'],
            [self::SALES_DOCUMENTS_CREATE, 'Create sales documents'],
            [self::SALES_DOCUMENTS_UPDATE, 'Update sales documents'],
            [self::SALES_DOCUMENTS_DELETE, 'Delete sales documents'],
            [self::SALES_DOCUMENTS_POST, 'Post / confirm sales'],
            [self::SALES_DOCUMENTS_CANCEL, 'Cancel sales documents'],
            [self::SALES_DOCUMENTS_DUPLICATE, 'Duplicate sales documents'],
            [self::SALES_DOCUMENTS_EXPORT, 'Export / print sales invoices'],
            [self::CUSTOMERS_VIEW, 'View customers (legacy)'],
            [self::CUSTOMERS_CREATE, 'Create customers (legacy)'],
            [self::CUSTOMERS_UPDATE, 'Update customers (legacy)'],
            [self::CUSTOMERS_DELETE, 'Delete customers (legacy)'],
            [self::CUSTOMERS_MASTER_VIEW, 'View customer master list'],
            [self::CUSTOMERS_MASTER_CREATE, 'Create customers'],
            [self::CUSTOMERS_MASTER_UPDATE, 'Update customers'],
            [self::CUSTOMERS_MASTER_DELETE, 'Delete customers'],
            [self::CUSTOMERS_MASTER_IMPORT, 'Import customers from Excel'],
            [self::CUSTOMERS_ACCOUNTS_VIEW, 'View customer accounts'],
            [self::CUSTOMERS_SETTINGS_VIEW, 'View customer settings'],
            [self::CUSTOMERS_SETTINGS_UPDATE, 'Update customer settings'],
            [self::ACCOUNTING_VIEW, 'View accounting package'],
            [self::ACCOUNTING_ACCOUNTS_VIEW, 'View chart of accounts'],
            [self::ACCOUNTING_ACCOUNTS_CREATE, 'Create accounts'],
            [self::ACCOUNTING_ACCOUNTS_UPDATE, 'Update accounts'],
            [self::ACCOUNTING_ACCOUNTS_DELETE, 'Delete / deactivate accounts'],
            [self::ACCOUNTING_JOURNALS_VIEW, 'View journal entries'],
            [self::ACCOUNTING_JOURNALS_CREATE, 'Create journal entries'],
            [self::ACCOUNTING_JOURNALS_UPDATE, 'Update journal entries'],
            [self::ACCOUNTING_JOURNALS_DELETE, 'Delete journal entries'],
            [self::ACCOUNTING_CURRENCY_RATES_VIEW, 'View currency rates'],
            [self::ACCOUNTING_CURRENCY_RATES_CREATE, 'Create currency rates'],
            [self::ACCOUNTING_CURRENCY_RATES_UPDATE, 'Update currency rates'],
            [self::ACCOUNTING_CURRENCY_RATES_DELETE, 'Delete currency rates'],
            [self::ACCOUNTING_FISCAL_YEARS_VIEW, 'View fiscal years'],
            [self::ACCOUNTING_FISCAL_YEARS_CREATE, 'Create fiscal years'],
            [self::ACCOUNTING_FISCAL_YEARS_UPDATE, 'Update fiscal years'],
            [self::ACCOUNTING_FISCAL_YEARS_OPEN_PERIOD, 'Open accounting periods'],
            [self::ACCOUNTING_FISCAL_YEARS_CLOSE_PERIOD, 'Close accounting periods'],
            [self::ACCOUNTING_FISCAL_YEARS_REOPEN_PERIOD, 'Reopen accounting periods'],
            [self::ACCOUNTING_VOUCHER_BOOKS_VIEW, 'View voucher books'],
            [self::ACCOUNTING_VOUCHER_BOOKS_CREATE, 'Create voucher books'],
            [self::ACCOUNTING_VOUCHER_BOOKS_UPDATE, 'Update voucher books'],
            [self::ACCOUNTING_VOUCHER_BOOKS_DELETE, 'Delete voucher books'],
            [self::ACCOUNTING_TRANSFERS_VIEW, 'View fund transfers'],
            [self::ACCOUNTING_TRANSFERS_CREATE, 'Create fund transfers'],
            [self::ACCOUNTING_TRANSFERS_UPDATE, 'Update fund transfers'],
            [self::ACCOUNTING_TRANSFERS_DELETE, 'Delete fund transfers'],
            [self::ACCOUNTING_TRANSFERS_POST, 'Post fund transfers'],
            [self::ACCOUNTING_TRANSFERS_CANCEL, 'Cancel fund transfers'],
            [self::ACCOUNTING_CURRENCY_CONVERSIONS_VIEW, 'View currency conversions'],
            [self::ACCOUNTING_CURRENCY_CONVERSIONS_CREATE, 'Create currency conversions'],
            [self::ACCOUNTING_CURRENCY_CONVERSIONS_UPDATE, 'Update currency conversions'],
            [self::ACCOUNTING_CURRENCY_CONVERSIONS_DELETE, 'Delete currency conversions'],
            [self::ACCOUNTING_CURRENCY_CONVERSIONS_POST, 'Post currency conversions'],
            [self::ACCOUNTING_CURRENCY_CONVERSIONS_CANCEL, 'Cancel currency conversions'],
            [self::ACCOUNTING_REPORTS_VIEW, 'View accounting reports'],
            [self::RECEIPTS_VIEW, 'View receipts'],
            [self::RECEIPTS_CREATE, 'Create receipts'],
            [self::RECEIPTS_UPDATE, 'Update receipts'],
            [self::RECEIPTS_POST, 'Post receipts'],
            [self::RECEIPTS_CANCEL, 'Cancel receipts'],
            [self::PAYMENTS_VIEW, 'View payments'],
            [self::PAYMENTS_CREATE, 'Create payments'],
            [self::PAYMENTS_UPDATE, 'Update payments'],
            [self::PAYMENTS_POST, 'Post payments'],
            [self::PAYMENTS_CANCEL, 'Cancel payments'],
            [self::RECEIPTS_PAYMENTS_REPORTS_VIEW, 'View receipts & payments reports'],
            [self::RECEIPTS_PAYMENTS_REPORTS_EXPORT, 'Export receipts & payments reports'],
            [self::RECEIPTS_PAYMENTS_SYNC, 'Sync receipts & payments'],
            [self::REPORTS_VIEW, 'View reports package (legacy)'],
            [self::REPORTS_SALES_PERIOD_VIEW, 'View sales period report'],
            [self::REPORTS_SALES_PERIOD_EXPORT, 'Export sales period report'],
            [self::REPORTS_ACCOUNT_STATEMENT_VIEW, 'View account statement report'],
            [self::REPORTS_ACCOUNT_STATEMENT_EXPORT, 'Export account statement report'],
            [self::REPORTS_TRIAL_BALANCE_VIEW, 'View trial balance report'],
            [self::REPORTS_TRIAL_BALANCE_EXPORT, 'Export trial balance report'],
            [self::REPORTS_JOURNAL_BOOK_VIEW, 'View journal book report'],
            [self::REPORTS_JOURNAL_BOOK_EXPORT, 'Export journal book report'],
            [self::SETTINGS_VIEW, 'View settings'],
            [self::SETTINGS_UPDATE, 'Update settings'],
            [self::SYNC_VIEW, 'View sync status'],
            [self::SYNC_EXECUTE, 'Execute synchronization'],
            [self::DEVICES_VIEW, 'View devices'],
            [self::DEVICES_REVOKE, 'Revoke devices'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function syncEntityPermissions(): array
    {
        return [
            'product|create' => self::INVENTORY_PRODUCTS_CREATE,
            'product|update' => self::INVENTORY_PRODUCTS_UPDATE,
            'product|delete' => self::INVENTORY_PRODUCTS_DELETE,
            'inventory_item|create' => self::INVENTORY_STOCK_COUNT_ADJUST,
            'inventory_item|update' => self::INVENTORY_STOCK_COUNT_ADJUST,
            'inventory_item|delete' => self::INVENTORY_STOCK_COUNT_CLEAR,
            'customer|create' => self::CUSTOMERS_MASTER_CREATE,
            'customer|update' => self::CUSTOMERS_MASTER_UPDATE,
            'customer|delete' => self::CUSTOMERS_MASTER_DELETE,
            'sale|create' => self::SALES_DOCUMENTS_CREATE,
            'sale|update' => self::SALES_DOCUMENTS_UPDATE,
            'sale|delete' => self::SALES_DOCUMENTS_DELETE,
            'account|create' => self::ACCOUNTING_ACCOUNTS_CREATE,
            'account|update' => self::ACCOUNTING_ACCOUNTS_UPDATE,
            'account|delete' => self::ACCOUNTING_ACCOUNTS_DELETE,
            'journal_entry|create' => self::ACCOUNTING_JOURNALS_CREATE,
            'journal_entry|update' => self::ACCOUNTING_JOURNALS_UPDATE,
            'journal_entry|delete' => self::ACCOUNTING_JOURNALS_DELETE,
            'currency_rate|create' => self::ACCOUNTING_CURRENCY_RATES_CREATE,
            'currency_rate|update' => self::ACCOUNTING_CURRENCY_RATES_UPDATE,
            'currency_rate|delete' => self::ACCOUNTING_CURRENCY_RATES_DELETE,
            'fiscal_year|create' => self::ACCOUNTING_FISCAL_YEARS_CREATE,
            'fiscal_year|update' => self::ACCOUNTING_FISCAL_YEARS_UPDATE,
            'fiscal_year|delete' => self::ACCOUNTING_FISCAL_YEARS_UPDATE,
            'financial_transaction|create' => self::RECEIPTS_CREATE,
            'financial_transaction|update' => self::RECEIPTS_UPDATE,
            'financial_transaction|delete' => self::RECEIPTS_CANCEL,
            'company_profile|create' => self::COMPANIES_UPDATE,
            'company_profile|update' => self::COMPANIES_UPDATE,
            'company_profile|delete' => self::COMPANIES_UPDATE,
            'fund_transfer|create' => self::ACCOUNTING_TRANSFERS_CREATE,
            'fund_transfer|update' => self::ACCOUNTING_TRANSFERS_UPDATE,
            'fund_transfer|delete' => self::ACCOUNTING_TRANSFERS_CANCEL,
            'currency_conversion|create' => self::ACCOUNTING_CURRENCY_CONVERSIONS_CREATE,
            'currency_conversion|update' => self::ACCOUNTING_CURRENCY_CONVERSIONS_UPDATE,
            'currency_conversion|delete' => self::ACCOUNTING_CURRENCY_CONVERSIONS_CANCEL,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function permissionAliases(): array
    {
        return [
            self::INVENTORY_STOCK_COUNT_VIEW => [self::INVENTORY_VIEW],
            self::INVENTORY_STOCK_COUNT_ADJUST => [self::INVENTORY_ADJUST, self::INVENTORY_UPDATE],
            self::INVENTORY_STOCK_COUNT_IMPORT => [self::INVENTORY_CREATE, self::INVENTORY_UPDATE],
            self::INVENTORY_STOCK_COUNT_CLEAR => [self::INVENTORY_DELETE],
            self::INVENTORY_PRODUCTS_VIEW => [self::PRODUCTS_VIEW],
            self::INVENTORY_PRODUCTS_CREATE => [self::PRODUCTS_CREATE],
            self::INVENTORY_PRODUCTS_UPDATE => [self::PRODUCTS_UPDATE],
            self::INVENTORY_PRODUCTS_DELETE => [self::PRODUCTS_DELETE],
            self::INVENTORY_PRODUCTS_IMPORT => [self::PRODUCTS_CREATE, self::PRODUCTS_UPDATE],
            self::SALES_DOCUMENTS_VIEW => [self::SALES_VIEW],
            self::SALES_DOCUMENTS_CREATE => [self::SALES_CREATE],
            self::SALES_DOCUMENTS_UPDATE => [self::SALES_UPDATE],
            self::SALES_DOCUMENTS_DELETE => [self::SALES_DELETE],
            self::SALES_DOCUMENTS_POST => [self::SALES_POST],
            self::SALES_DOCUMENTS_CANCEL => [self::SALES_CANCEL],
            self::CUSTOMERS_MASTER_VIEW => [self::CUSTOMERS_VIEW],
            self::CUSTOMERS_MASTER_CREATE => [self::CUSTOMERS_CREATE],
            self::CUSTOMERS_MASTER_UPDATE => [self::CUSTOMERS_UPDATE],
            self::CUSTOMERS_MASTER_DELETE => [self::CUSTOMERS_DELETE],
            self::REPORTS_SALES_PERIOD_VIEW => [self::REPORTS_VIEW],
            self::REPORTS_ACCOUNT_STATEMENT_VIEW => [self::REPORTS_VIEW],
            self::REPORTS_TRIAL_BALANCE_VIEW => [self::REPORTS_VIEW],
            self::REPORTS_JOURNAL_BOOK_VIEW => [self::REPORTS_VIEW],
            self::INVENTORY_VIEW => [self::INVENTORY_STOCK_COUNT_VIEW],
            self::INVENTORY_ADJUST => [self::INVENTORY_STOCK_COUNT_ADJUST],
            self::PRODUCTS_VIEW => [self::INVENTORY_PRODUCTS_VIEW],
            self::PRODUCTS_CREATE => [self::INVENTORY_PRODUCTS_CREATE],
            self::PRODUCTS_UPDATE => [self::INVENTORY_PRODUCTS_UPDATE],
            self::PRODUCTS_DELETE => [self::INVENTORY_PRODUCTS_DELETE],
            self::SALES_VIEW => [self::SALES_DOCUMENTS_VIEW],
            self::SALES_CREATE => [self::SALES_DOCUMENTS_CREATE],
            self::SALES_UPDATE => [self::SALES_DOCUMENTS_UPDATE],
            self::SALES_DELETE => [self::SALES_DOCUMENTS_DELETE],
            self::SALES_POST => [self::SALES_DOCUMENTS_POST],
            self::SALES_CANCEL => [self::SALES_DOCUMENTS_CANCEL],
            self::CUSTOMERS_VIEW => [self::CUSTOMERS_MASTER_VIEW],
            self::CUSTOMERS_CREATE => [self::CUSTOMERS_MASTER_CREATE],
            self::CUSTOMERS_UPDATE => [self::CUSTOMERS_MASTER_UPDATE],
            self::CUSTOMERS_DELETE => [self::CUSTOMERS_MASTER_DELETE],
            self::REPORTS_VIEW => [
                self::REPORTS_SALES_PERIOD_VIEW,
                self::REPORTS_ACCOUNT_STATEMENT_VIEW,
                self::REPORTS_TRIAL_BALANCE_VIEW,
                self::REPORTS_JOURNAL_BOOK_VIEW,
            ],
        ];
    }

    /**
     * @param  array<string, true>|list<string>  $codes
     * @return list<string>
     */
    public static function expandPermissionCodes(array $codes): array
    {
        $expanded = [];
        foreach ($codes as $k => $v) {
            $code = is_int($k) ? (string) $v : (string) $k;
            $expanded[$code] = true;
        }
        $aliases = self::permissionAliases();
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach (array_keys($expanded) as $code) {
                foreach ($aliases[$code] ?? [] as $alias) {
                    if (! isset($expanded[$alias])) {
                        $expanded[$alias] = true;
                        $changed = true;
                    }
                }
            }
        }

        return array_keys($expanded);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function systemRolePermissions(): array
    {
        $all = array_map(fn ($row) => $row[0], self::allPermissions());
        $inventoryFull = [
            self::INVENTORY_VIEW, self::INVENTORY_CREATE, self::INVENTORY_UPDATE, self::INVENTORY_DELETE, self::INVENTORY_ADJUST,
            self::PRODUCTS_VIEW, self::PRODUCTS_CREATE, self::PRODUCTS_UPDATE, self::PRODUCTS_DELETE,
            self::INVENTORY_STOCK_COUNT_VIEW, self::INVENTORY_STOCK_COUNT_ADJUST, self::INVENTORY_STOCK_COUNT_IMPORT,
            self::INVENTORY_STOCK_COUNT_EXPORT, self::INVENTORY_STOCK_COUNT_CLEAR,
            self::INVENTORY_PRODUCTS_VIEW, self::INVENTORY_PRODUCTS_CREATE, self::INVENTORY_PRODUCTS_UPDATE,
            self::INVENTORY_PRODUCTS_DELETE, self::INVENTORY_PRODUCTS_IMPORT, self::INVENTORY_PRODUCTS_BARCODE,
        ];
        $salesFull = [
            self::SALES_VIEW, self::SALES_CREATE, self::SALES_UPDATE, self::SALES_DELETE, self::SALES_POST, self::SALES_CANCEL,
            self::SALES_DOCUMENTS_VIEW, self::SALES_DOCUMENTS_CREATE, self::SALES_DOCUMENTS_UPDATE, self::SALES_DOCUMENTS_DELETE,
            self::SALES_DOCUMENTS_POST, self::SALES_DOCUMENTS_CANCEL, self::SALES_DOCUMENTS_DUPLICATE, self::SALES_DOCUMENTS_EXPORT,
        ];
        $customersFull = [
            self::CUSTOMERS_VIEW, self::CUSTOMERS_CREATE, self::CUSTOMERS_UPDATE, self::CUSTOMERS_DELETE,
            self::CUSTOMERS_MASTER_VIEW, self::CUSTOMERS_MASTER_CREATE, self::CUSTOMERS_MASTER_UPDATE, self::CUSTOMERS_MASTER_DELETE,
            self::CUSTOMERS_MASTER_IMPORT, self::CUSTOMERS_ACCOUNTS_VIEW, self::CUSTOMERS_SETTINGS_VIEW, self::CUSTOMERS_SETTINGS_UPDATE,
        ];
        $accountingFull = [
            self::ACCOUNTING_VIEW,
            self::ACCOUNTING_ACCOUNTS_VIEW, self::ACCOUNTING_ACCOUNTS_CREATE, self::ACCOUNTING_ACCOUNTS_UPDATE, self::ACCOUNTING_ACCOUNTS_DELETE,
            self::ACCOUNTING_JOURNALS_VIEW, self::ACCOUNTING_JOURNALS_CREATE, self::ACCOUNTING_JOURNALS_UPDATE, self::ACCOUNTING_JOURNALS_DELETE,
            self::ACCOUNTING_CURRENCY_RATES_VIEW, self::ACCOUNTING_CURRENCY_RATES_CREATE, self::ACCOUNTING_CURRENCY_RATES_UPDATE, self::ACCOUNTING_CURRENCY_RATES_DELETE,
            self::ACCOUNTING_FISCAL_YEARS_VIEW, self::ACCOUNTING_FISCAL_YEARS_CREATE, self::ACCOUNTING_FISCAL_YEARS_UPDATE,
            self::ACCOUNTING_FISCAL_YEARS_OPEN_PERIOD, self::ACCOUNTING_FISCAL_YEARS_CLOSE_PERIOD, self::ACCOUNTING_FISCAL_YEARS_REOPEN_PERIOD,
            self::ACCOUNTING_VOUCHER_BOOKS_VIEW, self::ACCOUNTING_VOUCHER_BOOKS_CREATE, self::ACCOUNTING_VOUCHER_BOOKS_UPDATE, self::ACCOUNTING_VOUCHER_BOOKS_DELETE,
            self::ACCOUNTING_REPORTS_VIEW,
        ];
        $reportsFull = [
            self::REPORTS_VIEW,
            self::REPORTS_SALES_PERIOD_VIEW, self::REPORTS_SALES_PERIOD_EXPORT,
            self::REPORTS_ACCOUNT_STATEMENT_VIEW, self::REPORTS_ACCOUNT_STATEMENT_EXPORT,
            self::REPORTS_TRIAL_BALANCE_VIEW, self::REPORTS_TRIAL_BALANCE_EXPORT,
            self::REPORTS_JOURNAL_BOOK_VIEW, self::REPORTS_JOURNAL_BOOK_EXPORT,
        ];
        $rpFull = [
            self::RECEIPTS_VIEW, self::RECEIPTS_CREATE, self::RECEIPTS_UPDATE, self::RECEIPTS_POST, self::RECEIPTS_CANCEL,
            self::PAYMENTS_VIEW, self::PAYMENTS_CREATE, self::PAYMENTS_UPDATE, self::PAYMENTS_POST, self::PAYMENTS_CANCEL,
            self::RECEIPTS_PAYMENTS_REPORTS_VIEW, self::RECEIPTS_PAYMENTS_REPORTS_EXPORT, self::RECEIPTS_PAYMENTS_SYNC,
        ];

        return [
            'Super Admin' => $all,
            'Company Admin' => array_values(array_filter($all, fn ($c) => ! str_starts_with($c, 'platform.'))),
            'Accountant' => array_merge(
                [self::COMPANIES_VIEW, self::CUSTOMERS_VIEW, self::CUSTOMERS_MASTER_VIEW, self::CUSTOMERS_ACCOUNTS_VIEW, self::SALES_VIEW, self::SALES_DOCUMENTS_VIEW],
                $accountingFull,
                $rpFull,
                $reportsFull,
                [self::SETTINGS_VIEW, self::SYNC_VIEW, self::SYNC_EXECUTE, self::DEVICES_VIEW],
            ),
            'Sales Manager' => array_merge(
                [self::COMPANIES_VIEW, self::PRODUCTS_VIEW, self::INVENTORY_PRODUCTS_VIEW],
                $customersFull,
                $salesFull,
                [self::ACCOUNTING_JOURNALS_CREATE, self::ACCOUNTING_JOURNALS_UPDATE, self::ACCOUNTING_JOURNALS_DELETE],
                [self::RECEIPTS_VIEW, self::RECEIPTS_CREATE, self::RECEIPTS_UPDATE, self::RECEIPTS_POST, self::RECEIPTS_CANCEL, self::RECEIPTS_PAYMENTS_REPORTS_VIEW],
                [self::REPORTS_VIEW, self::REPORTS_SALES_PERIOD_VIEW, self::REPORTS_SALES_PERIOD_EXPORT, self::SYNC_VIEW, self::SYNC_EXECUTE, self::DEVICES_VIEW],
            ),
            'Sales Employee' => [
                self::COMPANIES_VIEW, self::PRODUCTS_VIEW, self::INVENTORY_PRODUCTS_VIEW,
                self::CUSTOMERS_VIEW, self::CUSTOMERS_MASTER_VIEW, self::CUSTOMERS_MASTER_CREATE, self::CUSTOMERS_CREATE,
                self::SALES_VIEW, self::SALES_CREATE, self::SALES_DOCUMENTS_VIEW, self::SALES_DOCUMENTS_CREATE,
                self::ACCOUNTING_JOURNALS_CREATE, self::ACCOUNTING_JOURNALS_UPDATE, self::ACCOUNTING_JOURNALS_DELETE,
                self::RECEIPTS_VIEW, self::RECEIPTS_CREATE, self::SYNC_VIEW, self::SYNC_EXECUTE, self::DEVICES_VIEW,
            ],
            'Inventory Manager' => array_merge(
                [self::COMPANIES_VIEW],
                $inventoryFull,
                [self::REPORTS_VIEW, self::SYNC_VIEW, self::SYNC_EXECUTE, self::DEVICES_VIEW],
            ),
            'Inventory Employee' => [
                self::COMPANIES_VIEW, self::PRODUCTS_VIEW, self::INVENTORY_PRODUCTS_VIEW, self::INVENTORY_VIEW,
                self::INVENTORY_STOCK_COUNT_VIEW, self::INVENTORY_STOCK_COUNT_ADJUST,
                self::INVENTORY_CREATE, self::INVENTORY_UPDATE, self::INVENTORY_ADJUST,
                self::SYNC_VIEW, self::SYNC_EXECUTE, self::DEVICES_VIEW,
            ],
            'Viewer' => array_merge(
                [
                    self::COMPANIES_VIEW, self::PRODUCTS_VIEW, self::INVENTORY_PRODUCTS_VIEW, self::INVENTORY_VIEW,
                    self::INVENTORY_STOCK_COUNT_VIEW, self::CUSTOMERS_VIEW, self::CUSTOMERS_MASTER_VIEW,
                    self::SALES_VIEW, self::SALES_DOCUMENTS_VIEW, self::ACCOUNTING_VIEW, self::ACCOUNTING_ACCOUNTS_VIEW,
                    self::ACCOUNTING_JOURNALS_VIEW, self::ACCOUNTING_REPORTS_VIEW, self::RECEIPTS_VIEW, self::PAYMENTS_VIEW,
                    self::RECEIPTS_PAYMENTS_REPORTS_VIEW,
                ],
                $reportsFull,
                [self::SETTINGS_VIEW, self::SYNC_VIEW, self::DEVICES_VIEW],
            ),
        ];
    }
}
