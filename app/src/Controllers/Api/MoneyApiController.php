<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Money\MoneyAccount;
use App\Money\MoneyBudget;
use App\Money\MoneyHistory;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Upload;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\MoneyApiController
 *
 */
class MoneyApiController extends ApiController
{
    private static $url_segment = 'api/v1/money';

    private static $allowed_actions = [
        'index',
        'account',
        'accountStore',
        'accountUpdate',
        'accountRemove',
        'budgetStore',
        'budgetUpdate',
        'budgetRemove',
        'entryStore',
        'entryUpdate',
        'entryApprove',
        'entry',
    ];

    private const RECEIPT_MAX_SIZE = 5 * 1024 * 1024;
    private const RECEIPT_ALLOWED_MIMES = ['image/jpeg', 'image/png', 'application/pdf'];
    private const RECEIPT_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

    protected function getDefaultAction()
    {
        return 'index';
    }

    /** GET /api/v1/money */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgIDs = $member->getOrganizationIDs();

        $accounts = [];
        if (!empty($orgIDs)) {
            foreach (MoneyAccount::get()->filter('ParentID', $orgIDs) as $account) {
                if (!$account->canViewInApp($member)) {
                    continue;
                }
                $accounts[] = $this->formatAccount($account, $member, false);
            }
        }

