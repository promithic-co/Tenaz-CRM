<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONTACT_LIST_UNIQUE = 'cl_tenant_id_id_unique';

    private const TEMPLATE_UNIQUE = 'wt_tenant_id_id_unique';

    private const CAMPAIGN_CONTACT_LIST_FOREIGN = 'camp_tenant_contact_list_fk';

    private const CAMPAIGN_TEMPLATE_FOREIGN = 'camp_tenant_template_fk';

    public function up(): void
    {
        $this->assertCampaignReferencesAreTenantConsistent();

        if (! $this->hasUniqueIndex(
            'contact_lists',
            self::CONTACT_LIST_UNIQUE,
            ['tenant_id', 'id'],
        )) {
            Schema::table('contact_lists', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'id'], self::CONTACT_LIST_UNIQUE);
            });
        }

        if (! $this->hasUniqueIndex(
            'whatsapp_templates',
            self::TEMPLATE_UNIQUE,
            ['tenant_id', 'id'],
        )) {
            Schema::table('whatsapp_templates', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'id'], self::TEMPLATE_UNIQUE);
            });
        }

        if (! $this->hasCampaignForeignKey(
            self::CAMPAIGN_CONTACT_LIST_FOREIGN,
            ['tenant_id', 'contact_list_id'],
            'contact_lists',
            ['tenant_id', 'id'],
        )) {
            Schema::table('campaigns', function (Blueprint $table): void {
                $table->foreign(
                    ['tenant_id', 'contact_list_id'],
                    self::CAMPAIGN_CONTACT_LIST_FOREIGN,
                )->references(['tenant_id', 'id'])
                    ->on('contact_lists')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->hasCampaignForeignKey(
            self::CAMPAIGN_TEMPLATE_FOREIGN,
            ['tenant_id', 'whatsapp_template_id'],
            'whatsapp_templates',
            ['tenant_id', 'id'],
        )) {
            Schema::table('campaigns', function (Blueprint $table): void {
                $table->foreign(
                    ['tenant_id', 'whatsapp_template_id'],
                    self::CAMPAIGN_TEMPLATE_FOREIGN,
                )->references(['tenant_id', 'id'])
                    ->on('whatsapp_templates')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if ($this->hasCampaignForeignKey(
            self::CAMPAIGN_CONTACT_LIST_FOREIGN,
            ['tenant_id', 'contact_list_id'],
            'contact_lists',
            ['tenant_id', 'id'],
        )) {
            $this->dropCampaignForeignKey(
                self::CAMPAIGN_CONTACT_LIST_FOREIGN,
                ['tenant_id', 'contact_list_id'],
            );
        }

        if ($this->hasCampaignForeignKey(
            self::CAMPAIGN_TEMPLATE_FOREIGN,
            ['tenant_id', 'whatsapp_template_id'],
            'whatsapp_templates',
            ['tenant_id', 'id'],
        )) {
            $this->dropCampaignForeignKey(
                self::CAMPAIGN_TEMPLATE_FOREIGN,
                ['tenant_id', 'whatsapp_template_id'],
            );
        }

        if ($this->hasUniqueIndex(
            'contact_lists',
            self::CONTACT_LIST_UNIQUE,
            ['tenant_id', 'id'],
        )) {
            Schema::table('contact_lists', function (Blueprint $table): void {
                $table->dropUnique(self::CONTACT_LIST_UNIQUE);
            });
        }

        if ($this->hasUniqueIndex(
            'whatsapp_templates',
            self::TEMPLATE_UNIQUE,
            ['tenant_id', 'id'],
        )) {
            Schema::table('whatsapp_templates', function (Blueprint $table): void {
                $table->dropUnique(self::TEMPLATE_UNIQUE);
            });
        }
    }

    /** @param list<string> $columns */
    private function hasUniqueIndex(string $table, string $name, array $columns): bool
    {
        if (! Schema::hasIndex($table, $name, 'unique')) {
            return false;
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (
                $index['name'] === $name
                && $index['columns'] === $columns
                && $index['unique']
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * SQLite does not expose foreign-key names, so its exact structural signature is used.
     *
     * @param  list<string>  $columns
     * @param  list<string>  $foreignColumns
     */
    private function hasCampaignForeignKey(
        string $name,
        array $columns,
        string $foreignTable,
        array $foreignColumns,
    ): bool {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        foreach (Schema::getForeignKeys('campaigns') as $foreignKey) {
            if (! $isSqlite && $foreignKey['name'] !== $name) {
                continue;
            }

            if (
                $foreignKey['columns'] === $columns
                && $foreignKey['foreign_table'] === $foreignTable
                && $foreignKey['foreign_columns'] === $foreignColumns
                && $foreignKey['on_delete'] === 'cascade'
            ) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $columns */
    private function dropCampaignForeignKey(string $name, array $columns): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::table('campaigns', function (Blueprint $table) use ($isSqlite, $name, $columns): void {
            if ($isSqlite) {
                $table->dropForeign($columns);

                return;
            }

            $table->dropForeign($name);
        });
    }

    private function assertCampaignReferencesAreTenantConsistent(): void
    {
        $invalidContactList = DB::table('campaigns as campaign')
            ->leftJoin('contact_lists as contact_list', 'contact_list.id', '=', 'campaign.contact_list_id')
            ->where(function ($query): void {
                $query->whereNull('contact_list.id')
                    ->orWhereColumn('campaign.tenant_id', '<>', 'contact_list.tenant_id');
            })
            ->select([
                'campaign.id as campaign_id',
                'campaign.tenant_id as campaign_tenant_id',
                'campaign.contact_list_id',
                'contact_list.tenant_id as contact_list_tenant_id',
            ])
            ->first();

        if ($invalidContactList !== null) {
            throw new RuntimeException(sprintf(
                'Campaign %s references contact list %s outside tenant %s (list tenant: %s).',
                $invalidContactList->campaign_id,
                $invalidContactList->contact_list_id,
                $invalidContactList->campaign_tenant_id,
                $invalidContactList->contact_list_tenant_id ?? 'missing',
            ));
        }

        $invalidTemplate = DB::table('campaigns as campaign')
            ->leftJoin('whatsapp_templates as template', 'template.id', '=', 'campaign.whatsapp_template_id')
            ->where(function ($query): void {
                $query->whereNull('template.id')
                    ->orWhereColumn('campaign.tenant_id', '<>', 'template.tenant_id');
            })
            ->select([
                'campaign.id as campaign_id',
                'campaign.tenant_id as campaign_tenant_id',
                'campaign.whatsapp_template_id',
                'template.tenant_id as template_tenant_id',
            ])
            ->first();

        if ($invalidTemplate !== null) {
            throw new RuntimeException(sprintf(
                'Campaign %s references WhatsApp template %s outside tenant %s (template tenant: %s).',
                $invalidTemplate->campaign_id,
                $invalidTemplate->whatsapp_template_id,
                $invalidTemplate->campaign_tenant_id,
                $invalidTemplate->template_tenant_id ?? 'missing',
            ));
        }
    }
};
