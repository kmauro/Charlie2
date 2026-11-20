<?php

namespace App\Http\Controllers\Domains\Billing\Controllers;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Billing\Models\FinancialAccount;
use App\Domains\Billing\Requests\ImportFinancialStatementRequest;
use App\Domains\Billing\Services\FinancialStatementImportService;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class FinancialStatementImportController extends Controller
{
    public function __construct(private FinancialStatementImportService $imports, private UserScopeService $scope, private AuditService $audit) {}
    public function store(ImportFinancialStatementRequest $request, FinancialAccount $financialAccount): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->scope->canAccessLegalEntity($actor, (int) $financialAccount->legal_entity_id), 403);
        $import = $this->imports->import($financialAccount, $request->file('file'), $actor);
        $this->audit->record('accounting.statement_imported', $import, actor: $actor, context: AuditContext::fromRequest($request));
        return back()->with('success', 'El extracto fue importado sin duplicar movimientos.');
    }
}
