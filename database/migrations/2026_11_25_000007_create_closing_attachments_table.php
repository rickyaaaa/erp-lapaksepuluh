<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_attachments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('daily_closing_id');
            $table->string('type', 30);
            $table->string('file_path', 255);
            $table->unsignedBigInteger('uploaded_by');
            $table->timestampTz('uploaded_at');
            $table->timestampsTz();

            $table->foreign('daily_closing_id')->references('id')->on('daily_closings')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['daily_closing_id', 'type'], 'closing_attachments_closing_type_index');
        });

        if (DB::getDriverName() === 'pgsql') { DB::statement("ALTER TABLE closing_attachments ADD CONSTRAINT closing_attachments_type_check CHECK (type IN ('bukti_tunai', 'bukti_qris_settlement', 'logbook', 'lain'))"); }
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_attachments');
    }
};
