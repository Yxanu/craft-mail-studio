<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailstudio_brand_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uid')->unique();
            $table->string('name');
            $table->string('handle')->unique();
            $table->json('config');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('mailstudio_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uid')->unique();
            $table->string('name');
            $table->string('handle')->unique();
            $table->text('description')->nullable();
            $table->string('preset_key')->nullable();
            $table->foreignId('brand_profile_id')->nullable()->constrained('mailstudio_brand_profiles')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mailstudio_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('mailstudio_templates')->cascadeOnDelete();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('locale', 32)->nullable();
            $table->string('draft_subject');
            $table->string('draft_preheader')->default('');
            $table->json('draft_document');
            $table->unsignedBigInteger('active_revision_id')->nullable();
            $table->timestamps();
            $table->index(['template_id', 'site_id', 'locale'], 'mailstudio_variant_lookup');
        });

        Schema::create('mailstudio_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('mailstudio_templates')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('mailstudio_variants')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('subject');
            $table->string('preheader')->default('');
            $table->json('document');
            $table->json('brand_snapshot');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['variant_id', 'number']);
        });

        Schema::create('mailstudio_source_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('adapter', 64);
            $table->string('source_key');
            $table->foreignId('template_id')->constrained('mailstudio_templates')->cascadeOnDelete();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('locale', 32)->nullable();
            $table->timestamps();
            $table->index(['adapter', 'source_key', 'site_id', 'locale'], 'mailstudio_source_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailstudio_source_mappings');
        Schema::dropIfExists('mailstudio_revisions');
        Schema::dropIfExists('mailstudio_variants');
        Schema::dropIfExists('mailstudio_templates');
        Schema::dropIfExists('mailstudio_brand_profiles');
    }
};
