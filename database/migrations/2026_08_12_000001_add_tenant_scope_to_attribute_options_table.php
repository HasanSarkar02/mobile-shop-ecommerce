<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attribute options were previously NOT tenant-scoped (no tenant_id column),
 * the only table in the catalog domain that could leak option rows across
 * tenants. This migration:
 *
 *  1. adds a nullable tenant_id,
 *  2. backfills it from each option's owning AttributeDefinition.tenant_id,
 *  3. deduplicates any exact (attribute_definition_id, value) pairs that would
 *     otherwise violate the new tenant-scoped unique constraint — the lowest-id
 *     option wins and any ProductAttributeValue references are repointed to it,
 *  4. makes tenant_id NOT NULL and adds the FK + tenant-scoped unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_options', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
        });

        $this->backfillTenantIds();
        $this->deduplicateOptions();

        Schema::table('attribute_options', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'attribute_definition_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::table('attribute_options', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'attribute_definition_id', 'value']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }

    private function backfillTenantIds(): void
    {
        $definitions = DB::table('attribute_definitions')
            ->select(['id', 'tenant_id'])
            ->get()
            ->keyBy('id');

        foreach (DB::table('attribute_options')->whereNull('tenant_id')->get(['id', 'attribute_definition_id']) as $option) {
            $tenantId = $definitions->get($option->attribute_definition_id)?->tenant_id;

            if ($tenantId === null) {
                continue;
            }

            DB::table('attribute_options')->where('id', $option->id)->update(['tenant_id' => $tenantId]);
        }
    }

    private function deduplicateOptions(): void
    {
        $rows = DB::table('attribute_options')->orderBy('id')->get(['id', 'attribute_definition_id', 'value', 'tenant_id']);

        foreach ($rows->groupBy(fn ($row) => $row->attribute_definition_id.'|'.$row->value) as $group) {
            if ($group->count() <= 1) {
                continue;
            }

            $retained = $group->sortBy('id')->first();

            foreach ($group->sortBy('id')->slice(1) as $duplicate) {
                // Keep referential integrity: repoint any product attribute values
                // to the retained option before removing the duplicate row.
                DB::table('product_attribute_values')
                    ->where('attribute_option_id', $duplicate->id)
                    ->update(['attribute_option_id' => $retained->id]);

                DB::table('attribute_options')->where('id', $duplicate->id)->delete();
            }
        }
    }
};
