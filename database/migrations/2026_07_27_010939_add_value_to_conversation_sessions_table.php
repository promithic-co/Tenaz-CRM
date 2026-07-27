<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money and a forecast date on the atendimento.
 *
 * The value belongs to the cycle, not to the lead: a returning customer opens a new
 * ConversationSession, and each cycle is negotiated — and won or lost — on its own. Storing
 * it on the lead would overwrite the previous deal's number every time someone came back.
 *
 * Stored in cents as an integer, never a float: 0.10 has no exact binary representation and
 * the error accumulates once column sums feed the dashboard. `unsignedBigInteger` because a
 * negative amount is a data error, not a discount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('value_cents')->nullable()->after('outcome');
            $table->date('expected_close_at')->nullable()->after('value_cents');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_sessions', function (Blueprint $table): void {
            $table->dropColumn(['value_cents', 'expected_close_at']);
        });
    }
};