        return $this->jsonResponse(['accounts' => $accounts]);
    }

    /** GET /api/v1/money/account/$ID */
    public function account(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $account = MoneyAccount::get()->byID((int) $request->param('ID'));
        if (!$account || !$account->exists()) {
            return $this->errorResponse('Kasse nicht gefunden', 404);
        }

        if (!$account->canViewInApp($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        return $this->jsonResponse(['account' => $this->formatAccount($account, $member, true)]);
    }

    /** POST /api/v1/money/accountStore */
    public function accountStore(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body = $this->getJsonBody();
        $title = trim($body['Title'] ?? '');
        if (!$title) {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }

        $org = Organization::get()->byID((int) ($body['OrganizationID'] ?? 0));
        if (!$org || !$org->exists()) {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }

        if (!$member->hasOrgPermission($org, OrgPermissions::MONEY_ACCOUNTS_CREATE)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $account = MoneyAccount::create();
        $this->applyAccountFields($account, $body);
        $account->ParentID = $org->ID;
        $account->write();
        $this->recalculateBalances($account);

        return $this->successResponse(['account' => $this->formatAccount($account, $member, true)], 'Kasse erstellt');
    }

    /** PUT /api/v1/money/accountUpdate/$ID */
    public function accountUpdate(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $account = MoneyAccount::get()->byID((int) $request->param('ID'));
        if (!$account || !$account->exists()) {
            return $this->errorResponse('Kasse nicht gefunden', 404);
        }

        if (!$account->canEditInApp($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        $this->applyAccountFields($account, $body, false);
        $account->write();
        $this->recalculateBalances($account);

        return $this->successResponse(['account' => $this->formatAccount($account, $member, true)], 'Kasse aktualisiert');
    }

    /** DELETE /api/v1/money/accountRemove/$ID */
    public function accountRemove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $account = MoneyAccount::get()->byID((int) $request->param('ID'));
        if (!$account || !$account->exists()) {
            return $this->errorResponse('Kasse nicht gefunden', 404);
        }

        if (!$account->canDeleteInApp($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        foreach (MoneyHistory::get()->filter('ParentID', $account->ID) as $entry) {
            $receipt = $entry->Receipt();
            if ($receipt && $receipt->exists()) {
                $receipt->deleteFile();
                $receipt->delete();
            }
            $entry->delete();
        }

        foreach (MoneyBudget::get()->filter('ParentID', $account->ID) as $budget) {
            $budget->delete();
        }

        $account->delete();

        return $this->successResponse([], 'Kasse gelöscht');
    }

    /** POST /api/v1/money/budgetStore */
    public function budgetStore(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body = $this->getJsonBody();
        $title = trim($body['Title'] ?? '');
        if (!$title) {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }

        $account = MoneyAccount::get()->byID((int) ($body['AccountID'] ?? 0));
        if (!$account || !$account->exists()) {
            return $this->errorResponse('Kasse nicht gefunden', 404);
        }

        if (!$account->canManageBudgetsInApp($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $budget = MoneyBudget::create();
        $this->applyBudgetFields($budget, $body);
        $budget->ParentID = $account->ID;
        $budget->write();

        return $this->successResponse(['budget' => $this->formatBudget($budget)], 'Budget erstellt');
    }

    /** PUT /api/v1/money/budgetUpdate/$ID */
    public function budgetUpdate(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $budget = MoneyBudget::get()->byID((int) $request->param('ID'));
        if (!$budget || !$budget->exists()) {
            return $this->errorResponse('Budget nicht gefunden', 404);
        }

        $account = $budget->Parent();
        if (!$account || !$account->exists() || !$account->canManageBudgetsInApp($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        $this->applyBudgetFields($budget, $body, false);
        $budget->write();

        return $this->successResponse(['budget' => $this->formatBudget($budget)], 'Budget aktualisiert');
    }

    /** DELETE /api/v1/money/budgetRemove/$ID */
    public function budgetRemove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $budget = MoneyBudget::get()->byID((int) $request->param('ID'));
        if (!$budget || !$budget->exists()) {
            return $this->errorResponse('Budget nicht gefunden', 404);
        }

        $account = $budget->Parent();
        if (!$account || !$account->exists() || !$account->canManageBudgetsInApp($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        // Buchungen bleiben erhalten, verlieren nur die Budget-Zuordnung
        foreach (MoneyHistory::get()->filter('BudgetID', $budget->ID) as $entry) {
            $entry->BudgetID = 0;
            $entry->write();
        }

        $budget->delete();

        return $this->successResponse([
            'account' => $this->formatAccount($account, $member, true),
        ], 'Budget gelöscht');
    }

    /** POST /api/v1/money/entryStore (multipart/form-data) */
    public function entryStore(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $account = MoneyAccount::get()->byID((int) ($_POST['AccountID'] ?? 0));
        if (!$account || !$account->exists()) {
            return $this->errorResponse('Kasse nicht gefunden', 404);
        }

        $changeType = ($_POST['ChangeType'] ?? '') === 'Deposit' ? 'Deposit' : 'Withdrawal';

        $canEnter = $changeType === 'Deposit'
            ? $account->canEnterDepositInApp($member)
            : $account->canEnterWithdrawalInApp($member);
        if (!$canEnter) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $amount = (float) str_replace(',', '.', (string) ($_POST['ChangeAmount'] ?? '0'));
        if ($amount <= 0) {
            return $this->errorResponse('Der Betrag muss größer als 0 sein', 400);
        }

        $reason = trim($_POST['ChangeReason'] ?? '');
        if (!$reason) {
            return $this->errorResponse('Grund ist erforderlich', 400);
        }

        $changeDate = trim($_POST['ChangeDate'] ?? '') ?: date('Y-m-d H:i:s');

        $budget = null;
        $budgetID = (int) ($_POST['BudgetID'] ?? 0);
        if ($budgetID) {
            $budget = MoneyBudget::get()->byID($budgetID);
            if (!$budget || !$budget->exists() || (int) $budget->ParentID !== $account->ID) {
                return $this->errorResponse('Budget gehört nicht zu dieser Kasse', 400);
            }
            if ($changeType === 'Withdrawal' && $budget->HasBudget && !$budget->CanBeOverBudget) {
                $projected = (float) $budget->CachedCurrentBalance + $amount;
                if ($projected > (float) $budget->Budget) {
                    return $this->errorResponse('Diese Buchung würde das Budget überschreiten', 400);
                }
            }
        }

        $file = $_FILES['receipt'] ?? null;
        $hasFile = $file && $file['error'] === UPLOAD_ERR_OK;

        $receiptRequired = $changeType === 'Deposit' ? $account->RequiresReceiptDeposit : $account->RequiresReceiptWithdrawal;
        if ($receiptRequired && !$hasFile) {
            return $this->errorResponse('Für diese Buchung ist ein Beleg erforderlich', 400);
        }

        $receiptID = 0;
        if ($hasFile) {
            $result = $this->storeReceipt($file, $account, $changeDate);
            if (!$result['success']) {
                return $this->errorResponse($result['error'], 400);
            }
            $receiptID = $result['fileID'];
        }

        $entry = MoneyHistory::create();
        $entry->ChangeReason = $reason;
        $entry->ChangeAmount = $amount;
        $entry->ChangeType = $changeType;
        $entry->ChangeDate = $changeDate;
        $entry->Approved = !$account->RequiresApproval;
        $entry->ParentID = $account->ID;
        $entry->UserID = $member->ID;
        $entry->BudgetID = $budget?->ID ?: 0;
        $entry->ReceiptID = $receiptID;
        $entry->write();

        if ($entry->Approved) {
            $this->recalculateBalances($account);
        }

        return $this->successResponse([
            'entry' => $this->formatEntry($entry),
            'account' => $this->formatAccount($account, $member, true),
        ], 'Buchung erfasst');
    }

    /**
     * POST /api/v1/money/entryUpdate/$ID
     * (POST statt PUT, da PHP multipart-Bodies nur bei POST in $_POST/$_FILES parst —
     * wird für den optionalen Beleg-Austausch benötigt.)
     */
    public function entryUpdate(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $entry = MoneyHistory::get()->byID((int) $request->param('ID'));
        if (!$entry || !$entry->exists()) {
            return $this->errorResponse('Buchung nicht gefunden', 404);
        }

        $account = $entry->Parent();
        if (!$account || !$account->exists()) {
            return $this->errorResponse('Kasse nicht gefunden', 404);
        }

        $isOwner = (int) $entry->UserID === (int) $member->ID;
        $canManage = $account->canApproveEntriesInApp($member);
        if (!$canManage && !($isOwner && !$entry->Approved)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $changeType = ($_POST['ChangeType'] ?? '') === 'Deposit' ? 'Deposit' : 'Withdrawal';

        $amount = (float) str_replace(',', '.', (string) ($_POST['ChangeAmount'] ?? '0'));
        if ($amount <= 0) {
            return $this->errorResponse('Der Betrag muss größer als 0 sein', 400);
        }

        $reason = trim($_POST['ChangeReason'] ?? '');
        if (!$reason) {
            return $this->errorResponse('Grund ist erforderlich', 400);
        }

        $changeDate = trim($_POST['ChangeDate'] ?? '') ?: $entry->ChangeDate;

        $budget = null;
        $budgetID = (int) ($_POST['BudgetID'] ?? 0);
        if ($budgetID) {
            $budget = MoneyBudget::get()->byID($budgetID);
            if (!$budget || !$budget->exists() || (int) $budget->ParentID !== $account->ID) {
                return $this->errorResponse('Budget gehört nicht zu dieser Kasse', 400);
            }
        }

        $file = $_FILES['receipt'] ?? null;
        $hasFile = $file && $file['error'] === UPLOAD_ERR_OK;
        if ($hasFile) {
            $result = $this->storeReceipt($file, $account, $changeDate);
            if (!$result['success']) {
                return $this->errorResponse($result['error'], 400);
            }
            $oldReceipt = $entry->Receipt();
            if ($oldReceipt && $oldReceipt->exists()) {
                $oldReceipt->deleteFile();
                $oldReceipt->delete();
            }
            $entry->ReceiptID = $result['fileID'];
        }

        $wasApproved = (bool) $entry->Approved;

        $entry->ChangeReason = $reason;
        $entry->ChangeAmount = $amount;
        $entry->ChangeType = $changeType;
        $entry->ChangeDate = $changeDate;
        $entry->BudgetID = $budget?->ID ?: 0;
        $entry->write();

        if ($wasApproved) {
            $this->recalculateBalances($account);
        }

        return $this->successResponse([
            'entry' => $this->formatEntry($entry),
            'account' => $this->formatAccount($account, $member, true),
        ], 'Buchung aktualisiert');
    }

    /** PUT /api/v1/money/entryApprove/$ID */
    public function entryApprove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $entry = MoneyHistory::get()->byID((int) $request->param('ID'));
        if (!$entry || !$entry->exists()) {
            return $this->errorResponse('Buchung nicht gefunden', 404);
        }

        $account = $entry->Parent();
        if (!$account || !$account->exists() || !$account->canApproveEntriesInApp($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        $approve = (bool) ($body['approve'] ?? false);

        if ($approve) {
            $entry->Approved = true;
            $entry->write();
            $this->recalculateBalances($account);

            return $this->successResponse([
                'account' => $this->formatAccount($account, $member, true),
            ], 'Buchung freigegeben');
        }

        $receipt = $entry->Receipt();
        $entry->delete();
        if ($receipt && $receipt->exists()) {
            $receipt->deleteFile();
            $receipt->delete();
        }

        return $this->successResponse([
            'account' => $this->formatAccount($account, $member, true),
        ], 'Buchung abgelehnt');
    }

    /** DELETE /api/v1/money/entry/$ID */
    public function entry(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $entry = MoneyHistory::get()->byID((int) $request->param('ID'));
        if (!$entry || !$entry->exists()) {
            return $this->errorResponse('Buchung nicht gefunden', 404);
        }

        $account = $entry->Parent();
        if (!$account || !$account->exists()) {
            return $this->errorResponse('Kasse nicht gefunden', 404);
        }

        $isOwner = (int) $entry->UserID === (int) $member->ID;
        $canManage = $account->canApproveEntriesInApp($member);
        if (!$canManage && !($isOwner && !$entry->Approved)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $wasApproved = (bool) $entry->Approved;
        $receipt = $entry->Receipt();
        $entry->delete();
        if ($receipt && $receipt->exists()) {
            $receipt->deleteFile();
            $receipt->delete();
        }

        if ($wasApproved) {
            $this->recalculateBalances($account);
        }

        return $this->successResponse([
            'account' => $this->formatAccount($account, $member, true),
        ], 'Buchung gelöscht');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function applyAccountFields(MoneyAccount $account, array $body, bool $isCreate = true): void
    {
        if ($isCreate || isset($body['Title'])) {
            $account->Title = trim($body['Title'] ?? $account->Title);
        }
        if (isset($body['IBAN'])) {
            $account->IBAN = trim($body['IBAN']);
        }
        if (isset($body['StartingAmount'])) {
            $account->StartingAmount = (float) $body['StartingAmount'];
        }
        if (isset($body['TargetAmount'])) {
            $account->TargetAmount = (float) $body['TargetAmount'];
        }
        if (isset($body['RequiresApproval'])) {
            $account->RequiresApproval = (bool) $body['RequiresApproval'];
        }
        if (isset($body['RequiresReceiptDeposit'])) {
            $account->RequiresReceiptDeposit = (bool) $body['RequiresReceiptDeposit'];
        }
        if (isset($body['RequiresReceiptWithdrawal'])) {
            $account->RequiresReceiptWithdrawal = (bool) $body['RequiresReceiptWithdrawal'];
        }
    }

    private function applyBudgetFields(MoneyBudget $budget, array $body, bool $isCreate = true): void
    {
        if ($isCreate || isset($body['Title'])) {
            $budget->Title = trim($body['Title'] ?? $budget->Title);
        }
        if (isset($body['Budget'])) {
            $budget->Budget = (float) $body['Budget'];
        }
        if (isset($body['HasBudget'])) {
            $budget->HasBudget = (bool) $body['HasBudget'];
        }
        if (isset($body['CanBeOverBudget'])) {
            $budget->CanBeOverBudget = (bool) $body['CanBeOverBudget'];
        }
    }

    private function recalculateBalances(MoneyAccount $account): void
    {
        $approved = MoneyHistory::get()->filter(['ParentID' => $account->ID, 'Approved' => true]);

        $balance = (float) $account->StartingAmount;
        foreach ($approved as $entry) {
            $balance += $entry->ChangeType === 'Deposit' ? (float) $entry->ChangeAmount : -(float) $entry->ChangeAmount;
        }
        $account->CachedCurrentBalance = $balance;
        $account->write();

        foreach ($account->MoneyBudget() as $budget) {
            $spent = (float) MoneyHistory::get()->filter([
                'BudgetID' => $budget->ID,
                'Approved' => true,
                'ChangeType' => 'Withdrawal',
            ])->sum('ChangeAmount');
            $budget->CachedCurrentBalance = $spent;
            $budget->write();
        }
    }

    private function storeReceipt(array $file, MoneyAccount $account, string $changeDate): array
    {
        if ($file['size'] > self::RECEIPT_MAX_SIZE) {
            return ['success' => false, 'error' => 'Die Datei darf maximal 5 MB groß sein'];
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, self::RECEIPT_ALLOWED_MIMES, true)) {
            return ['success' => false, 'error' => 'Nur PNG, JPEG und PDF sind erlaubt'];
        }

        $year = $changeDate ? date('Y', strtotime($changeDate)) : date('Y');
        $org = $account->Parent();
        $orgTitle = ($org && $org->exists()) ? $org->Title : null;
        $folder = 'Receipts/' . $this->slug($orgTitle) . '/' . $this->slug($account->Title) . '/' . $year;

        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
        $ext = $extMap[$mime] ?? 'jpg';
        $filename = 'beleg-' . date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;

        $receipt = File::create();
        $upload = Upload::create();
        $upload->getValidator()->setAllowedExtensions(self::RECEIPT_ALLOWED_EXTENSIONS);
        $upload->getValidator()->setAllowedMaxFileSize(self::RECEIPT_MAX_SIZE);

        $uploaded = $upload->loadIntoFile([
            'name' => $filename,
            'type' => $mime,
            'tmp_name' => $file['tmp_name'],
            'error' => UPLOAD_ERR_OK,
            'size' => $file['size'],
        ], $receipt, $folder);

        if (!$uploaded) {
            $errors = $upload->getErrors();
            return ['success' => false, 'error' => !empty($errors) ? implode(', ', $errors) : 'Beleg konnte nicht gespeichert werden'];
        }

        $receipt->write();
        $receipt->publishSingle();

        return ['success' => true, 'fileID' => $receipt->ID];
    }

    private function slug(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value);
        $value = trim((string) $value, '-');

        return $value !== '' ? $value : 'Unbenannt';
    }

    private function formatMember(?Member $member): ?array
    {
        if (!$member || !$member->exists()) {
            return null;
        }
        return [
            'ID' => $member->ID,
            'Name' => $member->getDisplayName(),
            'Avatar' => $member->RenderProfileImage(),
        ];
    }

    private function formatBudget(MoneyBudget $budget): array
    {
        return [
            'ID' => $budget->ID,
            'Title' => $budget->Title,
            'Budget' => (float) $budget->Budget,
            'HasBudget' => (bool) $budget->HasBudget,
            'CanBeOverBudget' => (bool) $budget->CanBeOverBudget,
            'Spent' => (float) $budget->CachedCurrentBalance,
            'Remaining' => $budget->HasBudget ? (float) $budget->Budget - (float) $budget->CachedCurrentBalance : null,
        ];
    }

    private function formatEntry(MoneyHistory $entry): array
    {
        $receipt = $entry->Receipt();
        $budget = $entry->Budget();

        return [
            'ID' => $entry->ID,
            'ChangeReason' => $entry->ChangeReason,
            'ChangeAmount' => (float) $entry->ChangeAmount,
            'ChangeType' => $entry->ChangeType,
            'ChangeDate' => $entry->ChangeDate,
            'Approved' => (bool) $entry->Approved,
            'User' => $this->formatMember($entry->User()),
            'ReceiptURL' => ($receipt && $receipt->exists()) ? $receipt->getURL() : null,
            'Budget' => ($budget && $budget->exists()) ? ['ID' => $budget->ID, 'Title' => $budget->Title] : null,
        ];
    }

    private function formatAccount(MoneyAccount $account, Member $member, bool $withDetails): array
    {
        $org = $account->Parent();

        $canApprove = $account->canApproveEntriesInApp($member);

        $data = [
            'ID' => $account->ID,
            'Title' => $account->Title,
            'IBAN' => $account->IBAN,
            'StartingAmount' => (float) $account->StartingAmount,
            'TargetAmount' => (float) $account->TargetAmount,
            'RequiresApproval' => (bool) $account->RequiresApproval,
            'RequiresReceiptDeposit' => (bool) $account->RequiresReceiptDeposit,
            'RequiresReceiptWithdrawal' => (bool) $account->RequiresReceiptWithdrawal,
            'CachedCurrentBalance' => (float) $account->CachedCurrentBalance,
            'Organization' => $org && $org->exists() ? [
                'ID' => $org->ID,
                'Title' => $org->Title,
                'LogoURL' => $org->Logo()->exists() ? $org->Logo()->ScaleWidth(80)->getURL() : null,
            ] : null,
            'Permissions' => [
                'canEnterDeposit' => $account->canEnterDepositInApp($member),
                'canEnterWithdrawal' => $account->canEnterWithdrawalInApp($member),
                'canManageAccount' => $account->canEditInApp($member),
                'canDeleteAccount' => $account->canDeleteInApp($member),
                'canManageBudgets' => $account->canManageBudgetsInApp($member),
                'canApprove' => $canApprove,
            ],
        ];

        if ($canApprove) {
            $data['PendingCount'] = MoneyHistory::get()->filter(['ParentID' => $account->ID, 'Approved' => false])->count();
        }

        if ($withDetails) {
            $budgets = [];
            foreach ($account->MoneyBudget() as $budget) {
                $budgets[] = $this->formatBudget($budget);
            }
            $data['Budgets'] = $budgets;

            $history = [];
            foreach (MoneyHistory::get()->filter('ParentID', $account->ID)->sort('ChangeDate DESC')->limit(50) as $entry) {
                $history[] = $this->formatEntry($entry);
            }
            $data['History'] = $history;

            if ($canApprove) {
                $pending = [];
                foreach (MoneyHistory::get()->filter(['ParentID' => $account->ID, 'Approved' => false])->sort('ChangeDate DESC') as $entry) {
                    $pending[] = $this->formatEntry($entry);
                }
                $data['PendingEntries'] = $pending;
            }
        }

        return $data;
    }
}
