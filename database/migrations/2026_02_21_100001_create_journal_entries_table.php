<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('entry_type'); // 'payment_run', 'manual', 'correction', etc.
            $table->string('reference')->nullable(); // Referenz zum PaymentRun, etc.
            $table->text('description');
            $table->decimal('amount', 10, 2);
            
            // Vorbereitet für doppelte Buchführung
            $table->string('debit_account')->nullable(); // z.B. "1200" (Bank)
            $table->string('credit_account')->nullable(); // z.B. "4000" (Mitgliedsbeiträge)
            
            // Konkretes Bankkonto vermerken
            $table->string('bank_reference')->nullable(); // z.B. "Sparkasse-DE89...", "PayPal", etc.
            $table->string('cost_center')->nullable(); // Kostenstelle (optional, für später)
            
            // Für Detailbuchungen (später erweiterbar)
            $table->json('line_items')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index('entry_date');
            $table->index('entry_type');
            $table->index('bank_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
