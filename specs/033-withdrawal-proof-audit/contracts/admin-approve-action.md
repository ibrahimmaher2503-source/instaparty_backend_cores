# Contract — Filament Admin Action: Approve Withdrawal

**Resource**: `App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource`
**Action key**: `approve`
**Permission**: `withdrawal.approve`
**Visible when**: `$record->status === WithdrawalStatus::Pending`
**Confirmation**: yes (single confirmation modal — no form fields)

## Form schema

(no fields — Approve does not collect any input)

## Behaviour

1. `auth()->user()->can('withdrawal.approve')` — Filament `visible()` already guards; defense-in-depth check inside the action.
2. Call `app(ApproveWithdrawalAction::class)->execute($record, auth()->user())`.
3. On success: `Notification::make()->title(__('settlement.notifications.withdrawal_approved'))->success()->send();`
4. On `InvalidWithdrawalTransitionException`: `Notification::make()->title(__('settlement.errors.withdrawal_state'))->danger()->body($e->getMessage())->send();`

## Translations (resources/lang/{en,ar}/settlement.php)

| Key | EN | AR |
|---|---|---|
| `notifications.withdrawal_approved` | Withdrawal approved. | تم اعتماد طلب السحب. |
| `errors.withdrawal_state` | Cannot perform this action in the current state. | لا يمكن تنفيذ هذا الإجراء في الحالة الحالية. |

## Postconditions (asserted in Pest)

- `withdrawals.status = 'approved'`
- `withdrawals.approved_at IS NOT NULL`
- `withdrawals.approved_by_admin_id = auth user id`
- `audit_logs` has a new row with `action='withdrawal_approved'`
- `wallet_ledger` row count unchanged
- `event_outbox` (or async event channel) has a new `WithdrawalApproved` payload — fired via `DB::afterCommit`
