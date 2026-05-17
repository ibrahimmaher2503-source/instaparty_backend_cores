# Contract — Filament Admin Action: Mark Withdrawal Paid

**Resource**: `App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource`
**Action key**: `markPaid`
**Permission**: `withdrawal.mark_paid`
**Visible when**: `$record->status === WithdrawalStatus::Approved`
**Confirmation**: implicit via form-submission step (no extra confirmation modal)

## Form schema

```php
[
    TextInput::make('bank_transfer_reference')
        ->label(__('settlement.fields.bank_transfer_reference'))
        ->required()
        ->maxLength(120)
        ->rule(function (Get $get, Withdrawal $record) {
            return Rule::unique('withdrawals', 'bank_transfer_reference')
                ->where(fn ($query) => $query->where('vendor_profile_id', $record->vendor_profile_id))
                ->ignore($record->id);
        }),

    FileUpload::make('proof_file')
        ->label(__('settlement.fields.transfer_proof'))
        ->required()
        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
        ->maxSize(10240)
        ->disk('s3_private')
        ->directory('withdrawal-proofs')
        ->visibility('private'),

    Tabs::make('payment_note')
        ->tabs([
            Tabs\Tab::make(__('common.locale.en'))
                ->schema([
                    Textarea::make('admin_payment_note_en')
                        ->label(__('settlement.fields.payment_note_en'))
                        ->maxLength(1000)
                        ->rows(3),
                ]),
            Tabs\Tab::make(__('common.locale.ar'))
                ->schema([
                    Textarea::make('admin_payment_note_ar')
                        ->label(__('settlement.fields.payment_note_ar'))
                        ->maxLength(1000)
                        ->rows(3),
                ]),
        ]),
]
```

## Behaviour

1. Validate form (Filament + Laravel rules).
2. Build `MarkWithdrawalPaidInput` from form data:
    - `bankTransferReference = $data['bank_transfer_reference']`
    - `proofFile = UploadedFile` reconstructed from the Filament FileUpload path (same pattern as the current `ApproveAndMarkWithdrawalPaidAction` call site, lines 121–131)
    - `paymentNote = array_filter(['en' => $data['admin_payment_note_en'] ?? null, 'ar' => $data['admin_payment_note_ar'] ?? null])`
3. Call `app(MarkWithdrawalPaidAction::class)->execute($record, $input, auth()->user())`.
4. On success: `Notification::make()->title(__('settlement.notifications.withdrawal_paid'))->success()->send();` + redirect to detail page.
5. On any exception: `Notification::make()->title(__('settlement.errors.mark_paid_failed'))->danger()->body($e->getMessage())->send();` — status remains `approved`.

## Validation error responses

The form-level errors render inline in Filament; the underlying Action throws on the same conditions for the future API:

| Condition | Exception | UI message key |
|---|---|---|
| status ≠ approved | `InvalidWithdrawalTransitionException` | `settlement.errors.withdrawal_state` |
| `bank_transfer_reference` empty | (Filament form validation) | `settlement.validation.bank_transfer_reference_required` |
| `bank_transfer_reference` not unique per vendor | (Laravel Rule::unique + DB UNIQUE backstop) | `settlement.validation.bank_transfer_reference_duplicate` |
| `proof_file` missing | (Filament form validation) | `settlement.validation.proof_required` |
| `proof_file` wrong MIME or > 10 MB | (FileUpload built-in) | `settlement.validation.proof_format` |
| Wallet lock acquisition timeout (Phase 4.9) | `LockAcquisitionTimeoutException` | `settlement.errors.wallet_locked_retry` |

## Translations (resources/lang/{en,ar}/settlement.php)

| Key | EN | AR |
|---|---|---|
| `notifications.withdrawal_paid` | Withdrawal marked as paid. | تم تسجيل صرف طلب السحب. |
| `fields.bank_transfer_reference` | Bank transfer reference | رقم إشعار التحويل البنكي |
| `fields.transfer_proof` | Transfer proof | إثبات التحويل |
| `fields.payment_note_en` | Payment note (English) | ملاحظة الدفع (إنجليزي) |
| `fields.payment_note_ar` | Payment note (Arabic) | ملاحظة الدفع (عربي) |
| `validation.bank_transfer_reference_required` | Bank transfer reference is required. | رقم إشعار التحويل البنكي مطلوب. |
| `validation.bank_transfer_reference_duplicate` | This reference has already been used for this vendor. | هذا الرقم مستخدم من قبل لهذا المورد. |
| `validation.proof_required` | Transfer proof file is required. | ملف إثبات التحويل مطلوب. |
| `validation.proof_format` | Proof must be PDF, JPEG, or PNG and at most 10 MB. | يجب أن يكون الإثبات بصيغة PDF أو JPEG أو PNG وبحجم أقصى 10 ميجا. |
| `errors.wallet_locked_retry` | Wallet is currently locked — please retry in a few seconds. | المحفظة محجوزة حاليًا — برجاء المحاولة بعد ثوانٍ. |
| `errors.mark_paid_failed` | Could not mark as paid. | تعذر تسجيل العملية كمدفوعة. |

## Postconditions (asserted in Pest)

- `withdrawals.status = 'paid'`
- `withdrawals.paid_at IS NOT NULL`
- `withdrawals.paid_by_admin_id = auth user id`
- `withdrawals.bank_transfer_reference = submitted value`
- `withdrawals.admin_payment_note` JSON contains submitted locales
- `withdrawals.bank_proof_media_id` references a `media` row in the `bank_proof` collection
- `withdrawals.settled_ledger_entry_id` references a new `wallet_ledger` row
- `wallet_ledger` group with `transaction_groups.kind = 'withdrawal_settle'` exists with balanced debit/credit on the configured suspense accounts
- `audit_logs` has a new row with `action='withdrawal_paid'`
- `event_outbox` (or async event channel) has a new `WithdrawalPaid` payload — fired via `DB::afterCommit`
- Replaying the action with the same `wd_settle:{withdrawal_id}` idempotency key returns the same response and does NOT create a second ledger group / media row / audit row
