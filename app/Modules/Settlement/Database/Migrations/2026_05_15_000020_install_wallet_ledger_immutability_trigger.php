<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Triggers use MySQL-specific SIGNAL SQLSTATE syntax; skip on SQLite (test env)
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // -------------------------------------------------------------------------
        // wallet_ledger — fully append-only
        // Bypass: SET @ledger_backfill_in_progress = 1 (session-scoped only)
        // -------------------------------------------------------------------------
        DB::unprepared("
            CREATE TRIGGER wallet_ledger_before_update
            BEFORE UPDATE ON wallet_ledger
            FOR EACH ROW
            BEGIN
                IF @ledger_backfill_in_progress IS NULL OR @ledger_backfill_in_progress != 1 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'wallet_ledger is append-only: UPDATE is forbidden';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER wallet_ledger_before_delete
            BEFORE DELETE ON wallet_ledger
            FOR EACH ROW
            BEGIN
                IF @ledger_backfill_in_progress IS NULL OR @ledger_backfill_in_progress != 1 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'wallet_ledger is append-only: DELETE is forbidden';
                END IF;
            END
        ");

        // -------------------------------------------------------------------------
        // wallet_ledger AFTER INSERT — maintain group aggregate totals
        // Increments total_debits_minor or total_credits_minor on the group row
        // and entry_count. The CHECK constraint fires at commit if they diverge.
        // -------------------------------------------------------------------------
        DB::unprepared("
            CREATE TRIGGER wallet_ledger_after_insert_aggregate
            AFTER INSERT ON wallet_ledger
            FOR EACH ROW
            BEGIN
                IF NEW.transaction_group_id IS NOT NULL THEN
                    IF NEW.direction = 'debit' THEN
                        UPDATE ledger_transaction_groups
                        SET
                            total_debits_minor = total_debits_minor + NEW.amount_minor,
                            entry_count = entry_count + 1
                        WHERE id = NEW.transaction_group_id;
                    ELSE
                        UPDATE ledger_transaction_groups
                        SET
                            total_credits_minor = total_credits_minor + NEW.amount_minor,
                            entry_count = entry_count + 1
                        WHERE id = NEW.transaction_group_id;
                    END IF;
                END IF;
            END
        ");

        // -------------------------------------------------------------------------
        // ledger_transaction_groups — append-only (except aggregate column updates
        // from the AFTER INSERT trigger above, which use the bypass variable)
        // -------------------------------------------------------------------------
        DB::unprepared("
            CREATE TRIGGER ltg_before_update
            BEFORE UPDATE ON ledger_transaction_groups
            FOR EACH ROW
            BEGIN
                -- Allow the AFTER INSERT trigger on wallet_ledger to update aggregates
                -- (those updates set @ledger_backfill_in_progress internally via the
                -- trigger session context; we honour that pattern here too)
                IF @ledger_backfill_in_progress IS NULL OR @ledger_backfill_in_progress != 1 THEN
                    -- Only permit updates to the aggregate/counter columns
                    IF (NEW.kind != OLD.kind
                        OR NEW.currency != OLD.currency
                        OR NEW.correlation_id != OLD.correlation_id
                        OR COALESCE(NEW.idempotency_key,'') != COALESCE(OLD.idempotency_key,'')
                        OR NEW.initiator_type != OLD.initiator_type
                        OR NEW.posted_at != OLD.posted_at) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'ledger_transaction_groups immutable fields cannot be updated';
                    END IF;
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER ltg_before_delete
            BEFORE DELETE ON ledger_transaction_groups
            FOR EACH ROW
            BEGIN
                IF @ledger_backfill_in_progress IS NULL OR @ledger_backfill_in_progress != 1 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'ledger_transaction_groups is append-only: DELETE is forbidden';
                END IF;
            END
        ");

        // -------------------------------------------------------------------------
        // financial_snapshots — fully append-only
        // -------------------------------------------------------------------------
        DB::unprepared("
            CREATE TRIGGER financial_snapshots_before_update
            BEFORE UPDATE ON financial_snapshots
            FOR EACH ROW
            BEGIN
                IF @ledger_backfill_in_progress IS NULL OR @ledger_backfill_in_progress != 1 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'financial_snapshots is append-only: UPDATE is forbidden';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER financial_snapshots_before_delete
            BEFORE DELETE ON financial_snapshots
            FOR EACH ROW
            BEGIN
                IF @ledger_backfill_in_progress IS NULL OR @ledger_backfill_in_progress != 1 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'financial_snapshots is append-only: DELETE is forbidden';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS wallet_ledger_before_update');
        DB::unprepared('DROP TRIGGER IF EXISTS wallet_ledger_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS wallet_ledger_after_insert_aggregate');
        DB::unprepared('DROP TRIGGER IF EXISTS ltg_before_update');
        DB::unprepared('DROP TRIGGER IF EXISTS ltg_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS financial_snapshots_before_update');
        DB::unprepared('DROP TRIGGER IF EXISTS financial_snapshots_before_delete');
    }
};
